<?php namespace RealTimeAutoFindReplace\Maintenance\Data;

use RealTimeAutoFindReplace\Maintenance\Data\Schema\Tables;
use RealTimeAutoFindReplace\Maintenance\Support\Logger;

/**
 * The 404 log.
 *
 * Every other table in this platform is written by code the site owner ran.
 * This one is written by whoever sends a request, which makes it the only
 * attacker-controlled table here and changes what "correct" means: an
 * unbounded 404 log is a way to fill somebody's disk with a for-loop.
 *
 * Three bounds, all in this class:
 *
 *   - a repeat hit is an UPDATE of an existing row, never a new one, enforced
 *     by the UNIQUE index on path_hash rather than by looking first;
 *   - a daily budget caps how many DISTINCT paths can be recorded, and once it
 *     is spent everything else is counted in a single overflow row, so a
 *     scanner producing ten thousand URLs produces one row;
 *   - a retention window prunes what nobody looked at.
 *
 * The user agent is hashed, never stored. It is visitor data, this feature does
 * not need it, and storing it would make the table a privacy problem as well as
 * a disk one.
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

class NotFoundRepository {

	/** Whether the monitor records anything. Off until the user says otherwise. */
	const ENABLED_OPTION = 'bfr_404_enabled';

	/** Tracks how much of today's distinct-row budget is spent. */
	const BUDGET_OPTION = 'bfr_404_budget';

	/** Distinct new paths recordable per day, before filtering. */
	const DEFAULT_DAILY_BUDGET = 500;

	/** Days a row is kept, before filtering. */
	const DEFAULT_RETENTION_DAYS = 30;

	/** The single row everything past the budget is counted in. */
	const OVERFLOW_PATH = '(further URLs not recorded today)';

	const STATUS_NEW        = 'new';
	const STATUS_IGNORED    = 'ignored';
	const STATUS_REDIRECTED = 'redirected';
	const STATUS_RESOLVED   = 'resolved';

	/**
	 * Is the monitor switched on?
	 *
	 * @return bool
	 */
	public static function is_enabled() {
		if ( ! function_exists( 'get_option' ) ) {
			return false;
		}

		return (bool) get_option( self::ENABLED_OPTION, false );
	}

	/**
	 * Turn the monitor on or off.
	 *
	 * @param bool $enabled Whether to record 404s.
	 * @return void
	 */
	public static function set_enabled( $enabled ) {
		// Autoloaded: the front end reads it on every 404 and must not pay a
		// query to find out it has nothing to do.
		update_option( self::ENABLED_OPTION, $enabled ? 1 : 0, true );
	}

	/**
	 * Record one 404.
	 *
	 * One query in the common case. The insert is attempted first and the
	 * unique index arbitrates: a path already seen becomes an increment, a new
	 * one becomes a row, and two simultaneous requests for the same new path
	 * cannot both create it.
	 *
	 * @param array $args {
	 *     @type string $path       Normalised path.
	 *     @type string $raw_path   Path as requested, for display.
	 *     @type string $referrer   Referring URL, if any.
	 *     @type string $user_agent Raw user agent - hashed here, never stored.
	 * }
	 * @return string What happened: 'incremented', 'created', 'overflow' or 'skipped'.
	 */
	public static function record( array $args ) {
		global $wpdb;

		$path = isset( $args['path'] ) ? (string) $args['path'] : '';

		if ( '' === $path ) {
			return 'skipped';
		}

		$path      = self::trim255( $path );
		$path_hash = sha1( $path );
		$now       = self::now();

		// An increment beats an insert for anything already known, and costs
		// exactly one query whether or not the row exists.
		$table = Tables::not_found();

		$updated = $wpdb->query(
			$wpdb->prepare(
				"UPDATE {$table} SET hit_count = hit_count + 1, last_seen_at = %s WHERE path_hash = %s",
				array( $now, $path_hash )
			)
		);

		if ( $updated ) {
			return 'incremented';
		}

		// A path we have not seen before costs a slot in today's budget.
		if ( ! self::claim_budget_slot() ) {
			self::record_overflow();

			return 'overflow';
		}

		$suppress = $wpdb->suppress_errors( true );

		$inserted = $wpdb->insert(
			$table,
			array(
				'path_hash'       => $path_hash,
				'request_path'    => self::trim255( isset( $args['raw_path'] ) ? $args['raw_path'] : $path ),
				'normalized_path' => $path,
				'referrer'        => self::trim255( isset( $args['referrer'] ) ? $args['referrer'] : '' ),
				'user_agent_hash' => isset( $args['user_agent'] ) && '' !== $args['user_agent']
					? sha1( (string) $args['user_agent'] )
					: '',
				'hit_count'       => 1,
				'status'          => self::STATUS_NEW,
				'first_seen_at'   => $now,
				'last_seen_at'    => $now,
			)
		);

		$wpdb->suppress_errors( $suppress );

		if ( ! $inserted ) {
			// Lost the race to another request for the same new path. It exists
			// now, so count the hit rather than dropping it.
			$wpdb->query(
				$wpdb->prepare(
					"UPDATE {$table} SET hit_count = hit_count + 1, last_seen_at = %s WHERE path_hash = %s",
					array( $now, $path_hash )
				)
			);

			return 'incremented';
		}

		return 'created';
	}

	/**
	 * Count one request that arrived after the budget was spent.
	 *
	 * @return void
	 */
	private static function record_overflow() {
		global $wpdb;

		$table = Tables::not_found();
		$hash  = sha1( self::OVERFLOW_PATH );
		$now   = self::now();

		$updated = $wpdb->query(
			$wpdb->prepare(
				"UPDATE {$table} SET hit_count = hit_count + 1, last_seen_at = %s WHERE path_hash = %s",
				array( $now, $hash )
			)
		);

		if ( $updated ) {
			return;
		}

		$suppress = $wpdb->suppress_errors( true );

		$wpdb->insert(
			$table,
			array(
				'path_hash'       => $hash,
				'request_path'    => self::OVERFLOW_PATH,
				'normalized_path' => self::OVERFLOW_PATH,
				'referrer'        => '',
				'user_agent_hash' => '',
				'hit_count'       => 1,
				'status'          => self::STATUS_IGNORED,
				'first_seen_at'   => $now,
				'last_seen_at'    => $now,
			)
		);

		$wpdb->suppress_errors( $suppress );
	}

	/**
	 * Take one slot from today's distinct-path budget.
	 *
	 * @return bool False when today's budget is spent.
	 */
	private static function claim_budget_slot() {
		if ( ! function_exists( 'get_option' ) ) {
			return true;
		}

		$today  = gmdate( 'Y-m-d' );
		$budget = self::daily_budget();
		$state  = get_option( self::BUDGET_OPTION, array() );

		if ( ! is_array( $state ) || ! isset( $state['date'] ) || $state['date'] !== $today ) {
			$state = array(
				'date'  => $today,
				'count' => 0,
			);
		}

		if ( (int) $state['count'] >= $budget ) {
			return false;
		}

		++$state['count'];

		// Not autoloaded: only written when a genuinely new path arrives, and
		// only read on that same path.
		update_option( self::BUDGET_OPTION, $state, false );

		if ( (int) $state['count'] === $budget ) {
			Logger::log( '404.budget_spent', array( 'budget' => $budget ) );
		}

		return true;
	}

	/**
	 * How many distinct new paths may be recorded in a day.
	 *
	 * @return int
	 */
	public static function daily_budget() {
		$budget = self::DEFAULT_DAILY_BUDGET;

		if ( function_exists( 'apply_filters' ) ) {
			/**
			 * Filter the daily distinct-path budget for the 404 log.
			 *
			 * @param int $budget Rows per day.
			 */
			$budget = (int) apply_filters( 'bfr_404_daily_budget', $budget );
		}

		// Floor of 1, not 10: a site owner who wants a budget of 5 should get 5.
		// Switching the monitor off is what "log nothing" means, not a zero budget.
		return max( 1, min( 100000, $budget ) );
	}

	/**
	 * How long a row is kept.
	 *
	 * @return int Days.
	 */
	public static function retention_days() {
		$days = self::DEFAULT_RETENTION_DAYS;

		if ( function_exists( 'apply_filters' ) ) {
			/**
			 * Filter how many days 404 rows are kept.
			 *
			 * @param int $days Days.
			 */
			$days = (int) apply_filters( 'bfr_404_retention_days', $days );
		}

		return max( 1, min( 365, $days ) );
	}

	/**
	 * Delete rows nobody acted on, older than the retention window.
	 *
	 * Rows the user did something with - ignored, redirected, resolved - are
	 * kept: those represent a decision, and deleting one would make the same
	 * dead URL reappear as new.
	 *
	 * @param int $limit Maximum rows per call, so the job stays bounded.
	 * @return int Rows deleted.
	 */
	public static function prune( $limit = 500 ) {
		global $wpdb;

		$table  = Tables::not_found();
		$cutoff = gmdate( 'Y-m-d H:i:s', time() - ( self::retention_days() * DAY_IN_SECONDS ) );

		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$table} WHERE status = %s AND last_seen_at < %s LIMIT %d",
				array( self::STATUS_NEW, $cutoff, max( 1, (int) $limit ) )
			)
		);

		return (int) $wpdb->rows_affected;
	}

	/**
	 * One row by id.
	 *
	 * @param int $id Row id.
	 * @return object|null
	 */
	public static function get( $id ) {
		global $wpdb;

		$table = Tables::not_found();

		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", (int) $id ) );
	}

	/**
	 * Change a row's status.
	 *
	 * @param int    $id     Row id.
	 * @param string $status New status.
	 * @return bool
	 */
	public static function set_status( $id, $status ) {
		global $wpdb;

		$allowed = array( self::STATUS_NEW, self::STATUS_IGNORED, self::STATUS_REDIRECTED, self::STATUS_RESOLVED );

		if ( ! in_array( $status, $allowed, true ) ) {
			return false;
		}

		return false !== $wpdb->update(
			Tables::not_found(),
			array( 'status' => $status ),
			array( 'id' => (int) $id )
		);
	}

	/**
	 * Rows for the admin list, most-hit first.
	 *
	 * @param array $args {
	 *     @type string $status   Status filter, or 'any'.
	 *     @type string $search   Substring of the path.
	 *     @type int    $per_page Rows per page.
	 *     @type int    $page     1-based page.
	 * }
	 * @return array
	 */
	public static function find( array $args = array() ) {
		global $wpdb;

		$table    = Tables::not_found();
		$per_page = max( 1, min( 200, isset( $args['per_page'] ) ? (int) $args['per_page'] : 20 ) );
		$offset   = ( max( 1, isset( $args['page'] ) ? (int) $args['page'] : 1 ) - 1 ) * $per_page;

		$where  = array();
		$params = array();

		$status = isset( $args['status'] ) ? (string) $args['status'] : self::STATUS_NEW;

		if ( 'any' !== $status ) {
			$where[]  = 'status = %s';
			$params[] = in_array( $status, array( self::STATUS_NEW, self::STATUS_IGNORED, self::STATUS_REDIRECTED, self::STATUS_RESOLVED ), true )
				? $status
				: self::STATUS_NEW;
		}

		if ( ! empty( $args['search'] ) ) {
			$where[]  = 'normalized_path LIKE %s';
			$params[] = '%' . $wpdb->esc_like( (string) $args['search'] ) . '%';
		}

		$clause = empty( $where ) ? '' : 'WHERE ' . implode( ' AND ', $where );
		$params = array_merge( $params, array( $per_page, $offset ) );

		return (array) $wpdb->get_results(
			// Every clause in $where contributes exactly one placeholder and
			// pushes exactly one value onto $params, in the same order, so the
			// counts match by construction - not visible through the implode().
			// phpcs:ignore WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber
			$wpdb->prepare(
				"SELECT * FROM {$table} {$clause} ORDER BY hit_count DESC, last_seen_at DESC LIMIT %d OFFSET %d",
				$params
			)
		);
	}

	/**
	 * How many rows match a status.
	 *
	 * @param string $status Status, or 'any'.
	 * @return int
	 */
	public static function count( $status = self::STATUS_NEW ) {
		global $wpdb;

		$table = Tables::not_found();

		if ( 'any' === $status ) {
			return (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" );
		}

		return (int) $wpdb->get_var(
			$wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE status = %s", (string) $status )
		);
	}

	/**
	 * Current UTC timestamp in MySQL format.
	 *
	 * @return string
	 */
	private static function now() {
		return function_exists( 'current_time' ) ? current_time( 'mysql', true ) : gmdate( 'Y-m-d H:i:s' );
	}

	/**
	 * Fit a value into a varchar(255) column without splitting a UTF-8 byte.
	 *
	 * @param string $value Raw value.
	 * @return string
	 */
	private static function trim255( $value ) {
		$value = (string) $value;

		if ( strlen( $value ) <= 255 ) {
			return $value;
		}

		return function_exists( 'mb_strcut' ) ? mb_strcut( $value, 0, 255, 'UTF-8' ) : substr( $value, 0, 255 );
	}
}
