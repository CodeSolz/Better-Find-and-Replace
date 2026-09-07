<?php namespace RealTimeAutoFindReplace\admin\functions\ai\Providers;

/**
 * Anthropic Claude — Messages API with x-api-key + anthropic-version headers.
 *
 * @package AI
 * @since 1.9.0
 */

if ( ! defined( 'CS_RTAFAR_VERSION' ) ) {
	die();
}

class Anthropic extends AbstractProvider {

	const BASE_URL          = 'https://api.anthropic.com/v1';
	const ANTHROPIC_VERSION = '2023-06-01';

	/**
	 * Output budget for one suggestion.
	 *
	 * The current Claude models (Opus 5, Sonnet 5 and above) run adaptive
	 * thinking by default, and those thinking tokens are drawn from max_tokens
	 * before any text block is produced. The old 200-token budget could be spent
	 * entirely on thinking, which surfaced as "Empty response from Anthropic".
	 *
	 * No `thinking` parameter is sent: the shapes differ per model generation
	 * (and Fable-class models reject an explicit one), so letting each model use
	 * its own default and paying for a larger ceiling is the portable choice.
	 * Unused budget is not billed.
	 */
	const MAX_TOKENS = 1024;

	public function getSlug() {
		return 'anthropic';
	}

	public function getName() {
		return 'Anthropic Claude';
	}

	private function authHeaders() {
		return array(
			'x-api-key'         => $this->credential(),
			'anthropic-version' => self::ANTHROPIC_VERSION,
		);
	}

	public function getSuggestion( $text, $systemPrompt, $userPromptFormat ) {
		if ( empty( $this->credential() ) ) {
			return array(
				'status' => false,
				'error'  => 'Anthropic API key is not set.',
			);
		}

		$model = $this->cfg( 'model', '' );
		if ( empty( $model ) ) {
			return array(
				'status' => false,
				'error'  => 'No model selected.',
			);
		}

		$res = $this->postJson(
			self::BASE_URL . '/messages',
			$this->authHeaders(),
			array(
				'model'      => $model,
				'system'     => $systemPrompt,
				'max_tokens' => self::MAX_TOKENS,
				'messages'   => array(
					array(
						'role'    => 'user',
						'content' => sprintf( $userPromptFormat, $text ),
					),
				),
			)
		);

		if ( ! $res['status'] ) {
			return $this->withModelHint( $res );
		}

		// Only text blocks carry the answer; thinking blocks are skipped.
		$content = '';
		if ( isset( $res['body']['content'] ) && is_array( $res['body']['content'] ) ) {
			foreach ( $res['body']['content'] as $block ) {
				if ( isset( $block['type'] ) && $block['type'] === 'text' && isset( $block['text'] ) ) {
					$content .= $block['text'];
				}
			}
		}

		if ( trim( $content ) === '' ) {
			$reason = isset( $res['body']['stop_reason'] ) ? $res['body']['stop_reason'] : '';

			// A refusal carries a category worth showing.
			if ( 'refusal' === $reason && ! empty( $res['body']['stop_details']['category'] ) ) {
				return $this->withModelHint(
					array(
						'status' => false,
						'error'  => sprintf(
							'Claude declined to answer this request (%s).',
							sanitize_text_field( $res['body']['stop_details']['category'] )
						),
					)
				);
			}

			return $this->withModelHint(
				$this->emptyResponseError( $reason, 'Empty response from Anthropic. The model returned no text — try another model.' )
			);
		}

		return array(
			'status'     => true,
			'suggestion' => trim( $content, " \"'\n\r\t" ),
		);
	}

	public function testConnection() {
		if ( empty( $this->credential() ) ) {
			return array(
				'status'  => false,
				'message' => 'Missing API key.',
			);
		}

		$res = $this->getJson(
			self::BASE_URL . '/models',
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
		if ( empty( $this->credential() ) ) {
			return array(
				'status' => false,
				'error'  => 'Missing API key.',
			);
		}

		$res = $this->getJson(
			self::BASE_URL . '/models',
			$this->authHeaders()
		);

		if ( ! $res['status'] ) {
			return array(
				'status' => false,
				'error'  => isset( $res['error'] ) ? $res['error'] : 'Failed to fetch models.',
			);
		}

		$models = array();
		if ( isset( $res['body']['data'] ) && is_array( $res['body']['data'] ) ) {
			foreach ( $res['body']['data'] as $m ) {
				if ( isset( $m['id'] ) ) {
					$label = isset( $m['display_name'] ) ? $m['display_name'] : $m['id'];
					$models[ $m['id'] ] = $label;
				}
			}
		}

		return array(
			'status' => true,
			'models' => $models,
		);
	}
}
