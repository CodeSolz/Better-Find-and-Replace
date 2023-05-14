<?php namespace RealTimeAutoFindReplace\actions;

/**
 * Class: Register custom menu
 *
 * @package Action
 * @since 1.0.0
 * @author M.Tuhin <info@codesolz.net>
 */

if ( ! defined( 'CS_RTAFAR_VERSION' ) ) {
	die();
}

use RealTimeAutoFindReplace\lib\Util;
use RealTimeAutoFindReplace\install\Activate;
use RealTimeAutoFindReplace\admin\functions\Masking;
use RealTimeAutoFindReplace\admin\functions\ProActions;
use RealTimeAutoFindReplace\actions\RTAFAR_RegisterMenu;
use RealTimeAutoFindReplace\admin\options\pages\AdvScreenOptions\ScreenOptions;

class RTAFAR_WP_Hooks {

	private $WP_Ins;

	function __construct() {

		/*** add settings link */
		add_filter( 'plugin_action_links_' . CS_RTAFAR_PLUGIN_IDENTIFIER, array( __class__, 'rtafarSettingsLink' ) );

		/*** add docs link */
		add_filter( 'plugin_row_meta', array( $this, 'rtafar_plugin_row_meta' ), 10, 2 );

		add_action( 'template_redirect', array( $this, 'rtafar_filter_contents' ) );

		/*** add function after upgrade process complete */
		add_action( 'upgrader_process_complete', array( __class__, 'rtafarAfterUpgrade' ), 10, 2 );

		/*** screen options */
		add_action( 'admin_menu', array( $this, 'rtafar_current_screen_options' ), 25 );
		add_filter( 'set-screen-option', array( $this, 'rtafar_set_amr_per_page' ), 15, 3 );

		/*** Capability options */
		add_action( 'init', array( $this, 'rtafar_role_caps' ), 11 );
		add_filter( 'ure_capabilities_groups_tree', array( $this, 'rtafar_ure_capabilities' ), 15 );
		add_filter( 'ure_custom_capability_groups', array( $this, 'rtafar_ure_custom_capability_groups' ), 15, 2 );

	}


	/**
	 * Filter content
	 *
	 * @return void
	 */
	public function rtafar_filter_contents() {
		$replace_rules = Masking::get_rules( 'all' );
		$has_pro = ProActions::hasPro();

		// pre_print( $replace_rules );

		return ob_start(
			function( $buffer ) use ( $replace_rules, $has_pro ) {
				return $this->get_filtered_content( $buffer, $replace_rules, $has_pro );
			}
		);
	}

	/**
	 * Filter content
	 *
	 * @param [type] $buffer
	 * @return void
	 */
	private function get_filtered_content( $buffer, $replace_rules, $has_pro ) {
		if ( $replace_rules ) {
			foreach ( $replace_rules as $item ) {
				if( $has_pro ){
					$buffer = apply_filters( 'bfrp_render_real_time_content', $item, $buffer );
				}else{
					$buffer = $this->replace( $item, $buffer );
				}
			}
		}

		return $buffer;
	}


	/**
	 * Replace
	 *
	 * @param [type]  $item
	 * @param [type]  $buffer
	 * @param boolean $find
	 * @return void
	 */
	private function replace( $item, $buffer, $find = false ) {
		$find = false !== $find ? $find : $item->find;

		if ( $item->type == 'regex' ) {
			$find    = '#' . Util::cs_stripslashes( $find ) . '#';
			$replace = Util::cs_stripslashes( $item->replace );
			return \preg_replace( $find, $replace, $buffer );
		} 
		elseif ( $item->type == 'regexCustom' ) {
			//NOTE: search with custom pattern  
			return \preg_replace( $find, $item->replace, $buffer );
		} 
		elseif ( $item->type == 'multiByte' ) {
			//NOTE: search and replace on multiByte string
			\mb_regex_encoding( $item->html_charset );
			return \mb_ereg_replace( $find, Util::cs_stripslashes( $item->replace ), $buffer );
		} 
		else {
			return \str_replace( Util::cs_stripslashes( $find ), Util::cs_stripslashes( $item->replace ), $buffer );
		}

	}

	/**
	 * Add settings links
	 *
	 * @param [type] $links
	 * @return void
	 */
	public static function rtafarSettingsLink( $links ) {
		$custom_links = array(
			'add_new_rules' => '<a href="' . Util::cs_generate_admin_url( 'cs-add-replacement-rule' ) . '">' . __( 'Add New Rule', 'real-time-auto-find-and-replace' ) . '</a>',
			'all_rules'     => '<a href="' . Util::cs_generate_admin_url( 'cs-all-masking-rules' ) . '" aria-label="' . esc_attr__( 'All Replacement Rules', 'real-time-auto-find-and-replace' ) . '">' . __( 'All Rules', 'real-time-auto-find-and-replace' ) . '</a>',
		);

		$pro_links = array(
			'cs-bfar-go-pro-action-link' => '<a target="_blank" href="' . esc_url( Util::cs_pro_link() . '?utm_campaign=gopro&utm_source=pl-actions-links&utm_medium=wp-dash' ) . '" aria-label="' . esc_attr__( 'Go Pro', 'real-time-auto-find-and-replace' ) . '"> ' . esc_html__( 'Go Pro', 'real-time-auto-find-and-replace' ) . '</a>',
		);

		if ( ProActions::hasPro() ) {
			$pro_links = array();
		}

		return array_merge( $custom_links, $links, $pro_links );
	}

	/**
	 * Plugins Row
	 *
	 * @param [type] $links
	 * @param [type] $file
	 * @return void
	 */
	public function rtafar_plugin_row_meta( $links, $file ) {
		if ( plugin_basename( CS_RTAFAR_PLUGIN_IDENTIFIER ) !== $file ) {
			return $links;
		}

		$row_meta = apply_filters(
			'rtafar_row_meta',
			array(
				'docs'    => '<a target="_blank" href="' . esc_url( 'https://docs.codesolz.net/better-find-and-replace/' ) . '" aria-label="' . esc_attr__( 'documentation', 'real-time-auto-find-and-replace' ) . '">' . esc_html__( 'Docs', 'real-time-auto-find-and-replace' ) . '</a>',
				'videos'  => '<a target="_blank" href="' . esc_url( 'https://www.youtube.com/watch?v=nDv6T72sRfc&list=PLxLVEan0phTv5OfCX-FPu6n3RgpHS4kn6' ) . '" aria-label="' . esc_attr__( 'Video Tutorials', 'real-time-auto-find-and-replace' ) . '">' . esc_html__( 'Video Tutorials', 'real-time-auto-find-and-replace' ) . '</a>',
				'support' => '<a target="_blank" href="' . esc_url( 'https://codesolz.net/forum' ) . '" aria-label="' . esc_attr__( 'Community support', 'real-time-auto-find-and-replace' ) . '">' . esc_html__( 'Community support', 'real-time-auto-find-and-replace' ) . '</a>',
			)
		);

		return array_merge( $links, $row_meta );

	}

	/**
	 * Add function after
	 * plugin upgrade
	 *
	 * @return void
	 */
	public static function rtafarAfterUpgrade( $upgrader_object, $options ) {
		if ( isset( $options['action'] ) && $options['action'] == 'update' &&
			 isset( $options['type'] ) && $options['type'] == 'plugin' &&
			 isset( $options['plugins'] )
			 ) {

			foreach ( $options['plugins'] as $eachPlugin ) {
				if ( $eachPlugin == CS_RTAFAR_PLUGIN_IDENTIFIER ) {
					Activate::onUpgrade();
					break;
				}
			}
		}
	}

	/**
	 * Screen options
	 *
	 * @since 1.3.8
	 * @return void
	 */
	public function rtafar_current_screen_options() {
		global $rtafr_menu;

		$ScreenOptions = new ScreenOptions();

		if ( isset( $rtafr_menu['add_masking_rule'] ) && ! empty( $rtafr_menu['add_masking_rule'] ) ) {
			// add_action( 'load-' . $rtafr_menu['add_masking_rule'], array( $ScreenOptions, 'rtafar_arr_screen_options' ) );
		}

		if ( isset( $rtafr_menu['all_masking_rules'] ) && ! empty( $rtafr_menu['all_masking_rules'] ) ) {
			add_action( 'load-' . $rtafr_menu['all_masking_rules'], array( $ScreenOptions, 'rtafar_all_rules_screen_options' ) );
		}

		if ( isset( $rtafr_menu['replace_in_db'] ) && ! empty( $rtafr_menu['replace_in_db'] ) ) {
			// add_action( 'load-' . $rtafr_menu['replace_in_db'], array( $ScreenOptions, 'rtafar_screen_options_replace_in_db' ) );
		}
	}


	/**
	 * Save Screen option
	 *
	 * @return void
	 */
	public function rtafar_set_amr_per_page( $status, $option, $value ) {
		return ScreenOptions::rtafar_set_amr_per_page( $status, $option, $value );
	}


	/**
	 * Add user capabilities
	 *
	 * @return void
	 */
	public function rtafar_role_caps() {
		$role = \get_role( 'administrator' );
		$caps = RTAFAR_RegisterMenu::$nav_cap;
		if ( $caps ) {
			foreach ( $caps as $cap ) {
				$role->add_cap( $cap, true );
			}
		}
	}

	/**
	 * URE Capabilities
	 *
	 * @param [type] $groups
	 * @return void
	 */
	public function rtafar_ure_capabilities( $groups ) {
		$groups['better_find_and_replace'] = array(
			'caption' => esc_html__( 'Better find and replace', 'real-time-auto-find-and-replace' ),
			'parent'  => 'custom',
			'level'   => 2,
		);

		return $groups;
	}

	/**
	 * Add Capabilities in "USER ROLE EDITOR" plugin
	 *
	 * @param [type] $groups
	 * @param [type] $cap_id
	 * @return void
	 */
	public function rtafar_ure_custom_capability_groups( $groups, $cap_id ) {
		$caps = RTAFAR_RegisterMenu::$nav_cap;
		if ( $caps && \is_array( $caps ) && \in_array( $cap_id, $caps ) ) {
			$groups = array( 'custom', 'better_find_and_replace', 'better_find_and_replace_core' );
		}
		return $groups;
	}

}

