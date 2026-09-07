<?php namespace RealTimeAutoFindReplace\Maintenance\Data;

use RealTimeAutoFindReplace\Maintenance\Data\Schema\Tables;

/**
 * How often each redirect fired, by day.
 *
 * The free plugin owns the data layer, so the repository lives here even though
 * only pro currently writes to it - the same arrangement as `LinkCheckRepository`.
 *
 * Two decisions shape it, and both are about this being the only thing in the
 * platform that writes on a **front-end** request:
 *
 * - **One row per rule per day, upserted.** The table's size follows the number
 *   of rules and how long they have existed, never the amount of traffic. A
 *   redirect that fires a million times in a day is one row updated a million
 *   times, and an UPDATE on a unique key is about as cheap as a write gets.
 * - **`INSERT … ON DUPLICATE KEY UPDATE`, never select-then-insert.** Two
 *   requests hitting the same redirect in the same moment is the normal case,
 *   not the edge case, and the unique index is what makes that safe.
 *
 * @package Maintenance
 * @since 1.11.0
 * @author M.Tuhin <info@codesolz.net>
 */

if ( ! defined( 'CS_RTAFAR_VERSION' ) ) {
	exit;
}

class RedirectHitRepository {

	/**
	 * Count one hit against a rule, today.
	 *
	 * @param int    $redirect_id Redirect id.
	 * @param string $date        Date, Y-m-d in UTC. Defaults to today.
	 * @return bool
	 */
	public static function record( $redirect_id, $date = '' ) {
		global $wpdb;

		$redirect_id = (int) $redirect_id;

		if ( $redirect_id <= 0 ) {
			return false;
		}

		$date  = '' === $date ? gmdate( 'Y-m-d' ) : (string) $date;
		$table = Tables::redirect_hits();

		// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$ok = $wpdb->query(
			$wpdb->prepare(
				"INSERT INTO {$table} ( redirect_id, hit_date, hits )
				 VALUES ( %d, %s, 1 )
				 ON DUPLICATE KEY UPDATE hits = hits + 1",
				$redirect_id,
				$date
			)
		);
		// phpcs:enable

		return false !== $ok;
	}

	/**
	 * Hits per day for one rule.
	 *
	 * @param int $redirect_id Redirect id.
	 * @param int $days        How far back to look.
	 * @return array date => hits, oldest first.
	 */
	public static function series( $redirect_id, $days = 30 ) {
		global $wpdb;

		$redirect_id = (int) $redirect_id;
		$days        = max( 1, min( 365, (int) $days ) );

		if ( $redirect_id <= 0 ) {
			return array();
		}

		$table = Tables::redirect_hits();
		$since = gmdate( 'Y-m-d', time() - ( $days * DAY_IN_SECONDS ) );

		// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT hit_date, hits FROM {$table}
				 WHERE redirect_id = %d AND hit_date >= %s
				 ORDER BY hit_date ASC",
				$redirect_id,
				$since
			)
		);
		// phpcs:enable

		$out = array();

		foreach ( (array) $rows as $row ) {
			$out[ (string) $row->hit_date ] = (int) $row->hits;
		}

		return $out;
	}

	/**
	 * The rules that fired most in a period.
	 *
	 * @param int $days  How far back to look.
	 * @param int $limit Rules to return.
	 * @return array List of array( redirect_id, hits ).
	 */
	public static function busiest( $days = 30, $limit = 10 ) {
		global $wpdb;

		$days  = max( 1, min( 365, (int) $days ) );
		$limit = max( 1, min( 100, (int) $limit ) );
		$table = Tables::redirect_hits();
		$since = gmdate( 'Y-m-d', time() - ( $days * DAY_IN_SECONDS ) );

		// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT redirect_id, SUM( hits ) AS total FROM {$table}
				 WHERE hit_date >= %s
				 GROUP BY redirect_id
				 ORDER BY total DESC
				 LIMIT %d",
				$since,
				$limit
			)
		);
		// phpcs:enable

		$out = array();

		foreach ( (array) $rows as $row ) {
			$out[] = array(
				'redirect_id' => (int) $row->redirect_id,
				'hits'        => (int) $row->total,
			);
		}

		return $out;
	}

	/**
	 * Total hits in a period.
	 *
	 * @param int $days How far back to look.
	 * @return int
	 */
	public static function total( $days = 30 ) {
		global $wpdb;

		$days  = max( 1, min( 365, (int) $days ) );
		$table = Tables::redirect_hits();
		$since = gmdate( 'Y-m-d', time() - ( $days * DAY_IN_SECONDS ) );

		// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		return (int) $wpdb->get_var(
			$wpdb->prepare( "SELECT SUM( hits ) FROM {$table} WHERE hit_date >= %s", $since )
		);
		// phpcs:enable
	}

	/**
	 * Forget everything older than a date.
	 *
	 * @param string $before Date, Y-m-d.
	 * @param int    $limit  Rows deleted per call.
	 * @return int Rows deleted.
	 */
	public static function prune( $before, $limit = 500 ) {
		global $wpdb;

		$table = Tables::redirect_hits();
		$limit = max( 1, min( 5000, (int) $limit ) );

		// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		return (int) $wpdb->query(
			$wpdb->prepare( "DELETE FROM {$table} WHERE hit_date < %s LIMIT %d", (string) $before, $limit )
		);
		// phpcs:enable
	}

	/**
	 * Drop everything recorded for one rule.
	 *
	 * Called when the rule itself is deleted: counts for a rule that no longer
	 * exists are just a leak.
	 *
	 * @param int $redirect_id Redirect id.
	 * @return int Rows deleted.
	 */
	public static function forget( $redirect_id ) {
		global $wpdb;

		$redirect_id = (int) $redirect_id;

		if ( $redirect_id <= 0 ) {
			return 0;
		}

		$table = Tables::redirect_hits();

		// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		return (int) $wpdb->query(
			$wpdb->prepare( "DELETE FROM {$table} WHERE redirect_id = %d", $redirect_id )
		);
		// phpcs:enable
	}
}
