<?php namespace RealTimeAutoFindReplace\admin\functions\ai;

/**
 * Static catalog of supported AI providers and their UI metadata.
 *
 * @package AI
 * @since 1.9.0
 * @author M.Tuhin <info@codesolz.net>
 */

if ( ! defined( 'CS_RTAFAR_VERSION' ) ) {
	die();
}

class ProviderRegistry {

	/** Keys: slug, name, class, free, free_label, signup_url, api_key_url, oauth, oauth_kind, base_url, default_model, models_static, notes. */
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
				'default_model' => 'gpt-4o-mini',
				'models_static' => array(
					'gpt-4o'            => 'GPT-4o',
					'gpt-4o-mini'       => 'GPT-4o Mini',
					'gpt-4.1'           => 'GPT-4.1',
					'gpt-4.1-mini'      => 'GPT-4.1 Mini',
					'gpt-4.1-nano'      => 'GPT-4.1 Nano',
					'gpt-3.5-turbo'     => 'GPT-3.5 Turbo',
					'o1'                => 'o1',
					'o1-mini'           => 'o1 Mini',
					'o3-mini'           => 'o3 Mini',
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
				'default_model' => 'claude-3-5-haiku-latest',
				'models_static' => array(
					'claude-opus-4-5-20250929'   => 'Claude Opus 4.5',
					'claude-sonnet-4-5-20250929' => 'Claude Sonnet 4.5',
					'claude-3-5-haiku-latest'    => 'Claude 3.5 Haiku',
					'claude-3-5-sonnet-latest'   => 'Claude 3.5 Sonnet',
					'claude-3-haiku-20240307'    => 'Claude 3 Haiku',
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
				'default_model' => 'gemini-2.0-flash',
				'models_static' => array(
					'gemini-2.5-pro'          => 'Gemini 2.5 Pro',
					'gemini-2.5-flash'        => 'Gemini 2.5 Flash',
					'gemini-2.0-flash'        => 'Gemini 2.0 Flash',
					'gemini-2.0-flash-lite'   => 'Gemini 2.0 Flash Lite',
					'gemini-1.5-flash'        => 'Gemini 1.5 Flash',
					'gemini-1.5-pro'          => 'Gemini 1.5 Pro',
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
					'mixtral-8x7b-32768'      => 'Mixtral 8x7B',
					'gemma2-9b-it'            => 'Gemma 2 9B',
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
				'default_model' => 'mistral-small-latest',
				'models_static' => array(
					'mistral-large-latest' => 'Mistral Large',
					'mistral-small-latest' => 'Mistral Small',
					'open-mistral-7b'      => 'Open Mistral 7B',
					'open-mixtral-8x7b'    => 'Open Mixtral 8x7B',
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
				'default_model' => 'meta-llama/llama-3.1-8b-instruct:free',
				'models_static' => array(
					'meta-llama/llama-3.1-8b-instruct:free' => 'Llama 3.1 8B (Free)',
					'google/gemma-2-9b-it:free'             => 'Gemma 2 9B (Free)',
					'mistralai/mistral-7b-instruct:free'    => 'Mistral 7B (Free)',
					'openai/gpt-4o-mini'                    => 'GPT-4o Mini (Paid)',
					'anthropic/claude-3.5-haiku'            => 'Claude 3.5 Haiku (Paid)',
				),
				'notes'         => 'Single login for 100+ models (OpenAI, Anthropic, Llama, Gemma, Mistral…). Sign in with your OpenRouter account — no API key to copy.',
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
				'default_model' => 'deepseek-chat',
				'models_static' => array(
					'deepseek-chat'     => 'DeepSeek Chat',
					'deepseek-reasoner' => 'DeepSeek Reasoner',
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
				'default_model' => 'grok-2-latest',
				'models_static' => array(
					'grok-2-latest'      => 'Grok 2',
					'grok-2-mini-latest' => 'Grok 2 Mini',
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
					'meta-llama/Llama-3.1-8B-Instruct'  => 'Llama 3.1 8B Instruct',
					'mistralai/Mistral-7B-Instruct-v0.3' => 'Mistral 7B Instruct',
					'google/gemma-2-9b-it'              => 'Gemma 2 9B',
				),
				'notes'         => 'Free tier on Inference Providers. OpenAI-compatible router endpoint.',
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
					'llama3.2'   => 'Llama 3.2',
					'llama3.1'   => 'Llama 3.1',
					'mistral'    => 'Mistral',
					'gemma2'     => 'Gemma 2',
					'phi3'       => 'Phi-3',
					'qwen2.5'    => 'Qwen 2.5',
				),
				'notes'         => 'Run models locally. No API key, no cost. Install Ollama and pull a model first.',
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
}
