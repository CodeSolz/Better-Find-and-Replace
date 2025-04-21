<?php namespace RealTimeAutoFindReplace\admin\options\pages;

/**
 * Class: Media Replacer
 *
 * @package Admin
 * @since 1.2.4
 * @author CodeSolz <customer-support@codesolz.net>
 */

if ( ! defined( 'CS_RTAFAR_VERSION' ) ) {
	die();
}

use RealTimeAutoFindReplace\lib\Util;
use RealTimeAutoFindReplace\admin\builders\FormBuilder;
use RealTimeAutoFindReplace\admin\builders\AdminPageBuilder;

class MediaReplacer {

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

		$option = array();

		$fields = array(
			'cs_masking_rule[media_replacer]'                  => array(
				'title'       => __( 'Search Media by Name', 'real-time-auto-find-and-replace' ),
				'type'        => 'text',
				'class'       => 'form-control input-media-replace-query',
				'required'    => true,
				'value'       => FormBuilder::get_value( 'media_replacer', $option, '' ),
				'placeholder' => __( 'Enter file name. Partial or full name can used', 'real-time-auto-find-and-replace' ),
				'desc_tip'    => __( 'Enter the name of the media file you wish to replace in the search box above. Matching results will appear for you to select and replace.', 'real-time-auto-find-and-replace' ),
			),
		);

		$fields          = apply_filters( 'bfrp_media_replacer_fields', $fields, $option );
		$args['content'] = $this->Form_Generator->generate_html_fields( $fields ) . '';
		$args['body_class'] = 'no-bottom-margin';
		

		//Section to show search results

		$before_footer_fields = array(
			'st1'              => array(
				'wrapper_class' => "search-results st1-wrapper ",
				'type'          => 'section_title',
				'title'         => __( 'Search Results...', 'real-time-auto-find-and-replace' ),
				'desc_tip'      => __( 'The replacement action cannot be undone!', 'real-time-auto-find-and-replace' ),
			),
		);

		$before_footer = $this->Form_Generator->generate_html_fields( $before_footer_fields );

		\ob_start();
		?>
		<div class="image-container"><!--do not remove--></div>
		<?php

		$html = \ob_get_clean();

		$args['hidden_content'] = $this->popupHtml();
		$args['before_footer_wrapper']   = true;
		$args['before_footer']   = $before_footer . $html;

		return $this->Admin_Page_Generator->generate_page( $args );
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
					<!-- <h2 class="title">---</h2> -->
					<!-- <p class="sub-title">--</p> -->
					<a class="close" >&times;</a>
					<div id="bfrModalContent" class="">

					<div class="media-modal-contents" role="document" style="min-height:600px">
						<div class="edit-attachment-frame mode-select hide-router">
		
		<div class="media-frame-title"><h1 class="title-popup-media-replacer"></h1></div>
		<div class="media-frame-content">
			
		<div class="attachment-details save-ready">
		<div class="attachment-media-view landscape">
			<div class="response"><!--do not remove --></div>
			<div class="upload-container" id="upload-container">
				<p>Drag and drop a file here, or <label for="file-input" style="color: #0073aa; cursor: pointer;">browse</label></p>
				<input type="file" id="file-input" accept="*/*">
			</div>

			<h2 class="screen-reader-text">Attachment Preview</h2>
			<div class="image-placeholder">
				<div class="image-wrapper upload-preview-wrapper">

					<img
					class="preview-image upload-preview"
					src="<?php echo CS_RTAFAR_PLUGIN_ASSET_URI . 'img/new-media-placeholder250x207.svg'; ?>"
					draggable="false"
					alt="new media preview"
					/>
				</div>
				<div class="arrow-wrapper">
					<div class="custom-arrow">&rarr;</div>
				</div>
				<div class="image-wrapper old-media-preview-wrapper">
					<!-- <img
					class="preview-image preview-image-old"
					src="<?php //echo CS_RTAFAR_PLUGIN_ASSET_URI . 'img/old-media-placeholder250x207.svg'; ?>"
					draggable="false"
					alt="old media preview"
					/> -->
				</div>
			</div>

		</div>
		<div class="attachment-info">
			
			<div class="details">
				<h2 class="">Details</h2>
				<div class="uploaded"><strong>Uploaded on:</strong> <span class="ai-date"></span></div>
				<div class="uploaded-by">
					<strong>Uploaded by:</strong>
					<span class="author-info"></span>
				</div>
				<div class="filename"><strong>File name:</strong> <span class="ai-filename"> </span></div>
				<div class="file-type"><strong>File type:</strong> <span class="ai-filetype"> </span></div>
				<div class="file-size"><strong>File size:</strong> <span class="ai-filesize"> </span></div>
				<div class="dimensions"><strong>Dimensions:</strong><span class="ai-dimensions"> </span> pixels </div>

				<div class="compat-meta">
					
				</div>
			</div>

			<div class="settings">
				
				
				<span class="setting alt-text has-description" data-setting="alt">
					<label for="attachment-details-two-column-alt-text" class="name">Alternative Text</label>
					<textarea id="attachment-details-two-column-alt-text" aria-describedby="alt-text-description"></textarea>
				</span>

				<span class="setting" data-setting="title">
					<label for="attachment-details-two-column-title" class="name">Title</label>
					<input type="text" id="attachment-details-two-column-title" value="" style="border: 1px solid #8c8f94" />
				</span>
					
								
				<span class="setting" data-setting="caption">
					<label for="attachment-details-two-column-caption" class="name">Caption</label>
					<textarea id="attachment-details-two-column-caption"></textarea>
				</span>
				<span class="setting" data-setting="description">
					<label for="attachment-details-two-column-description" class="name">Description</label>
					<textarea id="attachment-details-two-column-description"></textarea>
				</span>
				<span class="setting" data-setting="url">
					<label for="attachment-details-two-column-copy-link" class="name">File URL:</label>
					<input type="text" class="attachment-details-copy-link" id="attachment-details-two-column-copy-link" value="" readonly="">
				</span>
				
			</div>

			<div class="actions">
				<button class="btn btn-custom-submit btn-media-replace">Replace</button>
			</div>
		</div>
	</div></div>
	</div></div>

					</div>
					<div class="after-content"><!-- after content elements --> </div>
					<div class="apiResponse"></div>
				</div>
			</div>
		<?php
		$html = ob_get_clean();

		return $html;
	}
}
