<?php namespace RealTimeAutoFindReplace\admin\functions;



/**
 * Class: DB replacer
 *
 * @package Admin
 * @since 1.3.1
 * @author M.Tuhin <info@codesolz.net>
 */

if ( ! defined( 'CS_RTAFAR_VERSION' ) ) {
	die();
}

use RealTimeAutoFindReplace\lib\Util;

class aiHandler {

	/**
	 * Get AI settings
	 *
	 * @return array
	 */
	private static $optionKey = 'cs_ai_config';
	private static $openAiEndpoint = 'https://api.openai.com/v1/chat/completions';

	/**
	 * Get AI settings
	 *
	 * @return array
	 */
	public static function getSettings() {
		$settings = get_option( self::$optionKey, array() );

		if ( ! empty( $settings ) ) {
			return $settings;
		}

		return array();
	}

	/**
	 * Save AI settings
	 *
	 * @param array $data
	 */
	public static function saveSettings( $data ) {
		if ( ! empty( $data['cs_ai_config'] ) ) {
			$settings = array(
				'api_key' => sanitize_text_field( $data['cs_ai_config']['api_key'] ),
				'language_model' => sanitize_text_field( $data['cs_ai_config']['language_model'] ),
			);

			// Check if the user has permission to save settings
			if ( !current_user_can( 'manage_options' ) && !current_user_can( Util::bfar_nav_cap('replace_in_db') ) ) {
				return wp_send_json(
					array(
						'status' => false,
						'title'  => __( 'Access Denied', 'real-time-auto-find-and-replace' ),
						'text'   => __( 'You do not have permission to perform this action.', 'real-time-auto-find-and-replace' ),
					)
				);
			}

			update_option( self::$optionKey, $settings );
			return wp_send_json(
				array(
					'status' => true,
					'title'  => __( 'Success', 'real-time-auto-find-and-replace' ),
					'text'   => __( 'Settings saved successfully.', 'real-time-auto-find-and-replace' ),
				)
			);
			
		}

		return wp_send_json(
			array(
				'status' => false,
				'title'  => __( 'Error', 'real-time-auto-find-and-replace' ),
				'text'   => __( 'Failed to save settings.', 'real-time-auto-find-and-replace' ),
			)
		);
	}
	
	/**
	 * Get AI suggestion
	 *
	 * @param array $userInput
	 * @return array
	 */
	public function getAiSuggestion( $userInput ){
		$AISettings = self::getSettings();
		if ( empty( $AISettings ) || empty( $AISettings['api_key'] ) ) {
			return wp_send_json( array(
				'status' => false,
				'title'  => __( 'Error', 'real-time-auto-find-and-replace' ),
				'text'   => __( 'API key is not set.', 'real-time-auto-find-and-replace' ),
			));
		}

		$text = Util::check_evil_script( $userInput['find'] );
		if ( empty( $text ) ) {
			return wp_send_json( array(
				'status' => false,
				'title'  => __( 'Error', 'real-time-auto-find-and-replace' ),
				'text'   => __( 'Please enter find text to get suggestion.', 'real-time-auto-find-and-replace' ),
			));
		}

		$body = [
			'model' => $AISettings['language_model'],
			'messages' => [
				['role' => 'system', 'content' => 'You are a helpful assistant that rewrites text to be more persuasive and SEO-friendly.'],
				['role' => 'user', 'content' => "Rewrite this phrase for a website: \"$text\""]
			],
			'temperature' => 0.7,
			'max_tokens' => 60
		];

		$response = wp_remote_post( self::$openAiEndpoint , [
			'headers' => [
				'Authorization' => 'Bearer ' . $AISettings['api_key'],
				'Content-Type'  => 'application/json',
			],
			'body'    => json_encode($body),
			'timeout' => 20,
		]);

		$response_code = wp_remote_retrieve_response_code( $response );

		if ( $response_code !== 200 ) {
			$body = wp_remote_retrieve_body( $response );
			$data = json_decode( $body, true );

			// pre_print( $data );

			if ( isset( $data['error']['message'] ) ) {

				return wp_send_json( array(
					'status' => false,
					'title'  => __( 'API Error', 'real-time-auto-find-and-replace' ),
					'text'   => esc_html( $data['error']['message'] ),
				));
			} else {
				return wp_send_json(array(
					'status' => false,
					'title'  => __( 'API Error', 'real-time-auto-find-and-replace' ),
					'text'   => esc_html( 'API returned HTTP ' . $response_code ),
				));
			}
		}

		$body = json_decode(wp_remote_retrieve_body($response), true);

		if (!isset($body['choices'][0]['message']['content'])) {
			return wp_send_json( array(
				'status' => false,
				'title'  => __( 'AI Error', 'real-time-auto-find-and-replace' ),
				'text'   => __( 'Invalid AI response.', 'real-time-auto-find-and-replace' ),
			));
		}

		return wp_send_json( array(
				'status' => true,
				'title'  => __( 'Applied', 'real-time-auto-find-and-replace' ),
				'text'   => __( 'The replacement text has been updated.', 'real-time-auto-find-and-replace' ),
				'suggestion' => trim($body['choices'][0]['message']['content'], '"')
		));
	}

}


?>