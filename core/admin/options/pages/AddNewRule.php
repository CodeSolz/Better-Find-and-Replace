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


	public function __construct( AdminPageBuilder $AdminPageGenerator ) {
		$this->Admin_Page_Generator = $AdminPageGenerator;

		/*create obj form generator*/
		$this->Form_Generator = new FormBuilder();

		/**
		 *  Admin scripts
		 */
		add_action( 'admin_footer', array( $this, 'rtafarAddNewRuleScripts' ) );
	}

	/**
	 * Generate add new coin page
	 *
	 * @param type $args
	 * @return type
	 */
	public function generate_page( $args, $option ) {

		$ajaxSeRepFields = 'force-hidden';
		$ruleType        = FormBuilder::get_value( 'type', $option, '' );
		if ( $ruleType == 'ajaxContent' ) {
			$ajaxSeRepFields = '';
		}

		$ajaxMultiByte = 'force-hidden';
		if ( $ruleType == 'multiByte' ) {
			$ajaxMultiByte = '';
		}

		$hiddenBypassRule    = 'force-hidden';
		$hiddenAdvanceFilter = 'force-hidden';
		if ( $ruleType == 'plain' || empty( $option ) ) {
			$hiddenBypassRule    = '';
			$hiddenAdvanceFilter = '';
		}

		if ( $ruleType == 'regex' ) {
			$hiddenAdvanceFilter = '';
		}

		$isShowSt2      = $hiddenAdvanceFilter;
		$isShowSkipPage = $hiddenAdvanceFilter;
		$isShowSkipPost = $hiddenAdvanceFilter;

		if ( has_filter( 'bfrp_filterSnAnrFields' ) ) {
			$isShowScFields = array();
			$isShowScFields = apply_filters( 'bfrp_filterSnAnrFields', $isShowScFields, $option, $ruleType );

			if ( isset( $isShowScFields['st2'] ) && $isShowScFields['st2'] == 'show' ) {
				$isShowSt2 = '';
			}
			if ( isset( $isShowScFields['skip_pages'] ) && $isShowScFields['skip_pages'] == 'show' ) {
				$isShowSkipPage = '';
			}
			if ( isset( $isShowScFields['skip_posts'] ) && $isShowScFields['skip_posts'] == 'show' ) {
				$isShowSkipPost = '';
			}
		}

		// pre_print( $isShowScFields );

		$fields = array(
			'cs_masking_rule[find]'                  => array(
				'title'       => __( 'Find', 'real-time-auto-find-and-replace' ),
				'type'        => 'textarea',
				'class'       => 'form-control',
				'required'    => true,
				'value'       => FormBuilder::get_value( 'find', $option, '' ),
				'placeholder' => __( 'Set find rules', 'real-time-auto-find-and-replace' ),
				'desc_tip'    => __( 'Enter the text or phrase you want to search for. e.g: Shop', 'real-time-auto-find-and-replace' ),
			),
			'cs_masking_rule[replace]'               => array(
				'title'       => __( 'Replace With', 'real-time-auto-find-and-replace' ),
				'type'        => 'textarea',
				'class'       => 'form-control',
				'value'       => FormBuilder::get_value( 'replace', $option, '' ),
				'placeholder' => __( 'set replace rule', 'real-time-auto-find-and-replace' ),
				'desc_tip'    => __( 'Enter the word you want to replace with. e.g: My Store', 'real-time-auto-find-and-replace' ),
			),
			'cs_masking_rule[type]'                  => array(
				'title'       => __( 'Rule\'s Type', 'real-time-auto-find-and-replace' ),
				'type'        => 'select',
				'class'       => 'form-control rule-type',
				'required'    => true,
				'placeholder' => __( 'Please select %s rule type', 'real-time-auto-find-and-replace' ),
				'options'     => apply_filters(
					'bfrp_masking_rules',
					array(
						'hasGroup' => array(
							__( 'Realtime Masking  (no effect in Database)', 'real-time-auto-find-and-replace' ) => array(
								'plain'                  => __( 'Plain Text', 'real-time-auto-find-and-replace' ),
								'regex'                  => __( 'Regular Expression - Managed', 'real-time-auto-find-and-replace' ),
								'regexCustom'                  => __( 'Regular Expression - Custom', 'real-time-auto-find-and-replace' ),
								'ajaxContent'            => __( 'jQuery / Ajax - Onload', 'real-time-auto-find-and-replace' ),
								'multiByte'            => __( 'Multibyte characters ( lang: Arabic / Chinese etc)', 'real-time-auto-find-and-replace' ),
								'htmlTags_disabled234' => __( 'Replace HTML tag(s) - pro version only (pro PRO & above)', 'real-time-auto-find-and-replace' ),
								'advance_regex_disabled' => __( 'Advance Regular Expression (multiple lines at once / code blocks ) - pro version only', 'real-time-auto-find-and-replace' ),
								'filterShortCodes_disabled' => __( 'Shortcode   - pro version only', 'real-time-auto-find-and-replace' ),
								'filterOldComments_disabled' => __( 'Old Comments  - pro version only', 'real-time-auto-find-and-replace' ),
							),
							__( 'Database Replacement (affect in Database)', 'real-time-auto-find-and-replace' ) => array(
								'filterAutoPost_disabled' => __( 'Auto / New Post (replace before inserting into Database)  - pro version only', 'real-time-auto-find-and-replace' ),
								'filterComment_disabled'  => __( 'New Comment (replace before inserting into Database)  - pro version only', 'real-time-auto-find-and-replace' ),
							),
						),
					)
				),
				'value'       => FormBuilder::get_value( 'type', $option, '' ),
				'desc_tip'    => __( 'Select find and replacement rule\'s type. e.g : Plain Text', 'real-time-auto-find-and-replace' ),
			),
			'cs_masking_rule[html_charset]'                  => array(
				'wrapper_class'     => "html-charset {$ajaxMultiByte}",
				'title'       => __( 'Website Charset', 'real-time-auto-find-and-replace' ),
				'type'        => 'text',
				'class'       => 'form-control',
				'value'       => FormBuilder::get_value( 'html_charset', $option, '' ),
				'placeholder' => __( 'Enter website charset', 'real-time-auto-find-and-replace' ),
				'desc_tip'    => __( 'Enter website charset. It supports 25 character encoding types.. e.g: UTF-8', 'real-time-auto-find-and-replace' ),
			),
			'cs_masking_rule[delay]'                 => array(
				'wrapper_class'     => "delay-time {$ajaxSeRepFields}",
				'title'             => __( 'Delay Time', 'real-time-auto-find-and-replace' ),
				'type'              => 'number',
				'class'             => 'form-control width-100 delay-time-input',
				'value'             => FormBuilder::get_value( 'delay', $option, 2 ),
				'placeholder'       => __( 'Set delay time in seconds. e.g : 2', 'real-time-auto-find-and-replace' ),
				'desc_tip'          => __( 'Set delay time in seconds. e.g: 2. If your text still not replace then increase the delay time. ', 'real-time-auto-find-and-replace' ),
				'custom_attributes' => array(
					'min' => 1,
					'max' => 10,
				),
			),
			'cs_masking_rule[where_to_replace]'      => array(
				'wrapper_class' => 'where_to_replace',
				'title'         => __( 'Where To Replace', 'real-time-auto-find-and-replace' ),
				'type'          => 'select',
				'class'         => 'form-control where-to-replace-select',
				'required'      => true,
				'placeholder'   => __( 'Please select where to replace', 'real-time-auto-find-and-replace' ),
				'options'       => apply_filters(
					'bfrp_masking_location',
					array(
						'all'                       => __( 'All over the website', 'real-time-auto-find-and-replace' ),
						'specificPagePost_disabled' => __( 'On specific page or post - pro version only', 'real-time-auto-find-and-replace' ),
					)
				),
				'value'         => FormBuilder::get_value( 'where_to_replace', $option, '' ),
				'desc_tip'      => __( 'Select rule\'s type. e.g : All over the website', 'real-time-auto-find-and-replace' ),
			),
			'st1'                                    => array(
				'wrapper_class' => "bypass-rule {$hiddenBypassRule}",
				'type'          => 'section_title',
				'title'         => __( 'Bypass Rule', 'real-time-auto-find-and-replace' ),
				'desc_tip'      => __( 'Turn on the following settings if you desire to keep text unchanged in a particular region using a bypass rule pattern.', 'real-time-auto-find-and-replace' ),
			),
			'cs_masking_rule[bypass_rule_is_active]' => array(
				'wrapper_class'     => "bypass-rule {$hiddenBypassRule}",
				'title'             => sprintf( __( 'Activate Bypass Rule %1$s Pro version only %2$s', 'real-time-auto-find-and-replace' ), '<br/><span class="pro-version-only">', '</span>' ),
				'type'              => 'checkbox',
				'is_pro'            => true,
				'value'             => FormBuilder::get_value( 'bypass_rule_is_active', $option, '' ),
				'custom_attributes' => array(
					'disabled' => 'disabled',
				),
				'desc_tip'          => __( 'Check this checkbox if you want to apply Bypass rule', 'real-time-auto-find-and-replace' ),
			),
			'cs_masking_rule[bypass_rule]'           => array(
				'title'         => sprintf( __( 'Bypass Rule %1$s Pro version only %2$s', 'real-time-auto-find-and-replace' ), '<br/><span class="pro-version-only">', '</span>' ),
				'wrapper_class' => "bypass-rule {$hiddenBypassRule}",
				'type'          => 'miscellaneous',
				'is_pro'        => true,
				'desc_tip'      => __( 'Keep the string / text / word / code blocks unchanged wrapped up with this pattern. e.g: {test} ', 'real-time-auto-find-and-replace' ),
				'options'       => array(
					'cs_masking_rule[bypass_rule_wrapped_first_char]' => array(
						'type'              => 'text',
						'class'             => 'form-controller width-30',
						'value'             => FormBuilder::get_value( 'bypass_rule_wrapped_first_char', $option, '{' ),
						'custom_attributes' => array(
							'disabled' => 'disabled',
						),
						'after_text'        => __( ' find word ', 'real-time-auto-find-and-replace' ),
					),
					'cs_masking_rule[bypass_rule_wrapped_last_char]' => array(
						'type'              => 'text',
						'class'             => 'form-controller width-30',
						'value'             => FormBuilder::get_value( 'bypass_rule_wrapped_last_char', $option, '}' ),
						'custom_attributes' => array(
							'disabled' => 'disabled',
						),
					),
				),
			),
			'cs_masking_rule[remove_bypass_wrapper]' => array(
				'title'             => sprintf( __( 'Remove Wrapper %1$s Pro version only %2$s', 'real-time-auto-find-and-replace' ), '<br/><span class="pro-version-only">', '</span>' ),
				'wrapper_class'     => "bypass-rule {$hiddenBypassRule}",
				'type'              => 'checkbox',
				'is_pro'            => true,
				'value'             => FormBuilder::get_value( 'remove_bypass_wrapper', $option, '' ),
				'custom_attributes' => array(
					'disabled' => 'disabled',
				),
				'desc_tip'          => sprintf( __( 'Check this checkbox if you want to remove the bypass rule wrapper on final output. eg. %1$s{test}%2$s will render finally %1$stest%2$s.', 'real-time-auto-find-and-replace' ), '<code>', '</code>' ),
			),
			'st2'                                    => array(
				'wrapper_class' => "advance-filter st2-wrapper {$isShowSt2}",
				'type'          => 'section_title',
				'title'         => __( 'Advance Filters', 'real-time-auto-find-and-replace' ),
				'desc_tip'      => __( 'Configure the following settings if you wish to implement specialized filter options.', 'real-time-auto-find-and-replace' ),
			),
			'cs_masking_rule[skip_pages][]'          => array(
				'wrapper_class'     => "advance-filter wrap-skip-pages {$isShowSkipPage}",
				'title'             => \apply_filters( 'bfrp_skip_pages_title', sprintf( __( 'Skip Pages %1$s Pro version only %2$s', 'real-time-auto-find-and-replace' ), '<br/><span class="pro-version-only">', '</span>' ), $option ),
				'type'              => 'select',
				'class'             => 'form-control skip-pages',
				'multiple'          => true,
				'is_pro'            => true,
				'custom_attributes' => array(
					'disabled' => 'disabled',
				),
				'value'             => \apply_filters( 'bfrp_active_skip_pages', FormBuilder::get_value( 'skip_pages', $option, '' ), $option ),
				'placeholder'       => __( 'Please select page(s)', 'real-time-auto-find-and-replace' ),
				'options'           => \apply_filters( 'bfrp_skip_pages', FormBuilder::get_value( 'skip_pages', $option, '' ), $option ),
				'desc_tip'          => \apply_filters( 'bfrp_skip_pages_desc_tip', __( 'Select pages where you don\'t want to apply this rule. e.g: Checkout, Home', 'real-time-auto-find-and-replace' ), $option ),
			),
			'cs_masking_rule[skip_posts][]'          => array(
				'wrapper_class'     => "advance-filter wrap-skip-posts {$isShowSkipPost}",
				'title'             => \apply_filters( 'bfrp_skip_posts_title', sprintf( __( 'Skip Posts %1$s Pro version only %2$s', 'real-time-auto-find-and-replace' ), '<br/><span class="pro-version-only">', '</span>' ), $option ),
				'type'              => 'select',
				'class'             => 'form-control skip-posts',
				'multiple'          => true,
				'is_pro'            => true,
				'custom_attributes' => array(
					'disabled' => 'disabled',
				),
				'value'             => \apply_filters( 'bfrp_active_skip_posts', FormBuilder::get_value( 'skip_posts', $option, '' ), $option ),
				'placeholder'       => __( 'Please select posts(s)', 'real-time-auto-find-and-replace' ),
				'options'           => \apply_filters( 'bfrp_skip_posts', FormBuilder::get_value( 'skip_posts', $option, '' ), $option ),
				'desc_tip'          => \apply_filters( 'bfrp_skip_posts_desc_tip', __( 'Select posts where you don\'t want to apply this rule. Rules will be applied on single post pages only. e.g: My post', 'real-time-auto-find-and-replace' ), $option ),
			),
			'cs_masking_rule[skip_base_url]'         => array(
				'wrapper_class'     => "advance-filter {$hiddenAdvanceFilter}",
				'title'             => sprintf( __( 'Skip Base URLs %1$s Pro version only %2$s', 'real-time-auto-find-and-replace' ), '<br/><span class="pro-version-only">', '</span>' ),
				'type'              => 'checkbox',
				'is_pro'            => true,
				'value'             => FormBuilder::get_value( 'skip_base_url', $option, '' ),
				'custom_attributes' => array(
					'disabled' => 'disabled',
				),
				'desc_tip'          => sprintf(
					__(
						'Check this checkbox, if you want to keep unchanged the URLs match with the website URL. 
							e.g. If you desire to modify the word %1$stest%2$s within a post or page, but it appears within the URL as %1$s%3$s%2$s, please note that applying the find and replace rule will alter the 
							URL and potentially render any dynamic links within the page or post inactive. 
							As an example - Recent Post widget links.',
						'real-time-auto-find-and-replace'
					),
					'<code>',
					'</code>',
					\site_url( 'test-post' )
				),
			),
			'cs_masking_rule[skip_css]'              => array(
				'title'                    => sprintf( __( 'Skip CSS %1$s Pro version only %2$s', 'real-time-auto-find-and-replace' ), '<br/><span class="pro-version-only">', '</span>' ),
				'wrapper_class'            => "bypass-rule {$hiddenBypassRule}",
				'type'                     => 'miscellaneous',
				'is_pro'                   => true,
				'desc_tip'                 => __(
					'Check the checkboxes, if you want to keep unchanged all the CSS',
					'real-time-auto-find-and-replace'
				),
				'after_text_wrapper_class' => 'type-of-css-rule',
				'options'                  => array(
					'cs_masking_rule[skip_css_url_external]' => array(
						'type'              => 'checkbox',
						'value'             => FormBuilder::get_value( 'skip_css_url_external', $option, '' ),
						'custom_attributes' => array(
							'disabled' => 'disabled',
						),
						'after_text'        => sprintf(
							__(
								' External CSS URL %1$s e.g: The CSS loaded with a tag like this - %3$s %2$s',
								'real-time-auto-find-and-replace'
							),
							'<i>(',
							')</i>',
							'<code>' . esc_html( '<link rel="stylesheet" href="mystyle.css">' ) . '</code>'
						),
					),
					'cs_masking_rule[skip_css_internal]' => array(
						'type'              => 'checkbox',
						'value'             => FormBuilder::get_value( 'skip_css_internal', $option, '' ),
						'custom_attributes' => array(
							'disabled' => 'disabled',
						),
						'after_text'        => sprintf(
							__(
								' Internal CSS %1$s e.g: The CSS loaded with a internal tag like this - %3$s %2$s',
								'real-time-auto-find-and-replace'
							),
							'<i>(',
							')</i>',
							'<code>' . esc_html( '<style> //css code  </style>' ) . '</code>'
						),
					),
					'cs_masking_rule[skip_css_inline]'   => array(
						'type'              => 'checkbox',
						'value'             => FormBuilder::get_value( 'skip_css_inline', $option, '' ),
						'custom_attributes' => array(
							'disabled' => 'disabled',
						),
						'after_text'        => sprintf(
							__(
								' Inline CSS %1$s e.g: The CSS loaded with a tag like this - %3$s %2$s',
								'real-time-auto-find-and-replace'
							),
							'<i>(',
							')</i>',
							'<code>' . esc_html( '<body style="color:red;">' ) . '</code>'
						),
					),
				),
			),
			'cs_masking_rule[skip_js]'               => array(
				'title'                    => sprintf( __( 'Skip JavaScript %1$s Pro version only %2$s', 'real-time-auto-find-and-replace' ), '<br/><span class="pro-version-only">', '</span>' ),
				'wrapper_class'            => "bypass-rule {$hiddenBypassRule}",
				'type'                     => 'miscellaneous',
				'is_pro'                   => true,
				'desc_tip'                 => __(
					'Check the checkboxes, if you want to keep unchanged all the JS',
					'real-time-auto-find-and-replace'
				),
				'after_text_wrapper_class' => 'type-of-js-rule',
				'options'                  => array(
					'cs_masking_rule[skip_js_url_external]' => array(
						'type'              => 'checkbox',
						'value'             => FormBuilder::get_value( 'skip_js_url_external', $option, '' ),
						'custom_attributes' => array(
							'disabled' => 'disabled',
						),
						'after_text'        => sprintf(
							__(
								' External JS URL %1$s e.g: The JS loaded with a tag like this - %3$s %2$s',
								'real-time-auto-find-and-replace'
							),
							'<i>(',
							')</i>',
							'<code>' . esc_html( '<script type="text/javascript" src="external.js"></script>' ) . '</code>'
						),
					),
					'cs_masking_rule[skip_js_internal]' => array(
						'type'              => 'checkbox',
						'value'             => FormBuilder::get_value( 'skip_js_internal', $option, '' ),
						'custom_attributes' => array(
							'disabled' => 'disabled',
						),
						'after_text'        => sprintf(
							__(
								' Internal JS %1$s e.g: The JS loaded with a internal tag like this - %3$s %2$s',
								'real-time-auto-find-and-replace'
							),
							'<i>(',
							')</i>',
							'<code>'
											. esc_html( '<script type="text/javascript"> //JavScript code </script>' )
											. '</code>'
						),
					),
				),
			),

			'cs_masking_rule[case_insensitive]'      => array(
				'wrapper_class'     => "advance-filter {$hiddenAdvanceFilter}",
				'title'             => sprintf( __( 'Case-Insensitive %1$s Pro version only %2$s', 'real-time-auto-find-and-replace' ), '<br/><span class="pro-version-only">', '</span>' ),
				'type'              => 'checkbox',
				'value'             => FormBuilder::get_value( 'case_insensitive', $option, '' ),
				'is_pro'            => true,
				'custom_attributes' => array(
					'disabled' => 'disabled',
				),
				'desc_tip'          => __( 'Check this checkbox if you wish to perform a case-insensitive search, or leave it unchecked to perform a case-sensitive search. e.g : Shop / shop / SHOP, all will be treated as the same word if you check this checkbox.', 'real-time-auto-find-and-replace' ),
			),
			'cs_masking_rule[whole_word]'            => array(
				'wrapper_class'     => "advance-filter {$hiddenAdvanceFilter}",
				'title'             => sprintf( __( 'Whole Words Only %1$s Pro version only %2$s', 'real-time-auto-find-and-replace' ), '<br/><span class="pro-version-only">', '</span>' ),
				'type'              => 'checkbox',
				'is_pro'            => true,
				'value'             => FormBuilder::get_value( 'whole_word', $option, '' ),
				'custom_attributes' => array(
					'disabled' => 'disabled',
				),
				'desc_tip'          => \sprintf(
					__( 'Check this checkbox, if you want the find and replace function to only match complete words. e.g : if you want to replace - %1$stest%2$s from - %1$sThis is a test sentence for testing%2$s, then only replacement will be on -  %1$sThis is a %3$stest%4$s sentence for testing%2$s ', 'real-time-auto-find-and-replace' ),
					'<code>',
					'</code>',
					'<em>',
					'</em>'
				),
			),
			'cs_masking_rule[unicode_modifier]'      => array(
				'wrapper_class'     => "advance-filter {$hiddenAdvanceFilter}",
				'title'             => sprintf( __( 'Unicode Characters %1$s Pro version only %2$s', 'real-time-auto-find-and-replace' ), '<br/><span class="pro-version-only">', '</span>' ),
				'type'              => 'checkbox',
				'is_pro'            => true,
				'value'             => FormBuilder::get_value( 'unicode_modifier', $option, '' ),
				'custom_attributes' => array(
					'disabled' => 'disabled',
				),
				'desc_tip'          => __( 'Check this checkbox, if you want to find and replace unicode characters (UTF-8). e.g: U+0026, REČA', 'real-time-auto-find-and-replace' ),
			),
		);

		$fields          = apply_filters( 'bfrp_masking_settings_fields', $fields, $option );
		$args['content'] = $this->Form_Generator->generate_html_fields( $fields );

		$swal_title = __( 'Adding Rule', 'real-time-auto-find-and-replace' );
		$btn_txt    = __( 'Add Rule', 'real-time-auto-find-and-replace' );

		$update_hidden_fields = array();

		if ( ! empty( $option ) ) {
			$swal_title = __( 'Updating Rule', 'real-time-auto-find-and-replace' );
			$btn_txt    = __( 'Update Rule', 'real-time-auto-find-and-replace' );

			$update_hidden_fields = array(
				'cs_masking_rule[id]' => array(
					'id'    => 'rule_id',
					'type'  => 'hidden',
					'value' => $option['id'],
				),
			);

		}

		$hidden_fields = array_merge_recursive(
			array(
				'method'           => array(
					'id'    => 'method',
					'type'  => 'hidden',
					'value' => "admin\\functions\\Masking@add_masking_rule",
				),
				'swal_title'       => array(
					'id'    => 'swal_title',
					'type'  => 'hidden',
					'value' => $swal_title,
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
			),
			$update_hidden_fields
		);

		$args['hidden_fields'] = $this->Form_Generator->generate_hidden_fields( $hidden_fields );

		$args['btn_text']   = $btn_txt;
		$args['show_btn']   = true;
		$args['body_class'] = 'no-bottom-margin';

		return $this->Admin_Page_Generator->generate_page( $args );
	}

	/**
	 * Admin footer scripts
	 *
	 * @return void
	 */
	public function rtafarAddNewRuleScripts() {
		?>
			<script type="text/javascript">
				jQuery(document).ready(function(){
					jQuery("body").on('change', '.rule-type', function(){
						jQuery(".delay-time, .tag-selector, .html-charset").addClass('force-hidden');
						jQuery(".delay-time-input, .tag-selector-input").removeAttr('required');
						if( jQuery(this).val() === 'ajaxContent' ){
							jQuery(".delay-time, .tag-selector").removeClass('force-hidden');
							jQuery(".delay-time-input, .tag-selector-input").attr('required', 'required');
						}
						
						if( jQuery(this).val() === 'multiByte' ){ 
							jQuery(".html-charset").removeClass('force-hidden');
						}
					});

				});

				
			</script>
		<?php
		do_action( 'bfrp_footer_add_new_rule_masking' );
	}

}


