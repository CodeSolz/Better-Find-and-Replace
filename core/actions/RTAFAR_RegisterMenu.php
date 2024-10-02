<?php namespace RealTimeAutoFindReplace\actions;

/**
 * Class: Register custom menu
 *
 * @package Action
 * @since 1.0.0
 * @author M.Tuhin <info@codesolz.net>
 */

if ( ! defined( 'CS_RTAFAR_VERSION' ) ) {
	die();
}

use RealTimeAutoFindReplace\admin\functions\Masking;
use RealTimeAutoFindReplace\admin\options\Scripts_Settings;
use RealTimeAutoFindReplace\admin\builders\AdminPageBuilder;
use RealTimeAutoFindReplace\lib\Util;


class RTAFAR_RegisterMenu {

	/**
	 * Hold pages
	 *
	 * @var type
	 */
	private $pages;

	/**
	 *
	 * @var type
	 */
	private $WcFunc;

	/**
	 *
	 * @var type
	 */
	public $current_screen;

	/**
	 * Hold Menus
	 *
	 * @var [type]
	 */
	public $rtafr_menus;


	public static $nav_cap = array(
		'add_masking_rule'  => 'bfar_menu_add_new_rule',
		'all_masking_rules' => 'bfar_menu_all_replacement_rules',
		'replace_in_db'     => 'bfar_menu_replace_in_database',
		'restore_in_db'     => 'bfar_menu_restore_in_database',
	);

	private static $_instance;

	public function __construct() {
		// call WordPress admin menu hook
		add_action( 'admin_menu', array( $this, 'rtafar_register_menu' ) );
	}

	/**
	 * Init current screen
	 *
	 * @return type
	 */
	public function init_current_screen() {
		$this->current_screen = \get_current_screen();
		return $this->current_screen;
	}

	/**
	 * Create plugins menu
	 */
	public function rtafar_register_menu() {
		global $rtafr_menu;
		add_menu_page(
			__( 'Real time auto find and replace', 'real-time-auto-find-and-replace' ),
			__( 'Find & Replace', 'real-time-auto-find-and-replace' ),
			'read',
			CS_RTAFAR_PLUGIN_IDENTIFIER,
			'cs-woo-altcoin-gateway',
			CS_RTAFAR_PLUGIN_ASSET_URI . 'img/icon-24x24.png',
			57
		);

		$this->rtafr_menus['add_masking_rule'] = add_submenu_page(
			CS_RTAFAR_PLUGIN_IDENTIFIER,
			__( 'Add Replacement Rule', 'real-time-auto-find-and-replace' ),
			__( 'Add New Rule', 'real-time-auto-find-and-replace' ),
			'read',
			'cs-add-replacement-rule',
			array( $this, 'rtafr_page_add_rule' )
		);

		$this->rtafr_menus['all_masking_rules'] = add_submenu_page(
			CS_RTAFAR_PLUGIN_IDENTIFIER,
			__( 'All Replacement Rules', 'real-time-auto-find-and-replace' ),
			__( 'All Replacement Rules', 'real-time-auto-find-and-replace' ),
			'read',
			'cs-all-masking-rules',
			array( $this, 'rtafr_page_all_masking_rules' )
		);

		$this->rtafr_menus['replace_in_db'] = add_submenu_page(
			CS_RTAFAR_PLUGIN_IDENTIFIER,
			__( 'Replace in DB', 'real-time-auto-find-and-replace' ),
			__( 'Replace in Database', 'real-time-auto-find-and-replace' ),
			'read',
			'cs-replace-in-database',
			array( $this, 'rtafr_page_replace_in_db' )
		);

		$this->rtafr_menus['restore_in_db_pro'] = add_submenu_page(
			CS_RTAFAR_PLUGIN_IDENTIFIER,
			__( 'Restore Database', 'real-time-auto-find-and-replace' ),
			__( 'Restore in Database', 'real-time-auto-find-and-replace' ),
			'read',
			'cs-bfar-restore-database-pro',
			array( $this, 'rtafar_page_restore_db' )
		);

		$this->rtafr_menus['go_pro'] = add_submenu_page(
			CS_RTAFAR_PLUGIN_IDENTIFIER,
			__( 'Go Pro', 'real-time-auto-find-and-replace' ),
			'<span class="dashicons dashicons-star-filled" style="font-size: 17px"></span> ' . __( 'Go Pro', 'real-time-auto-find-and-replace' ),
			'read',
			'cs-bfar-go-pro',
			array( $this, 'rtafar_handle_external_redirects' )
		);

		// load script
		add_action( "load-{$this->rtafr_menus['add_masking_rule']}", array( $this, 'rtafr_register_admin_settings_scripts' ) );
		add_action( "load-{$this->rtafr_menus['all_masking_rules']}", array( $this, 'rtafr_register_admin_settings_scripts' ) );
		add_action( "load-{$this->rtafr_menus['replace_in_db']}", array( $this, 'rtafr_register_admin_settings_scripts' ) );
		add_action( "load-{$this->rtafr_menus['restore_in_db_pro']}", array( $this, 'rtafr_register_admin_settings_scripts' ) );

		\remove_submenu_page( CS_RTAFAR_PLUGIN_IDENTIFIER, CS_RTAFAR_PLUGIN_IDENTIFIER );

		// init pages
		$this->pages = new AdminPageBuilder();
		$rtafr_menu  = $this->rtafr_menus;
	}

	/**
	 * Add Replacement Rule
	 *
	 * @return void
	 */
	public function rtafr_page_add_rule() {

		$title  = 'Add New';
		$option = array();
		if ( isset( $_GET['action'] ) && ! empty( $_GET['rule_id'] ) ) {
			$option = Masking::get_rules( '', $_GET['rule_id'], false, 'admin_setting' );
			$option = (array) $option[0];
			$title  = 'Update';
		}

		$page_info = array(
			/* translators: %s: Page title */
			'title'     => sprintf( __( '%s Rule', 'real-time-auto-find-and-replace' ), $title ),
			'sub_title' => __( 'The real-time masking find and replace rules will be applied prior to the website being rendered in the browser. Additionally, the database masking will take effect in the database.', 'real-time-auto-find-and-replace' ),
		);

		if ( current_user_can( 'manage_options' ) || current_user_can( 'administrator' ) || current_user_can( self::$nav_cap['add_masking_rule'] ) ) {
			$AddNewRule = $this->pages->AddNewRule();
			if ( is_object( $AddNewRule ) ) {
				echo $AddNewRule->generate_page( array_merge_recursive( $page_info, array( 'default_settings' => array() ) ), $option );
			} else {
				echo $AddNewRule, Util::cs_allowed_html();
			}
		} else {
			$AccessDenied = $this->pages->AccessDenied();
			if ( is_object( $AccessDenied ) ) {
				echo $AccessDenied->generate_access_denided( array_merge_recursive( $page_info, array( 'default_settings' => array() ) ) );
			} else {
				echo $AccessDenied, Util::cs_allowed_html();
			}
		}
	}

	public function rtafr_page_all_masking_rules() {
		$page_info = array(
			'title'     => __( 'All Replacement Rule', 'real-time-auto-find-and-replace' ),
			'sub_title' => __( 'The real-time find and replace rules will be executed before the website is displayed in the browser. The database replacement will take effect in the database permanently.', 'real-time-auto-find-and-replace' ),
		);

		if ( current_user_can( 'manage_options' ) || current_user_can( 'administrator' ) || current_user_can( self::$nav_cap['all_masking_rules'] ) ) {
			$AllMaskingRules = $this->pages->AllMaskingRules();
			if ( is_object( $AllMaskingRules ) ) {
				echo $AllMaskingRules->generate_page( array_merge_recursive( $page_info, array( 'default_settings' => array() ) ) );
			} else {
				echo $AllMaskingRules, Util::cs_allowed_html();
			}
		} else {
			$AccessDenied = $this->pages->AccessDenied();
			if ( is_object( $AccessDenied ) ) {
				echo $AccessDenied->generate_access_denided( array_merge_recursive( $page_info, array( 'default_settings' => array() ) ) );
			} else {
				echo $AccessDenied, Util::cs_allowed_html();
			}
		}
	}

	/**
	 * Generate default settings page
	 *
	 * @return type
	 */
	public function rtafr_page_replace_in_db() {
		$page_info = array(
			'title'     => __( 'Replace In Database', 'real-time-auto-find-and-replace' ),
			'sub_title' => __( 'Effortlessly and permanently replace strings in database tables instantly.', 'real-time-auto-find-and-replace' ),
		);

		if ( current_user_can( 'manage_options' ) || current_user_can( 'administrator' ) || current_user_can( self::$nav_cap['replace_in_db'] ) ) {
			$Default_Settings = $this->pages->ReplaceInDB();
			if ( is_object( $Default_Settings ) ) {
				echo $Default_Settings->generate_default_settings( array_merge_recursive( $page_info, array( 'default_settings' => array() ) ) );
			} else {
				echo $Default_Settings, Util::cs_allowed_html();
			}
		} else {
			$AccessDenied = $this->pages->AccessDenied();
			if ( \is_object( $AccessDenied ) ) {
				echo $AccessDenied->generate_access_denided( array_merge_recursive( $page_info, array( 'default_settings' => array() ) ) );
			} else {
				echo $AccessDenied, Util::cs_allowed_html();
			}
		}
	}

	/**
	 * Restore DB
	 *
	 * @return void
	 */
	public function rtafar_page_restore_db() {
		$page_info = array(
			'title'     => __( 'Replace In Database', 'real-time-auto-find-and-replace' ),
			'sub_title' => __( 'You can restore data to database what you have replaced', 'real-time-auto-find-and-replace' ),
		);

		if ( current_user_can( 'manage_options' ) || current_user_can( 'administrator' ) || current_user_can( self::$nav_cap['restore_in_db'] ) ) {
			?>
				<img src="<?php echo \esc_html( CS_RTAFAR_PLUGIN_ASSET_URI ); ?>img/restore-db-pro.png" style="width: 99%" />
			<?php
		} else {
			$AccessDenied = $this->pages->AccessDenied();
			if ( \is_object( $AccessDenied ) ) {
				echo $AccessDenied->generate_access_denided( array_merge_recursive( $page_info, array( 'default_settings' => array() ) ) );
			} else {
				echo $AccessDenied, Util::cs_allowed_html();
			}
		}
	}

	/**
	 * generate instance
	 *
	 * @return void
	 */
	public static function get_instance() {
		if ( ! ( self::$_instance instanceof self ) ) {
			self::$_instance = new self();
		}
		return self::$_instance;
	}

	/**
	 * load funnel builder scripts
	 */
	public function rtafr_register_admin_settings_scripts() {

		// register scripts
		add_action( 'admin_enqueue_scripts', array( $this, 'rtafar_load_settings_scripts' ) );

		// init current screen
		$this->init_current_screen();

		// load all admin footer script
		add_action( 'admin_footer', array( $this, 'rtafar_load_admin_footer_script' ) );
	}

	/**
	 * Load admin scripts
	 */
	public function rtafar_load_settings_scripts( $page_id ) {
		return Scripts_Settings::load_admin_settings_scripts( $page_id, $this->rtafr_menus );
	}

	/**
	 * load custom scripts on admin footer
	 */
	public function rtafar_load_admin_footer_script() {
		return Scripts_Settings::load_admin_footer_script( $this->current_screen->id, $this->rtafr_menus );
	}



	/**
	 * Handler external redirect
	 *
	 * @return void
	 */
	public function rtafar_handle_external_redirects() {
		if ( empty( $_GET['page'] ) ) {
			return;
		}

		if ( 'cs-bfar-go-pro' === $_GET['page'] ) {
			esc_html_e( 'Please wait a while redirecting..', 'real-time-auto-find-and-replace' );

			add_action(
				'admin_footer',
				function () {
					$redirect_url = Util::cs_get_pro_link( Util::cs_pro_link() . '?utm_source=wp-menu&utm_campaign=gopro&utm_medium=wp-dash' );
					?>
					<script type="text/javascript">
						window.location.href = '<?php echo \esc_url( $redirect_url ); ?>';
					</script>
					<?php
				}
			);
		}
	}
}
