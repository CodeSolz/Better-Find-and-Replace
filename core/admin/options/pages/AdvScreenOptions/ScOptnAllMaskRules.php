<?php namespace RealTimeAutoFindReplace\admin\options\pages\AdvScreenOptions;

use RealTimeAutoFindReplace\lib\Util;

/**
 * Class: Screen options
 *
 * @package Options
 * @since 1.3.8
 * @author M.Tuhin <info@codesolz.net>
 */

if ( ! defined( 'CS_RTAFAR_VERSION' ) ) {
	die();
}


class ScOptnAllMaskRules {

	private static $amr_per_page_optn_id = 'bfrp_all_masking_list_per_page';

	/**
	 * screen option
	 *
	 * @return void
	 */
	public static function rtafar_screen_options() {
		\add_screen_option(
			'per_page',
			array(
				'label'   => esc_html__( 'Number of items per page : ', 'real-time-auto-find-and-replace' ),
				'default' => 10,
				'option'  => self::$amr_per_page_optn_id,
			)
		);

		$screen = \get_current_screen();
		// if ( self::help_tabs() ) {
			// foreach ( self::help_tabs() as $tab ) {
			// $tab = (object) $tab;
			// $screen->add_help_tab(
			// array(
			// 'id'       => $tab->id,
			// 'title'    => $tab->title,
			// 'content'  => $tab->content,
			// 'callback' => $tab->callback,
			// 'priority' => $tab->priority,
			// )
			// );
			// }
		// }
		// $screen->set_help_sidebar( self::amr_help_sidebar_content() );
	}


	/**
	 * Set amr per page
	 *
	 * @param [type] $status
	 * @param [type] $option
	 * @param [type] $value
	 * @return void
	 */
	public static function rtafar_set_amr_per_page( $status, $option, $value ) {
		return $value;
	}

	/**
	 * Get amr per page item number
	 *
	 * @return void
	 */
	public static function rtafar_get_amr_per_page() {
		$current_user_id = Util::bfar_get_current_user_id();
		if ( $current_user_id ) {
			return \get_user_meta( $current_user_id, self::$amr_per_page_optn_id, true );
			return false;
		}

		return false;
	}


}
