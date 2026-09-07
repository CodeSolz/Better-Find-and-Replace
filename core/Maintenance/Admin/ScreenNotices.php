<?php namespace RealTimeAutoFindReplace\Maintenance\Admin;

/**
 * Keeps admin notices out of the maintenance screens.
 *
 * These screens are working surfaces: a list you are triaging, a form you are
 * filling in. A promotional notice landing in the middle of that is noise at
 * best, and the way WordPress positions notices makes it worse than noise here.
 *
 * How the notices got *inside* the panel, which is worth recording because it
 * is not obvious. wp-admin/js/common.js relocates every notice on the page:
 *
 *     if ( ! $headerEnd.length ) {
 *         $headerEnd = $( '.wrap h1, .wrap h2' ).first();
 *     }
 *     $( 'div.updated, div.error, div.notice' ).not( '.inline, .below-h2' )
 *         .insertAfter( $headerEnd );
 *
 * AdminPageBuilder emits no `.wp-header-end` marker and titles its panel with an
 * `h3`, so on the plugin's older screens nothing matches and the notices simply
 * stay at the top of the page. Adding a tabbed `<h2 class="nav-tab-wrapper">`
 * changed that: it became the first `h2` inside `.wrap`, and every notice on the
 * page got moved to just below the tabs - inside the panel body, above the form.
 *
 * So there are two fixes, and both are here for a reason:
 *
 *   1. the tab rows are rendered as `div.nav-tab-wrapper` rather than `h2`, so
 *      nothing can be relocated into the panel even if it is displayed;
 *   2. the notice hooks are emptied for these screens, which is what actually
 *      keeps them out.
 *
 * The second is deliberately scoped to our own pages, on `in_admin_header`, so
 * nothing is removed for the rest of wp-admin. It is still a blunt instrument -
 * a genuinely urgent core or third-party message will not be shown here either -
 * which is why it is one call in one place that can be reversed by deleting it,
 * rather than scattered around the screens.
 *
 * @package Maintenance
 * @since 1.10.0
 * @author M.Tuhin <info@codesolz.net>
 */

if ( ! defined( 'CS_RTAFAR_VERSION' ) ) {
	exit;
}

class ScreenNotices {

	/**
	 * Every hook WordPress prints notices from.
	 *
	 * @var array
	 */
	private static $hooks = array(
		'admin_notices',
		'all_admin_notices',
		'user_admin_notices',
		'network_admin_notices',
	);

	/**
	 * Suppress notices for the screen being loaded.
	 *
	 * Call from a screen's `load-{$hook}` handler, so it applies to that one
	 * request and that one page.
	 *
	 * @return void
	 */
	public static function suppress() {
		// in_admin_header fires immediately before the notice hooks are run,
		// which is late enough for every plugin to have registered and early
		// enough that nothing has been printed yet.
		add_action( 'in_admin_header', array( __CLASS__, 'remove_all' ), PHP_INT_MAX );
	}

	/**
	 * Empty the notice hooks.
	 *
	 * @return void
	 */
	public static function remove_all() {
		foreach ( self::$hooks as $hook ) {
			remove_all_actions( $hook );
		}
	}

	/**
	 * The hooks this class empties.
	 *
	 * @return array
	 */
	public static function hooks() {
		return self::$hooks;
	}
}
