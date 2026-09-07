<?php namespace RealTimeAutoFindReplace\Maintenance\Support;

/**
 * How urgent is this issue?
 *
 * One shared, additive, explainable score across every module, so a broken
 * link, a dead image and a high-traffic 404 can sit in one sorted list without
 * the ordering being a mystery.
 *
 * Explainability is a requirement, not a nicety (master spec 26). Every
 * component is bounded, every component is named, and explain() returns the
 * breakdown that the UI shows. A number nobody can account for erodes trust in
 * every other number on the page.
 *
 * No WordPress functions, so the unit suite can exercise it directly.
 *
 * @package Maintenance
 * @since 1.10.0
 * @author M.Tuhin <info@codesolz.net>
 */

if ( ! defined( 'CS_RTAFAR_VERSION' ) ) {
	exit;
}

class Priority {

	/** Highest score any issue can reach. */
	const MAX_SCORE = 100;

	/**
	 * Component ceilings. They sum to more than MAX_SCORE on purpose: an issue
	 * has to be bad in several dimensions at once to saturate.
	 *
	 * @var array
	 */
	private static $caps = array(
		'severity'    => 40,
		'occurrences' => 15,
		'traffic'     => 20,
		'recency'     => 10,
		'confidence'  => 10,
		'placement'   => 15,
	);

	/**
	 * Score one issue.
	 *
	 * @param array $factors {
	 *     @type int   $severity     1-5, module-assigned.
	 *     @type int   $occurrences  How many times it appears in its object.
	 *     @type int   $hits         Traffic, for 404s.
	 *     @type int   $age_days     Days since it was last seen.
	 *     @type float $confidence   0-1, AI-sourced issues only.
	 *     @type bool  $is_internal  Internal targets are our responsibility.
	 *     @type bool  $on_front     Appears on the front page or a menu.
	 *     @type bool  $search_referrer A search engine sent traffic to it.
	 * }
	 * @return int 0-100.
	 */
	public static function score( array $factors ) {
		$parts = self::components( $factors );

		$total = 0;
		foreach ( $parts as $value ) {
			$total += $value;
		}

		if ( $total < 0 ) {
			$total = 0;
		}

		return (int) min( self::MAX_SCORE, $total );
	}

	/**
	 * The score, broken into named parts.
	 *
	 * @param array $factors See score().
	 * @return array {
	 *     @type int   $score      Final score, after the cap.
	 *     @type array $components Component name => points.
	 *     @type int   $raw        Sum before the cap, so a saturated issue is visible as such.
	 * }
	 */
	public static function explain( array $factors ) {
		$parts = self::components( $factors );
		$raw   = 0;

		foreach ( $parts as $value ) {
			$raw += $value;
		}

		return array(
			'score'      => (int) min( self::MAX_SCORE, max( 0, $raw ) ),
			'components' => $parts,
			'raw'        => (int) $raw,
		);
	}

	/**
	 * Component ceilings, for the UI legend.
	 *
	 * @return array
	 */
	public static function caps() {
		return self::$caps;
	}

	/**
	 * Work out each component.
	 *
	 * @param array $factors See score().
	 * @return array component name => points
	 */
	private static function components( array $factors ) {
		$severity    = self::clamp_int( isset( $factors['severity'] ) ? $factors['severity'] : 3, 1, 5 );
		$occurrences = max( 0, (int) ( isset( $factors['occurrences'] ) ? $factors['occurrences'] : 1 ) );
		$hits        = max( 0, (int) ( isset( $factors['hits'] ) ? $factors['hits'] : 0 ) );
		$age_days    = max( 0, (int) ( isset( $factors['age_days'] ) ? $factors['age_days'] : 0 ) );
		$confidence  = isset( $factors['confidence'] ) ? (float) $factors['confidence'] : 0.0;

		$parts = array();

		// Severity is the module's own judgement and carries the most weight.
		$parts['severity'] = (int) round( self::$caps['severity'] * ( $severity / 5 ) );

		// Occurrences matter, but with diminishing returns: the difference
		// between 1 and 5 is real, between 40 and 80 is noise.
		$parts['occurrences'] = (int) min(
			self::$caps['occurrences'],
			$occurrences <= 1 ? 0 : round( 5 * log( $occurrences, 2 ) )
		);

		// Traffic is the strongest evidence that a problem is costing something.
		$parts['traffic'] = (int) min(
			self::$caps['traffic'],
			$hits <= 0 ? 0 : round( 4 * log( $hits + 1, 2 ) )
		);

		// Something seen today outranks something last seen a month ago.
		if ( $age_days <= 1 ) {
			$parts['recency'] = self::$caps['recency'];
		} elseif ( $age_days <= 7 ) {
			$parts['recency'] = (int) round( self::$caps['recency'] * 0.7 );
		} elseif ( $age_days <= 30 ) {
			$parts['recency'] = (int) round( self::$caps['recency'] * 0.3 );
		} else {
			$parts['recency'] = 0;
		}

		// AI-sourced issues are ranked by how sure the model was. A missing or
		// zero confidence contributes nothing rather than penalising.
		$parts['confidence'] = (int) min(
			self::$caps['confidence'],
			max( 0, round( self::$caps['confidence'] * $confidence ) )
		);

		// Where the problem sits. An internal target is ours to fix; the front
		// page is seen by everyone; a search referrer means it is costing
		// traffic that already existed.
		$placement = 0;
		if ( ! empty( $factors['is_internal'] ) ) {
			$placement += 5;
		}
		if ( ! empty( $factors['on_front'] ) ) {
			$placement += 7;
		}
		if ( ! empty( $factors['search_referrer'] ) ) {
			$placement += 3;
		}
		$parts['placement'] = (int) min( self::$caps['placement'], $placement );

		return $parts;
	}

	/**
	 * Clamp to an integer range.
	 *
	 * @param mixed $value Raw value.
	 * @param int   $min   Lower bound.
	 * @param int   $max   Upper bound.
	 * @return int
	 */
	private static function clamp_int( $value, $min, $max ) {
		$value = (int) $value;

		if ( $value < $min ) {
			return $min;
		}

		return $value > $max ? $max : $value;
	}
}
