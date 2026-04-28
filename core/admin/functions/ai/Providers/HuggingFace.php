<?php namespace RealTimeAutoFindReplace\admin\functions\ai\Providers;

if ( ! defined( 'CS_RTAFAR_VERSION' ) ) {
	die();
}

/** OpenAI-compatible router; /models listing isn't exposed there. */
class HuggingFace extends OpenAiCompatibleProvider {
	protected $baseUrl = 'https://router.huggingface.co/v1';

	public function getSlug() {
		return 'huggingface';
	}

	public function getName() {
		return 'Hugging Face';
	}

	public function listModels() {
		return array(
			'status' => false,
			'error'  => 'Hugging Face has too many models to list. Pick one from the static list or paste a model ID like "meta-llama/Llama-3.1-8B-Instruct".',
		);
	}

	public function testConnection() {
		if ( empty( $this->credential() ) ) {
			return array(
				'status'  => false,
				'message' => 'Missing token.',
			);
		}

		// /models isn't exposed — tiny chat ping instead.
		$model = $this->cfg( 'model', '' );
		if ( empty( $model ) ) {
			return array(
				'status'  => false,
				'message' => 'Select a model first.',
			);
		}

		$res = $this->postJson(
			$this->resolveBaseUrl() . '/chat/completions',
			$this->authHeaders(),
			array(
				'model'      => $model,
				'messages'   => array(
					array(
						'role'    => 'user',
						'content' => 'ping',
					),
				),
				'max_tokens' => 1,
			)
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
}
