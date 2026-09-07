<?php namespace RealTimeAutoFindReplace\admin\functions\ai\Providers;

/**
 * Google Gemini — :generateContent endpoint, API key as query param.
 *
 * @package AI
 * @since 1.9.0
 */

if ( ! defined( 'CS_RTAFAR_VERSION' ) ) {
	die();
}

class Gemini extends AbstractProvider {

	const BASE_URL = 'https://generativelanguage.googleapis.com/v1beta';

	/**
	 * Output budget for one suggestion.
	 *
	 * Gemini 2.5 and the 3.x line think before answering, and those thinking
	 * tokens come out of maxOutputTokens — the previous 200 could be consumed
	 * entirely, returning a candidate with finishReason MAX_TOKENS and no parts.
	 *
	 * thinkingConfig is deliberately not sent: the Flash models accept a zero
	 * budget but the Pro models reject it, so an explicit value would trade one
	 * failure for another. A larger ceiling works everywhere and unused budget
	 * is not billed.
	 */
	const MAX_OUTPUT_TOKENS = 1024;

	public function getSlug() {
		return 'gemini';
	}

	public function getName() {
		return 'Google Gemini';
	}

	private function buildUrl( $path ) {
		$url = self::BASE_URL . $path;
		$key = $this->credential();
		if ( ! empty( $key ) ) {
			$url = add_query_arg( 'key', $key, $url );
		}
		return $url;
	}

	public function getSuggestion( $text, $systemPrompt, $userPromptFormat ) {
		if ( empty( $this->credential() ) ) {
			return array(
				'status' => false,
				'error'  => 'Gemini API key is not set.',
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
			'systemInstruction' => array(
				'parts' => array(
					array( 'text' => $systemPrompt ),
				),
			),
			'contents'          => array(
				array(
					'role'  => 'user',
					'parts' => array(
						array( 'text' => sprintf( $userPromptFormat, $text ) ),
					),
				),
			),
			'generationConfig'  => array(
				'temperature'     => 0.7,
				'maxOutputTokens' => self::MAX_OUTPUT_TOKENS,
			),
		);

		$res = $this->postJson(
			$this->buildUrl( '/models/' . rawurlencode( $model ) . ':generateContent' ),
			array(),
			$body
		);

		if ( ! $res['status'] ) {
			return $this->withModelHint( $res );
		}

		$content = '';
		if ( isset( $res['body']['candidates'][0]['content']['parts'] ) ) {
			foreach ( $res['body']['candidates'][0]['content']['parts'] as $part ) {
				if ( isset( $part['text'] ) ) {
					$content .= $part['text'];
				}
			}
		}

		if ( trim( $content ) === '' ) {
			// A prompt rejected before generation reports itself here, not on the candidate.
			if ( ! empty( $res['body']['promptFeedback']['blockReason'] ) ) {
				return $this->withModelHint(
					$this->emptyResponseError(
						$res['body']['promptFeedback']['blockReason'],
						'Gemini blocked the prompt. Try rephrasing the find text.'
					)
				);
			}

			$reason = isset( $res['body']['candidates'][0]['finishReason'] )
				? $res['body']['candidates'][0]['finishReason']
				: '';

			return $this->withModelHint(
				$this->emptyResponseError( $reason, 'Empty response from Gemini. The model returned no text — try another model.' )
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

		$res = $this->getJson( $this->buildUrl( '/models' ) );

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

		$res = $this->getJson( $this->buildUrl( '/models' ) );

		if ( ! $res['status'] ) {
			return array(
				'status' => false,
				'error'  => isset( $res['error'] ) ? $res['error'] : 'Failed to fetch models.',
			);
		}

		$models = array();
		if ( isset( $res['body']['models'] ) && is_array( $res['body']['models'] ) ) {
			foreach ( $res['body']['models'] as $m ) {
				if ( ! isset( $m['name'] ) ) {
					continue;
				}
				// Only models supporting generateContent.
				if ( isset( $m['supportedGenerationMethods'] ) && is_array( $m['supportedGenerationMethods'] )
					&& ! in_array( 'generateContent', $m['supportedGenerationMethods'], true ) ) {
					continue;
				}
				$id    = preg_replace( '#^models/#', '', $m['name'] );
				$label = isset( $m['displayName'] ) ? $m['displayName'] : $id;
				$models[ $id ] = $label;
			}
		}

		return array(
			'status' => true,
			'models' => $models,
		);
	}
}
