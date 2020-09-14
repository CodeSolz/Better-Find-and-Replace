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
			'cs_db_string_replace[find]'             => array(
				'title'       => __( 'Find', 'real-time-auto-find-and-replace' ),
				'type'        => 'textarea',
				'class'       => 'form-control',
				'required'    => true,
				'value'       => '',
				'placeholder' => __( 'Enter word to find ', 'real-time-auto-find-and-replace' ),
				'desc_tip'    => __( 'Enter a word you want to find in Database. e.g: _test ', 'real-time-auto-find-and-replace' ),
			),
			'cs_db_string_replace[case_insensitive]' => array(
				'title'    => __( 'Case-Insensitive', 'real-time-auto-find-and-replace' ),
				'type'     => 'checkbox',
				'desc_tip' => __( 'Check this checkbox if you want to find case insensitive or keep it un-check to find case-sensitive. e.g : Shop / shop / SHOP, all will be treated as same if you check this checkbox.', 'real-time-auto-find-and-replace' ),
			),
			'cs_db_string_replace[replace]'          => array(
				'title'       => __( 'Replace With', 'real-time-auto-find-and-replace' ),
				'type'        => 'text',
				'class'       => 'form-control',
				'required'    => true,
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
					'tables' => __( 'Database Tables', 'real-time-auto-find-and-replace' ),
					'urls'   => __( 'URLs', 'real-time-auto-find-and-replace' ),
				),
				'placeholder' => __( 'Select where to find and replace', 'real-time-auto-find-and-replace' ),
				'desc_tip'    => __( 'Select where to find and replace. e.g : Database Tables', 'real-time-auto-find-and-replace' ),
			),
			'db_tables[]'                            => array(
				'wrapper_class' => 'no-border db-tables-wrap',
				'title'         => __( 'Select tables', 'real-time-auto-find-and-replace' ),
				'type'          => 'select',
				'class'         => 'form-control db-tables',
				'multiple'      => true,
				'required'      => true,
				'placeholder'   => __( 'Please select tables', 'real-time-auto-find-and-replace' ),
				'options'       => apply_filters( 'bfrp_selectTables', array() ),
				'desc_tip'      => __( 'Select / Enter table name where you want to replace. e.g : post.', 'real-time-auto-find-and-replace' ),
			),
			'url_options[]'                          => array(
				'wrapper_class' => 'url-options force-hidden',
				'title'         => __( 'Select which url', 'real-time-auto-find-and-replace' ),
				'type'          => 'select',
				'class'         => 'form-control in-which-url',
				'multiple'      => true,
				'placeholder'   => __( 'Please select options', 'real-time-auto-find-and-replace' ),
				'options'       => apply_filters(
					'bfrp_urlOptions',
					array(
						'post'       => __( 'Post URLs', 'real-time-auto-find-and-replace' ),
						'page'       => __( 'Page URLs', 'real-time-auto-find-and-replace' ),
						'attachment' => __( 'Media URLs (images, attachments etc..)', 'real-time-auto-find-and-replace' ),
					)
				),
				'desc_tip'      => __( 'Select / Enter table name where you want to replace. e.g : post', 'real-time-auto-find-and-replace' ),
			),
			'cs_db_string_replace[dry_run]'          => array(
				'title'    => __( 'Dry run', 'real-time-auto-find-and-replace' ),
				'type'     => 'checkbox',
				'desc_tip' => __( 'If If checked, no changes will be made to the database, allowing you to check the results beforehand.', 'real-time-auto-find-and-replace' ),
			),
		);

		$args['content'] = $this->Form_Generator->generate_html_fields( $fields );

		$hidden_fields = array(
			'method'           => array(
				'id'    => 'method',
				'type'  => 'hidden',
				'value' => "admin\\functions\\DbReplacer@db_string_replace",
			),
			'swal_title'       => array(
				'id'    => 'swal_title',
				'type'  => 'hidden',
				'value' => 'Finding & Replacing..',
			),
			'swal_des'         => array(
				'id'    => 'swal_des',
				'type'  => 'hidden',
				'value' => __( 'Please wait a while...', 'real-time-auto-find-and-replace' ),
			),
			'swal_loading_gif' => array(
				'id'    => 'swal_loading_gif',
				'type'  => 'hidden',
				'value' => CS_RTAFAR_PLUGIN_ASSET_URI . 'img/loading-timer.gif',
			),
			'swal_error'       => array(
				'id'    => 'swal_error',
				'type'  => 'hidden',
				'value' => __( 'Something went wrong! Please try again by refreshing the page.', 'real-time-auto-find-and-replace' ),
			),

		);
		$args['hidden_fields'] = $this->Form_Generator->generate_hidden_fields( $hidden_fields );

		$args['btn_text']       = __( 'Find & Replace', 'real-time-auto-find-and-replace' );
		$args['show_btn']       = true;
		$args['body_class']     = 'no-bottom-margin';
		$args['well']           = '<ul>
                        <li> <b>' . __( 'Warning!', 'real-time-auto-find-and-replace' ) . '</b>
                            <ol>
                                <li>'
									. __( 'Replacement in database is permanent. You can\'t un-done it, once it get replaced.', 'real-time-auto-find-and-replace' )
								. '</li>
                            </ol>
                        </li>
					</ul>';
		$args['hidden_content'] = $this->popupHtml();

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

					jQuery("body").on('click', 'a.close', function(){
						$("#popup1").removeClass('show-popup');
					});

					jQuery("#bfrModalContent").scroll(function () {
						var pos = $(this).scrollTop();
						if( pos >= 1 ){
							jQuery( ".bfr-res-head").addClass('change-tbl-head-bg');
						}else{
							jQuery( ".bfr-res-head").removeClass('change-tbl-head-bg');
						}

					});
				});
						
			</script>
		<?php
	}

	/**
	 * Custom Modal
	 *
	 * @return void
	 */
	private function popupHtml() {
		$html = \ob_start();
		?>
			<div id="popup1" class="overlay">
				<div class="popup">
					<h2 class="title">---</h2>
					<p class="sub-title">--</p>
					<a class="close" >&times;</a>
					<div id="bfrModalContent" class="content"><!-- Content --></div>
					<div class="apiResponse"></div>
				</div>
			</div>
		<?php
		$html = ob_get_clean();

		return $html;
	}

}
