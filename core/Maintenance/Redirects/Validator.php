<?php namespace RealTimeAutoFindReplace\Maintenance\Redirects;

use RealTimeAutoFindReplace\Maintenance\Support\UrlNormalizer;

/**
 * Everything that must be true before a redirect is saved.
 *
 * A redirect is the one thing in this plugin that changes what a visitor sees
 * without anyone looking at a screen, so the checks here are the difference
 * between a useful tool and a site nobody can reach. Three of them matter more
 * than the rest:
 *
 *   - a source that redirects to itself is an infinite loop the browser gives
 *     up on, and normalisation is what makes "/a" and "/a/" the same source;
 *   - a source under /wp-admin or /wp-login.php locks the owner out of their
 *     own site, and no confirmation dialog undoes that;
 *   - an off-site destination is an open redirect if it can ever be derived
 *     from request input, so it is allowed only from an admin form and the
 *     executor still sends it through wp_safe_redirect().
 *
 * Chains are warned about rather than refused. A -> B where B -> C already
 * exists is legitimate (people build them while reorganising), it just costs
 * the visitor a hop, so the honest response is to say so and offer A -> C.
 *
 * Pure PHP: the unit suite exercises every branch without WordPress.
 *
 * @package Maintenance
 * @since 1.10.0
 * @author M.Tuhin <info@codesolz.net>
 */

if ( ! defined( 'CS_RTAFAR_VERSION' ) ) {
	exit;
}

class Validator {

	/**
	 * Paths that must never be redirected away.
	 *
	 * @var array
	 */
	private static $protected_prefixes = array(
		'/wp-admin',
		'/wp-login.php',
		'/wp-cron.php',
		'/wp-json',
		'/xmlrpc.php',
	);

	/**
	 * Redirect types the free tier offers.
	 *
	 * @return array
	 */
	public static function free_types() {
		return array( 301 );
	}

	/**
	 * Every redirect type the platform understands.
	 *
	 * @return array
	 */
	public static function all_types() {
		$types = array( 301, 302, 307, 308 );

		if ( function_exists( 'apply_filters' ) ) {
			/**
			 * Filter the available redirect types.
			 *
			 * Free answers with 301 only; pro adds the rest.
			 *
			 * @param array $types HTTP status codes.
			 */
			$types = apply_filters( 'bfr_redirect_types', self::free_types() );
		}

		return array_values( array_unique( array_map( 'intval', (array) $types ) ) );
	}

	/**
	 * Match types the free tier offers.
	 *
	 * @return array
	 */
	public static function free_match_types() {
		return array( 'exact' );
	}

	/**
	 * Every match type the platform understands.
	 *
	 * @return array
	 */
	public static function match_types() {
		$types = self::free_match_types();

		if ( function_exists( 'apply_filters' ) ) {
			/**
			 * Filter the available match types.
			 *
			 * Free answers with exact matching only; pro adds prefix and regex.
			 * The executor consults non-exact rules through
			 * `bfr_redirect_match`, which only fires after the indexed exact
			 * lookup has missed.
			 *
			 * @param array $types Match type slugs.
			 */
			$types = apply_filters( 'bfr_redirect_match_types', $types );
		}

		return array_values( array_unique( array_map( 'strval', (array) $types ) ) );
	}

	/**
	 * Check a redirect before it is stored.
	 *
	 * @param string $source      Source path, as typed.
	 * @param string $destination Destination path or URL, as typed.
	 * @param array  $args {
	 *     @type string $site_host  Host that counts as internal.
	 *     @type array  $existing   Existing redirects: rows with source_hash, source, destination.
	 *     @type int    $ignore_id  Redirect id being edited, excluded from conflict checks.
	 *     @type int    $type       HTTP status code.
	 * }
	 * @return array {
	 *     @type bool   $ok
	 *     @type array  $errors      Human-readable reasons it cannot be saved.
	 *     @type array  $warnings    Things worth saying that do not block.
	 *     @type string $source      Normalised source.
	 *     @type string $source_hash Index key for the normalised source.
	 *     @type string $destination Cleaned destination.
	 *     @type string $suggested   A better destination, when a chain was found.
	 * }
	 */
	public static function check( $source, $destination, array $args = array() ) {
		$site_host = isset( $args['site_host'] ) ? (string) $args['site_host'] : '';
		$existing  = isset( $args['existing'] ) && is_array( $args['existing'] ) ? $args['existing'] : array();
		$ignore_id = isset( $args['ignore_id'] ) ? (int) $args['ignore_id'] : 0;
		$type      = isset( $args['type'] ) ? (int) $args['type'] : 301;

		$errors    = array();
		$warnings  = array();
		$suggested = '';

		$source      = trim( (string) $source );
		$destination = trim( (string) $destination );

		if ( '' === $source ) {
			$errors[] = __( 'Enter the URL you want to redirect from.', 'real-time-auto-find-and-replace' );
		}

		if ( '' === $destination ) {
			$errors[] = __( 'Enter where it should go instead.', 'real-time-auto-find-and-replace' );
		}

		if ( ! empty( $errors ) ) {
			return self::result( false, $errors, $warnings, '', '', '', '' );
		}

		$match_type = isset( $args['match_type'] ) ? (string) $args['match_type'] : 'exact';

		if ( ! in_array( $match_type, self::match_types(), true ) ) {
			$errors[] = __( 'That match type is not available.', 'real-time-auto-find-and-replace' );

			return self::result( false, $errors, $warnings, '', '', '', '' );
		}

		if ( 'exact' !== $match_type ) {
			return self::check_pattern( $source, $destination, $match_type, $args );
		}

		// The source is always a path on this site. An absolute URL for another
		// host is not something this site can answer for.
		if ( ! UrlNormalizer::is_internal( $source, $site_host ) ) {
			$errors[] = __( 'The "from" URL has to be a path on this site, such as /old-page/.', 'real-time-auto-find-and-replace' );

			return self::result( false, $errors, $warnings, '', '', '', '' );
		}

		$source_path = UrlNormalizer::to_path( $source, $site_host );
		$source_key  = UrlNormalizer::normalize( $source, $site_host );
		$source_hash = UrlNormalizer::hash( $source, $site_host );

		if ( '/' === $source_path && false === strpos( $source_key, '?' ) ) {
			$errors[] = __( 'The home page cannot be redirected from here.', 'real-time-auto-find-and-replace' );
		}

		foreach ( self::$protected_prefixes as $prefix ) {
			if ( 0 === stripos( $source_path, $prefix ) ) {
				/* translators: %s: the protected URL path, e.g. /wp-admin */
				$errors[] = sprintf( __( '%s cannot be redirected - doing so would lock you out of your own site.', 'real-time-auto-find-and-replace' ), $prefix );
				break;
			}
		}

		$destination_check = self::check_destination( $destination );

		if ( '' !== $destination_check ) {
			$errors[] = $destination_check;
		}

		if ( ! in_array( $type, self::all_types(), true ) ) {
			$errors[] = __( 'That redirect type is not available.', 'real-time-auto-find-and-replace' );
		}

		if ( ! empty( $errors ) ) {
			return self::result( false, $errors, $warnings, $source_key, $source_hash, $destination, '' );
		}

		// Self-redirect, after normalisation - "/a" and "/a/" are the same page.
		if ( UrlNormalizer::is_internal( $destination, $site_host )
			&& UrlNormalizer::hash( $destination, $site_host ) === $source_hash ) {
			$errors[] = __( 'That would send the URL to itself, which loops forever.', 'real-time-auto-find-and-replace' );

			return self::result( false, $errors, $warnings, $source_key, $source_hash, $destination, '' );
		}

		// Another redirect already claims this source.
		foreach ( $existing as $row ) {
			if ( $ignore_id > 0 && isset( $row['id'] ) && (int) $row['id'] === $ignore_id ) {
				continue;
			}

			if ( isset( $row['source_hash'] ) && $row['source_hash'] === $source_hash ) {
				$errors[] = __( 'There is already a redirect for that URL. Edit the existing one instead.', 'real-time-auto-find-and-replace' );
				break;
			}
		}

		if ( ! empty( $errors ) ) {
			return self::result( false, $errors, $warnings, $source_key, $source_hash, $destination, '' );
		}

		$chain = self::follow( $destination, $existing, $site_host, $source_hash, $ignore_id );

		if ( $chain['cycle'] ) {
			$errors[] = __( 'That would create a loop: the destination eventually points back here.', 'real-time-auto-find-and-replace' );

			return self::result( false, $errors, $warnings, $source_key, $source_hash, $destination, '' );
		}

		if ( '' !== $chain['final'] ) {
			$warnings[] = __( 'The destination is itself redirected, so visitors would make two hops.', 'real-time-auto-find-and-replace' );
			$suggested  = $chain['final'];
		}

		if ( function_exists( 'apply_filters' ) ) {
			/**
			 * Filter validation errors for a redirect.
			 *
			 * Pro attaches chain and loop detection across the whole set here,
			 * where free only sees the rows it was handed. Returning a non-empty
			 * array blocks the save.
			 *
			 * @param array  $errors      Blocking reasons so far.
			 * @param array  $redirect    The redirect being checked.
			 */
			$errors = (array) apply_filters(
				'bfr_redirect_validate',
				$errors,
				array(
					'source'      => $source_key,
					'source_hash' => $source_hash,
					'destination' => $destination,
					'type'        => $type,
					'match_type'  => 'exact',
				)
			);
		}

		return self::result( empty( $errors ), $errors, $warnings, $source_key, $source_hash, $destination, $suggested );
	}

	/**
	 * Check a rule whose source is a pattern rather than a path.
	 *
	 * Everything that treats the source as a URL is skipped: normalising
	 * `^/old/(.*)$` as a path would mangle it, and hashing it as one would
	 * collide two different patterns onto the same key. The hash here is of the
	 * pattern itself, namespaced by match type, so the unique index still does
	 * its job.
	 *
	 * What the pattern *matches* is not free's business - it has no compiler for
	 * it. That check belongs to whoever added the match type, and reaches it
	 * through `bfr_redirect_validate` with the match type in the payload.
	 *
	 * @param string $source      Pattern, as typed.
	 * @param string $destination Destination.
	 * @param string $match_type  Match type slug.
	 * @param array  $args        See check().
	 * @return array
	 */
	private static function check_pattern( $source, $destination, $match_type, array $args ) {
		$existing  = isset( $args['existing'] ) && is_array( $args['existing'] ) ? $args['existing'] : array();
		$ignore_id = isset( $args['ignore_id'] ) ? (int) $args['ignore_id'] : 0;
		$type      = isset( $args['type'] ) ? (int) $args['type'] : 301;

		$errors   = array();
		$warnings = array();

		$source_key  = $source;
		$source_hash = sha1( $match_type . '|' . $source );

		$destination_check = self::check_destination( $destination );

		if ( '' !== $destination_check ) {
			$errors[] = $destination_check;
		}

		if ( ! in_array( $type, self::all_types(), true ) ) {
			$errors[] = __( 'That redirect type is not available.', 'real-time-auto-find-and-replace' );
		}

		foreach ( $existing as $row ) {
			if ( $ignore_id > 0 && isset( $row['id'] ) && (int) $row['id'] === $ignore_id ) {
				continue;
			}

			if ( isset( $row['source_hash'] ) && $row['source_hash'] === $source_hash ) {
				$errors[] = __( 'There is already a rule with that pattern.', 'real-time-auto-find-and-replace' );
				break;
			}
		}

		if ( function_exists( 'apply_filters' ) ) {
			/** This filter is documented in core/Maintenance/Redirects/Validator.php */
			$errors = (array) apply_filters(
				'bfr_redirect_validate',
				$errors,
				array(
					'source'      => $source_key,
					'source_hash' => $source_hash,
					'destination' => $destination,
					'type'        => $type,
					'match_type'  => $match_type,
				)
			);
		}

		return self::result( empty( $errors ), $errors, $warnings, $source_key, $source_hash, $destination, '' );
	}

	/**
	 * Is this destination safe to send a visitor to?
	 *
	 * @param string $destination Destination as typed.
	 * @return string Empty when acceptable, otherwise the reason.
	 */
	public static function check_destination( $destination ) {
		$destination = trim( (string) $destination );

		if ( '' === $destination ) {
			return __( 'Enter where it should go instead.', 'real-time-auto-find-and-replace' );
		}

		if ( strlen( $destination ) > 2000 ) {
			return __( 'That destination URL is too long.', 'real-time-auto-find-and-replace' );
		}

		$scheme = UrlNormalizer::scheme_of( $destination );

		if ( '' !== $scheme && ! in_array( $scheme, array( 'http', 'https' ), true ) ) {
			/* translators: %s: URL scheme, e.g. javascript */
			return sprintf( __( '"%s:" destinations are not allowed.', 'real-time-auto-find-and-replace' ), $scheme );
		}

		// A relative destination has to be a path, not a bare word that would
		// resolve against whatever page the visitor happened to be on.
		if ( '' === $scheme && 0 !== strpos( $destination, '/' ) ) {
			return __( 'The destination must start with / or be a full http(s) address.', 'real-time-auto-find-and-replace' );
		}

		if ( preg_match( '/[\s<>"\']/', $destination ) ) {
			return __( 'The destination contains characters that are not valid in a URL.', 'real-time-auto-find-and-replace' );
		}

		return '';
	}

	/**
	 * Walk the chain from a destination, looking for a cycle or a final target.
	 *
	 * @param string $destination Proposed destination.
	 * @param array  $existing    Existing redirect rows.
	 * @param string $site_host   Internal host.
	 * @param string $source_hash The source being saved.
	 * @param int    $ignore_id   Redirect id being edited.
	 * @return array array( 'cycle' => bool, 'final' => string )
	 */
	private static function follow( $destination, array $existing, $site_host, $source_hash, $ignore_id ) {
		$by_source = array();

		foreach ( $existing as $row ) {
			if ( $ignore_id > 0 && isset( $row['id'] ) && (int) $row['id'] === $ignore_id ) {
				continue;
			}

			if ( isset( $row['source_hash'], $row['destination'] ) ) {
				$by_source[ $row['source_hash'] ] = (string) $row['destination'];
			}
		}

		$current = $destination;
		$final   = '';
		$seen    = array();

		// Bounded: a malformed set of redirects must not spin here.
		for ( $hop = 0; $hop < 10; $hop++ ) {
			if ( ! UrlNormalizer::is_internal( $current, $site_host ) ) {
				break;
			}

			$hash = UrlNormalizer::hash( $current, $site_host );

			if ( $hash === $source_hash ) {
				return array(
					'cycle' => true,
					'final' => '',
				);
			}

			if ( isset( $seen[ $hash ] ) ) {
				// A pre-existing loop between other redirects. Not ours to
				// refuse, and not something to follow any further.
				break;
			}

			$seen[ $hash ] = true;

			if ( ! isset( $by_source[ $hash ] ) ) {
				break;
			}

			$current = $by_source[ $hash ];
			$final   = $current;
		}

		return array(
			'cycle' => false,
			'final' => $final,
		);
	}

	/**
	 * Shape a result.
	 *
	 * @param bool   $ok          Whether it may be saved.
	 * @param array  $errors      Blocking reasons.
	 * @param array  $warnings    Non-blocking notes.
	 * @param string $source      Normalised source.
	 * @param string $source_hash Source index key.
	 * @param string $destination Cleaned destination.
	 * @param string $suggested   Better destination, when a chain was found.
	 * @return array
	 */
	private static function result( $ok, $errors, $warnings, $source, $source_hash, $destination, $suggested ) {
		return array(
			'ok'          => (bool) $ok && empty( $errors ),
			'errors'      => array_values( $errors ),
			'warnings'    => array_values( $warnings ),
			'source'      => $source,
			'source_hash' => $source_hash,
			'destination' => $destination,
			'suggested'   => $suggested,
		);
	}
}
