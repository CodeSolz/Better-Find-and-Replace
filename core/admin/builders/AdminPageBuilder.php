<?php namespace RealTimeAutoFindReplace\admin\builders;

use RealTimeAutoFindReplace\lib\Util;

/**
 * Admin Page Builder Class
 *
 * @package Builder
 * @author CodeSolz <info@codesolz.com>
 */

if ( ! defined( 'CS_RTAFAR_VERSION' ) ) {
	exit;
}


class AdminPageBuilder {

	/**
	 * Hold admin pages
	 *
	 * @var type
	 */
	protected $admin_page = array();

	/**
	 * Hold current screen id
	 *
	 * @var type
	 */
	public $current_screen;

	/**
	 * Call Classes
	 *
	 * @param type $name
	 * @param type $arguments
	 * @return type
	 */
	public function __call( $name, $arguments ) {
		return $this->getClass( \ucwords( $name ) );
	}

	/**
	 * Get classes
	 *
	 * @param string $class
	 * @return type
	 */
	private function getClass( $class ) {
		$class_path = '\RealTimeAutoFindReplace\\admin\\options\\pages\\' . $class;

		if ( ! \class_exists( $class_path ) ) {
			return " Class / Method - '{$class_path}' - not found!";
		}

		if ( ! \array_key_exists( $class_path, $this->admin_page ) ) {
			$this->admin_page[ $class_path ] = new $class_path( $this );
		}

		return $this->admin_page[ $class_path ];
	}

	/**
	 * Init current screen
	 *
	 * @return type
	 */
	private function init_current_screen() {
		$this->current_screen = \get_current_screen();
		return $this->current_screen;
	}

	/**
	 * Generate page
	 *
	 * @param type $argc
	 */
	public function generate_page( $argc ) {
		// init current screen
		$this->init_current_screen();

		// Anything a page needs between its heading and its body - a banner
		// that reports on work started from the heading, for instance. It sits
		// outside the panel body on purpose: the body is the working surface,
		// and a message that pushes the surface around while it is being used
		// is worse than one that does not.
		$header = $this->generate_header( $argc );

		if ( isset( $argc['after_header'] ) && '' !== trim( $argc['after_header'] ) ) {
			$header .= $argc['after_header'];
		}

		$content    = '';
		$top_notice = isset( $argc['well'] ) ? $argc['well'] : '';
		if ( ! empty( $top_notice ) ) {
			$content = '<div class="well">' . $top_notice . '</div>';
		}

		$content .= isset( $argc['content'] ) ? $argc['content'] : '----';
		$content .= isset( $argc['hidden_content'] ) ? $argc['hidden_content'] : '';
		$content .= \wp_nonce_field( SECURE_AUTH_SALT, 'cs_token' );

		$before_footer = isset( $argc['before_footer'] ) ? $argc['before_footer'] : '';
		if( isset($argc['before_footer_wrapper']) && true === $argc['before_footer_wrapper'] ){
			$before_footer = '<div class="panel-body bg-white no-bottom-margin">' . $before_footer .'</div>';
		}

		return sprintf(
			$this->page_wrapper( $argc ),
			$header,
			$content,
			$this->generate_button_block( $argc ),
			$before_footer,
			$this->generate_footer()
		);
	}

	/**
	 * Get page wrapper
	 *
	 * @return string
	 */
	private function page_wrapper( $argc ) {
		$form_start = '';
		$form_end   = '';
		if ( isset( $argc['show_btn'] ) ) {
			$form_start = '<form method="post"  enctype="multipart/form-data">';
			$form_end   = '</form>';
		}

		$body_class = isset( $argc['body_class'] ) ? $argc['body_class'] : '';

		return "<div class=\"wrap\"> 
        <div id=\"product_binder\">
        <div class=\"panel\"> %s 
		{$form_start}
        <div class=\"panel-body bg-white {$body_class}\">
        <div class=\"container\"> 
			%s 
		</div>
		</div> %s 
		{$form_end}
		%s %s
		</div>
		</div></div>";
	}

	/**
	 * Generate page header block
	 *
	 * A page may hand the heading an action - the control that belongs to the
	 * screen as a whole rather than to one row of it, such as "Scan for broken
	 * links". It sits on the title's own row, at the far end. The heading only
	 * becomes a two-column row when a page actually supplies one, so every
	 * existing screen's heading markup is unchanged.
	 *
	 * @param type $argc
	 * @return type
	 */
	private function generate_header( $argc ) {
		$title     = isset( $argc['title'] ) ? $argc['title'] : '---';
		$sub_title = isset( $argc['sub_title'] ) ? $argc['sub_title'] : '---';
		$actions   = isset( $argc['header_actions'] ) ? trim( $argc['header_actions'] ) : '';

		$heading = '<h3 class="title"> ' . $title . '</h3>
            <p> ' . $sub_title . ' </p>';

		if ( '' === $actions ) {
			return '<div class="panel-heading">
            ' . $heading . '
        </div>';
		}

		return '<div class="panel-heading panel-heading-with-actions">
            <div class="panel-heading-text">
            ' . $heading . '
            </div>
            <div class="panel-heading-actions">' . $actions . '</div>
        </div>';
	}

	/**
	 * Generate button block
	 *
	 * @return string
	 */
	private function generate_button_block( $argc ) {
		if ( ! isset( $argc['show_btn'] ) || false === $argc['show_btn'] ) {
			return false;
		}
		$btn_text      = isset( $argc['btn_text'] ) ? $argc['btn_text'] : 'Submit';
		$prepend_btn   = isset( $argc['prepend_btn'] ) ? $argc['prepend_btn'] : '';
		$hidden_fields = isset( $argc['hidden_fields'] ) ? $argc['hidden_fields'] : '';
		return '<div class="section-submit-button">
            ' . $hidden_fields . '
            
            ' . $prepend_btn . '
            <input type="submit" class="btn btn-custom-submit" value="' . $btn_text . '" />
        </div>';
	}

	/**
	 * Generate footer
	 *
	 * @return string
	 */
	private function generate_footer() {
		$plugins_suggestion = '';
		if ( \current_user_can( 'install_plugins' ) ) {
			$plugins_suggestion = 'Enhance Your WordPress Experience with <a href="' . esc_url( Util::cs_free_plugins() ) . '" target="_blank" > More Smart Plugins</a>.';
		}

		return '<div class="panel-footer">
            <p>Thank you for choosing <a href="https://www.codesolz.net" target="_blank">CodeSolz\'s</a> Software! 
				<span class="doc-link">
					Looking for features details? Check plugin\'s <a href="https://docs.codesolz.net/better-find-and-replace/" target="_blank">Documentation</a>. 
					' . $plugins_suggestion . '  
				</span> 
			</p>
        </div>';
	}
}
