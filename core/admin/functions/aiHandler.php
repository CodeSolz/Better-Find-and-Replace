<?php namespace RealTimeAutoFindReplace\admin\functions;

/**
 * AI Handler — facade over the multi-provider AI subsystem.
 *
 * @package Admin
 * @since 1.3.1
 * @author M.Tuhin <info@codesolz.net>
 */

if ( ! defined( 'CS_RTAFAR_VERSION' ) ) {
	die();
}

use RealTimeAutoFindReplace\lib\Util;
use RealTimeAutoFindReplace\admin\functions\ai\Settings as AiSettings;
use RealTimeAutoFindReplace\admin\functions\ai\ProviderRegistry;
use RealTimeAutoFindReplace\admin\functions\ai\ProviderFactory;
use RealTimeAutoFindReplace\admin\functions\ai\PromptTemplates;

class aiHandler {

	private static function userCanWrite() {
		return current_user_can( 'manage_options' )
			|| current_user_can( Util::bfar_nav_cap( 'replace_in_db' ) )
			|| current_user_can( Util::bfar_nav_cap( 'ai_settings' ) );
	}

	public static function getSettings() {
		return AiSettings::get();
	}

	/** Falls back to the legacy { api_key, language_model } shape if posted. */
	public static function saveSettings( $data ) {
		if ( ! self::userCanWrite() ) {
			return wp_send_json(
				array(
					'status' => false,
					'title'  => __( 'Access Denied', 'real-time-auto-find-and-replace' ),
					'text'   => __( 'You do not have permission to perform this action.', 'real-time-auto-find-and-replace' ),
				)
			);
		}

		if ( empty( $data['cs_ai_config'] ) || ! is_array( $data['cs_ai_config'] ) ) {
			return wp_send_json(
				array(
					'status' => false,
					'title'  => __( 'Error', 'real-time-auto-find-and-replace' ),
					'text'   => __( 'No settings provided.', 'real-time-auto-find-and-replace' ),
				)
			);
		}

		$payload = $data['cs_ai_config'];

		// Legacy shape → OpenAI provider slot.
		if ( ! isset( $payload['providers'] ) && isset( $payload['api_key'] ) ) {
			$payload = array(
				'active_provider' => 'openai',
				'providers'       => array(
					'openai' => array(
						'auth_type' => 'api_key',
						'api_key'   => $payload['api_key'],
						'model'     => isset( $payload['language_model'] ) ? $payload['language_model'] : 'gpt-4o-mini',
					),
				),
			);
		}

		AiSettings::save( $payload );

		return wp_send_json(
			array(
				'status' => true,
				'title'  => __( 'Success', 'real-time-auto-find-and-replace' ),
				'text'   => __( 'Settings saved successfully.', 'real-time-auto-find-and-replace' ),
			)
		);
	}

	public static function testConnection( $data ) {
		if ( ! self::userCanWrite() ) {
			return wp_send_json(
				array(
					'status' => false,
					'title'  => __( 'Access Denied', 'real-time-auto-find-and-replace' ),
					'text'   => __( 'You do not have permission to perform this action.', 'real-time-auto-find-and-replace' ),
				)
			);
		}

		$slug = isset( $data['provider'] ) ? sanitize_key( $data['provider'] ) : '';
		if ( ! $slug || ! ProviderRegistry::get( $slug ) ) {
			return wp_send_json(
				array(
					'status' => false,
					'title'  => __( 'Error', 'real-time-auto-find-and-replace' ),
					'text'   => __( 'Unknown provider.', 'real-time-auto-find-and-replace' ),
				)
			);
		}

		$config = AiSettings::getProviderConfig( $slug );

		// Live overrides from the form (no save needed first).
		foreach ( array( 'api_key', 'model', 'base_url' ) as $field ) {
			if ( isset( $data[ $field ] ) && $data[ $field ] !== '' ) {
				$config[ $field ] = sanitize_text_field( $data[ $field ] );
			}
		}

		$provider = ProviderFactory::make( $slug, $config );
		if ( ! $provider ) {
			return wp_send_json(
				array(
					'status' => false,
					'title'  => __( 'Error', 'real-time-auto-find-and-replace' ),
					'text'   => __( 'Provider not available.', 'real-time-auto-find-and-replace' ),
				)
			);
		}

		$res = $provider->testConnection();

		return wp_send_json(
			array(
				'status' => ! empty( $res['status'] ),
				'title'  => ! empty( $res['status'] )
					? __( 'Connected', 'real-time-auto-find-and-replace' )
					: __( 'Connection failed', 'real-time-auto-find-and-replace' ),
				'text'   => isset( $res['message'] ) ? $res['message'] : '',
			)
		);
	}

	public static function listModels( $data ) {
		if ( ! self::userCanWrite() ) {
			return wp_send_json(
				array(
					'status' => false,
					'title'  => __( 'Access Denied', 'real-time-auto-find-and-replace' ),
					'text'   => __( 'You do not have permission to perform this action.', 'real-time-auto-find-and-replace' ),
				)
			);
		}

		$slug = isset( $data['provider'] ) ? sanitize_key( $data['provider'] ) : '';
		if ( ! $slug || ! ProviderRegistry::get( $slug ) ) {
			return wp_send_json(
				array(
					'status' => false,
					'title'  => __( 'Error', 'real-time-auto-find-and-replace' ),
					'text'   => __( 'Unknown provider.', 'real-time-auto-find-and-replace' ),
				)
			);
		}

		$config = AiSettings::getProviderConfig( $slug );
		foreach ( array( 'api_key', 'base_url' ) as $field ) {
			if ( isset( $data[ $field ] ) && $data[ $field ] !== '' ) {
				$config[ $field ] = sanitize_text_field( $data[ $field ] );
			}
		}

		$provider = ProviderFactory::make( $slug, $config );
		if ( ! $provider ) {
			return wp_send_json(
				array(
					'status' => false,
					'title'  => __( 'Error', 'real-time-auto-find-and-replace' ),
					'text'   => __( 'Provider not available.', 'real-time-auto-find-and-replace' ),
				)
			);
		}

		$res = $provider->listModels();

		return wp_send_json(
			array(
				'status' => ! empty( $res['status'] ),
				'title'  => ! empty( $res['status'] )
					? __( 'Models loaded', 'real-time-auto-find-and-replace' )
					: __( 'Could not fetch models', 'real-time-auto-find-and-replace' ),
				'text'   => isset( $res['error'] ) ? $res['error'] : '',
				'models' => isset( $res['models'] ) ? $res['models'] : array(),
			)
		);
	}

	public function getAiSuggestion( $userInput ) {
		$settings = AiSettings::get();
		$slug     = $settings['active_provider'];

		$registry = ProviderRegistry::get( $slug );
		if ( ! $registry ) {
			return wp_send_json(
				array(
					'status' => false,
					'title'  => __( 'Error', 'real-time-auto-find-and-replace' ),
					'text'   => __( 'No AI provider configured.', 'real-time-auto-find-and-replace' ),
				)
			);
		}

		$config   = AiSettings::getProviderConfig( $slug );
		$provider = ProviderFactory::make( $slug, $config, $registry );
		if ( ! $provider ) {
			return wp_send_json(
				array(
					'status' => false,
					'title'  => __( 'Error', 'real-time-auto-find-and-replace' ),
					'text'   => __( 'AI provider could not be initialized.', 'real-time-auto-find-and-replace' ),
				)
			);
		}

		$text = Util::check_evil_script( $userInput['find'] );
		if ( empty( $text ) ) {
			return wp_send_json(
				array(
					'status' => false,
					'title'  => __( 'Error', 'real-time-auto-find-and-replace' ),
					'text'   => __( 'Please enter find text to get suggestion.', 'real-time-auto-find-and-replace' ),
				)
			);
		}

		$prompts = PromptTemplates::resolve( $settings );
		$res     = $provider->getSuggestion( $text, $prompts['system_prompt'], $prompts['user_format'] );

		if ( empty( $res['status'] ) ) {
			$message = isset( $res['error'] ) ? $res['error']
				: __( 'API returned an error.', 'real-time-auto-find-and-replace' );
			return wp_send_json(
				array(
					'status' => false,
					'title'  => __( 'AI Error', 'real-time-auto-find-and-replace' ),
					'text'   => esc_html( $message ),
				)
			);
		}

		return wp_send_json(
			array(
				'status'     => true,
				'title'      => __( 'Applied', 'real-time-auto-find-and-replace' ),
				'text'       => __( 'The replacement text has been updated.', 'real-time-auto-find-and-replace' ),
				'suggestion' => $res['suggestion'],
			)
		);
	}
}
