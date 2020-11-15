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
use RealTimeAutoFindReplace\admin\functions\Masking;
use RealTimeAutoFindReplace\install\Activate;

class RTAFAR_WP_Hooks {

	function __construct() {

		/*** add settings link */
		add_filter( 'plugin_action_links_' . CS_RTAFAR_PLUGIN_IDENTIFIER, array( __class__, 'rtafarSettingsLink' ) );

		add_action( 'template_redirect', array( $this, 'rtafar_filter_contents' ) );

		/*** add function after upgrade process complete */
		add_action( 'upgrader_process_complete', array( __class__, 'rtafarAfterUpgrade' ), 10, 2 );
	}

	/**
	 * Filter content
	 *
	 * @return void
	 */
	public function rtafar_filter_contents() {
		// ob_get_clean();
		\ob_start(
			array( $this, 'get_filtered_content' )
		);
		// \ob_end_flush();
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
				if ( false !== stripos( $item->find, ',' ) && $item->type != 'regex' && $item->type != 'advance_regex' ) {
					$finds = explode( ',', $item->find );
					foreach ( $finds as $find ) {
						//check bypass filter rule
						if( has_filter( 'bfrp_add_bypass_rule' ) ){
							$buffer = apply_filters( 'bfrp_add_bypass_rule', $item, $buffer, $find );
						}

						$buffer = $this->replace( $item, $buffer, $find );

						if( has_filter( 'bfrp_remove_bypass_rule' ) ){
							$buffer = apply_filters( 'bfrp_remove_bypass_rule', $item, $buffer, $find );
						}

					}
				} else {

					//check bypass filter rule
					if( has_filter( 'bfrp_add_bypass_rule' ) ){
						$buffer = apply_filters( 'bfrp_add_bypass_rule', $item, $buffer, false );
					}
					
					$buffer = $this->replace( $item, $buffer );
					
					if( has_filter( 'bfrp_remove_bypass_rule' ) ){
						$buffer = apply_filters( 'bfrp_remove_bypass_rule', $item, $buffer, false );
					}
					
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
			return preg_replace( $find, $replace, $buffer );
		} elseif ( $item->type == 'advance_regex' ) {
			if ( \has_filter( 'bfrp_advance_regex_mask' ) ) {
				return \apply_filters( 'bfrp_advance_regex_mask', $find, $item->replace, $buffer );
			} else {
				return $buffer;
			}
		} else {
			return \str_replace( $find, $item->replace, $buffer );
		}

	}

	/**
	 * Add settings links
	 *
	 * @param [type] $links
	 * @return void
	 */
	public static function rtafarSettingsLink( $links ) {
		$links[] = '<a href="' .
		Util::cs_generate_admin_url( 'cs-all-masking-rules' ) .
		'">' . __( 'All Rules' ) . '</a>';
		$links[] = '<a href="' .
		Util::cs_generate_admin_url( 'cs-add-replacement-rule' ) .
		'">' . __( 'Add New Rule' ) . '</a>';

		return $links;
	}

	/**
	 * Add function after
	 * plugin upgrade
	 *
	 * @return void
	 */
	public static function rtafarAfterUpgrade( $upgrader_object, $options ) {
		if ( $options['action'] == 'update' && $options['type'] == 'plugin' ) {
			foreach ( $options['plugins'] as $eachPlugin ) {
				if ( $eachPlugin == CS_RTAFAR_PLUGIN_IDENTIFIER ) {
					Activate::onUpgrade();
				}
			}
		}
	}

}

