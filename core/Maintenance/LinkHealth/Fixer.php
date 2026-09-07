<?php namespace RealTimeAutoFindReplace\Maintenance\LinkHealth;

use RealTimeAutoFindReplace\Maintenance\Data\ActivityLog;
use RealTimeAutoFindReplace\Maintenance\Data\IssueRepository;
use RealTimeAutoFindReplace\Maintenance\MediaHealth\Classifier;
use RealTimeAutoFindReplace\Maintenance\Support\UrlNormalizer;

/**
 * Applies the two fixes the free tier offers: replace a URL, or unlink it.
 *
 * This is the first code in the platform that writes to somebody's content, so
 * every rule in 01-SPEC.md §6 lands here:
 *
 *   - the live post is re-read and re-parsed at apply time, never trusted from
 *     the issue row, because the author may have rewritten the paragraph since
 *     the scan;
 *   - only occurrences whose normalised URL matches the issue are touched, so a
 *     second link in the same post survives untouched;
 *   - edits are applied back-to-front, so each offset is still valid when it is
 *     used;
 *   - the write goes through wp_update_post(), which fires the normal hooks and
 *     creates a native revision;
 *   - bfar_save_item_history fires with the exact shape the existing Restore in
 *     Database screen already reads, so this is reversible with no new code.
 *
 * Preview and apply share one code path. A preview that is computed differently
 * from the change it previews is not a preview.
 *
 * @package Maintenance
 * @since 1.10.0
 * @author M.Tuhin <info@codesolz.net>
 */

if ( ! defined( 'CS_RTAFAR_VERSION' ) ) {
	exit;
}

class Fixer {

	/**
	 * Work out what replacing this issue's URL would change.
	 *
	 * @param int    $issue_id    Issue id.
	 * @param string $replacement New URL.
	 * @return array See plan().
	 */
	public static function preview_replace( $issue_id, $replacement ) {
		return self::plan( $issue_id, 'replace', $replacement );
	}

	/**
	 * Work out what unlinking would change.
	 *
	 * @param int $issue_id Issue id.
	 * @return array See plan().
	 */
	public static function preview_unlink( $issue_id ) {
		return self::plan( $issue_id, 'unlink', '' );
	}

	/**
	 * Replace every occurrence of the issue's URL in its post.
	 *
	 * @param int    $issue_id    Issue id.
	 * @param string $replacement New URL.
	 * @return array See apply().
	 */
	public static function replace( $issue_id, $replacement ) {
		return self::apply( $issue_id, 'replace', $replacement );
	}

	/**
	 * Remove the links, keeping their text.
	 *
	 * @param int $issue_id Issue id.
	 * @return array See apply().
	 */
	public static function unlink( $issue_id ) {
		return self::apply( $issue_id, 'unlink', '' );
	}

	/**
	 * Re-resolve one issue against the live site.
	 *
	 * @param int $issue_id Issue id.
	 * @return array array( 'ok' => bool, 'status' => string, 'resolved' => bool )
	 */
	public static function recheck( $issue_id ) {
		$issue = IssueRepository::get( $issue_id );

		if ( ! $issue ) {
			return array(
				'ok'       => false,
				'status'   => '',
				'resolved' => false,
				'message'  => __( 'That issue no longer exists.', 'real-time-auto-find-and-replace' ),
			);
		}

		InternalResolver::flush();
		$resolution = InternalResolver::resolve( $issue->target_url );

		$still_broken = in_array( $resolution['status'], InternalResolver::problem_statuses(), true );

		if ( ! $still_broken ) {
			IssueRepository::transition( (int) $issue->id, IssueRepository::STATUS_RESOLVED, array( IssueRepository::STATUS_OPEN, IssueRepository::STATUS_FIXING ) );
		}

		return array(
			'ok'       => true,
			'status'   => $resolution['status'],
			'resolved' => ! $still_broken,
			'message'  => $still_broken
				? __( 'Still broken.', 'real-time-auto-find-and-replace' )
				: __( 'Fixed - the link resolves now.', 'real-time-auto-find-and-replace' ),
		);
	}

	/**
	 * Work out exactly which bytes an operation would change.
	 *
	 * @param int    $issue_id    Issue id.
	 * @param string $operation   'replace' or 'unlink'.
	 * @param string $replacement New URL, for a replace.
	 * @return array {
	 *     @type bool   $ok
	 *     @type string $message
	 *     @type string $code       Machine-readable failure reason.
	 *     @type array  $edits      Byte edits, ordered last-first.
	 *     @type object $issue
	 *     @type object $post
	 * }
	 */
	private static function plan( $issue_id, $operation, $replacement ) {
		$issue = IssueRepository::get( $issue_id );

		if ( ! $issue ) {
			return self::fail( 'issue_gone', __( 'That issue no longer exists.', 'real-time-auto-find-and-replace' ) );
		}

		if ( 'post' !== $issue->object_type ) {
			return self::fail( 'unsupported_object', __( 'This issue is not attached to a post.', 'real-time-auto-find-and-replace' ) );
		}

		$post = get_post( (int) $issue->object_id );

		if ( ! $post ) {
			return self::fail( 'post_gone', __( 'The post that contained this link has been deleted.', 'real-time-auto-find-and-replace' ) );
		}

		if ( 'replace' === $operation ) {
			$check = self::validate_replacement( $replacement, (string) $issue->type );

			if ( '' !== $check ) {
				return self::fail( 'bad_replacement', $check );
			}
		}

		$site_host = InternalResolver::site_host();
		$target    = (string) $issue->target_hash;
		$edits     = array();

		foreach ( Extractor::extract( $post->post_content ) as $one ) {
			if ( UrlNormalizer::hash( $one['url'], $site_host ) !== $target ) {
				continue;
			}

			if ( 'unlink' === $operation ) {
				// Only an anchor can be unlinked; an <img src> has no wrapper
				// to remove, and silently doing nothing to it would be worse
				// than saying so.
				if ( 'link' !== $one['type'] || $one['outer_offset'] < 0 ) {
					continue;
				}

				$edits[] = array(
					'offset' => $one['outer_offset'],
					'length' => $one['outer_length'],
					'from'   => substr( $post->post_content, $one['outer_offset'], $one['outer_length'] ),
					'to'     => $one['inner'],
				);

				continue;
			}

			$edits[] = array(
				'offset' => $one['offset'],
				'length' => strlen( $one['raw'] ),
				'from'   => $one['raw'],
				'to'     => self::encode_like( $one['raw'], $one['url'], $replacement ),
			);
		}

		if ( empty( $edits ) ) {
			return self::fail(
				'no_occurrences',
				'unlink' === $operation
					? __( 'There is no link to remove - this URL is not inside a link tag any more.', 'real-time-auto-find-and-replace' )
					: __( 'That URL is no longer in this post. It may already have been changed.', 'real-time-auto-find-and-replace' )
			);
		}

		// Apply back-to-front so earlier offsets stay valid as we go.
		usort(
			$edits,
			function ( $a, $b ) {
				return $b['offset'] - $a['offset'];
			}
		);

		return array(
			'ok'      => true,
			'message' => '',
			'code'    => '',
			'edits'   => $edits,
			'issue'   => $issue,
			'post'    => $post,
		);
	}

	/**
	 * Carry out a planned operation.
	 *
	 * @param int    $issue_id    Issue id.
	 * @param string $operation   'replace' or 'unlink'.
	 * @param string $replacement New URL.
	 * @return array array( ok, message, code, changed, post_id )
	 */
	private static function apply( $issue_id, $operation, $replacement ) {
		$plan = self::plan( $issue_id, $operation, $replacement );

		if ( ! $plan['ok'] ) {
			// A plan that no longer matches the content means the record is
			// stale, not that the user did something wrong.
			if ( in_array( $plan['code'], array( 'no_occurrences', 'post_gone' ), true ) ) {
				IssueRepository::transition( (int) $issue_id, IssueRepository::STATUS_STALE, array( IssueRepository::STATUS_OPEN, IssueRepository::STATUS_FIXING ) );
			}

			return $plan;
		}

		$issue = $plan['issue'];
		$post  = $plan['post'];

		// Claim the issue before writing. A double-clicked button, or two
		// editors on the same row, must not both apply the change.
		$claimed = IssueRepository::transition(
			(int) $issue->id,
			IssueRepository::STATUS_FIXING,
			array( IssueRepository::STATUS_OPEN )
		);

		if ( ! $claimed ) {
			return self::fail( 'not_claimable', __( 'Someone else is already working on this issue.', 'real-time-auto-find-and-replace' ) );
		}

		$original = (string) $post->post_content;
		$updated  = $original;

		foreach ( $plan['edits'] as $edit ) {
			$updated = substr_replace( $updated, $edit['to'], $edit['offset'], $edit['length'] );
		}

		if ( $updated === $original ) {
			IssueRepository::transition( (int) $issue->id, IssueRepository::STATUS_OPEN, array( IssueRepository::STATUS_FIXING ) );

			return self::fail( 'no_change', __( 'Nothing needed changing.', 'real-time-auto-find-and-replace' ) );
		}

		// wp_update_post(), never a raw UPDATE on wp_posts: it fires the normal
		// hooks and creates the native revision that is the second safety net.
		$result = wp_update_post(
			array(
				'ID'           => (int) $post->ID,
				'post_content' => $updated,
			),
			true
		);

		if ( is_wp_error( $result ) ) {
			IssueRepository::transition( (int) $issue->id, IssueRepository::STATUS_OPEN, array( IssueRepository::STATUS_FIXING ) );

			return self::fail( 'update_failed', $result->get_error_message() );
		}

		self::record_undo( (int) $post->ID, $original, $updated, $issue, $operation, $replacement );

		$operation_id = ActivityLog::new_operation();

		ActivityLog::record(
			'unlink' === $operation ? 'link_unlinked' : 'link_replaced',
			array(
				'operation_id' => $operation_id,
				'object_type'  => 'post',
				'object_id'    => (int) $post->ID,
				'summary'      => sprintf(
					/* translators: 1: number of occurrences, 2: post title */
					_n( 'Fixed %1$d link in "%2$s"', 'Fixed %1$d links in "%2$s"', count( $plan['edits'] ), 'real-time-auto-find-and-replace' ),
					count( $plan['edits'] ),
					(string) $post->post_title
				),
				'metadata'     => array(
					'issue_id'    => (int) $issue->id,
					'operation'   => $operation,
					'occurrences' => count( $plan['edits'] ),
					'target_url'  => (string) $issue->target_url,
					'replacement' => 'replace' === $operation ? (string) $replacement : '',
				),
			)
		);

		IssueRepository::transition(
			(int) $issue->id,
			IssueRepository::STATUS_RESOLVED,
			array( IssueRepository::STATUS_FIXING )
		);

		return array(
			'ok'      => true,
			'code'    => '',
			'message' => sprintf(
				/* translators: %d: number of occurrences changed */
				_n( '%d occurrence updated.', '%d occurrences updated.', count( $plan['edits'] ), 'real-time-auto-find-and-replace' ),
				count( $plan['edits'] )
			),
			'changed' => count( $plan['edits'] ),
			'post_id' => (int) $post->ID,
		);
	}

	/**
	 * Write the undo entry the existing Restore screen reads.
	 *
	 * The shape is not ours to choose. DbReplacer fires this action with
	 * exactly these keys, ActionHandler stores them, and AllRestoreDbData
	 * reverses them with a generic $wpdb->update(). Matching it is what makes
	 * this change reversible without a line of new restore code.
	 *
	 * @param int    $post_id     Post id.
	 * @param string $original    Content before.
	 * @param string $updated     Content after.
	 * @param object $issue       Issue row.
	 * @param string $operation   Operation performed.
	 * @param string $replacement Replacement URL.
	 * @return void
	 */
	private static function record_undo( $post_id, $original, $updated, $issue, $operation, $replacement ) {
		global $wpdb;

		if ( ! function_exists( 'do_action' ) ) {
			return;
		}

		do_action(
			'bfar_save_item_history',
			wp_json_encode(
				array(
					'tbl'     => $wpdb->posts,
					'rid'     => (int) $post_id,
					'pCol'    => 'ID',
					'col'     => 'post_content',
					'find'    => (string) $issue->target_url,
					'replace' => 'unlink' === $operation ? '' : (string) $replacement,
					'ici'     => false,
					'old_val' => $original,
					'new_val' => $updated,
				)
			)
		);
	}

	/**
	 * Is this replacement URL safe to write into somebody's content?
	 *
	 * The issue type matters for one check only, and it is worth the extra
	 * argument: under a broken image, the box invites a URL, and the URL people
	 * have to hand is often the *page* the picture was on. Accepting that would
	 * leave an `<img src>` pointing at an HTML document - a blank space where a
	 * photo was, reported as fixed. So a replacement offered for media has to
	 * look like media.
	 *
	 * @param string $replacement Proposed URL.
	 * @param string $issue_type  Issue type being fixed. Optional: an omitted
	 *                            type keeps the original, link-only behaviour.
	 * @return string Empty when acceptable, otherwise the reason.
	 */
	public static function validate_replacement( $replacement, $issue_type = '' ) {
		$replacement = trim( (string) $replacement );

		if ( '' === $replacement ) {
			return __( 'Enter a replacement URL.', 'real-time-auto-find-and-replace' );
		}

		if ( strlen( $replacement ) > 2000 ) {
			return __( 'That URL is too long.', 'real-time-auto-find-and-replace' );
		}

		$scheme = UrlNormalizer::scheme_of( $replacement );

		if ( '' !== $scheme && ! in_array( $scheme, array( 'http', 'https' ), true ) ) {
			/* translators: %s: URL scheme, e.g. javascript */
			return sprintf( __( '"%s:" links cannot be inserted here.', 'real-time-auto-find-and-replace' ), $scheme );
		}

		if ( preg_match( '/[\s<>"\']/', $replacement ) ) {
			return __( 'That URL contains characters that would break the markup around it.', 'real-time-auto-find-and-replace' );
		}

		if ( Classifier::MEDIA === $issue_type && ! Classifier::looks_like_media( $replacement ) ) {
			return __( 'That does not look like a file - a replacement for an image or a video needs to point at the file itself, not at the page it appears on.', 'real-time-auto-find-and-replace' );
		}

		return '';
	}

	/**
	 * Escape a replacement the same way the text it replaces was escaped.
	 *
	 * The bytes in the document are not always the URL: inside a Gutenberg
	 * block comment every slash is JSON-escaped, and inside an href an
	 * ampersand is usually an entity. Writing a raw URL into either place
	 * produces content that renders wrongly or JSON that no longer parses.
	 *
	 * @param string $raw         The bytes being replaced.
	 * @param string $decoded     Those bytes, decoded.
	 * @param string $replacement The new URL.
	 * @return string
	 */
	private static function encode_like( $raw, $decoded, $replacement ) {
		$replacement = (string) $replacement;

		// JSON-escaped slashes, as found in block attributes.
		if ( false !== strpos( $raw, '\\/' ) ) {
			return str_replace( '/', '\\/', $replacement );
		}

		// HTML entities, as found in an href.
		if ( $raw !== $decoded && false !== strpos( $raw, '&amp;' ) ) {
			return str_replace( '&', '&amp;', $replacement );
		}

		return $replacement;
	}

	/**
	 * Shape a failure.
	 *
	 * @param string $code    Machine-readable reason.
	 * @param string $message Human-readable reason.
	 * @return array
	 */
	private static function fail( $code, $message ) {
		return array(
			'ok'      => false,
			'code'    => $code,
			'message' => $message,
			'edits'   => array(),
			'issue'   => null,
			'post'    => null,
		);
	}
}
