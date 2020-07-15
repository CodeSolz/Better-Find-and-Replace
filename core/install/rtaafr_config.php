<?php
/**
 * Config
 * 
 * @package DB
 * @since 1.0.8
 * @author CodeSolz <customer-service@codesolz.com>
 */

if ( ! defined( 'CS_WAPG_VERSION' ) ) {
   exit;
}

global $rtaafr_tables, $rtaafr_current_db_version, $wpdb;

//assign db version globally in variable
$wapg_current_db_version = CS_RTAAFR_DB_VERSION;

/**
 * load custom table names
 */
if( ! isset( $wapg_tables ) ){
    $wapg_tables = array(
        'replace_rules' => $wpdb->prefix . 'rtaafr_replace_rules',
    );
}