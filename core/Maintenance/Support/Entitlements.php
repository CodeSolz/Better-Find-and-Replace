<?php namespace RealTimeAutoFindReplace\Maintenance\Support;

use RealTimeAutoFindReplace\admin\functions\ProActions;

/**
 * The single place that answers "is this capability part of Pro?".
 *
 * Master spec 22: do not scatter `if ( hasPro() )` through the modules. Every
 * module asks the same way:
 *
 *     if ( Entitlements::can( 'link_health.external' ) ) { ... }
 *
 * The answer is not a new licensing model. It is the gate every other Pro
 * feature in this product already uses - ProActions::hasPro() - behind a key,
 * so that the free plugin can describe a Pro capability it does not implement
 * and the pro plugin can answer for it without either side hard-coding the
 * other's internals.
 *
 * An unknown key is false. Never true: a typo must not silently unlock a paid
 * feature, and it must not silently lock a free one either - it fails closed
 * and complains loudly under WP_DEBUG.
 *
 * @package Maintenance
 * @since 1.10.0
 * @author M.Tuhin <info@codesolz.net>
 */

if ( ! defined( 'CS_RTAFAR_VERSION' ) ) {
	exit;
}

class Entitlements {

	/**
	 * Capabilities the free plugin implements itself.
	 *
	 * Listed rather than assumed, so a screen can ask about a free capability
	 * with the same call it uses for a paid one.
	 *
	 * @var array
	 */
	private static $free = array(
		'dashboard.basic',
		'link_health.internal',
		'redirects.basic',
		'duplicate.single',
	);

	/**
	 * Capabilities the pro plugin implements.
	 *
	 * A key appears here only when something reads it. Speculative keys make
	 * the list a wish list rather than a contract.
	 *
	 * @var array
	 */
	private static $pro = array(
		// Moved here from the free list. Each of these has one visible tab, and
		// the tab is still shown in free - locked, with a real figure from the
		// site on it. See Admin\LockedTab for why showing beats hiding.
		'activity.basic',
		'not_found.basic',
		'media_health.internal',
		'replace_redirect.single',
		'code_insert.global',

		'dashboard.history',
		'dashboard.scheduled_scans',

		'link_health.external',
		'link_health.media_checks',
		'link_health.scheduled',
		'link_health.bulk_fix',
		'link_health.ai_suggestions',
		'link_health.export',

		'redirects.advanced_types',
		'redirects.regex',
		'redirects.prefix',
		'redirects.auto_slug',
		'redirects.bulk',
		'redirects.analytics',
		'redirects.ai_suggestions',
		'redirects.import_export',
		'redirects.expiring',

		'not_found.analytics',
		'not_found.grouping',
		'not_found.ai_destination',
		'not_found.bulk_redirect',
		'not_found.reference_detection',

		'replace_redirect.bulk',
		'replace_redirect.scheduled_recheck',

		'content_refresher.full_scan',
		'content_refresher.scheduled',
		'content_refresher.bulk',
		'content_refresher.safe_revision',
		'content_refresher.ai_rewrite',

		'safe_revision',

		'duplicate.bulk',
		'duplicate.woocommerce',
		'duplicate.advanced_rules',

		'code_insert.conditions',

		'media_health.external',
		'media_health.bulk',
		'media_health.ai_match',

		'maintenance_agent',
		'reports.advanced',
		'notifications',
	);

	/**
	 * Memoised pro-plugin check.
	 *
	 * Calling hasPro() loads wp-admin/includes/plugin.php on a cold call, and a
	 * screen asks about a dozen keys.
	 *
	 * @var bool|null
	 */
	private static $has_pro = null;

	/**
	 * Is this capability available on this site?
	 *
	 * @param string $key Entitlement key, e.g. 'redirects.regex'.
	 * @return bool
	 */
	public static function can( $key ) {
		$key     = strtolower( trim( (string) $key ) );
		$known   = self::is_free( $key ) || self::is_pro( $key );
		$default = false;

		if ( self::is_free( $key ) ) {
			$default = true;
		} elseif ( self::is_pro( $key ) ) {
			$default = self::has_pro();
		} elseif ( function_exists( '_doing_it_wrong' ) && defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			_doing_it_wrong(
				__METHOD__,
				'Unknown BFR maintenance entitlement key: ' . esc_html( $key ),
				'1.10.0'
			);
		}

		if ( ! function_exists( 'apply_filters' ) ) {
			return $default;
		}

		/**
		 * Filter one entitlement answer.
		 *
		 * Pro attaches here rather than the free plugin importing anything
		 * from Pro. Third parties can use it to unlock a capability they have
		 * implemented themselves.
		 *
		 * @param bool   $default Default answer.
		 * @param string $key     Entitlement key.
		 * @param bool   $known   Whether the key is registered at all.
		 */
		return (bool) apply_filters( 'bfr_maintenance_can', $default, $key, $known );
	}

	/**
	 * Every registered key.
	 *
	 * @return array
	 */
	public static function keys() {
		return array_merge( self::$free, self::$pro );
	}

	/**
	 * Is this a capability the free plugin implements?
	 *
	 * @param string $key Entitlement key.
	 * @return bool
	 */
	public static function is_free( $key ) {
		return in_array( strtolower( trim( (string) $key ) ), self::$free, true );
	}

	/**
	 * Is this a capability the pro plugin implements?
	 *
	 * @param string $key Entitlement key.
	 * @return bool
	 */
	public static function is_pro( $key ) {
		return in_array( strtolower( trim( (string) $key ) ), self::$pro, true );
	}

	/**
	 * Is the pro plugin active?
	 *
	 * Kept private on purpose: modules ask about a capability, never about a
	 * plugin. That is what stops `if ( hasPro() )` spreading again.
	 *
	 * @return bool
	 */
	private static function has_pro() {
		if ( null !== self::$has_pro ) {
			return self::$has_pro;
		}

		self::$has_pro = class_exists( '\RealTimeAutoFindReplace\admin\functions\ProActions' )
			? (bool) ProActions::hasPro()
			: false;

		return self::$has_pro;
	}

	/**
	 * Forget the memoised pro check.
	 *
	 * Only tests and the plugin-activation path need this.
	 *
	 * @return void
	 */
	public static function flush() {
		self::$has_pro = null;
	}
}
