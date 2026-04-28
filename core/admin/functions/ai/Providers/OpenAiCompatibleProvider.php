<?php namespace RealTimeAutoFindReplace\admin\functions\ai\Providers;

/**
 * Base for providers that speak the OpenAI chat-completions schema.
 * Used by OpenAI, Groq, Mistral, OpenRouter, DeepSeek, xAI, and Ollama.
 *
 * @package AI
 * @since 1.9.0
 * @author M.Tuhin <info@codesolz.net>
 */

if ( ! defined( 'CS_RTAFAR_VERSION' ) ) {
	die();
}

abstract class OpenAiCompatibleProvider extends AbstractProvider {

	protected $baseUrl            = '';
	protected $requiresCredential = true;
	protected $chatPath           = '/chat/completions';
	protected $modelsPath         = '/models';

	/** Config override beats class default. */
	protected function resolveBaseUrl() {
		$configured = $this->cfg( 'base_url', '' );
		if ( ! empty( $configured ) ) {
			return rtrim( $configured, '/' );
		}
		return rtrim( $this->baseUrl, '/' );
	}

	protected function authHeaders() {
		$cred = $this->credential();
		if ( empty( $cred ) ) {
			return array();
		}
		return array( 'Authorization' => 'Bearer ' . $cred );
	}

	public function getSuggestion( $text, $systemPrompt, $userPromptFormat ) {
		if ( $this->requiresCredential && empty( $this->credential() ) ) {
			return array(
				'status' => false,
				'error'  => sprintf( 'Credentials are not set for %s.', $this->getName() ),
			);
		}

		$model = $this->cfg( 'model', '' );
		if ( empty( $model ) ) {
			return array(
				'status' => false,
				'error'  => 'No model selected.',
			);
		}

		$body = array(
			'model'       => $model,
			'messages'    => array(
				array(
					'role'    => 'system',
					'content' => $systemPrompt,
				),
				array(
					'role'    => 'user',
					'content' => sprintf( $userPromptFormat, $text ),
				),
			),
			'temperature' => 0.7,
			'max_tokens'  => 120,
		);

		$res = $this->postJson(
			$this->resolveBaseUrl() . $this->chatPath,
			$this->authHeaders(),
			$body
		);

		if ( ! $res['status'] ) {
			return $res;
		}

		$content = isset( $res['body']['choices'][0]['message']['content'] )
			? $res['body']['choices'][0]['message']['content']
			: '';

		if ( $content === '' ) {
			return array(
				'status' => false,
				'error'  => 'Empty response from provider.',
			);
		}

		return array(
			'status'     => true,
			'suggestion' => trim( $content, " \"'\n\r\t" ),
		);
	}

	public function testConnection() {
		if ( $this->requiresCredential && empty( $this->credential() ) ) {
			return array(
				'status'  => false,
				'message' => 'Missing credential.',
			);
		}

		$res = $this->getJson(
			$this->resolveBaseUrl() . $this->modelsPath,
			$this->authHeaders()
		);

		if ( $res['status'] ) {
			return array(
				'status'  => true,
				'message' => 'Connection successful.',
			);
		}

		return array(
			'status'  => false,
			'message' => isset( $res['error'] ) ? $res['error'] : 'Connection failed.',
		);
	}

	public function listModels() {
		if ( $this->requiresCredential && empty( $this->credential() ) ) {
			return array(
				'status' => false,
				'error'  => 'Missing credential.',
			);
		}

		$res = $this->getJson(
			$this->resolveBaseUrl() . $this->modelsPath,
			$this->authHeaders()
		);

		if ( ! $res['status'] ) {
			return array(
				'status' => false,
				'error'  => isset( $res['error'] ) ? $res['error'] : 'Failed to fetch models.',
			);
		}

		$models = array();
		$items  = array();
		if ( isset( $res['body']['data'] ) && is_array( $res['body']['data'] ) ) {
			$items = $res['body']['data'];
		} elseif ( isset( $res['body']['models'] ) && is_array( $res['body']['models'] ) ) {
			$items = $res['body']['models'];
		} elseif ( is_array( $res['body'] ) && isset( $res['body'][0] ) ) {
			$items = $res['body'];
		}

		foreach ( $items as $item ) {
			if ( is_array( $item ) ) {
				$id = isset( $item['id'] ) ? $item['id']
					: ( isset( $item['name'] ) ? $item['name']
					: ( isset( $item['model'] ) ? $item['model'] : '' ) );
				if ( $id !== '' ) {
					$models[ $id ] = $id;
				}
			} elseif ( is_string( $item ) ) {
				$models[ $item ] = $item;
			}
		}

		return array(
			'status' => true,
			'models' => $models,
		);
	}
}
