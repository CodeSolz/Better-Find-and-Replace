<?php namespace RealTimeAutoFindReplace\admin\functions\ai\Providers;

if ( ! defined( 'CS_RTAFAR_VERSION' ) ) {
	die();
}

class XAi extends OpenAiCompatibleProvider {
	protected $baseUrl = 'https://api.x.ai/v1';

	public function getSlug() {
		return 'xai';
	}

	public function getName() {
		return 'xAI Grok';
	}
}
