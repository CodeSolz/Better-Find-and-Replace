<?php namespace RealTimeAutoFindReplace\Maintenance\CodeInsert;

use RealTimeAutoFindReplace\Maintenance\Support\Entitlements;

/**
 * Putting the three snippets on the page.
 *
 * The second front-end write-path in this project, after the redirect executor,
 * and it follows the same rule: **a site that has never used this feature pays
 * one autoloaded option read and returns.** Three hooks that each loaded a class
 * and ran a query would be a real regression on every page view of every site
 * that does not want the feature, which is most of them.
 *
 * Where it does not run, and why each one matters:
 *
 * - **the admin.** A tracking tag firing on wp-admin is at best noise in
 *   somebody's analytics and at worst a script running against a logged-in
 *   administrator's session;
 * - **feeds.** A feed is XML. A `<script>` in it makes it invalid XML;
 * - **REST and AJAX.** Both emit JSON, and neither runs `wp_head` for a reason;
 * - **robots.txt, sitemaps, and any other non-HTML response** WordPress routes
 *   through the template loader.
 *
 * Output is printed exactly as stored. That is what the feature is: the content
 * is markup the site owner wrote and wants on the page. The safety is upstream,
 * in `Guard` - see `Snippets` for why there is no escaping that could sit here
 * without breaking every snippet it was meant to protect.
 *
 * @package Maintenance
 * @since 1.10.0
 * @author M.Tuhin <info@codesolz.net>
 */

if ( ! defined( 'CS_RTAFAR_VERSION' ) ) {
	exit;
}

class Printer {

	/**
	 * Late in `<head>`, so a snippet can override what the theme set.
	 */
	const HEAD_PRIORITY = 99;

	/**
	 * Hook the front end.
	 *
	 * @return void
	 */
	public function register() {
		add_action( 'wp_head', array( $this, 'print_header' ), self::priority( Snippets::HEADER ) );
		add_action( 'wp_body_open', array( $this, 'print_body' ), self::priority( Snippets::BODY, 1 ) );
		add_action( 'wp_footer', array( $this, 'print_footer' ), self::priority( Snippets::FOOTER ) );
	}

	/**
	 * Where in the hook this slot prints.
	 *
	 * A seam rather than a setting, because free has no screen for it and the
	 * default is right for almost everybody. It exists because "my tag has to
	 * load before the consent banner" is a real request with no other answer,
	 * and because the alternative - a customer editing the plugin - is worse.
	 *
	 * @param string $slot    One of Snippets::slots().
	 * @param int    $fallback Priority when nothing filters it.
	 * @return int
	 */
	public static function priority( $slot, $fallback = self::HEAD_PRIORITY ) {
		/**
		 * Filter the hook priority one slot prints at.
		 *
		 * @param int    $priority Hook priority.
		 * @param string $slot     Which slot.
		 */
		return (int) apply_filters( 'bfr_code_insert_priority', (int) $fallback, (string) $slot );
	}

	/**
	 * Print the header slot.
	 *
	 * @return void
	 */
	public function print_header() {
		$this->print_slot( Snippets::HEADER );
	}

	/**
	 * Print the body-start slot.
	 *
	 * @return void
	 */
	public function print_body() {
		$this->print_slot( Snippets::BODY );
	}

	/**
	 * Print the footer slot.
	 *
	 * @return void
	 */
	public function print_footer() {
		$this->print_slot( Snippets::FOOTER );
	}

	/**
	 * Should anything print at all on this request?
	 *
	 * Ordered cheapest first: the context checks are function calls against
	 * flags WordPress has already set, and the option read is last.
	 *
	 * @return bool
	 */
	public static function should_run() {
		if ( is_admin() ) {
			return false;
		}

		// Asked before anything else because it is the cheapest question and
		// the most decisive: without the licence this feature does not exist,
		// so a free site pays one entitlement lookup on a page view and stops.
		//
		// The consequence is worth stating plainly: a site that lets its licence
		// lapse stops printing its snippets. That is what "a paid feature"
		// means, and the alternative - carrying on indefinitely - would make the
		// paywall a suggestion. The snippets themselves are never deleted, so
		// renewing brings them straight back.
		if ( ! Entitlements::can( 'code_insert.global' ) ) {
			return false;
		}

		if ( function_exists( 'is_feed' ) && is_feed() ) {
			return false;
		}

		if ( function_exists( 'wp_doing_ajax' ) && wp_doing_ajax() ) {
			return false;
		}

		if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) {
			return false;
		}

		if ( defined( 'DOING_CRON' ) && DOING_CRON ) {
			return false;
		}

		if ( function_exists( 'is_robots' ) && is_robots() ) {
			return false;
		}

		/**
		 * Filter whether code snippets print on this request.
		 *
		 * Returning false is honoured; returning true cannot re-enable a
		 * request the checks above ruled out, because this runs after them.
		 *
		 * @param bool $run Whether to print.
		 */
		return (bool) apply_filters( 'bfr_code_insert_should_run', true );
	}

	/**
	 * Print one slot, if there is anything in it for this request.
	 *
	 * @param string $slot One of Snippets::slots().
	 * @return void
	 */
	private function print_slot( $slot ) {
		if ( ! self::should_run() ) {
			return;
		}

		// The whole budget for a site that does not use this: one autoloaded
		// option read, and out. Nothing below this line runs on such a site.
		if ( ! Snippets::any() ) {
			return;
		}

		$snippet = Snippets::get( $slot );

		if ( empty( $snippet['enabled'] ) || '' === trim( $snippet['content'] ) ) {
			return;
		}

		$content = (string) $snippet['content'];

		/**
		 * Filter one snippet immediately before it is printed.
		 *
		 * This is where pro attaches its conditions: returning an empty string
		 * suppresses the snippet for this request. It is deliberately the last
		 * word, so a condition can veto output that free would otherwise print,
		 * and deliberately cannot introduce content of its own that free's
		 * capability check never saw - the value handed in is what was stored.
		 *
		 * @param string $content The stored snippet.
		 * @param string $slot    Which slot it is.
		 */
		$content = (string) apply_filters( 'bfr_code_insert_output', $content, $slot );

		if ( '' === trim( $content ) ) {
			return;
		}

		// Printed unescaped, which is the entire feature: this is markup the
		// site owner wrote and asked to have on the page. Guard decides who is
		// allowed to be that site owner.
		echo "\n<!-- Better Find and Replace: {$slot} -->\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo $content; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo "\n";
	}
}
