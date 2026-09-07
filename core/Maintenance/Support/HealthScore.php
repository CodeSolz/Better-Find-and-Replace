<?php namespace RealTimeAutoFindReplace\Maintenance\Support;

/**
 * The site health score.
 *
 * One number on a dashboard is the easiest thing in this product to get wrong.
 * A score nobody can account for is worse than no score: it invites the reader
 * to distrust every other figure on the page, and it cannot be argued with when
 * they think it is unfair.
 *
 * So the rules are:
 *
 *   - it starts at 100 and problems subtract from it, which is the direction
 *     people expect and the only one where "no issues" has an obvious answer;
 *   - every deduction is named, bounded, and reported by explain(), so the UI
 *     can show the arithmetic;
 *   - it is relative to the size of the site. Twelve broken links on a
 *     forty-post blog is a different situation from twelve on forty thousand
 *     posts, and a score that ignored that would be useless to both;
 *   - it is deterministic. The same inputs give the same score, always.
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

class HealthScore {

	/** A site with nothing wrong. */
	const MAX_SCORE = 100;

	/**
	 * The most each category can take off, whatever its count.
	 *
	 * No single problem type can sink the score on its own: a site with 500
	 * broken links and nothing else wrong is in trouble, but it is not in more
	 * trouble than one that is also serving 500 dead images.
	 *
	 * @var array
	 */
	private static $caps = array(
		'broken_link'   => 35,
		'not_found'     => 20,
		'missing_media' => 20,
		'stale_content' => 15,
		'other'         => 10,
	);

	/**
	 * How many issues of a type count as "one unit of damage".
	 *
	 * Scaled against the size of the site, so the same absolute count means
	 * less on a large one.
	 *
	 * @var array
	 */
	private static $weights = array(
		'broken_link'   => 1.0,
		'not_found'     => 0.6,
		'missing_media' => 0.8,
		'stale_content' => 0.4,
		'other'         => 0.4,
	);

	/**
	 * Score a site.
	 *
	 * @param array $counts  Issue type => open count.
	 * @param int   $content Number of published items scanned. Used to scale.
	 * @return int 0-100.
	 */
	public static function score( array $counts, $content = 0 ) {
		$explained = self::explain( $counts, $content );

		return $explained['score'];
	}

	/**
	 * The score with its arithmetic.
	 *
	 * @param array $counts  Issue type => open count.
	 * @param int   $content Number of published items scanned.
	 * @return array {
	 *     @type int    $score      Final score, 0-100.
	 *     @type array  $deductions Category => points taken off.
	 *     @type int    $total      Total deducted.
	 *     @type string $band       'good', 'fair' or 'poor'.
	 *     @type int    $issues     Total open issues considered.
	 * }
	 */
	public static function explain( array $counts, $content = 0 ) {
		$content    = max( 0, (int) $content );
		$deductions = array();
		$issues     = 0;

		// The denominator: how much content there is to be wrong about. Floored
		// so a brand-new site with three posts and one broken link does not
		// score zero, and so division is always safe.
		$scale = max( 20, $content );

		foreach ( $counts as $type => $count ) {
			$count = max( 0, (int) $count );

			if ( $count < 1 ) {
				continue;
			}

			$issues += $count;

			$key    = isset( self::$caps[ $type ] ) ? $type : 'other';
			$cap    = self::$caps[ $key ];
			$weight = self::$weights[ $key ];

			// Proportion of the site affected, weighted by how much this kind
			// of problem matters, then curved: the first few issues cost more
			// than the hundredth, because the first few are what a visitor
			// actually notices.
			$ratio  = ( $count * $weight ) / $scale;
			$damage = $cap * ( 1 - exp( -4 * $ratio ) );

			$taken = (int) round( min( $cap, $damage ) );

			if ( $taken < 1 ) {
				// Something is wrong, so it has to cost at least a point -
				// otherwise a site with issues can still show a perfect score.
				$taken = 1;
			}

			$deductions[ $key ] = isset( $deductions[ $key ] ) ? $deductions[ $key ] + $taken : $taken;

			if ( $deductions[ $key ] > $cap ) {
				$deductions[ $key ] = $cap;
			}
		}

		$total = array_sum( $deductions );
		$score = (int) max( 0, self::MAX_SCORE - $total );

		return array(
			'score'      => $score,
			'deductions' => $deductions,
			'total'      => (int) $total,
			'band'       => self::band( $score ),
			'issues'     => $issues,
		);
	}

	/**
	 * A word for a score, for colour and copy.
	 *
	 * @param int $score 0-100.
	 * @return string 'good', 'fair' or 'poor'.
	 */
	public static function band( $score ) {
		$score = (int) $score;

		if ( $score >= 85 ) {
			return 'good';
		}

		return $score >= 60 ? 'fair' : 'poor';
	}

	/**
	 * The deduction ceilings, for the UI legend.
	 *
	 * @return array
	 */
	public static function caps() {
		return self::$caps;
	}
}
