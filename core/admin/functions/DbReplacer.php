<?php namespace RealTimeAutoFindReplace\admin\functions;

/**
 * From Builder Class
 * 
 * @package Builder 
 * @author CodeSolz <info@codesolz.com>
 */

if ( ! defined( 'CS_RTAAFR_VERSION' ) ) {
    exit;
}



class DbReplacer{

    public static function db_string_replace( $user_query ){

        $find = $user_query['cs_db_string_replace']['find'];
        $replace = $user_query['cs_db_string_replace']['replace'];
        
        global $wpdb;

        $i =0;
        $get_data = $wpdb->get_results("select * from {$wpdb->postmeta} where meta_key = '_elementor_data' ");
        if($get_data) {
            foreach( $get_data as $item ){

                $patterns = array();
                $patterns[0] = '/http:/';
                $patterns[1] = '/yepcoinstore.codesolzlab.website/';
                $replacements = array();
                $replacements[0] = 'https:';
                $replacements[1] = 'yepshop.io';
        
                $new_string = \preg_replace( $patterns, $replacements, $item->meta_value );
        
                // $strpos = \strpos( $get_data->meta_value, 'yepcoinstore');
        
                
                $wpdb->update( $wpdb->postmeta, array( 'meta_value' => $new_string ), array( 'meta_id' => $item->meta_id ) );
                $i++;
            }
        }

        
        return wp_send_json(array(
            'success' => true,
            'response' => __( 'Thank you! replacement completed successfully. Total replaced : ' . $i, 'woo-altcoin-payment-gateway' )
        ));


    }

}



?>

