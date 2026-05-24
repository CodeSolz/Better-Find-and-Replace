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
	 * @return string
	 */
	private function popupHtml() {
		return self::render_modal();
	}

	/**
	 * Reusable replacer modal markup.
	 *
	 * Shared by the dedicated Media Replacer page and the Media Library
	 * (upload.php) row-action integration so both present the same UI.
	 *
	 * @return string
	 */
	public static function render_modal() {
		\ob_start();
		?>
			<div id="popup1" class="overlay bfar-media-overlay">
				<div class="popup">
					<!-- <h2 class="title">---</h2> -->
					<!-- <p class="sub-title">--</p> -->
					<a class="close" >&times;</a>
					<div id="bfrModalContent" class="">

					<div class="media-modal-contents" role="document">
						<div class="edit-attachment-frame mode-select hide-router">
		
		<div class="media-frame-title"><h1 class="title-popup-media-replacer"></h1></div>
		<div class="media-frame-content">
			
		<div class="attachment-details save-ready">
		<div class="attachment-media-view landscape">
			<div class="bfar-media-stage">
			<div class="response"><!--do not remove --></div>

			<div class="bfar-dropzone" id="upload-container">
				<input type="file" id="file-input" accept="*/*" class="bfar-dropzone-input" />
				<label for="file-input" class="bfar-dropzone-inner">
					<span class="bfar-dropzone-icon" aria-hidden="true">
						<svg viewBox="0 0 24 24" width="42" height="42" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M12 15V4"/><path d="m7 9 5-5 5 5"/><path d="M5 15v3a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2v-3"/></svg>
					</span>
					<span class="bfar-dropzone-title"><?php esc_html_e( 'Drop your new file here', 'real-time-auto-find-and-replace' ); ?></span>
					<span class="bfar-dropzone-hint"><?php esc_html_e( 'or click to browse from your computer', 'real-time-auto-find-and-replace' ); ?></span>
				</label>
			</div>

			<h2 class="screen-reader-text">Attachment Preview</h2>

			<div class="bfar-compare">
				<figure class="bfar-compare-card">
					<span class="bfar-compare-label"><?php esc_html_e( 'Current', 'real-time-auto-find-and-replace' ); ?></span>
					<div class="bfar-compare-media old-media-preview-wrapper"></div>
					<figcaption class="bfar-compare-caption bfar-cur-name"></figcaption>
				</figure>

				<span class="bfar-compare-arrow" aria-hidden="true">
					<svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14"/><path d="m13 6 6 6-6 6"/></svg>
				</span>

				<figure class="bfar-compare-card bfar-compare-card--new">
					<span class="bfar-compare-label bfar-compare-label--new"><?php esc_html_e( 'New', 'real-time-auto-find-and-replace' ); ?></span>
					<div class="bfar-compare-media upload-preview-wrapper">
						<span class="bfar-compare-empty">
							<svg viewBox="0 0 24 24" width="34" height="34" fill="none" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="16" rx="2"/><circle cx="8.5" cy="9.5" r="1.5"/><path d="m21 16-4.5-4.5L7 21"/></svg>
							<span><?php esc_html_e( 'Your new file appears here', 'real-time-auto-find-and-replace' ); ?></span>
						</span>
					</div>
					<figcaption class="bfar-compare-caption bfar-new-name"></figcaption>
				</figure>
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

			<div class="settings media-popup-settings">
				
				
				<span class="setting alt-text has-description" data-setting="alt">
					<div class="label">
						<label for="attachment-details-two-column-alt-text" class="name media-alternative-text">Alternative Text</label>
					</div>
					<div class="input">
						<textarea id="attachment-details-two-column-alt-text" aria-describedby="alt-text-description"></textarea>
					</div>
				</span>

				<span class="setting" data-setting="title">
					<div class="label">
						<label for="attachment-details-two-column-title" class="name media-title">Title</label>
					</div>
					<div class="input">
						<input type="text" id="attachment-details-two-column-title" value="" style="border: 1px solid #8c8f94" />
					</div>
				</span>
					
								
				<span class="setting" data-setting="caption">
					<div class="label">
						<label for="attachment-details-two-column-caption" class="name media-caption">Caption</label>
					</div>
					<div class="input">
						<textarea id="attachment-details-two-column-caption"></textarea>
					</div>
				</span>
				<div class="setting" data-setting="description">
					<div class="label">
						<label for="attachment-details-two-column-description" class="name media-description">Description</label>
					</div>
					<div class="input">
						<textarea id="attachment-details-two-column-description"></textarea>
					</div>
				</div>
				<span class="setting" data-setting="url">
					<div class="label">
						<label for="attachment-details-two-column-copy-link" class="name">File URL:</label>
					</div>
					<div class="input">
						<input type="text" class="attachment-details-copy-link" id="attachment-details-two-column-copy-link" value="" readonly="">
					</div>
				</span>
				
			</div>

			<div class="bfar-replace-mode is-collapsed" data-setting="replace_mode">
				<button type="button" class="bfar-replace-mode-toggle" aria-expanded="false">
					<span class="bfar-replace-mode-heading"><?php esc_html_e( 'Replacement method', 'real-time-auto-find-and-replace' ); ?></span>
					<span class="bfar-replace-mode-summary"></span>
					<span class="bfar-replace-mode-chevron" aria-hidden="true"></span>
				</button>
				<div class="bfar-replace-mode-panel">
					<div class="bfar-replace-mode-options">
						<label class="bfar-mode-option is-selected" data-label="<?php esc_attr_e( 'Keep the current file name', 'real-time-auto-find-and-replace' ); ?>">
							<input type="radio" name="bfar_replace_mode" value="keep_name" checked="checked" />
							<span class="bfar-mode-mark" aria-hidden="true"></span>
							<span class="bfar-mode-body">
								<span class="bfar-mode-title">
									<?php esc_html_e( 'Keep the current file name', 'real-time-auto-find-and-replace' ); ?>
									<span class="bfar-mode-badge"><?php esc_html_e( 'Recommended', 'real-time-auto-find-and-replace' ); ?></span>
								</span>
								<span class="bfar-mode-note">
									<?php esc_html_e( 'Upload a file of the same type. The existing name', 'real-time-auto-find-and-replace' ); ?>
									<code class="bfar-mode-filename"></code>
									<?php esc_html_e( 'is kept, so links that already point to it keep working untouched.', 'real-time-auto-find-and-replace' ); ?>
								</span>
							</span>
						</label>
						<label class="bfar-mode-option" data-label="<?php esc_attr_e( "Adopt the uploaded file's name & repoint links", 'real-time-auto-find-and-replace' ); ?>">
							<input type="radio" name="bfar_replace_mode" value="new_name" />
							<span class="bfar-mode-mark" aria-hidden="true"></span>
							<span class="bfar-mode-body">
								<span class="bfar-mode-title"><?php esc_html_e( "Adopt the uploaded file's name &amp; repoint links", 'real-time-auto-find-and-replace' ); ?></span>
								<span class="bfar-mode-note">
									<?php esc_html_e( "The new file's own name and type take over, and every reference to", 'real-time-auto-find-and-replace' ); ?>
									<code class="bfar-mode-filename"></code>
									<?php esc_html_e( 'is rewritten to the new file throughout your content.', 'real-time-auto-find-and-replace' ); ?>
								</span>
							</span>
						</label>
					</div>
				</div>
			</div>

			<div class="actions">
				<button type="button" class="button button-primary button-large btn-media-replace"><?php esc_html_e( 'Replace', 'real-time-auto-find-and-replace' ); ?></button>
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
