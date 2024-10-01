<?php namespace RealTimeAutoFindReplace\admin\options\pages\AdvScreenOptions;

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

use RealTimeAutoFindReplace\admin\options\pages\AdvScreenOptions\ScOptnAddNewRule;
use RealTimeAutoFindReplace\admin\options\pages\AdvScreenOptions\ScOptnReplaceInDb;

class ScreenOptions {

	/**
	 * Screen option : Add replacement rule page
	 *
	 * @return void
	 */
	public function rtafar_arr_screen_options() {
		return ScOptnAddNewRule::rtafar_arr_screen_options();
	}

	/**
	 * Screen option : Add replacement rule page
	 *
	 * @return void
	 */
	public function rtafar_screen_options_replace_in_db() {
		return ScOptnReplaceInDb::rtafar_screen_options_replace_in_db();
	}

	/**
	 * Screen option: all masking rules
	 *
	 * @return void
	 */
	public function rtafar_all_rules_screen_options() {
		return ScOptnAllMaskRules::rtafar_screen_options();
	}

	/**
	 * Save screen option - All masking rule Page
	 *
	 * @param [type] $status
	 * @param [type] $option
	 * @param [type] $value
	 * @return void
	 */
	public static function rtafar_set_amr_per_page( $status, $option, $value ) {
		return ScOptnAllMaskRules::rtafar_set_amr_per_page( $status, $option, $value );
	}

	/**
	 * Get amr per page number
	 *
	 * @return void
	 */
	public static function rtafar_get_amr_per_page() {
		return ScOptnAllMaskRules::rtafar_get_amr_per_page();
	}
}
