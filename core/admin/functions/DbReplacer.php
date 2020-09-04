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
		$userInput = $user_query['cs_db_string_replace'];
		if ( ! isset( $userInput['find'] ) ||
			empty( $find = $userInput['find'] ) ||
			! isset( $userInput['replace'] ) ||
			empty( $replace = $userInput['replace'] )
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

		$whereToReplace = $userInput['where_to_replace'];

		global $wpdb;

		$i           = 0;
		$replaceType = '';
		// replace type is table
		if ( $whereToReplace == 'tables' ) {
			$tables      = isset( $user_query['db_tables'] ) ? $user_query['db_tables'] : '';
			$replaceType = 'text';

			if ( ! empty( $tables ) && in_array( 'posts', $tables ) ) {
				$i += $this->tbl_post( $find, $replace );
			}

			if ( ! empty( $tables ) && in_array( 'postmeta', $tables ) ) {
				$i += $this->tbl_postmeta( $find, $replace );
			}

			if ( ! empty( $tables ) && in_array( 'options', $tables ) ) {
				$i += $this->tbl_options( $find, $replace );
			}
		} elseif ( $whereToReplace == 'urls' ) {
			$inWhichUrl  = isset( $user_query['url_options'] ) ? $user_query['url_options'] : '';
			$replaceType = 'URLs';
			$i          += $this->replace_urls( $find, $replace, $inWhichUrl );
		}

		return wp_send_json(
			array(
				'status' => true,
				'title'  => 'Success!',
				'text'   => sprintf( __( 'Thank you! replacement completed!. Total %1$s replaced : %2$d', 'real-time-auto-find-and-replace' ), $replaceType, $i ),
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
	 * URL replacer
	 *
	 * @param [type] $find
	 * @param [type] $replace
	 * @param [type] $inWhichUrl
	 * @return void
	 */
	private function replace_urls( $find, $replace, $inWhichUrl ) {
		$r = 0;

		if ( ! empty( $inWhichUrl ) && in_array( 'posts', $inWhichUrl ) ) {
			$r += $this->post_urls( $find, $replace );
		}
		if ( ! empty( $inWhichUrl ) && in_array( 'pages', $inWhichUrl ) ) {
			$r += $this->page_urls( $find, $replace );

		}
		if ( ! empty( $inWhichUrl ) && in_array( 'media', $inWhichUrl ) ) {
			$r += $this->media_urls( $find, $replace );
		}

		// if url replaced flash url permalink
		if ( $r > 0 ) {
			\flush_rewrite_rules();
		}

		return $r;
	}

	/**
	 * Replace post urls
	 *
	 * @param [type] $find
	 * @param [type] $replace
	 * @return void
	 */
	private function post_urls( $find, $replace ) {
		global $wpdb;
		$i        = 0;
		$get_data = $wpdb->get_results( "select * from {$wpdb->posts} where post_type = 'post' " );
		if ( $get_data ) {
			foreach ( $get_data as $item ) {

				// replace in guid
				$is_replaced = $this->replace(
					$find,
					$replace,
					$item->guid,
					'posts',
					'guid',
					array( 'ID' => $item->ID )
				);

				// replace in post name
				$is_replaced = $this->replace(
					$find,
					$replace,
					$item->post_name,
					'posts',
					'post_name',
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
	 * Replace page urls
	 *
	 * @param [type] $find
	 * @param [type] $replace
	 * @return void
	 */
	private function page_urls( $find, $replace ) {
		global $wpdb;
		$i        = 0;
		$get_data = $wpdb->get_results( "select * from {$wpdb->posts} where post_type = 'page' " );
		if ( $get_data ) {
			foreach ( $get_data as $item ) {

				// replace in guid
				$is_replaced = $this->replace(
					$find,
					$replace,
					$item->guid,
					'posts',
					'guid',
					array( 'ID' => $item->ID )
				);

				// replace in post name
				$is_replaced = $this->replace(
					$find,
					$replace,
					$item->post_name,
					'posts',
					'post_name',
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
	 * Replace Media URLs
	 *
	 * @param [type] $find
	 * @param [type] $replace
	 * @return void
	 */
	private function media_urls( $find, $replace ) {
		global $wpdb;
		$i        = 0;
		$get_data = $wpdb->get_results( "select * from {$wpdb->posts} where post_type = 'attachment' " );
		if ( $get_data ) {
			foreach ( $get_data as $item ) {

				// replace in guid
				$is_replaced = $this->replace(
					$find,
					$replace,
					$item->guid,
					'posts',
					'guid',
					array( 'ID' => $item->ID )
				);

				// replace in post name
				$is_replaced = $this->replace(
					$find,
					$replace,
					$item->post_name,
					'posts',
					'post_name',
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





