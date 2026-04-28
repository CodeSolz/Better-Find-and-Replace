<?php namespace RealTimeAutoFindReplace\admin\functions\ai\Providers;

if ( ! defined( 'CS_RTAFAR_VERSION' ) ) {
	die();
}

class Groq extends OpenAiCompatibleProvider {
	protected $baseUrl = 'https://api.groq.com/openai/v1';

	public function getSlug() {
		return 'groq';
	}

	public function getName() {
		return 'Groq';
	}
}
