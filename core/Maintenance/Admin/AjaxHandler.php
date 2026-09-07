<?php namespace RealTimeAutoFindReplace\Maintenance\Admin;

use RealTimeAutoFindReplace\Maintenance\Data\ActivityLog;
use RealTimeAutoFindReplace\Maintenance\Data\IssueRepository;
use RealTimeAutoFindReplace\Maintenance\Data\NotFoundRepository;
use RealTimeAutoFindReplace\Maintenance\Data\RedirectRepository;
use RealTimeAutoFindReplace\Maintenance\Data\ScanRunRepository;
use RealTimeAutoFindReplace\Maintenance\Data\Schema\Tables;
use RealTimeAutoFindReplace\Maintenance\LinkHealth\Fixer;
use RealTimeAutoFindReplace\Maintenance\LinkHealth\InternalResolver;
use RealTimeAutoFindReplace\Maintenance\LinkHealth\Scanner;
use RealTimeAutoFindReplace\Maintenance\NotFound\References;
use RealTimeAutoFindReplace\Maintenance\Queue\JobRepository;
use RealTimeAutoFindReplace\Maintenance\Queue\Runner;
use RealTimeAutoFindReplace\Maintenance\Redirects\Validator;
use RealTimeAutoFindReplace\Maintenance\ReplaceRedirect\CacheFlush;
use RealTimeAutoFindReplace\Maintenance\ReplaceRedirect\Workflow;
use RealTimeAutoFindReplace\Maintenance\Support\Capabilities;

/**
 * Maintenance AJAX, registered on the free plugin's dispatcher.
 *
 * RTAFAR_CustomAjax already enforces the things that are easy to forget: it
 * verifies the nonce, it looks the method up in an allow-list, it checks the
 * per-method capability, and it wraps the call in a try/catch so an exception
 * reaches the browser as a message rather than a stack trace. Registering here
 * inherits all of that; hand-rolling a second wp_ajax_ endpoint would mean
 * getting it right again.
 *
 * Two mechanics of that dispatcher shape this class:
 *   - keys are lower-cased and stripped to [a-z0-9@_:-], so they look like
 *     'bfrmaint@status';
 *   - handlers are instantiated with `new $class()` and no arguments, so this
 *     class must stay constructible with none.
 *
 * Every method re-checks the capability itself. The dispatcher already did, but
 * a handler that only works because something upstream remembered to check is
 * one refactor away from being an open endpoint.
 *
 * @package Maintenance
 * @since 1.10.0
 * @author M.Tuhin <info@codesolz.net>
 */

if ( ! defined( 'CS_RTAFAR_VERSION' ) ) {
	exit;
}

class AjaxHandler {

	/**
	 * Add this module's methods to the dispatcher's allow-list.
	 *
	 * @param array $methods Existing allow-list.
	 * @return array
	 */
	public static function register( $methods ) {
		if ( ! is_array( $methods ) ) {
			$methods = array();
		}

		$dashboard = Capabilities::for_module( 'dashboard' );
		$health    = Capabilities::for_module( 'content_health' );

		$methods['bfrmaint@status'] = array(
			'callback' => array( __CLASS__, 'status' ),
			'cap'      => $dashboard,
		);

		$methods['bfrmaint@scanstart'] = array(
			'callback' => array( __CLASS__, 'scan_start' ),
			'cap'      => $health,
		);

		$methods['bfrmaint@scanstatus'] = array(
			'callback' => array( __CLASS__, 'scan_status' ),
			'cap'      => $health,
		);

		$methods['bfrmaint@issueaction'] = array(
			'callback' => array( __CLASS__, 'issue_action' ),
			'cap'      => $health,
		);

		$methods['bfrmaint@fixpreview'] = array(
			'callback' => array( __CLASS__, 'fix_preview' ),
			'cap'      => $health,
		);

		$methods['bfrmaint@fixapply'] = array(
			'callback' => array( __CLASS__, 'fix_apply' ),
			'cap'      => $health,
		);

		$redirects = Capabilities::for_module( 'redirects' );

		$methods['bfrmaint@redirectsave'] = array(
			'callback' => array( __CLASS__, 'redirect_save' ),
			'cap'      => $redirects,
		);

		$methods['bfrmaint@redirectdelete'] = array(
			'callback' => array( __CLASS__, 'redirect_delete' ),
			'cap'      => $redirects,
		);

		$methods['bfrmaint@redirecttoggle'] = array(
			'callback' => array( __CLASS__, 'redirect_toggle' ),
			'cap'      => $redirects,
		);

		$methods['bfrmaint@404toggle'] = array(
			'callback' => array( __CLASS__, 'not_found_toggle' ),
			'cap'      => $redirects,
		);

		$methods['bfrmaint@404status'] = array(
			'callback' => array( __CLASS__, 'not_found_status' ),
			'cap'      => $redirects,
		);

		$methods['bfrmaint@404references'] = array(
			'callback' => array( __CLASS__, 'not_found_references' ),
			'cap'      => $redirects,
		);

		$methods['bfrmaint@rrpreview'] = array(
			'callback' => array( __CLASS__, 'replace_redirect_preview' ),
			'cap'      => $redirects,
		);

		$methods['bfrmaint@rrapply'] = array(
			'callback' => array( __CLASS__, 'replace_redirect_apply' ),
			'cap'      => $redirects,
		);

		return $methods;
	}

	/**
	 * Create or update a redirect.
	 *
	 * @param array $data Request data.
	 * @return array
	 */
	public static function redirect_save( $data = array() ) {
		if ( ! Capabilities::current_user_can( 'redirects' ) ) {
			return self::denied();
		}

		$id = isset( $data['id'] ) ? absint( $data['id'] ) : 0;

		// esc_url_raw would strip a bare path's leading slash context and mangle
		// nothing useful here, but it also drops characters we want to reject
		// loudly rather than silently clean - so the validator sees what the
		// user actually typed.
		$source      = isset( $data['source'] ) ? sanitize_text_field( wp_unslash( $data['source'] ) ) : '';
		$destination = isset( $data['destination'] ) ? sanitize_text_field( wp_unslash( $data['destination'] ) ) : '';
		$type        = isset( $data['type'] ) ? absint( $data['type'] ) : 301;

		// Validated against the registered list inside Validator::check(), so
		// an unavailable type is refused rather than silently stored.
		$match_type = isset( $data['match_type'] ) ? sanitize_key( wp_unslash( $data['match_type'] ) ) : 'exact';

		$check = Validator::check(
			$source,
			$destination,
			array(
				'site_host'  => InternalResolver::site_host(),
				'existing'   => RedirectRepository::all_for_validation(),
				'ignore_id'  => $id,
				'type'       => $type,
				'match_type' => $match_type,
			)
		);

		if ( ! $check['ok'] ) {
			return array(
				'status'   => false,
				'errors'   => $check['errors'],
				'message'  => implode( ' ', $check['errors'] ),
				'warnings' => $check['warnings'],
			);
		}

		$row = array(
			'source'        => $check['source'],
			'source_hash'   => $check['source_hash'],
			'destination'   => $check['destination'],
			'redirect_type' => $type,
			'match_type'    => $match_type,
			'enabled'       => 1,
		);

		if ( $id > 0 ) {
			$saved = RedirectRepository::update( $id, $row );
		} else {
			$result = RedirectRepository::insert( $row );
			$saved  = $result['ok'];
			$id     = $result['id'];

			if ( ! $saved && 'duplicate' === $result['reason'] ) {
				return array(
					'status'  => false,
					'message' => __( 'There is already a redirect for that URL.', 'real-time-auto-find-and-replace' ),
				);
			}
		}

		if ( ! $saved ) {
			return array(
				'status'  => false,
				'message' => __( 'The redirect could not be saved.', 'real-time-auto-find-and-replace' ),
			);
		}

		ActivityLog::record(
			'redirect_created',
			array(
				'object_type' => 'redirect',
				'object_id'   => $id,
				'summary'     => sprintf(
					/* translators: 1: source path, 2: destination */
					__( 'Redirect added: %1$s to %2$s', 'real-time-auto-find-and-replace' ),
					$check['source'],
					$check['destination']
				),
				'metadata'    => array(
					'source'      => $check['source'],
					'destination' => $check['destination'],
					'type'        => $type,
				),
			)
		);

		return array(
			'status'    => true,
			'id'        => $id,
			'warnings'  => $check['warnings'],
			'suggested' => $check['suggested'],
			'message'   => __( 'Redirect saved.', 'real-time-auto-find-and-replace' ),
		);
	}

	/**
	 * Delete a redirect.
	 *
	 * @param array $data Request data.
	 * @return array
	 */
	public static function redirect_delete( $data = array() ) {
		if ( ! Capabilities::current_user_can( 'redirects' ) ) {
			return self::denied();
		}

		$id = isset( $data['id'] ) ? absint( $data['id'] ) : 0;

		if ( $id <= 0 ) {
			return array(
				'status'  => false,
				'message' => __( 'Missing redirect.', 'real-time-auto-find-and-replace' ),
			);
		}

		// Recorded before the delete, so the log keeps what it pointed at - the
		// row itself is about to be gone.
		$existing = RedirectRepository::get( $id );
		$deleted  = RedirectRepository::delete( $id );

		if ( $deleted && $existing ) {
			ActivityLog::record(
				'redirect_deleted',
				array(
					'object_type' => 'redirect',
					'object_id'   => $id,
					'summary'     => sprintf(
						/* translators: 1: source path, 2: destination */
						__( 'Redirect removed: %1$s to %2$s', 'real-time-auto-find-and-replace' ),
						(string) $existing->source,
						(string) $existing->destination
					),
					'metadata'    => array(
						'source'      => (string) $existing->source,
						'destination' => (string) $existing->destination,
						'type'        => (int) $existing->redirect_type,
					),
				)
			);
		}

		return array(
			'status'  => (bool) $deleted,
			'message' => $deleted
				? __( 'Redirect deleted.', 'real-time-auto-find-and-replace' )
				: __( 'That redirect could not be deleted.', 'real-time-auto-find-and-replace' ),
		);
	}

	/**
	 * Enable or disable a redirect.
	 *
	 * @param array $data Request data.
	 * @return array
	 */
	public static function redirect_toggle( $data = array() ) {
		if ( ! Capabilities::current_user_can( 'redirects' ) ) {
			return self::denied();
		}

		$id = isset( $data['id'] ) ? absint( $data['id'] ) : 0;

		if ( $id <= 0 ) {
			return array(
				'status'  => false,
				'message' => __( 'Missing redirect.', 'real-time-auto-find-and-replace' ),
			);
		}

		$existing = RedirectRepository::get( $id );

		if ( ! $existing ) {
			return array(
				'status'  => false,
				'message' => __( 'That redirect no longer exists.', 'real-time-auto-find-and-replace' ),
			);
		}

		$enable = ! (int) $existing->enabled;
		$done   = RedirectRepository::set_enabled( $id, $enable );

		return array(
			'status'  => (bool) $done,
			'enabled' => $enable,
			'message' => $enable
				? __( 'Redirect enabled.', 'real-time-auto-find-and-replace' )
				: __( 'Redirect disabled.', 'real-time-auto-find-and-replace' ),
		);
	}

	/**
	 * Platform health, for the dashboard and for support.
	 *
	 * Deliberately says nothing about content - counts and states only.
	 *
	 * @param array $data Request data, unused.
	 * @return array
	 */
	public static function status( $data = array() ) {
		unset( $data );

		if ( ! Capabilities::current_user_can( 'dashboard' ) ) {
			return self::denied();
		}

		$installed = Tables::installed();

		return array(
			'status'     => true,
			'installed'  => $installed,
			'db_version' => $installed ? Tables::DB_VERSION : '',
			'db_error'   => Tables::last_error(),
			'queue'      => $installed ? JobRepository::counts() : array(),
			'pending'    => $installed ? JobRepository::pending_count() : 0,
			'driver'     => Runner::driver()->name(),
		);
	}

	/**
	 * Start a link scan.
	 *
	 * @param array $data Request data.
	 * @return array
	 */
	public static function scan_start( $data = array() ) {
		if ( ! Capabilities::current_user_can( 'content_health' ) ) {
			return self::denied();
		}

		$post_id = isset( $data['post_id'] ) ? absint( $data['post_id'] ) : 0;

		$result = Scanner::start(
			array(
				'scope'   => $post_id > 0 ? 'post' : 'full',
				'post_id' => $post_id,
			)
		);

		if ( ! $result['started'] ) {
			return array(
				'status'  => false,
				'scan_id' => $result['scan_id'],
				'code'    => $result['reason'],
				'message' => self::start_message( $result['reason'] ),
			);
		}

		return array(
			'status'  => true,
			'scan_id' => $result['scan_id'],
			'message' => __( 'Scan started.', 'real-time-auto-find-and-replace' ),
		);
	}

	/**
	 * Progress of a scan.
	 *
	 * @param array $data Request data.
	 * @return array
	 */
	public static function scan_status( $data = array() ) {
		if ( ! Capabilities::current_user_can( 'content_health' ) ) {
			return self::denied();
		}

		$scan_id = isset( $data['scan_id'] ) ? absint( $data['scan_id'] ) : 0;
		$run     = $scan_id > 0 ? ScanRunRepository::get( $scan_id ) : ScanRunRepository::latest( Scanner::SCAN_TYPE );

		if ( ! $run ) {
			return array(
				'status'  => true,
				'running' => false,
				'message' => __( 'No scan has run yet.', 'real-time-auto-find-and-replace' ),
			);
		}

		// A run the heartbeat says is dead is closed here rather than left
		// for the next scan_start to deal with. This poll is what notices -
		// and until the row is closed, active() keeps finding it, the button
		// stays disabled, and the one control that would have reclaimed it
		// cannot be pressed.
		if ( ScanRunRepository::STATUS_RUNNING === $run->status && ! ScanRunRepository::is_alive( $run ) ) {
			ScanRunRepository::reclaim_stale();

			$reclaimed = ScanRunRepository::get( (int) $run->id );
			$run       = $reclaimed ? $reclaimed : $run;
		}

		$running   = ScanRunRepository::is_alive( $run );
		$total     = (int) $run->total_items;
		$processed = (int) $run->processed_items;

		return array(
			'status'    => true,
			'running'   => $running,
			'state'     => (string) $run->status,
			'scan_id'   => (int) $run->id,
			'total'     => $total,
			'processed' => $processed,
			'found'     => (int) $run->issues_found,
			'percent'   => $total > 0 ? min( 100, (int) round( ( $processed / $total ) * 100 ) ) : 0,
			'pending'   => JobRepository::pending_count(),
			'message'   => $running ? '' : self::stopped_message( $run ),
		);
	}

	/**
	 * What to say about a run that ended without finishing.
	 *
	 * A scan that was killed is not a scan that finished, and telling somebody
	 * their content is clean when the worker died half way through it is the
	 * one answer this must never give. An empty string means "it finished
	 * properly" - the caller says so in its own words.
	 *
	 * @param object $run Run row.
	 * @return string
	 */
	private static function stopped_message( $run ) {
		$status = isset( $run->status ) ? (string) $run->status : '';

		if ( ScanRunRepository::STATUS_CANCELLED === $status ) {
			return __( 'The scan was stopped. What it had already found is on the list.', 'real-time-auto-find-and-replace' );
		}

		if ( ScanRunRepository::STATUS_FAILED === $status ) {
			return __( 'The scan stopped before it finished. What it found so far is on the list - run it again to check the rest.', 'real-time-auto-find-and-replace' );
		}

		return '';
	}

	/**
	 * Ignore, reopen, or re-check one issue.
	 *
	 * @param array $data Request data.
	 * @return array
	 */
	public static function issue_action( $data = array() ) {
		if ( ! Capabilities::current_user_can( 'content_health' ) ) {
			return self::denied();
		}

		$issue_id = isset( $data['issue_id'] ) ? absint( $data['issue_id'] ) : 0;
		$action   = isset( $data['do'] ) ? sanitize_key( $data['do'] ) : '';

		if ( $issue_id <= 0 ) {
			return array(
				'status'  => false,
				'message' => __( 'Missing issue.', 'real-time-auto-find-and-replace' ),
			);
		}

		switch ( $action ) {
			case 'ignore':
				$moved = IssueRepository::transition(
					$issue_id,
					IssueRepository::STATUS_IGNORED,
					array( IssueRepository::STATUS_OPEN, IssueRepository::STATUS_STALE )
				);

				return array(
					'status'  => $moved,
					'message' => $moved
						? __( 'Ignored. It will stay ignored on future scans.', 'real-time-auto-find-and-replace' )
						: __( 'That issue could not be ignored - it may have changed already.', 'real-time-auto-find-and-replace' ),
				);

			case 'unignore':
				$moved = IssueRepository::transition(
					$issue_id,
					IssueRepository::STATUS_OPEN,
					array( IssueRepository::STATUS_IGNORED )
				);

				return array(
					'status'  => $moved,
					'message' => $moved
						? __( 'Back on the list.', 'real-time-auto-find-and-replace' )
						: __( 'That issue is not ignored.', 'real-time-auto-find-and-replace' ),
				);

			case 'recheck':
				$result = Fixer::recheck( $issue_id );

				return array(
					'status'   => $result['ok'],
					'resolved' => $result['resolved'],
					'message'  => $result['message'],
				);
		}

		return array(
			'status'  => false,
			'message' => __( 'Unknown action.', 'real-time-auto-find-and-replace' ),
		);
	}

	/**
	 * Show what a fix would change, without changing anything.
	 *
	 * @param array $data Request data.
	 * @return array
	 */
	public static function fix_preview( $data = array() ) {
		if ( ! Capabilities::current_user_can( 'content_health' ) ) {
			return self::denied();
		}

		$issue_id  = isset( $data['issue_id'] ) ? absint( $data['issue_id'] ) : 0;
		$operation = isset( $data['operation'] ) ? sanitize_key( $data['operation'] ) : 'replace';
		$new_url   = isset( $data['replacement'] ) ? esc_url_raw( trim( (string) $data['replacement'] ) ) : '';

		$plan = 'unlink' === $operation
			? Fixer::preview_unlink( $issue_id )
			: Fixer::preview_replace( $issue_id, $new_url );

		if ( ! $plan['ok'] ) {
			return array(
				'status'  => false,
				'code'    => $plan['code'],
				'message' => $plan['message'],
			);
		}

		// Only what the reviewer needs to judge the change: the surrounding
		// bytes, escaped for display.
		$samples = array();

		foreach ( array_slice( array_reverse( $plan['edits'] ), 0, 10 ) as $edit ) {
			$samples[] = array(
				'from' => esc_html( $edit['from'] ),
				'to'   => esc_html( $edit['to'] ),
			);
		}

		return array(
			'status'      => true,
			'occurrences' => count( $plan['edits'] ),
			'samples'     => $samples,
			'message'     => sprintf(
				/* translators: %d: number of occurrences that would change */
				_n( '%d occurrence will change.', '%d occurrences will change.', count( $plan['edits'] ), 'real-time-auto-find-and-replace' ),
				count( $plan['edits'] )
			),
		);
	}

	/**
	 * Apply a fix.
	 *
	 * @param array $data Request data.
	 * @return array
	 */
	public static function fix_apply( $data = array() ) {
		if ( ! Capabilities::current_user_can( 'content_health' ) ) {
			return self::denied();
		}

		$issue_id  = isset( $data['issue_id'] ) ? absint( $data['issue_id'] ) : 0;
		$operation = isset( $data['operation'] ) ? sanitize_key( $data['operation'] ) : 'replace';
		$new_url   = isset( $data['replacement'] ) ? esc_url_raw( trim( (string) $data['replacement'] ) ) : '';

		$result = 'unlink' === $operation
			? Fixer::unlink( $issue_id )
			: Fixer::replace( $issue_id, $new_url );

		return array(
			'status'  => (bool) $result['ok'],
			'code'    => isset( $result['code'] ) ? $result['code'] : '',
			'changed' => isset( $result['changed'] ) ? (int) $result['changed'] : 0,
			'message' => $result['message'],
		);
	}

	/**
	 * Switch 404 recording on or off.
	 *
	 * @param array $data Request data.
	 * @return array
	 */
	public static function not_found_toggle( $data = array() ) {
		// The dispatcher passes data to every method; this one has nothing to read.
		unset( $data );

		if ( ! Capabilities::current_user_can( 'redirects' ) ) {
			return self::denied();
		}

		$enable = ! NotFoundRepository::is_enabled();
		NotFoundRepository::set_enabled( $enable );

		return array(
			'status'  => true,
			'enabled' => $enable,
			'message' => $enable
				? __( 'Recording 404s.', 'real-time-auto-find-and-replace' )
				: __( 'Stopped recording 404s.', 'real-time-auto-find-and-replace' ),
		);
	}

	/**
	 * Change what has been done about a 404.
	 *
	 * @param array $data Request data.
	 * @return array
	 */
	public static function not_found_status( $data = array() ) {
		if ( ! Capabilities::current_user_can( 'redirects' ) ) {
			return self::denied();
		}

		$id     = isset( $data['id'] ) ? absint( $data['id'] ) : 0;
		$status = isset( $data['status'] ) ? sanitize_key( $data['status'] ) : '';

		if ( $id <= 0 ) {
			return array(
				'status'  => false,
				'message' => __( 'Missing row.', 'real-time-auto-find-and-replace' ),
			);
		}

		$done = NotFoundRepository::set_status( $id, $status );

		return array(
			'status'  => $done,
			'message' => $done
				? __( 'Updated.', 'real-time-auto-find-and-replace' )
				: __( 'That status is not available.', 'real-time-auto-find-and-replace' ),
		);
	}

	/**
	 * Find content still linking to a dead path.
	 *
	 * Reuses the link scanner's extractor rather than searching the database
	 * for a string: a URL appears in content in several spellings, and the
	 * extractor already knows all of them.
	 *
	 * @param array $data Request data.
	 * @return array
	 */
	public static function not_found_references( $data = array() ) {
		if ( ! Capabilities::current_user_can( 'redirects' ) ) {
			return self::denied();
		}

		$path = isset( $data['path'] ) ? sanitize_text_field( wp_unslash( $data['path'] ) ) : '';

		if ( '' === $path ) {
			return array(
				'status'  => false,
				'message' => __( 'Missing URL.', 'real-time-auto-find-and-replace' ),
			);
		}

		$found = References::find( $path );

		return array(
			'status'     => true,
			'references' => $found,
			'message'    => empty( $found )
				? __( 'Nothing on this site links to that URL any more.', 'real-time-auto-find-and-replace' )
				: sprintf(
					/* translators: %d: number of posts containing the link */
					_n( '%d page still links to it.', '%d pages still link to it.', count( $found ), 'real-time-auto-find-and-replace' ),
					count( $found )
				),
		);
	}
	/**
	 * Show what a Replace + Redirect would change.
	 *
	 * @param array $data Request data.
	 * @return array
	 */
	public static function replace_redirect_preview( $data = array() ) {
		if ( ! Capabilities::current_user_can( 'redirects' ) ) {
			return self::denied();
		}

		$from = isset( $data['from'] ) ? sanitize_text_field( wp_unslash( $data['from'] ) ) : '';
		$to   = isset( $data['to'] ) ? sanitize_text_field( wp_unslash( $data['to'] ) ) : '';

		$preview = Workflow::preview( $from, $to );

		if ( ! $preview['ok'] ) {
			return array(
				'status'  => false,
				'message' => implode( ' ', $preview['errors'] ),
			);
		}

		// Only what the reviewer needs to judge it, escaped for display.
		$locations = array();

		foreach ( $preview['locations'] as $location ) {
			$locations[] = array(
				'table'       => esc_html( $location['table'] ),
				'label'       => esc_html( $location['label'] ),
				'context'     => esc_html( $location['context'] ),
				'occurrences' => (int) $location['occurrences'],
			);
		}

		return array(
			'status'      => true,
			'occurrences' => (int) $preview['occurrences'],
			'rows'        => (int) $preview['rows'],
			'changes'     => (int) $preview['changes'],
			'truncated'   => (bool) $preview['truncated'],
			'locations'   => $locations,
			'redirect'    => array(
				'ready' => (bool) $preview['redirect_ready'],
				'note'  => esc_html( $preview['redirect_note'] ),
			),
			'message'     => 0 === (int) $preview['occurrences']
				? __( 'That URL does not appear anywhere in your content. You can still create the redirect.', 'real-time-auto-find-and-replace' )
				: sprintf(
					/* translators: 1: number of occurrences, 2: number of rows */
					_n( '%1$d occurrence across %2$d place.', '%1$d occurrences across %2$d places.', (int) $preview['occurrences'], 'real-time-auto-find-and-replace' ),
					(int) $preview['occurrences'],
					(int) $preview['rows']
				),
		);
	}

	/**
	 * Carry out a Replace + Redirect.
	 *
	 * @param array $data Request data.
	 * @return array
	 */
	public static function replace_redirect_apply( $data = array() ) {
		if ( ! Capabilities::current_user_can( 'redirects' ) ) {
			return self::denied();
		}

		$from     = isset( $data['from'] ) ? sanitize_text_field( wp_unslash( $data['from'] ) ) : '';
		$to       = isset( $data['to'] ) ? sanitize_text_field( wp_unslash( $data['to'] ) ) : '';
		$redirect = ! isset( $data['create_redirect'] ) || 'false' !== (string) $data['create_redirect'];

		$result = Workflow::apply( $from, $to, array( 'create_redirect' => $redirect ) );

		if ( ! $result['ok'] ) {
			return array(
				'status'  => false,
				'message' => implode( ' ', $result['errors'] ),
			);
		}

		$message = sprintf(
			/* translators: %d: number of places changed */
			_n( 'Updated %d place.', 'Updated %d places.', $result['replaced'], 'real-time-auto-find-and-replace' ),
			$result['replaced']
		);

		if ( $result['redirect']['created'] ) {
			$message .= ' ' . __( 'Redirect created.', 'real-time-auto-find-and-replace' );
		} elseif ( 'already_exists' === $result['redirect']['reason'] ) {
			$message .= ' ' . __( 'A redirect for that URL already existed.', 'real-time-auto-find-and-replace' );
		}

		// Never claim more than was actually purged.
		$message .= ' ' . CacheFlush::describe( $result['cache'] );

		return array(
			'status'   => true,
			'replaced' => (int) $result['replaced'],
			'step'     => $result['step'],
			'warnings' => array_map( 'esc_html', $result['warnings'] ),
			'message'  => $message,
		);
	}
	/**
	 * Why a scan could not start.
	 *
	 * @param string $reason Machine-readable reason.
	 * @return string
	 */
	private static function start_message( $reason ) {
		switch ( $reason ) {
			case 'already_running':
				return __( 'A scan is already running.', 'real-time-auto-find-and-replace' );
			case 'schema_missing':
				return __( 'The maintenance tables are not installed.', 'real-time-auto-find-and-replace' );
		}

		return __( 'The scan could not be started.', 'real-time-auto-find-and-replace' );
	}

	/**
	 * The standard refusal.
	 *
	 * @return array
	 */
	private static function denied() {
		return array(
			'status'  => false,
			'message' => __( 'You do not have permission to do that.', 'real-time-auto-find-and-replace' ),
		);
	}
}
