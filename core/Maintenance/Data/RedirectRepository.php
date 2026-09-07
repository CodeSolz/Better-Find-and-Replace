<?php namespace RealTimeAutoFindReplace\Maintenance\Data;

use RealTimeAutoFindReplace\Maintenance\Data\Schema\Tables;
use RealTimeAutoFindReplace\Maintenance\Support\Logger;

/**
 * Stores redirects, and keeps the front-end guard honest.
 *
 * The guard is the whole reason this class is the only thing allowed to write
 * to the redirects table. Every visitor request asks "are there any redirects?"
 * and must be able to answer from one autoloaded option without touching the
 * database. That option is only trustworthy while it is rewritten in the same
 * call as every insert, update and delete - so anything that writes redirects
 * around this class silently disables redirects for the whole site.
 *
 * The guard is deliberately fail-open: a missing option means "I do not know",
 * which costs one query, while a stale zero would mean "no redirects exist" and
 * break every one of them. Only an explicit zero short-circuits.
 *
 * @package Maintenance
 * @since 1.10.0
 * @author M.Tuhin <info@codesolz.net>
 */

if ( ! defined( 'CS_RTAFAR_VERSION' ) ) {
	exit;
}

// Table names come from Schema\Tables - prefix plus a literal, never request
// data - while every value is still a placeholder.
// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery
// phpcs:disable WordPress.DB.DirectDatabaseQuery.NoCaching

class RedirectRepository {

	/**
	 * Autoloaded count of enabled redirects. The front end's only read.
	 */
	const GUARD_OPTION = 'bfr_redirect_count';

	/**
	 * Add a redirect.
	 *
	 * @param array $row {
	 *     @type string $source        Normalised source.
	 *     @type string $source_hash   Index key.
	 *     @type string $destination   Where to send visitors.
	 *     @type int    $redirect_type HTTP status code.
	 *     @type string $match_type    'exact' in free.
	 *     @type int    $enabled       1 or 0.
	 * }
	 * @return array array( 'ok' => bool, 'id' => int, 'reason' => string )
	 */
	public static function insert( array $row ) {
		global $wpdb;

		$source_hash = isset( $row['source_hash'] ) ? (string) $row['source_hash'] : '';

		if ( '' === $source_hash ) {
			return array(
				'ok'     => false,
				'id'     => 0,
				'reason' => 'missing_source',
			);
		}

		$now = self::now();

		// Hitting the unique index is the expected outcome when two admins save
		// the same source at once, not an error worth logging.
		$suppress = $wpdb->suppress_errors( true );

		$ok = $wpdb->insert(
			Tables::redirects(),
			array(
				'source_hash'   => $source_hash,
				'source'        => substr( (string) ( isset( $row['source'] ) ? $row['source'] : '' ), 0, 255 ),
				'destination'   => substr( (string) ( isset( $row['destination'] ) ? $row['destination'] : '' ), 0, 255 ),
				'redirect_type' => isset( $row['redirect_type'] ) ? (int) $row['redirect_type'] : 301,
				'match_type'    => isset( $row['match_type'] ) ? (string) $row['match_type'] : 'exact',
				'enabled'       => isset( $row['enabled'] ) ? (int) (bool) $row['enabled'] : 1,
				'group_name'    => isset( $row['group_name'] ) ? substr( (string) $row['group_name'], 0, 64 ) : '',

				// NULL rather than an empty string: this is a datetime column,
				// and "no expiry" is the absence of one, not a zero date.
				'expires_at'    => empty( $row['expires_at'] ) ? null : (string) $row['expires_at'],
				'hit_count'     => 0,
				'created_by'    => function_exists( 'get_current_user_id' ) ? (int) get_current_user_id() : 0,
				'created_at'    => $now,
				'updated_at'    => $now,
			)
		);

		$wpdb->suppress_errors( $suppress );

		if ( ! $ok ) {
			return array(
				'ok'     => false,
				'id'     => 0,
				'reason' => 'duplicate',
			);
		}

		self::refresh_guard();

		return array(
			'ok'     => true,
			'id'     => (int) $wpdb->insert_id,
			'reason' => '',
		);
	}

	/**
	 * Change an existing redirect.
	 *
	 * @param int   $id     Redirect id.
	 * @param array $fields Fields to change.
	 * @return bool
	 */
	public static function update( $id, array $fields ) {
		global $wpdb;

		$id = (int) $id;

		if ( $id <= 0 ) {
			return false;
		}

		$allowed = array( 'source', 'source_hash', 'destination', 'redirect_type', 'match_type', 'enabled', 'group_name', 'expires_at' );
		$data    = array();

		foreach ( $allowed as $key ) {
			if ( ! array_key_exists( $key, $fields ) ) {
				continue;
			}

			if ( 'redirect_type' === $key || 'enabled' === $key ) {
				$data[ $key ] = (int) $fields[ $key ];
				continue;
			}

			$data[ $key ] = substr( (string) $fields[ $key ], 0, 255 );
		}

		if ( empty( $data ) ) {
			return false;
		}

		$data['updated_at'] = self::now();

		$done = $wpdb->update( Tables::redirects(), $data, array( 'id' => $id ) );

		self::refresh_guard();

		return false !== $done;
	}

	/**
	 * Remove a redirect.
	 *
	 * @param int $id Redirect id.
	 * @return bool
	 */
	public static function delete( $id ) {
		global $wpdb;

		$done = $wpdb->delete( Tables::redirects(), array( 'id' => (int) $id ) );

		self::refresh_guard();

		if ( $done && function_exists( 'do_action' ) ) {
			/**
			 * Fires after a redirect rule has been deleted.
			 *
			 * Anything keeping data keyed by redirect id - hit history, for
			 * one - hears about it here. Without this, deleting a rule leaks
			 * every row that referred to it.
			 *
			 * @param int $id The id that was deleted.
			 */
			do_action( 'bfr_redirect_deleted', (int) $id );
		}

		return (bool) $done;
	}

	/**
	 * Turn a redirect on or off.
	 *
	 * @param int  $id      Redirect id.
	 * @param bool $enabled Whether it should fire.
	 * @return bool
	 */
	public static function set_enabled( $id, $enabled ) {
		return self::update( $id, array( 'enabled' => $enabled ? 1 : 0 ) );
	}

	/**
	 * One redirect by id.
	 *
	 * @param int $id Redirect id.
	 * @return object|null
	 */
	public static function get( $id ) {
		global $wpdb;

		$table = Tables::redirects();

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", (int) $id ) );
	}

	/**
	 * The enabled redirect for a normalised source, if there is one.
	 *
	 * The front end's only query. One row, by unique index.
	 *
	 * @param string $source_hash Normalised source key.
	 * @return object|null
	 */
	public static function find_enabled( $source_hash ) {
		global $wpdb;

		$table = Tables::redirects();

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		return $wpdb->get_row(
			$wpdb->prepare(
				"SELECT id, source, destination, redirect_type, match_type FROM {$table}
				 WHERE source_hash = %s AND enabled = 1 AND match_type = %s
				 LIMIT 1",
				array( (string) $source_hash, 'exact' )
			)
		);
	}

	/**
	 * Redirects for the admin list.
	 *
	 * @param array $args {
	 *     @type string $search   Substring of source or destination.
	 *     @type int    $per_page Rows per page.
	 *     @type int    $page     1-based page.
	 * }
	 * @return array
	 */
	public static function find( array $args = array() ) {
		global $wpdb;

		$table    = Tables::redirects();
		$per_page = max( 1, min( 200, isset( $args['per_page'] ) ? (int) $args['per_page'] : 20 ) );
		$offset   = ( max( 1, isset( $args['page'] ) ? (int) $args['page'] : 1 ) - 1 ) * $per_page;

		if ( ! empty( $args['search'] ) ) {
			$like = '%' . $wpdb->esc_like( (string) $args['search'] ) . '%';

			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			return (array) $wpdb->get_results(
				$wpdb->prepare(
					"SELECT * FROM {$table} WHERE source LIKE %s OR destination LIKE %s ORDER BY id DESC LIMIT %d OFFSET %d",
					array( $like, $like, $per_page, $offset )
				)
			);
		}

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		return (array) $wpdb->get_results(
			$wpdb->prepare( "SELECT * FROM {$table} ORDER BY id DESC LIMIT %d OFFSET %d", array( $per_page, $offset ) )
		);
	}

	/**
	 * Walk every redirect in id order, a page at a time.
	 *
	 * A keyset cursor rather than an offset: reading a whole table by offset
	 * re-scans everything before each page, and a write during the walk shifts
	 * the rows. Both matter here, because the callers are exports and sweeps
	 * rather than a screen showing twenty rows.
	 *
	 * @param int $after_id Last id already handled; 0 to start.
	 * @param int $limit    Rows to return.
	 * @return array
	 */
	public static function walk( $after_id = 0, $limit = 200 ) {
		global $wpdb;

		$table = Tables::redirects();
		$limit = max( 1, min( 1000, (int) $limit ) );

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		return (array) $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE id > %d ORDER BY id ASC LIMIT %d",
				array( (int) $after_id, $limit )
			)
		);
	}

	/**
	 * How many redirects exist in total.
	 *
	 * @return int
	 */
	public static function count_all() {
		global $wpdb;

		$table = Tables::redirects();

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		return (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" );
	}

	/**
	 * Every redirect, as plain arrays, for loop and chain detection.
	 *
	 * Bounded: chain detection on a set this large is already unusual, and the
	 * validator only needs enough of the graph to answer one question.
	 *
	 * @param int $limit Maximum rows.
	 * @return array
	 */
	public static function all_for_validation( $limit = 2000 ) {
		global $wpdb;

		$table = Tables::redirects();

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$rows = $wpdb->get_results(
			$wpdb->prepare( "SELECT id, source_hash, source, destination FROM {$table} LIMIT %d", (int) $limit ),
			ARRAY_A
		);

		return (array) $rows;
	}

	/**
	 * Count this hit.
	 *
	 * One primary-key UPDATE, and only after the response has been handed to
	 * the visitor where the platform allows it - see Executor. Filterable off
	 * for anyone who would rather not pay for it at all.
	 *
	 * @param int $id Redirect id.
	 * @return void
	 */
	public static function record_hit( $id ) {
		global $wpdb;

		if ( function_exists( 'apply_filters' ) ) {
			/**
			 * Filter whether redirect hits are counted.
			 *
			 * @param bool $count Whether to count.
			 */
			if ( ! apply_filters( 'bfr_redirect_count_hits', true ) ) {
				return;
			}
		}

		$table = Tables::redirects();

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$wpdb->query(
			$wpdb->prepare(
				"UPDATE {$table} SET hit_count = hit_count + 1, last_hit_at = %s WHERE id = %d",
				array( self::now(), (int) $id )
			)
		);
	}

	/**
	 * Recount enabled redirects into the autoloaded guard.
	 *
	 * Called after every write. Also safe to call defensively - the redirects
	 * screen does, so a guard corrupted by something outside this class heals
	 * the next time an admin looks at the page.
	 *
	 * @return int The count now stored.
	 */
	public static function refresh_guard() {
		global $wpdb;

		$table = Tables::redirects();

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$count = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table} WHERE enabled = 1" );

		if ( function_exists( 'update_option' ) ) {
			// Autoloaded on purpose: the front end must read it without a query.
			update_option( self::GUARD_OPTION, $count, true );
		}

		Logger::log( 'redirects.guard', array( 'enabled' => $count ) );

		if ( function_exists( 'do_action' ) ) {
			/**
			 * Fires after any change to the redirect set.
			 *
			 * The guard is refreshed on every write, which makes this the one
			 * place that reliably knows the rules have moved. Pro caches a
			 * compiled set of its non-exact rules and invalidates it here; the
			 * option the guard itself writes is not enough, because editing a
			 * pattern changes no count.
			 *
			 * @param int $count Enabled redirects now.
			 */
			do_action( 'bfr_redirects_changed', $count );
		}

		return $count;
	}

	/**
	 * Does this site have any redirects to check?
	 *
	 * Fail-open: only an explicit zero is trusted to mean "none". A missing
	 * option means the guard was never written, and answering "none" to that
	 * would silently break every redirect on the site.
	 *
	 * @return bool
	 */
	public static function guard_says_none() {
		if ( ! function_exists( 'get_option' ) ) {
			return false;
		}

		$count = get_option( self::GUARD_OPTION, null );

		if ( null === $count || '' === $count || false === $count ) {
			return false;
		}

		return 0 === (int) $count;
	}

	/**
	 * Current UTC timestamp in MySQL format.
	 *
	 * @return string
	 */
	private static function now() {
		return function_exists( 'current_time' ) ? current_time( 'mysql', true ) : gmdate( 'Y-m-d H:i:s' );
	}
}
