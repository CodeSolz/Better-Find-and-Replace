<?php namespace RealTimeAutoFindReplace\admin\functions\ai;

/**
 * Static catalog of supported AI providers and their UI metadata.
 *
 * Model lists are a curated starting point, not an authority: every provider
 * panel has a "Refresh from API" button that replaces them with the live list
 * for that account. Keep `models_static` short and current, and move anything a
 * provider has shut down into `models_legacy` so the UI can flag it and the
 * error path can name a replacement instead of failing with a bare HTTP 404.
 *
 * @package AI
 * @since 1.9.0
 * @author M.Tuhin <info@codesolz.net>
 */

if ( ! defined( 'CS_RTAFAR_VERSION' ) ) {
	die();
}

class ProviderRegistry {

	/**
	 * Keys: slug, name, class, free, free_label, signup_url, api_key_url, oauth,
	 * oauth_kind, base_url, default_model, models_static, models_legacy, notes.
	 *
	 * `models_legacy` maps a withdrawn model id to the id that replaced it. Ids
	 * listed there must never also appear in `models_static`.
	 */
	public static function all() {
		return array(

			'openai' => array(
				'slug'          => 'openai',
				'name'          => 'OpenAI',
				'class'         => '\\RealTimeAutoFindReplace\\admin\\functions\\ai\\Providers\\OpenAi',
				'free'          => false,
				'free_label'    => '',
				'signup_url'    => 'https://platform.openai.com/signup',
				'api_key_url'   => 'https://platform.openai.com/api-keys',
				'oauth'         => false,
				'base_url'      => 'https://api.openai.com/v1',
				'default_model' => 'gpt-5.6-luna',
				'models_static' => array(
					'gpt-6-astra'   => 'GPT-6 Astra',
					'gpt-5.6-sol'   => 'GPT-5.6 Sol',
					'gpt-5.6-terra' => 'GPT-5.6 Terra',
					'gpt-5.6-luna'  => 'GPT-5.6 Luna',
				),
				'models_legacy' => array(
					'gpt-4o'        => 'gpt-5.6-terra',
					'gpt-4o-mini'   => 'gpt-5.6-luna',
					'gpt-4.1'       => 'gpt-5.6-terra',
					'gpt-4.1-mini'  => 'gpt-5.6-luna',
					'gpt-4.1-nano'  => 'gpt-5.6-luna',
					'gpt-3.5-turbo' => 'gpt-5.6-luna',
					'o1'            => 'gpt-6-astra',
					'o1-mini'       => 'gpt-5.6-luna',
					'o3-mini'       => 'gpt-5.6-luna',
				),
				'notes'         => 'Industry standard. Pay-as-you-go API keys.',
			),

			'anthropic' => array(
				'slug'          => 'anthropic',
				'name'          => 'Anthropic Claude',
				'class'         => '\\RealTimeAutoFindReplace\\admin\\functions\\ai\\Providers\\Anthropic',
				'free'          => false,
				'free_label'    => '',
				'signup_url'    => 'https://console.anthropic.com/',
				'api_key_url'   => 'https://console.anthropic.com/settings/keys',
				'oauth'         => false,
				'base_url'      => 'https://api.anthropic.com/v1',
				'default_model' => 'claude-haiku-4-5',
				'models_static' => array(
					'claude-opus-5'     => 'Claude Opus 5',
					'claude-sonnet-5'   => 'Claude Sonnet 5',
					'claude-haiku-4-5'  => 'Claude Haiku 4.5',
					'claude-opus-4-8'   => 'Claude Opus 4.8',
					'claude-sonnet-4-6' => 'Claude Sonnet 4.6',
				),
				'models_legacy' => array(
					'claude-opus-4-5-20250929'   => 'claude-opus-5',
					'claude-sonnet-4-5-20250929' => 'claude-sonnet-5',
					'claude-3-5-haiku-latest'    => 'claude-haiku-4-5',
					'claude-3-5-sonnet-latest'   => 'claude-sonnet-4-6',
					'claude-3-haiku-20240307'    => 'claude-haiku-4-5',
				),
				'notes'         => 'Best-in-class reasoning. Pay-as-you-go API keys.',
			),

			'gemini' => array(
				'slug'          => 'gemini',
				'name'          => 'Google Gemini',
				'class'         => '\\RealTimeAutoFindReplace\\admin\\functions\\ai\\Providers\\Gemini',
				'free'          => true,
				'free_label'    => 'Free tier',
				'signup_url'    => 'https://aistudio.google.com/',
				'api_key_url'   => 'https://aistudio.google.com/app/apikey',
				'oauth'         => false,
				'oauth_kind'    => 'device',
				'base_url'      => 'https://generativelanguage.googleapis.com/v1beta',
				'default_model' => 'gemini-3.5-flash',
				'models_static' => array(
					'gemini-3.8-flash'      => 'Gemini 3.8 Flash',
					'gemini-3.7-flash'      => 'Gemini 3.7 Flash',
					'gemini-3.6-flash'      => 'Gemini 3.6 Flash',
					'gemini-3.5-flash'      => 'Gemini 3.5 Flash',
					'gemini-3.5-flash-lite' => 'Gemini 3.5 Flash-Lite',
					'gemini-3.1-flash-lite' => 'Gemini 3.1 Flash-Lite',
					'gemini-2.5-pro'        => 'Gemini 2.5 Pro',
					'gemini-2.5-flash'      => 'Gemini 2.5 Flash',
					'gemini-2.5-flash-lite' => 'Gemini 2.5 Flash-Lite',
				),
				'models_legacy' => array(
					'gemini-2.0-flash'              => 'gemini-3.5-flash',
					'gemini-2.0-flash-lite'         => 'gemini-3.5-flash-lite',
					'gemini-1.5-flash'              => 'gemini-3.5-flash',
					'gemini-1.5-pro'                => 'gemini-2.5-pro',
					'gemini-3-pro-preview'          => 'gemini-2.5-pro',
					'gemini-3.1-flash-lite-preview' => 'gemini-3.1-flash-lite',
				),
				'notes'         => 'Generous free tier. Get a free API key in seconds via AI Studio.',
			),

			'groq' => array(
				'slug'          => 'groq',
				'name'          => 'Groq',
				'class'         => '\\RealTimeAutoFindReplace\\admin\\functions\\ai\\Providers\\Groq',
				'free'          => true,
				'free_label'    => 'Free tier',
				'signup_url'    => 'https://console.groq.com/',
				'api_key_url'   => 'https://console.groq.com/keys',
				'oauth'         => false,
				'base_url'      => 'https://api.groq.com/openai/v1',
				'default_model' => 'llama-3.3-70b-versatile',
				'models_static' => array(
					'llama-3.3-70b-versatile' => 'Llama 3.3 70B Versatile',
					'llama-3.1-8b-instant'    => 'Llama 3.1 8B Instant',
					'openai/gpt-oss-120b'     => 'GPT-OSS 120B',
					'openai/gpt-oss-20b'      => 'GPT-OSS 20B',
					'groq/compound'           => 'Groq Compound',
					'groq/compound-mini'      => 'Groq Compound Mini',
				),
				'models_legacy' => array(
					// Both shut down by Groq; these are the replacements Groq named.
					'mixtral-8x7b-32768' => 'llama-3.3-70b-versatile',
					'gemma2-9b-it'       => 'llama-3.1-8b-instant',
				),
				'notes'         => 'Free tier with very fast inference (open-weight models).',
			),

			'mistral' => array(
				'slug'          => 'mistral',
				'name'          => 'Mistral',
				'class'         => '\\RealTimeAutoFindReplace\\admin\\functions\\ai\\Providers\\Mistral',
				'free'          => true,
				'free_label'    => 'Free tier',
				'signup_url'    => 'https://console.mistral.ai/',
				'api_key_url'   => 'https://console.mistral.ai/api-keys/',
				'oauth'         => false,
				'base_url'      => 'https://api.mistral.ai/v1',
				'default_model' => 'mistral-small-4-0-26-03',
				'models_static' => array(
					'mistral-medium-3-5-26-04' => 'Mistral Medium 3.5',
					'mistral-small-4-0-26-03'  => 'Mistral Small 4',
					'mistral-large-3-25-12'    => 'Mistral Large 3',
					'ministral-3-14b-25-12'    => 'Ministral 3 14B',
					'ministral-3-8b-25-12'     => 'Ministral 3 8B',
					'ministral-3-3b-25-12'     => 'Ministral 3 3B',
				),
				'models_legacy' => array(
					'mistral-large-latest' => 'mistral-large-3-25-12',
					'mistral-small-latest' => 'mistral-small-4-0-26-03',
					'open-mistral-7b'      => 'ministral-3-8b-25-12',
					'open-mixtral-8x7b'    => 'mistral-small-4-0-26-03',
				),
				'notes'         => 'Free tier on La Plateforme.',
			),

			'openrouter' => array(
				'slug'          => 'openrouter',
				'name'          => 'OpenRouter',
				'class'         => '\\RealTimeAutoFindReplace\\admin\\functions\\ai\\Providers\\OpenRouter',
				'free'          => true,
				'free_label'    => 'Free models',
				'signup_url'    => 'https://openrouter.ai/',
				'api_key_url'   => 'https://openrouter.ai/keys',
				'oauth'         => true,
				'oauth_kind'    => 'pkce',
				'base_url'      => 'https://openrouter.ai/api/v1',
				'default_model' => 'nvidia/nemotron-3.5-lightning:free',
				'models_static' => array(
					'nvidia/nemotron-3.5-lightning:free'  => 'Nemotron 3.5 Lightning (Free)',
					'liquid/lfm-2.5-2.6b:free'            => 'LFM 2.5 2.6B (Free)',
					'thinkingmachines/inkling-small:free' => 'Inkling Small (Free)',
					'openai/gpt-6-astra'                  => 'GPT-6 Astra (Paid)',
					'anthropic/claude-fable-5.1'          => 'Claude Fable 5.1 (Paid)',
					'google/gemini-3.8-flash'             => 'Gemini 3.8 Flash (Paid)',
				),
				'models_legacy' => array(
					'meta-llama/llama-3.1-8b-instruct:free' => 'nvidia/nemotron-3.5-lightning:free',
					'google/gemma-2-9b-it:free'             => 'liquid/lfm-2.5-2.6b:free',
					'mistralai/mistral-7b-instruct:free'    => 'liquid/lfm-2.5-2.6b:free',
					'openai/gpt-4o-mini'                    => 'openai/gpt-6-astra',
					'anthropic/claude-3.5-haiku'            => 'anthropic/claude-fable-5.1',
				),
				'notes'         => 'Single login for 100+ models (OpenAI, Anthropic, Llama, Gemma, Mistral…). Sign in with your OpenRouter account — no API key to copy. Free models rotate often; use "Refresh from API" for the current list.',
			),

			'deepseek' => array(
				'slug'          => 'deepseek',
				'name'          => 'DeepSeek',
				'class'         => '\\RealTimeAutoFindReplace\\admin\\functions\\ai\\Providers\\DeepSeek',
				'free'          => false,
				'free_label'    => '',
				'signup_url'    => 'https://platform.deepseek.com/',
				'api_key_url'   => 'https://platform.deepseek.com/api_keys',
				'oauth'         => false,
				'base_url'      => 'https://api.deepseek.com/v1',
				'default_model' => 'deepseek-v4-flash',
				'models_static' => array(
					'deepseek-v4-flash' => 'DeepSeek V4 Flash',
					'deepseek-v4-pro'   => 'DeepSeek V4 Pro',
				),
				'models_legacy' => array(
					'deepseek-chat'     => 'deepseek-v4-flash',
					'deepseek-reasoner' => 'deepseek-v4-pro',
				),
				'notes'         => 'Very low cost per token.',
			),

			'xai' => array(
				'slug'          => 'xai',
				'name'          => 'xAI Grok',
				'class'         => '\\RealTimeAutoFindReplace\\admin\\functions\\ai\\Providers\\XAi',
				'free'          => false,
				'free_label'    => '',
				'signup_url'    => 'https://x.ai/api',
				'api_key_url'   => 'https://console.x.ai/',
				'oauth'         => false,
				'base_url'      => 'https://api.x.ai/v1',
				'default_model' => 'grok-4.6',
				'models_static' => array(
					'grok-4.6' => 'Grok 4.6',
					'grok-4.5' => 'Grok 4.5',
					'grok-4.3' => 'Grok 4.3',
				),
				'models_legacy' => array(
					'grok-2-latest'      => 'grok-4.6',
					'grok-2-mini-latest' => 'grok-4.5',
				),
				'notes'         => 'X / Twitter\'s AI. OpenAI-compatible API.',
			),

			'huggingface' => array(
				'slug'          => 'huggingface',
				'name'          => 'Hugging Face',
				'class'         => '\\RealTimeAutoFindReplace\\admin\\functions\\ai\\Providers\\HuggingFace',
				'free'          => true,
				'free_label'    => 'Free tier',
				'signup_url'    => 'https://huggingface.co/join',
				'api_key_url'   => 'https://huggingface.co/settings/tokens',
				'oauth'         => false,
				'oauth_kind'    => 'redirect',
				'base_url'      => 'https://router.huggingface.co/v1',
				'default_model' => 'meta-llama/Llama-3.1-8B-Instruct',
				'models_static' => array(
					'meta-llama/Llama-3.1-8B-Instruct'   => 'Llama 3.1 8B Instruct',
					'mistralai/Mistral-7B-Instruct-v0.3' => 'Mistral 7B Instruct',
					'google/gemma-2-9b-it'               => 'Gemma 2 9B',
				),
				'models_legacy' => array(),
				'notes'         => 'Free tier on Inference Providers. OpenAI-compatible router endpoint. Availability depends on which inference provider currently serves the repo.',
			),

			'ollama' => array(
				'slug'          => 'ollama',
				'name'          => 'Ollama (Local)',
				'class'         => '\\RealTimeAutoFindReplace\\admin\\functions\\ai\\Providers\\Ollama',
				'free'          => true,
				'free_label'    => 'Local / Free',
				'signup_url'    => 'https://ollama.com/download',
				'api_key_url'   => '',
				'oauth'         => false,
				'base_url'      => 'http://localhost:11434/v1',
				'default_model' => 'llama3.2',
				'models_static' => array(
					'llama3.2'    => 'Llama 3.2',
					'llama3.1'    => 'Llama 3.1',
					'qwen3'       => 'Qwen 3',
					'qwen2.5'     => 'Qwen 2.5',
					'gemma3'      => 'Gemma 3',
					'deepseek-r1' => 'DeepSeek R1',
					'mistral'     => 'Mistral',
				),
				'models_legacy' => array(),
				'notes'         => 'Run models locally. No API key, no cost. Install Ollama and pull a model first — "Refresh from API" lists what you actually have.',
			),

		);
	}

	/** Filterable so plugins can extend or override. */
	public static function getProviders() {
		return apply_filters( 'rtafar_ai_providers', self::all() );
	}

	public static function get( $slug ) {
		$all = self::getProviders();
		return isset( $all[ $slug ] ) ? $all[ $slug ] : null;
	}

	public static function slugs() {
		return array_keys( self::getProviders() );
	}

	/**
	 * Withdrawn model id => replacement id, for one provider.
	 *
	 * @param string $slug Provider slug.
	 * @return array
	 */
	public static function legacyModels( $slug ) {
		$entry = self::get( $slug );
		return ( $entry && ! empty( $entry['models_legacy'] ) && is_array( $entry['models_legacy'] ) )
			? $entry['models_legacy']
			: array();
	}

	/**
	 * The id that replaced a withdrawn model, or '' when the model is not a
	 * known-withdrawn one. An empty string means "nothing to say about it" —
	 * never treat it as "the model is fine".
	 *
	 * @param string $slug  Provider slug.
	 * @param string $model Model id to look up.
	 * @return string
	 */
	public static function replacementFor( $slug, $model ) {
		$legacy = self::legacyModels( $slug );
		return isset( $legacy[ $model ] ) ? $legacy[ $model ] : '';
	}

	/**
	 * Human label for a model id — the curated label when known, else the id.
	 *
	 * @param string $slug  Provider slug.
	 * @param string $model Model id.
	 * @return string
	 */
	public static function modelLabel( $slug, $model ) {
		$entry = self::get( $slug );
		if ( $entry && isset( $entry['models_static'][ $model ] ) ) {
			return $entry['models_static'][ $model ];
		}
		return $model;
	}
}
