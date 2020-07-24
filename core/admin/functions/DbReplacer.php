<?php namespace RealTimeAutoFindReplace\admin\functions;

/**
 * From Builder Class
 *
 * @package Funcitons
 * @since 1.0.0
 * @author M.Tuhin <info@codesolz.net>
 */

if ( ! defined( 'CS_RTAFAR_VERSION' ) ) {
	exit;
}



class DbReplacer {

	/**
	 * String Replace In Database
	 *
	 * @param [type] $user_query
	 * @return void
	 */
	public function db_string_replace( $user_query ) {
		if ( ! isset( $user_query['cs_db_string_replace']['find'] ) ||
			empty( $find = $user_query['cs_db_string_replace']['find'] ) ||
			! isset( $user_query['cs_db_string_replace']['replace'] ) ||
			empty( $replace = $user_query['cs_db_string_replace']['replace'] )
		) {
			return wp_send_json(
				array(
					'status' => false,
					'title'  => 'Error!',
					'text'   => __( 'Please enter string to find and replace.', 'real-time-auto-find-and-replace' ),
				)
			);
		}

		$find    = $this->format_find( $find );
		$replace = $this->format_replace( $replace );

		global $wpdb;

		$i  = 0;
		$i += $this->tbl_post( $find, $replace );
		$i += $this->tbl_postmeta( $find, $replace );
		$i += $this->tbl_options( $find, $replace );

		return wp_send_json(
			array(
				'status' => true,
				'title'  => 'Success!',
				'text'   => __( 'Thank you! replacement completed!. Total replaced : ' . $i, 'real-time-auto-find-and-replace' ),
			)
		);
	}

	/**
	 * Replace in post table
	 *
	 * @return void
	 */
	private function tbl_post( $find, $replace ) {
		global $wpdb;
		$i        = 0;
		$get_data = $wpdb->get_results( "select * from {$wpdb->posts} " );
		if ( $get_data ) {
			foreach ( $get_data as $item ) {

				// replace in post_title
				$is_replaced = $this->replace(
					$find,
					$replace,
					$item->post_title,
					'posts',
					'post_title',
					array( 'ID' => $item->ID )
				);

				if ( true === $is_replaced ) {
					$i++;
				}

				// replace in post_content
				$is_replaced = $this->replace(
					$find,
					$replace,
					$item->post_content,
					'posts',
					'post_content',
					array( 'ID' => $item->ID )
				);

				if ( true === $is_replaced ) {
					$i++;
				}

				// replace in post_excerpt
				$is_replaced = $this->replace(
					$find,
					$replace,
					$item->post_excerpt,
					'posts',
					'post_excerpt',
					array( 'ID' => $item->ID )
				);

				if ( true === $is_replaced ) {
					$i++;
				}

				// replace in guid
				$is_replaced = $this->replace(
					$find,
					$replace,
					$item->guid,
					'posts',
					'guid',
					array( 'ID' => $item->ID )
				);

				if ( true === $is_replaced ) {
					$i++;
				}
			}
		}
		return $i;
	}

	/**
	 * Replace in post meta table
	 *
	 * @return void
	 */
	private function tbl_postmeta( $find, $replace ) {
		global $wpdb;
		$i        = 0;
		$get_data = $wpdb->get_results( "select * from {$wpdb->postmeta} " );
		if ( $get_data ) {
			foreach ( $get_data as $item ) {

				$is_replaced = $this->replace(
					$find,
					$replace,
					$item->meta_value,
					'postmeta',
					'meta_value',
					array( 'meta_id' => $item->meta_id )
				);

				if ( true === $is_replaced ) {
					$i++;
				}
			}
		}
		return $i;
	}

	/**
	 * Replace in option table
	 *
	 * @param [type] $find
	 * @param [type] $replace
	 * @return void
	 */
	private function tbl_options( $find, $replace ) {
		global $wpdb;
		$i        = 0;
		$get_data = $wpdb->get_results( "select * from {$wpdb->options} " );
		if ( $get_data ) {
			foreach ( $get_data as $item ) {

				$is_replaced = $this->replace(
					$find,
					$replace,
					$item->option_value,
					'options',
					'option_value',
					array( 'option_id' => $item->option_id )
				);

				if ( true === $is_replaced ) {
					$i++;
				}
			}
		}
		return $i;
	}

	/**
	 * Replace String In DB
	 *
	 * @param [type] $find
	 * @param [type] $replace
	 * @param [type] $old_value
	 * @param [type] $tbl
	 * @param [type] $update_col
	 * @param [type] $update_con
	 * @return void
	 */
	private function replace( $find, $replace, $old_value, $tbl, $update_col, $update_con ) {
		$new_string = \str_replace( $find, $replace, $old_value );
		$is_updated = false;
		if ( $new_string != $old_value ) {
			global $wpdb;
			$wpdb->update( $wpdb->prefix . $tbl, array( $update_col => $new_string ), $update_con );
			$is_updated = true;
		}

		return $is_updated;
	}

	/**
	 * Format find
	 *
	 * @param [type] $find
	 * @return void
	 */
	private function format_find( $find ) {
		if ( false !== \strpos( $find, ',' ) ) {
			$find = explode( ',', $find );
			$find = array_map(
				function( $str ) {
					return '/' . trim( $str ) . '/';
				},
				$find
			);
		}
		return $find;
	}

	/**
	 * Format replace
	 *
	 * @param [type] $replace
	 * @return void
	 */
	private function format_replace( $replace ) {
		if ( false !== \strpos( $replace, ',' ) ) {
			$replace = explode( ',', $replace );
			$replace = array_map(
				function( $str ) {
					return $str;
				},
				$replace
			);
		}

		return $replace;
	}

}





