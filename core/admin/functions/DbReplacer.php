<?php namespace RealTimeAutoFindReplace\admin\functions;

/**
 * Database Replacer Class
 *
 * @package Functions
 * @since 1.0.0
 * @author M.Tuhin <info@codesolz.net>
 */

if ( ! defined( 'CS_RTAFAR_VERSION' ) ) {
	exit;
}

use RealTimeAutoFindReplace\lib\Util;
use RealTimeAutoFindReplace\lib\SerializedReplacer;

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
	 * Hold special chars
	 *
	 * @var string
	 */
	private $bfar_special_chars;

	/**
	 * Report Holder
	 *
	 * @var array
	 */
	private $report_holder = array(
		'find'                   => '',
		'replace'                => '',
		'str'                    => '',
		'cleanStr'               => '',
		'is_preg'                => false,
		'is_regular'             => false,
		'is_case_in_sensitive'   => false,
		'is_serialized'          => false,
		'has_escaped_serialized' => false,
		'is_serialize_data'      => false,
	);

	/**
	 * Whether to also match JSON-escaped / percent-encoded variants of the
	 * find string (e.g. https:\/\/ in Elementor data). Off by default.
	 *
	 * @var bool
	 */
	private $encoded_urls = false;

	/**
	 * Whether the current run is the "All URLs" mode (Where to Replace = urls).
	 * Switches the per-pass variant source to the URL-format builder.
	 *
	 * @var bool
	 */
	private $url_mode = false;

	/**
	 * Whether to also match other formats of the same URL — http/https,
	 * protocol-relative (//), and www / non-www. Opt-in ("Match all URL
	 * formats" checkbox), only effective in URL mode. Off by default.
	 *
	 * @var bool
	 */
	private $url_formats = false;

	/**
	 * Post IDs whose rows were changed during a live (non-dry) run, used to
	 * regenerate builder/object caches afterwards.
	 *
	 * @var array
	 */
	private $touched_post_ids = array();

	/**
	 * Page-builder keys selected on the form (e.g. 'elementor'); scopes which
	 * postmeta rows to target and which caches to clear.
	 *
	 * @var array
	 */
	private $selected_builders = array();

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
		if ( !current_user_can( 'manage_options' ) && !current_user_can( Util::bfar_nav_cap('replace_in_db') ) ) {
			return wp_send_json(
				array(
					'status' => false,
					'title'  => __( 'Access Denied', 'real-time-auto-find-and-replace' ),
                'text'   => __( 'You do not have permission to perform this action.', 'real-time-auto-find-and-replace' ),
				)
			);
        }

		if( empty( $user_query) || ! \is_array($user_query) ){
			return wp_send_json(
				array(
					'status' => false,
					'title'  => __( 'Error!', 'real-time-auto-find-and-replace' ),
					'text'   => __( 'Unable to read the request data. Please send it in the correct format.', 'real-time-auto-find-and-replace' ),
				)
			);
		}

		$userInput = Util::check_evil_script( $user_query['cs_db_string_replace'] );

		// `find` must stay raw — users search for HTML, multiline, and regex
		// content that sanitize_text_field() would strip. Source it from the
		// unsanitized payload rather than the sanitized $userInput copy.
		$find = isset( $user_query['cs_db_string_replace']['find'] )
			? $user_query['cs_db_string_replace']['find']
			: '';

		if ( '' === trim( (string) $find ) ) {
			return wp_send_json(
				array(
					'status' => false,
					'title'  => __( 'Error!', 'real-time-auto-find-and-replace' ),
					'text'   => __( 'Please enter a string to find and replace.', 'real-time-auto-find-and-replace' ),
				)
			);
		}

		$this->bfar_special_chars                      = Util::bfar_special_chars();
		$replace                                       = $this->format_replace( $user_query['cs_db_string_replace']['replace'] );
		$user_query['cs_db_string_replace']['replace'] = $replace;

		$this->settings = Util::check_evil_script( $user_query );

		// Restore raw find/replace inside settings (pro hooks and regex modes
		// consume them); only the structural flags above need sanitizing.
		$this->settings['cs_db_string_replace']['find']    = $find;
		$this->settings['cs_db_string_replace']['replace'] = $replace;

		// Opt-in: also match JSON-escaped / percent-encoded variants (Elementor & JSON).
		$this->encoded_urls = ! empty( $this->settings['cs_db_string_replace']['encoded_urls'] );

		// Opt-in: also match http/https + protocol-relative + www URL forms (URL mode only).
		$this->url_formats = ! empty( $this->settings['cs_db_string_replace']['url_formats'] );

		// Page-builder targeting (scoping + cache regeneration).
		$this->selected_builders = isset( $this->settings['bfrp_page_builders'] ) && is_array( $this->settings['bfrp_page_builders'] )
			? array_values( array_filter( (array) $this->settings['bfrp_page_builders'], 'is_string' ) )
			: array();

		$find           = $this->format_find( $find );
		$whereToReplace = $userInput['where_to_replace'];

		global $wpdb;
		$i           = 0;
		$replaceType = '';
		$custom_data = '';
		// replace type is table
		if ( $whereToReplace == 'tables' ) {
			$tables      = isset( $user_query['db_tables'] ) ? $user_query['db_tables'] : '';
			$replaceType = 'text';

			if ( ! empty( $tables ) && in_array( $wpdb->base_prefix . 'posts', $tables ) &&
					( ! isset( $userInput['large_table'] ) || empty( $userInput['large_table'] ) )
				) {
				$i += $this->tbl_post( $find, $replace );
				if ( ( $key = array_search( $wpdb->base_prefix . 'posts', $tables ) ) !== false ) {
					unset( $tables[ $key ] );
				}
			}

			if ( ! empty( $tables ) && in_array( $wpdb->base_prefix . 'postmeta', $tables ) &&
				( ! isset( $userInput['large_table'] ) || empty( $userInput['large_table'] ) )
			) {
				$i += $this->tbl_postmeta( $find, $replace );
				if ( ( $key = array_search( $wpdb->base_prefix . 'postmeta', $tables ) ) !== false ) {
					unset( $tables[ $key ] );
				}
			}

			if ( ! empty( $tables ) && in_array( $wpdb->base_prefix . 'options', $tables ) &&
				( ! isset( $userInput['large_table'] ) || empty( $userInput['large_table'] ) )
			) {
				$i += $this->tbl_options( $find, $replace );
				if ( ( $key = array_search( $wpdb->base_prefix . 'options', $tables ) ) !== false ) {
					unset( $tables[ $key ] );
				}
			}

			// Content-only WordPress core tables (comments, terms, links). No
			// authentication data (users/usermeta) is touched here — see
			// docs/new-update for the reasoning.
			$large_table_skip = ! empty( $userInput['large_table'] );

			$i += $this->replace_core_table( $tables, $wpdb->base_prefix . 'comments', array( $this, 'tbl_comments' ), $find, $replace, $large_table_skip );
			$i += $this->replace_core_table( $tables, $wpdb->base_prefix . 'commentmeta', array( $this, 'tbl_commentmeta' ), $find, $replace, $large_table_skip );
			$i += $this->replace_core_table( $tables, $wpdb->base_prefix . 'terms', array( $this, 'tbl_terms' ), $find, $replace, $large_table_skip );
			$i += $this->replace_core_table( $tables, $wpdb->base_prefix . 'termmeta', array( $this, 'tbl_termmeta' ), $find, $replace, $large_table_skip );
			$i += $this->replace_core_table( $tables, $wpdb->base_prefix . 'term_taxonomy', array( $this, 'tbl_term_taxonomy' ), $find, $replace, $large_table_skip );
			$i += $this->replace_core_table( $tables, $wpdb->base_prefix . 'links', array( $this, 'tbl_links' ), $find, $replace, $large_table_skip );

			// pre_print( $this->settings );

			$res = apply_filters( 'bfrp_custom_tables', $this->settings, $tables );

			$i                 += isset( $res['i'] ) ? (int) $res['i'] : 0;
			$this->dryRunReport = isset( $res['dryRunReport'] ) ? \array_merge_recursive( $this->dryRunReport, $res['dryRunReport'] ) : $this->dryRunReport;
			$custom_data        = isset( $res['custom_data'] ) ? $res['custom_data'] : $custom_data;

		} elseif ( $whereToReplace == 'urls' ) {
			$inWhichUrl = isset( $user_query['url_options'] ) ? $user_query['url_options'] : '';

			// Require at least one real URL type (Post / Page / Media). The
			// 'all' / 'unselect_all' entries are UI helpers, not real types.
			$selectedUrlTypes = is_array( $inWhichUrl )
				? array_values( array_diff( $inWhichUrl, array( 'all', 'unselect_all', '' ) ) )
				: array();

			if ( empty( $selectedUrlTypes ) ) {
				return wp_send_json(
					array(
						'status' => false,
						'title'  => __( 'Error!', 'real-time-auto-find-and-replace' ),
						'text'   => __( 'Please select at least one URL type (Post, Page or Media URLs) to replace in.', 'real-time-auto-find-and-replace' ),
					)
				);
			}

			$this->url_mode = true;
			$replaceType    = 'URLs';
			$i             += $this->replace_urls( $find, $replace, $inWhichUrl );

		} elseif ( $whereToReplace == 'page' || $whereToReplace == 'post' ) {
			// pre_print($whereToReplace);
			$filters     = isset( $user_query['page_post_filters'] ) ? $user_query['page_post_filters'] : '';
			$replaceType = 'text';
			$i          += $this->tbl_post( $find, $replace, $filters, $whereToReplace );
		}

		$dryRunReport = array();
		if ( isset( $this->settings['cs_db_string_replace']['dry_run'] ) ) {

			$totalReplacement = \array_reduce(
				$this->dryRunReport,
				function ( $carry, $item ) {
					$carry += \array_sum( \array_column( $item, 'findCount' ) );
					return $carry;
				}
			);

			// pre_print( $user_query );

			$dryRunReport = array(
				'show_custom_content' => true,
				'replacement'         => $i,
				'replacementTotal'    => (int) $totalReplacement,
				'replacementInTable'  => count( $this->dryRunReport ),
				'dryRunReport'        => $this->dryRunReport,
				'user_data'           => $user_query,
				'custom_data'         => $custom_data,
			);
		}

		// Live run that changed something — regenerate builder/object caches so
		// the front-end reflects the new content immediately.
		if ( ! isset( $this->settings['cs_db_string_replace']['dry_run'] ) && $i > 0 ) {
			$this->regenerate_caches();

			do_action(
				'bfrp_after_db_replace',
				array(
					'replaced' => $i,
					'where'    => $whereToReplace,
					'builders' => $this->selected_builders,
					'post_ids' => array_keys( $this->touched_post_ids ),
					'settings' => $this->settings,
				)
			);
		}

		$dryRunReport = apply_filters( 'bfrp_dryrun_final_report', $dryRunReport );

		return wp_send_json(
			\array_merge_recursive(
				array(
					'status'        => true,
					'title'         => __( 'Success!', 'real-time-auto-find-and-replace' ),
					/* translators: %1$s: content type (text/URLs); %2$d: number of replacements */
					'text'          => sprintf( __( 'Replacement completed. Total %1$s replaced: %2$d', 'real-time-auto-find-and-replace' ), $replaceType, $i ),
					'nothing_found' => __( 'Sorry, nothing was found.', 'real-time-auto-find-and-replace' ),
				),
				$dryRunReport
			)
		);
	}

	/**
	 * Repoint one URL/string to another across posts, postmeta and options as a
	 * live (non-dry) operation, returning the number of changed cells.
	 *
	 * Used by the media replacer to update links after a file rename. Reuses the
	 * serialize-safe engine and, by default, also matches JSON-escaped /
	 * percent-encoded variants (so Elementor & encoded URLs are caught), then
	 * regenerates builder/object caches.
	 *
	 * @param string $find
	 * @param string $replace
	 * @param bool   $encoded
	 * @return int
	 */
	public function replace_links( $find, $replace, $encoded = true ) {
		if ( ! is_string( $find ) || '' === $find || $find === $replace ) {
			return 0;
		}

		// Force a live run regardless of how this instance was constructed.
		if ( isset( $this->settings['cs_db_string_replace']['dry_run'] ) ) {
			unset( $this->settings['cs_db_string_replace']['dry_run'] );
		}
		$this->encoded_urls      = (bool) $encoded;
		$this->selected_builders = array();
		$this->touched_post_ids  = array();

		$i  = 0;
		$i += (int) $this->tbl_post( $find, $replace );
		$i += (int) $this->tbl_postmeta( $find, $replace );
		$i += (int) $this->tbl_options( $find, $replace );

		$this->regenerate_caches();

		return $i;
	}

	/**
	 * Replace in post table
	 *
	 * @return void
	 */
	private function tbl_post( $find, $replace, $is_page_post_filter = array(), $post_type = '' ) {
		global $wpdb;
		$i = 0;

		// post_content can hold Divi / WPBakery / Gutenberg markup; broaden the
		// match to escaped/encoded (and, in URL mode, scheme/www) forms.
		$variants = $this->variants_for( $find, $replace );

		$sql = '';
		// check for page / post options filters
		if ( ! empty( $is_page_post_filter ) ) {

			$filterParts   = array();
			$placeholders  = array( $post_type );
			$selectColumns = array();

			foreach ( array( 'post_title', 'post_content', 'post_excerpt' ) as $column ) {
				if ( in_array( $column, $is_page_post_filter ) ) {
					$filterParts[]   = $this->like_clause_for_variants( $column, $variants, $placeholders );
					$selectColumns[] = $column;
				}
			}

			// Guard: no recognised columns means nothing to match.
			$filterQuery = empty( $filterParts ) ? '0=1' : implode( ' OR ', $filterParts );

			// PHP 7.0 or higher
			$selectColumnsString = empty( $selectColumns ) ? 'ID' : implode( ', ', $selectColumns );
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$sql                 = $wpdb->prepare( "SELECT ID, $selectColumnsString FROM {$wpdb->posts} WHERE post_type = %s AND ( $filterQuery )", $placeholders );

			// pre_print( $sql );

		} else {
			$placeholders = array();
			$title_clause   = $this->like_clause_for_variants( 'post_title', $variants, $placeholders );
			$content_clause = $this->like_clause_for_variants( 'post_content', $variants, $placeholders );
			$excerpt_clause = $this->like_clause_for_variants( 'post_excerpt', $variants, $placeholders );

			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$sql = $wpdb->prepare(
				"select * from {$wpdb->posts} where {$title_clause} or {$content_clause} or {$excerpt_clause}",
				$placeholders
			);
		}

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$get_data = $wpdb->get_results( $sql );
		if ( $get_data ) {

			foreach ( $get_data as $item ) {

				// replace in post_title
				if ( isset( $item->post_title ) ) {
					$is_replaced = $this->bfrReplaceVariants(
						$variants,
						$item->post_title,
						$wpdb->base_prefix . 'posts',
						$item->ID, // row id
						'ID', // primary key
						'post_title',
						array( 'ID' => $item->ID )
					);

					if ( true === $is_replaced ) {
						++$i;
						$this->mark_touched_post( $item->ID );
					}
				}

				// replace in post_content
				if ( isset( $item->post_content ) ) {
					$is_replaced = $this->bfrReplaceVariants(
						$variants,
						$item->post_content,
						$wpdb->base_prefix . 'posts',
						$item->ID,
						'ID', // primary key
						'post_content',
						array( 'ID' => $item->ID )
					);

					if ( true === $is_replaced ) {
						++$i;
						$this->mark_touched_post( $item->ID );
					}
				}

				// replace in post_excerpt
				if ( isset( $item->post_excerpt ) ) {
					$is_replaced = $this->bfrReplaceVariants(
						$variants,
						$item->post_excerpt,
						$wpdb->base_prefix . 'posts',
						$item->ID,
						'ID', // primary key
						'post_excerpt',
						array( 'ID' => $item->ID )
					);

					if ( true === $is_replaced ) {
						++$i;
						$this->mark_touched_post( $item->ID );
					}
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
		$i = 0;

		// Variants used for row SELECTION. Pro broadens these to escaped/encoded
		// forms (via the bfrp_find_replace_variants filter) when the encoded option
		// is on OR a page-builder that stores escaped JSON (e.g. Elementor) is
		// selected — passed through as the 'force_encoded' hint.
		$select_variants = $this->variants_for( $find, $replace, array( 'force_encoded' => $this->builders_need_encoded() ) );

		$placeholders = array();
		$key_clause   = $this->like_clause_for_variants( 'meta_key', $select_variants, $placeholders );
		$val_clause   = $this->like_clause_for_variants( 'meta_value', $select_variants, $placeholders );

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$get_data = $wpdb->get_results(
			$wpdb->prepare(
				"select * from {$wpdb->postmeta} where {$key_clause} or {$val_clause}",
				$placeholders
			)
		);

		if ( $get_data ) {
			foreach ( $get_data as $item ) {

				// Per-row variants: a registered builder JSON key (e.g. _elementor_data)
				// auto-enables escaped matching even when the global option is off.
				$row_variants = $this->postmeta_variants( $find, $replace, $item->meta_key );

				$is_replaced = $this->bfrReplaceVariants(
					$row_variants,
					$item->meta_value,
					$wpdb->base_prefix . 'postmeta',
					$item->meta_id,
					'meta_id', // primary key
					'meta_value',
					array( 'meta_id' => $item->meta_id )
				);

				if ( true === $is_replaced ) {
					++$i;
					$this->mark_touched_post( $item->post_id );
				}

				$is_replaced = $this->bfrReplaceVariants(
					$row_variants,
					$item->meta_key,
					$wpdb->base_prefix . 'postmeta',
					$item->meta_id,
					'meta_id', // primary key
					'meta_key',
					array( 'meta_id' => $item->meta_id )
				);

				if ( true === $is_replaced ) {
					++$i;
					$this->mark_touched_post( $item->post_id );
				}
			}
		}
		return $i;
	}

	/**
	 * Find/replace variants for a single postmeta row. Escaped/encoded variants
	 * are added when the global option is on, or when the row's meta_key belongs
	 * to a selected builder that stores escaped JSON (Elementor).
	 *
	 * @param string $find
	 * @param string $replace
	 * @param string $meta_key
	 * @return array
	 */
	private function postmeta_variants( $find, $replace, $meta_key ) {
		return $this->variants_for( $find, $replace, array( 'force_encoded' => $this->meta_key_needs_encoded( $meta_key ) ) );
	}

	/** True if any selected builder stores escaped-JSON (slash-escaped) content. */
	private function builders_need_encoded() {
		return ! empty( $this->selected_builders )
			&& \RealTimeAutoFindReplace\lib\BuilderRegistry::any_json( $this->selected_builders );
	}

	/** True if $meta_key is a JSON-strategy key for a selected builder. */
	private function meta_key_needs_encoded( $meta_key ) {
		if ( empty( $this->selected_builders ) ) {
			return false;
		}
		return 'json' === \RealTimeAutoFindReplace\lib\BuilderRegistry::postmeta_strategy( $meta_key, $this->selected_builders );
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
		$i = 0;

		$variants     = $this->variants_for( $find, $replace );
		$placeholders = array();
		$val_clause   = $this->like_clause_for_variants( 'option_value', $variants, $placeholders );
		$name_clause  = $this->like_clause_for_variants( 'option_name', $variants, $placeholders );

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$get_data = $wpdb->get_results(
			$wpdb->prepare(
				"select * from {$wpdb->options} where {$val_clause} or {$name_clause}",
				$placeholders
			)
		);
		if ( $get_data ) {
			foreach ( $get_data as $item ) {

				$is_replaced = $this->bfrReplaceVariants(
					$variants,
					$item->option_value,
					$wpdb->base_prefix . 'options',
					$item->option_id,
					'option_id', // primary key
					'option_value',
					array( 'option_id' => $item->option_id )
				);

				if ( true === $is_replaced ) {
					++$i;
				}

				$is_replaced = $this->bfrReplaceVariants(
					$variants,
					$item->option_name,
					$wpdb->base_prefix . 'options',
					$item->option_id,
					'option_id', // primary key
					'option_name',
					array( 'option_id' => $item->option_id )
				);

				if ( true === $is_replaced ) {
					++$i;
				}
			}
		}
		return $i;
	}

	/**
	 * Runs a whitelisted core-table handler if that table was selected, then
	 * removes it from $tables so it isn't also passed to the (pro) custom
	 * tables filter. Mirrors the inline pattern used above for posts/postmeta/options.
	 *
	 * @param array    $tables           Selected table names, checked by reference.
	 * @param string   $table            Fully-prefixed table name to match against $tables.
	 * @param callable $handler          [$this, 'tbl_x'] — called as $handler($find, $replace).
	 * @param string   $find
	 * @param string   $replace
	 * @param bool     $large_table_skip Pro "large table" flag; core tables sit this out same as posts/postmeta/options.
	 * @return int
	 */
	private function replace_core_table( &$tables, $table, $handler, $find, $replace, $large_table_skip ) {
		if ( empty( $tables ) || $large_table_skip || ! in_array( $table, $tables, true ) ) {
			return 0;
		}

		$count = (int) call_user_func( $handler, $find, $replace );

		$key = array_search( $table, $tables, true );
		if ( false !== $key ) {
			unset( $tables[ $key ] );
		}

		return $count;
	}

	/**
	 * Replace in comments table (author name/email/url + content). No IP or
	 * user-agent columns — those aren't meaningful find/replace targets.
	 *
	 * @param string $find
	 * @param string $replace
	 * @return int
	 */
	private function tbl_comments( $find, $replace ) {
		global $wpdb;
		$i = 0;

		$columns      = array( 'comment_author', 'comment_author_email', 'comment_author_url', 'comment_content' );
		$variants     = $this->variants_for( $find, $replace );
		$placeholders = array();
		$clauses      = array();
		foreach ( $columns as $column ) {
			$clauses[] = $this->like_clause_for_variants( $column, $variants, $placeholders );
		}

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$get_data = $wpdb->get_results(
			$wpdb->prepare(
				"select * from {$wpdb->comments} where " . implode( ' or ', $clauses ),
				$placeholders
			)
		);

		if ( $get_data ) {
			foreach ( $get_data as $item ) {
				foreach ( $columns as $column ) {
					if ( ! isset( $item->$column ) ) {
						continue;
					}
					$is_replaced = $this->bfrReplaceVariants(
						$variants,
						$item->$column,
						$wpdb->base_prefix . 'comments',
						$item->comment_ID,
						'comment_ID',
						$column,
						array( 'comment_ID' => $item->comment_ID )
					);
					if ( true === $is_replaced ) {
						++$i;
					}
				}
			}
		}
		return $i;
	}

	/**
	 * Replace in comment meta table.
	 *
	 * @param string $find
	 * @param string $replace
	 * @return int
	 */
	private function tbl_commentmeta( $find, $replace ) {
		global $wpdb;
		$i = 0;

		$variants     = $this->variants_for( $find, $replace );
		$placeholders = array();
		$key_clause   = $this->like_clause_for_variants( 'meta_key', $variants, $placeholders );
		$val_clause   = $this->like_clause_for_variants( 'meta_value', $variants, $placeholders );

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$get_data = $wpdb->get_results(
			$wpdb->prepare(
				"select * from {$wpdb->commentmeta} where {$key_clause} or {$val_clause}",
				$placeholders
			)
		);

		if ( $get_data ) {
			foreach ( $get_data as $item ) {
				$is_replaced = $this->bfrReplaceVariants(
					$variants,
					$item->meta_value,
					$wpdb->base_prefix . 'commentmeta',
					$item->meta_id,
					'meta_id',
					'meta_value',
					array( 'meta_id' => $item->meta_id )
				);
				if ( true === $is_replaced ) {
					++$i;
				}

				$is_replaced = $this->bfrReplaceVariants(
					$variants,
					$item->meta_key,
					$wpdb->base_prefix . 'commentmeta',
					$item->meta_id,
					'meta_id',
					'meta_key',
					array( 'meta_id' => $item->meta_id )
				);
				if ( true === $is_replaced ) {
					++$i;
				}
			}
		}
		return $i;
	}

	/**
	 * Replace in terms table (name + slug). Note: replacing slug changes term
	 * archive URLs, same tradeoff the plugin already accepts for post_name/guid.
	 *
	 * @param string $find
	 * @param string $replace
	 * @return int
	 */
	private function tbl_terms( $find, $replace ) {
		global $wpdb;
		$i = 0;

		$variants     = $this->variants_for( $find, $replace );
		$placeholders = array();
		$name_clause  = $this->like_clause_for_variants( 'name', $variants, $placeholders );
		$slug_clause  = $this->like_clause_for_variants( 'slug', $variants, $placeholders );

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$get_data = $wpdb->get_results(
			$wpdb->prepare(
				"select * from {$wpdb->terms} where {$name_clause} or {$slug_clause}",
				$placeholders
			)
		);

		if ( $get_data ) {
			foreach ( $get_data as $item ) {
				foreach ( array( 'name', 'slug' ) as $column ) {
					$is_replaced = $this->bfrReplaceVariants(
						$variants,
						$item->$column,
						$wpdb->base_prefix . 'terms',
						$item->term_id,
						'term_id',
						$column,
						array( 'term_id' => $item->term_id )
					);
					if ( true === $is_replaced ) {
						++$i;
					}
				}
			}
		}
		return $i;
	}

	/**
	 * Replace in term meta table.
	 *
	 * @param string $find
	 * @param string $replace
	 * @return int
	 */
	private function tbl_termmeta( $find, $replace ) {
		global $wpdb;
		$i = 0;

		$variants     = $this->variants_for( $find, $replace );
		$placeholders = array();
		$key_clause   = $this->like_clause_for_variants( 'meta_key', $variants, $placeholders );
		$val_clause   = $this->like_clause_for_variants( 'meta_value', $variants, $placeholders );

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$get_data = $wpdb->get_results(
			$wpdb->prepare(
				"select * from {$wpdb->termmeta} where {$key_clause} or {$val_clause}",
				$placeholders
			)
		);

		if ( $get_data ) {
			foreach ( $get_data as $item ) {
				$is_replaced = $this->bfrReplaceVariants(
					$variants,
					$item->meta_value,
					$wpdb->base_prefix . 'termmeta',
					$item->meta_id,
					'meta_id',
					'meta_value',
					array( 'meta_id' => $item->meta_id )
				);
				if ( true === $is_replaced ) {
					++$i;
				}

				$is_replaced = $this->bfrReplaceVariants(
					$variants,
					$item->meta_key,
					$wpdb->base_prefix . 'termmeta',
					$item->meta_id,
					'meta_id',
					'meta_key',
					array( 'meta_id' => $item->meta_id )
				);
				if ( true === $is_replaced ) {
					++$i;
				}
			}
		}
		return $i;
	}

	/**
	 * Replace in term_taxonomy table. Only `description` is touched — taxonomy,
	 * parent and count are structural and must never be find/replace targets.
	 *
	 * @param string $find
	 * @param string $replace
	 * @return int
	 */
	private function tbl_term_taxonomy( $find, $replace ) {
		global $wpdb;
		$i = 0;

		$variants     = $this->variants_for( $find, $replace );
		$placeholders = array();
		$desc_clause  = $this->like_clause_for_variants( 'description', $variants, $placeholders );

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$get_data = $wpdb->get_results(
			$wpdb->prepare(
				"select * from {$wpdb->term_taxonomy} where {$desc_clause}",
				$placeholders
			)
		);

		if ( $get_data ) {
			foreach ( $get_data as $item ) {
				$is_replaced = $this->bfrReplaceVariants(
					$variants,
					$item->description,
					$wpdb->base_prefix . 'term_taxonomy',
					$item->term_taxonomy_id,
					'term_taxonomy_id',
					'description',
					array( 'term_taxonomy_id' => $item->term_taxonomy_id )
				);
				if ( true === $is_replaced ) {
					++$i;
				}
			}
		}
		return $i;
	}

	/**
	 * Replace in links table (Link Manager / blogroll — legacy but still present
	 * on most installs). Only the content-bearing columns are touched.
	 *
	 * @param string $find
	 * @param string $replace
	 * @return int
	 */
	private function tbl_links( $find, $replace ) {
		global $wpdb;
		$i = 0;

		$columns      = array( 'link_url', 'link_name', 'link_description', 'link_notes' );
		$variants     = $this->variants_for( $find, $replace );
		$placeholders = array();
		$clauses      = array();
		foreach ( $columns as $column ) {
			$clauses[] = $this->like_clause_for_variants( $column, $variants, $placeholders );
		}

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$get_data = $wpdb->get_results(
			$wpdb->prepare(
				"select * from {$wpdb->links} where " . implode( ' or ', $clauses ),
				$placeholders
			)
		);

		if ( $get_data ) {
			foreach ( $get_data as $item ) {
				foreach ( $columns as $column ) {
					if ( ! isset( $item->$column ) ) {
						continue;
					}
					$is_replaced = $this->bfrReplaceVariants(
						$variants,
						$item->$column,
						$wpdb->base_prefix . 'links',
						$item->link_id,
						'link_id',
						$column,
						array( 'link_id' => $item->link_id )
					);
					if ( true === $is_replaced ) {
						++$i;
					}
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

		// Global, URL-format-aware replace across every place a URL can live —
		// post content/excerpt/title, postmeta (Elementor/ACF/etc.) and options
		// (siteurl, home, widgets, theme_mods). A URL string is identical
		// wherever stored, so this is what makes "All URLs" actually replace
		// everywhere. The serialize-safe engine + variants_for() handle it.
		$r += $this->tbl_post( $find, $replace );
		$r += $this->tbl_postmeta( $find, $replace );
		$r += $this->tbl_options( $find, $replace );

		// Permalink columns (guid + post_name) for the selected post types.
		$r += $this->urlFromPostTables( $find, $replace, $urlTypes );

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

		$variants     = $this->variants_for( $find, $replace );
		$placeholders = array();
		$guid_clause  = $this->like_clause_for_variants( 'guid', $variants, $placeholders );
		$name_clause  = $this->like_clause_for_variants( 'post_name', $variants, $placeholders );

		$con      = \implode( ' OR ', $urlTypes );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$get_data = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->posts} WHERE ( {$guid_clause} OR {$name_clause} ) AND ( {$con} ) ",
				$placeholders
			)
		);

		if ( $get_data ) {
			foreach ( $get_data as $item ) {

				// replace in guid
				$is_replaced_guid = $this->bfrReplaceVariants(
					$variants,
					$item->guid,
					$wpdb->base_prefix . 'posts',
					$item->ID,
					'ID',
					'guid',
					array( 'ID' => $item->ID )
				);

				if ( true === $is_replaced_guid ) {
					++$i;
					$this->mark_touched_post( $item->ID );
				}

				// replace in post name
				$is_replaced_name = $this->bfrReplaceVariants(
					$variants,
					$item->post_name,
					$wpdb->base_prefix . 'posts',
					$item->ID,
					'ID',
					'post_name',
					array( 'ID' => $item->ID )
				);

				if ( true === $is_replaced_name ) {
					++$i;
					$this->mark_touched_post( $item->ID );
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
		return $this->bfrReplaceVariants(
			array( array( 'find' => $find, 'replace' => $replace ) ),
			$old_value,
			$tbl,
			$row_id,
			$primary_col,
			$update_col,
			$update_con
		);
	}

	/**
	 * Apply one or more find/replace variant pairs to a single column value,
	 * then perform exactly ONE write (or one dry-run report row). Threading the
	 * value through each variant means escaped/encoded variants compound on the
	 * same value without double-writing or inflating the report.
	 *
	 * With a single pair this is byte-for-byte equivalent to the old bfrReplace.
	 *
	 * @param array  $variants    list of array( 'find' => ..., 'replace' => ... )
	 * @param mixed  $old_value
	 * @param string $tbl
	 * @param mixed  $row_id
	 * @param string $primary_col
	 * @param string $update_col
	 * @param array  $update_con
	 * @return bool whether the value changed
	 */
	public function bfrReplaceVariants( $variants, $old_value, $tbl, $row_id, $primary_col, $update_col, $update_con ) {

		if ( empty( $old_value ) || empty( $variants ) ) {
			return false;
		}

		$original     = $this->format_old_value( $old_value );
		$current       = $original;
		$totalFind    = 0;
		$totalReplace = 0;
		$display      = null;
		$isCaseInsensitive = false;

		foreach ( $variants as $variant ) {
			if ( ! isset( $variant['find'] ) || '' === $variant['find'] ) {
				continue;
			}
			$res = $this->compute_replacement( $variant['find'], $variant['replace'], $current );
			if ( $res['changed'] ) {
				$current            = $res['new_value'];
				$totalFind         += $res['findCount'];
				$totalReplace      += $res['replaceCount'];
				$isCaseInsensitive  = $res['ici'];
				if ( null === $display ) {
					$display = $res['display']; // first matching variant drives the visual preview
				}
			}
		}

		if ( $current === $original || $totalFind < 1 ) {
			return false;
		}

		$primary = $variants[0];
		global $wpdb;

		// check for dry run
		if ( ! isset( $this->settings['cs_db_string_replace']['dry_run'] ) ) {

			$wpdb->update( $tbl, array( $update_col => $current ), $update_con );

			do_action(
				'bfar_save_item_history',
				\json_encode(
					array(
						'tbl'     => $tbl,
						'rid'     => $row_id,
						'pCol'    => $primary_col, // primary col
						'col'     => $update_col,
						'find'    => $primary['find'],
						'replace' => $primary['replace'],
						'ici'     => $isCaseInsensitive,
						'old_val' => $original,
						'new_val' => $current,
					)
				)
			);

		} elseif ( $this->settings['cs_db_string_replace']['dry_run'] == 'on' ) {

			$reportRow = array(
				'bfrp_' . $row_id . '_' . $update_col => array(
					'tbl'          => $tbl,
					'rid'          => $row_id,
					'pCol'         => $primary_col, // primary col
					'col'          => $update_col,
					'find'         => $primary['find'],
					'replace'      => $primary['replace'],
					'ici'          => $isCaseInsensitive,
					'old_val'      => $original,
					'new_val'      => $current,
					'dis_find'     => isset( $display['find'] ) ? $display['find'] : '',
					'dis_replace'  => isset( $display['replace'] ) ? $display['replace'] : '',
					'findCount'    => $totalFind,
					'replaceCount' => $totalReplace,
				),
			);

			if ( isset( $this->dryRunReport[ $tbl ] ) ) {
				$this->dryRunReport[ $tbl ] = array_merge_recursive( $this->dryRunReport[ $tbl ], $reportRow );
			} else {
				$this->dryRunReport[ $tbl ] = $reportRow;
			}
		}

		return true;
	}

	/**
	 * Replace one find/replace pair in a value WITHOUT touching the DB.
	 *
	 * @return array{changed:bool,new_value:mixed,display:array,findCount:int,replaceCount:int,ici:bool}
	 */
	private function compute_replacement( $find, $replace, $old_value ) {

		$isCaseInsensitive = false;
		$formattedFind     = '';

		if ( isset( $this->settings['cs_db_string_replace']['whole_word'] ) ||
			isset( $this->settings['cs_db_string_replace']['unicode_modifier'] )
		) {

			if ( ! isset( $this->settings['cs_db_string_replace']['case_insensitive'] ) ) {
				$formattedFind = $this->formatFindWholeWord( $find );
			} else {
				$formattedFind     = $this->formatFindWholeWord( $find, true );
				$isCaseInsensitive = true;
			}

			$this->report_holder['find']                 = $formattedFind;
			$this->report_holder['replace']              = $replace;
			$this->report_holder['str']                  = $old_value;
			$this->report_holder['is_preg']              = 'pregReplace';
			$this->report_holder['is_regular']           = false;
			$this->report_holder['is_case_in_sensitive'] = false;

		} else {

			if ( isset( $this->settings['cs_db_string_replace']['case_insensitive'] ) ) {
				$isCaseInsensitive = true;
			}

			$this->report_holder['find']                 = $find;
			$this->report_holder['replace']              = $replace;
			$this->report_holder['str']                  = $old_value;
			$this->report_holder['is_case_in_sensitive'] = $isCaseInsensitive;
			$this->report_holder['is_regular']           = 'regular';
			$this->report_holder['is_preg']              = false;
		}

		$new_string = $this->bfar_replace_formatter( $this->report_holder );

		$displayReplace = $this->highlightDisplayFindReplace(
			array(
				'formattedFind'     => $formattedFind,
				'find'              => $find,
				'replace'           => $replace,
				'old_value'         => $old_value,
				'new_value'         => $new_string['str'],
				'isCaseInsensitive' => $isCaseInsensitive,
			)
		);

		$findCount = isset( $displayReplace['findCount'] ) ? (int) $displayReplace['findCount'] : 0;
		$changed   = ( $new_string['str'] != $old_value && $findCount >= 1 );

		return array(
			'changed'      => $changed,
			'new_value'    => $new_string['cleanStr'],
			'display'      => $displayReplace,
			'findCount'    => $findCount,
			'replaceCount' => isset( $displayReplace['replaceCount'] ) ? (int) $displayReplace['replaceCount'] : 0,
			'ici'          => $isCaseInsensitive,
		);
	}

	/**
	 * Find/replace pairs to apply for one column. Always starts with the raw
	 * pair. The escaped/encoded (`\/`, `%2F`) and URL-format (http/https/`//` +
	 * www) variants are a PRO feature — the pro add-on appends them via the
	 * `bfrp_find_replace_variants` filter, using the flags below. Without pro,
	 * only the raw pair is used (so escaped/encoded + URL-format matching is
	 * pro-gated, mirroring how Unicode's behaviour lives in pro).
	 *
	 * The result feeds the SELECT + replace machinery and runs through
	 * compute_replacement(), so the Case / Whole-Word / Unicode filters still
	 * apply to every variant. The raw pair stays first so it drives the dry-run
	 * display and the saved history.
	 *
	 * @param string $find
	 * @param string $replace
	 * @param array  $context per-pass hints (e.g. 'force_encoded' for builder JSON keys)
	 * @return array list of array( 'find' => ..., 'replace' => ... ); raw pair first
	 */
	private function variants_for( $find, $replace, $context = array() ) {
		$variants = array( array( 'find' => $find, 'replace' => $replace ) );

		$flags = array(
			'encoded_urls'  => $this->encoded_urls,
			'url_formats'   => $this->url_formats,
			'url_mode'      => $this->url_mode,
			'force_encoded' => ! empty( $context['force_encoded'] ),
		);

		return apply_filters( 'bfrp_find_replace_variants', $variants, $find, $replace, $flags );
	}

	/**
	 * Build a "( col LIKE %s OR col LIKE %s ... )" fragment for every variant
	 * find, pushing the matching esc_like placeholders onto $placeholders.
	 *
	 * @param string $column
	 * @param array  $variants
	 * @param array  $placeholders passed by reference
	 * @return string
	 */
	private function like_clause_for_variants( $column, $variants, &$placeholders ) {
		global $wpdb;

		$parts = array();
		foreach ( $variants as $variant ) {
			if ( ! isset( $variant['find'] ) || '' === $variant['find'] ) {
				continue;
			}
			$parts[]        = "{$column} LIKE %s";
			$placeholders[] = '%' . $wpdb->esc_like( $variant['find'] ) . '%';
		}

		return empty( $parts ) ? '0=1' : '( ' . implode( ' OR ', $parts ) . ' )';
	}

	/** Records a post id touched by a live (non-dry) replacement for cache regeneration. */
	private function mark_touched_post( $post_id ) {
		$post_id = (int) $post_id;
		if ( $post_id > 0 && ! isset( $this->settings['cs_db_string_replace']['dry_run'] ) ) {
			$this->touched_post_ids[ $post_id ] = true;
		}
	}

	/**
	 * Clear caches that direct $wpdb->update() writes leave stale: per-post
	 * object cache, page-builder rendered caches, and (opt-out) the object cache.
	 * Nothing here is destructive — every cache is regenerated on next view.
	 */
	private function regenerate_caches() {
		$post_ids = array_keys( $this->touched_post_ids );

		if ( function_exists( 'clean_post_cache' ) ) {
			foreach ( $post_ids as $pid ) {
				clean_post_cache( $pid );
			}
		}

		$this->clear_elementor_cache( $post_ids );

		// Beaver Builder asset cache.
		if ( class_exists( '\FLBuilderModel' ) && method_exists( '\FLBuilderModel', 'delete_asset_cache_for_all_posts' ) ) {
			\FLBuilderModel::delete_asset_cache_for_all_posts();
		}

		// Flush the object cache (direct SQL writes bypass it). Opt-out via filter
		// for sites on shared persistent caches where a full flush is undesirable.
		if ( apply_filters( 'bfrp_flush_object_cache', true ) && function_exists( 'wp_cache_flush' ) ) {
			wp_cache_flush();
		}
	}

	/** Clears Elementor's generated CSS so it rebuilds with the new content. */
	private function clear_elementor_cache( $post_ids ) {
		if ( class_exists( '\Elementor\Plugin' ) ) {
			$elementor = \Elementor\Plugin::$instance;
			if ( isset( $elementor->files_manager ) && method_exists( $elementor->files_manager, 'clear_cache' ) ) {
				$elementor->files_manager->clear_cache();
				return;
			}
		}

		// Fallback: drop per-post generated CSS meta; Elementor regenerates it.
		if ( function_exists( 'delete_post_meta' ) ) {
			foreach ( $post_ids as $pid ) {
				delete_post_meta( $pid, '_elementor_css' );
			}
		}
	}

	/**
	 * Highlight find & replace text
	 *
	 * @param [type] $args
	 * @return void
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
				$find = $this->bfarApplySpcialCharsFlag( $args['find'] );
				$find = '#(' . \preg_quote( $find ) . ')#';
				if ( true === $args['isCaseInsensitive'] ) {
					$find .= 'i';
				}
			}

			$old_value     = $this->bfarApplySpcialCharsFlag( $args['old_value'] );
			$findNewDisStr = \preg_replace( \esc_html( $find ), "<span class='find'>$1</span>", \esc_html( $old_value ), -1, $countFind );
			$findNewDisStr = $this->bfarRemoveSpcialCharsFlag( $findNewDisStr );
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
		$replaceNewDisStr = $this->bfarRemoveSpcialCharsFlag( $replaceNewDisStr );

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
		$find = \preg_quote( $find );
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
		$replace = \stripslashes( $replace );
		$replace = $this->bfarApplySpcialCharsFlag( $replace );
		return $replace;
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

	/**
	 * Replace formatter
	 *
	 * @param [type] $args
	 * @return void
	 */
	public function bfar_replace_formatter( $args ) {

		if ( is_string( $args['str'] ) && false === $args['has_escaped_serialized'] &&
				SerializedReplacer::is_php_serialized( $args['str'] ) ) {

			$temp = array();

			// Properly unserialize — NOT json_decode (PHP-serialized data is not
			// JSON). safe_unserialize restricts classes to stdClass and refuses
			// anything it can't decode, so we never raw-replace a serialized blob
			// (which would break the s:N: length prefixes and corrupt the value).
			list( $ok, $decoded ) = SerializedReplacer::safe_unserialize( $args['str'] );

			if ( $ok ) {
				$temp['is_serialized']     = true;
				$temp['str']               = $decoded;
				$temp['is_serialize_data'] = true;

				$args = $this->bfar_replace_formatter( \array_merge( $args, $temp ) );
				unset( $temp['str'] );
				$args = \array_merge( $args, $temp );
			} else {
				// Undecodable / unsafe blob: leave the value exactly as-is.
				$args['cleanStr'] = $this->bfarRemoveSpcialCharsFlag( $args['str'] );
			}

		} elseif ( is_array( $args['str'] ) ) {

			$flag      = array();
			$flagFresh = array();

			foreach ( $args['str'] as $key => $value ) {

				// check empty
				if ( $args['replace'] == '~&nbsp;~' && ! empty( $key ) && $key == $args['find'] && ! is_array( $key ) && ! is_object( $key ) ) {
					unset( $flag[ $key ], $flagFresh[ $key ] );
				} else {
					$rawKey   = $key;
					$cleanKey = $key;

					if ( is_string( $key ) && ! empty( $key ) ) {

						$args['str']           = $key;
						$args['is_serialized'] = false;

						$nKey    = $this->bfar_replace_formatter( $args );
						$nRawKey = is_array( $nKey ) && isset( $nKey['str'] ) ? $nKey['str'] : $nKey;
						if ( ! empty( $nKey ) && $nRawKey != $key ) {
							unset( $flag[ $key ] );
							$rawKey   = $nRawKey;
							$cleanKey = is_array( $nKey ) && isset( $nKey['cleanStr'] ) ? $nKey['cleanStr'] : $nKey;
						}
					}

					$args['str']            = $value;
					$args['is_serialized']  = false;
					$arrNes                 = $this->bfar_replace_formatter( $args );
					$flag[ $rawKey ]        = is_array( $arrNes ) && isset( $arrNes['str'] ) ? $arrNes['str'] : $arrNes;
					$flagFresh[ $cleanKey ] = is_array( $arrNes ) && isset( $arrNes['cleanStr'] ) ? $arrNes['cleanStr'] : $arrNes;

				}
			}

			$args['str']      = $flag;
			$args['cleanStr'] = $flagFresh;
			unset( $flag, $flagFresh );

		} elseif ( \is_object( $args['str'] ) ) {

			$flag     = $args['str'];
			$cleanStr = isset( $args['cleanStr'] ) && ! empty( $args['cleanStr'] ) ? $args['cleanStr'] : new \stdClass();

			$objVars = \get_object_vars( $args['str'] );
			foreach ( $objVars as $key => $value ) {

				if ( $args['replace'] == '~&nbsp;~' && ! empty( $key ) && $key == $args['find'] && ! is_array( $key ) && ! is_object( $key ) ) {
					unset( $flag->$key, $cleanStr->$key );
				} else {

					$rawKey   = $key;
					$cleanKey = $key;

					if ( is_string( $key ) && ! empty( $key ) ) {
						$args['str']           = $key;
						$args['is_serialized'] = false;
						$nObjKey               = $this->bfar_replace_formatter( $args );
						$newRawKey             = is_array( $nObjKey ) && isset( $nObjKey['str'] ) ? $nObjKey['str'] : $nObjKey;

						if ( ! empty( $nObjKey ) && $newRawKey != $key ) { // replace old key with new key
							unset( $flag->$key, $cleanStr->$key );
							$rawKey   = $newRawKey;
							$cleanKey = is_array( $nObjKey ) && isset( $nObjKey['cleanStr'] ) ? $nObjKey['cleanStr'] : $nObjKey;
						}
					}

					$cleanKey              = trim( $cleanKey );
					$args['str']           = $value;
					$args['is_serialized'] = false;
					$objNes                = $this->bfar_replace_formatter( $args );
					$flag->$rawKey         = is_array( $objNes ) && isset( $objNes['str'] ) ? $objNes['str'] : $objNes;
					$cleanStr->$cleanKey   = is_array( $objNes ) && isset( $objNes['cleanStr'] ) ? $objNes['cleanStr'] : $objNes;

				}
			}

			$args['str']      = $flag;
			$args['cleanStr'] = $cleanStr;
			unset( $flag, $cleanStr );

		} elseif ( is_string( $args['str'] ) ) {

			$args['str']      = Util::bfar_replacer( $args['find'], $args['replace'], $args['str'], $args['is_preg'], $args['is_regular'], $args['is_case_in_sensitive'] );
			$args['cleanStr'] = $this->bfarRemoveSpcialCharsFlag( $args['str'] );

		} elseif ( \is_numeric( $args['str'] ) ) {

			// Numeric scalar (e.g. inside a serialized array). Replace on its
			// string form, but if nothing changed keep the original int/float
			// type so re-serialization stays byte-faithful (i:5 stays i:5).
			$original = $args['str'];
			$replaced = Util::bfar_replacer( $args['find'], $args['replace'], (string) $original, $args['is_preg'], $args['is_regular'], $args['is_case_in_sensitive'] );

			if ( (string) $original === (string) $replaced ) {
				$args['str']      = $original;
				$args['cleanStr'] = $original;
			} else {
				$args['str']      = $replaced;
				$args['cleanStr'] = $this->bfarRemoveSpcialCharsFlag( $replaced );
			}

		} else {

			// bool / null / anything not string-replaceable — leave untouched.
			$args['cleanStr'] = $args['str'];

		}

		if ( $args['is_serialized'] && ! is_serialized( $args['str'] ) ) {
			// Re-serialize with serialize() (NOT json_encode) so each inner
			// string's s:N: length prefix is recomputed from the replaced value.
			// Using json_encode here was the bug that corrupted serialized data
			// on any length-changing replacement.
			$args['str']      = \serialize( $args['str'] );
			$args['cleanStr'] = \serialize( $args['cleanStr'] );
		}

		return $args;
	}

	/**
	 * apply filter
	 *
	 * @return void
	 */
	public function bfarApplySpcialCharsFlag( $str ) {
		if ( empty( $str ) ) {
			$str = '~&nbsp;~';
		}

		if ( empty( $this->bfar_special_chars ) ) {
			$this->bfar_special_chars = Util::bfar_special_chars();
		}

		return \str_replace(
			$this->bfar_special_chars['chars'],
			$this->bfar_special_chars['flags'],
			$str
		);
	}

	/**
	 * Remove replace flag
	 *
	 * @param [type] $replace
	 * @return void
	 */
	public function bfarRemoveSpcialCharsFlag( $str ) {

		if ( empty( $this->bfar_special_chars ) ) {
			$this->bfar_special_chars = Util::bfar_special_chars();
		}

		return \str_replace(
			$this->bfar_special_chars['flags'],
			$this->bfar_special_chars['chars'],
			$str
		);
	}
}
