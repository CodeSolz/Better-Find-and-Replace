<?php namespace RealTimeAutoFindReplace\admin\functions\ai\Providers;

if ( ! defined( 'CS_RTAFAR_VERSION' ) ) {
	die();
}

/** Local server, no credential, base_url overridable. */
class Ollama extends OpenAiCompatibleProvider {
	protected $baseUrl            = 'http://localhost:11434/v1';
	protected $requiresCredential = false;

	public function getSlug() {
		return 'ollama';
	}

	public function getName() {
		return 'Ollama';
	}

	protected function authHeaders() {
		return array();
	}

	public function listModels() {
		// /api/tags returns a richer list than /v1/models.
		$base = $this->resolveBaseUrl();
		$root = preg_replace( '#/v1/?$#', '', $base );
		$res  = $this->getJson( $root . '/api/tags' );

		if ( ! $res['status'] ) {
			return parent::listModels();
		}

		$models = array();
		if ( isset( $res['body']['models'] ) && is_array( $res['body']['models'] ) ) {
			foreach ( $res['body']['models'] as $m ) {
				if ( isset( $m['name'] ) ) {
					$models[ $m['name'] ] = $m['name'];
				}
			}
		}

		if ( empty( $models ) ) {
			return array(
				'status' => false,
				'error'  => 'No models installed in Ollama. Run "ollama pull llama3.2" first.',
			);
		}

		return array(
			'status' => true,
			'models' => $models,
		);
	}
}
