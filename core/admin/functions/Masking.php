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

if( ! \class_exists( 'Masking' ) ){ 

class Masking{

    /**
     * Add Masking Rules
     *
     * @param [type] $user_query
     * @return void
     */
    public function add_masking_rule( $user_query ){

        $find = $user_query['cs_masking_rule']['find'];
        $replace = $user_query['cs_masking_rule']['replace'];
        $type = $user_query['cs_masking_rule']['type'];
        $replace_where = $user_query['cs_masking_rule']['where_to_replace'];
        
        $id = isset( $user_query['cs_masking_rule']['id'] ) ? $user_query['cs_masking_rule']['id'] : '';
        
        $msg = $this->insert_masking_rules( $find, $replace, $type, $replace_where, $id);

        return wp_send_json(array(
            'status' => true,
            'title' => 'Success!',
            'text' => __( "Thank you! replacement rule {$msg} successfully.", 'real-time-auto-find-and-replace' ),
            'redirect_url' => admin_url( 'admin.php?page=cs-all-masking-rules')
        ));
    }

    /**
     * Add Masking Rules
     *
     * @return void
     */
    public function insert_masking_rules( $find, $replace, $type, $replace_where, $id = ''){
        global $wpdb;
        $userData = array(
            'find' => util::check_evil_script( $find ),
            'replace' => util::check_evil_script( $replace ),
            'type' => util::check_evil_script( $type ),
            'where_to_replace' => util::check_evil_script( $replace_where ),
        );

        $isExists = $wpdb->get_var( $wpdb->prepare( 
            "select id from {$wpdb->prefix}rtafar_rules where find = '%s' ",
            $find
        ));

        $msg = ' added ';
        if( $isExists || !empty( $id ) ) {
            $isExists = $id;
            $msg = ' updated ';
            $wpdb->update( "{$wpdb->prefix}rtafar_rules", $userData, array( 'id' => $isExists) );
        }else{
            $wpdb->insert( "{$wpdb->prefix}rtafar_rules", $userData );
        }

        return $msg;
    }

    /**
     * Get rules
     *
     * @return void
     */
    public static function get_rules( $rule_type = 'all', $id = ''){
        global $wpdb;

        $where_id = '';
        if( $id ){
            $where_id = "and id = {$id}";
        }

        $get_rules = $wpdb->get_results( $wpdb->prepare(  
            "select * from {$wpdb->prefix}rtafar_rules where where_to_replace = '%s' {$where_id} order by id asc",
            $rule_type
        ));
        if( $get_rules ){
            return $get_rules;
        }
        return false;
    }

}

}

