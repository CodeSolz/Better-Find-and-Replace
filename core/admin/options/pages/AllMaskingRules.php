<?php namespace RealTimeAutoFindReplace\admin\options\pages;

/**
 * Class: All Masking List
 *
 * @package Admin
 * @since 1.2.4
 * @author CodeSolz <customer-support@codesolz.net>
 */

if ( ! defined( 'CS_RTAFAR_VERSION' ) ) {
	die();
}

use RealTimeAutoFindReplace\lib\Util;
use RealTimeAutoFindReplace\admin\builders\AdminPageBuilder;
use RealTimeAutoFindReplace\admin\options\functions\AllMaskingRulesList;


class AllMaskingRules {

	/**
	 * Hold page generator class
	 *
	 * @var type
	 */
	private $Admin_Page_Generator;

	public function __construct( AdminPageBuilder $AdminPageGenerator ) {
		$this->Admin_Page_Generator = $AdminPageGenerator;
	}

	/**
	 * Generate all coins list
	 *
	 * @param type $args
	 * @return type
	 */
	public function generate_page( $args ) {

		$page = isset( $_GET['page'] ) ? Util::check_evil_script( $_GET['page'] ) : '';
		if ( isset( $_GET['s'] ) && ! empty( $_GET['s'] ) ) {
			$back_url     = Util::cs_generate_admin_url( $page );
			$args['well'] = "<p class='search-keyword'>Search results for : '<b>" . Util::check_evil_script( $_GET['s'] ) . "</b>' </p> <a href='{$back_url}' class='button'><< Back to all</a> ";
		}

		ob_start();
		$adCodeList = new AllMaskingRulesList();
		$adCodeList->prepare_items();
		echo '<form id="plugins-filter" method="get"><input type="hidden" name="page" value="' . $page . '" />';
		$adCodeList->views();
		$adCodeList->search_box( __( 'Search Rule', 'real-time-auto-find-and-replace' ), '' );
		$adCodeList->display();
		echo '</form>';
		$html = ob_get_clean();

		$args['content'] = $html;

		return $this->Admin_Page_Generator->generate_page( $args );
	}


}


