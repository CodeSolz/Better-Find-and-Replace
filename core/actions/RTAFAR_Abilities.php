<?php namespace RealTimeAutoFindReplace\actions;

/**
 * Class: Abilities (WordPress Abilities API, WordPress 6.9+)
 *
 * Lets AI assistants and other tools work with replacement rules the same way the admin screens
 * do: read the available rule types, list rules, add or change a rule, delete a rule. Every call
 * checks the same capabilities as the admin pages, validates rules with the same checks, and saves
 * through the same code (so the Pro add-on's hooks run too). Changes return the rule as it was
 * before, so a caller can put it back.
 *
 * @package Action
 * @since 2.1.0
 * @author M.Tuhin <info@codesolz.net>
 */

if ( ! defined( 'CS_RTAFAR_VERSION' ) ) {
	die();
}

use RealTimeAutoFindReplace\admin\functions\Masking;
use RealTimeAutoFindReplace\admin\options\pages\AddNewRule;
use RealTimeAutoFindReplace\lib\Util;

/**
 * Registers the bfr/* abilities.
 */
class RTAFAR_Abilities {

	/** The abilities' category slug. */
	const CATEGORY = 'better-find-and-replace';

	/**
	 * Hooks in on WordPress 6.9+ (the Abilities API); does nothing on older versions.
	 */
	public function __construct() {
		if ( ! function_exists( 'wp_register_ability' ) ) {
			return;
		}
		add_action( 'wp_abilities_api_categories_init', array( $this, 'register_category' ) );
		add_action( 'wp_abilities_api_init', array( $this, 'register_abilities' ) );
	}

	/**
	 * The category the abilities are listed under.
	 */
	public function register_category() {
		wp_register_ability_category(
			self::CATEGORY,
			array(
				'label'       => __( 'Better Find and Replace', 'real-time-auto-find-and-replace' ),
				'description' => __( 'Real-time find and replace rules: text replaced as pages are shown, without changing the database.', 'real-time-auto-find-and-replace' ),
			)
		);
	}

	/**
	 * The four abilities.
	 */
	public function register_abilities() {
		$rule_props = array(
			'find'             => array(
				'type'        => 'string',
				'description' => __( 'Text (or pattern, for regex types) to find.', 'real-time-auto-find-and-replace' ),
			),
			'replace'          => array(
				'type'        => 'string',
				'description' => __( 'What to show instead.', 'real-time-auto-find-and-replace' ),
			),
			'type'             => array(
				'type'        => 'string',
				'description' => __( 'Rule type key from bfr/get-rule-options, e.g. plain.', 'real-time-auto-find-and-replace' ),
			),
			'where_to_replace' => array(
				'type'        => 'string',
				'description' => __( 'Where the rule applies; "all" for the whole website.', 'real-time-auto-find-and-replace' ),
				'default'     => 'all',
			),
			'delay'            => array(
				'type'        => 'number',
				'minimum'     => 0,
				'maximum'     => 10,
				'description' => __( 'Seconds to wait before replacing (jQuery / Ajax types only).', 'real-time-auto-find-and-replace' ),
			),
			'html_charset'     => array(
				'type'        => 'string',
				'description' => __( 'Website charset for the multibyte type, e.g. UTF-8.', 'real-time-auto-find-and-replace' ),
			),
			'case_insensitive' => array( 'type' => 'boolean' ),
			'whole_word'       => array( 'type' => 'boolean' ),
			'unicode_modifier' => array( 'type' => 'boolean' ),
		);

		wp_register_ability(
			'bfr/get-rule-options',
			array(
				'label'               => __( 'Rule types and locations', 'real-time-auto-find-and-replace' ),
				'description'         => __( 'The replacement rule types and places a rule can apply to on this site (Pro adds more).', 'real-time-auto-find-and-replace' ),
				'category'            => self::CATEGORY,
				'output_schema'       => array( 'type' => 'object' ),
				'execute_callback'    => array( $this, 'rule_options' ),
				'permission_callback' => array( $this, 'can_list' ),
				'meta'                => array(
					'show_in_rest' => true,
					'annotations'  => array(
						'readonly'   => true,
						'idempotent' => true,
					),
				),
			)
		);

		wp_register_ability(
			'bfr/list-rules',
			array(
				'label'               => __( 'List replacement rules', 'real-time-auto-find-and-replace' ),
				'description'         => __( 'Replacement rules on this site, newest first, optionally filtered by text or type.', 'real-time-auto-find-and-replace' ),
				'category'            => self::CATEGORY,
				'input_schema'        => array(
					'type'                 => 'object',
					'properties'           => array(
						'search' => array( 'type' => 'string' ),
						'type'   => array( 'type' => 'string' ),
						'limit'  => array(
							'type'    => 'integer',
							'minimum' => 1,
							'maximum' => 200,
							'default' => 50,
						),
						'offset' => array(
							'type'    => 'integer',
							'minimum' => 0,
							'default' => 0,
						),
					),
					'additionalProperties' => false,
				),
				'output_schema'       => array( 'type' => 'object' ),
				'execute_callback'    => array( $this, 'list_rules' ),
				'permission_callback' => array( $this, 'can_list' ),
				'meta'                => array(
					'show_in_rest' => true,
					'annotations'  => array(
						'readonly'   => true,
						'idempotent' => true,
					),
				),
			)
		);

		wp_register_ability(
			'bfr/save-rule',
			array(
				'label'               => __( 'Add or change a replacement rule', 'real-time-auto-find-and-replace' ),
				'description'         => __( 'Adds a rule, or changes one (id). A rule with the same find text and type is updated instead of duplicated. Returns the rule before and after.', 'real-time-auto-find-and-replace' ),
				'category'            => self::CATEGORY,
				'input_schema'        => array(
					'type'                 => 'object',
					'properties'           => array_merge(
						array(
							'id' => array(
								'type'    => 'integer',
								'minimum' => 1,
							),
						),
						$rule_props
					),
					'required'             => array( 'find', 'type' ),
					'additionalProperties' => false,
				),
				'output_schema'       => array( 'type' => 'object' ),
				'execute_callback'    => array( $this, 'save_rule' ),
				'permission_callback' => array( $this, 'can_add' ),
				'meta'                => array(
					'show_in_rest' => true,
					'annotations'  => array(
						'readonly'    => false,
						'destructive' => false,
						'idempotent'  => true,
					),
				),
			)
		);

		wp_register_ability(
			'bfr/delete-rule',
			array(
				'label'               => __( 'Delete a replacement rule', 'real-time-auto-find-and-replace' ),
				'description'         => __( 'Deletes one rule. Returns the deleted rule, so it can be added back with bfr/save-rule.', 'real-time-auto-find-and-replace' ),
				'category'            => self::CATEGORY,
				'input_schema'        => array(
					'type'                 => 'object',
					'properties'           => array(
						'id' => array(
							'type'    => 'integer',
							'minimum' => 1,
						),
					),
					'required'             => array( 'id' ),
					'additionalProperties' => false,
				),
				'output_schema'       => array( 'type' => 'object' ),
				'execute_callback'    => array( $this, 'delete_rule' ),
				'permission_callback' => array( $this, 'can_list' ),
				'meta'                => array(
					'show_in_rest' => true,
					'annotations'  => array(
						'readonly'    => false,
						'destructive' => true,
						'idempotent'  => true,
					),
				),
			)
		);
	}

	/** Same gate as the "Add New Rule" screen. */
	public function can_add() {
		return current_user_can( 'manage_options' ) || current_user_can( Util::bfar_nav_cap( 'add_masking_rule' ) );
	}

	/** Same gate as the "All Rules" screen (which lists and deletes rules). */
	public function can_list() {
		return current_user_can( 'manage_options' ) || current_user_can( Util::bfar_nav_cap( 'all_masking_rules' ) );
	}

	/**
	 * Usable rule types and locations (Pro-only placeholders left out).
	 *
	 * @return array
	 */
	public function rule_options() {
		$types = array();
		$opts  = AddNewRule::rule_type_options();
		$add   = function ( $items, $group ) use ( &$types ) {
			foreach ( (array) $items as $key => $label ) {
				if ( is_string( $key ) && false === strpos( $key, '_disabled' ) ) {
					$types[] = array(
						'type'  => $key,
						'label' => trim( preg_replace( '/\s+/', ' ', wp_strip_all_tags( (string) $label ) ) ),
						'group' => $group,
					);
				}
			}
		};
		if ( isset( $opts['hasGroup'] ) && is_array( $opts['hasGroup'] ) ) {
			foreach ( $opts['hasGroup'] as $group => $list ) {
				$add( $list, trim( preg_replace( '/\s+/', ' ', (string) $group ) ) );
			}
		} else {
			$add( $opts, '' );
		}
		$locations = array();
		foreach ( (array) AddNewRule::location_options() as $key => $label ) {
			if ( is_string( $key ) && false === strpos( $key, '_disabled' ) ) {
				$locations[] = array(
					'where_to_replace' => $key,
					'label'            => trim( wp_strip_all_tags( (string) $label ) ),
				);
			}
		}
		return array(
			'types'     => $types,
			'locations' => $locations,
			'notes'     => __( 'Realtime types change only what visitors see; the database stays as it is. Rules for specific pages are set in the plugin\'s admin screen.', 'real-time-auto-find-and-replace' ),
		);
	}

	/**
	 * Rules, newest first.
	 *
	 * @param array $input {search?, type?, limit?, offset?}.
	 * @return array
	 */
	public function list_rules( $input = array() ) {
		global $wpdb;
		$input  = (array) $input;
		$limit  = min( 200, max( 1, (int) ( $input['limit'] ?? 50 ) ) );
		$offset = max( 0, (int) ( $input['offset'] ?? 0 ) );
		$where  = array( '1=1' );
		$args   = array();
		if ( ! empty( $input['search'] ) ) {
			$like    = '%' . $wpdb->esc_like( (string) $input['search'] ) . '%';
			$where[] = '( `find` LIKE %s OR `replace` LIKE %s )';
			$args[]  = $like;
			$args[]  = $like;
		}
		if ( ! empty( $input['type'] ) ) {
			$where[] = '`type` = %s';
			$args[]  = (string) $input['type'];
		}
		$table = $wpdb->prefix . 'rtafar_rules';
		$sql   = 'FROM `' . $table . '` WHERE ' . implode( ' AND ', $where );
		// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery -- the table name is the plugin's own; every value is a placeholder.
		$total = (int) ( $args ? $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) ' . $sql, $args ) ) : $wpdb->get_var( 'SELECT COUNT(*) ' . $sql ) );
		$rows  = $wpdb->get_results( $wpdb->prepare( 'SELECT * ' . $sql . ' ORDER BY id DESC LIMIT %d OFFSET %d', array_merge( $args, array( $limit, $offset ) ) ), ARRAY_A );
		// phpcs:enable
		return array(
			'rules' => array_map( array( $this, 'shape' ), (array) $rows ),
			'total' => $total,
		);
	}

	/**
	 * Adds a rule or changes one; returns it before and after.
	 *
	 * @param array $input Rule fields (see the input schema).
	 * @return array|\WP_Error
	 */
	public function save_rule( $input = array() ) {
		global $wpdb;
		$input = (array) $input;
		$find  = (string) ( $input['find'] ?? '' );
		$type  = (string) ( $input['type'] ?? '' );
		$where = (string) ( $input['where_to_replace'] ?? 'all' );
		$opts  = $this->rule_options();
		if ( ! in_array( $type, wp_list_pluck( $opts['types'], 'type' ), true ) ) {
			return new \WP_Error( 'bfr_bad_type', __( 'Unknown rule type: use one from bfr/get-rule-options.', 'real-time-auto-find-and-replace' ) );
		}
		if ( 'all' !== $where ) {
			return new \WP_Error( 'bfr_bad_location', __( 'Only rules for the whole website ("all") can be saved this way; rules for specific pages are set in the plugin\'s admin screen.', 'real-time-auto-find-and-replace' ) );
		}
		if ( '' === trim( $find ) ) {
			return new \WP_Error( 'bfr_empty_find', __( 'A rule needs something to find.', 'real-time-auto-find-and-replace' ) );
		}
		$valid = Masking::validate_rule( $find, $type );
		if ( true !== $valid ) {
			return new \WP_Error( 'bfr_invalid_rule', $valid );
		}
		$table = $wpdb->prefix . 'rtafar_rules';
		$id    = (int) ( $input['id'] ?? 0 );
		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery -- the plugin's own table.
		$before = $id
			? $wpdb->get_row( $wpdb->prepare( "SELECT * FROM `{$table}` WHERE id = %d", $id ), ARRAY_A )
			: $wpdb->get_row( $wpdb->prepare( "SELECT * FROM `{$table}` WHERE `find` = %s AND `type` = %s", $find, $type ), ARRAY_A );
		if ( $id && ! $before ) {
			return new \WP_Error( 'bfr_not_found', __( 'No rule with that id.', 'real-time-auto-find-and-replace' ) );
		}
		$query = array(
			'find'             => $find,
			'replace'          => (string) ( $input['replace'] ?? '' ),
			'type'             => $type,
			'where_to_replace' => $where,
			'html_charset'     => (string) ( $input['html_charset'] ?? '' ),
		);
		// Like the admin form, a checkbox is sent only when it is on (the Pro add-on reads a present key as "on").
		foreach ( array( 'case_insensitive', 'unicode_modifier', 'whole_word' ) as $flag ) {
			if ( ! empty( $input[ $flag ] ) ) {
				$query[ $flag ] = 'on';
			}
		}
		$delay = isset( $input['delay'] ) ? (float) $input['delay'] : ( $before ? (float) $before['delay'] : 0 );
		$msg   = ( new Masking() )->insert_masking_rules( $query['find'], $query['replace'], $type, $where, $before ? (int) $before['id'] : '', $delay, $query );
		$after = $before
			? $wpdb->get_row( $wpdb->prepare( "SELECT * FROM `{$table}` WHERE id = %d", (int) $before['id'] ), ARRAY_A )
			: $wpdb->get_row( $wpdb->prepare( "SELECT * FROM `{$table}` WHERE `type` = %s ORDER BY id DESC LIMIT 1", $type ), ARRAY_A );
		// phpcs:enable
		return array(
			'action'   => trim( (string) $msg ),
			'rule'     => $after ? $this->shape( $after ) : null,
			'previous' => $before ? $this->shape( $before ) : null,
		);
	}

	/**
	 * Deletes a rule; returns it.
	 *
	 * @param array $input {id}.
	 * @return array|\WP_Error
	 */
	public function delete_rule( $input = array() ) {
		global $wpdb;
		$id    = (int) ( ( (array) $input )['id'] ?? 0 );
		$table = $wpdb->prefix . 'rtafar_rules';
		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery -- the plugin's own table.
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM `{$table}` WHERE id = %d", $id ), ARRAY_A );
		if ( ! $row ) {
			return new \WP_Error( 'bfr_not_found', __( 'No rule with that id.', 'real-time-auto-find-and-replace' ) );
		}
		$wpdb->delete( $table, array( 'id' => $id ), array( '%d' ) );
		// phpcs:enable
		return array(
			'deleted' => $this->shape( $row ),
			'restore' => __( 'To put it back, call bfr/save-rule with the deleted rule\'s find, replace, type and where_to_replace.', 'real-time-auto-find-and-replace' ),
		);
	}

	/**
	 * A rule row for callers.
	 *
	 * @param array $r Row.
	 * @return array
	 */
	private function shape( $r ) {
		$flags = (string) ( $r['flags'] ?? '' );
		return array(
			'id'               => (int) $r['id'],
			'find'             => (string) $r['find'],
			'replace'          => (string) $r['replace'],
			'type'             => (string) $r['type'],
			'where_to_replace' => (string) $r['where_to_replace'],
			'delay'            => (float) ( $r['delay'] ?? 0 ),
			'html_charset'     => (string) ( $r['html_charset'] ?? '' ),
			'case_insensitive' => false !== strpos( $flags, 'i' ),
			'whole_word'       => false !== strpos( $flags, 'w' ),
			'unicode_modifier' => false !== strpos( $flags, 'u' ),
		);
	}
}
