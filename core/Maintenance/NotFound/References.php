<?php namespace RealTimeAutoFindReplace\Maintenance\NotFound;

use RealTimeAutoFindReplace\Maintenance\LinkHealth\Extractor;
use RealTimeAutoFindReplace\Maintenance\LinkHealth\InternalResolver;
use RealTimeAutoFindReplace\Maintenance\LinkHealth\Scanner;
use RealTimeAutoFindReplace\Maintenance\Support\UrlNormalizer;

/**
 * Finds the content still linking to a dead URL.
 *
 * This is the join between the 404 monitor and everything else: knowing that
 * /old-page/ is being requested is useful, but knowing that four of your own
 * posts still link to it is what turns a log entry into a fix.
 *
 * It does not search the database for the string. A URL appears in content in
 * several spellings at once - absolute and relative, with and without www, with
 * JSON-escaped slashes inside a block comment - and a LIKE for one of them
 * misses the rest. The candidate rows are narrowed with a LIKE on the path,
 * then each one is parsed with the link extractor and compared on the
 * normalised hash, which is the same comparison the link scanner uses.
 *
 * @package Maintenance
 * @since 1.10.0
 * @author M.Tuhin <info@codesolz.net>
 */

if ( ! defined( 'CS_RTAFAR_VERSION' ) ) {
	exit;
}

// Table names are WordPress's own ($wpdb->posts); values are placeholders.
// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery
// phpcs:disable WordPress.DB.DirectDatabaseQuery.NoCaching

class References {

	/** Candidate rows examined, so one query cannot read the whole site. */
	const MAX_CANDIDATES = 200;

	/** References reported back to the screen. */
	const MAX_RESULTS = 25;

	/**
	 * Which published content still links to this path?
	 *
	 * @param string $path Dead path, e.g. /old-page/.
	 * @return array List of array( post_id, title, edit_url, occurrences ).
	 */
	public static function find( $path ) {
		global $wpdb;

		$path = trim( (string) $path );

		if ( '' === $path ) {
			return array();
		}

		$site_host = InternalResolver::site_host();
		$target    = UrlNormalizer::hash( $path, $site_host );

		if ( '' === $target ) {
			return array();
		}

		$types = Scanner::target_types();

		if ( empty( $types ) ) {
			return array();
		}

		$type_slots = implode( ',', array_fill( 0, count( $types ), '%s' ) );

		// Narrow with a LIKE on the bare path, which every spelling contains,
		// then confirm properly below. The trailing slash is stripped so
		// "/old-page" matches "/old-page/" too.
		$needle = '%' . $wpdb->esc_like( rtrim( $path, '/' ) ) . '%';

		$sql = "SELECT ID, post_title, post_content
			FROM {$wpdb->posts}
			WHERE post_type IN ({$type_slots})
			  AND post_status = 'publish'
			  AND post_content LIKE %s
			LIMIT %d";

		$rows = $wpdb->get_results(
			// $sql is assembled from literals plus placeholders directly above;
			// the sniff cannot follow the variable to see that.
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			$wpdb->prepare( $sql, array_merge( $types, array( $needle, self::MAX_CANDIDATES ) ) )
		);

		$found = array();

		foreach ( (array) $rows as $row ) {
			$count = 0;

			foreach ( Extractor::extract( $row->post_content ) as $one ) {
				if ( UrlNormalizer::hash( $one['url'], $site_host ) === $target ) {
					++$count;
				}
			}

			if ( $count < 1 ) {
				// The LIKE matched something that is not actually this link -
				// a longer path with the same prefix, or the URL in prose.
				continue;
			}

			$found[] = array(
				'post_id'     => (int) $row->ID,
				'title'       => (string) $row->post_title,
				'edit_url'    => (string) get_edit_post_link( (int) $row->ID, 'raw' ),
				'occurrences' => $count,
			);

			if ( count( $found ) >= self::MAX_RESULTS ) {
				break;
			}
		}

		return $found;
	}
}
