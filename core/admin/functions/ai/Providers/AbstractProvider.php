<?php namespace RealTimeAutoFindReplace\admin\functions\ai\Providers;

use RealTimeAutoFindReplace\admin\functions\ai\Contracts\AiProviderInterface;
use RealTimeAutoFindReplace\admin\functions\ai\ProviderRegistry;

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

	/**
	 * Adds actionable guidance to a failed result when the configured model is
	 * one the provider has withdrawn.
	 *
	 * Deliberately reactive: a withdrawn id is never blocked before the call,
	 * because provider aliases sometimes keep resolving long after the docs stop
	 * listing them. A working setup is left alone; a failing one names the model
	 * to move to instead of showing a bare HTTP 404.
	 *
	 * @param array $res Result from getSuggestion() / testConnection().
	 * @return array
	 */
	protected function withModelHint( array $res ) {
		if ( ! empty( $res['status'] ) ) {
			return $res;
		}

		$model = $this->cfg( 'model', '' );
		if ( '' === $model ) {
			return $res;
		}

		$replacement = ProviderRegistry::replacementFor( $this->getSlug(), $model );
		if ( '' === $replacement ) {
			return $res;
		}

		$hint = sprintf(
			'The model "%1$s" is no longer offered by %2$s — switch to "%3$s" in AI Settings.',
			$model,
			$this->getName(),
			$replacement
		);

		foreach ( array( 'error', 'message' ) as $key ) {
			if ( isset( $res[ $key ] ) && '' !== $res[ $key ] ) {
				$res[ $key ] = rtrim( (string) $res[ $key ], ' .' ) . '. ' . $hint;
				return $res;
			}
		}

		$res['error'] = $hint;
		return $res;
	}

	/**
	 * A 2xx response that carried no usable text. Explains why, so the user can act.
	 *
	 * Thinking / reasoning models are the common cause: they spend the whole
	 * output budget on reasoning tokens and return an empty message.
	 *
	 * @param string $reason   Provider's machine reason (finish_reason, stop_reason, finishReason).
	 * @param string $fallback Message used when the reason is unrecognised.
	 * @return array
	 */
	protected function emptyResponseError( $reason, $fallback ) {
		switch ( strtolower( (string) $reason ) ) {
			case 'length':
			case 'max_tokens':
			case 'model_length':
				$message = 'The model used its whole output budget before returning any text. That usually means a reasoning model spent it all on thinking — pick a faster model (a Flash, Mini or Haiku variant) in AI Settings.';
				break;

			case 'content_filter':
			case 'safety':
			case 'prohibited_content':
			case 'blocklist':
			case 'recitation':
			case 'image_safety':
				$message = 'The provider blocked this text with its content filter. Try rephrasing the find text.';
				break;

			case 'refusal':
				$message = 'The model declined to answer this request.';
				break;

			default:
				$message = $fallback;
				break;
		}

		return array(
			'status' => false,
			'error'  => $message,
		);
	}
}
