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

		$delayTimer = 'force-hidden';
		$ruleType   = FormBuilder::get_value( 'type', $option, '' );
		if ( $ruleType == 'ajaxContent' ) {
			$delayTimer = '';
		}

		$fields = array(
			'cs_masking_rule[find]'             => array(
				'title'       => __( 'Find', 'real-time-auto-find-and-replace' ),
				'type'        => 'textarea',
				'class'       => 'form-control',
				'required'       => true,
				'value'       => FormBuilder::get_value( 'find', $option, '' ),
				'placeholder' => __( 'Set find rules', 'real-time-auto-find-and-replace' ),
				'desc_tip'    => __( 'Enter your word what do you want to find.  Add single or comma separated multiple words. e.g: Shop, Store. (multiple words works only with <code>Plain Text</code> rule )', 'real-time-auto-find-and-replace' ),
			),
			'cs_masking_rule[replace]'          => array(
				'title'       => __( 'Replace With', 'real-time-auto-find-and-replace' ),
				'type'        => 'textarea',
				'class'       => 'form-control',
				'value'       => FormBuilder::get_value( 'replace', $option, '' ),
				'placeholder' => __( 'set replace rule', 'real-time-auto-find-and-replace' ),
				'desc_tip'    => __( 'Enter a word what do you want to replace with. e.g: My Store', 'real-time-auto-find-and-replace' ),
			),
			'cs_masking_rule[type]'             => array(
				'title'       => __( 'Rule\'s Type', 'real-time-auto-find-and-replace' ),
				'type'        => 'select',
				'class'       => 'form-control rule-type',
				'required'    => true,
				'placeholder' => __( 'Please select rules type', 'real-time-auto-find-and-replace' ),
				'options'     => apply_filters(
					'bfrp_masking_rules',
					array(
						'plain'                  => __( 'Plain Text', 'real-time-auto-find-and-replace' ),
						'regex'                  => __( 'Regular Expression', 'real-time-auto-find-and-replace' ),
						'ajaxContent'            => __( 'jQuery / Ajax', 'real-time-auto-find-and-replace' ),
						'advance_regex_disabled' => __( 'Advance Regular Expression (multiple lines at once / code blocks ) - pro version only', 'real-time-auto-find-and-replace' ),
					)
				),
				'value'       => FormBuilder::get_value( 'type', $option, '' ),
				'desc_tip'    => __( 'Select find and replacement rule\'s type. e.g : Plain Text', 'real-time-auto-find-and-replace' ),
			),
			'cs_masking_rule[delay]'            => array(
				'wrapper_class'     => "delay-time {$delayTimer}",
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
			'cs_masking_rule[tag_selector]'     => array(
				'wrapper_class' => "tag-selector {$delayTimer}",
				'title'         => __( 'Tag selector', 'real-time-auto-find-and-replace' ),
				'type'          => 'text',
				'class'         => 'form-control tag-selector-input',
				'value'         => FormBuilder::get_value( 'tag_selector', $option, '' ),
				'placeholder'   => __( 'Please enter tag selector. e.g: .mytext', 'real-time-auto-find-and-replace' ),
				'desc_tip'      => __( 'Enter Tag selector. Suppose if you select a tag by it\'s class then use a (.) before class name, (#) for ID. e.g: .mytext or #mytext', 'real-time-auto-find-and-replace' ),
			),
			'cs_masking_rule[where_to_replace]' => array(
				'title'       => __( 'Where To Replace', 'real-time-auto-find-and-replace' ),
				'type'        => 'select',
				'class'       => 'form-control coin-type-select',
				'required'    => true,
				'placeholder' => __( 'Please select where to replace', 'real-time-auto-find-and-replace' ),
				'options'     => array(
					'all'               => __( 'All over the website', 'real-time-auto-find-and-replace' ),
					'posts_disabled'    => __( 'All Blog Posts', 'real-time-auto-find-and-replace' ),
					'pages_disabled'    => __( 'All Pages', 'real-time-auto-find-and-replace' ),
					'comments_disabled' => __( 'All Comments', 'real-time-auto-find-and-replace' ),
				),
				'value'       => FormBuilder::get_value( 'where_to_replace', $option, '' ),
				'desc_tip'    => __( 'Select rule\'s type. e.g : All over the website', 'real-time-auto-find-and-replace' ),
			),
			'st1' => array(
				'wrapper_class' => 'bypass-rule',
				'type'     => 'section_title',
				'title'    => __( 'Bypass Rule', 'real-time-auto-find-and-replace' ),
				'desc_tip' => __( 'Set the following settings if you want to keep unchange the text in specific area\'s.', 'real-time-auto-find-and-replace' ),
			),
			'cs_masking_rule[bypass_rule_is_active]' => array(
				'wrapper_class' => 'bypass-rule',
				'title'       => sprintf(__( 'Activate Bypass Rule %s Pro version only %s', 'real-time-auto-find-and-replace' ), '<br/><span class="pro-version-only">', '</span>' ),
				'type'        => 'checkbox',
				'is_pro'        => true,
				'value'       => FormBuilder::get_value( 'bypass_rule_is_active', $option, '' ),
				'custom_attributes' => [
					'disabled' => 'disabled'
				],
				'desc_tip'    => __( 'Check this checkbox if you want to apply Bypass rule', 'real-time-auto-find-and-replace' ),
			),
			'cs_masking_rule[bypass_rule]'          => array(
				'title'       => sprintf( __( 'Bypass Rule %s Pro version only %s', 'real-time-auto-find-and-replace' ), '<br/><span class="pro-version-only">', '</span>' ),
				'wrapper_class' => 'bypass-rule',
				'type'        => 'miscellaneous',
				'is_pro'        => true,
				'desc_tip'    => __( 'Keep the string / text / word / code blocks unchanged wrapped up with this pattern. e.g: {test} ', 'real-time-auto-find-and-replace' ),
				'options'	  => array(
					'cs_masking_rule[bypass_rule_wrapped_first_char]' => array(
						'type' => 'text',
						'class' => 'form-controller width-30',
						'value'       => FormBuilder::get_value( 'bypass_rule_wrapped_first_char', $option, '{' ),
						'custom_attributes' => [
							'disabled' => 'disabled'
						],
						'after_text' => __( ' find word ', 'real-time-auto-find-and-replace' )
					),
					'cs_masking_rule[bypass_rule_wrapped_last_char]' => array(
						'type' => 'text',
						'class' => 'form-controller width-30',
						'value'       => FormBuilder::get_value( 'bypass_rule_wrapped_last_char', $option, '}' ),
						'custom_attributes' => [
							'disabled' => 'disabled'
						],
					)
				)	
			),
			'cs_masking_rule[remove_bypass_wrapper]' => array(
				'title'       => sprintf(__( 'Remove Wrapper %s Pro version only %s', 'real-time-auto-find-and-replace' ), '<br/><span class="pro-version-only">', '</span>' ),
				'wrapper_class' => 'bypass-rule',
				'type'        => 'checkbox',
				'is_pro'        => true,
				'value'       => FormBuilder::get_value( 'remove_bypass_wrapper', $option, '' ),
				'custom_attributes' => [
					'disabled' => 'disabled'
				],
				'desc_tip'    => sprintf(__( 'Check this checkbox if you want to remove the bypass rule wrapper. eg. %1$s{test}%2$s will render finally %1$stest%2$s.', 'real-time-auto-find-and-replace' ), '<code>', '</code>'),
			),
			'st2' => array(
				'type'     => 'section_title',
				'title'    => __( 'Advance Filters', 'real-time-auto-find-and-replace' ),
				'desc_tip' => __( 'Set the following filter if you want to apply special filter options.', 'real-time-auto-find-and-replace' ),
			),
			'cs_masking_rule[case_insensitive]' => array(
				'title'    => sprintf( __( 'Case-Insensitive %s Pro version only %s', 'real-time-auto-find-and-replace' ), '<br/><span class="pro-version-only">', '</span>' ),
				'type'     => 'checkbox',
				'value'       => FormBuilder::get_value( 'case_insensitive', $option, '' ),
				'is_pro'        => true,
				'custom_attributes' => [
					'disabled' => 'disabled'
				],
				'desc_tip' => __( 'Check this checkbox if you want to find case insensitive or keep it un-check to find case-sensitive. e.g : Shop / shop / SHOP, all will be treated as same if you check this checkbox.', 'real-time-auto-find-and-replace' ),
			),
			'cs_masking_rule[whole_word]'       => array(
				'title'    => sprintf(__( 'Whole Words Only %s Pro version only %s', 'real-time-auto-find-and-replace' ), '<br/><span class="pro-version-only">', '</span>' ),
				'type'     => 'checkbox',
				'is_pro'        => true,
				'value'       => FormBuilder::get_value( 'whole_word', $option, '' ),
				'custom_attributes' => [
					'disabled' => 'disabled'
				],
				'desc_tip' => \sprintf(
					__( 'Check this checkbox, if you want to find & replace match whole words only. e.g : if you want to replace - %1$stest%2$s from - %1$sThis is a test sentence for testing%2$s, then only replacement will be on -  %1$sThis is a %3$stest%4$s sentence for testing%2$s ', 'real-time-auto-find-and-replace' ),
					'<code>',
					'</code>',
					'<em>',
					'</em>'
				),
			),

		);

		$fields          = apply_filters( 'bfrp_masking_settings_fields', $fields );
		$args['content'] = $this->Form_Generator->generate_html_fields( $fields );

		$swal_title           = __( 'Adding Rule', 'real-time-auto-find-and-replace' );
		$btn_txt              = __( 'Add Rule', 'real-time-auto-find-and-replace' );
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
						jQuery(".delay-time, .tag-selector").addClass('force-hidden');
						jQuery(".delay-time-input, .tag-selector-input").removeAttr('required');
						if( jQuery(this).val() === 'ajaxContent' ){
							jQuery(".delay-time, .tag-selector").removeClass('force-hidden');
							jQuery(".delay-time-input, .tag-selector-input").attr('required', 'required');
						}

						jQuery(".bypass-rule").addClass('force-hidden');
						if( jQuery(this).val() === 'plain' ){
							jQuery(".bypass-rule").removeClass('force-hidden');
						}
					});
				});

				
			</script>
		<?php
	}

}


