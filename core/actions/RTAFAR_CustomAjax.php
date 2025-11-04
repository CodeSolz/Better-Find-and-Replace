<?php namespace RealTimeAutoFindReplace\actions;

use RealTimeAutoFindReplace\admin\builders\AjaxResponseBuilder;

/**
 * Class: Custom ajax call
 *
 * @package Admin
 * @since 1.0.0
 * @author M.Tuhin <info@codesolz.net>
 */

if ( ! defined( 'CS_RTAFAR_VERSION' ) ) {
	die();
}


class RTAFAR_CustomAjax {

	function __construct() {
		add_action( 'wp_ajax_rtafar_ajax', array( $this, 'rtafar_ajax' ) );
		add_action( 'wp_ajax_nopriv_rtafar_ajax', array( $this, 'rtafar_ajax' ) );
	}


	/**
	 * Allowed methods for ajax call
	 *
	 * @return array
	 */
	private function allowed_methods() {
		return array(
			'aihandler@savesettings' => array(
				'callback' => array( '\RealTimeAutoFindReplace\admin\functions\aiHandler', 'saveSettings' ),
				'cap'      => 'manage_options',
			),
			'aihandler@getaisuggestion' => array(
				'callback' => array( '\RealTimeAutoFindReplace\admin\functions\aiHandler', 'getAiSuggestion' ),
				'cap'      => 'manage_options',
			),
			'masking@add_masking_rule' => array(
				'callback' => array( '\RealTimeAutoFindReplace\admin\functions\Masking', 'add_masking_rule' ),
				'cap'      => 'manage_options',
			),
			'dbfuncreplaceindb@get_tables_in_select_options' => array(
				'callback' => array( '\RealTimeAutoFindReplace\admin\options\functions\DbFuncReplaceInDb', 'get_tables_in_select_options' ),
				'cap'      => 'manage_options',
			),
			'dbfuncreplaceindb@get_urls_in_select_options' => array(
				'callback' => array( '\RealTimeAutoFindReplace\admin\options\functions\DbFuncReplaceInDb', 'get_urls_in_select_options' ),
				'cap'      => 'manage_options',
			),
			'dbfuncreplaceindb@get_db_cols_select_options' => array(
				'callback' => array( '\RealTimeAutoFindReplace\admin\options\functions\DbFuncReplaceInDb', 'get_db_cols_select_options' ),
				'cap'      => 'manage_options',
			),
			'dbreplacer@db_string_replace' => array(
				'callback' => array( '\RealTimeAutoFindReplace\admin\functions\DbReplacer', 'db_string_replace' ),
				'cap'      => 'manage_options',
			),
			'mediaimagereplacer@handlemediareplace' => array(
				'callback' => array( '\RealTimeAutoFindReplace\admin\functions\MediaImageReplacer', 'handleMediaReplace' ),
				'cap'      => 'manage_options',
			),
		);	
	}

	/**
	 * custom ajax call
	 */
	public function rtafar_ajax() {

		if ( false === check_ajax_referer( SECURE_AUTH_SALT, 'cs_token', false ) ) {
			wp_send_json(
				array(
					'status' => false,
					'title'  => __( 'Invalid token', 'real-time-auto-find-and-replace' ),
					'text'   => __( 'Sorry! we are unable recognize your auth!', 'real-time-auto-find-and-replace' ),
				)
			);
		}

		$raw_method = isset( $_POST['method'] ) ? wp_unslash( $_POST['method'] ) : '';
		$raw_data   = isset( $_POST['data'] ) ? wp_unslash( $_POST['data'] ) : wp_unslash( $_POST );

		$method = strtolower( trim( (string) $raw_method ) );
		$data   = $raw_data;

		if ( empty( $method ) ) {
			wp_send_json(
				array(
					'status' => false,
					'title'  => __( 'Method Error!', 'real-time-auto-find-and-replace' ),
					'text'   => __( 'Method missing / invalid!', 'real-time-auto-find-and-replace' ),
				)
			);
		}

		$allowed_methods = (array) apply_filters( 'rtafar_allowed_methods', $this->allowed_methods() );

		$lookup = array();
		foreach ( $allowed_methods as $key => $cfg ) {
			$norm_key = strtolower( preg_replace( '/[^a-z0-9@_\\\:-]/i', '', $key ) );
			// ensure shape and defaults
			$callback = isset( $cfg['callback'] ) ? $cfg['callback'] : null;
			$cap      = isset( $cfg['cap'] ) ? $cfg['cap'] : 'manage_options';
			$lookup[ $norm_key ] = array(
				'callback' => $callback,
				'cap'      => $cap,
			);
		}

		$norm_method_key = strtolower( preg_replace( '/[^a-z0-9@_\\\:-]/i', '', $method ) );
		if ( ! isset( $lookup[ $norm_method_key ] ) ) {
			wp_send_json(
				array(
					'status' => false,
					'title'  => __( 'Method Error!', 'real-time-auto-find-and-replace' ),
					'text'   => __( 'Method parameter missing / invalid!', 'real-time-auto-find-and-replace' ),
				)
			);
		}

		$entry = $lookup[ $norm_method_key ];
		$required_cap = $entry['cap'] ?? 'manage_options';
		if ( ! current_user_can( $required_cap ) ) {
			wp_send_json(
				array(
					'status' => false,
					'title'  => __( 'Permission Error!', 'real-time-auto-find-and-replace' ),
					'text'   => __( 'Insufficient permissions to perform this action!', 'real-time-auto-find-and-replace' ),
				)
			);
		}

		$callable = $this->make_callable( $entry['callback'] );
		if ( ! $callable ) {
			wp_send_json(
				array(
					'status' => false,
					'title'  => __( 'Error!', 'real-time-auto-find-and-replace' ),
					'text'   => __( 'Configured callback not callable', 'real-time-auto-find-and-replace' ),
				)
			);
		}

		try {
			$result = call_user_func( $callable, $data );

			if ( is_wp_error( $result ) ) {
				wp_send_json(
					array(
						'status' => false,
						'title'  => __( 'Error!', 'real-time-auto-find-and-replace' ),
						'text'   => $result->get_error_message()
					)
				);
			}

			if ( is_string( $result ) && strlen( $result ) > 32768 ) {
				$result = substr( $result, 0, 32768 );
			}

			wp_send_json_success( $result );
		} catch ( Throwable $t ) {
			error_log( 'rtafar : ajax handler error: ' . $t->getMessage() );
			wp_send_json(
				array(
					'status' => false,
					'title'  => __( 'Error!', 'real-time-auto-find-and-replace' ),
					'text'   => 'Internal error'
				)
			);
		}

	}

	/**
	 * Make callable from string
	 *
	 * @param mixed $callback
	 * @return callable|null
	 */
	private function make_callable( $callback ) {
		if ( is_callable( $callback ) ) {
			return $callback;
		}

		if ( is_array( $callback ) && isset( $callback[0], $callback[1] ) && is_string( $callback[0] ) ) {
			$class  = $callback[0];
			$method = $callback[1];

			if ( class_exists( $class ) ) {
				$obj = new $class();
				if ( is_callable( array( $obj, $method ) ) ) {
					return array( $obj, $method );
				}
			}
		}

		return null;
	}


}
