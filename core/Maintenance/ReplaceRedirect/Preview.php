<?php namespace RealTimeAutoFindReplace\Maintenance\ReplaceRedirect;

/**
 * What a Replace + Redirect would change, without changing anything.
 *
 * Read-only, and scoped to exactly the places DbReplacer::replace_links()
 * writes to - posts (title, content, excerpt), postmeta and options. Scanning
 * more would promise changes that will not happen; scanning less would hide
 * changes that will.
 *
 * It is a count, not a dry run of the engine. The engine's own dry-run mode
 * lives behind db_string_replace(), which ends in wp_send_json() and therefore
 * terminates the request - unusable from a workflow. So this asks the same
 * question a different way: the same variant set (see Variants), the same
 * tables, the same columns. The integration suite asserts the two agree by
 * previewing, applying, and comparing the counts.
 *
 * Bounded on purpose. A URL that appears in ten thousand rows does not need ten
 * thousand rows rendered to make the decision; the count is exact, the listed
 * sample is capped.
 *
 * @package Maintenance
 * @since 1.10.0
 * @author M.Tuhin <info@codesolz.net>
 */

if ( ! defined( 'CS_RTAFAR_VERSION' ) ) {
	exit;
}

// Table names here are WordPress's own ($wpdb->posts and friends); every value
// is a placeholder. A preview is a direct, uncached read by definition.
// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery
// phpcs:disable WordPress.DB.DirectDatabaseQuery.NoCaching

class Preview {

	/** Rows listed back per table. The counts are exact regardless. */
	const SAMPLE_LIMIT = 25;

	/** Rows examined per table, so one preview cannot read the whole database. */
	const SCAN_LIMIT = 2000;

	/**
	 * Find everywhere this URL appears.
	 *
	 * Three figures, because they answer different questions and confusing them
	 * is how a preview ends up lying:
	 *
	 *   occurrences - how many times the URL appears. What a person wants to
	 *                 hear: "this URL is used 12 times".
	 *   rows        - how many database rows contain it. Roughly "on 5 pages".
	 *   changes     - how many individual columns would be written. This is the
	 *                 engine's own unit: DbReplacer counts post_title,
	 *                 post_content and post_excerpt separately, so a post with
	 *                 the URL in its content and its excerpt is one row but two
	 *                 changes. This is the number that must match what apply()
	 *                 reports, and the integration suite asserts that it does.
	 *
	 * @param string $find    URL being replaced.
	 * @param string $replace URL replacing it.
	 * @return array {
	 *     @type int   $occurrences Total appearances across everything scanned.
	 *     @type int   $rows        Database rows containing it.
	 *     @type int   $changes     Column cells that would be written.
	 *     @type array $locations   Capped sample: table, label, edit_url, occurrences.
	 *     @type bool  $truncated   Whether the scan hit its limit.
	 *     @type array $variants    The spellings searched for.
	 * }
	 */
	public static function build( $find, $replace ) {
		$find     = trim( (string) $find );
		$variants = Variants::build( $find, (string) $replace );

		$result = array(
			'occurrences' => 0,
			'rows'        => 0,
			'changes'     => 0,
			'locations'   => array(),
			'truncated'   => false,
			'variants'    => Variants::needles( $variants ),
		);

		if ( '' === $find || empty( $variants ) ) {
			return $result;
		}

		foreach ( array( 'posts', 'postmeta', 'options' ) as $source ) {
			$found = call_user_func( array( __CLASS__, 'scan_' . $source ), $variants );

			$result['occurrences'] += $found['occurrences'];
			$result['rows']        += $found['rows'];
			$result['changes']     += $found['changes'];
			$result['truncated']    = $result['truncated'] || $found['truncated'];
			$result['locations']    = array_merge( $result['locations'], $found['locations'] );
		}

		// Most-affected first: that is the row somebody wants to eyeball.
		usort(
			$result['locations'],
			function ( $a, $b ) {
				return $b['occurrences'] - $a['occurrences'];
			}
		);

		$result['locations'] = array_slice( $result['locations'], 0, self::SAMPLE_LIMIT );

		return $result;
	}

	/**
	 * Posts: title, content and excerpt, the three columns tbl_post writes.
	 *
	 * @param array $variants Variant set.
	 * @return array
	 */
	private static function scan_posts( array $variants ) {
		global $wpdb;

		$placeholders = array();
		$clause       = self::like_clause( array( 'post_title', 'post_content', 'post_excerpt' ), $variants, $placeholders );

		$placeholders[] = self::SCAN_LIMIT;

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT ID, post_title, post_content, post_excerpt, post_type, post_status
				 FROM {$wpdb->posts}
				 WHERE {$clause}
				 LIMIT %d",
				$placeholders
			)
		);

		$out = self::empty_result( count( (array) $rows ) );

		foreach ( (array) $rows as $row ) {
			// Counted per column, because DbReplacer writes and counts each of
			// these separately - one row here can be two or three changes.
			$count = 0;

			foreach ( array( 'post_title', 'post_content', 'post_excerpt' ) as $column ) {
				$in_column = Variants::count_in( $row->{$column}, $variants );

				if ( $in_column > 0 ) {
					$count += $in_column;
					++$out['changes'];
				}
			}

			if ( $count < 1 ) {
				continue;
			}

			$out['occurrences'] += $count;
			++$out['rows'];

			$title = '' !== $row->post_title ? $row->post_title : __( '(no title)', 'real-time-auto-find-and-replace' );

			$out['locations'][] = array(
				'table'       => 'posts',
				'label'       => $title,
				'context'     => (string) $row->post_type,
				'edit_url'    => (string) get_edit_post_link( (int) $row->ID, 'raw' ),
				'occurrences' => $count,
			);
		}

		return $out;
	}

	/**
	 * Post meta: where page builders keep their content.
	 *
	 * @param array $variants Variant set.
	 * @return array
	 */
	private static function scan_postmeta( array $variants ) {
		global $wpdb;

		$placeholders = array();
		$clause       = self::like_clause( array( 'meta_value' ), $variants, $placeholders );

		$placeholders[] = self::SCAN_LIMIT;

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT meta_id, post_id, meta_key, meta_value
				 FROM {$wpdb->postmeta}
				 WHERE {$clause}
				 LIMIT %d",
				$placeholders
			)
		);

		$out = self::empty_result( count( (array) $rows ) );

		foreach ( (array) $rows as $row ) {
			$count = Variants::count_in( $row->meta_value, $variants );

			if ( $count < 1 ) {
				continue;
			}

			$out['occurrences'] += $count;
			++$out['rows'];
			++$out['changes'];

			$out['locations'][] = array(
				'table'       => 'postmeta',
				'label'       => (string) $row->meta_key,
				'context'     => get_the_title( (int) $row->post_id ),
				'edit_url'    => (string) get_edit_post_link( (int) $row->post_id, 'raw' ),
				'occurrences' => $count,
			);
		}

		return $out;
	}

	/**
	 * Options: site URL settings, widgets, theme mods, plugin configuration.
	 *
	 * @param array $variants Variant set.
	 * @return array
	 */
	private static function scan_options( array $variants ) {
		global $wpdb;

		$placeholders = array();
		$clause       = self::like_clause( array( 'option_value' ), $variants, $placeholders );

		$placeholders[] = self::SCAN_LIMIT;

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT option_id, option_name, option_value
				 FROM {$wpdb->options}
				 WHERE {$clause}
				 LIMIT %d",
				$placeholders
			)
		);

		$out = self::empty_result( count( (array) $rows ) );

		foreach ( (array) $rows as $row ) {
			$count = Variants::count_in( $row->option_value, $variants );

			if ( $count < 1 ) {
				continue;
			}

			$out['occurrences'] += $count;
			++$out['rows'];
			++$out['changes'];

			$out['locations'][] = array(
				'table'       => 'options',
				'label'       => (string) $row->option_name,
				'context'     => __( 'Site option', 'real-time-auto-find-and-replace' ),
				'edit_url'    => '',
				'occurrences' => $count,
			);
		}

		return $out;
	}

	/**
	 * A "( col LIKE %s OR ... )" clause covering every column and variant.
	 *
	 * Mirrors DbReplacer::like_clause_for_variants(), which is private, so that
	 * the preview selects the same candidate rows the engine will.
	 *
	 * @param array $columns      Columns to search.
	 * @param array $variants     Variant set.
	 * @param array $placeholders Filled with the bound values, by reference.
	 * @return string
	 */
	private static function like_clause( array $columns, array $variants, array &$placeholders ) {
		global $wpdb;

		$parts = array();

		foreach ( $columns as $column ) {
			foreach ( Variants::needles( $variants ) as $needle ) {
				$parts[]        = "{$column} LIKE %s";
				$placeholders[] = '%' . $wpdb->esc_like( $needle ) . '%';
			}
		}

		return empty( $parts ) ? '0=1' : '( ' . implode( ' OR ', $parts ) . ' )';
	}

	/**
	 * A blank per-table result.
	 *
	 * @param int $examined Rows the query returned.
	 * @return array
	 */
	private static function empty_result( $examined ) {
		return array(
			'occurrences' => 0,
			'rows'        => 0,
			'changes'     => 0,
			'locations'   => array(),
			'truncated'   => $examined >= self::SCAN_LIMIT,
		);
	}
}
