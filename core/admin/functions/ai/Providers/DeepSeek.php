<?php namespace RealTimeAutoFindReplace\admin\functions\ai\Providers;

if ( ! defined( 'CS_RTAFAR_VERSION' ) ) {
	die();
}

class DeepSeek extends OpenAiCompatibleProvider {
	protected $baseUrl = 'https://api.deepseek.com/v1';

	public function getSlug() {
		return 'deepseek';
	}

	public function getName() {
		return 'DeepSeek';
	}
}
