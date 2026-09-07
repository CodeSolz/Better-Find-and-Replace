<?php namespace RealTimeAutoFindReplace\Maintenance\NotFound;

use RealTimeAutoFindReplace\Maintenance\Data\NotFoundRepository;
use RealTimeAutoFindReplace\Maintenance\Redirects\Executor;
use RealTimeAutoFindReplace\Maintenance\Support\UrlNormalizer;

/**
 * Records requests that ended in a 404.
 *
 * The platform's second and last front-end hook, and it runs to the same budget
 * as the first: a site with the monitor switched off answers from one
 * autoloaded option and returns before loading anything else.
 *
 * Priority 999 on template_redirect, which is deliberate in both directions.
 * Late enough that the redirect executor at priority 1 has already had its turn
 * - a request that gets redirected is not a 404 and must never be logged as one
 * - and late enough that anything else claiming to handle the request has run
 * too.
 *
 * Normalisation here is more aggressive than anywhere else in the platform:
 * paths are lower-cased and query strings dropped, so a scanner probing /Admin,
 * /ADMIN and /admin?x=1 produces one row rather than three. That is the right
 * trade for an aggregation view, and the wrong one for link health - which is
 * why it is an option on UrlNormalizer rather than its default.
 *
 * @package Maintenance
 * @since 1.10.0
 * @author M.Tuhin <info@codesolz.net>
 */

if ( ! defined( 'CS_RTAFAR_VERSION' ) ) {
	exit;
}

class Capture {

	/**
	 * Priority of the template_redirect hook.
	 *
	 * Must stay well above Executor::PRIORITY so a redirected request is never
	 * counted as a 404.
	 */
	const PRIORITY = 999;

	/**
	 * Hook the front end.
	 *
	 * @return void
	 */
	public function register() {
		add_action( 'template_redirect', array( $this, 'maybe_record' ), self::PRIORITY );
	}

	/**
	 * Record this request if it 404ed and is worth keeping.
	 *
	 * @return void
	 */
	public function maybe_record() {
		// One autoloaded option. A site that never switched the monitor on does
		// no more work than this.
		if ( ! NotFoundRepository::is_enabled() ) {
			return;
		}

		if ( ! Executor::should_run() ) {
			return;
		}

		if ( ! is_404() ) {
			return;
		}

		$request = Executor::request_path();

		if ( '' === $request ) {
			return;
		}

		$path = UrlNormalizer::normalize(
			$request,
			'',
			array(
				'lower_path' => true,
				'drop_query' => true,
			)
		);

		if ( '' === $path || '/' === $path ) {
			return;
		}

		$user_agent = isset( $_SERVER['HTTP_USER_AGENT'] )
			? substr( (string) wp_unslash( $_SERVER['HTTP_USER_AGENT'] ), 0, 500 ) // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
			: '';

		$should_log = BotFilter::should_log( $path, $user_agent );

		/**
		 * Filter whether one 404 is recorded.
		 *
		 * Pro uses this for smarter crawler detection; a site owner can use it
		 * to stop logging a path they already know about.
		 *
		 * @param bool   $should_log Whether to record it.
		 * @param string $path       Normalised path.
		 * @param array  $context    Raw request, referrer and user agent.
		 */
		$should_log = apply_filters(
			'bfr_404_should_log',
			$should_log,
			$path,
			array(
				'request'    => $request,
				'referrer'   => self::referrer(),
				'user_agent' => $user_agent,
			)
		);

		if ( ! $should_log ) {
			return;
		}

		NotFoundRepository::record(
			array(
				'path'       => $path,
				'raw_path'   => $request,
				'referrer'   => self::referrer(),
				'user_agent' => $user_agent,
			)
		);
	}

	/**
	 * Where the visitor came from, if the browser said.
	 *
	 * @return string
	 */
	private static function referrer() {
		if ( ! isset( $_SERVER['HTTP_REFERER'] ) ) {
			return '';
		}

		$referrer = wp_unslash( $_SERVER['HTTP_REFERER'] ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized

		if ( ! is_string( $referrer ) ) {
			return '';
		}

		return esc_url_raw( substr( $referrer, 0, 255 ) );
	}
}
