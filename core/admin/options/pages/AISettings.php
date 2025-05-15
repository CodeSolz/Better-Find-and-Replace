<?php namespace RealTimeAutoFindReplace\admin\options\pages;

/**
 * Class: Add New Coin
 *
 * @package Admin
 * @since 1.0.0
 * @author CodeSolz <customer-support@codesolz.net>
 */

if ( ! defined( 'CS_RTAFAR_VERSION' ) ) {
	die();
}

use RealTimeAutoFindReplace\lib\Util;
use RealTimeAutoFindReplace\admin\functions\Masking;
use RealTimeAutoFindReplace\admin\builders\FormBuilder;
use RealTimeAutoFindReplace\admin\builders\AdminPageBuilder;

class AISettings {

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
	}

	/**
	 * Generate add new coin page
	 *
	 * @param type $args
	 * @return type
	 */
	public function generate_page( $args ) {

		// pre_print( $args);

		// $settings_data = [];

		$fields = array(
			'cs_ai_config[api_key]'      => array(
				'title'       => __( 'API Key', 'real-time-auto-find-and-replace' ),
				'type'        => 'text',
				'class'       => 'form-control',
				'required'    => true,
				'value'       => FormBuilder::get_value( 'api_key', $args, '' ),
				'placeholder' => __( 'Enter your API Key', 'real-time-auto-find-and-replace' ),
				'desc_tip'    => sprintf( __( 'Enter your API Key. You can find your API Key in the openapi platform in %1$s API Keys Menu %2$s .', 'real-time-auto-find-and-replace' ), "<a href='https://platform.openai.com/api-keys' target='_blank'>", '</a>' ),
			),
			'cs_ai_config[language_model]'      => array(
				'title'         => __( 'Model', 'real-time-auto-find-and-replace' ),
				'type'          => 'select',
				'class'         => 'form-control where-to-replace-select',
				'required'      => true,
				'placeholder'   => __( 'Please select an AI model', 'real-time-auto-find-and-replace' ),
				'options'       => apply_filters(
					'bfrp_ai_language_model',
					array(
							'hasGroup' => array(
								__( 'OpenAI Models – General Purpose', 'real-time-auto-find-and-replace' ) => array(
									'gpt-4o' => __( 'GPT-4o – Multimodal model supporting text, image, and audio inputs; optimized for speed and cost.', 'real-time-auto-find-and-replace' ),
									'gpt-4.1' => __( 'GPT-4.1 – Enhanced version of GPT-4 with improved reasoning and coding capabilities.', 'real-time-auto-find-and-replace' ),
									'gpt-4.1-mini' => __( 'GPT-4.1 Mini – Smaller, faster variant of GPT-4.1, suitable for lightweight applications.', 'real-time-auto-find-and-replace' ),
									'gpt-4.1-nano' => __( 'GPT-4.1 Nano – Ultra-lightweight model for cost-effective tasks.', 'real-time-auto-find-and-replace' ),
									'gpt-4' => __( 'GPT-4 – Original GPT-4 model with strong performance across various tasks.', 'real-time-auto-find-and-replace' ),
									'gpt-3.5-turbo' => __( 'GPT-3.5 Turbo – Budget-friendly, fast and capable for many tasks.', 'real-time-auto-find-and-replace' ),
									'gpt-3.5-turbo-16k' => __( 'GPT-3.5 Turbo 16K – Variant with extended context length support.', 'real-time-auto-find-and-replace' ),
								),
								__( 'OpenAI Models – Specialized', 'real-time-auto-find-and-replace' ) => array(
									'o1' => __( 'O1 – Reasoning-focused model designed for complex problem-solving.', 'real-time-auto-find-and-replace' ),
									'o1-mini' => __( 'O1 Mini – Compact version of O1 for faster inference.', 'real-time-auto-find-and-replace' ),
									'o1-pro' => __( 'O1 Pro – Premium version of O1 with enhanced capabilities.', 'real-time-auto-find-and-replace' ),
									'o3' => __( 'O3 – Advanced reasoning model for in-depth analysis tasks.', 'real-time-auto-find-and-replace' ),
									'o3-mini' => __( 'O3 Mini – Streamlined version of O3 for quicker responses.', 'real-time-auto-find-and-replace' ),
								),
								__( 'OpenAI Models – Embeddings & Moderation', 'real-time-auto-find-and-replace' ) => array(
									'text-embedding-3-large' => __( 'Text Embedding 3 Large – High-accuracy embeddings for semantic search.', 'real-time-auto-find-and-replace' ),
									'text-embedding-3-small' => __( 'Text Embedding 3 Small – Efficient embeddings for resource-constrained scenarios.', 'real-time-auto-find-and-replace' ),
									'text-moderation-latest' => __( 'Text Moderation – Model for content moderation and safety checks.', 'real-time-auto-find-and-replace' ),
								),
							),
						)
				),
				'value'         => FormBuilder::get_value( 'language_model', $args, '' ),
				'desc_tip'      => __( 'Select language model. ', 'real-time-auto-find-and-replace' ),
			),
		);

		$args['content'] = $this->Form_Generator->generate_html_fields( $fields );

		$hidden_fields = array(
			
			'method'                          => array(
				'id'    => 'method',
				'type'  => 'hidden',
				'value' => 'admin\\functions\\aiHandler@saveSettings',
			),
			'swal_title'                      => array(
				'id'    => 'swal_title',
				'type'  => 'hidden',
				'value' => 'Saving Settings..',
			),
			'swal_des'                        => array(
				'id'    => 'swal_des',
				'type'  => 'hidden',
				'value' => __( 'Please wait a while...', 'real-time-auto-find-and-replace' ),
			),
			'swal_loading_gif'                => array(
				'id'    => 'swal_loading_gif',
				'type'  => 'hidden',
				'value' => CS_RTAFAR_PLUGIN_ASSET_URI . 'img/loading-timer.gif',
			),
			'swal_error'                      => array(
				'id'    => 'swal_error',
				'type'  => 'hidden',
				'value' => __( 'Something went wrong! Please try again by refreshing the page.', 'real-time-auto-find-and-replace' ),
			)

		);

		$args['hidden_fields'] = $this->Form_Generator->generate_hidden_fields( $hidden_fields );

		$args['btn_text']   = 'Save Settings';
		$args['show_btn']   = true;
		$args['body_class'] = 'no-bottom-margin';

		$args['well'] = '<ul>
            <li> <b>Basic Hints</b>
               
				<ol>
				<li><strong>Create an OpenAI account</strong><br>
					<em>Visit <a href="https://platform.openai.com/signup" target="_blank" rel="noopener noreferrer">https://platform.openai.com/signup</a> and sign up or log in.</em>
				</li>
				<li><strong>Go to the API Keys page</strong><br>
					<em>After logging in, navigate to <a href="https://platform.openai.com/api-keys" target="_blank" rel="noopener noreferrer">https://platform.openai.com/api-keys</a>.</em>
				</li>
				<li><strong>Click “+ Create new secret key”</strong><br>
					<em>Copy the generated key and store it safely. You won\'t be able to view it again later.</em>
				</li>
				<li><strong>Paste the key here</strong><br>
					<em>Enter the API key in the field bellow to enable AI-powered suggestions in your plugin.</em>
				</li>
				</ol>
				<p class="highlight-red"><em>Note: You are responsible for any usage or charges on your OpenAI account. No data is stored or shared outside your site.</em></p>

            </li>
        </ul>';

		return $this->Admin_Page_Generator->generate_page( $args );
	}
}
