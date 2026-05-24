<?php namespace RealTimeAutoFindReplace\actions;

/**
 * Class: Media Library integration
 *
 * Adds a "Replace media" row action to the Media Library list view
 * (wp-admin/upload.php) and loads the replacer modal there, so a file can be
 * replaced in-place without visiting the dedicated Media Replacer page.
 *
 * @package Action
 * @since 1.9.1
 * @author M.Tuhin <info@codesolz.net>
 */

if ( ! defined( 'CS_RTAFAR_VERSION' ) ) {
	die();
}

use RealTimeAutoFindReplace\admin\options\pages\MediaReplacer;

class RTAFAR_MediaLibrary {

	public function __construct() {
		add_filter( 'media_row_actions', array( $this, 'add_row_action' ), 10, 2 );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_action( 'admin_footer', array( $this, 'print_modal' ) );
	}

	/**
	 * Capability gate. Mirrors the AJAX endpoint, which requires manage_options,
	 * so the link is only shown to users whose request would actually succeed.
	 *
	 * @return bool
	 */
	private function user_can_replace() {
		return current_user_can( 'manage_options' );
	}

	/**
	 * Are we on the Media Library list screen (upload.php)?
	 *
	 * @return bool
	 */
	private function is_upload_screen() {
		if ( ! function_exists( 'get_current_screen' ) ) {
			return false;
		}
		$screen = get_current_screen();
		return $screen && 'upload' === $screen->id;
	}

	/**
	 * Inject the "Replace media" link into the list-view row actions.
	 *
	 * @param array    $actions
	 * @param \WP_Post  $post
	 * @return array
	 */
	public function add_row_action( $actions, $post ) {
		if ( ! $this->user_can_replace() || empty( $post->ID ) ) {
			return $actions;
		}

		$actions['bfar_replace_media'] = sprintf(
			'<a href="#" class="bfar-replace-media-row" data-id="%1$d" aria-label="%2$s">%3$s</a>',
			(int) $post->ID,
			esc_attr__( 'Replace this media file', 'real-time-auto-find-and-replace' ),
			esc_html__( 'Replace media', 'real-time-auto-find-and-replace' )
		);

		return $actions;
	}

	/**
	 * Load the replacer modal assets on the Media Library list screen.
	 *
	 * @return void
	 */
	public function enqueue_assets() {
		if ( ! $this->is_upload_screen() || ! $this->user_can_replace() ) {
			return;
		}

		// wp.media is used to fetch attachment details for the modal.
		wp_enqueue_media();

		wp_enqueue_style(
			'rtafar-media-replacer',
			CS_RTAFAR_PLUGIN_ASSET_URI . 'css/rtafar-admin-style.min.css',
			array(),
			CS_RTAFAR_VERSION
		);

		wp_enqueue_script(
			'rtafar.media.replacer.min',
			CS_RTAFAR_PLUGIN_ASSET_URI . 'js/rtafar.media.replacer.min.js',
			array( 'jquery' ),
			CS_RTAFAR_VERSION,
			true
		);

		// Note: the placeholder image vars (rtafr.pimg / rtafr.opimg) are localized
		// globally by RTAFAR_EnqueueScript on every admin screen, so we don't
		// re-localize `rtafr` here (that would clobber it). The JS detects this
		// screen via the `upload-php` body class.
	}

	/**
	 * Print the replacer modal + nonce in the footer of the list screen.
	 *
	 * @return void
	 */
	public function print_modal() {
		if ( ! $this->is_upload_screen() || ! $this->user_can_replace() ) {
			return;
		}

		echo '<div class="bfar-media-replacer-context">';
		// cs_token nonce consumed by the AJAX handler.
		wp_nonce_field( SECURE_AUTH_SALT, 'cs_token' );
		// Trusted, plugin-generated markup.
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo MediaReplacer::render_modal();
		echo '</div>';
	}
}
