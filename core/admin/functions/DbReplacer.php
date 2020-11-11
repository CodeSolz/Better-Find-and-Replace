<?php namespace RealTimeAutoFindReplace\admin\functions;

/**
 * From Builder Class
 *
 * @package Functions
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

		$this->settings = Util::check_evil_script( $user_query );
		$find           = $this->format_find( $find );
		$replace        = $this->format_replace( $replace );
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

			$totalReplacement = \array_reduce(
				$this->dryRunReport,
				function( $carry, $item ) {
					$carry += \array_sum( \array_column( $item, 'findCount' ) );
					return $carry;
				}
			);

			$dryRunReport = array(
				'show_custom_content' => true,
				'replacement'         => $i,
				'replacementTotal'    => (int) $totalReplacement,
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
		$get_data = $wpdb->get_results( "select * from {$wpdb->posts}" );
		if ( $get_data ) {

			foreach ( $get_data as $item ) {

				// replace in post_title
				$is_replaced = $this->bfrReplace(
					$find,
					$replace,
					$item->post_title,
					$wpdb->base_prefix . 'posts',
					$item->ID, // row id
					'ID', // primary key
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
					'ID', // primary key
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
					'ID', // primary key
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
					'meta_id', // primary key
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
					'option_id', // primary key
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
					'ID',
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
					'ID',
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
	public function bfrReplace( $find, $replace, $old_value, $tbl, $row_id, $primary_col, $update_col, $update_con ) {

		//if old value is empty
		if( empty( $old_value )){
			return false;
		}

		// check for case-sensitive
		$isCaseInsensitive = false;

		$old_value = $this->format_old_value( $old_value );

		if ( isset( $this->settings['cs_db_string_replace']['whole_word'] ) ||
			isset( $this->settings['cs_db_string_replace']['unicode_modifier'] )
		) {

			if ( ! isset( $this->settings['cs_db_string_replace']['case_insensitive'] ) ) {
				$formattedFind = $this->formatFindWholeWord( $find );
				$new_string    = \preg_replace( $formattedFind, $replace, $old_value );
			} else {
				$formattedFind     = $this->formatFindWholeWord( $find, true );
				$new_string        = \preg_replace( $formattedFind, $replace, $old_value );
				$isCaseInsensitive = true;
			}
		} else {

			if ( ! isset( $this->settings['cs_db_string_replace']['case_insensitive'] ) ) {
				$new_string = \str_replace( $find, $replace, $old_value );
			} else {
				$new_string        = \str_ireplace( $find, $replace, $old_value );
				$isCaseInsensitive = true;
			}

			
		}
		
		$is_updated = false;
		if ( $new_string != $old_value ) {
			global $wpdb;
			
			// check for dry run
			if ( ! isset( $this->settings['cs_db_string_replace']['dry_run'] ) ) {
				// pre_print( $this->settings );
				$wpdb->update( $tbl, array( $update_col => $new_string ), $update_con );
			} elseif ( $this->settings['cs_db_string_replace']['dry_run'] == 'on' ) {

				$displayReplace = $this->highlightDisplayFindReplace(
					array(
						'formattedFind'     => isset( $formattedFind ) ? $formattedFind : '',
						'find'              => $find,
						'replace'           => $replace,
						'old_value'         => $old_value,
						'new_value'         => $new_string,
						'isCaseInsensitive' => $isCaseInsensitive,
					)
				);

				$reportRow = array(
					'bfrp_' . $row_id .'_' . $update_col => array(
						'tbl'          => $tbl,
						'rid'          => $row_id,
						'pCol'         => $primary_col, // primary col
						'col'          => $update_col,
						'find'         => $find,
						'replace'      => $replace,
						'ici'          => $isCaseInsensitive,
						'old_val'      => $old_value,
						'new_val'      => $new_string,
						'dis_find'     => $displayReplace['find'],
						'dis_replace'  => $displayReplace['replace'],
						'findCount'    => $displayReplace['findCount'],
						'replaceCount' => $displayReplace['replaceCount'],
					),
				);

				if ( isset( $this->dryRunReport[ $tbl ] ) ) {
					$this->dryRunReport[ $tbl ] = array_merge_recursive( $this->dryRunReport[ $tbl ], $reportRow );
					// pre_print($this->dryRunReport);
				} else {
					$this->dryRunReport[ $tbl ] = $reportRow;
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
	private function highlightDisplayFindReplace( $args ) {

		$countFind = 0;

		if ( isset( $args['formattedFind'] ) && ! empty( $args['formattedFind'] ) ) {
			$findNewDisStr = \preg_replace( $args['formattedFind'], "<span class='find'>$1</span>", \esc_html( $args['old_value'] ), -1, $countFind );
		} else {
			if ( \is_array( $args['find'] ) ) {
				$pregCase = '#($0)#';
				if ( true === $args['isCaseInsensitive'] ) {
					$pregCase .= 'i';
				}
				$find = \preg_filter( '#^(.*?)$#', $pregCase, $args['find'] );
			} else {
				$find = '#(' . $args['find'] . ')#';

				if ( true === $args['isCaseInsensitive'] ) {
					$find .= 'i';
				}
			}

			$findNewDisStr = \preg_replace( \esc_html( $find ), "<span class='find'>$1</span>", \esc_html( $args['old_value'] ), -1, $countFind );
		}

		// get replace highlight
		if ( \is_array( $args['replace'] ) ) {
			$pregCase = '#($0)#';
			if ( true === $args['isCaseInsensitive'] ) {
				$pregCase .= 'i';
			}
			$replace = \preg_filter( '#^(.*?)$#', $pregCase, $args['replace'] );
		} else {
			$replace = '#(' . $args['replace'] . ')#';

			if ( true === $args['isCaseInsensitive'] ) {
				$replace .= 'i';
			}
		}

		$countReplace     = 0;
		$replaceNewDisStr = \preg_replace( \esc_html( $replace ), "<span class='replace'>$1</span>", \esc_html( $args['new_value'] ), -1, $countReplace );

		return array(
			'find'         => $findNewDisStr,
			'replace'      => $replaceNewDisStr,
			'findCount'    => $countFind,
			'replaceCount' => $countReplace,
		);

	}


	/**
	 * format find for whole word
	 *
	 * @param [type]  $find
	 * @param boolean $isCaseInsensitive
	 * @return void
	 */
	private function formatFindWholeWord( $find, $isCaseInsensitive = false ) {
		if ( \has_filter( 'bfrp_format_find_whole_word' ) ) {
			return apply_filters( 'bfrp_format_find_whole_word', $this->settings, $isCaseInsensitive, $find );
		}

		$pregCase = '#\b($0)\b#';
		if ( true === $isCaseInsensitive ) {
			$pregCase .= 'i';
		}

		return \preg_filter( '#^(.*?)$#', $pregCase, $find );
	}

	/**
	 * Format find
	 *
	 * @param [type] $find
	 * @return void
	 */
	private function format_find( $find ) {
		return \stripslashes( $find );
	}
	
	/**
	 * Format replace
	 *
	 * @param [type] $replace
	 * @return void
	 */
	private function format_replace( $replace ) {
		return \stripslashes( $replace );
	}

	/**
	 * Format old_value
	 *
	 * @param [type] $old_value
	 * @return void
	 */
	private function format_old_value( $old_value ) {
		return $old_value;
	}

	

}





