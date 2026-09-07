<?php namespace RealTimeAutoFindReplace\Maintenance\Admin;

use RealTimeAutoFindReplace\Maintenance\Support\Entitlements;

/**
 * A tab that is visible in free and does its work in pro.
 *
 * The house rule used to be the opposite: *a tab whose module has not shipped
 * is simply absent - never a disabled tab*. That was the right rule while the
 * modules were still being built, because a tab pointing at nothing is worse
 * than no tab. It is the wrong rule now that they all exist, for a reason that
 * only shows up on a real site: somebody who cannot see that the 404 monitor
 * exists cannot decide they want it, and the plugin looks smaller than it is.
 *
 * So every tab is always shown, and one of two things happens when it is
 * opened. Either the module renders it, or this class does - with what the
 * feature actually does, **the real number from this site** where we have one,
 * and a link. The real number is the point: "12 unhandled 404s were recorded on
 * this site last month" is an argument, and "Upgrade for 404 monitoring" is an
 * advertisement.
 *
 * Nothing here is a dark pattern. The tab opens, the panel says plainly that
 * the feature is part of the paid version, and no control on it pretends to
 * work.
 *
 * @package Maintenance
 * @since 1.10.0
 * @author M.Tuhin <info@codesolz.net>
 */

if ( ! defined( 'CS_RTAFAR_VERSION' ) ) {
	exit;
}

class LockedTab {

	/**
	 * Where the upgrade link goes.
	 */
	const UPGRADE_URL = 'https://codesolz.net/our-products/wordpress-plugin/real-time-auto-find-and-replace/?utm_source=plugin-locked-tab&utm_medium=wp-admin&utm_campaign=locked-tab';

	/**
	 * Is this tab's feature available on this install?
	 *
	 * One question asked one way, so a screen never decides for itself what
	 * "locked" means.
	 *
	 * @param string $entitlement The capability the tab needs.
	 * @return bool
	 */
	public static function unlocked( $entitlement ) {
		return Entitlements::can( (string) $entitlement );
	}

	/**
	 * The marker that sits after a locked tab's label.
	 *
	 * Shown in the strip rather than only inside the tab, so somebody scanning
	 * the row can tell at a glance which three of the four are theirs. A tab
	 * that only reveals it is locked after you click it wastes the click and
	 * reads as a bait.
	 *
	 * @return string
	 */
	public static function badge() {
		return ' <span class="bfrmaint-tab-pro">'
			. esc_html__( 'Pro', 'real-time-auto-find-and-replace' )
			. '</span>';
	}

	/**
	 * The label for one tab, with the marker when its feature is unavailable.
	 *
	 * The label arrives already escaped-safe as plain text and is escaped here,
	 * so a caller cannot forget; the badge is our own markup.
	 *
	 * @param string $label       The tab label.
	 * @param string $entitlement The capability it needs, or empty for a free tab.
	 * @return string Markup, ready to place inside the anchor.
	 */
	public static function label( $label, $entitlement = '' ) {
		$label = esc_html( (string) $label );

		if ( '' === (string) $entitlement || self::unlocked( $entitlement ) ) {
			return $label;
		}

		return $label . self::badge();
	}

	/**
	 * The panel shown in place of a locked tab's contents.
	 *
	 * @param array $args {
	 *     @type string $title   What the feature is called.
	 *     @type string $body    What it does, in a sentence or two.
	 *     @type array  $points  Bullet points, optional.
	 *     @type string $measure A real figure from this site, optional.
	 * }
	 * @return string
	 */
	public static function panel( array $args ) {
		$title   = isset( $args['title'] ) ? (string) $args['title'] : '';
		$body    = isset( $args['body'] ) ? (string) $args['body'] : '';
		$points  = isset( $args['points'] ) && is_array( $args['points'] ) ? $args['points'] : array();
		$measure = isset( $args['measure'] ) ? (string) $args['measure'] : '';

		$out = '<div class="bfrmaint-locked">';

		$out .= '<p class="bfrmaint-locked-badge">'
			. esc_html__( 'Part of Better Find and Replace Pro', 'real-time-auto-find-and-replace' )
			. '</p>';

		if ( '' !== $title ) {
			$out .= '<h3>' . esc_html( $title ) . '</h3>';
		}

		// The site's own number, when the free tier already knows it. This is
		// the whole difference between an argument and an advertisement.
		if ( '' !== $measure ) {
			$out .= '<p class="bfrmaint-locked-measure"><strong>' . esc_html( $measure ) . '</strong></p>';
		}

		if ( '' !== $body ) {
			$out .= '<p>' . esc_html( $body ) . '</p>';
		}

		if ( ! empty( $points ) ) {
			$out .= '<ul class="bfrmaint-locked-points">';

			foreach ( $points as $point ) {
				$out .= '<li>' . esc_html( $point ) . '</li>';
			}

			$out .= '</ul>';
		}

		$out .= sprintf(
			'<p><a class="btn btn-custom-submit" href="%s" target="_blank" rel="noopener noreferrer">%s</a></p>',
			esc_url( self::url() ),
			esc_html__( 'See what Pro adds', 'real-time-auto-find-and-replace' )
		);

		return $out . '</div>';
	}

	/**
	 * The upgrade URL, filterable so a bundle or a reseller can point it
	 * somewhere else rather than editing the plugin.
	 *
	 * @return string
	 */
	public static function url() {
		return (string) apply_filters( 'bfr_maintenance_upgrade_url', self::UPGRADE_URL );
	}
}
