<?php namespace RealTimeAutoFindReplace\Maintenance\Data;

use RealTimeAutoFindReplace\Maintenance\Data\Schema\Tables;

/**
 * Scan runs: what is being scanned, how far it got, and whether it is alive.
 *
 * A scan on a large site cannot finish in one request, so a run is a durable
 * record rather than a variable. Three columns carry the weight:
 *
 *   cursor_position - where to resume. Written every batch, so a killed PHP
 *                     process costs one batch, not the whole scan.
 *   heartbeat_at    - when a worker last touched it. A run still marked
 *                     "running" with an old heartbeat was killed, and can be
 *                     reclaimed without anyone noticing it died.
 *   status          - moved by conditional UPDATEs, so a re-queued job cannot
 *                     restart a completed run.
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

class ScanRunRepository {

	const STATUS_QUEUED    = 'queued';
	const STATUS_RUNNING   = 'running';
	const STATUS_PAUSED    = 'paused';
	const STATUS_COMPLETED = 'completed';
	const STATUS_FAILED    = 'failed';
	const STATUS_CANCELLED = 'cancelled';

	/**
	 * Minutes without a heartbeat after which a running scan is presumed dead.
	 */
	const STALE_MINUTES = 10;

	/**
	 * Every status a run may hold.
	 *
	 * @return array
	 */
	public static function statuses() {
		return array(
			self::STATUS_QUEUED,
			self::STATUS_RUNNING,
			self::STATUS_PAUSED,
			self::STATUS_COMPLETED,
			self::STATUS_FAILED,
			self::STATUS_CANCELLED,
		);
	}

	/**
	 * Statuses that mean the run is over, whatever the outcome.
	 *
	 * @return array
	 */
	public static function terminal_statuses() {
		return array( self::STATUS_COMPLETED, self::STATUS_FAILED, self::STATUS_CANCELLED );
	}

	/**
	 * Open a new run.
	 *
	 * @param string $scan_type Module scan type, e.g. 'link_health'.
	 * @param array  $args {
	 *     @type string $scope       'full', 'post', 'post_type', 'selection'.
	 *     @type int    $total_items Known total, when it is knowable.
	 *     @type array  $metadata    Settings the run was started with.
	 * }
	 * @return int New run id, or 0 on failure.
	 */
	public static function start( $scan_type, array $args = array() ) {
		global $wpdb;

		$now      = self::now();
		$metadata = isset( $args['metadata'] ) ? $args['metadata'] : array();

		$ok = $wpdb->insert(
			Tables::scan_runs(),
			array(
				'scan_type'       => (string) $scan_type,
				'scope'           => isset( $args['scope'] ) ? (string) $args['scope'] : 'full',
				'status'          => self::STATUS_RUNNING,
				'total_items'     => isset( $args['total_items'] ) ? max( 0, (int) $args['total_items'] ) : 0,
				'processed_items' => 0,
				'issues_found'    => 0,
				'cursor_position' => '',
				'started_at'      => $now,
				'heartbeat_at'    => $now,
				'metadata'        => is_array( $metadata ) ? wp_json_encode( $metadata ) : (string) $metadata,
			)
		);

		if ( ! $ok ) {
			return 0;
		}

		$id = (int) $wpdb->insert_id;

		if ( $id > 0 && function_exists( 'do_action' ) ) {
			/**
			 * Fires when a scan run opens.
			 *
			 * @param int    $id        Run id.
			 * @param string $scan_type Scan type.
			 */
			do_action( 'bfr_maintenance_scan_started', $id, (string) $scan_type );
		}

		return $id;
	}

	/**
	 * Record progress and keep the run alive.
	 *
	 * Called once per batch. Counters are incremented in SQL rather than read
	 * and written, so two workers on the same run cannot lose each other's
	 * progress.
	 *
	 * @param int    $id        Run id.
	 * @param string $cursor    Resume point after this batch.
	 * @param int    $processed Items processed in this batch.
	 * @param int    $found     Issues found in this batch.
	 * @return bool
	 */
	public static function advance( $id, $cursor, $processed = 0, $found = 0 ) {
		global $wpdb;

		$id = (int) $id;

		if ( $id <= 0 ) {
			return false;
		}

		$table = Tables::scan_runs();

		$wpdb->query(
			$wpdb->prepare(
				"UPDATE {$table}
				 SET cursor_position = %s,
				     processed_items = processed_items + %d,
				     issues_found = issues_found + %d,
				     heartbeat_at = %s
				 WHERE id = %d AND status = %s",
				array(
					substr( (string) $cursor, 0, 64 ),
					max( 0, (int) $processed ),
					max( 0, (int) $found ),
					self::now(),
					$id,
					self::STATUS_RUNNING,
				)
			)
		);

		return ( (int) $wpdb->rows_affected ) > 0;
	}

	/**
	 * Touch the heartbeat without changing anything else.
	 *
	 * For a batch that took a long time but processed nothing - a page of
	 * skipped posts, say - which would otherwise look like a dead worker.
	 *
	 * @param int $id Run id.
	 * @return bool
	 */
	public static function heartbeat( $id ) {
		return self::advance( $id, self::cursor_of( $id ), 0, 0 );
	}

	/**
	 * Move a run to a new status, from an expected one.
	 *
	 * @param int    $id   Run id.
	 * @param string $to   Target status.
	 * @param array  $from Allowed current statuses. Empty means any.
	 * @return bool True when this call moved it.
	 */
	public static function transition( $id, $to, array $from = array() ) {
		global $wpdb;

		$id = (int) $id;
		$to = (string) $to;

		if ( $id <= 0 || ! in_array( $to, self::statuses(), true ) ) {
			return false;
		}

		$table = Tables::scan_runs();
		$from  = array_values( array_intersect( $from, self::statuses() ) );

		$completed_sql = in_array( $to, self::terminal_statuses(), true ) ? '%s' : 'NULL';

		$sql  = "UPDATE {$table} SET status = %s, heartbeat_at = %s, completed_at = {$completed_sql} WHERE id = %d";
		$args = in_array( $to, self::terminal_statuses(), true )
			? array( $to, self::now(), self::now(), $id )
			: array( $to, self::now(), $id );

		if ( ! empty( $from ) ) {
			$sql .= ' AND status IN (' . implode( ',', array_fill( 0, count( $from ), '%s' ) ) . ')';
			$args = array_merge( $args, $from );
		}

		// $sql is assembled from literals plus placeholders directly above and
		// is prepared on this line; the sniff cannot follow the variable.
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$wpdb->query( $wpdb->prepare( $sql, $args ) );

		$moved = ( (int) $wpdb->rows_affected ) > 0;

		if ( $moved && function_exists( 'do_action' ) ) {
			if ( self::STATUS_COMPLETED === $to ) {
				/**
				 * Fires when a scan run completes.
				 *
				 * @param int $id Run id.
				 */
				do_action( 'bfr_maintenance_scan_completed', $id );
			} elseif ( self::STATUS_FAILED === $to ) {
				/**
				 * Fires when a scan run fails.
				 *
				 * @param int $id Run id.
				 */
				do_action( 'bfr_maintenance_scan_failed', $id );
			}
		}

		return $moved;
	}

	/**
	 * Finish a run.
	 *
	 * @param int $id Run id.
	 * @return bool
	 */
	public static function complete( $id ) {
		return self::transition( $id, self::STATUS_COMPLETED, array( self::STATUS_RUNNING, self::STATUS_PAUSED ) );
	}

	/**
	 * Fail a run, recording why.
	 *
	 * @param int    $id     Run id.
	 * @param string $reason Short reason, stored in metadata.
	 * @return bool
	 */
	public static function fail( $id, $reason = '' ) {
		global $wpdb;

		$moved = self::transition( $id, self::STATUS_FAILED, array( self::STATUS_RUNNING, self::STATUS_PAUSED, self::STATUS_QUEUED ) );

		if ( $moved && '' !== $reason ) {
			$wpdb->update(
				Tables::scan_runs(),
				array( 'metadata' => wp_json_encode( array( 'error' => substr( (string) $reason, 0, 500 ) ) ) ),
				array( 'id' => (int) $id )
			);
		}

		return $moved;
	}

	/**
	 * One run by id.
	 *
	 * @param int $id Run id.
	 * @return object|null
	 */
	public static function get( $id ) {
		global $wpdb;

		$table = Tables::scan_runs();

		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", (int) $id ) );
	}

	/**
	 * The most recent run of a type, whatever its status.
	 *
	 * @param string $scan_type Scan type.
	 * @return object|null
	 */
	public static function latest( $scan_type ) {
		global $wpdb;

		$table = Tables::scan_runs();

		return $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM {$table} WHERE scan_type = %s ORDER BY id DESC LIMIT 1", (string) $scan_type )
		);
	}

	/**
	 * The active run of a type, if there is one.
	 *
	 * @param string $scan_type Scan type.
	 * @return object|null
	 */
	public static function active( $scan_type ) {
		global $wpdb;

		$table = Tables::scan_runs();

		return $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE scan_type = %s AND status IN (%s, %s) ORDER BY id DESC LIMIT 1",
				array( (string) $scan_type, self::STATUS_RUNNING, self::STATUS_PAUSED )
			)
		);
	}

	/**
	 * Is this run genuinely still going?
	 *
	 * The `status` column cannot answer this on its own. A worker killed
	 * mid-batch - a fatal, a timeout, a process the host reaped - never
	 * reaches the line that writes a terminal status, so the row says
	 * "running" for ever. The heartbeat is the part that stops.
	 *
	 * Where it is read is what makes it matter. The scan button is disabled
	 * while a run looks alive, and `reclaim_stale()` is only reached from
	 * `Scanner::start()` - so a dead run believed alive disables the one
	 * control that would have reclaimed it, and nothing short of editing the
	 * database gets the screen out of that. Asking the heartbeat here is what
	 * breaks the deadlock.
	 *
	 * @param object|null $run     Run row, from get() or latest().
	 * @param int         $minutes Heartbeat age that counts as dead.
	 * @return bool
	 */
	public static function is_alive( $run, $minutes = self::STALE_MINUTES ) {
		if ( ! is_object( $run ) || ! isset( $run->status ) || self::STATUS_RUNNING !== $run->status ) {
			return false;
		}

		$heartbeat = isset( $run->heartbeat_at ) ? (string) $run->heartbeat_at : '';

		// Not "too young to have one" - start() writes a heartbeat before the
		// first job is queued, so its absence means the run died before any
		// worker ever reached it.
		if ( '' === $heartbeat || '0000-00-00 00:00:00' === $heartbeat ) {
			return false;
		}

		// Both sides are UTC in MySQL's own format, which compares correctly
		// as text.
		return $heartbeat >= self::ago( max( 1, (int) $minutes ) );
	}

	/**
	 * Fail runs whose worker died.
	 *
	 * Without this a crashed scan blocks the next one forever, because
	 * active() keeps finding it.
	 *
	 * @param int $minutes Heartbeat age that counts as dead.
	 * @return int Runs reclaimed.
	 */
	public static function reclaim_stale( $minutes = self::STALE_MINUTES ) {
		global $wpdb;

		$table  = Tables::scan_runs();
		$cutoff = self::ago( max( 1, (int) $minutes ) );

		$wpdb->query(
			$wpdb->prepare(
				"UPDATE {$table} SET status = %s, completed_at = %s
				 WHERE status = %s AND (heartbeat_at IS NULL OR heartbeat_at < %s)",
				array( self::STATUS_FAILED, self::now(), self::STATUS_RUNNING, $cutoff )
			)
		);

		return (int) $wpdb->rows_affected;
	}

	/**
	 * The stored cursor of a run.
	 *
	 * @param int $id Run id.
	 * @return string
	 */
	public static function cursor_of( $id ) {
		$run = self::get( $id );

		return $run && isset( $run->cursor_position ) ? (string) $run->cursor_position : '';
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
	 * A UTC timestamp N minutes in the past.
	 *
	 * @param int $minutes Minutes back.
	 * @return string
	 */
	private static function ago( $minutes ) {
		return gmdate( 'Y-m-d H:i:s', time() - ( (int) $minutes * MINUTE_IN_SECONDS ) );
	}
}
