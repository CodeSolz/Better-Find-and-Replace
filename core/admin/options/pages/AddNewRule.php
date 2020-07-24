<?php namespace RealTimeAutoFindReplace\admin\options\pages;

/**
 * Class: Add New Rule
 * 
 * @package Options
 * @since 1.0.0
 * @author M.Tuhin <info@codesolz.net>
 */

if ( ! defined( 'CS_RTAFAR_VERSION' ) ) {
    die();
}

use RealTimeAutoFindReplace\lib\Util;
use RealTimeAutoFindReplace\admin\functions\Masking;
use RealTimeAutoFindReplace\admin\builders\FormBuilder;
use RealTimeAutoFindReplace\admin\builders\AdminPageBuilder;

if( ! \class_exists( 'AddNewRule' ) ){ 

class AddNewRule {
    
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
    
    
    public function __construct(AdminPageBuilder $AdminPageGenerator) {
        $this->Admin_Page_Generator = $AdminPageGenerator;
        
        /*create obj form generator*/
        $this->Form_Generator = new FormBuilder();
        
    }

    /**
     * Generate add new coin page
     * 
     * @param type $args
     * @return type
     */
    public function generate_page( $args, $option ){
        
        $fields = array(
            'cs_masking_rule[find]'=> array(
                'title'            => __( 'Find', 'real-time-auto-find-and-replace' ),
                'type'             => 'textarea',
                'class'            => "form-control",
                'value'            =>   FormBuilder::get_value( 'find', $option, ''), 
                'placeholder'      => __( 'Set find rules', 'real-time-auto-find-and-replace' ),
                'desc_tip'         => __( 'Enter your word (case-sensitive) what do you want to find.  Add single or comma separated multiple words. e.g: Shop, Store', 'real-time-auto-find-and-replace' ),
            ),
            'cs_masking_rule[replace]'=> array(
                'title'            => __( 'Replace With', 'real-time-auto-find-and-replace' ),
                'type'             => 'text',
                'class'            => "form-control",
                'value'            =>   FormBuilder::get_value( 'replace', $option, ''), 
                'placeholder'      => __( 'set replace rule', 'real-time-auto-find-and-replace' ),
                'desc_tip'         => __( 'Enter a word what do you want to replace with. e.g: My Store', 'real-time-auto-find-and-replace' ),
            ),
            'cs_masking_rule[type]'     => array(
                'title'                     => __( 'Rule\'s Type', 'real-time-auto-find-and-replace' ),
                'type'                      => 'select',
                'class'                     => "form-control coin-type-select",
                'required'                  => true,
                'placeholder'               => __( 'Please select rules type', 'real-time-auto-find-and-replace' ),
                'options'                   => array(
                                                    'plain' => __( 'Plain Text', 'real-time-auto-find-and-replace' ),
                                                    'regx_disabled' => __( 'Regular Expression', 'real-time-auto-find-and-replace' )
                                                ),
                'value'                     => FormBuilder::get_value( 'type', $option, ''),
                'desc_tip'                  => __( 'Select rule\'s type. e.g : Plain Text', 'real-time-auto-find-and-replace' )
            ),
            'cs_masking_rule[where_to_replace]'     => array(
                'title'                     => __( 'Where To Replace', 'real-time-auto-find-and-replace' ),
                'type'                      => 'select',
                'class'                     => "form-control coin-type-select",
                'required'                  => true,
                'placeholder'               => __( 'Please select where to replace', 'real-time-auto-find-and-replace' ),
                'options'                   => array(
                                                    'all' => __( 'All over the website', 'real-time-auto-find-and-replace' ),
                                                    'posts_disabled' => __( 'All Blog Posts', 'real-time-auto-find-and-replace' ),
                                                    'pages_disabled' => __( 'All Pages', 'real-time-auto-find-and-replace' ),
                                                    'comments_disabled' => __( 'All Comments', 'real-time-auto-find-and-replace' ),
                                                ),
                'value'                     => FormBuilder::get_value( 'where_to_replace', $option, ''),
                'desc_tip'                  => __( 'Select rule\'s type. e.g : All over the website', 'real-time-auto-find-and-replace' )
            ),
            
          
        );
        
        $args['content'] = $this->Form_Generator->generate_html_fields( $fields );
        
        $swal_title = __( 'Adding Rule', 'real-time-auto-find-and-replace' );
        $btn_txt = __( 'Add Rule', 'real-time-auto-find-and-replace' );
        $update_hidden_fields = [];
        if( !empty($option)){
            $swal_title = __( 'Updating Rule', 'real-time-auto-find-and-replace' );
            $btn_txt = __( 'Update Rule', 'real-time-auto-find-and-replace' );
            
            $update_hidden_fields = array(
                'cs_masking_rule[id]' => array(
                    'id' => 'rule_id',
                    'type' => 'hidden',
                    'value' => $option[ 'id']
                )
            );
            
        }
        
        $hidden_fields = array_merge_recursive( array(
            'method'=> array(
                'id'   => 'method',
                'type'  => 'hidden',
                'value' => "admin\\functions\\Masking@add_masking_rule"
            ),
            'swal_title'=> array(
                'id' => 'swal_title',
                'type'  => 'hidden',
                'value' => $swal_title
            )
        ), $update_hidden_fields );
        
        $args['hidden_fields'] = $this->Form_Generator->generate_hidden_fields( $hidden_fields );
        
        $args['btn_text'] = $btn_txt;
        $args['show_btn'] = true;
        $args['body_class'] = 'no-bottom-margin';
        
        return $this->Admin_Page_Generator->generate_page( $args );
    }
    
}

}