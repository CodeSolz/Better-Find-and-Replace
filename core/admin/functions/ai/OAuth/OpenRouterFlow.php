<?php namespace RealTimeAutoFindReplace\admin\functions\ai\OAuth;

/**
 * OpenRouter PKCE flow — https://openrouter.ai/docs/use-cases/oauth-pkce
 *
 * @package AI
 * @since 1.9.0
 */

if ( ! defined( 'CS_RTAFAR_VERSION' ) ) {
	die();
}

class OpenRouterFlow implements FlowInterface {

	const AUTH_URL     = 'https://openrouter.ai/auth';
	const KEY_EXCHANGE = 'https://openrouter.ai/api/v1/auth/keys';

	public function slug() {
		return 'openrouter';
	}

	public function buildAuthUrl( $callbackUrl ) {
		$verifier  = Pkce::verifier();
		$challenge = Pkce::challenge( $verifier );
		$state     = Pkce::state( 16 );

		$url = add_query_arg(
			array(
				'callback_url'          => urlencode( $callbackUrl ),
				'code_challenge'        => $challenge,
				'code_challenge_method' => 'S256',
				'state'                 => $state,
			),
			self::AUTH_URL
		);

		return array(
			'url'          => $url,
			'state'        => $state,
			'statePayload' => array(
				'verifier' => $verifier,
				'slug'     => $this->slug(),
			),
		);
	}

	public function exchangeCode( array $statePayload, $code ) {
		if ( empty( $statePayload['verifier'] ) ) {
			return array(
				'status' => false,
				'error'  => 'Missing PKCE verifier.',
			);
		}

		$response = wp_remote_post(
			self::KEY_EXCHANGE,
			array(
				'headers' => array(
					'Content-Type' => 'application/json',
				),
				'body'    => wp_json_encode(
					array(
						'code'                  => $code,
						'code_verifier'         => $statePayload['verifier'],
						'code_challenge_method' => 'S256',
					)
				),
				'timeout' => 20,
			)
		);

		if ( is_wp_error( $response ) ) {
			return array(
				'status' => false,
				'error'  => $response->get_error_message(),
			);
		}

		$code_resp = wp_remote_retrieve_response_code( $response );
		$body      = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( $code_resp < 200 || $code_resp >= 300 || empty( $body['key'] ) ) {
			$msg = is_array( $body ) && isset( $body['error']['message'] )
				? $body['error']['message']
				: ( isset( $body['message'] ) ? $body['message'] : 'OpenRouter rejected the code.' );
			return array(
				'status' => false,
				'error'  => 'OAuth exchange failed: ' . $msg . ' (HTTP ' . $code_resp . ')',
			);
		}

		return array(
			'status'  => true,
			'api_key' => $body['key'],
			'meta'    => array(
				'user_id' => isset( $body['user_id'] ) ? $body['user_id'] : '',
			),
		);
	}
}
