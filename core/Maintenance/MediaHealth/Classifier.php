<?php namespace RealTimeAutoFindReplace\Maintenance\MediaHealth;

use RealTimeAutoFindReplace\Maintenance\LinkHealth\InternalResolver;

/**
 * Which kind of problem is this?
 *
 * The scanner has always found missing images. It has never *said* so: every
 * occurrence it reports, whether an anchor or an `<img src>`, is filed as
 * `broken_link`. The consequences were quiet but real - the Dashboard has shown
 * a **Missing images** row since M4a and it was permanently zero, and
 * `HealthScore` has given `missing_media` its own cap and weight since the same
 * milestone and never used either. A site with forty dead pictures and no dead
 * links read as a link problem.
 *
 * So this class exists to answer one question in one place: given a group of
 * occurrences of one URL, is this a missing picture or a broken link? Putting
 * it here rather than inline in the scanner means pro and a site owner change
 * the answer the same way, through the same filter, and that a test can ask the
 * question without running a scan.
 *
 * @package Maintenance
 * @since 1.10.0
 * @author M.Tuhin <info@codesolz.net>
 */

if ( ! defined( 'CS_RTAFAR_VERSION' ) ) {
	exit;
}

class Classifier {

	/** A picture, a video, a file - something the page displays rather than points at. */
	const MEDIA = 'missing_media';

	/** Everything else. */
	const LINK = 'broken_link';

	/**
	 * Occurrence types the Extractor uses for media.
	 *
	 * `image` is an `<img>`; `media` is the rest of the media tag family -
	 * video, audio, source, iframe, embed, object, track.
	 *
	 * @return array
	 */
	public static function media_types() {
		$types = array( 'image', 'media' );

		/**
		 * Filter which occurrence types count as media.
		 *
		 * @param array $types Extractor occurrence types.
		 */
		return (array) apply_filters( 'bfr_media_occurrence_types', $types );
	}

	/**
	 * Every issue type a content scan can write.
	 *
	 * The scanner closes stale rows with `resolve_missing()`, which takes one
	 * type. **A scan that writes two types has to close two types**, or an
	 * image somebody fixed stays on the list until they ignore it. Nothing else
	 * should hard-code this list.
	 *
	 * @return array
	 */
	public static function issue_types() {
		return array( self::LINK, self::MEDIA );
	}

	/**
	 * Classify one grouped URL.
	 *
	 * A URL can appear as both a link and an image in the same post - a
	 * thumbnail wrapped in an anchor to the full size is the ordinary case.
	 * `Extractor::group()` keeps the type of whichever occurrence came first,
	 * which is arbitrary, so this reads the occurrence list instead: **if any
	 * occurrence displays it, a missing file is a missing picture.** That is
	 * the reading that matches what the page looks like when it breaks.
	 *
	 * @param array $group One entry from Extractor::group().
	 * @return string An issue type.
	 */
	public static function classify( array $group ) {
		$type = self::is_media( $group ) ? self::MEDIA : self::LINK;

		/**
		 * Filter the issue type a grouped URL is recorded under.
		 *
		 * @param string $type  Issue type.
		 * @param array  $group The grouped occurrences.
		 */
		$type = (string) apply_filters( 'bfr_issue_type_for_group', $type, $group );

		return in_array( $type, self::issue_types(), true ) ? $type : self::LINK;
	}

	/**
	 * Is any occurrence of this URL a media occurrence?
	 *
	 * @param array $group One entry from Extractor::group().
	 * @return bool
	 */
	public static function is_media( array $group ) {
		$media = self::media_types();

		if ( ! empty( $group['occurrences'] ) && is_array( $group['occurrences'] ) ) {
			foreach ( $group['occurrences'] as $one ) {
				if ( isset( $one['type'] ) && in_array( (string) $one['type'], $media, true ) ) {
					return true;
				}
			}

			return false;
		}

		// A group built by hand, or one from an older cache, still has a type.
		return isset( $group['type'] ) && in_array( (string) $group['type'], $media, true );
	}

	/**
	 * How bad is it, on the shared 1-5 scale?
	 *
	 * Media is scored separately from links because the evidence is different
	 * in kind. A missing upload is answered by the filesystem: the file is not
	 * there, and no amount of routing will make it appear. That is stronger
	 * evidence than a link to a page that might be behind a plugin's custom
	 * rewrite, so it scores higher.
	 *
	 * The case that is deliberately *not* here is `upload_offloaded_or_moved` -
	 * an attachment record whose file is not on this disk. `InternalResolver`
	 * answers that with UNKNOWN, which is not in `problem_statuses()`, so it
	 * never becomes an issue at all. That is the right default: an offload
	 * plugin is the normal cause, the picture is fine, and reporting it would
	 * fill the list with false alarms on exactly the sites least able to check.
	 *
	 * @param string $issue_type One of issue_types().
	 * @param string $status     InternalResolver status.
	 * @return int 1-5.
	 */
	public static function severity( $issue_type, $status ) {
		if ( self::MEDIA !== $issue_type ) {
			return 0; // The caller keeps its own scale for links.
		}

		switch ( $status ) {
			case InternalResolver::MALFORMED:
				return 5;
			case InternalResolver::MISSING:
				return 4;
			case InternalResolver::TRASHED:
				return 3;
			default:
				return 2;
		}
	}

	/**
	 * Does this replacement look like media?
	 *
	 * Free's `Fixer` already refuses a replacement that is not a usable URL.
	 * What it cannot know is that the thing being replaced was a picture, so a
	 * typo - a pasted page URL into the box under a broken image - would be
	 * accepted and would quietly turn a photo into nothing. This is the check
	 * that stops that, and it is deliberately about the *shape* of the URL
	 * rather than about fetching it: fetching is pro's job and happens in a
	 * queue job.
	 *
	 * @param string $url The proposed replacement.
	 * @return bool
	 */
	public static function looks_like_media( $url ) {
		$url  = trim( (string) $url );
		$path = (string) wp_parse_url( $url, PHP_URL_PATH );

		if ( '' === $path ) {
			return false;
		}

		$extension = strtolower( (string) pathinfo( $path, PATHINFO_EXTENSION ) );

		if ( '' === $extension ) {
			return false;
		}

		/**
		 * Filter the extensions accepted as media.
		 *
		 * Deliberately a list rather than "anything with a dot in it": the
		 * mistake being guarded against is a page URL, and plenty of page URLs
		 * end in something that looks like an extension.
		 *
		 * @param array $extensions Lower-case, no leading dot.
		 */
		$allowed = (array) apply_filters(
			'bfr_media_extensions',
			array(
				// Images.
				'jpg',
				'jpeg',
				'jpe',
				'png',
				'gif',
				'webp',
				'avif',
				'svg',
				'bmp',
				'ico',
				'tif',
				'tiff',
				'heic',
				// Video.
				'mp4',
				'm4v',
				'mov',
				'webm',
				'ogv',
				'avi',
				'mpg',
				'mpeg',
				'wmv',
				'flv',
				// Audio.
				'mp3',
				'm4a',
				'ogg',
				'oga',
				'wav',
				'flac',
				'aac',
				'wma',
				// Documents, which are media as far as the library is concerned.
				'pdf',
			)
		);

		return in_array( $extension, array_map( 'strtolower', $allowed ), true );
	}
}
