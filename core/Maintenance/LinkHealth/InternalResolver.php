<?php namespace RealTimeAutoFindReplace\Maintenance\LinkHealth;

use RealTimeAutoFindReplace\Maintenance\Support\UrlNormalizer;

/**
 * Decides what an internal URL actually points at - without making a request.
 *
 * The free tier resolves internal links against the database rather than over
 * HTTP (adapted spec S13). That is not a limitation dressed up as a feature:
 *
 *   - a site that loops HTTP requests back to itself during a scan is the
 *     classic way a link checker takes down a small shared host;
 *   - the database knows things a request cannot, such as whether the target is
 *     a draft rather than genuinely gone;
 *   - it is an order of magnitude faster, which is what makes scanning 40k
 *     posts realistic at all.
 *
 * The governing rule is precision over recall. Telling somebody a working link
 * is broken costs their trust in every other row on the screen, while missing
 * one costs almost nothing - so anything this class cannot settle confidently
 * comes back as UNKNOWN, and UNKNOWN never becomes an issue.
 *
 * @package Maintenance
 * @since 1.10.0
 * @author M.Tuhin <info@codesolz.net>
 */

if ( ! defined( 'CS_RTAFAR_VERSION' ) ) {
	exit;
}

class InternalResolver {

	/** The target exists and is publicly reachable. */
	const OK = 'ok';

	/** Nothing answers to this path. */
	const MISSING = 'missing';

	/** The target is in the trash. */
	const TRASHED = 'trashed';

	/** The target exists but is a draft, pending, private or a protected type. */
	const NON_PUBLIC = 'non_public';

	/** Not a URL a browser could follow. */
	const MALFORMED = 'malformed';

	/** Points off-site; the free tier does not check those. */
	const EXTERNAL = 'external';

	/** Not a web link at all - mailto:, tel:, an anchor. */
	const SKIP = 'skip';

	/**
	 * Could not be settled. Deliberately never reported as a problem.
	 */
	const UNKNOWN = 'unknown';

	/**
	 * Statuses that represent a real, reportable problem.
	 *
	 * @return array
	 */
	public static function problem_statuses() {
		return array( self::MISSING, self::TRASHED, self::NON_PUBLIC, self::MALFORMED );
	}

	/**
	 * Per-request memo, so twenty links to the same page cost one lookup.
	 *
	 * @var array
	 */
	private static $memo = array();

	/**
	 * What does this URL point at?
	 *
	 * @param string $url       Raw URL as found in the content.
	 * @param string $site_host Host that counts as internal.
	 * @param array  $context   Where the URL was found: 'type' is one of the
	 *                          Extractor's occurrence types (link, image,
	 *                          media, block, embed).
	 * @return array {
	 *     @type string $status    One of the class constants.
	 *     @type int    $object_id Post id when one was found.
	 *     @type string $reason    Short machine-readable detail.
	 *     @type string $path      Normalised internal path.
	 * }
	 */
	public static function resolve( $url, $site_host = '', array $context = array() ) {
		$url = trim( (string) $url );

		if ( '' === $site_host ) {
			$site_host = self::site_host();
		}

		$key  = UrlNormalizer::normalize( $url, $site_host );
		$path = UrlNormalizer::to_path( $url, $site_host );

		// The memo is keyed by type as well as URL. The same address found as a
		// link and as an image is two questions, and answering the second from
		// the first would hand a filter a verdict reached under the other one's
		// rules. At most a handful of extra entries; the alternative is a
		// silent wrong answer.
		$type = isset( $context['type'] ) ? (string) $context['type'] : 'link';
		$memo = '' === $key ? '' : $key . '|' . $type;

		if ( '' !== $memo && isset( self::$memo[ $memo ] ) ) {
			return self::$memo[ $memo ];
		}

		// Every path through this method ends at the filter below, including
		// the ones that decline to judge. That matters: "external" is not a
		// verdict, it is this tier saying it does not check off-site links, and
		// adding that check is the headline of the pro link module. If the
		// early returns skipped the filter, the one extension point that exists
		// for external checking could never see an external URL.
		if ( ! UrlNormalizer::is_checkable( $url ) ) {
			$result = self::result( self::SKIP, 0, 'not_a_web_url' );
		} elseif ( self::is_malformed( $url ) ) {
			$result = self::result( self::MALFORMED, 0, 'malformed_url' );
		} elseif ( ! UrlNormalizer::is_internal( $url, $site_host ) ) {
			$result = self::result( self::EXTERNAL, 0, 'external_host' );
		} else {
			$result = self::resolve_internal( $url, $path, $key );
		}

		$result['path'] = $path;

		if ( function_exists( 'apply_filters' ) ) {
			/**
			 * Filter the resolution of one URL.
			 *
			 * Runs for every URL the scanner sees, whatever this tier decided -
			 * internal, external, malformed or skipped. Pro attaches its HTTP
			 * checker here to turn an EXTERNAL verdict into a real one; a site
			 * owner can use it to teach the scanner about custom routing.
			 *
			 * Implementations must return the same array shape: status,
			 * object_id, reason, path.
			 *
			 * @param array  $result    Resolution result.
			 * @param string $url       The URL as found.
			 * @param string $path      Normalised internal path, empty when external.
			 * @param string $site_host The host treated as internal.
			 * @param array  $context   Where it was found: 'type' is one of the
			 *                          Extractor's occurrence types.
			 */
			$result = apply_filters( 'bfr_link_resolution', $result, $url, $path, $site_host, array( 'type' => $type ) );
		}

		// A URL nothing could normalise has no stable key to memoise against.
		if ( '' !== $memo ) {
			self::$memo[ $memo ] = $result;
		}

		return $result;
	}

	/**
	 * Forget the per-request memo.
	 *
	 * The scanner calls this between batches: a run that takes an hour must not
	 * keep answering with what was true when it started.
	 *
	 * @return void
	 */
	public static function flush() {
		self::$memo = array();
	}

	/**
	 * The real work, once we know the URL is internal and well-formed.
	 *
	 * Ordered cheapest and most certain first.
	 *
	 * @param string $url  Original URL.
	 * @param string $path Normalised path.
	 * @param string $key  Normalised key including any query.
	 * @return array
	 */
	private static function resolve_internal( $url, $path, $key ) {
		// On a site with plain permalinks every post URL is "/" plus a query -
		// ?p=12, ?page_id=3 - so the root can only be treated as the home page
		// when there is no query to resolve. Getting this wrong marks every
		// link on such a site as a working link to the front page.
		$has_query = ( false !== strpos( $key, '?' ) );
		$is_root   = ( '' === $path || '/' === $path );

		if ( $is_root && ! $has_query ) {
			return self::result( self::OK, 0, 'home' );
		}

		// A file under uploads is answered by the filesystem, not the router.
		$upload = self::resolve_upload( $path );

		if ( null !== $upload ) {
			return $upload;
		}

		// Anything else that looks like a static file is served by the web
		// server. We cannot see the theme or plugin directories reliably enough
		// to call one missing.
		if ( self::is_static_asset( $path ) ) {
			return self::result( self::UNKNOWN, 0, 'static_asset' );
		}

		// WordPress's own resolver understands permalinks, ?p=, page_id and
		// hierarchical paths.
		$post_id = (int) url_to_postid( $url );

		if ( $post_id > 0 ) {
			return self::post_status_result( $post_id );
		}

		// url_to_postid() only matches published, publicly-queryable posts, so
		// a draft or trashed target still looks like nothing. Ask directly.
		$by_path = self::resolve_by_path( $path );

		if ( null !== $by_path ) {
			return $by_path;
		}

		// A query-driven URL on the site root that nothing recognised: a search,
		// a feed, or any plugin's own routing. There is no path to judge, so
		// judging it would be guessing.
		if ( $is_root ) {
			return self::result( self::UNKNOWN, 0, 'query_url' );
		}

		// Archives: term, post type, date, author, paged, feed. These are
		// generated rather than stored, and getting them wrong is the easiest
		// way to produce false positives.
		$archive = self::resolve_archive( $path );

		if ( null !== $archive ) {
			return $archive;
		}

		// Nothing in the database answers to this path, and it is not shaped
		// like anything WordPress generates. That is a broken link.
		return self::result( self::MISSING, 0, 'no_match' );
	}

	/**
	 * Resolve a path inside the uploads directory.
	 *
	 * @param string $path Normalised path.
	 * @return array|null Null when the path is not an upload.
	 */
	private static function resolve_upload( $path ) {
		if ( ! function_exists( 'wp_get_upload_dir' ) ) {
			return null;
		}

		$uploads = wp_get_upload_dir();

		if ( empty( $uploads['baseurl'] ) || ! empty( $uploads['error'] ) ) {
			return null;
		}

		$base_path = wp_parse_url( $uploads['baseurl'], PHP_URL_PATH );

		if ( ! is_string( $base_path ) || '' === $base_path ) {
			return null;
		}

		$base_path = rtrim( $base_path, '/' );

		if ( 0 !== strpos( $path, $base_path . '/' ) ) {
			return null;
		}

		$relative = substr( $path, strlen( $base_path ) );
		$file     = rtrim( $uploads['basedir'], '/\\' ) . str_replace( '/', DIRECTORY_SEPARATOR, $relative );

		if ( file_exists( $file ) ) {
			return self::result( self::OK, 0, 'upload_file_present' );
		}

		// The file is not on this disk. Before calling that broken, check
		// whether an attachment record exists: offload plugins keep the record
		// and move the file to a bucket, and reporting every one of those as
		// missing would make the feature useless on those sites.
		// The attachment is registered under its uploads URL, not under the site
		// root - looking it up with home_url() finds nothing and reports every
		// offloaded file as broken.
		$attachment = function_exists( 'attachment_url_to_postid' )
			? (int) attachment_url_to_postid( rtrim( $uploads['baseurl'], '/' ) . $relative )
			: 0;

		if ( $attachment > 0 ) {
			return self::result( self::UNKNOWN, $attachment, 'upload_offloaded_or_moved' );
		}

		return self::result( self::MISSING, 0, 'upload_file_absent' );
	}

	/**
	 * Look the path up as a post of any status.
	 *
	 * @param string $path Normalised path.
	 * @return array|null
	 */
	private static function resolve_by_path( $path ) {
		if ( ! function_exists( 'get_page_by_path' ) ) {
			return null;
		}

		$types = get_post_types( array( 'public' => true ), 'names' );

		if ( empty( $types ) ) {
			return null;
		}

		$slug_path = trim( $path, '/' );

		if ( '' === $slug_path ) {
			return null;
		}

		$post = get_page_by_path( $slug_path, OBJECT, array_values( $types ) );

		if ( ! $post ) {
			// A permalink structure with a date or category prefix means the
			// stored slug is only the last segment.
			$segments = explode( '/', $slug_path );
			$last     = end( $segments );

			if ( count( $segments ) > 1 && '' !== $last ) {
				$post = get_page_by_path( $last, OBJECT, array_values( $types ) );
			}
		}

		if ( ! $post ) {
			return null;
		}

		return self::post_status_result( (int) $post->ID );
	}

	/**
	 * Is this path one of the archives WordPress generates?
	 *
	 * @param string $path Normalised path.
	 * @return array|null
	 */
	private static function resolve_archive( $path ) {
		$trimmed = trim( $path, '/' );

		if ( '' === $trimmed ) {
			return null;
		}

		$segments = explode( '/', $trimmed );

		// /page/2, /feed, /embed, /amp and friends hang off another URL whose
		// validity we cannot judge from here.
		foreach ( array( 'page', 'feed', 'embed', 'amp', 'comment-page-1' ) as $suffix ) {
			if ( in_array( $suffix, $segments, true ) ) {
				return self::result( self::UNKNOWN, 0, 'paged_or_feed' );
			}
		}

		// A date archive: /2024, /2024/05, /2024/05/17.
		if ( preg_match( '/^\d{4}(\/\d{2}(\/\d{2})?)?$/', $trimmed ) ) {
			return self::result( self::UNKNOWN, 0, 'date_archive' );
		}

		// A term archive. Verified rather than guessed: the term's own link has
		// to normalise to the same path, otherwise a post that happens to share
		// a slug with a tag would mask a genuinely broken link.
		$term = self::resolve_term( $segments );

		if ( null !== $term ) {
			return $term;
		}

		// A post type archive.
		if ( function_exists( 'get_post_type_archive_link' ) ) {
			foreach ( get_post_types(
				array(
					'public'      => true,
					'has_archive' => true,
				),
				'names'
			) as $type ) {
				$link = get_post_type_archive_link( $type );

				if ( $link && UrlNormalizer::to_path( $link, self::site_host() ) === $path ) {
					return self::result( self::OK, 0, 'post_type_archive' );
				}
			}
		}

		// An author archive.
		if ( function_exists( 'get_option' ) && count( $segments ) >= 2 ) {
			$author_base = self::author_base();

			if ( '' !== $author_base && $segments[0] === $author_base ) {
				return self::result( self::UNKNOWN, 0, 'author_archive' );
			}
		}

		return null;
	}

	/**
	 * Does a public taxonomy term own this path?
	 *
	 * @param array $segments Path segments.
	 * @return array|null
	 */
	private static function resolve_term( array $segments ) {
		if ( ! function_exists( 'get_terms' ) || ! function_exists( 'get_term_link' ) ) {
			return null;
		}

		$slug = end( $segments );

		if ( '' === $slug ) {
			return null;
		}

		$taxonomies = get_taxonomies( array( 'public' => true ), 'names' );

		if ( empty( $taxonomies ) ) {
			return null;
		}

		$terms = get_terms(
			array(
				'taxonomy'   => array_values( $taxonomies ),
				'slug'       => $slug,
				'hide_empty' => false,
				'number'     => 5,
			)
		);

		if ( is_wp_error( $terms ) || empty( $terms ) ) {
			return null;
		}

		$path = '/' . implode( '/', $segments );

		foreach ( $terms as $term ) {
			$link = get_term_link( $term );

			if ( is_wp_error( $link ) ) {
				continue;
			}

			if ( UrlNormalizer::to_path( $link, self::site_host() ) === $path ) {
				return self::result( self::OK, (int) $term->term_id, 'term_archive' );
			}
		}

		return null;
	}

	/**
	 * Turn a post id into a resolution.
	 *
	 * @param int $post_id Post id.
	 * @return array
	 */
	private static function post_status_result( $post_id ) {
		$post = get_post( $post_id );

		if ( ! $post ) {
			return self::result( self::MISSING, 0, 'post_gone' );
		}

		if ( 'trash' === $post->post_status ) {
			return self::result( self::TRASHED, $post_id, 'post_trashed' );
		}

		if ( in_array( $post->post_status, array( 'draft', 'pending', 'auto-draft', 'future' ), true ) ) {
			return self::result( self::NON_PUBLIC, $post_id, 'post_' . $post->post_status );
		}

		if ( 'private' === $post->post_status ) {
			return self::result( self::NON_PUBLIC, $post_id, 'post_private' );
		}

		if ( '' !== $post->post_password ) {
			// Password-protected pages answer, they just ask for a password.
			return self::result( self::OK, $post_id, 'post_password_protected' );
		}

		return self::result( self::OK, $post_id, 'post_published' );
	}

	/**
	 * Is the URL broken in a way no server could answer?
	 *
	 * @param string $url Raw URL.
	 * @return bool
	 */
	private static function is_malformed( $url ) {
		// A scheme with nothing after it, or a doubled scheme left behind by a
		// bad find-and-replace, such as http://https://example.com/x.
		if ( preg_match( '~^https?://\s*$~i', $url ) ) {
			return true;
		}

		if ( preg_match( '~^https?://https?://~i', $url ) ) {
			return true;
		}

		// A raw, unencoded space inside what claims to be an absolute URL.
		if ( preg_match( '~^https?://[^\s]*\s~i', $url ) ) {
			return true;
		}

		// Leftover template syntax that never got rendered.
		if ( false !== strpos( $url, '<?php' ) || false !== strpos( $url, '%3C?php' ) ) {
			return true;
		}

		return false;
	}

	/**
	 * A path that the web server, not WordPress, would answer.
	 *
	 * @param string $path Normalised path.
	 * @return bool
	 */
	private static function is_static_asset( $path ) {
		if ( preg_match( '~^/wp-(content|includes|admin)/~i', $path ) ) {
			return true;
		}

		return (bool) preg_match( '~\.(css|js|png|jpe?g|gif|svg|webp|avif|ico|woff2?|ttf|eot|pdf|zip|mp4|webm|mp3)$~i', $path );
	}

	/**
	 * The author permalink base, e.g. "author".
	 *
	 * @return string
	 */
	private static function author_base() {
		global $wp_rewrite;

		if ( isset( $wp_rewrite->author_base ) && is_string( $wp_rewrite->author_base ) ) {
			return trim( $wp_rewrite->author_base, '/' );
		}

		return 'author';
	}

	/**
	 * This site's host.
	 *
	 * @return string
	 */
	public static function site_host() {
		static $host = null;

		if ( null !== $host ) {
			return $host;
		}

		$host = '';

		if ( function_exists( 'home_url' ) ) {
			$parsed = wp_parse_url( home_url( '/' ), PHP_URL_HOST );
			$host   = is_string( $parsed ) ? $parsed : '';
		}

		return $host;
	}

	/**
	 * Shape a resolution result.
	 *
	 * @param string $status    Status constant.
	 * @param int    $object_id Related object id.
	 * @param string $reason    Machine-readable detail.
	 * @return array
	 */
	private static function result( $status, $object_id = 0, $reason = '' ) {
		return array(
			'status'    => $status,
			'object_id' => (int) $object_id,
			'reason'    => $reason,
			'path'      => '',
		);
	}
}
