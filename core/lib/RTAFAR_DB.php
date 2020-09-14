<?php namespace RealTimeAutoFindReplace\lib;

/**
 * Util Functions
 *
 * @package Library
 * @since 1.0.0
 * @author CodeSolz <customer-service@codesolz.com>
 */

if ( ! defined( 'CS_RTAFAR_VERSION' ) ) {
	exit;
}


class RTAFAR_DB{

    /**
     * Get all tables in db
     *
     * @return void
     */
    public static function get_tables() {
		global $wpdb;
        return $wpdb->get_col( 'SHOW TABLES' );
    }
    
    /**
     * Get tables size
     *
     * @return void
     */
    public static function get_sizes( $type = '' ) {
		global $wpdb;

		$sizes 	= array();
        $active = [];
		$tables	= $wpdb->get_results( 'SHOW TABLE STATUS', ARRAY_A );

		if ( is_array( $tables ) && ! empty( $tables ) ) {
            
            foreach ( $tables as $table ) {
                $size = round( $table['Data_length'] / 1024 / 1024, 2 );
                
                $isActive = '';
                if( $type && ! \in_array( $table['Name'], self::freeVersionTbls() ) ){
                    $isActive = '_disabled';
                    $sizes[$table['Name'] . $isActive ] = sprintf( __( '%s (%s MB) - Pro version only!', 'real-time-auto-find-and-replace' ), $table['Name'], $size );
                }else{
                    $active[$table['Name'] . $isActive ] = sprintf( __( '%s (%s MB)', 'real-time-auto-find-and-replace' ), $table['Name'], $size );
                }
			}

		}

		return $active + $sizes;
    }
    
    /**
     * Get Free Version 
     * Tables list
     *
     * @return void
     */
    private static function freeVersionTbls(){
        global $wpdb;
        return array(
            $wpdb->base_prefix . 'posts',
            $wpdb->base_prefix . 'postmeta',
            $wpdb->base_prefix . 'options'
        );
    }

}