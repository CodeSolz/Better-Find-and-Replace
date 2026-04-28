<?php namespace RealTimeAutoFindReplace\admin\functions\ai\Providers;

if ( ! defined( 'CS_RTAFAR_VERSION' ) ) {
	die();
}

class OpenRouter extends OpenAiCompatibleProvider {
	protected $baseUrl = 'https://openrouter.ai/api/v1';

	public function getSlug() {
		return 'openrouter';
	}

	public function getName() {
		return 'OpenRouter';
	}

	protected function authHeaders() {
		$headers = parent::authHeaders();
		$headers['HTTP-Referer'] = home_url( '/' );
		$headers['X-Title']      = get_bloginfo( 'name' );
		return $headers;
	}
}
