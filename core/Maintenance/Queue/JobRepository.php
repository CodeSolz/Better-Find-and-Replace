<?php namespace RealTimeAutoFindReplace\Maintenance\Queue;

use RealTimeAutoFindReplace\Maintenance\Data\Schema\Tables;
use RealTimeAutoFindReplace\Maintenance\Support\Logger;

/**
 * The job table.
 *
 * Claiming is the whole design. A worker does not read a job and then mark it
 * taken - it writes the claim first, conditionally, and only works on rows the
 * database confirms it won:
 *
 *     UPDATE ... SET claim_token = %s WHERE status = 'pending' AND claim_token IS NULL
 *
 * Two workers running at the same instant both issue that statement; MySQL
 * serialises them, and the loser updates nothing. A transient-based lock cannot
 * make that promise: transients race, and on a site with a persistent object
 * cache they can be visible to one process and not another.
 *
 * Completed jobs are deleted rather than marked done. job_key is UNIQUE, so a
 * finished job that lingered would permanently block the same work from ever
 * being queued again.
 *
 * @package Maintenance
 * @since 1.10.0
 * @author M.Tuhin <info@codesolz.net>
 */

if ( ! defined( 'CS_RTAFAR_VERSION' ) ) {
	exit;
}

// Table names in this file always come from Schema\Tables, which composes them
// from $wpdb->prefix plus a literal - no request data ever reaches an SQL
// identifier, while every VALUE still travels as a placeholder through
// $wpdb->prepare(). A repository is also the one layer where direct, uncached
// queries are the point rather than an oversight. Disabled for the file rather
// than line by line because a multi-line statement silently escapes a per-line
// annotation, which is how these end up unreviewed.
// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery
// phpcs:disable WordPress.DB.DirectDatabaseQuery.NoCaching

class JobRepository {

	const STATUS_PENDING = 'pending';
	const STATUS_CLAIMED = 'claimed';
	const STATUS_FAILED  = 'failed';

	/** Attempts before a job is parked as failed. */
	const MAX_ATTEMPTS = 3;

	/** Minutes after which a claim is presumed abandoned. */
	const STALE_MINUTES = 10;

	/**
	 * The deterministic identity of a job.
	 *
	 * Callers that want the same logical work to be queueable again later must
	 * include something that varies - a scan id, usually - because job_key is
	 * UNIQUE and a pending job with that key is exactly what stops a retried
	 * enqueue creating a second copy.
	 *
	 * @param string $type    Job type.
	 * @param array  $payload Job payload.
	 * @return string 40-char hex.
	 */
	public static function key( $type, array $payload = array() ) {
		ksort( $payload );

		return sha1( (string) $type . '|' . wp_json_encode( $payload ) );
	}

	/**
	 * Add a job unless an identical one is already waiting.
	 *
	 * @param string $type    Job type.
	 * @param array  $payload Small payload - ids and cursors, never content.
	 * @param array  $args {
	 *     @type int    $delay   Seconds before the job may run.
	 *     @type string $job_key Explicit key, when the default is not distinct enough.
	 * }
	 * @return array array( 'queued' => bool, 'id' => int, 'reason' => string )
	 */
	public static function enqueue( $type, array $payload = array(), array $args = array() ) {
		global $wpdb;

		$type = (string) $type;

		if ( '' === $type ) {
			return array(
				'queued' => false,
				'id'     => 0,
				'reason' => 'missing_type',
			);
		}

		$job_key = isset( $args['job_key'] ) && '' !== $args['job_key']
			? (string) $args['job_key']
			: self::key( $type, $payload );

		$delay = isset( $args['delay'] ) ? max( 0, (int) $args['delay'] ) : 0;
		$now   = self::now();

		// Hitting the unique index is an expected outcome when a scan re-queues
		// itself, not an error worth logging.
		$suppress = $wpdb->suppress_errors( true );

		$ok = $wpdb->insert(
			Tables::jobs(),
			array(
				'job_key'      => $job_key,
				'job_type'     => $type,
				'payload'      => wp_json_encode( $payload ),
				'status'       => self::STATUS_PENDING,
				'attempts'     => 0,
				'claim_token'  => null,
				'available_at' => $delay > 0 ? self::in_seconds( $delay ) : $now,
				'created_at'   => $now,
				'updated_at'   => $now,
			)
		);

		$wpdb->suppress_errors( $suppress );

		if ( ! $ok ) {
			return array(
				'queued' => false,
				'id'     => 0,
				'reason' => 'duplicate',
			);
		}

		return array(
			'queued' => true,
			'id'     => (int) $wpdb->insert_id,
			'reason' => '',
		);
	}

	/**
	 * Take ownership of up to $limit due jobs.
	 *
	 * The claim is written before anything is read back, so the rows returned
	 * are provably ours.
	 *
	 * @param int $limit Maximum jobs to claim.
	 * @return array Job rows, possibly empty.
	 */
	public static function claim( $limit = 5 ) {
		global $wpdb;

		$limit = max( 1, min( 50, (int) $limit ) );
		$token = self::token();
		$table = Tables::jobs();
		$now   = self::now();

		$wpdb->query(
			$wpdb->prepare(
				"UPDATE {$table}
				 SET claim_token = %s, claimed_at = %s, status = %s, attempts = attempts + 1, updated_at = %s
				 WHERE status = %s AND claim_token IS NULL AND available_at <= %s
				 ORDER BY id ASC
				 LIMIT %d",
				array( $token, $now, self::STATUS_CLAIMED, $now, self::STATUS_PENDING, $now, $limit )
			)
		);

		if ( ( (int) $wpdb->rows_affected ) < 1 ) {
			return array();
		}

		$rows = $wpdb->get_results(
			$wpdb->prepare( "SELECT * FROM {$table} WHERE claim_token = %s ORDER BY id ASC", $token )
		);

		return (array) $rows;
	}

	/**
	 * The job is done. Remove it.
	 *
	 * @param int $id Job id.
	 * @return bool
	 */
	public static function complete( $id ) {
		global $wpdb;

		$deleted = $wpdb->delete( Tables::jobs(), array( 'id' => (int) $id ) );

		return (bool) $deleted;
	}

	/**
	 * Hand a claimed job back untouched.
	 *
	 * For a job that was claimed but never started - the tick ran out of time
	 * before reaching it. The attempt counter is wound back, because a job that
	 * did not run has not failed, and three unlucky ticks must not park work
	 * that was never attempted.
	 *
	 * @param object $job Job row as returned by claim().
	 * @return bool
	 */
	public static function release( $job ) {
		global $wpdb;

		$id = isset( $job->id ) ? (int) $job->id : 0;

		if ( $id <= 0 ) {
			return false;
		}

		$table = Tables::jobs();

		// GREATEST() keeps the counter at zero rather than going negative if a
		// row is ever released more times than it was claimed.
		$wpdb->query(
			$wpdb->prepare(
				"UPDATE {$table}
				 SET status = %s, claim_token = NULL, claimed_at = NULL,
				     attempts = GREATEST(0, attempts - 1), updated_at = %s
				 WHERE id = %d",
				array( self::STATUS_PENDING, self::now(), $id )
			)
		);

		return ( (int) $wpdb->rows_affected ) > 0;
	}

	/**
	 * The job failed. Put it back, or park it.
	 *
	 * Backoff is exponential in the attempt count, so a provider that is down
	 * is not hammered by a queue that keeps waking up.
	 *
	 * @param object $job   Job row as returned by claim().
	 * @param string $error Short reason.
	 * @return void
	 */
	public static function fail( $job, $error = '' ) {
		global $wpdb;

		$id       = isset( $job->id ) ? (int) $job->id : 0;
		$attempts = isset( $job->attempts ) ? (int) $job->attempts : 1;

		if ( $id <= 0 ) {
			return;
		}

		$error = substr( (string) $error, 0, 255 );

		if ( $attempts >= self::MAX_ATTEMPTS ) {
			$wpdb->update(
				Tables::jobs(),
				array(
					'status'      => self::STATUS_FAILED,
					'claim_token' => null,
					'last_error'  => $error,
					'updated_at'  => self::now(),
				),
				array( 'id' => $id )
			);

			Logger::log(
				'queue.parked',
				array(
					'id'       => $id,
					'type'     => isset( $job->job_type ) ? $job->job_type : '',
					'attempts' => $attempts,
				)
			);

			return;
		}

		$backoff = (int) min( 3600, 30 * pow( 2, max( 0, $attempts - 1 ) ) );

		$wpdb->update(
			Tables::jobs(),
			array(
				'status'       => self::STATUS_PENDING,
				'claim_token'  => null,
				'claimed_at'   => null,
				'available_at' => self::in_seconds( $backoff ),
				'last_error'   => $error,
				'updated_at'   => self::now(),
			),
			array( 'id' => $id )
		);
	}

	/**
	 * Return claims whose worker never came back.
	 *
	 * A PHP process killed mid-job leaves its rows claimed forever otherwise.
	 * attempts is not reset, so a job that reliably kills its worker still
	 * gets parked rather than looping.
	 *
	 * @param int $minutes Claim age that counts as abandoned.
	 * @return int Jobs released.
	 */
	public static function reclaim_stale( $minutes = self::STALE_MINUTES ) {
		global $wpdb;

		$table  = Tables::jobs();
		$cutoff = self::ago( max( 1, (int) $minutes ) );

		$wpdb->query(
			$wpdb->prepare(
				"UPDATE {$table}
				 SET status = %s, claim_token = NULL, claimed_at = NULL, updated_at = %s
				 WHERE status = %s AND claimed_at IS NOT NULL AND claimed_at < %s",
				array( self::STATUS_PENDING, self::now(), self::STATUS_CLAIMED, $cutoff )
			)
		);

		$released = (int) $wpdb->rows_affected;

		if ( $released > 0 ) {
			Logger::log( 'queue.reclaimed', array( 'jobs' => $released ) );
		}

		return $released;
	}

	/**
	 * How many jobs are waiting to run right now.
	 *
	 * @return int
	 */
	public static function pending_count() {
		global $wpdb;

		$table = Tables::jobs();

		return (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$table} WHERE status = %s AND available_at <= %s",
				array( self::STATUS_PENDING, self::now() )
			)
		);
	}

	/**
	 * Counts per status, for diagnostics.
	 *
	 * @return array status => count
	 */
	public static function counts() {
		global $wpdb;

		$table = Tables::jobs();

		$rows = $wpdb->get_results( "SELECT status, COUNT(*) AS total FROM {$table} GROUP BY status" );

		$out = array();

		foreach ( (array) $rows as $row ) {
			$out[ $row->status ] = (int) $row->total;
		}

		return $out;
	}

	/**
	 * Remove parked jobs, so a fixed problem can be re-queued.
	 *
	 * @param string $type Optional job type to limit to.
	 * @return int Rows removed.
	 */
	public static function purge_failed( $type = '' ) {
		global $wpdb;

		$table = Tables::jobs();
		$type  = (string) $type;

		if ( '' === $type ) {
			$wpdb->query( $wpdb->prepare( "DELETE FROM {$table} WHERE status = %s", self::STATUS_FAILED ) );
		} else {
			$wpdb->query(
				$wpdb->prepare(
					"DELETE FROM {$table} WHERE status = %s AND job_type = %s",
					array( self::STATUS_FAILED, $type )
				)
			);
		}

		return (int) $wpdb->rows_affected;
	}

	/**
	 * A claim token.
	 *
	 * @return string 32 chars.
	 */
	private static function token() {
		if ( function_exists( 'wp_generate_password' ) ) {
			return substr( md5( wp_generate_password( 24, false ) . microtime( true ) ), 0, 32 );
		}

		return substr( md5( uniqid( 'bfrq', true ) ), 0, 32 );
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
	 * A UTC timestamp N seconds ahead.
	 *
	 * @param int $seconds Seconds forward.
	 * @return string
	 */
	private static function in_seconds( $seconds ) {
		return gmdate( 'Y-m-d H:i:s', time() + (int) $seconds );
	}

	/**
	 * A UTC timestamp N minutes back.
	 *
	 * @param int $minutes Minutes back.
	 * @return string
	 */
	private static function ago( $minutes ) {
		return gmdate( 'Y-m-d H:i:s', time() - ( (int) $minutes * 60 ) );
	}
}
