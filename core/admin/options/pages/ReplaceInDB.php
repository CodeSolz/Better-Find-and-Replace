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


use RealTimeAutoFindReplace\admin\functions\DbReplacer;

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
				'placeholder' => __( 'Enter a word or phrase to find', 'real-time-auto-find-and-replace' ),
				'desc_tip'    => __( 'Enter the word or phrase you want to find in the database. e.g. _test', 'real-time-auto-find-and-replace' ),
			),

			'cs_db_string_replace[replace]'          => array(
				'title'       => __( 'Replace With', 'real-time-auto-find-and-replace' ),
				'type'        => 'text',
				'class'       => 'form-control',
				'value'       => '',
				'placeholder' => __( 'Enter a word or phrase to replace with', 'real-time-auto-find-and-replace' ),
				'desc_tip'    => __( 'Enter the word or phrase you want to replace it with. e.g. test', 'real-time-auto-find-and-replace' ),
			),
			'cs_db_string_replace[where_to_replace]' => array(
				'title'       => __( 'Where to Replace', 'real-time-auto-find-and-replace' ),
				'type'        => 'select',
				'class'       => 'form-control where-to-replace',
				'required'    => true,
				'options'     => apply_filters(
					'bfrp_where_to_replace_options',
					array(
						'tables' => __( 'Database Tables', 'real-time-auto-find-and-replace' ),
						'urls'   => __( 'All URLs', 'real-time-auto-find-and-replace' ),
						'page'   => __( 'In All Pages', 'real-time-auto-find-and-replace' ),
						'post'   => __( 'In All Posts', 'real-time-auto-find-and-replace' ),
					)
				),
				'placeholder' => __( 'Select where to find and replace', 'real-time-auto-find-and-replace' ),
				'desc_tip'    => __( 'Select where to find and replace. e.g. Database Tables', 'real-time-auto-find-and-replace' ),
			),
			'page_post_filters[]'                    => array(
				'wrapper_class' => 'no-border page-post-filters-wrap force-hidden',
				'title'         => __( 'Select Options / Filters', 'real-time-auto-find-and-replace' ),
				'type'          => 'select',
				'class'         => 'form-control page-post-filters',
				'multiple'      => true,
				'required'      => false,
				'placeholder'   => __( 'Please select options', 'real-time-auto-find-and-replace' ),
				'options'       => '', // loads dynamically
				'desc_tip'      => __( 'Select the fields you want to replace in. This narrows the search to make the results more accurate. e.g. Page title.', 'real-time-auto-find-and-replace' ),
			),
			'cs_db_string_replace[large_table]'      => array(
				'wrapper_class'     => 'large-table',
				// Translators: %1$s is a line break with a span element, %2$s is the closing span tag.
				'title'             => sprintf( __( 'Large Table %1$s Pro Extend version only %2$s', 'real-time-auto-find-and-replace' ), '<br/><span class="pro-version-only">', '</span>' ),
				'type'              => 'checkbox',
				'is_pro'            => true,
				'pro_plan'          => '34',
				'class'             => 'large-table',
				'custom_attributes' => array(
					'disabled' => 'disabled',
				),
				'desc_tip'          => __( 'Check this box if you want to search in a large table. Select one table at a time.', 'real-time-auto-find-and-replace' ),
			),
			'db_tables[]'                            => array(
				'wrapper_class' => 'no-border db-tables-wrap',
				'title'         => __( 'Select tables', 'real-time-auto-find-and-replace' ),
				'type'          => 'select',
				'class'         => 'form-control db-tables',
				'multiple'      => true,
				'required'      => true,
				'placeholder'   => __( 'Please select tables', 'real-time-auto-find-and-replace' ),
				'options'       => '', // loads dynamically
				'desc_tip'      => __( 'Select the tables you want to replace in. e.g. posts', 'real-time-auto-find-and-replace' ),
			),
			'bfrp_page_builders[]'                   => array(
				'wrapper_class' => 'no-border bfrp-page-builders-wrap force-hidden',
				'title'         => __( 'Page Builders (optional)', 'real-time-auto-find-and-replace' ),
				'type'          => 'select',
				'class'         => 'form-control bfrp-page-builders',
				'multiple'      => true,
				'required'      => false,
				'placeholder'   => __( 'Select page builders to target', 'real-time-auto-find-and-replace' ),
				'options'       => '', // loads dynamically
				'desc_tip'      => __( 'Optional. Select the page builders used on your site (Elementor, Beaver Builder, etc.). This matches their escaped JSON content and regenerates their caches after a live replace. Leaving it empty still works for normal content.', 'real-time-auto-find-and-replace' ),
			),
			'url_options[]'                          => array(
				'wrapper_class' => 'url-options force-hidden',
				'title'         => __( 'Select which url', 'real-time-auto-find-and-replace' ),
				'type'          => 'select',
				'class'         => 'form-control in-which-url',
				'multiple'      => true,
				'placeholder'   => __( 'Please select options', 'real-time-auto-find-and-replace' ),
				'options'       => '', // loads value dynamically
				'desc_tip'      => __( 'Select which URL types to replace (Post, Page or Media URLs). At least one is required. The find &amp; replace runs everywhere the URL appears — content, custom fields and site options.', 'real-time-auto-find-and-replace' ),
			),
			'st2'                                    => array(
				'wrapper_class' => 'advance-filter ',
				'type'          => 'section_title',
				'title'         => __( 'Advanced Filters', 'real-time-auto-find-and-replace' ),
				'desc_tip'      => __( 'Enable the settings below if you want to apply special filtering options.', 'real-time-auto-find-and-replace' ),
			),
			'cs_db_string_replace[case_insensitive]' => array(
				'title'    => __( 'Case-Insensitive', 'real-time-auto-find-and-replace' ),
				'type'     => 'checkbox',
				'desc_tip' => __( 'Check this checkbox if you wish to perform a case-insensitive search, or leave it unchecked to perform a case-sensitive search. e.g : Shop / shop / SHOP, all will be treated as the same word if you check this checkbox.', 'real-time-auto-find-and-replace' ),
			),
			'cs_db_string_replace[whole_word]'       => array(
				'title'    => __( 'Whole Words Only', 'real-time-auto-find-and-replace' ),
				'type'     => 'checkbox',
				'desc_tip' => \sprintf(
					// Translators: %1$s and %2$s are HTML <code> tags for formatting; %3$s and %4$s are HTML <em> tags for emphasis.
					__( 'Check this checkbox, if you want the find and replace function to only match complete words. e.g : if you want to replace - %1$stest%2$s from - %1$sThis is a test sentence for testing%2$s, then only replacement will be on -  %1$sThis is a %3$stest%4$s sentence for testing%2$s ', 'real-time-auto-find-and-replace' ),
					'<code>',
					'</code>',
					'<em>',
					'</em>'
				),
			),
			'cs_db_string_replace[encoded_urls]'     => array(
				'wrapper_class'     => 'url-only-filter force-hidden',
				// Translators: %1$s is a line break with a span element for indicating pro features; %2$s is the closing span tag.
				'title'             => sprintf( __( 'Escaped &amp; Encoded URLs %1$s Pro version only %2$s', 'real-time-auto-find-and-replace' ), '<br/><span class="pro-version-only">', '</span>' ),
				'type'              => 'checkbox',
				'is_pro'            => true,
				'custom_attributes' => array(
					'disabled' => 'disabled',
				),
				'desc_tip'          => __( 'Check this checkbox, if you want find and replace to also match URLs stored in escaped or encoded form. e.g : JSON-escaped (https:\/\/) and percent-encoded (https%3A%2F%2F) variants, like the ones Elementor and other page builders store inside JSON.', 'real-time-auto-find-and-replace' ),
			),
			'cs_db_string_replace[url_formats]'      => array(
				'wrapper_class'     => 'url-only-filter force-hidden',
				// Translators: %1$s is a line break with a span element for indicating pro features; %2$s is the closing span tag.
				'title'             => sprintf( __( 'Match all URL formats %1$s Pro version only %2$s', 'real-time-auto-find-and-replace' ), '<br/><span class="pro-version-only">', '</span>' ),
				'type'              => 'checkbox',
				'is_pro'            => true,
				'custom_attributes' => array(
					'disabled' => 'disabled',
				),
				'desc_tip'          => __( 'Check this checkbox, if you want a URL search to also match other formats of the same address. e.g : searching https://old.com also catches http://old.com, //old.com (protocol-relative), and the www / non-www variants. Applies when "Where to Replace" is set to All URLs.', 'real-time-auto-find-and-replace' ),
			),
			'cs_db_string_replace[unicode_modifier]' => array(
				// Translators: %1$s is a line break with a span element for indicating pro features; %2$s is the closing span tag.
				'title'             => sprintf( __( 'Unicode Characters %1$s Pro version only %2$s', 'real-time-auto-find-and-replace' ), '<br/><span class="pro-version-only">', '</span>' ),
				'type'              => 'checkbox',
				'is_pro'            => true,
				'custom_attributes' => array(
					'disabled' => 'disabled',
				),
				'desc_tip'          => __( 'Check this checkbox, if you want find and replace unicode characters (UTF-8). e.g: U+0026, REČA', 'real-time-auto-find-and-replace' ),
			),

			'cs_db_string_replace[dry_run]'          => array(
				'title'             => __( 'Dry run', 'real-time-auto-find-and-replace' ),
				'type'              => 'checkbox',
				'value'             => true,
				'custom_attributes' => array(
					'checked' => true,
				),
				'desc_tip'          => __( 'By ticking this checkbox, you can preview the areas where the find and replace action will take place. The popup window will display a comprehensive list of the identified items and their respective replacements. The database will remain unchanged until you opt to uncheck this option.', 'real-time-auto-find-and-replace' ),
			),
		);

		$fields          = apply_filters( 'bfrp_replacedb_settings_fields', $fields, $option );
		$args['content'] = $this->Form_Generator->generate_html_fields( $fields );

		$hidden_fields = array(
			'method'           => array(
				'id'    => 'method',
				'type'  => 'hidden',
				'value' => "DbReplacer@db_string_replace",
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

		$args['btn_text']       = __( 'Create Reports', 'real-time-auto-find-and-replace' );
		$args['show_btn']       = true;
		$args['body_class']     = 'no-bottom-margin';
		$args['well']           = '<ul>
                        <li> <b>' . __( 'Warning!', 'real-time-auto-find-and-replace' ) . '</b>
                            <ol>
                                <li>'
									. __( 'Replacement in the database is permanent and cannot be undone once it has been applied.', 'real-time-auto-find-and-replace' )
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

					jQuery("body").on('click', 'a.close', function(){
						$("#popup1").removeClass('show-popup').addClass('hide-popup');
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
					<div class="after-content"><!-- after content elements --> </div>
					<div class="apiResponse"></div>
				</div>
			</div>
		<?php
		$html = ob_get_clean();

		return $html;
	}
}
