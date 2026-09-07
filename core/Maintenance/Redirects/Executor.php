<?php namespace RealTimeAutoFindReplace\Maintenance\Redirects;

use RealTimeAutoFindReplace\Maintenance\Data\RedirectRepository;
use RealTimeAutoFindReplace\Maintenance\Support\UrlNormalizer;

/**
 * Sends visitors to the right place.
 *
 * This is the only code in the platform that runs for people who are not logged
 * in, on every single request, so it is written to the budget in
 * 00-ARCHITECTURE-AUDIT.md §4:
 *
 *   - hooked at template_redirect priority 1, ahead of the plugin's existing
 *     output-buffering filter at 10, so a redirected page never pays for a full
 *     render first;
 *   - a site with no redirects answers from one autoloaded option and returns
 *     before loading another class or running a single query;
 *   - a site with redirects costs exactly one indexed lookup by source_hash;
 *   - nothing runs at all in admin, AJAX, cron, REST, CLI or on wp-login.php.
 *
 * Free matches exact sources only. Prefix and regex matching are pro (M7) and
 * attach through bfr_redirect_match, which is consulted only after the exact
 * lookup misses - so the common case never pays for the expensive one.
 *
 * @package Maintenance
 * @since 1.10.0
 * @author M.Tuhin <info@codesolz.net>
 */

if ( ! defined( 'CS_RTAFAR_VERSION' ) ) {
	exit;
}

class Executor {

	/**
	 * Priority of the template_redirect hook.
	 *
	 * Must stay below RTAFAR_WP_Hooks::rtafar_filter_contents at 10.
	 */
	const PRIORITY = 1;

	/**
	 * Hook the front end.
	 *
	 * @return void
	 */
	public function register() {
		add_action( 'template_redirect', array( $this, 'maybe_redirect' ), self::PRIORITY );
	}

	/**
	 * Redirect this request, if it is one we know about.
	 *
	 * @return void
	 */
	public function maybe_redirect() {
		if ( ! self::should_run() ) {
			return;
		}

		// The whole front-end budget: one autoloaded option read. A site that
		// has never created a redirect stops here, before any class below this
		// one is loaded or any query is made.
		if ( RedirectRepository::guard_says_none() ) {
			return;
		}

		$request = self::request_path();

		if ( '' === $request ) {
			return;
		}

		$site_host = self::site_host();
		$hash      = UrlNormalizer::hash( $request, $site_host );

		if ( '' === $hash ) {
			return;
		}

		$match = RedirectRepository::find_enabled( $hash );

		if ( ! $match ) {
			/**
			 * Filter in a non-exact match.
			 *
			 * Runs only after the exact lookup missed, so prefix and regex
			 * matching in pro never slows down the ordinary case.
			 *
			 * @param object|null $match   Matched redirect, or null.
			 * @param string      $request The requested path.
			 */
			$match = apply_filters( 'bfr_redirect_match', null, $request );
		}

		if ( ! $match || empty( $match->destination ) ) {
			return;
		}

		/**
		 * Fires before a redirect is sent. Return false to cancel it.
		 *
		 * @param object $match   The redirect about to fire.
		 * @param string $request The requested path.
		 */
		$proceed = apply_filters( 'bfr_redirect_before_execute', true, $match, $request );

		if ( ! $proceed ) {
			return;
		}

		$destination = self::destination_url( $match->destination );

		if ( '' === $destination ) {
			return;
		}

		$type = isset( $match->redirect_type ) ? (int) $match->redirect_type : 301;

		if ( ! in_array( $type, array( 301, 302, 307, 308 ), true ) ) {
			$type = 301;
		}

		// A redirect that points at the URL just requested would loop. The
		// validator refuses to save one, but a destination can become
		// equivalent later - through a permalink change, say.
		if ( UrlNormalizer::hash( $destination, $site_host ) === $hash ) {
			return;
		}

		// wp_safe_redirect, always: it refuses off-site hosts unless they are
		// allow-listed, which is what stops a stored destination becoming an
		// open redirect.
		wp_safe_redirect( $destination, $type, 'Better Find and Replace' );

		// Hand the response to the visitor before doing bookkeeping, where the
		// platform allows it. The redirect is what they asked for; the counter
		// is for us.
		if ( function_exists( 'fastcgi_finish_request' ) ) {
			fastcgi_finish_request();
		}

		if ( isset( $match->id ) ) {
			RedirectRepository::record_hit( (int) $match->id );
		}

		/**
		 * Fires after a redirect has been sent to the visitor.
		 *
		 * Deliberately here rather than before the redirect: the response has
		 * already been flushed by this point where the platform allows it, so
		 * bookkeeping attached here costs the visitor nothing. Anything that
		 * needs to *change* the redirect belongs on
		 * `bfr_redirect_before_execute` instead - by the time this runs it is
		 * far too late.
		 *
		 * @param object $match   The redirect that fired.
		 * @param string $request The requested path.
		 */
		do_action( 'bfr_redirect_executed', $match, $request );

		exit;
	}

	/**
	 * Is this a request we should even look at?
	 *
	 * @return bool
	 */
	public static function should_run() {
		if ( is_admin() ) {
			return false;
		}

		if ( defined( 'DOING_AJAX' ) && DOING_AJAX ) {
			return false;
		}

		if ( defined( 'DOING_CRON' ) && DOING_CRON ) {
			return false;
		}

		if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) {
			return false;
		}

		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			return false;
		}

		if ( defined( 'XMLRPC_REQUEST' ) && XMLRPC_REQUEST ) {
			return false;
		}

		// A redirect that catches the login screen locks everyone out.
		if ( isset( $GLOBALS['pagenow'] ) && 'wp-login.php' === $GLOBALS['pagenow'] ) {
			return false;
		}

		return true;
	}

	/**
	 * The path the visitor asked for.
	 *
	 * @return string
	 */
	public static function request_path() {
		if ( ! isset( $_SERVER['REQUEST_URI'] ) ) {
			return '';
		}

		// Unslashed and length-capped before anything else touches it: this is
		// the one genuinely attacker-controlled string in the platform.
		$uri = wp_unslash( $_SERVER['REQUEST_URI'] ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized

		if ( ! is_string( $uri ) ) {
			return '';
		}

		if ( strlen( $uri ) > 2000 ) {
			return '';
		}

		return $uri;
	}

	/**
	 * Turn a stored destination into a URL to send.
	 *
	 * @param string $destination Stored destination.
	 * @return string
	 */
	private static function destination_url( $destination ) {
		$destination = trim( (string) $destination );

		if ( '' === $destination ) {
			return '';
		}

		$scheme = UrlNormalizer::scheme_of( $destination );

		if ( '' !== $scheme ) {
			return in_array( $scheme, array( 'http', 'https' ), true ) ? $destination : '';
		}

		if ( 0 !== strpos( $destination, '/' ) ) {
			return '';
		}

		return home_url( $destination );
	}

	/**
	 * This site's host.
	 *
	 * @return string
	 */
	private static function site_host() {
		static $host = null;

		if ( null !== $host ) {
			return $host;
		}

		$parsed = wp_parse_url( home_url( '/' ), PHP_URL_HOST );
		$host   = is_string( $parsed ) ? $parsed : '';

		return $host;
	}
}
