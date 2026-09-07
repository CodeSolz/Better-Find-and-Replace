<?php namespace RealTimeAutoFindReplace\Maintenance\Data;

use RealTimeAutoFindReplace\Maintenance\Data\Schema\Tables;

/**
 * The read side of the issue model.
 *
 * Separate from IssueRepository because the risks are different. The repository
 * writes rows the scanner produced; this builds SQL out of things a browser
 * sent - a sort column, a direction, a page, a search term, a status filter.
 *
 * So every one of those is matched against an allow-list before it goes
 * anywhere near a query. Not escaped, not sanitised: matched. A column name
 * cannot be a placeholder, so the only safe version is one where the set of
 * possible values is fixed in this file and anything else is replaced by a
 * default.
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

class IssueQuery {

	/**
	 * Columns a caller may sort by.
	 *
	 * @return array
	 */
	public static function sortable_columns() {
		return array( 'priority_score', 'last_seen_at', 'occurrences', 'severity', 'target_url', 'id' );
	}

	/**
	 * Find issues.
	 *
	 * @param array $args {
	 *     @type string $type     Issue type.
	 *     @type string $status   Issue status, or 'any'.
	 *     @type string $subtype  Issue subtype.
	 *     @type string $search   Substring of the target URL.
	 *     @type int    $object_id   Restrict to one object.
	 *     @type string $target_hash Restrict to one normalised URL.
	 *     @type int    $after_id    Keyset cursor: only ids above this.
	 *     @type string $orderby  Allow-listed column.
	 *     @type string $order    ASC or DESC.
	 *     @type int    $per_page Rows per page.
	 *     @type int    $page     1-based page number.
	 * }
	 * @return array
	 */
	public static function find( array $args = array() ) {
		global $wpdb;

		$table = Tables::issues();
		$where = self::where( $args );
		$order = self::order( $args );
		$limit = self::limit( $args );

		$sql = "SELECT * FROM {$table} {$where['sql']} {$order} LIMIT %d OFFSET %d";

		$params = array_merge( $where['args'], array( $limit['per_page'], $limit['offset'] ) );

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		return (array) $wpdb->get_results( $wpdb->prepare( $sql, $params ) );
	}

	/**
	 * How many issues match.
	 *
	 * @param array $args See find().
	 * @return int
	 */
	public static function count( array $args = array() ) {
		global $wpdb;

		$table = Tables::issues();
		$where = self::where( $args );

		$sql = "SELECT COUNT(*) FROM {$table} {$where['sql']}";

		if ( empty( $where['args'] ) ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			return (int) $wpdb->get_var( $sql );
		}

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		return (int) $wpdb->get_var( $wpdb->prepare( $sql, $where['args'] ) );
	}

	/**
	 * Counts per status, for the list table's view links.
	 *
	 * @param string $type Issue type.
	 * @return array status => count
	 */
	public static function status_counts( $type ) {
		global $wpdb;

		$table = Tables::issues();

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$rows = $wpdb->get_results(
			$wpdb->prepare( "SELECT status, COUNT(*) AS total FROM {$table} WHERE type = %s GROUP BY status", (string) $type )
		);

		$out = array();

		foreach ( (array) $rows as $row ) {
			$out[ $row->status ] = (int) $row->total;
		}

		return $out;
	}

	/**
	 * Open counts per subtype, for one type.
	 *
	 * Added for the maintenance agent's census, which opens with a breakdown
	 * rather than a total - "5 broken internal links, 2 dead external links"
	 * tells somebody what afternoon they are in for, and "7 broken links" does
	 * not. One grouped query rather than one count per subtype, because the
	 * caller does not know the subtypes until it asks.
	 *
	 * @param string $type Issue type.
	 * @return array subtype => open count, highest first.
	 */
	public static function subtype_counts( $type ) {
		global $wpdb;

		$table = Tables::issues();

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT subtype, COUNT(*) AS total FROM {$table} WHERE type = %s AND status = %s GROUP BY subtype",
				(string) $type,
				IssueRepository::STATUS_OPEN
			)
		);

		$out = array();

		foreach ( (array) $rows as $row ) {
			$key = '' === (string) $row->subtype ? 'other' : (string) $row->subtype;

			$out[ $key ] = (int) $row->total;
		}

		arsort( $out );

		return $out;
	}

	/**
	 * Build the WHERE clause and its bound values.
	 *
	 * @param array $args See find().
	 * @return array array( 'sql' => string, 'args' => array )
	 */
	private static function where( array $args ) {
		$clauses = array();
		$params  = array();

		if ( ! empty( $args['type'] ) ) {
			$clauses[] = 'type = %s';
			$params[]  = (string) $args['type'];
		}

		$status = isset( $args['status'] ) ? (string) $args['status'] : IssueRepository::STATUS_OPEN;

		if ( 'any' !== $status ) {
			// Matched against the model's own list, so an invented status
			// cannot reach the query at all.
			if ( ! in_array( $status, IssueRepository::statuses(), true ) ) {
				$status = IssueRepository::STATUS_OPEN;
			}

			$clauses[] = 'status = %s';
			$params[]  = $status;
		}

		if ( ! empty( $args['subtype'] ) ) {
			$clauses[] = 'subtype = %s';
			$params[]  = (string) $args['subtype'];
		}

		if ( ! empty( $args['object_id'] ) ) {
			$clauses[] = 'object_id = %d';
			$params[]  = (int) $args['object_id'];
		}

		if ( ! empty( $args['target_hash'] ) ) {
			$clauses[] = 'target_hash = %s';
			$params[]  = (string) $args['target_hash'];
		}

		// A keyset cursor, for walking a result set that is being written to as
		// it is read. Fixing an issue takes it out of the OPEN filter, so a
		// second page fetched by offset would start past work that had shuffled
		// down into the first - and skip it silently.
		if ( ! empty( $args['after_id'] ) ) {
			$clauses[] = 'id > %d';
			$params[]  = (int) $args['after_id'];
		}

		if ( isset( $args['search'] ) && '' !== $args['search'] ) {
			global $wpdb;

			$clauses[] = '(target_url LIKE %s OR source_url LIKE %s)';
			$like      = '%' . $wpdb->esc_like( (string) $args['search'] ) . '%';
			$params[]  = $like;
			$params[]  = $like;
		}

		return array(
			'sql'  => empty( $clauses ) ? '' : 'WHERE ' . implode( ' AND ', $clauses ),
			'args' => $params,
		);
	}

	/**
	 * Build the ORDER BY clause.
	 *
	 * Both halves are allow-listed. Neither can be a placeholder, so this is
	 * the only way it is safe.
	 *
	 * @param array $args See find().
	 * @return string
	 */
	private static function order( array $args ) {
		$orderby = isset( $args['orderby'] ) ? (string) $args['orderby'] : 'priority_score';

		if ( ! in_array( $orderby, self::sortable_columns(), true ) ) {
			$orderby = 'priority_score';
		}

		$order = isset( $args['order'] ) && 'asc' === strtolower( (string) $args['order'] ) ? 'ASC' : 'DESC';

		// id is the tie-breaker so paging is stable when scores match.
		return "ORDER BY {$orderby} {$order}, id DESC";
	}

	/**
	 * Bound page size and offset.
	 *
	 * @param array $args See find().
	 * @return array
	 */
	private static function limit( array $args ) {
		$per_page = isset( $args['per_page'] ) ? (int) $args['per_page'] : 20;
		$per_page = max( 1, min( 200, $per_page ) );

		$page   = isset( $args['page'] ) ? max( 1, (int) $args['page'] ) : 1;
		$offset = ( $page - 1 ) * $per_page;

		return array(
			'per_page' => $per_page,
			'offset'   => $offset,
		);
	}
}
