<?php namespace RealTimeAutoFindReplace\admin\functions\ai\Providers;

if ( ! defined( 'CS_RTAFAR_VERSION' ) ) {
	die();
}

class Mistral extends OpenAiCompatibleProvider {
	protected $baseUrl = 'https://api.mistral.ai/v1';

	public function getSlug() {
		return 'mistral';
	}

	public function getName() {
		return 'Mistral';
	}
}
