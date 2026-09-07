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

	/**
	 * Name of the output-limit parameter. OpenAI deprecated `max_tokens` in
	 * favour of `max_completion_tokens` and rejects the old name on its
	 * reasoning models; every other OpenAI-compatible vendor here still takes
	 * `max_tokens`, so the name is per-provider rather than global.
	 *
	 * @var string
	 */
	protected $maxTokensParam = 'max_tokens';

	/**
	 * Whether to send `temperature`. Reasoning models reject any value other
	 * than the default, so providers whose current line-up is reasoning-only
	 * turn this off and inherit the server-side default.
	 *
	 * @var bool
	 */
	protected $sendTemperature = true;

	/**
	 * Output budget for a suggestion.
	 *
	 * A rewritten phrase is a few dozen tokens, but on a thinking model the
	 * reasoning tokens come out of this same budget — too small a value returns
	 * an empty message instead of an answer. Providers whose models reason
	 * heavily raise it further.
	 *
	 * @var int
	 */
	protected $maxOutputTokens = 1024;

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
			'model'    => $model,
			'messages' => array(
				array(
					'role'    => 'system',
					'content' => $systemPrompt,
				),
				array(
					'role'    => 'user',
					'content' => sprintf( $userPromptFormat, $text ),
				),
			),
		);

		$body[ $this->maxTokensParam ] = $this->maxOutputTokens;

		if ( $this->sendTemperature ) {
			$body['temperature'] = 0.7;
		}

		$res = $this->postJson(
			$this->resolveBaseUrl() . $this->chatPath,
			$this->authHeaders(),
			$body
		);

		if ( ! $res['status'] ) {
			return $this->withModelHint( $res );
		}

		$choice  = isset( $res['body']['choices'][0] ) ? $res['body']['choices'][0] : array();
		$content = isset( $choice['message']['content'] ) && is_string( $choice['message']['content'] )
			? $choice['message']['content']
			: '';

		if ( trim( $content ) === '' ) {
			$reason = isset( $choice['finish_reason'] ) ? $choice['finish_reason'] : '';

			return $this->withModelHint(
				$this->emptyResponseError(
					$reason,
					sprintf( 'Empty response from %s. The model returned no text — try another model.', $this->getName() )
				)
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
