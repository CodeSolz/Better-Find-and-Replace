<?php namespace RealTimeAutoFindReplace\Maintenance\CodeInsert;

/**
 * Where the three global snippets live.
 *
 * One autoloaded option, not a table. There are exactly three of these and there
 * always will be in the free tier - header, body-start, footer - so a table would
 * be a schema version, a migration and an uninstall routine for something that
 * fits in a row WordPress already loads on every request. The front-end printer
 * reads that one option and nothing else, which is the whole budget for this
 * feature on a page view.
 *
 * **Content is stored exactly as it was typed.** That is deliberate and it is
 * the reason `Guard` exists. Spec §30 asks for escaping that does not corrupt
 * legitimate script content, and there is no such escaping: `sanitize_textarea_field`
 * eats the `<` of every tag, and `wp_kses_post` removes a `<script>` block
 * outright. Anything that makes a snippet safe also makes it not work.
 *
 * So the safety of this module is not in what it does to the content. It is
 * entirely in **who was allowed to type it** - see `Guard` - and in escaping it
 * on the way back into the form, so that the editing screen is not itself the
 * vulnerability the printer was careful not to be.
 *
 * @package Maintenance
 * @since 1.10.0
 * @author M.Tuhin <info@codesolz.net>
 */

if ( ! defined( 'CS_RTAFAR_VERSION' ) ) {
	exit;
}

class Snippets {

	/** The autoloaded option holding all three slots. */
	const OPTION = 'bfar_code_inserts';

	/** In `<head>`. */
	const HEADER = 'header';

	/** Immediately after `<body>`, where a theme supports it. */
	const BODY = 'body';

	/** Before `</body>`. */
	const FOOTER = 'footer';

	/** Longest snippet accepted, in bytes. */
	const MAX_BYTES = 65535;

	/**
	 * The three slots, in print order.
	 *
	 * @return array
	 */
	public static function slots() {
		return array( self::HEADER, self::BODY, self::FOOTER );
	}

	/**
	 * Human names for the slots.
	 *
	 * @return array
	 */
	public static function labels() {
		return array(
			self::HEADER => __( 'Header', 'real-time-auto-find-and-replace' ),
			self::BODY   => __( 'Body start', 'real-time-auto-find-and-replace' ),
			self::FOOTER => __( 'Footer', 'real-time-auto-find-and-replace' ),
		);
	}

	/**
	 * Everything, in a shape the caller never has to check.
	 *
	 * @return array slot => array( content, enabled, updated_at, updated_by )
	 */
	public static function all() {
		$stored = get_option( self::OPTION, array() );
		$stored = is_array( $stored ) ? $stored : array();
		$out    = array();

		foreach ( self::slots() as $slot ) {
			$out[ $slot ] = self::shape( isset( $stored[ $slot ] ) ? $stored[ $slot ] : array() );
		}

		return $out;
	}

	/**
	 * One slot.
	 *
	 * @param string $slot One of slots().
	 * @return array
	 */
	public static function get( $slot ) {
		$all = self::all();

		return isset( $all[ $slot ] ) ? $all[ $slot ] : self::shape( array() );
	}

	/**
	 * Save one slot.
	 *
	 * Takes no view on whether the caller was allowed to do this. That is
	 * `Guard`'s job and every caller checks it - keeping the check out of here
	 * means the store can be exercised by a test without a logged-in user, and
	 * means there is exactly one place the rule is written down.
	 *
	 * @param string $slot    One of slots().
	 * @param string $content Raw snippet, exactly as typed.
	 * @param bool   $enabled Whether it should print.
	 * @return array {
	 *     @type bool   $ok
	 *     @type string $code
	 *     @type string $message
	 * }
	 */
	public static function save( $slot, $content, $enabled = true ) {
		$slot = (string) $slot;

		if ( ! in_array( $slot, self::slots(), true ) ) {
			return self::fail( 'unknown_slot', __( 'There is no such code slot.', 'real-time-auto-find-and-replace' ) );
		}

		// Not sanitised, only bounded. A limit is the one restriction that can
		// be applied to a snippet without changing what it does.
		$content = (string) $content;

		if ( strlen( $content ) > self::MAX_BYTES ) {
			return self::fail(
				'too_long',
				__( 'That snippet is too long. Move it into a file and link to it instead.', 'real-time-auto-find-and-replace' )
			);
		}

		$stored = get_option( self::OPTION, array() );
		$stored = is_array( $stored ) ? $stored : array();

		$stored[ $slot ] = array(
			'content'    => $content,
			'enabled'    => (bool) $enabled,
			'updated_at' => gmdate( 'Y-m-d H:i:s' ),
			'updated_by' => function_exists( 'get_current_user_id' ) ? (int) get_current_user_id() : 0,
		);

		// Autoloaded on purpose: the printer reads it on every front-end page,
		// so paying for it in the load WordPress already does is cheaper than a
		// query per request.
		update_option( self::OPTION, $stored, true );

		/**
		 * Fires after a code snippet is stored.
		 *
		 * @param string $slot    The slot that changed.
		 * @param array  $snippet Its new state.
		 */
		do_action( 'bfr_code_insert_saved', $slot, $stored[ $slot ] );

		return array(
			'ok'      => true,
			'code'    => '',
			'message' => '',
		);
	}

	/**
	 * The slots that would actually print, in order.
	 *
	 * @return array slot => content
	 */
	public static function enabled() {
		$out = array();

		foreach ( self::all() as $slot => $snippet ) {
			if ( empty( $snippet['enabled'] ) || '' === trim( $snippet['content'] ) ) {
				continue;
			}

			$out[ $slot ] = $snippet['content'];
		}

		return $out;
	}

	/**
	 * Is there anything at all to print?
	 *
	 * The whole front-end budget for a site that has never used this feature:
	 * one autoloaded option read, and a return.
	 *
	 * @return bool
	 */
	public static function any() {
		$stored = get_option( self::OPTION, array() );

		if ( ! is_array( $stored ) || empty( $stored ) ) {
			return false;
		}

		foreach ( $stored as $snippet ) {
			if ( ! empty( $snippet['enabled'] ) && '' !== trim( (string) ( isset( $snippet['content'] ) ? $snippet['content'] : '' ) ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Forget everything. Used by uninstall and by tests.
	 *
	 * @return void
	 */
	public static function forget() {
		delete_option( self::OPTION );
	}

	/**
	 * Fill in a stored row so callers never test for a key.
	 *
	 * @param mixed $row Stored value.
	 * @return array
	 */
	private static function shape( $row ) {
		$row = is_array( $row ) ? $row : array();

		return array(
			'content'    => isset( $row['content'] ) ? (string) $row['content'] : '',
			'enabled'    => ! empty( $row['enabled'] ),
			'updated_at' => isset( $row['updated_at'] ) ? (string) $row['updated_at'] : '',
			'updated_by' => isset( $row['updated_by'] ) ? (int) $row['updated_by'] : 0,
		);
	}

	/**
	 * A refusal, in the shape every caller here expects.
	 *
	 * @param string $code    Machine-readable reason.
	 * @param string $message Human-readable reason.
	 * @return array
	 */
	private static function fail( $code, $message ) {
		return array(
			'ok'      => false,
			'code'    => (string) $code,
			'message' => (string) $message,
		);
	}
}
