<?php namespace RealTimeAutoFindReplace\Maintenance\Data;

use RealTimeAutoFindReplace\Maintenance\Data\Schema\Tables;
use RealTimeAutoFindReplace\Maintenance\Support\Priority;

/**
 * Reads and writes the shared issue model.
 *
 * Two rules drive every method here, and both exist because scans run in
 * resumable batches that can overlap.
 *
 * 1. Duplicate prevention is the UNIQUE index on issue_key, never a SELECT
 *    first. Two workers scanning the same post concurrently would both see
 *    "not there" and both insert; only the database can arbitrate. upsert()
 *    is therefore a single INSERT ... ON DUPLICATE KEY UPDATE.
 *
 * 2. Status changes are conditional UPDATEs checked by affected-rows. Reading
 *    a status and then acting on it leaves a window in which a double-clicked
 *    Ignore, or a re-queued job, applies the same transition twice.
 *
 * The upsert deliberately does not reset a user's decision: an issue the user
 * ignored stays ignored when the scanner finds it again, because otherwise
 * "ignore" would mean "hide until the next scan", which is worthless.
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

class IssueRepository {

	/** An issue nobody has dealt with yet. */
	const STATUS_OPEN = 'open';

	/** The user said they do not care. Survives rescans. */
	const STATUS_IGNORED = 'ignored';

	/** A fix is being applied right now. */
	const STATUS_FIXING = 'fixing';

	/** Gone: fixed, or no longer present. */
	const STATUS_RESOLVED = 'resolved';

	/** The world moved on and the record no longer describes reality. */
	const STATUS_STALE = 'stale';

	/**
	 * Every status an issue may hold.
	 *
	 * @return array
	 */
	public static function statuses() {
		return array(
			self::STATUS_OPEN,
			self::STATUS_IGNORED,
			self::STATUS_FIXING,
			self::STATUS_RESOLVED,
			self::STATUS_STALE,
		);
	}

	/**
	 * The deterministic identity of an issue.
	 *
	 * Deliberately excludes anything that changes when the surrounding content
	 * is edited. If an unrelated edit minted a new key, every rescan would
	 * resurrect issues the user had already ignored.
	 *
	 * @param string $type        Issue type.
	 * @param string $object_type Object kind, e.g. 'post'.
	 * @param int    $object_id   Object id.
	 * @param string $target      Normalised target - a URL key, a path, an anchor.
	 * @return string 40-char hex.
	 */
	public static function key( $type, $object_type, $object_id, $target ) {
		return sha1(
			strtolower( (string) $type ) . '|' .
			strtolower( (string) $object_type ) . '|' .
			(int) $object_id . '|' .
			(string) $target
		);
	}

	/**
	 * Record an issue, or refresh the one already recorded.
	 *
	 * One query, whichever happens. The return value distinguishes them using
	 * the row count MySQL reports for INSERT ... ON DUPLICATE KEY UPDATE:
	 * 1 means inserted, 2 means an existing row changed, 0 means an existing
	 * row was already identical.
	 *
	 * @param array $row {
	 *     @type string $issue_key   Optional; computed from the parts when absent.
	 *     @type string $type        Required.
	 *     @type string $subtype     Optional.
	 *     @type string $object_type Optional, default 'post'.
	 *     @type int    $object_id   Optional.
	 *     @type string $source_url  Optional.
	 *     @type string $target_url  Optional.
	 *     @type string $target_hash Optional.
	 *     @type int    $occurrences Optional, default 1.
	 *     @type int    $severity    Optional, 1-5, default 3.
	 *     @type float  $confidence  Optional.
	 *     @type int    $scan_id     Optional.
	 *     @type array  $priority    Optional factors for Priority::score().
	 *     @type array  $metadata    Optional; json-encoded.
	 * }
	 * @return array array( 'ok' => bool, 'created' => bool, 'issue_key' => string )
	 */
	public static function upsert( array $row ) {
		global $wpdb;

		$type = isset( $row['type'] ) ? (string) $row['type'] : '';

		if ( '' === $type ) {
			return array(
				'ok'        => false,
				'created'   => false,
				'issue_key' => '',
			);
		}

		$object_type = isset( $row['object_type'] ) ? (string) $row['object_type'] : 'post';
		$object_id   = isset( $row['object_id'] ) ? (int) $row['object_id'] : 0;
		$target_hash = isset( $row['target_hash'] ) ? (string) $row['target_hash'] : '';

		$issue_key = isset( $row['issue_key'] ) && '' !== $row['issue_key']
			? (string) $row['issue_key']
			: self::key( $type, $object_type, $object_id, '' !== $target_hash ? $target_hash : ( isset( $row['target_url'] ) ? $row['target_url'] : '' ) );

		$now         = self::now();
		$occurrences = isset( $row['occurrences'] ) ? max( 1, (int) $row['occurrences'] ) : 1;
		$severity    = isset( $row['severity'] ) ? (int) $row['severity'] : 3;
		$confidence  = isset( $row['confidence'] ) ? (float) $row['confidence'] : null;

		$factors = isset( $row['priority'] ) && is_array( $row['priority'] ) ? $row['priority'] : array();
		$factors = array_merge(
			array(
				'severity'    => $severity,
				'occurrences' => $occurrences,
				'confidence'  => null === $confidence ? 0 : $confidence,
				'age_days'    => 0,
			),
			$factors
		);

		$score    = Priority::score( $factors );
		$metadata = isset( $row['metadata'] ) ? $row['metadata'] : '';

		if ( is_array( $metadata ) ) {
			$metadata = wp_json_encode( $metadata );
		}

		$metadata = (string) $metadata;
		$table    = Tables::issues();

		// confidence is NULL for everything that did not come from a model,
		// and NULL cannot travel through a %s placeholder - prepare() turns it
		// into an empty string, which a decimal column rejects. The literal is
		// chosen here by code, never by a caller, so interpolating it is safe.
		$confidence_sql = null === $confidence ? 'NULL' : '%f';

		// The column list is a literal; every value is a placeholder. The
		// ON DUPLICATE clause is what makes a rescan idempotent, and the two
		// CASE expressions are what stop it overwriting a human decision.
		// resolved_at is assigned before status on purpose: MySQL evaluates
		// the SET list left to right, so reading `status` after assigning it
		// would read the new value.
		$sql = "INSERT INTO {$table}
			(issue_key, type, subtype, object_type, object_id, source_url, target_url, target_hash,
			 occurrences, status, severity, priority_score, confidence, scan_id,
			 first_seen_at, last_seen_at, metadata, created_at, updated_at)
			VALUES (%s, %s, %s, %s, %d, %s, %s, %s, %d, %s, %d, %d, {$confidence_sql}, %d, %s, %s, %s, %s, %s)
			ON DUPLICATE KEY UPDATE
				subtype = %s,
				source_url = %s,
				target_url = %s,
				target_hash = %s,
				occurrences = %d,
				severity = %d,
				priority_score = %d,
				confidence = {$confidence_sql},
				scan_id = %d,
				last_seen_at = %s,
				metadata = %s,
				updated_at = %s,
				resolved_at = CASE WHEN status = 'ignored' THEN resolved_at ELSE NULL END,
				status = CASE WHEN status = 'ignored' THEN 'ignored' ELSE 'open' END";

		$subtype    = isset( $row['subtype'] ) ? (string) $row['subtype'] : '';
		$source_url = isset( $row['source_url'] ) ? self::trim255( $row['source_url'] ) : '';
		$target_url = isset( $row['target_url'] ) ? self::trim255( $row['target_url'] ) : '';
		$scan_id    = isset( $row['scan_id'] ) ? (int) $row['scan_id'] : 0;

		// Built in three pieces so the confidence placeholder can drop out
		// entirely when the value is NULL, keeping placeholders and arguments
		// in step.
		$args = array( $issue_key, $type, $subtype, $object_type, $object_id, $source_url, $target_url, $target_hash, $occurrences, self::STATUS_OPEN, $severity, $score );

		if ( null !== $confidence ) {
			$args[] = $confidence;
		}

		$args = array_merge( $args, array( $scan_id, $now, $now, $metadata, $now, $now, $subtype, $source_url, $target_url, $target_hash, $occurrences, $severity, $score ) );

		if ( null !== $confidence ) {
			$args[] = $confidence;
		}

		$args = array_merge( $args, array( $scan_id, $now, $metadata, $now ) );

		// $sql is assembled from literals plus placeholders directly above and
		// is prepared on this line; the sniff cannot follow the variable.
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$result = $wpdb->query( $wpdb->prepare( $sql, $args ) );

		if ( false === $result ) {
			return array(
				'ok'        => false,
				'created'   => false,
				'issue_key' => $issue_key,
			);
		}

		$created = ( 1 === (int) $result );

		if ( $created && function_exists( 'do_action' ) ) {
			/**
			 * Fires when an issue is recorded for the first time.
			 *
			 * @param string $issue_key Deterministic issue identity.
			 * @param array  $row       The row as supplied by the module.
			 */
			do_action( 'bfr_maintenance_issue_created', $issue_key, $row );
		}

		return array(
			'ok'        => true,
			'created'   => $created,
			'issue_key' => $issue_key,
		);
	}

	/**
	 * Move an issue from one of several expected statuses to a new one.
	 *
	 * Conditional by design: the caller states what it believes the current
	 * status to be, and a caller that was wrong is told so rather than
	 * silently winning a race.
	 *
	 * @param int    $id   Issue id.
	 * @param string $to   Target status.
	 * @param array  $from Statuses the transition is allowed from. Empty means any.
	 * @return bool True when this call is the one that moved it.
	 */
	public static function transition( $id, $to, array $from = array() ) {
		global $wpdb;

		$id = (int) $id;
		$to = (string) $to;

		if ( $id <= 0 || ! in_array( $to, self::statuses(), true ) ) {
			return false;
		}

		$from = array_values( array_intersect( $from, self::statuses() ) );
		$now  = self::now();

		$table = Tables::issues();

		// Same reason as upsert(): NULL cannot travel through a placeholder,
		// and an empty string is not a valid datetime. The literal is chosen
		// here, not by a caller.
		$resolved_sql = self::STATUS_RESOLVED === $to ? '%s' : 'NULL';

		$sql  = "UPDATE {$table} SET status = %s, updated_at = %s, resolved_at = {$resolved_sql} WHERE id = %d";
		$args = self::STATUS_RESOLVED === $to
			? array( $to, $now, $now, $id )
			: array( $to, $now, $id );

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
			if ( self::STATUS_RESOLVED === $to ) {
				/**
				 * Fires when an issue is resolved.
				 *
				 * @param int $id Issue id.
				 */
				do_action( 'bfr_maintenance_issue_resolved', $id );
			} elseif ( self::STATUS_IGNORED === $to ) {
				/**
				 * Fires when an issue is ignored.
				 *
				 * @param int $id Issue id.
				 */
				do_action( 'bfr_maintenance_issue_ignored', $id );
			}
		}

		return $moved;
	}

	/**
	 * Close out issues for an object that the current scan no longer sees.
	 *
	 * This is how a fixed link disappears from the list without anyone
	 * clicking anything. Ignored issues are left alone - the user's decision
	 * outlives the problem.
	 *
	 * @param string $type        Issue type.
	 * @param string $object_type Object kind.
	 * @param int    $object_id   Object id.
	 * @param int    $scan_id     The scan that just ran.
	 * @return int Rows closed.
	 */
	public static function resolve_missing( $type, $object_type, $object_id, $scan_id ) {
		global $wpdb;

		$table = Tables::issues();
		$now   = self::now();

		$wpdb->query(
			$wpdb->prepare(
				"UPDATE {$table}
				 SET status = %s, resolved_at = %s, updated_at = %s
				 WHERE type = %s AND object_type = %s AND object_id = %d
				   AND scan_id <> %d AND status = %s",
				array(
					self::STATUS_RESOLVED,
					$now,
					$now,
					(string) $type,
					(string) $object_type,
					(int) $object_id,
					(int) $scan_id,
					self::STATUS_OPEN,
				)
			)
		);

		return (int) $wpdb->rows_affected;
	}

	/**
	 * One issue by id.
	 *
	 * @param int $id Issue id.
	 * @return object|null
	 */
	public static function get( $id ) {
		global $wpdb;

		$table = Tables::issues();

		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", (int) $id ) );
	}

	/**
	 * One issue by its deterministic key.
	 *
	 * @param string $issue_key 40-char hex.
	 * @return object|null
	 */
	public static function find_by_key( $issue_key ) {
		global $wpdb;

		$table = Tables::issues();

		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE issue_key = %s", (string) $issue_key ) );
	}

	/**
	 * Open-issue counts per type, for the dashboard.
	 *
	 * @return array type => count
	 */
	public static function counts_by_type() {
		global $wpdb;

		$table = Tables::issues();

		$rows = $wpdb->get_results(
			$wpdb->prepare( "SELECT type, COUNT(*) AS total FROM {$table} WHERE status = %s GROUP BY type", self::STATUS_OPEN )
		);

		$out = array();

		foreach ( (array) $rows as $row ) {
			$out[ $row->type ] = (int) $row->total;
		}

		return $out;
	}

	/**
	 * Delete resolved issues older than a cut-off.
	 *
	 * Resolved rows are history, and history that nobody reads is just table
	 * size. Open and ignored rows are never touched.
	 *
	 * @param string $before MySQL datetime, UTC.
	 * @param int    $limit  Maximum rows per call, so the job stays bounded.
	 * @return int Rows deleted.
	 */
	public static function prune_resolved( $before, $limit = 500 ) {
		global $wpdb;

		$table = Tables::issues();

		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$table} WHERE status = %s AND resolved_at IS NOT NULL AND resolved_at < %s LIMIT %d",
				array( self::STATUS_RESOLVED, (string) $before, max( 1, (int) $limit ) )
			)
		);

		return (int) $wpdb->rows_affected;
	}

	/**
	 * Current UTC timestamp in MySQL format.
	 *
	 * Uses current_time( 'mysql', true ) rather than NOW(): the database server's
	 * clock and time zone are not necessarily the site's.
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

		if ( function_exists( 'mb_strcut' ) ) {
			return mb_strcut( $value, 0, 255, 'UTF-8' );
		}

		return substr( $value, 0, 255 );
	}
}
