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

use RealTimeAutoFindReplace\admin\functions\Masking;

if ( ! \class_exists( 'RTAFAR_WP_Hooks' ) ) {

	class RTAFAR_WP_Hooks {

		function __construct() {

			add_action( 'template_redirect', array( $this, 'rtafar_filter_contents' ) );
		}

		/**
		 * Filter content
		 *
		 * @return void
		 */
		public function rtafar_filter_contents() {
			ob_start(
				array( $this, 'get_filtered_content' )
			);
		}

		/**
		 * Filter content
		 *
		 * @param [type] $buffer
		 * @return void
		 */
		private function get_filtered_content( $buffer ) {
			$replace_rules = Masking::get_rules( 'all' );
			if ( $replace_rules ) {
				foreach ( $replace_rules as $item ) {
					if ( false !== stripos( $item->find, ',' ) ) {
						$finds = explode( ',', $item->find );
						foreach ( $finds as $find ) {
							$buffer = str_replace( $find, $item->replace, $buffer );
						}
					} else {
						$buffer = str_replace( $item->find, $item->replace, $buffer );
					}
				}
			}

			return $buffer;
		}

	}

}
