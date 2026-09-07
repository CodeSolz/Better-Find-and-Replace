<?php namespace RealTimeAutoFindReplace\Maintenance\CodeInsert;

use RealTimeAutoFindReplace\Maintenance\Support\Capabilities;
use RealTimeAutoFindReplace\Maintenance\Support\Entitlements;

/**
 * Who may put raw markup on every page of this site.
 *
 * Every other module in this platform treats `manage_options` as sufficient.
 * This one does not, and the difference is the point of the class.
 *
 * WordPress already has a capability meaning exactly *"may save unfiltered
 * markup"*: `unfiltered_html`. On a single site an administrator holds it; on
 * multisite **only a super admin does**, which is precisely the right answer for
 * a textarea that injects script into every page of every site in a network. A
 * network's site administrators can be given `manage_options` for their own
 * site without being handed the whole network, and this module has to respect
 * that distinction even though the rest of the plugin has no reason to.
 *
 * So editing needs both:
 *
 * - the `code_inserts` module capability, which decides who sees the screen;
 * - `unfiltered_html`, which decides who may save.
 *
 * They are separate on purpose. Somebody may legitimately be allowed to look at
 * what is running on the site without being allowed to change it, and a screen
 * that shows a dead Save button is kinder than one that 403s on submit.
 *
 * @package Maintenance
 * @since 1.10.0
 * @author M.Tuhin <info@codesolz.net>
 */

if ( ! defined( 'CS_RTAFAR_VERSION' ) ) {
	exit;
}

class Guard {

	/** The WordPress capability that means "may save raw markup". */
	const RAW_CAP = 'unfiltered_html';

	/**
	 * May the current user see the Code Inserts screen?
	 *
	 * @return bool
	 */
	public static function can_view() {
		return Capabilities::current_user_can( 'code_inserts' );
	}

	/**
	 * May the current user save a snippet?
	 *
	 * Both halves, always, and never `manage_options` on its own - on multisite
	 * that would hand every site administrator the ability to run script on
	 * their site, which is the one thing `unfiltered_html` exists to withhold.
	 *
	 * @return bool
	 */
	public static function can_edit() {
		if ( ! self::can_view() ) {
			return false;
		}

		// Code inserts are a paid feature. The capability checks below decide
		// *who* on a licensed site may save; this decides whether the site has
		// the feature at all, and it is asked first because no amount of
		// capability makes an unlicensed feature available.
		if ( ! Entitlements::can( 'code_insert.global' ) ) {
			return false;
		}

		if ( ! function_exists( 'current_user_can' ) ) {
			return false;
		}

		$can = current_user_can( self::RAW_CAP );

		/**
		 * Filter whether the current user may save code snippets.
		 *
		 * Exists so a site can tighten this further - a filter that returns
		 * false is honoured. It cannot loosen it: the return is ANDed with the
		 * capability check above, so nothing added here can grant an ability
		 * WordPress itself has withheld.
		 *
		 * @param bool $can Whether editing is allowed.
		 */
		return (bool) apply_filters( 'bfr_code_insert_can_edit', $can ) && $can;
	}

	/**
	 * Why the current user cannot save, in a sentence for the screen.
	 *
	 * Returns an empty string when they can. Saying which half is missing
	 * matters: "you are an administrator but this network reserves raw markup
	 * for super admins" is actionable, and "permission denied" is not.
	 *
	 * @return string
	 */
	public static function reason() {
		if ( ! self::can_view() ) {
			return __( 'You do not have permission to view this page.', 'real-time-auto-find-and-replace' );
		}

		if ( self::can_edit() ) {
			return '';
		}

		// Distinguished on purpose: "your licence does not include this" and
		// "your account is not allowed to do this" are different problems with
		// different fixes, and telling somebody the wrong one wastes their
		// afternoon.
		if ( ! Entitlements::can( 'code_insert.global' ) ) {
			return __( 'Code inserts are part of Better Find and Replace Pro.', 'real-time-auto-find-and-replace' );
		}

		if ( is_multisite() ) {
			return __( 'On a network, only super admins can save code that runs on the site. You can see what is here, but not change it.', 'real-time-auto-find-and-replace' );
		}

		return __( 'Your account is not allowed to save unfiltered HTML, so you can see what is here but not change it.', 'real-time-auto-find-and-replace' );
	}
}
