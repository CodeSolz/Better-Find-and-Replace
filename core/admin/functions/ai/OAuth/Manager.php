<?php namespace RealTimeAutoFindReplace\admin\functions\ai\OAuth;

use RealTimeAutoFindReplace\admin\functions\ai\Settings as AiProviderSettings;

/**
 * OAuth orchestrator: slug → flow, start/callback, persist credential.
 *
 * @package AI
 * @since 1.9.0
 */

if ( ! defined( 'CS_RTAFAR_VERSION' ) ) {
	die();
}

class Manager {

	/** Filterable slug => FlowInterface FQN. */
	public static function flows() {
		return apply_filters(
			'rtafar_ai_oauth_flows',
			array(
				'openrouter' => '\\RealTimeAutoFindReplace\\admin\\functions\\ai\\OAuth\\OpenRouterFlow',
			)
		);
	}

	public static function supports( $slug ) {
		$flows = self::flows();
		return isset( $flows[ $slug ] ) && class_exists( $flows[ $slug ] );
	}

	public static function flow( $slug ) {
		$flows = self::flows();
		if ( ! isset( $flows[ $slug ] ) ) {
			return null;
		}
		$class = $flows[ $slug ];
		if ( ! class_exists( $class ) ) {
			return null;
		}
		$instance = new $class();
		return $instance instanceof FlowInterface ? $instance : null;
	}

	public static function saveCredential( $slug, $api_key, array $meta = array() ) {
		AiProviderSettings::saveProvider(
			$slug,
			array(
				'auth_type' => 'oauth',
				'api_key'   => $api_key,
				'token'     => $api_key,
			)
		);

		if ( ! empty( $meta ) ) {
			update_user_option( get_current_user_id(), 'rtafar_ai_oauth_meta_' . $slug, $meta );
		}
	}

	public static function disconnect( $slug ) {
		AiProviderSettings::saveProvider(
			$slug,
			array(
				'auth_type' => 'api_key',
				'api_key'   => '',
				'token'     => '',
			)
		);
		delete_user_option( get_current_user_id(), 'rtafar_ai_oauth_meta_' . $slug );
	}

	public static function meta( $slug ) {
		$meta = get_user_option( 'rtafar_ai_oauth_meta_' . $slug, get_current_user_id() );
		return is_array( $meta ) ? $meta : array();
	}
}
