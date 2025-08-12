<?php namespace RealTimeAutoFindReplace\admin\options;

/**
 * Class: Admin Menu Scripts
 *
 * @package Admin
 * @since 1.0.0
 * @author M.Tuhin <info@codesolz.net>
 */

if ( ! defined( 'CS_RTAFAR_VERSION' ) ) {
	exit;
}

use RealTimeAutoFindReplace\lib\Util;


class Scripts_Settings {

	/**
	 * load admin settings scripts
	 */
	public static function load_admin_settings_scripts( $page_id, $rtafr_menu ) {

		$rtafr_menu = apply_filters( 'rtafar_menu_scripts', $rtafr_menu );
		if ( apply_filters( 'bfrp_should_load_form_assets', false, $page_id, $rtafr_menu ) ) {
			wp_enqueue_style(
				'select2',
				CS_RTAFAR_PLUGIN_ASSET_URI . 'plugins/select2/css/select2.min.css',
				array(),
				CS_RTAFAR_VERSION
			);
			wp_enqueue_script(
				'select2',
				CS_RTAFAR_PLUGIN_ASSET_URI . 'plugins/select2/js/select2.min.js',
				array(),
				CS_RTAFAR_VERSION,
				true
			);

			
			wp_enqueue_script(
				'ratfar.ai.features',
				CS_RTAFAR_PLUGIN_ASSET_URI . 'js/rtafar.ai.min.js',
				array(),
				CS_RTAFAR_VERSION,
				true
			);

			// load vars
			wp_localize_script(
				'ratfar.ai.features',
				'ratfar',
				array(
					'ai_icon' => CS_RTAFAR_PLUGIN_ASSET_URI . 'img/ai-technology.png',
				)
			);

		}

		if ( ( isset( $rtafr_menu['replace_in_db'] ) && $page_id == $rtafr_menu['replace_in_db'] )
		) {

			wp_enqueue_script(
				'rtafar.admin.replace.in.db',
				CS_RTAFAR_PLUGIN_ASSET_URI . 'js/rtafar.admin.replace.in.db.min.js',
				array(),
				CS_RTAFAR_VERSION,
				true
			);

			// load vars
			wp_localize_script(
				'rtafar.admin.replace.in.db',
				'repndb',
				array(
					'mgt'    => 'admin\\options\\functions\\DbFuncReplaceInDb@get_tables_in_select_options',
					'mgurls' => 'admin\\options\\functions\\DbFuncReplaceInDb@get_urls_in_select_options',
					'ppoptn' => 'admin\\options\\functions\\DbFuncReplaceInDb@get_db_cols_select_options',
				)
			);
		}

		
		if ( apply_filters( 'bfrp_should_load_page_assets', false, $page_id, $rtafr_menu ) ) {
				wp_enqueue_script(
					'rtafar.app.admin.min',
					CS_RTAFAR_PLUGIN_ASSET_URI . 'js/rtafar.app.admin.min.js',
					array(),
					CS_RTAFAR_VERSION,
					true
				);
		}


		if ( ( isset( $rtafr_menu['media_replacer'] ) && $page_id == $rtafr_menu['media_replacer'] ) 
			) {
				// Enqueue WordPress Media Uploader
				wp_enqueue_media();
				
				wp_enqueue_script(
					'rtafar.media.replacer.min',
					CS_RTAFAR_PLUGIN_ASSET_URI . 'js/rtafar.media.replacer.min.js',
					array(),
					CS_RTAFAR_VERSION,
					true
				);
		}

		wp_enqueue_style( 'wapg', CS_RTAFAR_PLUGIN_ASSET_URI . 'css/rtafar-admin-style.min.css', array(), CS_RTAFAR_VERSION );

		return;
	}


	/**
	 * Check if should load page assets
	 *
	 * @param [type] $should_load
	 * @param [type] $page_id
	 * @param [type] $pages
	 * @return void
	 */	
	public static function bfrpShouldLoadPageAssets( $should_load, $page_id, $pages ) {
		//default plugin pages
		$target_pages = [
			$pages['add_masking_rule'] ?? '',
			$pages['replace_in_db'] ?? '',
			$pages['brafp_license'] ?? '',
			$pages['ai_settings'] ?? '',
		];

		// pre_print( $target_pages);

		return $should_load || in_array( $page_id, $target_pages, true );
	}

	/**
	 * Check if should load form assets
	 *
	 * @param [type] $should_load
	 * @param [type] $page_id
	 * @param [type] $pages
	 * @return void
	 */
	public static function bfrpShouldLoadFormAssets( $should_load, $page_id, $pages ) {
		//default plugin pages
		$target_pages = [
			$pages['add_masking_rule'] ?? '',
			$pages['replace_in_db'] ?? '',
			$pages['media_replacer'] ?? '',
		];

		return $should_load || in_array( $page_id, $target_pages, true );
	}

	/**
	 * admin footer script processor
	 *
	 * @global array $rtafr_menu
	 * @param string $page_id
	 */
	public static function load_admin_footer_script( $page_id, $rtafr_menu ) {

		Util::markup_tag( __( 'admin footer script start', 'real-time-auto-find-and-replace' ) );

		// load form submit script on footer
		if ( ( isset( $rtafr_menu['add_masking_rule'] ) && $page_id == $rtafr_menu['add_masking_rule'] ) ||
		( isset( $rtafr_menu['replace_in_db'] ) && $page_id == $rtafr_menu['replace_in_db'] )
		) {
			// custom scripts here
		}

		Util::markup_tag( __( 'admin footer script end', 'real-time-auto-find-and-replace' ) );

		return;
	}
}
