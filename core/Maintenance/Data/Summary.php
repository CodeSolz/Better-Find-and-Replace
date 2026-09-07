<?php namespace RealTimeAutoFindReplace\Maintenance\Data;

use RealTimeAutoFindReplace\Maintenance\LinkHealth\Scanner;
use RealTimeAutoFindReplace\Maintenance\Support\HealthScore;

/**
 * The dashboard's numbers, computed once and cached.
 *
 * A dashboard is the screen most likely to be opened on a site large enough for
 * counting to hurt. Every figure it shows is a COUNT over a table that grows,
 * so computing them on page load is the difference between a screen that works
 * at 50,000 issues and one that times out.
 *
 * The cache is invalidated by events rather than by time alone: an issue
 * created, resolved or ignored, a scan completing, a redirect changing. A
 * short TTL then covers anything that changes without firing one of those -
 * a row edited directly in the database, say - so the numbers cannot be wrong
 * indefinitely.
 *
 * @package Maintenance
 * @since 1.10.0
 * @author M.Tuhin <info@codesolz.net>
 */

if ( ! defined( 'CS_RTAFAR_VERSION' ) ) {
	exit;
}

class Summary {

	/** Where the computed summary lives. */
	const TRANSIENT = 'bfr_maintenance_summary';

	/** Backstop for changes that fire no event. */
	const TTL = 900;

	/**
	 * Attach the invalidation hooks.
	 *
	 * @return void
	 */
	public static function register() {
		foreach ( array( 'bfr_maintenance_issue_created', 'bfr_maintenance_issue_resolved', 'bfr_maintenance_issue_ignored', 'bfr_maintenance_scan_completed' ) as $hook ) {
			add_action( $hook, array( __CLASS__, 'flush' ) );
		}
	}

	/**
	 * The dashboard figures.
	 *
	 * @param bool $fresh Skip the cache.
	 * @return array {
	 *     @type array $issues    Issue type => open count.
	 *     @type int   $not_found Unhandled 404s.
	 *     @type int   $redirects Active redirects.
	 *     @type int   $content   Published items in scannable types.
	 *     @type array $health    HealthScore::explain() output.
	 *     @type int   $generated Unix time the figures were computed.
	 * }
	 */
	public static function get( $fresh = false ) {
		if ( ! $fresh ) {
			$cached = get_transient( self::TRANSIENT );

			if ( is_array( $cached ) && isset( $cached['health'] ) ) {
				return $cached;
			}
		}

		$issues    = IssueRepository::counts_by_type();
		$not_found = NotFoundRepository::count( NotFoundRepository::STATUS_NEW );
		$content   = self::content_count();

		// 404s are a health signal too, but they are not rows in the issue
		// table - so they join the score here rather than being counted twice.
		$for_score = $issues;

		if ( $not_found > 0 ) {
			$for_score['not_found'] = $not_found;
		}

		$summary = array(
			'issues'    => $issues,
			'not_found' => $not_found,
			'redirects' => (int) get_option( RedirectRepository::GUARD_OPTION, 0 ),
			'content'   => $content,
			'health'    => HealthScore::explain( $for_score, $content ),
			'generated' => time(),
		);

		set_transient( self::TRANSIENT, $summary, self::TTL );

		return $summary;
	}

	/**
	 * Throw the cached figures away.
	 *
	 * @return void
	 */
	public static function flush() {
		delete_transient( self::TRANSIENT );
	}

	/**
	 * How much published content there is to be wrong about.
	 *
	 * Cached with the summary rather than counted separately: it is the
	 * denominator of the health score and changes slowly.
	 *
	 * @return int
	 */
	private static function content_count() {
		$total = 0;

		foreach ( Scanner::target_types() as $type ) {
			$counts = wp_count_posts( $type );

			if ( isset( $counts->publish ) ) {
				$total += (int) $counts->publish;
			}
		}

		return $total;
	}
}
