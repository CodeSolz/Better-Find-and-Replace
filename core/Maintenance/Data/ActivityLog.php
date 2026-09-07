<?php namespace RealTimeAutoFindReplace\Maintenance\Data;

use RealTimeAutoFindReplace\Maintenance\Data\Schema\Tables;

/**
 * What happened, who did it, and what it touched.
 *
 * Master spec 27. This is the user-facing history - the thing somebody reads
 * when they come back on Monday and want to know what changed on Friday.
 *
 * It is deliberately NOT the undo mechanism. Old and new values live in
 * {prefix}rtafar_history, written through the existing bfar_save_item_history
 * action and read by the shipped Restore in Database screen. Keeping values
 * out of here is what stops this table filling up with post content, and what
 * keeps rollback working through code that is already tested in the field.
 *
 * Operations that span several writes share an operation_id, so a Replace +
 * Redirect reads as one event rather than five.
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

class ActivityLog {

	/**
	 * Metadata keys that must never be stored, at any depth.
	 *
	 * @var array
	 */
	private static $forbidden = array(
		'api_key',
		'apikey',
		'token',
		'cs_token',
		'refresh_token',
		'authorization',
		'password',
		'credential',
		'old_val',
		'new_val',
		'post_content',
		'content',
		'user_agent',
	);

	/**
	 * Start a new operation.
	 *
	 * @return string 32-char hex id to pass to every record() of this operation.
	 */
	public static function new_operation() {
		if ( function_exists( 'wp_generate_password' ) ) {
			return substr( md5( wp_generate_password( 32, false ) . microtime( true ) ), 0, 32 );
		}

		return substr( md5( uniqid( 'bfr', true ) ), 0, 32 );
	}

	/**
	 * Record one action.
	 *
	 * @param string $action  Action slug, e.g. 'redirect_created'.
	 * @param array  $args {
	 *     @type string $summary      Human-readable one-liner.
	 *     @type string $object_type  Object kind.
	 *     @type int    $object_id    Object id.
	 *     @type string $operation_id Groups several rows into one operation.
	 *     @type int    $user_id      Defaults to the current user.
	 *     @type array  $metadata     Scrubbed before storage.
	 * }
	 * @return int Row id, or 0 on failure.
	 */
	public static function record( $action, array $args = array() ) {
		global $wpdb;

		$action = substr( (string) $action, 0, 48 );

		if ( '' === $action ) {
			return 0;
		}

		$user_id = isset( $args['user_id'] )
			? (int) $args['user_id']
			: ( function_exists( 'get_current_user_id' ) ? (int) get_current_user_id() : 0 );

		$metadata = isset( $args['metadata'] ) && is_array( $args['metadata'] )
			? self::scrub( $args['metadata'] )
			: array();

		$ok = $wpdb->insert(
			Tables::activity(),
			array(
				'operation_id' => isset( $args['operation_id'] ) ? substr( (string) $args['operation_id'], 0, 32 ) : '',
				'action'       => $action,
				'object_type'  => isset( $args['object_type'] ) ? substr( (string) $args['object_type'], 0, 20 ) : '',
				'object_id'    => isset( $args['object_id'] ) ? (int) $args['object_id'] : 0,
				'user_id'      => $user_id,
				'summary'      => isset( $args['summary'] ) ? substr( (string) $args['summary'], 0, 255 ) : '',
				'metadata'     => empty( $metadata ) ? '' : wp_json_encode( $metadata ),
				'created_at'   => function_exists( 'current_time' ) ? current_time( 'mysql', true ) : gmdate( 'Y-m-d H:i:s' ),
			)
		);

		return $ok ? (int) $wpdb->insert_id : 0;
	}

	/**
	 * Recent entries, newest first.
	 *
	 * @param int $limit  Rows to return.
	 * @param int $offset Rows to skip.
	 * @return array
	 */
	public static function recent( $limit = 20, $offset = 0 ) {
		global $wpdb;

		$table = Tables::activity();

		return (array) $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table} ORDER BY id DESC LIMIT %d OFFSET %d",
				array( max( 1, min( 200, (int) $limit ) ), max( 0, (int) $offset ) )
			)
		);
	}

	/**
	 * How many entries there are.
	 *
	 * Added for the locked Activity tab, which shows the number of changes a
	 * free site has already accumulated. Free records activity whether or not
	 * it can display the log, so this is a fact about the site rather than a
	 * claim about the product - which is the difference between an argument
	 * and an advertisement.
	 *
	 * @return int
	 */
	public static function count() {
		global $wpdb;

		if ( ! Tables::installed() ) {
			return 0;
		}

		$table = Tables::activity();

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		return (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" );
	}

	/**
	 * Every row of one operation, oldest first.
	 *
	 * @param string $operation_id Operation id.
	 * @return array
	 */
	public static function for_operation( $operation_id ) {
		global $wpdb;

		$table = Tables::activity();

		return (array) $wpdb->get_results(
			$wpdb->prepare( "SELECT * FROM {$table} WHERE operation_id = %s ORDER BY id ASC", (string) $operation_id )
		);
	}

	/**
	 * Delete entries older than a cut-off.
	 *
	 * @param string $before MySQL datetime, UTC.
	 * @param int    $limit  Maximum rows per call.
	 * @return int Rows deleted.
	 */
	public static function prune( $before, $limit = 500 ) {
		global $wpdb;

		$table = Tables::activity();

		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$table} WHERE created_at < %s LIMIT %d",
				array( (string) $before, max( 1, (int) $limit ) )
			)
		);

		return (int) $wpdb->rows_affected;
	}

	/**
	 * Drop forbidden keys and keep what remains small.
	 *
	 * @param array $metadata Raw metadata.
	 * @param int   $depth    Recursion guard.
	 * @return array
	 */
	private static function scrub( array $metadata, $depth = 0 ) {
		$safe = array();

		foreach ( $metadata as $key => $value ) {
			$name = strtolower( (string) $key );

			if ( in_array( $name, self::$forbidden, true ) ) {
				continue;
			}

			if ( is_array( $value ) ) {
				$safe[ $name ] = $depth >= 2 ? count( $value ) : self::scrub( $value, $depth + 1 );
				continue;
			}

			if ( is_object( $value ) ) {
				continue;
			}

			if ( is_string( $value ) && strlen( $value ) > 255 ) {
				$value = substr( $value, 0, 255 );
			}

			$safe[ $name ] = $value;
		}

		return $safe;
	}
}
