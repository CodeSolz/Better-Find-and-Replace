<?php namespace RealTimeAutoFindReplace\Maintenance\Data;

use RealTimeAutoFindReplace\Maintenance\Data\Schema\Tables;

/**
 * What we know about each URL we have checked.
 *
 * One row per URL, updated in place - not an append-only log. The question this
 * table answers is "what is the current state of this URL, and when should it be
 * looked at again", and a history of every check would make that slower to
 * answer and unbounded in size.
 *
 * It lives in the free plugin even though the free tier does not make HTTP
 * requests, because the free plugin owns the data layer (P1) and the pro
 * external checker consumes it. Pro writing to a table free does not own would
 * invert that and leave the schema with two masters.
 *
 * `consecutive_failures` is the important column. A link is not broken because
 * one request timed out - a network hiccup, a slow origin, a firewall having a
 * moment. It is broken when it fails twice, in different runs, and this counter
 * plus `next_check_after` is what makes that possible without holding state in
 * memory across requests.
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

class LinkCheckRepository {

	/** Failures needed before a URL is called broken. */
	const FAILURES_BEFORE_BROKEN = 2;

	/** How long a healthy URL is trusted for, in seconds. */
	const OK_TTL = 604800;

	/** Base backoff after a failure, in seconds. Doubles per consecutive failure. */
	const FAILURE_BACKOFF = 3600;

	/**
	 * Record the outcome of one check.
	 *
	 * Idempotent by URL: the unique index on `url_hash` arbitrates, so two
	 * workers checking the same URL cannot create two rows.
	 *
	 * @param string $url_hash Normalised URL key, 40 chars.
	 * @param array  $result {
	 *     @type string $url          The URL as checked.
	 *     @type int    $http_status  Status code, or 0 when no response arrived.
	 *     @type string $error_type   timeout | dns | ssl | rate_limited | refused | redirect_loop | ''.
	 *     @type string $final_url    URL after redirects.
	 *     @type int    $time_ms      Round trip in milliseconds.
	 *     @type bool   $healthy      Whether the URL answered acceptably.
	 * }
	 * @return array {
	 *     @type int  $failures Consecutive failures after this check.
	 *     @type bool $broken   Whether it has now failed enough times to report.
	 * }
	 */
	public static function record( $url_hash, array $result ) {
		global $wpdb;

		$url_hash = (string) $url_hash;

		if ( '' === $url_hash ) {
			return array(
				'failures' => 0,
				'broken'   => false,
			);
		}

		$healthy = ! empty( $result['healthy'] );
		$table   = Tables::link_checks();
		$now     = self::now();

		$previous = self::get( $url_hash );
		$failures = $healthy ? 0 : ( $previous ? (int) $previous->consecutive_failures + 1 : 1 );

		$data = array(
			'url_hash'             => $url_hash,
			'url'                  => self::trim255( isset( $result['url'] ) ? $result['url'] : '' ),
			'http_status'          => isset( $result['http_status'] ) ? (int) $result['http_status'] : 0,
			'error_type'           => isset( $result['error_type'] ) ? substr( (string) $result['error_type'], 0, 20 ) : '',
			'final_url'            => self::trim255( isset( $result['final_url'] ) ? $result['final_url'] : '' ),
			'response_time_ms'     => isset( $result['time_ms'] ) ? max( 0, (int) $result['time_ms'] ) : 0,
			'consecutive_failures' => $failures,
			'checked_at'           => $now,
			'next_check_after'     => self::next_check( $healthy, $failures ),
		);

		if ( $previous ) {
			$wpdb->update( $table, $data, array( 'url_hash' => $url_hash ) );
		} else {
			$suppress = $wpdb->suppress_errors( true );
			$inserted = $wpdb->insert( $table, $data );
			$wpdb->suppress_errors( $suppress );

			if ( ! $inserted ) {
				// Lost the race; the row exists now, so update it instead.
				$wpdb->update( $table, $data, array( 'url_hash' => $url_hash ) );
			}
		}

		return array(
			'failures' => $failures,
			'broken'   => self::is_broken( $failures ),
		);
	}

	/**
	 * Has this URL failed enough times to be reported?
	 *
	 * @param int $failures Consecutive failures.
	 * @return bool
	 */
	public static function is_broken( $failures ) {
		return (int) $failures >= self::failures_before_broken();
	}

	/**
	 * How many consecutive failures before a URL is called broken.
	 *
	 * @return int
	 */
	public static function failures_before_broken() {
		$needed = self::FAILURES_BEFORE_BROKEN;

		if ( function_exists( 'apply_filters' ) ) {
			/**
			 * Filter how many failed checks are needed before a link is broken.
			 *
			 * Below two, a single network hiccup becomes a reported problem.
			 *
			 * @param int $needed Consecutive failures.
			 */
			$needed = (int) apply_filters( 'bfr_link_failures_before_broken', $needed );
		}

		return max( 1, $needed );
	}

	/**
	 * What we last recorded for a URL.
	 *
	 * @param string $url_hash Normalised URL key.
	 * @return object|null
	 */
	public static function get( $url_hash ) {
		global $wpdb;

		$table = Tables::link_checks();

		return $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM {$table} WHERE url_hash = %s", (string) $url_hash )
		);
	}

	/**
	 * Is this URL due for a check?
	 *
	 * A URL that answered recently is trusted for a while; one that just failed
	 * is backed off. Both keep the checker off hosts it has no new question for.
	 *
	 * @param string $url_hash Normalised URL key.
	 * @return bool
	 */
	public static function is_due( $url_hash ) {
		$row = self::get( $url_hash );

		if ( ! $row || empty( $row->next_check_after ) ) {
			return true;
		}

		return strtotime( $row->next_check_after . ' UTC' ) <= time();
	}

	/**
	 * Forget a URL, so the next scan checks it fresh.
	 *
	 * Used after a fix: the old verdict is about a URL nobody links to now.
	 *
	 * @param string $url_hash Normalised URL key.
	 * @return bool
	 */
	public static function forget( $url_hash ) {
		global $wpdb;

		return (bool) $wpdb->delete( Tables::link_checks(), array( 'url_hash' => (string) $url_hash ) );
	}

	/**
	 * Remove check records older than a cut-off.
	 *
	 * @param string $before MySQL datetime, UTC.
	 * @param int    $limit  Maximum rows per call.
	 * @return int Rows deleted.
	 */
	public static function prune( $before, $limit = 500 ) {
		global $wpdb;

		$table = Tables::link_checks();

		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$table} WHERE checked_at < %s LIMIT %d",
				array( (string) $before, max( 1, (int) $limit ) )
			)
		);

		return (int) $wpdb->rows_affected;
	}

	/**
	 * When this URL should be looked at again.
	 *
	 * @param bool $healthy  Whether it answered acceptably.
	 * @param int  $failures Consecutive failures so far.
	 * @return string MySQL datetime, UTC.
	 */
	private static function next_check( $healthy, $failures ) {
		if ( $healthy ) {
			return gmdate( 'Y-m-d H:i:s', time() + self::OK_TTL );
		}

		// Exponential, capped at a day: a host that is down stays down for a
		// while, and re-asking every minute helps nobody.
		$delay = min( 86400, self::FAILURE_BACKOFF * pow( 2, max( 0, $failures - 1 ) ) );

		return gmdate( 'Y-m-d H:i:s', time() + (int) $delay );
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
