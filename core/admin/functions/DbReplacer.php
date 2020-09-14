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

use RealTimeAutoFindReplace\lib\Util;

class DbReplacer {

	/**
	 * Hold Find & Replace
	 *  Settings
	 *
	 * @var array
	 */
	private $settings = array();

	/**
	 * Hold Dry Run Report
	 *
	 * @var array
	 */
	public $dryRunReport = array();

	/**
	 * Init
	 *
	 * @param array $settings
	 */
	public function __construct( $settings = array() ) {
		$this->settings = $settings;
	}

	/**
	 * String Replace In Database
	 *
	 * @param [type] $user_query
	 * @return void
	 */
	public function db_string_replace( $user_query ) {
		$userInput = Util::check_evil_script( $user_query['cs_db_string_replace'] );
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

		$find           = $this->format_find( $find );
		$replace        = $this->format_replace( $replace );
		$this->settings = Util::check_evil_script( $user_query );

		$whereToReplace = $userInput['where_to_replace'];

		global $wpdb;
		$i           = 0;
		$replaceType = '';
		// replace type is table
		if ( $whereToReplace == 'tables' ) {
			$tables      = isset( $user_query['db_tables'] ) ? $user_query['db_tables'] : '';
			$replaceType = 'text';

			if ( ! empty( $tables ) && in_array( $wpdb->base_prefix . 'posts', $tables ) ) {
				$i += $this->tbl_post( $find, $replace );
				if ( ( $key = array_search( $wpdb->base_prefix . 'posts', $tables ) ) !== false ) {
					unset( $tables[ $key ] );
				}
			}

			if ( ! empty( $tables ) && in_array( $wpdb->base_prefix . 'postmeta', $tables ) ) {
				$i += $this->tbl_postmeta( $find, $replace );
				if ( ( $key = array_search( $wpdb->base_prefix . 'postmeta', $tables ) ) !== false ) {
					unset( $tables[ $key ] );
				}
			}

			if ( ! empty( $tables ) && in_array( $wpdb->base_prefix . 'options', $tables ) ) {
				$i += $this->tbl_options( $find, $replace );
				if ( ( $key = array_search( $wpdb->base_prefix . 'options', $tables ) ) !== false ) {
					unset( $tables[ $key ] );
				}
			}

			$res = apply_filters( 'bfrp_custom_tables', $this->settings, $tables );

			$i                 += isset( $res['i'] ) ? (int) $res['i'] : 0;
			$this->dryRunReport = isset( $res['dryRunReport'] ) ? \array_merge_recursive( $this->dryRunReport, $res['dryRunReport'] ) : $this->dryRunReport;

		} elseif ( $whereToReplace == 'urls' ) {
			$inWhichUrl  = isset( $user_query['url_options'] ) ? $user_query['url_options'] : '';
			$replaceType = 'URLs';
			$i          += $this->replace_urls( $find, $replace, $inWhichUrl );
		}

		$dryRunReport = array();
		if ( isset( $this->settings['cs_db_string_replace']['dry_run'] ) ) {
			$dryRunReport = array(
				'show_custom_content' => true,
				'replacement'         => $i,
				'replacementInTable'  => count( $this->dryRunReport ),
				'dryRunReport'        => $this->dryRunReport,
			);
		}

		return wp_send_json(
			array(
				'status'        => true,
				'title'         => 'Success!',
				'text'          => sprintf( __( 'Thank you! replacement completed!. Total %1$s replaced : %2$d', 'real-time-auto-find-and-replace' ), $replaceType, $i ),
				'nothing_found' => __( 'Sorry! Nothing Found!', 'real-time-auto-find-and-replace' ),
			) + $dryRunReport
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
				$is_replaced = $this->bfrReplace(
					$find,
					$replace,
					$item->post_title,
					$wpdb->base_prefix . 'posts',
					$item->ID, // row id
					'post_title',
					array( 'ID' => $item->ID )
				);

				if ( true === $is_replaced ) {
					$i++;
				}

				// replace in post_content
				$is_replaced = $this->bfrReplace(
					$find,
					$replace,
					$item->post_content,
					$wpdb->base_prefix . 'posts',
					$item->ID,
					'post_content',
					array( 'ID' => $item->ID )
				);

				if ( true === $is_replaced ) {
					$i++;
				}

				// replace in post_excerpt
				$is_replaced = $this->bfrReplace(
					$find,
					$replace,
					$item->post_excerpt,
					$wpdb->base_prefix . 'posts',
					$item->ID,
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

				$is_replaced = $this->bfrReplace(
					$find,
					$replace,
					$item->meta_value,
					$wpdb->base_prefix . 'postmeta',
					$item->meta_id,
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

				$is_replaced = $this->bfrReplace(
					$find,
					$replace,
					$item->option_value,
					$wpdb->base_prefix . 'options',
					$item->option_id,
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
		$r        = 0;
		$urlTypes = array();

		if ( $inWhichUrl ) {
			if ( \in_array( 'post', $inWhichUrl ) ) {
				$urlTypes[] = "post_type = 'post' ";
				if ( ( $key = array_search( 'post', $inWhichUrl ) ) !== false ) {
					unset( $inWhichUrl[ $key ] );
				}
			}

			if ( \in_array( 'page', $inWhichUrl ) ) {
				$urlTypes[] = "post_type = 'page' ";
				if ( ( $key = array_search( 'page', $inWhichUrl ) ) !== false ) {
					unset( $inWhichUrl[ $key ] );
				}
			}

			if ( \in_array( 'attachment', $inWhichUrl ) ) {
				$urlTypes[] = "post_type = 'attachment' ";
				if ( ( $key = array_search( 'attachment', $inWhichUrl ) ) !== false ) {
					unset( $inWhichUrl[ $key ] );
				}
			}
		}

		// find & replace in db
		$r = $this->urlFromPostTables( $find, $replace, $urlTypes );

		// replace custom urls - category / taxonomy etc
		$res = apply_filters( 'bfrp_url_replacer', $this->settings, $inWhichUrl );

		$r                 += isset( $res['i'] ) ? (int) $res['i'] : 0;
		$this->dryRunReport = isset( $res['dryRunReport'] ) ? \array_merge_recursive( $this->dryRunReport, $res['dryRunReport'] ) : $this->dryRunReport;

		// if url replaced flash url permalink
		if ( $r > 0 && ! isset( $this->settings['cs_db_string_replace']['dry_run'] ) ) {
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
	public function urlFromPostTables( $find, $replace, $urlTypes ) {
		global $wpdb;
		$i = 0;

		// make search con
		if ( empty( $urlTypes ) ) {
			return $i;
		}

		$wpdb->flush();

		$con      = \implode( ' OR ', $urlTypes );
		$get_data = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->posts} WHERE (guid like %s OR post_name like %s ) AND ( {$con} ) ",
				"%$find%",
				"%$find%"
			)
		);

		if ( $get_data ) {
			foreach ( $get_data as $item ) {

				// replace in guid
				$is_replaced_guid = $this->bfrReplace(
					$find,
					$replace,
					$item->guid,
					$wpdb->base_prefix . 'posts',
					$item->ID,
					'guid',
					array( 'ID' => $item->ID )
				);

				if ( true === $is_replaced_guid ) {
					$i++;
				}

				// replace in post name
				$is_replaced_name = $this->bfrReplace(
					$find,
					$replace,
					$item->post_name,
					$wpdb->base_prefix . 'posts',
					$item->ID,
					'post_name',
					array( 'ID' => $item->ID )
				);

				if ( true === $is_replaced_name ) {
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
	public function bfrReplace( $find, $replace, $old_value, $tbl, $row_id, $update_col, $update_con ) {

		// check for case-sensitive
		$isCaseInsensitive = false;
		if ( ! isset( $this->settings['cs_db_string_replace']['case_insensitive'] ) ) {
			$new_string = \str_replace( $find, $replace, $old_value );
		} else {
			$new_string        = \str_ireplace( $find, $replace, $old_value );
			$isCaseInsensitive = true;
		}

		$is_updated = false;
		if ( $new_string != $old_value ) {
			global $wpdb;

			// check for dry run
			if ( ! isset( $this->settings['cs_db_string_replace']['dry_run'] ) ) {
				// pre_print( $this->settings );
				$wpdb->update( $tbl, array( $update_col => $new_string ), $update_con );
			} elseif ( $this->settings['cs_db_string_replace']['dry_run'] == 'on' ) {

				$displayReplace = $this->highlightDisplayFindReplace( $find, $replace, $old_value, $new_string, $tbl, $isCaseInsensitive );

				$reportRow = array(
					'row_id'            => $row_id,
					'col'               => $update_col,
					'find'              => $find,
					'replace'           => $replace,
					'old_val'           => $old_value,
					'new_val'           => $new_string,
					'dis_find'          => $displayReplace['find'],
					'dis_replace'       => $displayReplace['replace'],
					'isCaseInsensitive' => $isCaseInsensitive,
				);

				if ( isset( $this->dryRunReport[ $tbl ] ) ) {
					$this->dryRunReport[ $tbl ] = array_merge( $this->dryRunReport[ $tbl ], array( $reportRow ) );
				} else {
					$this->dryRunReport[ $tbl ] = array( $reportRow );
				}
			}

			$is_updated = true;
		}

		return $is_updated;
	}

	/**
	 * Highlight find & replace text
	 *
	 * @param [type] $find
	 * @param [type] $replace
	 * @param [type] $old_value
	 * @param [type] $new_string
	 * @return array
	 */
	private function highlightDisplayFindReplace( $find, $replace, $old_value, $new_string, $tbl, $isCaseInsensitive ) {
		$firstOccu = \strpos( strtolower( $old_value ), strtolower( $find ) );

		$findNewDisStr = Util::insertWordInStringPos( $old_value, '<span class="find">', $firstOccu );
		$findNewDisStr = Util::insertWordInStringPos( $findNewDisStr, '</span>', $firstOccu + Util::charCount( $find ) + 19 );

		$replaceNewDisStr = Util::insertWordInStringPos( $new_string, '<span class="replace">', $firstOccu );
		$replaceNewDisStr = Util::insertWordInStringPos( $replaceNewDisStr, '</span>', $firstOccu + Util::charCount( $replace ) + 22 );

		return array(
			'find'    => $findNewDisStr,
			'replace' => $replaceNewDisStr,
		);
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





