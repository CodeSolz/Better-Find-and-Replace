<?php namespace RealTimeAutoFindReplace\admin\functions\ai\Providers;

use RealTimeAutoFindReplace\admin\functions\ai\Contracts\AiProviderInterface;

/**
 * Shared HTTP/error helpers for AI providers.
 *
 * @package AI
 * @since 1.9.0
 * @author M.Tuhin <info@codesolz.net>
 */

if ( ! defined( 'CS_RTAFAR_VERSION' ) ) {
	die();
}

abstract class AbstractProvider implements AiProviderInterface {

	protected $config = array();

	public function __construct( array $config = array() ) {
		$this->config = $config;
	}

	protected function cfg( $key, $default = '' ) {
		return isset( $this->config[ $key ] ) && $this->config[ $key ] !== '' ? $this->config[ $key ] : $default;
	}

	/** OAuth token if present, otherwise api_key. */
	protected function credential() {
		$token = $this->cfg( 'token', '' );
		if ( ! empty( $token ) ) {
			return $token;
		}
		return $this->cfg( 'api_key', '' );
	}

	protected function postJson( $url, array $headers, array $body, $timeout = 25 ) {
		$response = wp_remote_post(
			$url,
			array(
				'headers' => array_merge( array( 'Content-Type' => 'application/json' ), $headers ),
				'body'    => wp_json_encode( $body ),
				'timeout' => $timeout,
			)
		);

		return $this->normalizeResponse( $response );
	}

	protected function getJson( $url, array $headers = array(), $timeout = 15 ) {
		$response = wp_remote_get(
			$url,
			array(
				'headers' => $headers,
				'timeout' => $timeout,
			)
		);

		return $this->normalizeResponse( $response );
	}

	private function normalizeResponse( $response ) {
		if ( is_wp_error( $response ) ) {
			return array(
				'status' => false,
				'error'  => $response->get_error_message(),
			);
		}

		$code = wp_remote_retrieve_response_code( $response );
		$raw  = wp_remote_retrieve_body( $response );
		$body = json_decode( $raw, true );

		if ( $code < 200 || $code >= 300 ) {
			return array(
				'status'    => false,
				'http_code' => $code,
				'body'      => is_array( $body ) ? $body : array(),
				'raw'       => $raw,
				'error'     => $this->extractErrorMessage( $body, $code, $raw ),
			);
		}

		return array(
			'status'    => true,
			'http_code' => $code,
			'body'      => is_array( $body ) ? $body : array(),
			'raw'       => $raw,
		);
	}

	protected function extractErrorMessage( $body, $code, $raw ) {
		if ( is_array( $body ) ) {
			if ( isset( $body['error']['message'] ) ) {
				return (string) $body['error']['message'];
			}
			if ( isset( $body['error'] ) && is_string( $body['error'] ) ) {
				return $body['error'];
			}
			if ( isset( $body['message'] ) && is_string( $body['message'] ) ) {
				return $body['message'];
			}
			if ( isset( $body['detail'] ) ) {
				return is_string( $body['detail'] ) ? $body['detail'] : wp_json_encode( $body['detail'] );
			}
		}
		return sprintf( 'HTTP %d', $code );
	}

	public function listModels() {
		return array(
			'status' => false,
			'error'  => 'Model listing not supported for this provider; please type the model name manually.',
		);
	}
}
