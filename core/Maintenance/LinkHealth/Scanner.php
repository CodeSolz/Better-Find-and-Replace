<?php namespace RealTimeAutoFindReplace\Maintenance\LinkHealth;

use RealTimeAutoFindReplace\Maintenance\Data\IssueRepository;
use RealTimeAutoFindReplace\Maintenance\MediaHealth\Classifier;
use RealTimeAutoFindReplace\Maintenance\Data\ScanRunRepository;
use RealTimeAutoFindReplace\Maintenance\Data\Schema\Tables;
use RealTimeAutoFindReplace\Maintenance\Queue\JobRepository;
use RealTimeAutoFindReplace\Maintenance\Queue\Runner;
use RealTimeAutoFindReplace\Maintenance\Support\Logger;

/**
 * Walks the site's content looking for internal links that go nowhere.
 *
 * A queue job, never a loop on an admin request. One batch of posts per job,
 * cursor persisted after every batch, next batch enqueued under a deterministic
 * key - so a killed PHP process, a cron that never fires, or a browser tab that
 * gets closed costs one batch rather than the run.
 *
 * Deliberately does NOT skip posts whose content is unchanged. A link's health
 * does not depend only on the post that contains it: the page it points at can
 * be trashed tomorrow while this post is never touched again. Caching on a
 * content hash would mean the scanner could never find that, which is most of
 * what it exists to find. Extraction is regex over a string and costs almost
 * nothing; resolution, which is the expensive half, is memoised per URL across
 * the whole batch instead.
 *
 * @package Maintenance
 * @since 1.10.0
 * @author M.Tuhin <info@codesolz.net>
 */

if ( ! defined( 'CS_RTAFAR_VERSION' ) ) {
	exit;
}

// Table names come from Schema\Tables - prefix plus a literal, never request
// data - while every value is still a placeholder. See 04 §6.
// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery
// phpcs:disable WordPress.DB.DirectDatabaseQuery.NoCaching

class Scanner {

	/** The queue job type this scanner runs under. */
	const JOB_TYPE = 'link_scan';

	/** The scan type recorded on the run. */
	const SCAN_TYPE = 'link_health';

	/** The issue type written. */
	const ISSUE_TYPE = 'broken_link';

	/** Posts per batch, before filtering. */
	const DEFAULT_BATCH = 25;

	/**
	 * Register the job handler.
	 *
	 * @param array $handlers Existing handlers.
	 * @return array
	 */
	public static function register_handler( $handlers ) {
		if ( ! is_array( $handlers ) ) {
			$handlers = array();
		}

		$handlers[ self::JOB_TYPE ] = array( __CLASS__, 'handle' );

		return $handlers;
	}

	/**
	 * Begin a scan.
	 *
	 * Refuses to start a second run while one is alive, because two runs would
	 * fight over the same issue rows with different scan ids and each would
	 * close the other's findings.
	 *
	 * @param array $args {
	 *     @type string $scope     'full' or 'post'.
	 *     @type int    $post_id   Single post to scan, when scope is 'post'.
	 * }
	 * @return array array( 'started' => bool, 'scan_id' => int, 'reason' => string )
	 */
	public static function start( array $args = array() ) {
		if ( ! Tables::installed() ) {
			return array(
				'started' => false,
				'scan_id' => 0,
				'reason'  => 'schema_missing',
			);
		}

		// A run whose worker died must not block the next one forever.
		ScanRunRepository::reclaim_stale();

		$active = ScanRunRepository::active( self::SCAN_TYPE );

		if ( $active ) {
			return array(
				'started' => false,
				'scan_id' => (int) $active->id,
				'reason'  => 'already_running',
			);
		}

		$scope   = isset( $args['scope'] ) ? (string) $args['scope'] : 'full';
		$post_id = isset( $args['post_id'] ) ? (int) $args['post_id'] : 0;

		$scan_id = ScanRunRepository::start(
			self::SCAN_TYPE,
			array(
				'scope'       => $scope,
				'total_items' => 'post' === $scope ? 1 : self::count_targets(),
				'metadata'    => array( 'post_id' => $post_id ),
			)
		);

		if ( $scan_id <= 0 ) {
			return array(
				'started' => false,
				'scan_id' => 0,
				'reason'  => 'run_not_created',
			);
		}

		self::enqueue_batch( $scan_id, 0, $post_id );
		Runner::schedule( 0 );

		Logger::log(
			'link_scan.started',
			array(
				'scan_id' => $scan_id,
				'scope'   => $scope,
			)
		);

		return array(
			'started' => true,
			'scan_id' => $scan_id,
			'reason'  => '',
		);
	}

	/**
	 * Run one batch. The queue calls this.
	 *
	 * @param array $payload Job payload.
	 * @return void
	 */
	public static function handle( array $payload ) {
		$scan_id = isset( $payload['scan_id'] ) ? (int) $payload['scan_id'] : 0;
		$cursor  = isset( $payload['cursor'] ) ? (int) $payload['cursor'] : 0;
		$post_id = isset( $payload['post_id'] ) ? (int) $payload['post_id'] : 0;

		if ( $scan_id <= 0 ) {
			return;
		}

		$run = ScanRunRepository::get( $scan_id );

		// Cancelled or already finished: a late job must not restart it.
		if ( ! $run || ScanRunRepository::STATUS_RUNNING !== $run->status ) {
			return;
		}

		// Answers can change between batches - a post trashed an hour into the
		// run must not be reported using what was true when the run began.
		InternalResolver::flush();

		$posts = $post_id > 0
			? array_filter( array( get_post( $post_id ) ) )
			: self::batch( $cursor, self::batch_size() );

		if ( empty( $posts ) ) {
			ScanRunRepository::complete( $scan_id );
			Logger::log( 'link_scan.completed', array( 'scan_id' => $scan_id ) );

			return;
		}

		$found = 0;
		$last  = $cursor;

		foreach ( $posts as $post ) {
			$found += self::scan_post( $post, $scan_id );
			$last   = (int) $post->ID;
		}

		ScanRunRepository::advance( $scan_id, (string) $last, count( $posts ), $found );

		if ( $post_id > 0 ) {
			ScanRunRepository::complete( $scan_id );

			return;
		}

		// Deterministic key: a retried job enqueues the same next batch rather
		// than a second copy of it.
		self::enqueue_batch( $scan_id, $last, 0 );
	}

	/**
	 * Scan one post and record what it finds.
	 *
	 * @param object $post    Post object.
	 * @param int    $scan_id Scan run id.
	 * @return int Issues written or refreshed.
	 */
	public static function scan_post( $post, $scan_id ) {
		if ( ! isset( $post->ID, $post->post_content ) ) {
			return 0;
		}

		$post_id     = (int) $post->ID;
		$site_host   = InternalResolver::site_host();
		$occurrences = Extractor::extract( $post->post_content );
		$groups      = Extractor::group( $occurrences );
		$written     = 0;
		$permalink   = function_exists( 'get_permalink' ) ? (string) get_permalink( $post_id ) : '';
		$on_front    = self::is_front_page( $post_id );

		foreach ( $groups as $url => $group ) {
			$resolution = InternalResolver::resolve( $url, $site_host, array( 'type' => $group['type'] ) );

			if ( ! in_array( $resolution['status'], InternalResolver::problem_statuses(), true ) ) {
				continue;
			}

			// M11: an <img src> that does not resolve is a missing picture, not a
			// broken link. The classifier decides in one place so pro and a site
			// owner change the answer the same way.
			$issue_type = Classifier::classify( $group );

			$result = IssueRepository::upsert(
				array(
					'type'        => $issue_type,
					'subtype'     => $resolution['status'],
					'object_type' => 'post',
					'object_id'   => $post_id,
					'source_url'  => $permalink,
					'target_url'  => $url,
					'target_hash' => \RealTimeAutoFindReplace\Maintenance\Support\UrlNormalizer::hash( $url, $site_host ),
					'occurrences' => $group['count'],
					'severity'    => self::severity( $resolution['status'], $issue_type ),
					'scan_id'     => $scan_id,
					'priority'    => array(
						'is_internal' => true,
						'on_front'    => $on_front,
					),
					'metadata'    => array(
						'reason'       => $resolution['reason'],
						'link_type'    => $group['type'],
						'anchors'      => array_slice( $group['anchors'], 0, 5 ),
						'first_offset' => $group['first_offset'],
						'target_id'    => $resolution['object_id'],
						'post_title'   => isset( $post->post_title ) ? (string) $post->post_title : '',
					),
				)
			);

			if ( $result['ok'] ) {
				++$written;
			}
		}

		// Anything this post used to have and no longer does is fixed. Ignored
		// issues are left alone - that decision outlives the problem.
		//
		// Once per type this scan can write, never once for a constant: a scan
		// that writes two types and closes one leaves the other open forever, so
		// a picture somebody fixed would sit on the list until they ignored it.
		foreach ( Classifier::issue_types() as $issue_type ) {
			IssueRepository::resolve_missing( $issue_type, 'post', $post_id, $scan_id );
		}

		return $written;
	}

	/**
	 * The next batch of posts to look at.
	 *
	 * @param int $after Last post id processed.
	 * @param int $limit Batch size.
	 * @return array
	 */
	private static function batch( $after, $limit ) {
		global $wpdb;

		$types    = self::target_types();
		$statuses = self::target_statuses();

		if ( empty( $types ) || empty( $statuses ) ) {
			return array();
		}

		$type_slots   = implode( ',', array_fill( 0, count( $types ), '%s' ) );
		$status_slots = implode( ',', array_fill( 0, count( $statuses ), '%s' ) );

		$args = array_merge( $types, $statuses, array( (int) $after, (int) $limit ) );

		$sql = "SELECT ID, post_title, post_content
			FROM {$wpdb->posts}
			WHERE post_type IN ({$type_slots})
			  AND post_status IN ({$status_slots})
			  AND ID > %d
			ORDER BY ID ASC
			LIMIT %d";

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		return (array) $wpdb->get_results( $wpdb->prepare( $sql, $args ) );
	}

	/**
	 * How many posts this scan will look at.
	 *
	 * @return int
	 */
	private static function count_targets() {
		global $wpdb;

		$types    = self::target_types();
		$statuses = self::target_statuses();

		if ( empty( $types ) || empty( $statuses ) ) {
			return 0;
		}

		$type_slots   = implode( ',', array_fill( 0, count( $types ), '%s' ) );
		$status_slots = implode( ',', array_fill( 0, count( $statuses ), '%s' ) );

		$sql = "SELECT COUNT(*) FROM {$wpdb->posts}
			WHERE post_type IN ({$type_slots}) AND post_status IN ({$status_slots})";

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		return (int) $wpdb->get_var( $wpdb->prepare( $sql, array_merge( $types, $statuses ) ) );
	}

	/**
	 * Put the next batch on the queue.
	 *
	 * @param int $scan_id Scan run id.
	 * @param int $cursor  Cursor to resume from.
	 * @param int $post_id Single post id, for a one-post scan.
	 * @return void
	 */
	private static function enqueue_batch( $scan_id, $cursor, $post_id = 0 ) {
		JobRepository::enqueue(
			self::JOB_TYPE,
			array(
				'scan_id' => (int) $scan_id,
				'cursor'  => (int) $cursor,
				'post_id' => (int) $post_id,
			)
		);
	}

	/**
	 * Post types worth scanning.
	 *
	 * @return array
	 */
	public static function target_types() {
		$types = function_exists( 'get_post_types' )
			? array_values( get_post_types( array( 'public' => true ), 'names' ) )
			: array( 'post', 'page' );

		// Attachments hold no editable content of their own.
		$types = array_diff( $types, array( 'attachment' ) );

		if ( function_exists( 'apply_filters' ) ) {
			/**
			 * Filter which post types the link scanner reads.
			 *
			 * @param array $types Post type names.
			 */
			$types = apply_filters( 'bfr_link_targets', array_values( $types ) );
		}

		return array_values( array_filter( array_map( 'strval', (array) $types ) ) );
	}

	/**
	 * Post statuses worth scanning.
	 *
	 * @return array
	 */
	public static function target_statuses() {
		$statuses = array( 'publish' );

		if ( function_exists( 'apply_filters' ) ) {
			/**
			 * Filter which post statuses the link scanner reads.
			 *
			 * @param array $statuses Status names.
			 */
			$statuses = apply_filters( 'bfr_link_statuses', $statuses );
		}

		return array_values( array_filter( array_map( 'strval', (array) $statuses ) ) );
	}

	/**
	 * Posts per batch.
	 *
	 * @return int
	 */
	private static function batch_size() {
		$size = self::DEFAULT_BATCH;

		if ( function_exists( 'apply_filters' ) ) {
			/**
			 * Filter how many posts one link-scan batch reads.
			 *
			 * @param int $size Posts per batch.
			 */
			$size = (int) apply_filters( 'bfr_link_batch_size', $size );
		}

		return max( 1, min( 200, $size ) );
	}

	/**
	 * How bad is this kind of breakage?
	 *
	 * @param string $status     Resolution status.
	 * @param string $issue_type The type this issue is being filed under.
	 * @return int 1-5.
	 */
	private static function severity( $status, $issue_type = self::ISSUE_TYPE ) {
		if ( Classifier::MEDIA === $issue_type ) {
			return Classifier::severity( $issue_type, $status );
		}

		switch ( $status ) {
			case InternalResolver::MALFORMED:
				return 5;
			case InternalResolver::MISSING:
			case InternalResolver::TRASHED:
				return 4;
			case InternalResolver::NON_PUBLIC:
				return 3;
			default:
				return 2;
		}
	}

	/**
	 * Is this post the site's front page?
	 *
	 * @param int $post_id Post id.
	 * @return bool
	 */
	private static function is_front_page( $post_id ) {
		if ( ! function_exists( 'get_option' ) ) {
			return false;
		}

		return (int) get_option( 'page_on_front' ) === (int) $post_id;
	}
}
