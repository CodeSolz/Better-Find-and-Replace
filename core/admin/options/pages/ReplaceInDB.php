<?php namespace RealTimeAutoFindReplace\admin\options\pages;

/**
 * Class: Replace in db
 *
 * @package Admin
 * @since 1.0.0
 * @author M.Tuhin <info@codesolz.net>
 */

if ( ! defined( 'CS_RTAFAR_VERSION' ) ) {
	die();
}

use RealTimeAutoFindReplace\lib\Util;
use RealTimeAutoFindReplace\admin\builders\FormBuilder;
use RealTimeAutoFindReplace\admin\builders\AdminPageBuilder;

class ReplaceInDB {

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


	public function __construct( AdminPageBuilder $AdminPageGenerator ) {
		$this->Admin_Page_Generator = $AdminPageGenerator;

		/*create obj form generator*/
		$this->Form_Generator = new FormBuilder();

		add_action( 'admin_footer', array( $this, 'default_page_scripts' ) );
	}

	/**
	 * Generate add new coin page
	 *
	 * @param type $args
	 * @return type
	 */
	public function generate_default_settings( $args ) {

		$settings = isset( $args['gateway_settings'] ) ? (object) $args['gateway_settings'] : '';
		$option   = isset( $settings->defaultOptn ) ? $settings->defaultOptn : '';

		$fields = array(
			'cs_db_string_replace[find]'    => array(
				'title'       => __( 'Find', 'real-time-auto-find-and-replace' ),
				'type'        => 'textarea',
				'class'       => 'form-control',
				'required'       => true,
				'value'       => '',
				'placeholder' => __( 'Enter word to find ', 'real-time-auto-find-and-replace' ),
				'desc_tip'    => __( 'Enter a word you want to find in Database. e.g: _test ', 'real-time-auto-find-and-replace' ),
			),
			'cs_db_string_replace[replace]' => array(
				'title'       => __( 'Replace With', 'real-time-auto-find-and-replace' ),
				'type'        => 'text',
				'class'       => 'form-control',
				'required'       => true,
				'value'       => '',
				'placeholder' => __( 'Enter word to replace with', 'real-time-auto-find-and-replace' ),
				'desc_tip'    => __( 'Enter word you want to replace with. e.g : test', 'real-time-auto-find-and-replace' ),
			),
			'cs_db_string_replace[where_to_replace]' => array(
				'title'       => __( 'Where to Replace', 'real-time-auto-find-and-replace' ),
				'type'        => 'select',
				'class'       => 'form-control where-to-replace',
				'required'    => true,
				'options'     => array(
					'tables'                => __( 'Database Tables', 'real-time-auto-find-and-replace' ),
					'urls'                => __( 'URLs', 'real-time-auto-find-and-replace' )
				),
				'placeholder' => __( 'Select where to find and replace', 'real-time-auto-find-and-replace' ),
				'desc_tip'    => __( 'Select where to find and replace. e.g : Database Tables', 'real-time-auto-find-and-replace' ),
			),
			'db_tables[]'                   => array(
				'wrapper_class'	  => 'no-border db-tables-wrap',
				'title'       => __( 'Select tables', 'woo-altcoin-payment-gateway' ),
				'type'        => 'select',
				'class'       => 'form-control db-tables',
				'multiple'    => true,
				'required'       => true,
				'placeholder' => __( 'Please select tables', 'woo-altcoin-payment-gateway' ),
				'options'     => array(
					'posts' => __( 'Posts', 'real-time-auto-find-and-replace' ),
					'postmeta' => __( 'Postmeta', 'real-time-auto-find-and-replace' ),
					'options' => __( 'Options', 'real-time-auto-find-and-replace' ),
				),
				'desc_tip'    => __( 'Select / Enter table name where you want to replace. e.g : post.', 'woo-altcoin-payment-gateway' ),
			),
			'url_options[]'                   => array(
				'wrapper_class'	  => 'url-options force-hidden',
				'title'       => __( 'Select which url', 'woo-altcoin-payment-gateway' ),
				'type'        => 'select',
				'class'       => 'form-control in-which-url',
				'multiple'    => true,
				'placeholder' => __( 'Please select options', 'woo-altcoin-payment-gateway' ),
				'options'     => array(
					'posts' => __( 'Post URLs', 'real-time-auto-find-and-replace' ),
					'pages' => __( 'Page URLs', 'real-time-auto-find-and-replace' ),
					'media' => __( 'Media URLs (images, attachments etc..)', 'real-time-auto-find-and-replace' )
				),
				'desc_tip'    => __( 'Select / Enter table name where you want to replace. e.g : post', 'woo-altcoin-payment-gateway' ),
			),
		);

		$args['content'] = $this->Form_Generator->generate_html_fields( $fields );

		$hidden_fields = array(
			'method'     => array(
				'id'    => 'method',
				'type'  => 'hidden',
				'value' => "admin\\functions\\DbReplacer@db_string_replace",
			),
			'swal_title' => array(
				'id'    => 'swal_title',
				'type'  => 'hidden',
				'value' => 'Finding & Replacing..',
			),

		);
		$args['hidden_fields'] = $this->Form_Generator->generate_hidden_fields( $hidden_fields );

		$args['btn_text']   = 'Find & Replace';
		$args['show_btn']   = true;
		$args['body_class'] = 'no-bottom-margin';
		$args['well']       = "<ul>
                        <li> <b>Warning!</b>
                            <ol>
                                <li>
                                    Replacement in database is permanent. You can't un-done it, once it get replaced.
                                </li>
                            </ol>
                        </li>
                    </ul>";

		return $this->Admin_Page_Generator->generate_page( $args );
	}

	/**
	 * Add custom scripts
	 */
	public function default_page_scripts() {
		?>
			<script>
				jQuery(document).ready(function($) {
					$('.db-tables, .in-which-url').select2();
					
					jQuery("body").on('change', '.where-to-replace', function(){
						var currVal = jQuery(this).val();
						if( currVal === 'tables' ){
							jQuery(".url-options").addClass('force-hidden');
							jQuery(".db-tables-wrap").removeClass('force-hidden');
							jQuery(".in-which-url").removeAttr('required');
							jQuery(".db-tables").attr('required', 'required');
						}
						else if( currVal === 'urls' ){
							jQuery(".url-options").removeClass('force-hidden');
							jQuery(".db-tables-wrap").addClass('force-hidden');
							jQuery(".in-which-url").attr('required', 'required');
							jQuery(".db-tables").removeAttr('required');
						}

						// $('.db-tables, .in-which-url').select2();

					});

				});
			</script>
		<?php
	}

}
