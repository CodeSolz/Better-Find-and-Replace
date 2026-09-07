<?php namespace RealTimeAutoFindReplace\Maintenance\ReplaceRedirect;

use RealTimeAutoFindReplace\admin\functions\DbReplacer;
use RealTimeAutoFindReplace\Maintenance\Data\ActivityLog;
use RealTimeAutoFindReplace\Maintenance\Data\RedirectRepository;
use RealTimeAutoFindReplace\Maintenance\LinkHealth\InternalResolver;
use RealTimeAutoFindReplace\Maintenance\Redirects\Validator;
use RealTimeAutoFindReplace\Maintenance\Support\Entitlements;
use RealTimeAutoFindReplace\Maintenance\Support\Logger;
use RealTimeAutoFindReplace\Maintenance\Support\UrlNormalizer;

/**
 * Replace a URL everywhere, then redirect the old one to the new one.
 *
 * The signature workflow, and deliberately the thinnest class in the platform:
 * it sequences things that already exist and records what happened. Every step
 * is somebody else's tested code.
 *
 *     preview   Preview                     read-only, changes nothing
 *     apply     DbReplacer::replace_links()  posts + postmeta + options
 *     redirect  Validator + RedirectRepository
 *     verify    InternalResolver
 *     log       ActivityLog, one operation_id across all of it
 *
 * Two properties matter more than the rest.
 *
 * **Undo comes free.** replace_links() writes through bfrReplaceVariants(),
 * which fires bfar_save_item_history for every cell it changes - the same
 * action the shipped Restore in Database screen reads. Driving the engine
 * rather than reimplementing it is what makes the whole operation reversible
 * without a line of new restore code.
 *
 * **The redirect is idempotent.** Re-running an operation must not leave a
 * second redirect behind, so a duplicate source is treated as success: the
 * desired end state already exists.
 *
 * The steps are ordered so a failure is survivable. The replacement happens
 * first because it is the reversible half; the redirect follows because a
 * redirect without the replacement is merely unnecessary, while a replacement
 * without the redirect still breaks inbound links from elsewhere - and that is
 * the case worth reporting loudly.
 *
 * @package Maintenance
 * @since 1.10.0
 * @author M.Tuhin <info@codesolz.net>
 */

if ( ! defined( 'CS_RTAFAR_VERSION' ) ) {
	exit;
}

class Workflow {

	/** Nothing has been done. */
	const STEP_PENDING = 'pending';

	/** Content has been rewritten. */
	const STEP_REPLACED = 'replaced';

	/** The redirect exists. */
	const STEP_REDIRECTED = 'redirected';

	/** Both URLs have been re-checked. */
	const STEP_VERIFIED = 'verified';

	/**
	 * Work out what an operation would change.
	 *
	 * @param string $from URL being replaced.
	 * @param string $to   URL replacing it.
	 * @return array Preview data, plus 'ok' and any 'errors'.
	 */
	public static function preview( $from, $to ) {
		$check = self::validate( $from, $to );

		if ( ! $check['ok'] ) {
			return array(
				'ok'          => false,
				'errors'      => $check['errors'],
				'occurrences' => 0,
				'rows'        => 0,
				'changes'     => 0,
				'locations'   => array(),
			);
		}

		$preview = Preview::build( $check['from'], $check['to'] );

		$preview['ok']             = true;
		$preview['errors']         = array();
		$preview['from']           = $check['from'];
		$preview['to']             = $check['to'];
		$preview['redirect_ready'] = $check['redirect_ready'];
		$preview['redirect_note']  = $check['redirect_note'];

		return $preview;
	}

	/**
	 * Do it.
	 *
	 * @param string $from URL being replaced.
	 * @param string $to   URL replacing it.
	 * @param array  $args {
	 *     @type bool $create_redirect Whether to create the 301. Default true.
	 * }
	 * @return array {
	 *     @type bool   $ok
	 *     @type string $step         How far it got.
	 *     @type int    $replaced     Cells rewritten.
	 *     @type array  $redirect     array( created, id, reason )
	 *     @type array  $verification array( old, new )
	 *     @type string $operation_id Ties the activity rows together.
	 *     @type array  $errors
	 *     @type array  $warnings
	 * }
	 */
	public static function apply( $from, $to, array $args = array() ) {
		$create_redirect = ! isset( $args['create_redirect'] ) || (bool) $args['create_redirect'];

		$check = self::validate( $from, $to );

		if ( ! $check['ok'] ) {
			return self::failure( $check['errors'] );
		}

		$from         = $check['from'];
		$to           = $check['to'];
		$operation_id = ActivityLog::new_operation();

		$result = array(
			'ok'           => true,
			'step'         => self::STEP_PENDING,
			'replaced'     => 0,
			'redirect'     => array(
				'created' => false,
				'id'      => 0,
				'reason'  => 'not_requested',
			),
			'verification' => array(),
			'cache'        => array(
				'purged'   => array(),
				'complete' => false,
			),
			'operation_id' => $operation_id,
			'errors'       => array(),
			'warnings'     => $check['warnings'],
		);

		// 1. Replace. The engine's own method: it walks posts, postmeta and
		// options, handles serialized and encoded content, fires
		// bfar_save_item_history per cell, and regenerates builder caches on the
		// way out.
		$replacer = new DbReplacer();

		try {
			$result['replaced'] = (int) $replacer->replace_links( $from, $to, true );
		} catch ( \Throwable $e ) {
			Logger::log( 'replace_redirect.failed', array( 'stage' => 'replace' ) );

			return self::failure(
				array( __( 'The replacement could not be completed. Nothing else was changed.', 'real-time-auto-find-and-replace' ) ),
				$operation_id
			);
		}

		$result['step'] = self::STEP_REPLACED;

		ActivityLog::record(
			'replacement_applied',
			array(
				'operation_id' => $operation_id,
				'object_type'  => 'url',
				'summary'      => sprintf(
					/* translators: 1: number of places changed, 2: old URL, 3: new URL */
					_n( 'Replaced %1$d reference to %2$s with %3$s', 'Replaced %1$d references to %2$s with %3$s', $result['replaced'], 'real-time-auto-find-and-replace' ),
					$result['replaced'],
					$from,
					$to
				),
				'metadata'     => array(
					'from'     => $from,
					'to'       => $to,
					'replaced' => $result['replaced'],
				),
			)
		);

		// 2. Redirect, so inbound links from other sites still work.
		if ( $create_redirect ) {
			$result['redirect'] = self::create_redirect( $from, $to, $operation_id );

			if ( $result['redirect']['created'] || 'already_exists' === $result['redirect']['reason'] ) {
				$result['step'] = self::STEP_REDIRECTED;
			} else {
				// The content is already rewritten and that half is reversible.
				// Say plainly what did not happen rather than implying the
				// whole operation failed.
				$result['warnings'][] = __( 'The content was updated, but the redirect could not be created. Inbound links to the old URL will still 404.', 'real-time-auto-find-and-replace' );
			}
		}

		// 3. Purge page caches, or the visitor keeps seeing the old HTML however
		// correct the database now is. Only when something actually changed.
		if ( $result['replaced'] > 0 ) {
			$result['cache'] = CacheFlush::run();
		}

		// 4. Verify both ends, so the report is observed rather than assumed.
		InternalResolver::flush();

		$result['verification'] = array(
			'old' => InternalResolver::resolve( $from )['status'],
			'new' => InternalResolver::resolve( $to )['status'],
		);

		if ( self::STEP_REDIRECTED === $result['step'] ) {
			$result['step'] = self::STEP_VERIFIED;
		}

		if ( InternalResolver::MISSING === $result['verification']['new'] ) {
			$result['warnings'][] = __( 'The new URL does not resolve to anything yet. Check the destination exists.', 'real-time-auto-find-and-replace' );
		}

		ActivityLog::record(
			'replace_redirect_completed',
			array(
				'operation_id' => $operation_id,
				'object_type'  => 'url',
				'summary'      => sprintf(
					/* translators: 1: old URL, 2: new URL */
					__( 'Replace + Redirect finished: %1$s to %2$s', 'real-time-auto-find-and-replace' ),
					$from,
					$to
				),
				'metadata'     => array(
					'from'         => $from,
					'to'           => $to,
					'replaced'     => $result['replaced'],
					'redirect_id'  => $result['redirect']['id'],
					'step'         => $result['step'],
					'verification' => $result['verification'],
				),
			)
		);

		/**
		 * Fires when a Replace + Redirect finishes.
		 *
		 * Pro attaches its verification pass and scheduled re-check here.
		 *
		 * @param array $result The completed operation.
		 */
		do_action( 'bfr_replace_redirect_after_apply', $result );

		Logger::log(
			'replace_redirect.done',
			array(
				'replaced' => $result['replaced'],
				'step'     => $result['step'],
			)
		);

		return $result;
	}

	/**
	 * Run several operations in one go.
	 *
	 * Gated on `replace_redirect.bulk`. The free tier does one URL at a time on
	 * purpose: this is the most destructive thing the product can do, and doing
	 * it fifty times from one click is exactly where a mistake stops being
	 * recoverable in practice - even though every individual change is still
	 * reversible.
	 *
	 * Each pair is a complete, independently logged operation. One failure does
	 * not abandon the rest, because the pairs are unrelated and stopping halfway
	 * would leave the user guessing which ones ran.
	 *
	 * @param array $pairs Each: array( 'from' => string, 'to' => string ).
	 * @param array $args  Passed through to apply().
	 * @return array {
	 *     @type bool  $ok
	 *     @type array $results One entry per pair, in order.
	 *     @type int   $applied Operations that succeeded.
	 *     @type int   $failed  Operations that did not.
	 *     @type array $errors
	 * }
	 */
	public static function apply_many( array $pairs, array $args = array() ) {
		if ( ! Entitlements::can( 'replace_redirect.bulk' ) ) {
			return array(
				'ok'      => false,
				'results' => array(),
				'applied' => 0,
				'failed'  => 0,
				'errors'  => array( __( 'Replacing several URLs at once is a Pro feature. You can run them one at a time here.', 'real-time-auto-find-and-replace' ) ),
			);
		}

		$results = array();
		$applied = 0;
		$failed  = 0;

		foreach ( $pairs as $pair ) {
			$from = isset( $pair['from'] ) ? $pair['from'] : '';
			$to   = isset( $pair['to'] ) ? $pair['to'] : '';

			$result    = self::apply( $from, $to, $args );
			$results[] = $result;

			if ( $result['ok'] ) {
				++$applied;
			} else {
				++$failed;
			}
		}

		return array(
			'ok'      => true,
			'results' => $results,
			'applied' => $applied,
			'failed'  => $failed,
			'errors'  => array(),
		);
	}

	/**
	 * Create the redirect, treating an existing equivalent as success.
	 *
	 * @param string $from         Old URL.
	 * @param string $to           New URL.
	 * @param string $operation_id Operation id for the log.
	 * @return array array( created, id, reason )
	 */
	private static function create_redirect( $from, $to, $operation_id ) {
		$check = Validator::check(
			$from,
			$to,
			array(
				'site_host' => InternalResolver::site_host(),
				'existing'  => RedirectRepository::all_for_validation(),
			)
		);

		if ( ! $check['ok'] ) {
			// The commonest reason is that a redirect for this source already
			// exists - which is the state we wanted, so it is not a failure.
			$existing = RedirectRepository::find_enabled( UrlNormalizer::hash( $from, InternalResolver::site_host() ) );

			if ( $existing ) {
				return array(
					'created' => false,
					'id'      => (int) $existing->id,
					'reason'  => 'already_exists',
				);
			}

			return array(
				'created' => false,
				'id'      => 0,
				'reason'  => implode( ' ', $check['errors'] ),
			);
		}

		$insert = RedirectRepository::insert(
			array(
				'source'        => $check['source'],
				'source_hash'   => $check['source_hash'],
				'destination'   => $check['destination'],
				'redirect_type' => 301,
				'match_type'    => 'exact',
				'enabled'       => 1,
			)
		);

		if ( ! $insert['ok'] ) {
			// Lost a race, or an equivalent redirect appeared between the
			// validation and the write. Either way the end state is correct.
			if ( 'duplicate' === $insert['reason'] ) {
				return array(
					'created' => false,
					'id'      => 0,
					'reason'  => 'already_exists',
				);
			}

			return array(
				'created' => false,
				'id'      => 0,
				'reason'  => $insert['reason'],
			);
		}

		ActivityLog::record(
			'redirect_created',
			array(
				'operation_id' => $operation_id,
				'object_type'  => 'redirect',
				'object_id'    => $insert['id'],
				'summary'      => sprintf(
					/* translators: 1: old URL, 2: new URL */
					__( 'Redirect added: %1$s to %2$s', 'real-time-auto-find-and-replace' ),
					$check['source'],
					$check['destination']
				),
				'metadata'     => array(
					'source'      => $check['source'],
					'destination' => $check['destination'],
					'type'        => 301,
				),
			)
		);

		return array(
			'created' => true,
			'id'      => (int) $insert['id'],
			'reason'  => '',
		);
	}

	/**
	 * Is this pair of URLs worth acting on?
	 *
	 * @param string $from URL being replaced.
	 * @param string $to   URL replacing it.
	 * @return array
	 */
	private static function validate( $from, $to ) {
		$from   = trim( (string) $from );
		$to     = trim( (string) $to );
		$errors = array();

		if ( '' === $from || '' === $to ) {
			$errors[] = __( 'Enter both the old URL and the new one.', 'real-time-auto-find-and-replace' );

			return self::validation( false, $from, $to, $errors );
		}

		if ( strlen( $from ) < 2 ) {
			// Replacing "/" everywhere would rewrite the entire site.
			$errors[] = __( 'That URL is too short to replace safely.', 'real-time-auto-find-and-replace' );

			return self::validation( false, $from, $to, $errors );
		}

		$site_host = InternalResolver::site_host();

		if ( UrlNormalizer::hash( $from, $site_host ) === UrlNormalizer::hash( $to, $site_host ) ) {
			$errors[] = __( 'The old and new URLs are the same.', 'real-time-auto-find-and-replace' );

			return self::validation( false, $from, $to, $errors );
		}

		$destination_error = Validator::check_destination( $to );

		if ( '' !== $destination_error ) {
			$errors[] = $destination_error;

			return self::validation( false, $from, $to, $errors );
		}

		// Whether a redirect is even possible is worth knowing before the
		// operation runs, not after - an external "from" cannot be redirected.
		$redirect_check = Validator::check(
			$from,
			$to,
			array(
				'site_host' => $site_host,
				'existing'  => RedirectRepository::all_for_validation(),
			)
		);

		$result                   = self::validation( true, $from, $to, array() );
		$result['warnings']       = $redirect_check['warnings'];
		$result['redirect_ready'] = $redirect_check['ok'];
		$result['redirect_note']  = $redirect_check['ok'] ? '' : implode( ' ', $redirect_check['errors'] );

		return $result;
	}

	/**
	 * Shape a validation result.
	 *
	 * @param bool   $ok     Whether it passed.
	 * @param string $from   Old URL.
	 * @param string $to     New URL.
	 * @param array  $errors Reasons it did not.
	 * @return array
	 */
	private static function validation( $ok, $from, $to, array $errors ) {
		return array(
			'ok'             => (bool) $ok,
			'from'           => $from,
			'to'             => $to,
			'errors'         => $errors,
			'warnings'       => array(),
			'redirect_ready' => false,
			'redirect_note'  => '',
		);
	}

	/**
	 * Shape a failed operation.
	 *
	 * @param array  $errors       Reasons.
	 * @param string $operation_id Operation id, when one was started.
	 * @return array
	 */
	private static function failure( array $errors, $operation_id = '' ) {
		return array(
			'ok'           => false,
			'step'         => self::STEP_PENDING,
			'replaced'     => 0,
			'redirect'     => array(
				'created' => false,
				'id'      => 0,
				'reason'  => 'not_attempted',
			),
			'verification' => array(),
			'cache'        => array(
				'purged'   => array(),
				'complete' => false,
			),
			'operation_id' => $operation_id,
			'errors'       => $errors,
			'warnings'     => array(),
		);
	}
}
