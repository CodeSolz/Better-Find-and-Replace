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
				'max_tokens' => 200,
				'messages'   => array(
					array(
						'role'    => 'user',
						'content' => sprintf( $userPromptFormat, $text ),
					),
				),
			)
		);

		if ( ! $res['status'] ) {
			return $res;
		}

		$content = '';
		if ( isset( $res['body']['content'] ) && is_array( $res['body']['content'] ) ) {
			foreach ( $res['body']['content'] as $block ) {
				if ( isset( $block['type'] ) && $block['type'] === 'text' && isset( $block['text'] ) ) {
					$content .= $block['text'];
				}
			}
		}

		if ( $content === '' ) {
			return array(
				'status' => false,
				'error'  => 'Empty response from Anthropic.',
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
