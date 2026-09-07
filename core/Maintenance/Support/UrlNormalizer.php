<?php namespace RealTimeAutoFindReplace\Maintenance\Support;

/**
 * Canonical URL keys for the maintenance platform.
 *
 * Every module compares URLs: the link scanner asks "have I already recorded
 * this target", the redirect matcher asks "is this request one of my sources",
 * the 404 monitor asks "is this the same dead path I saw a minute ago". All
 * three need the same answer for strings that are only cosmetically different,
 * so they all key off this class rather than off the raw text.
 *
 * The canonical form is deliberately scheme-less, because a site reached over
 * http and https is one site:
 *
 *   internal  ->  /path?query            ( "/a", never "https://site.com/a" )
 *   external  ->  //host/path?query      ( the leading // keeps it re-parseable )
 *   other     ->  scheme:rest            ( mailto:, tel: - lower-cased, left alone )
 *
 * The `//` on external forms is not decoration. normalize() must be idempotent
 * - normalize( normalize( $x ) ) === normalize( $x ) - and a bare
 * "example.com/a" would parse back as a relative path on the second pass.
 *
 * No WordPress functions are used here, so the unit suite can exercise it
 * without loading WordPress.
 *
 * @package Maintenance
 * @since 1.10.0
 * @author M.Tuhin <info@codesolz.net>
 */

if ( ! defined( 'CS_RTAFAR_VERSION' ) ) {
	exit;
}

class UrlNormalizer {

	/**
	 * Query parameters that identify a campaign, not a resource.
	 *
	 * Two URLs differing only by these point at the same page, so keeping them
	 * would split one broken link into dozens of issues and one dead path into
	 * dozens of 404 rows.
	 *
	 * @var array
	 */
	private static $tracking_params = array(
		'utm_source',
		'utm_medium',
		'utm_campaign',
		'utm_term',
		'utm_content',
		'utm_id',
		'gclid',
		'gbraid',
		'wbraid',
		'fbclid',
		'msclkid',
		'mc_cid',
		'mc_eid',
		'igshid',
		'_hsenc',
		'_hsmi',
		'yclid',
		'dclid',
	);

	/**
	 * Schemes we are able to fetch, resolve or redirect.
	 *
	 * @var array
	 */
	private static $web_schemes = array( 'http', 'https' );

	/**
	 * Default options.
	 *
	 * @return array
	 */
	public static function defaults() {
		return array(
			// Lower-case the path. Off by default: on a case-sensitive server
			// /File.PDF and /file.pdf are different files. The 404 monitor
			// turns it on deliberately, where collapsing case is what stops a
			// scanner probing /Admin, /ADMIN, /admin from minting three rows.
			'lower_path'          => false,

			// Drop the query string entirely.
			'drop_query'          => false,

			// Drop campaign parameters (see $tracking_params).
			'drop_tracking'       => true,

			// Keep a trailing slash rather than stripping it. Off by default:
			// WordPress serves /a and /a/ as the same page.
			'keep_trailing_slash' => false,
		);
	}

	/**
	 * Reduce a URL to its canonical comparison key.
	 *
	 * @param string $url       Raw URL, absolute, protocol-relative or relative.
	 * @param string $site_host Host that counts as internal, e.g. 'example.com'.
	 * @param array  $options   See defaults().
	 * @return string Canonical key, or '' when nothing usable was supplied.
	 */
	public static function normalize( $url, $site_host = '', array $options = array() ) {
		$options = array_merge( self::defaults(), $options );
		$url     = trim( (string) $url );

		if ( '' === $url ) {
			return '';
		}

		// A fragment never reaches the server, so it can never distinguish two
		// requests. It is always last, so a plain cut is correct.
		$hash_at = strpos( $url, '#' );
		if ( false !== $hash_at ) {
			$url = substr( $url, 0, $hash_at );
		}

		if ( '' === $url ) {
			return '';
		}

		$scheme = self::scheme_of( $url );

		// mailto:, tel:, javascript:, data: - not ours to normalise beyond
		// lower-casing the scheme, and never fetchable.
		if ( '' !== $scheme && ! in_array( $scheme, self::$web_schemes, true ) ) {
			return strtolower( $scheme ) . ':' . substr( $url, strlen( $scheme ) + 1 );
		}

		$rest = $url;
		$host = '';

		if ( '' !== $scheme ) {
			// scheme://host/path - tolerate a missing "//" (scheme:/path).
			$rest = preg_replace( '~^[a-zA-Z][a-zA-Z0-9+.\-]*:~', '', $rest );
		}

		if ( 0 === strpos( $rest, '//' ) ) {
			$rest      = substr( $rest, 2 );
			$slash_at  = strpos( $rest, '/' );
			$authority = false === $slash_at ? $rest : substr( $rest, 0, $slash_at );
			$rest      = false === $slash_at ? '' : substr( $rest, $slash_at );
			$host      = self::normalize_host( $authority );
		}

		$query = '';
		$mark  = strpos( $rest, '?' );
		if ( false !== $mark ) {
			$query = substr( $rest, $mark + 1 );
			$rest  = substr( $rest, 0, $mark );
		}

		$path  = self::normalize_path( $rest, $options );
		$query = self::normalize_query( $query, $options );

		$suffix = '' === $query ? '' : '?' . $query;

		if ( '' === $host || self::hosts_match( $host, $site_host ) ) {
			return $path . $suffix;
		}

		return '//' . $host . $path . $suffix;
	}

	/**
	 * Canonical key, hashed for storage and indexing.
	 *
	 * Fixed 40 chars, so it indexes cheaply however long the URL was.
	 *
	 * @param string $url       Raw URL.
	 * @param string $site_host Internal host.
	 * @param array  $options   See defaults().
	 * @return string 40-char hex, or '' when the URL was unusable.
	 */
	public static function hash( $url, $site_host = '', array $options = array() ) {
		$key = self::normalize( $url, $site_host, $options );

		return '' === $key ? '' : sha1( $key );
	}

	/**
	 * Does this URL point at our own site?
	 *
	 * Relative URLs are internal by definition. Absolute ones are internal when
	 * the host matches modulo case and a leading "www.".
	 *
	 * @param string $url       Raw URL.
	 * @param string $site_host Internal host.
	 * @return bool
	 */
	public static function is_internal( $url, $site_host ) {
		$key = self::normalize( $url, $site_host );

		if ( '' === $key ) {
			return false;
		}

		if ( 0 === strpos( $key, '//' ) ) {
			return false;
		}

		// mailto: and friends are not internal pages.
		return 0 === strpos( $key, '/' );
	}

	/**
	 * The site-relative path of an internal URL.
	 *
	 * @param string $url       Raw URL.
	 * @param string $site_host Internal host.
	 * @return string Path with a leading slash, or '' when the URL is external.
	 */
	public static function to_path( $url, $site_host ) {
		if ( ! self::is_internal( $url, $site_host ) ) {
			return '';
		}

		$key  = self::normalize( $url, $site_host );
		$mark = strpos( $key, '?' );

		return false === $mark ? $key : substr( $key, 0, $mark );
	}

	/**
	 * Can this URL be fetched or resolved at all?
	 *
	 * @param string $url Raw URL.
	 * @return bool False for mailto:, tel:, javascript:, data:, and empties.
	 */
	public static function is_checkable( $url ) {
		$url = trim( (string) $url );

		if ( '' === $url ) {
			return false;
		}

		$scheme = self::scheme_of( $url );

		if ( '' === $scheme ) {
			// Relative or protocol-relative - both resolve against the site.
			return true;
		}

		return in_array( strtolower( $scheme ), self::$web_schemes, true );
	}

	/**
	 * The scheme of a URL, without its colon.
	 *
	 * @param string $url Raw URL.
	 * @return string Lower-case scheme, or '' when there is none.
	 */
	public static function scheme_of( $url ) {
		if ( preg_match( '~^([a-zA-Z][a-zA-Z0-9+.\-]*):~', (string) $url, $m ) ) {
			return strtolower( $m[1] );
		}

		return '';
	}

	/**
	 * Reduce a host to its comparison form.
	 *
	 * Strips userinfo, default ports, a trailing dot and a leading "www.".
	 *
	 * @param string $authority Host, possibly with userinfo and port.
	 * @return string
	 */
	public static function normalize_host( $authority ) {
		$authority = (string) $authority;

		// Discard any userinfo - credentials do not identify a resource.
		$at = strrpos( $authority, '@' );
		if ( false !== $at ) {
			$authority = substr( $authority, $at + 1 );
		}

		// Bracketed IPv6 literals have colons of their own; leave them alone.
		// A non-default port identifies a different service and is kept; the
		// two implied ones are dropped so :80 and bare compare equal.
		if ( '' !== $authority && '[' !== $authority[0] ) {
			$colon = strrpos( $authority, ':' );
			if ( false !== $colon ) {
				$port = substr( $authority, $colon + 1 );
				if ( '' === $port || '80' === $port || '443' === $port ) {
					$authority = substr( $authority, 0, $colon );
				}
			}
		}

		$authority = strtolower( rtrim( $authority, '.' ) );

		if ( 0 === strpos( $authority, 'www.' ) ) {
			$authority = substr( $authority, 4 );
		}

		return $authority;
	}

	/**
	 * Canonicalise a path.
	 *
	 * @param string $path    Raw path.
	 * @param array  $options See defaults().
	 * @return string Always starts with '/'.
	 */
	private static function normalize_path( $path, array $options ) {
		$path = (string) $path;

		if ( '' === $path ) {
			return '/';
		}

		$path = str_replace( ' ', '%20', $path );
		$path = self::normalize_percent( $path );

		if ( '/' !== $path[0] ) {
			$path = '/' . $path;
		}

		// Collapse repeated slashes: //a///b -> /a/b.
		$path = preg_replace( '~/{2,}~', '/', $path );
		$path = self::remove_dot_segments( $path );

		if ( ! empty( $options['lower_path'] ) ) {
			$path = strtolower( $path );
		}

		if ( empty( $options['keep_trailing_slash'] ) && '/' !== $path ) {
			$path = rtrim( $path, '/' );

			if ( '' === $path ) {
				$path = '/';
			}
		}

		return $path;
	}

	/**
	 * Canonicalise a query string.
	 *
	 * Parameters are sorted, because ?a=1&b=2 and ?b=2&a=1 are the same
	 * request. Sorting is done on the raw pairs rather than through
	 * parse_str(), which mangles duplicate keys and dots in names.
	 *
	 * @param string $query   Raw query string, without the '?'.
	 * @param array  $options See defaults().
	 * @return string
	 */
	private static function normalize_query( $query, array $options ) {
		$query = (string) $query;

		if ( '' === $query || ! empty( $options['drop_query'] ) ) {
			return '';
		}

		$pairs = array();

		foreach ( explode( '&', $query ) as $pair ) {
			if ( '' === $pair ) {
				continue;
			}

			$eq    = strpos( $pair, '=' );
			$name  = false === $eq ? $pair : substr( $pair, 0, $eq );
			$value = false === $eq ? null : substr( $pair, $eq + 1 );

			if ( '' === $name ) {
				continue;
			}

			if ( ! empty( $options['drop_tracking'] )
				&& in_array( strtolower( rawurldecode( $name ) ), self::$tracking_params, true ) ) {
				continue;
			}

			$name  = self::normalize_percent( $name );
			$value = null === $value ? null : self::normalize_percent( $value );

			$pairs[] = null === $value ? $name : $name . '=' . $value;
		}

		if ( empty( $pairs ) ) {
			return '';
		}

		sort( $pairs, SORT_STRING );

		return implode( '&', $pairs );
	}

	/**
	 * Normalise percent-encoding.
	 *
	 * Unreserved characters are decoded (%7E -> ~) and everything else keeps
	 * upper-case hex, so two spellings of the same byte compare equal.
	 *
	 * @param string $text Path or query fragment.
	 * @return string
	 */
	public static function normalize_percent( $text ) {
		return preg_replace_callback(
			'/%([0-9a-fA-F]{2})/',
			function ( $m ) {
				$chr = chr( hexdec( $m[1] ) );

				if ( 1 === preg_match( '/[A-Za-z0-9\-._~]/', $chr ) ) {
					return $chr;
				}

				return '%' . strtoupper( $m[1] );
			},
			(string) $text
		);
	}

	/**
	 * RFC 3986 dot-segment removal: /a/./b/../c -> /a/c.
	 *
	 * @param string $path Path with a leading slash.
	 * @return string
	 */
	private static function remove_dot_segments( $path ) {
		if ( false === strpos( $path, '.' ) ) {
			return $path;
		}

		$out      = array();
		$trailing = '/' === substr( $path, -1 );

		foreach ( explode( '/', $path ) as $segment ) {
			if ( '.' === $segment || '' === $segment ) {
				continue;
			}

			if ( '..' === $segment ) {
				array_pop( $out );
				continue;
			}

			$out[] = $segment;
		}

		$result = '/' . implode( '/', $out );

		if ( $trailing && '/' !== $result ) {
			$result .= '/';
		}

		return $result;
	}

	/**
	 * Do two hosts refer to the same site?
	 *
	 * @param string $host      Already-normalised host.
	 * @param string $site_host Raw site host, possibly with scheme or www.
	 * @return bool
	 */
	private static function hosts_match( $host, $site_host ) {
		$site_host = (string) $site_host;

		if ( '' === $site_host ) {
			return false;
		}

		// Tolerate a full URL being passed as the site host.
		$site_host = preg_replace( '~^[a-zA-Z][a-zA-Z0-9+.\-]*://~', '', $site_host );
		$slash     = strpos( $site_host, '/' );
		if ( false !== $slash ) {
			$site_host = substr( $site_host, 0, $slash );
		}

		return self::normalize_host( $site_host ) === $host;
	}
}
