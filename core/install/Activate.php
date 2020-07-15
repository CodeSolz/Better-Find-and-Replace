<?php namespace WooGateWayCoreLib\install;
/**
 * Installation Functions
 * 
 * @package DB
 * @since 1.0.8
 * @author CodeSolz <customer-service@codesolz.com>
 */

if ( ! defined( 'CS_WAPG_VERSION' ) ) {
   exit;
}

use WooGateWayCoreLib\admin\functions\CsAdminQuery;
use WooGateWayCoreLib\admin\functions\WooFunctions;
use WooGateWayCoreLib\admin\functions\CsPaymentGateway;

class Activate{
    
    /**
     * On install Create table
     * 
     * @global type $wpdb
     */
    public static function on_activate(){
        global $wpdb, $wapg_tables;
        $charset_collate = $wpdb->get_charset_collate();
        
        $sqls = array(
            "CREATE TABLE IF NOT EXISTS `{$wapg_tables['coins']}`(
            `id` int(11) NOT NULL auto_increment,
            `name` varchar(56),
            `coin_web_id` varchar(56),
            `symbol` varchar(20),
            `coin_type` varchar(1) DEFAULT 1,
            `checkout_type` char(1),
            `status` char(1),
            PRIMARY KEY ( `id`)
            ) $charset_collate",
            "CREATE TABLE IF NOT EXISTS `{$wapg_tables['addresses']}`(
            `id` int(11) NOT NULL auto_increment,
            `coin_id` int(11),
            `address` varchar(1024),
            `lock_status` char(1),  
            PRIMARY KEY ( `id`)
            ) $charset_collate",
            "CREATE TABLE IF NOT EXISTS `{$wapg_tables['offers']}`(
            `id` int(11) NOT NULL auto_increment,
            `coin_id` int(11),
            `offer_amount` int(11),
            `offer_type` char(1),
            `offer_status` char(1),  
            `offer_show_on_product_page` char(1),  
            `offer_start` datetime,  
            `offer_end` datetime,  
            PRIMARY KEY ( `id`)
            ) $charset_collate",
            "CREATE TABLE IF NOT EXISTS `{$wapg_tables['coin_trxids']}`(
            `id` bigint(20) NOT NULL auto_increment,
            `cart_hash` varchar(128),
            `transaction_id` varchar(1024),
            `secret_word` varchar(1024),
            `used_in` datetime,  
            PRIMARY KEY ( `id`)
            ) $charset_collate"
        );
        
        foreach ( $sqls as $sql ) {
            if ( $wpdb->query( $sql ) === false ){
                continue;
            }
        }    
        
        //add db version to db
        add_option( 'wapg_db_version', CS_WAPG_DB_VERSION );
        
    }
    

    
}

