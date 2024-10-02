<?php namespace RealTimeAutoFindReplace\admin\options\functions;

use RealTimeAutoFindReplace\lib\Util;

/**
 * Class: DB replacer
 *
 * @package Admin
 * @since 1.3.1
 * @author M.Tuhin <info@codesolz.net>
 */

if ( ! defined( 'CS_RTAFAR_VERSION' ) ) {
	die();
}

class DbFuncReplaceInDb {


	/**
	 * Get tables list
	 *
	 * @param array $user_input
	 * @return json
	 */
	public function get_tables_in_select_options( $user_input = array() ) {
		$tables = \apply_filters( 'bfrp_select_tables', array() );

		return wp_send_json( array( 'tables' => $tables ) );
	}

	/**
	 * Get urls
	 *
	 * @param array $user_input
	 * @return json
	 */
	public function get_urls_in_select_options( $user_input = array() ) {
		$urls = \apply_filters(
			'bfrp_url_types',
			array(
				'all'          => __( 'Select All', 'real-time-auto-find-and-replace' ),
				'unselect_all' => __( 'Unselect All', 'real-time-auto-find-and-replace' ),
				'post'         => __( 'Post URLs', 'real-time-auto-find-and-replace' ),
				'page'         => __( 'Page URLs', 'real-time-auto-find-and-replace' ),
				'attachment'   => __( 'Media URLs (images, attachments etc..)', 'real-time-auto-find-and-replace' ),
			)
		);

		return wp_send_json( array( 'urls' => $urls ) );
	}


	/**
	 * Get urls
	 *
	 * @param array $user_input
	 * @return json
	 */
	public function get_db_cols_select_options( $user_input = array() ) {
		// pre_print( $user_input );

		$type = Util::check_evil_script( $user_input['option_type'] );

		$options = \apply_filters(
			'bfrp_db_columns',
			array(
				'all'          => __( 'Select All', 'real-time-auto-find-and-replace' ),
				'unselect_all' => __( 'Unselect All', 'real-time-auto-find-and-replace' ),
				/* translators: %s: post type (e.g., post, page, or custom post type) */
				'post_title'   =>  \ucwords(sprintf( __(  "%s Title" , 'real-time-auto-find-and-replace' ), $type)),
				/* translators: %s: post type (e.g., post, page, or custom post type) */
				'post_content' => \ucwords( sprintf( __( "%s Content" , 'real-time-auto-find-and-replace' ), $type)),
				/* translators: %s: post type (e.g., post, page, or custom post type) */
				'post_excerpt' => \ucwords( sprintf( __(  "%s Excerpt" , 'real-time-auto-find-and-replace' ), $type) ),
			)
		);

		return wp_send_json( array( 'urls' => $options ) );
	}
}
