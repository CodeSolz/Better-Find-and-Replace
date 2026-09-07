<?php namespace RealTimeAutoFindReplace\Maintenance\Support;

/**
 * Maintenance-platform logging.
 *
 * WP_DEBUG-gated, one line per event, greppable by the "BFR Maintenance"
 * prefix.
 *
 * What may be logged: ids, counts, statuses, HTTP codes, durations, job types.
 * What may never be logged: credentials, tokens, authorization headers, post
 * content, or a visitor's raw user agent. Debugging a scan does not require
 * the customer's post bodies - or their visitors - in debug.log.
 *
 * @package Maintenance
 * @since 1.10.0
 * @author M.Tuhin <info@codesolz.net>
 */

if ( ! defined( 'CS_RTAFAR_VERSION' ) ) {
	exit;
}

class Logger {

	/**
	 * Keys that must never reach a log line, at any nesting depth.
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
		'x-api-key',
		'credential',
		'password',
		'prompt',
		'content',
		'post_content',
		'user_agent',
		'useragent',
	);

	/**
	 * Write one log line.
	 *
	 * @param string $event   Short event slug, e.g. 'queue.claimed'.
	 * @param array  $context Scalar context. Sensitive keys are dropped.
	 * @return void
	 */
	public static function log( $event, array $context = array() ) {
		if ( ! defined( 'WP_DEBUG' ) || ! WP_DEBUG ) {
			return;
		}

		$parts = array();

		foreach ( self::scrub( $context ) as $key => $value ) {
			$parts[] = $key . '=' . $value;
		}

		$line = 'BFR Maintenance : ' . (string) $event;

		if ( ! empty( $parts ) ) {
			$line .= ' ' . implode( ' ', $parts );
		}

		// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
		error_log( $line );
	}

	/**
	 * Drop forbidden keys and flatten what remains to short scalars.
	 *
	 * Nested arrays are summarised by size rather than dumped: a log line that
	 * needs scrolling is a log line nobody reads, and a dumped array is how
	 * content leaks into a file the customer later emails to support.
	 *
	 * @param array $context Raw context.
	 * @param int   $depth   Recursion guard.
	 * @return array
	 */
	private static function scrub( array $context, $depth = 0 ) {
		$safe = array();

		foreach ( $context as $key => $value ) {
			$name = strtolower( (string) $key );

			if ( in_array( $name, self::$forbidden, true ) ) {
				continue;
			}

			if ( is_array( $value ) ) {
				$safe[ $name ] = $depth >= 2 ? 'array' : 'array(' . count( $value ) . ')';
				continue;
			}

			if ( is_object( $value ) ) {
				$safe[ $name ] = get_class( $value );
				continue;
			}

			if ( is_bool( $value ) ) {
				$safe[ $name ] = $value ? 'true' : 'false';
				continue;
			}

			if ( null === $value ) {
				$safe[ $name ] = 'null';
				continue;
			}

			$flat = (string) $value;

			if ( strlen( $flat ) > 120 ) {
				$flat = substr( $flat, 0, 117 ) . '...';
			}

			// Keep the line parseable: no newlines, no unquoted spaces.
			$safe[ $name ] = str_replace( array( "\r", "\n", ' ' ), array( '', ' ', '_' ), $flat );
		}

		return $safe;
	}
}
