<?php namespace RealTimeAutoFindReplace\Maintenance\Support;

use RealTimeAutoFindReplace\lib\Util;

/**
 * WordPress capabilities for the maintenance screens.
 *
 * These are real capabilities, not role checks. They are declared in
 * Util::bfar_nav_cap() and nowhere else, because two existing systems iterate
 * that map: the role-grant loop in RTAFAR_WP_Hooks::rtafar_role_caps(), which
 * is what actually gives administrators the capability, and the User Role
 * Editor integration, which is what lets a site owner delegate it. A capability
 * invented anywhere else is never granted to anybody.
 *
 * Never `current_user_can( 'administrator' )`. That reads a role name as a
 * capability; it appears in older screens here and is a bug, not a pattern.
 *
 * @package Maintenance
 * @since 1.10.0
 * @author M.Tuhin <info@codesolz.net>
 */

if ( ! defined( 'CS_RTAFAR_VERSION' ) ) {
	exit;
}

class Capabilities {

	/**
	 * Module key => Util::bfar_nav_cap() key.
	 *
	 * @var array
	 */
	private static $modules = array(
		'dashboard'      => 'maintenance_dashboard',
		'content_health' => 'content_health',
		'redirects'      => 'redirects',
		'content_tools'  => 'content_tools',
		'code_inserts'   => 'code_inserts',
	);

	/**
	 * Fallbacks used only when the capability map cannot be reached.
	 *
	 * Util is part of the same plugin, so this should never fire - but a
	 * capability check that fatals is worse than one that is briefly strict.
	 *
	 * @var array
	 */
	private static $fallbacks = array(
		'dashboard'      => 'bfar_menu_maintenance_dashboard',
		'content_health' => 'bfar_menu_content_health',
		'redirects'      => 'bfar_menu_redirects',
		'content_tools'  => 'bfar_menu_content_tools',
		'code_inserts'   => 'bfar_menu_code_inserts',
	);

	/**
	 * The capability that guards a module's screens.
	 *
	 * @param string $module One of self::$modules.
	 * @return string Capability name.
	 */
	public static function for_module( $module ) {
		$module = (string) $module;

		if ( ! isset( self::$modules[ $module ] ) ) {
			return 'manage_options';
		}

		if ( class_exists( '\RealTimeAutoFindReplace\lib\Util' ) ) {
			$cap = Util::bfar_nav_cap( self::$modules[ $module ] );

			if ( is_string( $cap ) && '' !== $cap ) {
				return $cap;
			}
		}

		return self::$fallbacks[ $module ];
	}

	/**
	 * May the current user work with this module?
	 *
	 * Accepts manage_options alongside the module capability so that a site
	 * which has never touched role editing behaves the way its owner expects.
	 *
	 * @param string $module One of self::$modules.
	 * @return bool
	 */
	public static function current_user_can( $module ) {
		if ( ! function_exists( 'current_user_can' ) ) {
			return false;
		}

		if ( \current_user_can( 'manage_options' ) ) {
			return true;
		}

		return \current_user_can( self::for_module( $module ) );
	}

	/**
	 * Every module key this class knows.
	 *
	 * @return array
	 */
	public static function modules() {
		return array_keys( self::$modules );
	}

	/**
	 * Capability names for every module.
	 *
	 * Used by the tests to assert that each one is actually present in the
	 * shared map, which is the thing that gets it granted.
	 *
	 * @return array module key => capability name
	 */
	public static function all() {
		$out = array();

		foreach ( array_keys( self::$modules ) as $module ) {
			$out[ $module ] = self::for_module( $module );
		}

		return $out;
	}
}
