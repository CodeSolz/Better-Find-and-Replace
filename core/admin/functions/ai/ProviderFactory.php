<?php namespace RealTimeAutoFindReplace\admin\functions\ai;

use RealTimeAutoFindReplace\admin\functions\ai\Contracts\AiProviderInterface;

/**
 * Builds a configured provider instance from a slug + per-provider config.
 *
 * @package AI
 * @since 1.9.0
 * @author M.Tuhin <info@codesolz.net>
 */

if ( ! defined( 'CS_RTAFAR_VERSION' ) ) {
	die();
}

class ProviderFactory {

	public static function make( $slug, array $providerConfig = array(), array $registryEntry = null ) {
		if ( null === $registryEntry ) {
			$registryEntry = ProviderRegistry::get( $slug );
		}
		if ( ! $registryEntry || empty( $registryEntry['class'] ) ) {
			return null;
		}

		$class = $registryEntry['class'];
		if ( ! class_exists( $class ) ) {
			return null;
		}

		$config = array_merge(
			array(
				'base_url' => isset( $registryEntry['base_url'] ) ? $registryEntry['base_url'] : '',
				'model'    => isset( $registryEntry['default_model'] ) ? $registryEntry['default_model'] : '',
			),
			$providerConfig
		);

		$instance = new $class( $config );
		return $instance instanceof AiProviderInterface ? $instance : null;
	}
}
