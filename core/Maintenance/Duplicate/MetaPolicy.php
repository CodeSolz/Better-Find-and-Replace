<?php namespace RealTimeAutoFindReplace\Maintenance\Duplicate;

/**
 * Which custom fields are allowed to travel with a clone.
 *
 * Meta copying is where cloners corrupt data, and there are two ways to get it
 * wrong rather than one. Copying everything drags across the original post's
 * edit lock, its trash state and its old slugs, so the clone claims things that
 * belong to another post. Copying only a known list drops the ACF fields, the
 * WooCommerce data and the page-builder layout that made the clone worth having
 * - and it does it silently, which is the same corruption from the other side.
 *
 * So this is a **deny-list, explicit and reviewable**, and the rule it enforces
 * is narrow enough to state: a key is denied when it encodes **the original
 * post's identity or its transient state**. Not "keys we dislike" - every entry
 * below has a reason, and a key that does not have one does not belong here.
 *
 * Anything else is copied. A site whose plugin stores something identity-shaped
 * under its own key adds it through `bfr_clone_denied_meta`.
 *
 * @package Maintenance
 * @since 1.11.0
 * @author M.Tuhin <info@codesolz.net>
 */

if ( ! defined( 'CS_RTAFAR_VERSION' ) ) {
	exit;
}

class MetaPolicy {

	/**
	 * Keys that must never travel, and why.
	 *
	 * @return array key => reason.
	 */
	public static function denied() {
		return array(
			// Who is editing the original, right now. Copied, it locks the
			// clone against an editing session that is not about it.
			'_edit_lock'                     => 'edit session on the original',
			'_edit_last'                     => 'who last edited the original',

			// Restores the clone into a state it was never in.
			'_wp_trash_meta_status'          => 'trash state of the original',
			'_wp_trash_meta_time'            => 'trash state of the original',
			'_wp_trash_meta_comments_status' => 'trash state of the original',

			// Old addresses belong to the post that used to live at them.
			// Copied, the clone claims redirects meant for the original.
			'_wp_old_slug'                   => 'redirects that belong to the original',
			'_wp_old_date'                   => 'redirects that belong to the original',

			// Work WordPress still has to do about the original: pinging and
			// enclosure lookups it has already queued.
			'_pingme'                        => 'pending work queued for the original',
			'_encloseme'                     => 'pending work queued for the original',

			// This platform's own per-post bookkeeping. A clone has not been
			// scanned, has no issues, and must not inherit the answers.
			'_bfar_issue_state'              => 'this platform\'s record about the original',
			'_bfar_last_scan'                => 'this platform\'s record about the original',
			'_bfrp_refresh_state'            => 'this platform\'s record about the original',
		);
	}

	/**
	 * Prefixes that are denied wholesale.
	 *
	 * Only this platform's own, because they are the only ones whose meaning is
	 * known here. Denying `_` outright would take ACF, WooCommerce and every
	 * page builder with it.
	 *
	 * @return array
	 */
	public static function denied_prefixes() {
		return array( '_bfar_', '_bfrp_' );
	}

	/**
	 * May this key travel?
	 *
	 * @param string $key Meta key.
	 * @return bool
	 */
	public static function allows( $key ) {
		$key = (string) $key;

		if ( '' === $key ) {
			return false;
		}

		if ( isset( self::all_denied()[ $key ] ) ) {
			return false;
		}

		foreach ( self::denied_prefixes() as $prefix ) {
			if ( 0 === strpos( $key, $prefix ) ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * The deny-list, after the filter.
	 *
	 * @return array key => reason.
	 */
	public static function all_denied() {
		$denied = self::denied();

		if ( ! function_exists( 'apply_filters' ) ) {
			return $denied;
		}

		/**
		 * Filter the meta keys a clone must not carry.
		 *
		 * Add a key here when a plugin stores something on a post that
		 * identifies *that* post rather than describing it - a licence binding,
		 * a queue position, an external record id.
		 *
		 * Removing a key from this list is possible and is almost always a
		 * mistake: everything here has a reason recorded next to it.
		 *
		 * @param array $denied Meta key => short reason.
		 */
		$filtered = apply_filters( 'bfr_clone_denied_meta', $denied );

		return is_array( $filtered ) ? $filtered : $denied;
	}

	/**
	 * Split a post's meta into what travels and what does not.
	 *
	 * Takes the meta rather than fetching it, so the decision is testable
	 * without a database and the caller keeps control of how much it reads.
	 *
	 * @param array $meta Meta as get_post_meta( $id ) returns it: key => values.
	 * @return array {
	 *     @type array $copy    Key => values that travel.
	 *     @type array $skipped Key => reason, for the ones that do not.
	 * }
	 */
	public static function partition( array $meta ) {
		$copy    = array();
		$skipped = array();

		$denied = self::all_denied();

		if ( function_exists( 'apply_filters' ) ) {
			/**
			 * Filter denials that depend on which keys the post actually has.
			 *
			 * The plain deny-list is a fixed set and cannot answer "copy only
			 * these three fields", because it never sees what the other fields
			 * are. This one does.
			 *
			 * Like the other, it may only ever **add**. Taking a key out here
			 * takes out a safety rule.
			 *
			 * @param array $denied Meta key => short reason.
			 * @param array $keys   Every meta key on the post being copied.
			 */
			$denied = (array) apply_filters( 'bfr_clone_denied_meta_for_post', $denied, array_keys( $meta ) );
		}

		foreach ( $meta as $key => $values ) {
			// The per-post list is consulted as well as allows(): allows()
			// answers from the fixed deny-list alone, and would let through a
			// key this particular post is not meant to carry.
			if ( ! isset( $denied[ $key ] ) && self::allows( $key ) ) {
				$copy[ $key ] = $values;

				continue;
			}

			$skipped[ $key ] = isset( $denied[ $key ] ) ? $denied[ $key ] : 'this platform\'s own record';
		}

		return array(
			'copy'    => $copy,
			'skipped' => $skipped,
		);
	}
}
