<?php namespace RealTimeAutoFindReplace\admin\functions;

/**
 * From Builder Class
 *
 * @package Funcitons
 * @since 1.0.0
 * @author M.Tuhin <info@codesolz.net>
 */

if ( ! defined( 'CS_RTAFAR_VERSION' ) ) {
	exit;
}

use RealTimeAutoFindReplace\lib\Util;


class Masking {

	/**
	 * Add Masking Rules
	 *
	 * @param [type] $user_query
	 * @return void
	 */
	public function add_masking_rule( $user_query ) {

		$user_query    = $user_query['cs_masking_rule'];
		$find          = isset( $user_query['find'] ) ? $user_query['find'] : '';
		$replace       = isset( $user_query['replace'] ) ? $user_query['replace'] : '';
		$type          = isset( $user_query['type'] ) ? $user_query['type'] : '';
		$replace_where = isset( $user_query['where_to_replace'] ) ? $user_query['where_to_replace'] : '';
		$delay_time    = isset( $user_query['delay'] ) ? (float) $user_query['delay'] : '';
		$tag_selector  = isset( $user_query['tag_selector'] ) ? $user_query['tag_selector'] : '';

		$id = isset( $user_query['id'] ) ? $user_query['id'] : '';

		$msg = $this->insert_masking_rules( $find, $replace, $type, $replace_where, $id, $delay_time, $tag_selector );

		return wp_send_json(
			array(
				'status'       => true,
				'title'        => 'Success!',
				'text'         => __( "Thank you! replacement rule {$msg} successfully.", 'real-time-auto-find-and-replace' ),
				'redirect_url' => admin_url( 'admin.php?page=cs-all-masking-rules' ),
			)
		);
	}

	/**
	 * Add Masking Rules
	 *
	 * @return void
	 */
	public function insert_masking_rules( $find, $replace, $type, $replace_where, $id = '', $delay_time, $tag_selector ) {
		global $wpdb;

		if ( $type == 'regex' || $type == 'advance_regex' ) {
			$find    = Util::cs_addslashes( $find );
			$replace = Util::cs_addslashes( $replace );
		} else {
			$find    = Util::check_evil_script( $find );
			$replace = Util::check_evil_script( $replace );
		}

		$userData = array(
			'find'             => $find,
			'replace'          => $replace,
			'type'             => Util::check_evil_script( $type ),
			'where_to_replace' => Util::check_evil_script( $replace_where ),
			'delay'            => $delay_time,
			'tag_selector'     => $tag_selector,
		);

		$isExists = $wpdb->get_var(
			$wpdb->prepare(
				"select id from {$wpdb->prefix}rtafar_rules where find = '%s' ",
				$find
			)
		);

		$msg = ' added ';
		if ( $isExists || ! empty( $id ) ) {
			$isExists = $id;
			$msg      = ' updated ';
			$wpdb->update( "{$wpdb->prefix}rtafar_rules", $userData, array( 'id' => $isExists ) );
		} else {
			$wpdb->insert( "{$wpdb->prefix}rtafar_rules", $userData );
		}

		return $msg;
	}

	/**
	 * Get rules
	 *
	 * @return void
	 */
	public static function get_rules( $where_to_replace = 'all', $id = '', $rule_type = false ) {
		global $wpdb;

		$where_id = '';
		if ( $id ) {
			$where_id = "and id = {$id}";
		}

		$ruleType = '';
		if ( $rule_type ) {
			$ruleType = " and type = '{$rule_type}' ";
		} else {
			$ruleType = " and type != 'ajaxContent' "; // get all but not ajaxRules
		}

		$get_rules = $wpdb->get_results(
			"SELECT * from `{$wpdb->prefix}rtafar_rules` as r where where_to_replace = '{$where_to_replace}' {$where_id} {$ruleType} order by id asc"
		);

		if ( $get_rules ) {
			return $get_rules;
		}
		return false;
	}

}


