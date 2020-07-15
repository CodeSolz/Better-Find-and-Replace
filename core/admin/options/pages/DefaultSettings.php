<?php namespace RealTimeAutoFindReplace\admin\options\pages;

/**
 * Class: Add New Coin
 * 
 * @package Admin
 * @since 1.2.4
 * @author CodeSolz <customer-support@codesolz.net>
 */

if ( ! defined( 'CS_RTAAFR_VERSION' ) ) {
    die();
}

use RealTimeAutoFindReplace\lib\Util;
use RealTimeAutoFindReplace\admin\builders\FarFormBuilder;
use RealTimeAutoFindReplace\admin\builders\FarAdminPageBuilder;

class DefaultSettings {
    
    /**
     * Hold page generator class
     *
     * @var type 
     */
    private $Admin_Page_Generator;
    
    /**
     * Form Generator
     *
     * @var type 
     */
    private $Form_Generator;
    
    
    public function __construct(FarAdminPageBuilder $AdminPageGenerator) {
        $this->Admin_Page_Generator = $AdminPageGenerator;
        
        /*create obj form generator*/
        $this->Form_Generator = new FarFormBuilder();
        
        add_action( 'admin_footer', array( $this, 'default_page_scripts'));
    }
    
    /**
     * Generate add new coin page
     * 
     * @param type $args
     * @return type
     */
    public function generate_default_settings( $args ){
        
        $settings = isset($args['gateway_settings']) ? (object)$args['gateway_settings'] : '';
        $option = isset( $settings->defaultOptn ) ? $settings->defaultOptn : '';
        
        $fields = array(
            'cs_db_string_replace[find]'=> array(
                'title'            => __( 'Replacement Rule', 'woo-altcoin-payment-gateway' ),
                'type'             => 'textarea',
                'class'            => "form-control",
                'value'            => FarFormBuilder::get_value( 'description', $option, 'Make your payment directly into our AltCoin address. Your order won’t be shipped until the funds have cleared in our account.'), 
                'placeholder'      => __( 'Enter your payment gateway description', 'woo-altcoin-payment-gateway' ),
                'desc_tip'         => __( 'Enter your payment gateway description. It will show in checkout page.', 'woo-altcoin-payment-gateway' ),
            ),
            'cs_db_string_replace[replace]'=> array(
                'title'            => __( 'Replacement Rule', 'woo-altcoin-payment-gateway' ),
                'type'             => 'textarea',
                'class'            => "form-control",
                'value'            => FarFormBuilder::get_value( 'description', $option, 'Make your payment directly into our AltCoin address. Your order won’t be shipped until the funds have cleared in our account.'), 
                'placeholder'      => __( 'Enter your payment gateway description', 'woo-altcoin-payment-gateway' ),
                'desc_tip'         => __( 'Enter your payment gateway description. It will show in checkout page.', 'woo-altcoin-payment-gateway' ),
            ),
        );
        
        $args['content'] = $this->Form_Generator->generate_html_fields( $fields );
        
        $hidden_fields = array(
            'method'=> array(
                'id'   => 'method',
                'type'  => 'hidden',
                'value' => "admin\\functions\\DbReplacer@db_string_replace"
            ),
            'swal_title'=> array(
                'id' => 'swal_title',
                'type'  => 'hidden',
                'value' => 'Settings Updating'
            ),
            
        );
        $args['hidden_fields'] = $this->Form_Generator->generate_hidden_fields( $hidden_fields );
        
        $args['btn_text'] = 'Find & Replace';
        $args['show_btn'] = true;
        $args['body_class'] = 'no-bottom-margin';
        $args['well'] = "<ul>
                        <li> <b>Basic Hints</b>
                            <ol>
                                <li>
                                    Followings options are the basic settings of the altcoin payment gateway.
                                </li>
                            </ol>
                        </li>
                    </ul>";
        
        return $this->Admin_Page_Generator->generate_page( $args );
    }
 
    /**
     * Add custom scripts
     */
    public function default_page_scripts(){
        ?>
            <script>
                jQuery(document).ready(function($){
                    $.wpMediaUploader( { buttonClass : '.button-secondary' } );
                });
            </script>
        <?php
    }
    
}