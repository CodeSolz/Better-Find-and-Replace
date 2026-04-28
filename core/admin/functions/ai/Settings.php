<?php namespace RealTimeAutoFindReplace\admin\functions\ai;

/**
 * Storage layer for AI settings (option key cs_ai_config).
 *
 * @package AI
 * @since 1.9.0
 * @author M.Tuhin <info@codesolz.net>
 */

if ( ! defined( 'CS_RTAFAR_VERSION' ) ) {
	die();
}

class Settings {

	const OPTION_KEY = 'cs_ai_config';

	private static $providerFields = array(
		'auth_type',
		'api_key',
		'token',
		'token_expires_at',
		'refresh_token',
		'model',
		'base_url',
	);

	public static function get() {
		$raw = get_option( self::OPTION_KEY, array() );

		if ( ! is_array( $raw ) || empty( $raw ) ) {
			return self::defaults();
		}

		// Legacy { api_key, language_model } shape → new shape.
		if ( isset( $raw['api_key'] ) && ! isset( $raw['providers'] ) ) {
			$migrated = self::defaults();
			$migrated['providers']['openai'] = array(
				'auth_type' => 'api_key',
				'api_key'   => (string) $raw['api_key'],
				'model'     => isset( $raw['language_model'] ) ? (string) $raw['language_model'] : 'gpt-4o-mini',
				'base_url'  => '',
			);
			$migrated['active_provider'] = 'openai';
			update_option( self::OPTION_KEY, $migrated );
			return $migrated;
		}

		return self::normalize( $raw );
	}

	public static function save( array $settings ) {
		$normalized = self::normalize( $settings );
		update_option( self::OPTION_KEY, $normalized );
		return $normalized;
	}

	/** Merges with existing config for that provider. */
	public static function saveProvider( $slug, array $providerConfig ) {
		$all = self::get();
		$existing = isset( $all['providers'][ $slug ] ) ? $all['providers'][ $slug ] : array();

		$merged = array_merge( $existing, self::sanitizeProviderConfig( $providerConfig ) );
		$all['providers'][ $slug ] = $merged;

		return self::save( $all );
	}

	public static function setActiveProvider( $slug ) {
		$all = self::get();
		$all['active_provider'] = self::sanitizeSlug( $slug );
		return self::save( $all );
	}

	/** Saved config for a provider, merged with registry defaults. */
	public static function getProviderConfig( $slug ) {
		$all   = self::get();
		$saved = isset( $all['providers'][ $slug ] ) ? $all['providers'][ $slug ] : array();

		$registry = ProviderRegistry::get( $slug );
		$defaults = array(
			'auth_type' => 'api_key',
			'api_key'   => '',
			'token'     => '',
			'model'     => $registry ? $registry['default_model'] : '',
			'base_url'  => $registry ? $registry['base_url'] : '',
		);

		return array_merge( $defaults, $saved );
	}

	public static function defaults() {
		return array(
			'active_provider' => 'openai',
			'prompt_template' => 'persuasive',
			'custom_prompt'   => '',
			'providers'       => array(),
		);
	}

	private static function normalize( array $raw ) {
		$active = isset( $raw['active_provider'] ) ? self::sanitizeSlug( $raw['active_provider'] ) : 'openai';

		$slugs = ProviderRegistry::slugs();
		if ( ! in_array( $active, $slugs, true ) ) {
			$active = 'openai';
		}

		$template = isset( $raw['prompt_template'] ) ? self::sanitizeSlug( $raw['prompt_template'] ) : 'persuasive';
		if ( ! in_array( $template, PromptTemplates::keys(), true ) && $template !== 'custom' ) {
			$template = 'persuasive';
		}

		$custom_prompt = isset( $raw['custom_prompt'] ) ? sanitize_textarea_field( $raw['custom_prompt'] ) : '';

		$providers = array();
		if ( isset( $raw['providers'] ) && is_array( $raw['providers'] ) ) {
			foreach ( $raw['providers'] as $slug => $cfg ) {
				$slug = self::sanitizeSlug( $slug );
				if ( ! in_array( $slug, $slugs, true ) ) {
					continue;
				}
				if ( ! is_array( $cfg ) ) {
					continue;
				}
				$providers[ $slug ] = self::sanitizeProviderConfig( $cfg );
			}
		}

		return array(
			'active_provider' => $active,
			'prompt_template' => $template,
			'custom_prompt'   => $custom_prompt,
			'providers'       => $providers,
		);
	}

	private static function sanitizeProviderConfig( array $cfg ) {
		$out = array();
		foreach ( self::$providerFields as $field ) {
			if ( ! isset( $cfg[ $field ] ) ) {
				continue;
			}
			$value = $cfg[ $field ];
			if ( $field === 'token_expires_at' ) {
				$out[ $field ] = (int) $value;
			} elseif ( $field === 'base_url' ) {
				$out[ $field ] = esc_url_raw( $value );
			} else {
				$out[ $field ] = sanitize_text_field( $value );
			}
		}
		return $out;
	}

	private static function sanitizeSlug( $slug ) {
		return preg_replace( '/[^a-z0-9_]/', '', strtolower( (string) $slug ) );
	}
}
