<?php namespace RealTimeAutoFindReplace\lib;

/**
 * Where popular page builders keep their content, and how it is encoded, so the
 * DB replacer can match it accurately and clear the right caches afterwards.
 *
 * Strategies per postmeta key:
 *   'json'       — slash-escaped JSON (Elementor): needs escaped-variant matching.
 *   'serialized' — PHP-serialized (Beaver, Bricks): handled length-safe by the engine.
 *   'raw'        — plain text / shortcodes (Oxygen, Divi/WPBakery in post_content).
 *
 * Selecting a builder is additive — it broadens matching and enables cache
 * clearing; it never narrows the rows the replacer would otherwise scan.
 *
 * @package Library
 * @since 1.9.1
 * @author M.Tuhin <info@codesolz.net>
 */

if ( ! defined( 'CS_RTAFAR_VERSION' ) ) {
	exit;
}

class BuilderRegistry {

	/**
	 * Filterable builder → storage/cache map.
	 *
	 * @return array
	 */
	public static function all() {
		$map = array(
			'elementor'     => array(
				'label'           => 'Elementor',
				'postmeta'        => array( '_elementor_data' => 'json' ),
				'postmeta_prefix' => array(),
				'content'         => array(),
				'cache_meta'      => array( '_elementor_css' ),
				'cache_clear'     => 'elementor',
			),
			'beaver'        => array(
				'label'           => 'Beaver Builder',
				'postmeta'        => array(
					'_fl_builder_data'           => 'serialized',
					'_fl_builder_data_settings'  => 'serialized',
					'_fl_builder_draft'          => 'serialized',
					'_fl_builder_draft_settings' => 'serialized',
				),
				'postmeta_prefix' => array(),
				'content'         => array(),
				'cache_meta'      => array(),
				'cache_clear'     => 'beaver',
			),
			'divi_wpbakery' => array(
				'label'           => 'Divi / WPBakery',
				'postmeta'        => array(),
				'postmeta_prefix' => array(),
				'content'         => array( 'post_content' ),
				'cache_meta'      => array(),
				'cache_clear'     => '',
			),
			'oxygen'        => array(
				'label'           => 'Oxygen',
				'postmeta'        => array( 'ct_builder_shortcodes' => 'raw' ),
				'postmeta_prefix' => array( '_ct_builder_shortcodes_' => 'raw' ),
				'content'         => array(),
				'cache_meta'      => array(),
				'cache_clear'     => '',
			),
			'bricks'        => array(
				'label'           => 'Bricks',
				'postmeta'        => array( '_bricks_page_content_2' => 'serialized' ),
				'postmeta_prefix' => array(),
				'content'         => array(),
				'cache_meta'      => array(),
				'cache_clear'     => '',
			),
		);

		return (array) \apply_filters( 'bfrp_builder_registry', $map );
	}

	/** slug => label, for the multi-select on the Replace-in-DB page. */
	public static function as_select_options() {
		$options = array();
		foreach ( self::all() as $slug => $cfg ) {
			$options[ $slug ] = isset( $cfg['label'] ) ? $cfg['label'] : $slug;
		}
		return $options;
	}

	/**
	 * Decode strategy for a postmeta key among the selected builders, or false.
	 *
	 * @param string $meta_key
	 * @param array  $selected builder slugs
	 * @return string|false
	 */
	public static function postmeta_strategy( $meta_key, array $selected ) {
		$all = self::all();
		foreach ( $selected as $slug ) {
			if ( ! isset( $all[ $slug ] ) ) {
				continue;
			}
			$cfg = $all[ $slug ];

			if ( isset( $cfg['postmeta'][ $meta_key ] ) ) {
				return $cfg['postmeta'][ $meta_key ];
			}

			if ( ! empty( $cfg['postmeta_prefix'] ) ) {
				foreach ( $cfg['postmeta_prefix'] as $prefix => $strategy ) {
					if ( '' !== $prefix && 0 === strpos( (string) $meta_key, $prefix ) ) {
						return $strategy;
					}
				}
			}
		}
		return false;
	}

	/** True if any selected builder stores slash-escaped JSON (needs escaped matching). */
	public static function any_json( array $selected ) {
		$all = self::all();
		foreach ( $selected as $slug ) {
			if ( empty( $all[ $slug ]['postmeta'] ) ) {
				continue;
			}
			foreach ( $all[ $slug ]['postmeta'] as $strategy ) {
				if ( 'json' === $strategy ) {
					return true;
				}
			}
		}
		return false;
	}

	/** Cache postmeta keys to delete after a live replace. */
	public static function cache_meta_keys( array $selected ) {
		$keys = array();
		$all  = self::all();
		foreach ( $selected as $slug ) {
			if ( ! empty( $all[ $slug ]['cache_meta'] ) ) {
				$keys = array_merge( $keys, $all[ $slug ]['cache_meta'] );
			}
		}
		return array_values( array_unique( $keys ) );
	}

	/** Builder cache-clear handler keys (e.g. 'elementor', 'beaver'). */
	public static function cache_handlers( array $selected ) {
		$handlers = array();
		$all      = self::all();
		foreach ( $selected as $slug ) {
			if ( ! empty( $all[ $slug ]['cache_clear'] ) ) {
				$handlers[] = $all[ $slug ]['cache_clear'];
			}
		}
		return array_values( array_unique( $handlers ) );
	}
}
