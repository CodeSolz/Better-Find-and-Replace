<?php namespace RealTimeAutoFindReplace\Maintenance\NotFound;

/**
 * Decides whether a 404 is worth recording.
 *
 * Deliberately conservative, and the asymmetry is the whole design: filtering
 * out a real visitor loses the one signal that would have told somebody a page
 * they published is unreachable, while letting a crawler through costs one row
 * that the daily budget already caps. So when in doubt, this logs.
 *
 * It is not a security control and makes no attempt to be. A scanner that lies
 * about its user agent gets logged, which is fine - that is what the budget and
 * the retention window are for.
 *
 * No WordPress functions, so the unit suite exercises it directly.
 *
 * @package Maintenance
 * @since 1.10.0
 * @author M.Tuhin <info@codesolz.net>
 */

if ( ! defined( 'CS_RTAFAR_VERSION' ) ) {
	exit;
}

class BotFilter {

	/**
	 * User-agent fragments that identify a crawler beyond reasonable doubt.
	 *
	 * Matched case-insensitively as substrings. Kept short on purpose: a long
	 * list of guesses is how real browsers start getting filtered.
	 *
	 * @var array
	 */
	private static $agents = array(
		'bot',
		'crawler',
		'spider',
		'slurp',
		'facebookexternalhit',
		'ia_archiver',
		'feedfetcher',
		'curl/',
		'wget/',
		'python-requests',
		'python-urllib',
		'go-http-client',
		'okhttp',
		'headlesschrome',
		'phantomjs',
		'ahrefs',
		'semrush',
		'mj12',
		'dotbot',
		'petalbot',
		'bytespider',
		'zgrab',
		'masscan',
		'nuclei',
	);

	/**
	 * Paths that are noise whoever asked for them.
	 *
	 * Probes for software this site does not run, and the handful of files
	 * browsers and tools request on their own. None of them is ever a link
	 * somebody published, which is what this monitor is for.
	 *
	 * @var array
	 */
	private static $paths = array(
		'/wp-content/plugins/',
		'/wp-content/themes/',
		'/wp-includes/',
		'/vendor/',
		'/.git',
		'/.env',
		'/.well-known/',
		'/autodiscover/',
		'/owa/',
		'/cgi-bin/',
		'/phpmyadmin',
		'/phpunit',
		'/wp-config',
		'/xmlrpc.php',
		'/apple-touch-icon',
		'/favicon.ico',
		'/robots.txt',
		'/sitemap',
		'/ads.txt',
		'/browserconfig.xml',
		'/service-worker.js',
	);

	/**
	 * File extensions that mean an asset, not a page.
	 *
	 * A missing asset is a media-health problem, not a 404 worth a redirect,
	 * and logging them buries the rows that matter.
	 *
	 * @var array
	 */
	private static $extensions = array(
		'css',
		'js',
		'map',
		'png',
		'jpg',
		'jpeg',
		'gif',
		'svg',
		'webp',
		'avif',
		'ico',
		'woff',
		'woff2',
		'ttf',
		'eot',
		'mp4',
		'webm',
		'mp3',
		'zip',
		'gz',
	);

	/**
	 * Should this request be recorded?
	 *
	 * @param string $path       Normalised request path.
	 * @param string $user_agent Raw user agent, or ''.
	 * @return bool
	 */
	public static function should_log( $path, $user_agent = '' ) {
		$path       = (string) $path;
		$user_agent = (string) $user_agent;

		if ( '' === $path ) {
			return false;
		}

		if ( self::is_bot( $user_agent ) ) {
			return false;
		}

		if ( self::is_noise( $path ) ) {
			return false;
		}

		return true;
	}

	/**
	 * Does this user agent belong to a crawler or a script?
	 *
	 * @param string $user_agent Raw user agent.
	 * @return bool
	 */
	public static function is_bot( $user_agent ) {
		$user_agent = strtolower( trim( (string) $user_agent ) );

		// No user agent at all is a script often enough, and a browser rarely
		// enough, that it is safe to skip.
		if ( '' === $user_agent ) {
			return true;
		}

		foreach ( self::$agents as $fragment ) {
			if ( false !== strpos( $user_agent, $fragment ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Is this path noise rather than a page somebody linked to?
	 *
	 * @param string $path Normalised request path.
	 * @return bool
	 */
	public static function is_noise( $path ) {
		$path  = (string) $path;
		$lower = strtolower( $path );

		foreach ( self::$paths as $prefix ) {
			if ( 0 === strpos( $lower, $prefix ) ) {
				return true;
			}
		}

		$clean     = strtok( $lower, '?' );
		$extension = strtolower( (string) pathinfo( (string) $clean, PATHINFO_EXTENSION ) );

		if ( '' !== $extension && in_array( $extension, self::$extensions, true ) ) {
			return true;
		}

		// A path that is mostly punctuation, or absurdly deep, is a probe.
		if ( substr_count( $path, '/' ) > 10 ) {
			return true;
		}

		return false;
	}
}
