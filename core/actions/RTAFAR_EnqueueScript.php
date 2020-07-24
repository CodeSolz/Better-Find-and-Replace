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

if ( ! \class_exists( 'RTAFAR_EnqueueScript' ) ) {

	class RTAFAR_EnqueueScript {

		function __construct() {

			add_action( 'admin_enqueue_scripts', array( $this, 'rtrar_action_admin_enqueue_scripts' ) );
		}

		/**
		 * Enqueue admin scripts
		 *
		 * @return void
		 */
		public function rtrar_action_admin_enqueue_scripts() {
			wp_enqueue_script( 'jquery' );
			wp_enqueue_script( 'admin.app.global', CS_RTAFAR_PLUGIN_ASSET_URI . 'js/rtafar.admin.global.min.js', false );
		}

	}
}



