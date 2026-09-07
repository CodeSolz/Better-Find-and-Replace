<?php namespace RealTimeAutoFindReplace\Maintenance\ReplaceRedirect;

/**
 * The spellings of a URL that the replacement engine will match.
 *
 * A preview computed differently from the change it previews is not a preview,
 * so this exists to make sure both sides ask the same question. DbReplacer
 * builds its match set in a private method:
 *
 *     private function variants_for( $find, $replace, $context = array() ) {
 *         $variants = array( array( 'find' => $find, 'replace' => $replace ) );
 *         ...
 *         return apply_filters( 'bfrp_find_replace_variants', $variants, $find, $replace, $flags );
 *     }
 *
 * The method is private, but the filter is public and is the only thing that
 * ever adds to the set - in the free plugin nothing hooks it, so the set is the
 * literal string; with pro active, BFRP_Hooks adds the escaped, percent-encoded
 * and scheme/www forms. Asking the same filter with the same flags therefore
 * gives exactly the set the engine will use, on whichever tier is running.
 *
 * The flags are the ones DbReplacer::replace_links() leaves in place, and they
 * are not guesswork: it sets encoded_urls from its own argument and touches
 * neither url_formats nor url_mode, both of which default to false. If that
 * ever changes, ReplaceRedirectIntegrationTest catches the drift by asserting
 * preview and apply agree on a real replacement.
 *
 * @package Maintenance
 * @since 1.10.0
 * @author M.Tuhin <info@codesolz.net>
 */

if ( ! defined( 'CS_RTAFAR_VERSION' ) ) {
	exit;
}

class Variants {

	/**
	 * Every find/replace pair the engine will apply for this URL change.
	 *
	 * @param string $find    URL being replaced.
	 * @param string $replace URL replacing it.
	 * @param bool   $encoded Whether encoded variants are in play - matches the
	 *                        $encoded argument passed to replace_links().
	 * @return array List of array( 'find' => string, 'replace' => string ).
	 */
	public static function build( $find, $replace, $encoded = true ) {
		$variants = array(
			array(
				'find'    => (string) $find,
				'replace' => (string) $replace,
			),
		);

		if ( ! function_exists( 'apply_filters' ) ) {
			return $variants;
		}

		/**
		 * This is not our filter - it belongs to DbReplacer, and we are asking
		 * it the same question the engine will ask so the preview cannot
		 * disagree with the apply.
		 */
		$variants = apply_filters(
			'bfrp_find_replace_variants',
			$variants,
			(string) $find,
			(string) $replace,
			array(
				'encoded_urls'  => (bool) $encoded,
				'url_formats'   => false,
				'url_mode'      => false,
				'force_encoded' => false,
			)
		);

		return self::clean( $variants );
	}

	/**
	 * Just the strings to search for.
	 *
	 * @param array $variants Output of build().
	 * @return array
	 */
	public static function needles( array $variants ) {
		$needles = array();

		foreach ( $variants as $variant ) {
			if ( isset( $variant['find'] ) && '' !== $variant['find'] ) {
				$needles[] = (string) $variant['find'];
			}
		}

		return array_values( array_unique( $needles ) );
	}

	/**
	 * How many times any variant appears in a string.
	 *
	 * Counts the longest variants first and blanks what it has counted, so a
	 * URL that is a prefix of another spelling is not counted twice.
	 *
	 * @param string $haystack Content to search.
	 * @param array  $variants Output of build().
	 * @return int
	 */
	public static function count_in( $haystack, array $variants ) {
		$haystack = (string) $haystack;

		if ( '' === $haystack ) {
			return 0;
		}

		$needles = self::needles( $variants );

		usort(
			$needles,
			function ( $a, $b ) {
				return strlen( $b ) - strlen( $a );
			}
		);

		$total = 0;

		foreach ( $needles as $needle ) {
			$found = substr_count( $haystack, $needle );

			if ( $found < 1 ) {
				continue;
			}

			$total   += $found;
			$haystack = str_replace( $needle, '', $haystack );
		}

		return $total;
	}

	/**
	 * Drop malformed entries a filter may have returned.
	 *
	 * @param mixed $variants Filter output.
	 * @return array
	 */
	private static function clean( $variants ) {
		if ( ! is_array( $variants ) ) {
			return array();
		}

		$clean = array();

		foreach ( $variants as $variant ) {
			if ( ! is_array( $variant ) || ! isset( $variant['find'] ) || '' === $variant['find'] ) {
				continue;
			}

			$clean[] = array(
				'find'    => (string) $variant['find'],
				'replace' => isset( $variant['replace'] ) ? (string) $variant['replace'] : '',
			);
		}

		return $clean;
	}
}
