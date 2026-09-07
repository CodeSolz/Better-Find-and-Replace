<?php namespace RealTimeAutoFindReplace\Maintenance\Duplicate;

use RealTimeAutoFindReplace\Maintenance\Support\Entitlements;

/**
 * "Duplicate" and "Clone & Edit", where somebody would look for them.
 *
 * On the post list, next to Edit and Trash. Not on a settings page, not behind a
 * menu item of its own: the moment somebody wants to copy a post is the moment
 * they are looking at it in a list, and a cloner they have to go and find is a
 * cloner they use once.
 *
 * The two actions differ only in where they leave you. *Duplicate* stays on the
 * list, because copying five pages in a row is a real thing people do.
 * *Clone & Edit* opens the copy, because copying one page to change it is the
 * other real thing.
 *
 * Every link is nonced per post. A cloner reachable by a bare URL is a cloner an
 * image tag on another site can fire on somebody's behalf.
 *
 * @package Maintenance
 * @since 1.11.0
 * @author M.Tuhin <info@codesolz.net>
 */

if ( ! defined( 'CS_RTAFAR_VERSION' ) ) {
	exit;
}

class Actions {

	/** The query argument that asks for a clone. */
	const ARG = 'bfar_clone';

	/** Nonce action prefix; the post id is appended. */
	const NONCE = 'bfar_clone_';

	/**
	 * Register the hooks. Nothing else happens here.
	 */
	public function __construct() {
		add_filter( 'post_row_actions', array( $this, 'row_actions' ), 10, 2 );
		add_filter( 'page_row_actions', array( $this, 'row_actions' ), 10, 2 );
		add_action( 'admin_init', array( $this, 'maybe_clone' ) );
		add_action( 'admin_notices', array( $this, 'notice' ) );
	}

	/**
	 * Add the two links to a row.
	 *
	 * @param array  $actions Existing row actions.
	 * @param object $post    The post the row is about.
	 * @return array
	 */
	public function row_actions( $actions, $post ) {
		if ( ! is_array( $actions ) ) {
			$actions = array();
		}

		if ( ! self::available() || ! isset( $post->ID ) ) {
			return $actions;
		}

		if ( ! Cloner::is_cloneable( $post->post_type ) || ! Cloner::user_can_clone( $post ) ) {
			return $actions;
		}

		$actions['bfar_clone'] = sprintf(
			'<a href="%1$s">%2$s</a>',
			esc_url( self::url( (int) $post->ID, false ) ),
			esc_html__( 'Duplicate', 'real-time-auto-find-and-replace' )
		);

		$actions['bfar_clone_edit'] = sprintf(
			'<a href="%1$s">%2$s</a>',
			esc_url( self::url( (int) $post->ID, true ) ),
			esc_html__( 'Clone &amp; Edit', 'real-time-auto-find-and-replace' )
		);

		return $actions;
	}

	/**
	 * Is duplicating available on this site?
	 *
	 * @return bool
	 */
	public static function available() {
		if ( ! class_exists( '\RealTimeAutoFindReplace\Maintenance\Support\Entitlements' ) ) {
			return true;
		}

		return Entitlements::can( 'duplicate.single' );
	}

	/**
	 * The address one of the links points at.
	 *
	 * @param int  $post_id Post to copy.
	 * @param bool $edit    Whether to open the copy afterwards.
	 * @return string
	 */
	public static function url( $post_id, $edit = false ) {
		$args = array(
			self::ARG => (int) $post_id,
		);

		if ( $edit ) {
			$args['bfar_edit'] = 1;
		}

		return wp_nonce_url(
			add_query_arg( $args, admin_url( 'edit.php' ) ),
			self::NONCE . (int) $post_id,
			'_bfarnonce'
		);
	}

	/**
	 * Do the copy, if that is what this request is.
	 *
	 * @return void
	 */
	public function maybe_clone() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( empty( $_GET[ self::ARG ] ) ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$post_id = (int) $_GET[ self::ARG ];
		$nonce   = isset( $_GET['_bfarnonce'] ) ? sanitize_text_field( wp_unslash( $_GET['_bfarnonce'] ) ) : '';

		if ( ! wp_verify_nonce( $nonce, self::NONCE . $post_id ) || ! self::available() ) {
			return;
		}

		$result = Cloner::clone_post( $post_id );

		if ( empty( $result['ok'] ) ) {
			self::redirect_back( $post_id, $result['code'] );

			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( ! empty( $_GET['bfar_edit'] ) ) {
			wp_safe_redirect( get_edit_post_link( $result['id'], 'raw' ) );
			exit;
		}

		self::redirect_back( $post_id, 'cloned' );
	}

	/**
	 * Back to the list, with something to say.
	 *
	 * @param int    $post_id The post that was being copied.
	 * @param string $code    What happened.
	 * @return void
	 */
	private static function redirect_back( $post_id, $code ) {
		$post = get_post( $post_id );
		$type = $post && isset( $post->post_type ) ? $post->post_type : 'post';

		wp_safe_redirect(
			add_query_arg(
				array(
					'post_type'   => $type,
					'bfar_cloned' => $code,
				),
				admin_url( 'edit.php' )
			)
		);

		exit;
	}

	/**
	 * Say what happened, once, on the list somebody landed back on.
	 *
	 * @return void
	 */
	public function notice() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( empty( $_GET['bfar_cloned'] ) ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$code = sanitize_key( wp_unslash( $_GET['bfar_cloned'] ) );

		$messages = array(
			'cloned'        => __( 'Duplicated as a draft.', 'real-time-auto-find-and-replace' ),
			'not_allowed'   => __( 'You do not have permission to duplicate that.', 'real-time-auto-find-and-replace' ),
			'bad_type'      => __( 'That kind of content cannot be duplicated.', 'real-time-auto-find-and-replace' ),
			'no_post'       => __( 'That post no longer exists.', 'real-time-auto-find-and-replace' ),
			'insert_failed' => __( 'The duplicate could not be created.', 'real-time-auto-find-and-replace' ),
		);

		if ( ! isset( $messages[ $code ] ) ) {
			return;
		}

		printf(
			'<div class="notice notice-%1$s is-dismissible"><p>%2$s</p></div>',
			'cloned' === $code ? 'success' : 'warning',
			esc_html( $messages[ $code ] )
		);
	}
}
