<?php namespace RealTimeAutoFindReplace\lib;

/**
 * Util Functions
 *
 * @package Library
 * @since 1.0.0
 * @author CodeSolz <customer-service@codesolz.com>
 */

if ( ! defined( 'CS_RTAFAR_VERSION' ) ) {
	exit;
}


class RTAFAR_DB {

	/**
	 * Get all tables in db
	 *
	 * @return void
	 */
	public static function get_tables() {
		global $wpdb;
		return $wpdb->get_col( 'SHOW TABLES' );
	}

	/**
	 * Get tables size
	 *
	 * @return void
	 */
	public static function get_sizes( $type = '' ) {
		global $wpdb;

		$sizes           = array();
		$active          = array();
		$freeVersionTbls = self::freeVersionTbls();
		$tables          = $wpdb->get_results( 'SHOW TABLE STATUS', ARRAY_A );

		// pre_print($tables);

		if ( is_array( $tables ) && ! empty( $tables ) ) {

			foreach ( $tables as $table ) {
				// $size = \round( $table['Data_length'] / 1024 / 1024, 2 );
				$size = self::tbl_size_mb( $table['Data_length'] );

				// escape plugins tbl
				if ( false !== strpos( $table['Name'], '_rtafar_' ) ) {
					continue;
				}

				if ( $type && ! \in_array( $table['Name'], $freeVersionTbls ) ) {
					// Translators: %1$s is the table name; %2$s is the size in MB; %3$s is the number of rows.
					$sizes[ $table['Name'] . '_disabled' ] = sprintf( __( '%1$s - ( %2$s MB - %3$s Rows) - Pro version only!', 'real-time-auto-find-and-replace' ), $table['Name'], $size, $table['Rows'] );
				} else {
					// Translators: %1$s is the table name; %2$s is the size in MB; %3$s is the number of rows.
					$active[ $table['Name'] ] = sprintf( __( '%1$s - ( %2$s MB - %3$s Rows )', 'real-time-auto-find-and-replace' ), $table['Name'], $size, $table['Rows'] );
				}
			}
		}

		$all_selector = array(
			'select_all'   => __( 'Select All', 'real-time-auto-find-and-replace' ),
			'unselect_all' => __( 'Unselect All', 'real-time-auto-find-and-replace' ),
		);

		return \array_merge_recursive( $all_selector, $active, $sizes );
	}

	/**
	 * Gets the columns in a table.
	 *
	 * @access public
	 * @param  string $table The table to check.
	 * @return array
	 */
	public static function get_columns( $table ) {

		if ( $table == 'select_all' ) {
			return false;
		}

		global $wpdb;
		$primary_key = null;
		$columns     = array();
		$fields      = $wpdb->get_results( 'DESCRIBE ' . $table );

		if ( is_array( $fields ) ) {
			foreach ( $fields as $column ) {
				$columns[] = $column->Field;
				if ( $column->Key == 'PRI' ) {
					$primary_key = $column->Field;
				}
			}
		}

		return array( $primary_key, $columns );
	}

	/**
	 * Get Number of Rows & Size of a Table
	 *
	 * @param [type] $table
	 * @return void
	 */
	public static function get_info_of_tbl( $table ) {
		if ( empty( $table ) ) {
			return false;
		}

		global $wpdb;
		$res = $wpdb->get_row( $wpdb->prepare( 'SHOW TABLE STATUS like %s', $table ), ARRAY_A );
		if ( $res ) {
			// $res = (array) $res;
			$res['Data_length_mb'] = self::tbl_size_mb( $res['Data_length'] );
		}

		return $res;
	}

	/**
	 * Get size to MB
	 *
	 * @param [type] $data_length
	 * @return void
	 */
	public static function tbl_size_mb( $data_length ) {
		if ( empty( $data_length ) ) {
			return 0; }

		return \round( $data_length / 1024 / 1024, 2 );
	}

	/**
	 * Get Free Version
	 * Tables list
	 *
	 * @return void
	 */
	private static function freeVersionTbls() {
		global $wpdb;
		return array(
			$wpdb->base_prefix . 'posts',
			$wpdb->base_prefix . 'postmeta',
			$wpdb->base_prefix . 'options',
		);
	}
}
