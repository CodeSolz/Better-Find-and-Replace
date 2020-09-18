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
		wp_enqueue_style( 'sweetalert', CS_RTAFAR_PLUGIN_ASSET_URI . 'plugins/sweetalert/dist/sweetalert.css', array(), CS_RTAFAR_VERSION );
		wp_enqueue_script( 'sweetalert', CS_RTAFAR_PLUGIN_ASSET_URI . 'plugins/sweetalert/dist/sweetalert.min.js', array(), CS_RTAFAR_VERSION, true );

		// pre_print( $page_id );

		if ( $page_id === $rtafr_menu['replace_in_db'] ) {
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

		}

		if ( $page_id == $rtafr_menu['add_masking_rule'] ||
				$page_id == $rtafr_menu['replace_in_db']
			) {
				wp_enqueue_script(
					'rtafar.app.admin.min',
					CS_RTAFAR_PLUGIN_ASSET_URI . 'js/rtafar.app.admin.min.js',
					array(),
					CS_RTAFAR_VERSION,
					true
				);
		}

		wp_enqueue_style( 'wapg', CS_RTAFAR_PLUGIN_ASSET_URI . 'css/rtafar-admin-style.min.css', false );

		return;
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
		if ( $page_id == $rtafr_menu['add_masking_rule'] ||
		$page_id == $rtafr_menu['replace_in_db']
		) {
			// custom scripts here
		}

		Util::markup_tag( __( 'admin footer script end', 'real-time-auto-find-and-replace' ) );

		return;
	}

}


