<?php namespace RealTimeAutoFindReplace\Maintenance\Data\Schema;

use RealTimeAutoFindReplace\Maintenance\Support\Logger;

/**
 * The maintenance platform's own schema.
 *
 * Deliberately independent of core/install/Activate.php. That installer is a
 * sound version ladder for rtafar_rules, but it wp_die()s the whole request
 * when dbDelta reports an error - acceptable for one small table at activation
 * time, not acceptable for seven tables checked on every admin load. A site
 * whose host forbids CREATE TABLE must still be able to use find and replace.
 *
 * So: our own version option, our own ladder, and a failure path that disables
 * the affected modules and says so, rather than white-screening the admin.
 *
 * Every table here carries the UNIQUE key that makes its writer idempotent.
 * That column is not optional - it is the only thing standing between a retried
 * background job and a duplicate row (see 05-DB-SCHEMA.md).
 *
 * @package Maintenance
 * @since 1.10.0
 * @author M.Tuhin <info@codesolz.net>
 */

if ( ! defined( 'CS_RTAFAR_VERSION' ) ) {
	exit;
}

// Table names in this file always come from Schema\Tables, which composes them
// from $wpdb->prefix plus a literal - no request data ever reaches an SQL
// identifier, while every VALUE still travels as a placeholder through
// $wpdb->prepare(). A repository is also the one layer where direct, uncached
// queries are the point rather than an oversight. Disabled for the file rather
// than line by line because a multi-line statement silently escapes a per-line
// annotation, which is how these end up unreviewed.
// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery
// phpcs:disable WordPress.DB.DirectDatabaseQuery.NoCaching

class Tables {

	/** Bump when any CREATE TABLE below changes. */
	const DB_VERSION = '1.2.0';

	/** Where that version is recorded. Per site: the tables are per site. */
	const VERSION_OPTION = 'bfr_maintenance_db_version';

	/** Set when dbDelta failed, so the UI can explain itself. */
	const ERROR_OPTION = 'bfr_maintenance_db_error';

	/**
	 * Install or upgrade if the stored version is behind.
	 *
	 * The cost on a normal request is one autoloaded option read and a string
	 * comparison. Nothing else runs unless the version actually moved.
	 *
	 * @param bool $force Run even when the stored version already matches.
	 * @return bool True when dbDelta ran and succeeded.
	 */
	public static function maybe_install( $force = false ) {
		if ( ! function_exists( 'get_option' ) ) {
			return false;
		}

		$installed = get_option( self::VERSION_OPTION, '' );

		if ( ! $force && $installed && version_compare( $installed, self::DB_VERSION, '>=' ) ) {
			return false;
		}

		$result = self::install();

		if ( ! $result['ok'] ) {
			// Record and stop. Do not store the version, so the next admin
			// load tries again - a transient permissions problem should heal
			// itself without the user doing anything.
			update_option( self::ERROR_OPTION, $result['error'], false );
			Logger::log( 'schema.failed', array( 'error' => $result['error'] ) );

			return false;
		}

		update_option( self::VERSION_OPTION, self::DB_VERSION, true );
		delete_option( self::ERROR_OPTION );
		self::seed_front_end_guards();

		Logger::log( 'schema.installed', array( 'version' => self::DB_VERSION ) );

		if ( function_exists( 'do_action' ) ) {
			/**
			 * Fires after the maintenance tables are created or upgraded.
			 *
			 * @param string $version Schema version now installed.
			 */
			do_action( 'bfr_maintenance_installed', self::DB_VERSION );
		}

		return true;
	}

	/**
	 * Run dbDelta for every table, then check the tables are actually there.
	 *
	 * Success is verified by existence, not by $wpdb->last_error. dbDelta
	 * begins by running DESCRIBE against a table that does not exist yet, which
	 * leaves last_error populated on a perfectly successful first install -
	 * trusting it means aborting after the first table and reporting a failure
	 * that did not happen.
	 *
	 * @return array array( 'ok' => bool, 'error' => string )
	 */
	public static function install() {
		global $wpdb;

		if ( ! function_exists( 'dbDelta' ) ) {
			require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		}

		$collate = $wpdb->get_charset_collate();

		foreach ( self::schemas( $collate ) as $sql ) {
			dbDelta( $sql );
		}

		// A host that refuses CREATE TABLE is the case this is really for. It
		// must be reported honestly rather than leaving screens that query
		// tables which are not there.
		$missing = self::missing_tables();

		if ( empty( $missing ) ) {
			return array(
				'ok'    => true,
				'error' => '',
			);
		}

		$error = $wpdb->last_error;

		if ( '' === $error ) {
			$error = 'Could not create: ' . implode( ', ', $missing );
		}

		return array(
			'ok'    => false,
			'error' => $error,
		);
	}

	/**
	 * Make sure the front-end guard options exist and are autoloaded.
	 *
	 * Both guards are read on visitor requests - one by the redirect executor,
	 * one by the 404 capture - and both are supposed to answer without a query.
	 * An option that has never been written is not in `alloptions`, so the first
	 * read of a missing one costs a query on *every* request until something
	 * finally saves it. Seeding them at install closes that window.
	 *
	 * add_option() never overwrites, so this cannot disturb a live setting.
	 *
	 * @return void
	 */
	private static function seed_front_end_guards() {
		if ( ! function_exists( 'add_option' ) ) {
			return;
		}

		add_option( 'bfr_redirect_count', 0, '', 'yes' );
		add_option( 'bfr_404_enabled', 0, '', 'yes' );
	}

	/**
	 * Which of our tables are not in the database.
	 *
	 * Probes with SELECT rather than SHOW TABLES LIKE. The LIKE form needs the
	 * pattern escaped because a table prefix contains underscores, which are
	 * single-character wildcards - and even escaped it is unreliable across
	 * environments (it returns nothing under the WordPress test harness, where
	 * a bare SHOW TABLES works). A one-row SELECT is unambiguous everywhere: a
	 * missing table is an error, an empty table is not.
	 *
	 * @return array Table names, empty when all are present.
	 */
	public static function missing_tables() {
		global $wpdb;

		$missing  = array();
		$suppress = $wpdb->suppress_errors( true );

		foreach ( self::all() as $table ) {
			$wpdb->last_error = '';

			// Table names come from self::all(): the prefix plus a literal.
			$wpdb->get_var( "SELECT 1 FROM {$table} LIMIT 1" );

			if ( ! empty( $wpdb->last_error ) ) {
				$missing[] = $table;
			}
		}

		$wpdb->last_error = '';
		$wpdb->suppress_errors( $suppress );

		return $missing;
	}

	/**
	 * Is the schema present and current?
	 *
	 * @return bool
	 */
	public static function installed() {
		if ( ! function_exists( 'get_option' ) ) {
			return false;
		}

		$installed = get_option( self::VERSION_OPTION, '' );

		return $installed && version_compare( $installed, self::DB_VERSION, '>=' );
	}

	/**
	 * The last install error, if any.
	 *
	 * @return string
	 */
	public static function last_error() {
		if ( ! function_exists( 'get_option' ) ) {
			return '';
		}

		return (string) get_option( self::ERROR_OPTION, '' );
	}

	/*
	 * Table names.
	 *
	 * Always the prefix plus a literal - never anything a request supplied.
	 * Callers interpolate these into SQL, so this is the only place that is
	 * allowed to produce a table name.
	 */

	/**
	 * The shared issue model - every module writes here.
	 *
	 * @return string
	 */
	public static function issues() {
		global $wpdb;
		return $wpdb->prefix . 'rtafar_issues';
	}

	/**
	 * Current state of each checked URL.
	 *
	 * @return string
	 */
	public static function link_checks() {
		global $wpdb;
		return $wpdb->prefix . 'rtafar_link_checks';
	}

	/**
	 * Aggregated 404 requests.
	 *
	 * @return string
	 */
	public static function not_found() {
		global $wpdb;
		return $wpdb->prefix . 'rtafar_404_logs';
	}

	/**
	 * Redirect rules, read on the front end.
	 *
	 * @return string
	 */
	public static function redirects() {
		global $wpdb;
		return $wpdb->prefix . 'rtafar_redirects';
	}

	/**
	 * Redirect hits, one row per rule per day.
	 *
	 * Aggregated rather than one row per hit, so the table's size follows the
	 * number of rules and the length of time they have existed - never the
	 * amount of traffic. A redirect that fires a million times in a day is one
	 * row, updated a million times, and an UPDATE on a primary key is about as
	 * cheap as a write gets.
	 *
	 * @return string
	 */
	public static function redirect_hits() {
		global $wpdb;
		return $wpdb->prefix . 'rtafar_redirect_hits';
	}

	/**
	 * Proposed content, held beside the post it is about.
	 *
	 * Deliberately not a row in wp_posts. A proposal that lived there would
	 * have its own id and therefore its own URL, and merging it would mean
	 * migrating comments, terms and metadata off one post and onto another -
	 * five careful copies where the requirement is that nothing moves.
	 *
	 * @return string
	 */
	public static function revisions() {
		global $wpdb;
		return $wpdb->prefix . 'rtafar_revisions';
	}

	/**
	 * Scan progress, including the resume cursor.
	 *
	 * @return string
	 */
	public static function scan_runs() {
		global $wpdb;
		return $wpdb->prefix . 'rtafar_scan_runs';
	}

	/**
	 * The queue.
	 *
	 * @return string
	 */
	public static function jobs() {
		global $wpdb;
		return $wpdb->prefix . 'rtafar_jobs';
	}

	/**
	 * User-facing history of what changed.
	 *
	 * @return string
	 */
	public static function activity() {
		global $wpdb;
		return $wpdb->prefix . 'rtafar_activity_log';
	}

	/**
	 * Every table this module owns.
	 *
	 * @return array
	 */
	public static function all() {
		return array(
			self::issues(),
			self::link_checks(),
			self::not_found(),
			self::redirects(),
			self::redirect_hits(),
			self::revisions(),
			self::scan_runs(),
			self::jobs(),
			self::activity(),
		);
	}

	/**
	 * Options this module owns.
	 *
	 * Listed here so the data-removal path has one source of truth.
	 *
	 * @return array
	 */
	public static function options() {
		return array(
			self::VERSION_OPTION,
			self::ERROR_OPTION,
			'bfr_redirect_count',
			'bfr_maintenance_settings',
			'bfr_404_enabled',
			'bfr_404_budget',
			'bfr_maintenance_pruned_on',
		);
	}

	/**
	 * Drop every table and option this module owns.
	 *
	 * Only ever called from an explicit, confirmed user action - never on
	 * deactivate, and never automatically. Neither plugin ships uninstall.php
	 * today, so this is the honest way to let someone leave cleanly.
	 *
	 * @return void
	 */
	public static function drop_all() {
		global $wpdb;

		foreach ( self::all() as $table ) {
			// Table names come from self::all(), which composes them from the
			// prefix plus a literal. No request data reaches this string.
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.SchemaChange
			$wpdb->query( "DROP TABLE IF EXISTS {$table}" );
		}

		foreach ( self::options() as $option ) {
			delete_option( $option );
		}

		Logger::log( 'schema.dropped' );
	}

	/**
	 * All CREATE TABLE statements.
	 *
	 * Note that dbDelta parses this text rather than the database, so formatting is
	 * load-bearing: one field per line, two spaces after PRIMARY KEY, KEY
	 * rather than INDEX, lower-case types. Sloppy formatting makes it re-run
	 * ALTERs on every single load.
	 *
	 * Indexed varchars are capped at 191 characters. utf8mb4 stores 4 bytes per
	 * character and older InnoDB rows cap an index at 767 bytes, so a full
	 * varchar(255) index fails to create on exactly the older hosts that most
	 * need it to work.
	 *
	 * @param string $collate Charset collation clause.
	 * @return array
	 */
	private static function schemas( $collate ) {
		$issues    = self::issues();
		$checks    = self::link_checks();
		$not_found = self::not_found();
		$redirects = self::redirects();
		$scan_runs = self::scan_runs();
		$jobs      = self::jobs();
		$activity  = self::activity();

		$schemas = array();

		// The shared issue model. Every module writes here; the dashboard
		// reads only here.
		$schemas[] = "CREATE TABLE {$issues} (
			id bigint(20) unsigned NOT NULL auto_increment,
			issue_key char(40) NOT NULL default '',
			type varchar(32) NOT NULL default '',
			subtype varchar(32) NOT NULL default '',
			object_type varchar(20) NOT NULL default '',
			object_id bigint(20) unsigned NOT NULL default 0,
			source_url varchar(255) NOT NULL default '',
			target_url varchar(255) NOT NULL default '',
			target_hash char(40) NOT NULL default '',
			occurrences int(10) unsigned NOT NULL default 1,
			status varchar(20) NOT NULL default 'open',
			severity tinyint(3) unsigned NOT NULL default 3,
			priority_score smallint(5) unsigned NOT NULL default 0,
			confidence decimal(3,2) default NULL,
			scan_id bigint(20) unsigned NOT NULL default 0,
			first_seen_at datetime default NULL,
			last_seen_at datetime default NULL,
			resolved_at datetime default NULL,
			metadata longtext,
			created_at datetime default NULL,
			updated_at datetime default NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY issue_key (issue_key),
			KEY status_type (status,type,priority_score),
			KEY object (object_type,object_id),
			KEY target_hash (target_hash),
			KEY last_seen_at (last_seen_at)
		) $collate";

		// One row per URL, updated in place. Check history belongs in the
		// activity log, not here - this table answers "what is the current
		// state of this URL", and it has to stay small enough to be fast.
		$schemas[] = "CREATE TABLE {$checks} (
			id bigint(20) unsigned NOT NULL auto_increment,
			url_hash char(40) NOT NULL default '',
			url varchar(255) NOT NULL default '',
			http_status smallint(5) unsigned NOT NULL default 0,
			error_type varchar(20) NOT NULL default '',
			final_url varchar(255) NOT NULL default '',
			response_time_ms int(10) unsigned NOT NULL default 0,
			consecutive_failures tinyint(3) unsigned NOT NULL default 0,
			checked_at datetime default NULL,
			next_check_after datetime default NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY url_hash (url_hash),
			KEY next_check_after (next_check_after),
			KEY http_status (http_status)
		) $collate";

		// Attacker-controlled: any visitor can mint distinct paths. Bounded by
		// the capture layer (bot filter, daily budget, retention), never by
		// hoping nobody notices.
		$schemas[] = "CREATE TABLE {$not_found} (
			id bigint(20) unsigned NOT NULL auto_increment,
			path_hash char(40) NOT NULL default '',
			request_path varchar(255) NOT NULL default '',
			normalized_path varchar(255) NOT NULL default '',
			referrer varchar(255) NOT NULL default '',
			user_agent_hash char(40) NOT NULL default '',
			hit_count int(10) unsigned NOT NULL default 1,
			status varchar(20) NOT NULL default 'new',
			first_seen_at datetime default NULL,
			last_seen_at datetime default NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY path_hash (path_hash),
			KEY status_hits (status,hit_count),
			KEY last_seen_at (last_seen_at)
		) $collate";

		// Read on the front end, on every request that gets that far, so it
		// stays narrow and its lookup key is a hash rather than a LIKE.
		$schemas[] = "CREATE TABLE {$redirects} (
			id bigint(20) unsigned NOT NULL auto_increment,
			source_hash char(40) NOT NULL default '',
			source varchar(255) NOT NULL default '',
			destination varchar(255) NOT NULL default '',
			redirect_type smallint(5) unsigned NOT NULL default 301,
			match_type varchar(16) NOT NULL default 'exact',
			enabled tinyint(1) NOT NULL default 1,
			group_name varchar(64) NOT NULL default '',
			hit_count int(10) unsigned NOT NULL default 0,
			last_hit_at datetime default NULL,
			expires_at datetime default NULL,
			created_by bigint(20) unsigned NOT NULL default 0,
			created_at datetime default NULL,
			updated_at datetime default NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY source_hash (source_hash),
			KEY enabled_match (enabled,match_type),
			KEY destination (destination(191))
		) $collate";

		// A scan that cannot be resumed is a scan that never finishes on a
		// large site, so the cursor and the heartbeat are the point of this
		// table, not decoration.
		$hits = self::redirect_hits();

		// One row per rule per day. The unique key is what makes the write an
		// upsert rather than a select-then-insert, which is the only shape that
		// is safe when two requests hit the same redirect in the same moment.
		$schemas[] = "CREATE TABLE {$hits} (
			id bigint(20) unsigned NOT NULL auto_increment,
			redirect_id bigint(20) unsigned NOT NULL default 0,
			hit_date date NOT NULL,
			hits int(10) unsigned NOT NULL default 0,
			PRIMARY KEY  (id),
			UNIQUE KEY rule_day (redirect_id,hit_date),
			KEY hit_date (hit_date)
		) $collate";

		$revisions = self::revisions();

		// baseline_* is what the post said when the proposal was made, and it
		// is what makes a stale merge detectable: if the post has moved on
		// since, applying blindly would overwrite somebody's work.
		//
		// replaced_* is filled in at merge time, before the write. That is the
		// whole of rollback, and it has to be recorded before the risky step
		// rather than reconstructed after it.
		$schemas[] = "CREATE TABLE {$revisions} (
			id bigint(20) unsigned NOT NULL auto_increment,
			object_type varchar(20) NOT NULL default 'post',
			object_id bigint(20) unsigned NOT NULL default 0,
			status varchar(20) NOT NULL default 'open',
			source varchar(32) NOT NULL default '',
			baseline_hash char(40) NOT NULL default '',
			baseline_title text,
			baseline_content longtext,
			baseline_excerpt text,
			proposed_title text,
			proposed_content longtext,
			proposed_excerpt text,
			replaced_title text,
			replaced_content longtext,
			replaced_excerpt text,
			decisions longtext,
			metadata longtext,
			created_by bigint(20) unsigned NOT NULL default 0,
			created_at datetime default NULL,
			applied_at datetime default NULL,
			updated_at datetime default NULL,
			PRIMARY KEY  (id),
			KEY object (object_type,object_id,status),
			KEY status_created (status,created_at)
		) $collate";

		$schemas[] = "CREATE TABLE {$scan_runs} (
			id bigint(20) unsigned NOT NULL auto_increment,
			scan_type varchar(32) NOT NULL default '',
			scope varchar(32) NOT NULL default 'full',
			status varchar(20) NOT NULL default 'queued',
			total_items int(10) unsigned NOT NULL default 0,
			processed_items int(10) unsigned NOT NULL default 0,
			issues_found int(10) unsigned NOT NULL default 0,
			cursor_position varchar(64) NOT NULL default '',
			started_at datetime default NULL,
			completed_at datetime default NULL,
			heartbeat_at datetime default NULL,
			metadata text,
			PRIMARY KEY  (id),
			KEY type_status (scan_type,status),
			KEY heartbeat_at (heartbeat_at)
		) $collate";

		// The queue. job_key is what makes enqueueing idempotent under retry.
		$schemas[] = "CREATE TABLE {$jobs} (
			id bigint(20) unsigned NOT NULL auto_increment,
			job_key char(40) NOT NULL default '',
			job_type varchar(32) NOT NULL default '',
			payload text,
			status varchar(16) NOT NULL default 'pending',
			attempts tinyint(3) unsigned NOT NULL default 0,
			claim_token char(32) default NULL,
			claimed_at datetime default NULL,
			available_at datetime default NULL,
			last_error varchar(255) NOT NULL default '',
			created_at datetime default NULL,
			updated_at datetime default NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY job_key (job_key),
			KEY status_available (status,available_at),
			KEY claim_token (claim_token)
		) $collate";

		// What happened, who did it, what it touched. Old and new values live
		// in rtafar_history, which is what the Restore screen reads - keeping
		// them out of here is what stops this table holding post content.
		$schemas[] = "CREATE TABLE {$activity} (
			id bigint(20) unsigned NOT NULL auto_increment,
			operation_id char(32) NOT NULL default '',
			action varchar(48) NOT NULL default '',
			object_type varchar(20) NOT NULL default '',
			object_id bigint(20) unsigned NOT NULL default 0,
			user_id bigint(20) unsigned NOT NULL default 0,
			summary varchar(255) NOT NULL default '',
			metadata text,
			created_at datetime default NULL,
			PRIMARY KEY  (id),
			KEY operation_id (operation_id),
			KEY action_created (action,created_at),
			KEY object (object_type,object_id)
		) $collate";

		return $schemas;
	}
}
