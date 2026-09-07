<?php namespace RealTimeAutoFindReplace\admin\functions\ai\Providers;

if ( ! defined( 'CS_RTAFAR_VERSION' ) ) {
	die();
}

class OpenAi extends OpenAiCompatibleProvider {
	protected $baseUrl = 'https://api.openai.com/v1';

	/**
	 * OpenAI deprecated `max_tokens` and rejects it outright on reasoning
	 * models; `max_completion_tokens` is accepted by the whole current line-up.
	 */
	protected $maxTokensParam = 'max_completion_tokens';

	/** The current GPT-5.6 / GPT-6 families are reasoning models, which reject a custom temperature. */
	protected $sendTemperature = false;

	/** Reasoning tokens are billed against this budget before any text appears. */
	protected $maxOutputTokens = 2048;

	public function getSlug() {
		return 'openai';
	}

	public function getName() {
		return 'OpenAI';
	}
}
