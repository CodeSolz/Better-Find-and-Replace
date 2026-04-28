<?php namespace RealTimeAutoFindReplace\actions;

/**
 * AI OAuth admin-post.php endpoints: start, callback, disconnect.
 *
 * @package Action
 * @since 1.9.0
 */

if ( ! defined( 'CS_RTAFAR_VERSION' ) ) {
	die();
}

use RealTimeAutoFindReplace\lib\Util;
use RealTimeAutoFindReplace\admin\functions\ai\OAuth\Manager;
use RealTimeAutoFindReplace\admin\functions\ai\OAuth\StateStore;

class RTAFAR_AiOauth {

	const ACTION_START      = 'rtafar_ai_oauth_start';
	const ACTION_CALLBACK   = 'rtafar_ai_oauth_callback';
	const ACTION_DISCONNECT = 'rtafar_ai_oauth_disconnect';
	const NONCE_ACTION      = 'rtafar_ai_oauth';

	public function __construct() {
		add_action( 'admin_post_' . self::ACTION_START, array( $this, 'handle_start' ) );
		add_action( 'admin_post_' . self::ACTION_CALLBACK, array( $this, 'handle_callback' ) );
		add_action( 'admin_post_' . self::ACTION_DISCONNECT, array( $this, 'handle_disconnect' ) );
	}

	private function settings_page_url( array $extra_args = array() ) {
		return add_query_arg(
			array_merge( array( 'page' => 'cs-bfar-ai-settings' ), $extra_args ),
			admin_url( 'admin.php' )
		);
	}

	private function ensure_capability() {
		if ( ! current_user_can( 'manage_options' )
			&& ! current_user_can( Util::bfar_nav_cap( 'ai_settings' ) ) ) {
			wp_die(
				esc_html__( 'You do not have permission to perform this action.', 'real-time-auto-find-and-replace' ),
				'',
				array( 'response' => 403 )
			);
		}
	}

	public function handle_start() {
		$this->ensure_capability();

		$nonce = isset( $_GET['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ) : '';
		if ( ! wp_verify_nonce( $nonce, self::NONCE_ACTION ) ) {
			wp_die( esc_html__( 'Security check failed.', 'real-time-auto-find-and-replace' ) );
		}

		$slug = isset( $_GET['provider'] ) ? sanitize_key( $_GET['provider'] ) : '';
		$flow = Manager::flow( $slug );
		if ( ! $flow ) {
			wp_safe_redirect( $this->settings_page_url( array( 'oauth' => 'unsupported' ) ) );
			exit;
		}

		$callback = add_query_arg(
			array( 'action' => self::ACTION_CALLBACK ),
			admin_url( 'admin-post.php' )
		);

		$built = $flow->buildAuthUrl( $callback );
		if ( empty( $built['url'] ) || empty( $built['state'] ) ) {
			wp_safe_redirect( $this->settings_page_url( array( 'oauth' => 'error' ) ) );
			exit;
		}

		StateStore::put( $built['state'], $built['statePayload'] );

		wp_redirect( $built['url'] );
		exit;
	}

	public function handle_callback() {
		$this->ensure_capability();

		$state = isset( $_GET['state'] ) ? sanitize_text_field( wp_unslash( $_GET['state'] ) ) : '';
		$code  = isset( $_GET['code'] ) ? sanitize_text_field( wp_unslash( $_GET['code'] ) ) : '';

		if ( empty( $state ) || empty( $code ) ) {
			wp_safe_redirect( $this->settings_page_url( array( 'oauth' => 'cancelled' ) ) );
			exit;
		}

		$payload = StateStore::consume( $state );
		if ( ! $payload ) {
			wp_safe_redirect( $this->settings_page_url( array( 'oauth' => 'expired' ) ) );
			exit;
		}

		$slug = isset( $payload['slug'] ) ? $payload['slug'] : '';
		$flow = Manager::flow( $slug );
		if ( ! $flow ) {
			wp_safe_redirect( $this->settings_page_url( array( 'oauth' => 'unsupported' ) ) );
			exit;
		}

		$res = $flow->exchangeCode( $payload, $code );
		if ( empty( $res['status'] ) || empty( $res['api_key'] ) ) {
			wp_safe_redirect(
				$this->settings_page_url(
					array(
						'oauth'    => 'error',
						'provider' => $slug,
						'msg'      => rawurlencode( isset( $res['error'] ) ? $res['error'] : 'unknown' ),
					)
				)
			);
			exit;
		}

		Manager::saveCredential( $slug, $res['api_key'], isset( $res['meta'] ) ? $res['meta'] : array() );

		wp_safe_redirect(
			$this->settings_page_url(
				array(
					'oauth'    => 'success',
					'provider' => $slug,
				)
			)
		);
		exit;
	}

	public function handle_disconnect() {
		$this->ensure_capability();

		$nonce = isset( $_GET['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ) : '';
		if ( ! wp_verify_nonce( $nonce, self::NONCE_ACTION ) ) {
			wp_die( esc_html__( 'Security check failed.', 'real-time-auto-find-and-replace' ) );
		}

		$slug = isset( $_GET['provider'] ) ? sanitize_key( $_GET['provider'] ) : '';
		if ( $slug ) {
			Manager::disconnect( $slug );
		}

		wp_safe_redirect(
			$this->settings_page_url(
				array(
					'oauth'    => 'disconnected',
					'provider' => $slug,
				)
			)
		);
		exit;
	}
}
