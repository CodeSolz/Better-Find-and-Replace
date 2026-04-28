<?php namespace RealTimeAutoFindReplace\admin\functions\ai\Providers;

if ( ! defined( 'CS_RTAFAR_VERSION' ) ) {
	die();
}

class OpenAi extends OpenAiCompatibleProvider {
	protected $baseUrl = 'https://api.openai.com/v1';

	public function getSlug() {
		return 'openai';
	}

	public function getName() {
		return 'OpenAI';
	}
}
