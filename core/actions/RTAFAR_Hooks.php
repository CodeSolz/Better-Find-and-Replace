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

use RealTimeAutoFindReplace\admin\functions\ProActions;
use RealTimeAutoFindReplace\admin\options\Scripts_Settings;

class RTAFAR_Hooks {


	public function __construct() {

		/*** update url options */
		add_filter( 'bfrp_url_types', array( $this, 'getAllProUrlOptions' ), 10 );

		/*** table list options */
		add_filter( 'bfrp_select_tables', array( $this, 'getAllTblList' ), 10 );

		/** should load common page assets */
		add_filter( 'bfrp_should_load_page_assets', array( $this, 'bfrpShouldLoadPageAssets'), 10, 3 );
		add_filter( 'bfrp_should_load_form_assets', array( $this, 'bfrpShouldLoadFormAssets'), 10, 3 );
	}

	/**
	 * Get url types
	 *
	 * @param [type] $args
	 * @return void
	 */
	public function getAllProUrlOptions( $args ) {
		return ProActions::getAllProUrlOptions( $args, 'selectOptions' );
	}

	/**
	 * Get all table list
	 *
	 * @param [type] $args
	 * @return void
	 */
	public function getAllTblList( $args ) {
		return ProActions::getAllTblList( $args );
	}

	/**
	 * Filter menu
	 *
	 * @param [type] $rtafr_menu
	 * @return void
	 */
	public function bfrpShouldLoadPageAssets( $should_load, $page_id, $pages ) {
		return Scripts_Settings::bfrpShouldLoadPageAssets( $should_load, $page_id, $pages );
	}
	/**
	 * Filter menu
	 *
	 * @param [type] $rtafr_menu
	 * @return void
	 */
	public function bfrpShouldLoadFormAssets( $should_load, $page_id, $pages ) {
		return Scripts_Settings::bfrpShouldLoadFormAssets( $should_load, $page_id, $pages );
	}
}
