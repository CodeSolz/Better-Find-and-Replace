<?php namespace RealTimeAutoFindReplace\Actions;

/**
 * Class: Register Frontend Scripts
 *
 * @package Action
 * @since 1.0.0
 * @author M.Tuhin <tuhin@codesolz.net>
 */

if ( ! defined( 'CS_RTAFAR_VERSION' ) ) {
	die();
}

use RealTimeAutoFindReplace\admin\functions\Masking;
use RealTimeAutoFindReplace\admin\functions\ProActions;

class RTAFAR_EnqueueScript {

	function __construct() {

		add_action( 'admin_enqueue_scripts', array( $this, 'rtrar_action_admin_enqueue_scripts' ), 10 );

		add_action( 'wp_enqueue_scripts', array( $this, 'rtrarAppRegisterVars' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'rtrarAppEnqueueScripts' ), 90 );
	}

	/**
	 * Enqueue admin scripts
	 *
	 * @return void
	 */
	public function rtrar_action_admin_enqueue_scripts() {
		wp_enqueue_script( 'jquery' );
		wp_enqueue_script( 'admin.app.global', CS_RTAFAR_PLUGIN_ASSET_URI . 'js/rtafar.admin.global.min.js', array(), CS_RTAFAR_VERSION, true );

		wp_enqueue_style( 'rtafar-global', CS_RTAFAR_PLUGIN_ASSET_URI . 'css/rtafar-admin-global-style.min.css', array(), CS_RTAFAR_VERSION );

		// register custom data
		wp_localize_script(
			'admin.app.global',
			'rtafr',
			array(
				'is_pro_activate' => ProActions::hasPro(),
				'baT'             => __( 'Bulk Action', 'real-time-auto-find-and-replace' ),
				'rT'              => __( 'Replace', 'real-time-auto-find-and-replace' ),
				'fT'              => __( 'Find', 'real-time-auto-find-and-replace' ),
				'aTp'             => __( 'Apply', 'real-time-auto-find-and-replace' ),
				'aT'              => __( 'Apply - Pro version only', 'real-time-auto-find-and-replace' ),
				'pvt'             => __( 'Pro version required!', 'real-time-auto-find-and-replace' ),
				'rBt'             => __( 'Replace - Pro', 'real-time-auto-find-and-replace' ),
				'drT'             => __( 'Creating Reports..', 'real-time-auto-find-and-replace' ),
				'frBnT'           => __( 'Find & Replace', 'real-time-auto-find-and-replace' ),
				'frDrBnT'         => __( 'Create Reports', 'real-time-auto-find-and-replace' ),
				'bTsN'            => __( 'Search Next', 'real-time-auto-find-and-replace' ),
				'bTsP'            => __( 'Search Prev', 'real-time-auto-find-and-replace' ),

				// "Replace in DB" live-run confirmation (dry_run unchecked).
				// Pro has a one-click Restore page (populated by the same
				// bfar_save_item_history hook DbReplacer already fires), so its
				// warning can point there instead of just saying "have a backup".
				'dbCfmTitle'      => __( 'Are you sure?', 'real-time-auto-find-and-replace' ),
				'dbCfmTextFree'   => __( 'This will make permanent changes to your database and cannot be undone. Please make sure you have a backup before proceeding.', 'real-time-auto-find-and-replace' ),
				'dbCfmTextPro'    => sprintf(
					/* translators: %1$s: opening <a> tag linking to the Restore page, %2$s: closing </a> tag */
					__( 'This will make permanent changes to your database. No worries though — every replaced value is logged, so you can restore it with one click from the %1$sRestore%2$s section afterwards.', 'real-time-auto-find-and-replace' ),
					'<a href="' . esc_url( admin_url( 'admin.php?page=cs-bfar-restore-database' ) ) . '" target="_blank" rel="noopener noreferrer">',
					'</a>'
				),
				'dbCfmYes'        => __( 'Yes, replace it!', 'real-time-auto-find-and-replace' ),
				'dbCfmNo'         => __( 'Cancel', 'real-time-auto-find-and-replace' ),

				'fedNotOfPerm'    => add_query_arg(
					array(
						CS_NOTICE_ID => 'Feedback_offPerm',
					),
					admin_url()
				),
				'pimg' => CS_RTAFAR_PLUGIN_ASSET_URI . 'img/new-media-placeholder250x207.svg',
				'opimg' => CS_RTAFAR_PLUGIN_ASSET_URI . 'img/old-media-placeholder250x207.svg'
			)
		);
	}

	/**
	 * Register locale
	 *
	 * @return void
	 */
	public function rtrarAppRegisterVars() {

		// load scripts on frontend
		if ( ! is_admin() ) {
			wp_enqueue_script( 'rtrar.appLocal', CS_RTAFAR_PLUGIN_ASSET_URI . 'js/rtafar.local.js', array(), CS_RTAFAR_VERSION, true );

			// get jquery / ajax replace rule
			$rules = Masking::get_rules( 'all', '', 'ajaxContent' );

			// register custom data
			wp_localize_script(
				'rtrar.appLocal',
				'rtafr',
				array( 'rules' => $rules )
			);
		}
	}

	/**
	 * Enqueue app scripts
	 *
	 * @return void
	 */
	public function rtrarAppEnqueueScripts() {
		if ( ! ProActions::hasPro() ) {
			// load scripts on frontend
			if ( ! is_admin() ) {
				wp_enqueue_script( 'rtrar.app', CS_RTAFAR_PLUGIN_ASSET_URI . 'js/rtafar.app.min.js', array(), CS_RTAFAR_VERSION, true );
			}
		}
	}
}
