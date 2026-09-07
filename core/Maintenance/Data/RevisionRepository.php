<?php namespace RealTimeAutoFindReplace\Maintenance\Data;

use RealTimeAutoFindReplace\Maintenance\Data\Schema\Tables;

/**
 * Proposed content, held beside the post it is about.
 *
 * The free plugin owns the data layer, so this lives here even though only pro
 * writes to it - the same arrangement as `LinkCheckRepository` and
 * `RedirectHitRepository`.
 *
 * Three columns carry the weight, and it is worth saying which:
 *
 * - **`baseline_*`** is what the post said when the proposal was made. Without
 *   it there is no way to tell an unchanged post from one somebody has edited
 *   since, and applying to the second silently destroys their work.
 * - **`replaced_*`** is filled in *before* the merge writes anything. That is
 *   the whole of rollback. Recording it afterwards would mean recording it from
 *   a post that had already changed.
 * - **`decisions`** is which hunks were accepted, so an applied revision can
 *   explain itself later and a partly-reviewed one can be picked back up.
 *
 * @package Maintenance
 * @since 1.12.0
 * @author M.Tuhin <info@codesolz.net>
 */

if ( ! defined( 'CS_RTAFAR_VERSION' ) ) {
	exit;
}

class RevisionRepository {

	/** Proposed, not yet decided. */
	const STATUS_OPEN = 'open';

	/** Merged into the post. */
	const STATUS_APPLIED = 'applied';

	/** Thrown away without being applied. */
	const STATUS_DISCARDED = 'discarded';

	/** Applied and then undone. */
	const STATUS_ROLLED_BACK = 'rolled_back';

	/**
	 * Every status a revision can hold.
	 *
	 * @return array
	 */
	public static function statuses() {
		return array(
			self::STATUS_OPEN,
			self::STATUS_APPLIED,
			self::STATUS_DISCARDED,
			self::STATUS_ROLLED_BACK,
		);
	}

	/**
	 * Store a proposal.
	 *
	 * @param array $row {
	 *     @type int    $object_id        The post this is about.
	 *     @type string $source           Who proposed it: 'ai', 'manual'.
	 *     @type string $baseline_title   The post's title when this was made.
	 *     @type string $baseline_content The post's content when this was made.
	 *     @type string $baseline_excerpt The post's excerpt when this was made.
	 *     @type string $proposed_title   Proposed title, or unset to leave alone.
	 *     @type string $proposed_content Proposed content, or unset.
	 *     @type string $proposed_excerpt Proposed excerpt, or unset.
	 *     @type array  $metadata         Anything the proposer wants to keep.
	 * }
	 * @return int New id, or 0.
	 */
	public static function create( array $row ) {
		global $wpdb;

		$object_id = isset( $row['object_id'] ) ? (int) $row['object_id'] : 0;

		if ( $object_id <= 0 ) {
			return 0;
		}

		$now = self::now();

		$data = array(
			'object_type'      => isset( $row['object_type'] ) ? (string) $row['object_type'] : 'post',
			'object_id'        => $object_id,
			'status'           => self::STATUS_OPEN,
			'source'           => isset( $row['source'] ) ? substr( (string) $row['source'], 0, 32 ) : '',
			'baseline_hash'    => self::hash_of( $row ),
			'baseline_title'   => isset( $row['baseline_title'] ) ? (string) $row['baseline_title'] : '',
			'baseline_content' => isset( $row['baseline_content'] ) ? (string) $row['baseline_content'] : '',
			'baseline_excerpt' => isset( $row['baseline_excerpt'] ) ? (string) $row['baseline_excerpt'] : '',
			'proposed_title'   => isset( $row['proposed_title'] ) ? (string) $row['proposed_title'] : '',
			'proposed_content' => isset( $row['proposed_content'] ) ? (string) $row['proposed_content'] : '',
			'proposed_excerpt' => isset( $row['proposed_excerpt'] ) ? (string) $row['proposed_excerpt'] : '',
			'decisions'        => '',
			'metadata'         => isset( $row['metadata'] ) ? self::encode( $row['metadata'] ) : '',
			'created_by'       => function_exists( 'get_current_user_id' ) ? (int) get_current_user_id() : 0,
			'created_at'       => $now,
			'updated_at'       => $now,
		);

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$ok = $wpdb->insert( Tables::revisions(), $data );

		return $ok ? (int) $wpdb->insert_id : 0;
	}

	/**
	 * The fingerprint of the content a proposal was made against.
	 *
	 * @param array $row Row with baseline fields.
	 * @return string
	 */
	public static function hash_of( array $row ) {
		return sha1(
			(string) ( isset( $row['baseline_title'] ) ? $row['baseline_title'] : '' )
			. '|' . (string) ( isset( $row['baseline_content'] ) ? $row['baseline_content'] : '' )
			. '|' . (string) ( isset( $row['baseline_excerpt'] ) ? $row['baseline_excerpt'] : '' )
		);
	}

	/**
	 * One revision.
	 *
	 * @param int $id Revision id.
	 * @return object|null
	 */
	public static function get( $id ) {
		global $wpdb;

		$id = (int) $id;

		if ( $id <= 0 ) {
			return null;
		}

		$table = Tables::revisions();

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $id ) );
	}

	/**
	 * Revisions for one post, newest first.
	 *
	 * @param int    $object_id Post id.
	 * @param string $status    Status, or empty for all.
	 * @param int    $limit     Rows.
	 * @return array
	 */
	public static function for_object( $object_id, $status = '', $limit = 20 ) {
		global $wpdb;

		$object_id = (int) $object_id;
		$limit     = max( 1, min( 200, (int) $limit ) );
		$table     = Tables::revisions();

		// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		if ( '' !== $status && in_array( $status, self::statuses(), true ) ) {
			return (array) $wpdb->get_results(
				$wpdb->prepare(
					"SELECT * FROM {$table} WHERE object_id = %d AND status = %s ORDER BY id DESC LIMIT %d",
					$object_id,
					$status,
					$limit
				)
			);
		}

		return (array) $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE object_id = %d ORDER BY id DESC LIMIT %d",
				$object_id,
				$limit
			)
		);
		// phpcs:enable
	}

	/**
	 * Remember which changes were accepted.
	 *
	 * @param int   $id        Revision id.
	 * @param array $decisions Hunk id => bool.
	 * @return bool
	 */
	public static function set_decisions( $id, array $decisions ) {
		return self::update( $id, array( 'decisions' => self::encode( $decisions ) ) );
	}

	/**
	 * Record what the merge is about to replace, then mark it applied.
	 *
	 * One call, because these two must not be able to happen separately: a
	 * revision marked applied with no record of what it replaced is a revision
	 * that cannot be rolled back.
	 *
	 * @param int   $id       Revision id.
	 * @param array $replaced Fields as they were immediately before the write.
	 * @return bool
	 */
	public static function mark_applied( $id, array $replaced ) {
		return self::update(
			$id,
			array(
				'status'           => self::STATUS_APPLIED,
				'replaced_title'   => isset( $replaced['title'] ) ? (string) $replaced['title'] : '',
				'replaced_content' => isset( $replaced['content'] ) ? (string) $replaced['content'] : '',
				'replaced_excerpt' => isset( $replaced['excerpt'] ) ? (string) $replaced['excerpt'] : '',
				'applied_at'       => self::now(),
			)
		);
	}

	/**
	 * Move a revision to a terminal status.
	 *
	 * @param int    $id     Revision id.
	 * @param string $status One of the status constants.
	 * @return bool
	 */
	public static function set_status( $id, $status ) {
		if ( ! in_array( $status, self::statuses(), true ) ) {
			return false;
		}

		return self::update( $id, array( 'status' => (string) $status ) );
	}

	/**
	 * Update some columns.
	 *
	 * @param int   $id     Revision id.
	 * @param array $fields Column => value.
	 * @return bool
	 */
	private static function update( $id, array $fields ) {
		global $wpdb;

		$id = (int) $id;

		if ( $id <= 0 || empty( $fields ) ) {
			return false;
		}

		$allowed = array(
			'status',
			'decisions',
			'metadata',
			'replaced_title',
			'replaced_content',
			'replaced_excerpt',
			'applied_at',
		);

		$data = array();

		foreach ( $allowed as $key ) {
			if ( array_key_exists( $key, $fields ) ) {
				$data[ $key ] = $fields[ $key ];
			}
		}

		if ( empty( $data ) ) {
			return false;
		}

		$data['updated_at'] = self::now();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		return false !== $wpdb->update( Tables::revisions(), $data, array( 'id' => $id ) );
	}

	/**
	 * Throw away revisions nobody is going to look at.
	 *
	 * Only ones that were never applied: an applied revision is the only record
	 * of what it replaced, and deleting it would delete the rollback.
	 *
	 * @param string $before MySQL datetime, UTC.
	 * @param int    $limit  Rows per call.
	 * @return int Rows deleted.
	 */
	public static function prune( $before, $limit = 200 ) {
		global $wpdb;

		$table = Tables::revisions();
		$limit = max( 1, min( 2000, (int) $limit ) );

		// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		return (int) $wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$table}
				 WHERE created_at < %s AND status IN ( %s, %s )
				 LIMIT %d",
				(string) $before,
				self::STATUS_OPEN,
				self::STATUS_DISCARDED,
				$limit
			)
		);
		// phpcs:enable
	}

	/**
	 * Drop everything for a post that no longer exists.
	 *
	 * @param int $object_id Post id.
	 * @return int Rows deleted.
	 */
	public static function forget( $object_id ) {
		global $wpdb;

		$object_id = (int) $object_id;

		if ( $object_id <= 0 ) {
			return 0;
		}

		$table = Tables::revisions();

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		return (int) $wpdb->query( $wpdb->prepare( "DELETE FROM {$table} WHERE object_id = %d", $object_id ) );
	}

	/**
	 * Decode a stored JSON column.
	 *
	 * @param string $value Column value.
	 * @return array
	 */
	public static function decode( $value ) {
		if ( empty( $value ) ) {
			return array();
		}

		$decoded = json_decode( (string) $value, true );

		return is_array( $decoded ) ? $decoded : array();
	}

	/**
	 * Encode a value for a JSON column.
	 *
	 * @param mixed $value Value.
	 * @return string
	 */
	private static function encode( $value ) {
		if ( is_string( $value ) ) {
			return $value;
		}

		$encoded = function_exists( 'wp_json_encode' ) ? wp_json_encode( $value ) : json_encode( $value ); // phpcs:ignore WordPress.WP.AlternativeFunctions.json_encode_json_encode

		return is_string( $encoded ) ? $encoded : '';
	}

	/**
	 * Now, in UTC.
	 *
	 * @return string
	 */
	private static function now() {
		return gmdate( 'Y-m-d H:i:s' );
	}
}
