<?php namespace RealTimeAutoFindReplace\Maintenance\ReplaceRedirect;

use RealTimeAutoFindReplace\Maintenance\Support\Logger;

/**
 * Purges page caches after content has been rewritten.
 *
 * Rewriting a URL in the database changes nothing a visitor sees while a page
 * cache is still serving the old HTML, so this runs after every Replace +
 * Redirect. Two things it deliberately does not do:
 *
 * **It does not call wp_cache_flush().** That empties the object cache, which
 * is shared with the rest of WordPress and with every other plugin - it would
 * evict thousands of unrelated entries to fix our own page, and on a busy site
 * that is a self-inflicted stampede. The object cache is already handled: the
 * replacement engine calls its own regenerate_caches() for builder and post
 * caches.
 *
 * **It does not claim more than it did.** Only a handful of cache plugins can be
 * purged programmatically, and there is no way to reach a CDN, a reverse proxy,
 * or a host-level cache from here. So it returns what it actually purged and
 * what it could not, and the UI says so - "we cleared WP Rocket, you may need to
 * purge your CDN" is useful; a silent claim that everything is fresh is not.
 *
 * @package Maintenance
 * @since 1.10.0
 * @author M.Tuhin <info@codesolz.net>
 */

if ( ! defined( 'CS_RTAFAR_VERSION' ) ) {
	exit;
}

class CacheFlush {

	/**
	 * Purge whatever page caches this site has.
	 *
	 * @return array {
	 *     @type array $purged   Human-readable names of caches that were cleared.
	 *     @type bool  $complete Whether anything is known to remain stale.
	 * }
	 */
	public static function run() {
		$purged = array();

		foreach ( self::handlers() as $name => $handler ) {
			if ( ! $handler['detect']() ) {
				continue;
			}

			try {
				$handler['purge']();
				$purged[] = $name;
			} catch ( \Throwable $e ) {
				// A cache plugin that throws is not a reason to fail the
				// operation - the content change already succeeded.
				Logger::log( 'cache.purge_failed', array( 'cache' => $name ) );
			}
		}

		/**
		 * Fires after a Replace + Redirect, for caches this class cannot detect.
		 *
		 * Add the name of anything you purge to the array so the user is told
		 * about it.
		 *
		 * @param array $purged Names of caches already cleared.
		 */
		$purged = (array) apply_filters( 'bfr_maintenance_flush_caches', $purged );

		if ( ! empty( $purged ) ) {
			Logger::log( 'cache.purged', array( 'count' => count( $purged ) ) );
		}

		return array(
			'purged'   => array_values( array_unique( $purged ) ),
			'complete' => false,
		);
	}

	/**
	 * A sentence for the user about what is and is not fresh.
	 *
	 * Written to be honest in both directions: it names what was cleared, and
	 * it never implies that a CDN or server-level cache was touched, because it
	 * was not.
	 *
	 * @param array $result Output of run().
	 * @return string
	 */
	public static function describe( array $result ) {
		if ( empty( $result['purged'] ) ) {
			return __( 'If you use a caching plugin, a CDN or server-side caching, clear it so visitors see the change.', 'real-time-auto-find-and-replace' );
		}

		return sprintf(
			/* translators: %s: comma-separated list of cache plugin names */
			__( 'Cleared: %s. If you also use a CDN or server-side caching, clear that too.', 'real-time-auto-find-and-replace' ),
			implode( ', ', $result['purged'] )
		);
	}

	/**
	 * The caches we know how to clear.
	 *
	 * Each entry detects its plugin and purges it. Kept to plugins that expose
	 * a documented, global purge - guessing at internals is how a cache plugin
	 * update turns into a fatal on somebody's site.
	 *
	 * @return array
	 */
	private static function handlers() {
		return array(
			'WP Rocket'            => array(
				'detect' => function () {
					return function_exists( 'rocket_clean_domain' );
				},
				'purge'  => function () {
					rocket_clean_domain();
				},
			),
			'W3 Total Cache'       => array(
				'detect' => function () {
					return function_exists( 'w3tc_flush_all' );
				},
				'purge'  => function () {
					w3tc_flush_all();
				},
			),
			'WP Super Cache'       => array(
				'detect' => function () {
					return function_exists( 'wp_cache_clear_cache' );
				},
				'purge'  => function () {
					wp_cache_clear_cache();
				},
			),
			'LiteSpeed Cache'      => array(
				'detect' => function () {
					return defined( 'LSCWP_V' ) || class_exists( '\LiteSpeed\Purge' );
				},
				'purge'  => function () {
					do_action( 'litespeed_purge_all' );
				},
			),
			'WP Fastest Cache'     => array(
				'detect' => function () {
					return isset( $GLOBALS['wp_fastest_cache'] ) && method_exists( $GLOBALS['wp_fastest_cache'], 'deleteCache' );
				},
				'purge'  => function () {
					$GLOBALS['wp_fastest_cache']->deleteCache( true );
				},
			),
			'Cache Enabler'        => array(
				'detect' => function () {
					return class_exists( '\Cache_Enabler' ) && method_exists( '\Cache_Enabler', 'clear_complete_cache' );
				},
				'purge'  => function () {
					\Cache_Enabler::clear_complete_cache();
				},
			),
			'SiteGround Optimizer' => array(
				'detect' => function () {
					return function_exists( 'sg_cachepress_purge_cache' );
				},
				'purge'  => function () {
					sg_cachepress_purge_cache();
				},
			),
			'Breeze'               => array(
				'detect' => function () {
					return class_exists( '\Breeze_PurgeCache' );
				},
				'purge'  => function () {
					do_action( 'breeze_clear_all_cache' );
				},
			),
			'Autoptimize'          => array(
				'detect' => function () {
					return class_exists( '\autoptimizeCache' ) && method_exists( '\autoptimizeCache', 'clearall' );
				},
				'purge'  => function () {
					\autoptimizeCache::clearall();
				},
			),
		);
	}
}
