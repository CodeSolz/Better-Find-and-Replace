<?php namespace RealTimeAutoFindReplace\install;

/**
 * Installation
 *
 * @package Install
 * @since 1.0.0
 * @author M.Tuhin <info@codesolz.com>
 */

if ( ! defined( 'CS_RTAFAR_VERSION' ) ) {
	exit;
}

use RealTimeAutoFindReplace\admin\functions\Masking;

class Activate {


	/**
	 * Install DB
	 *
	 * @return void
	 */
	public static function on_activate() {
		global $wpdb;
		$charset_collate = $wpdb->get_charset_collate();

		$sqls = array(
			"CREATE TABLE IF NOT EXISTS `{$wpdb->prefix}rtafar_rules`(
				`id` int(11) NOT NULL auto_increment,
				`find` text,
				`replace` mediumtext,
				`type` varchar(56),
				`delay` float,
				`html_charset` char(20),
				`flags` varchar(8),
				`where_to_replace` varchar(128),
				PRIMARY KEY ( `id`)
				) $charset_collate",
		);

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		foreach ( $sqls as $sql ) {
			dbDelta( $sql );

			// Log any errors from dbDelta
			if ( ! empty( $wpdb->last_error ) ) {
				wp_die( esc_html__( 'BFR Database installation failed. Error: ', 'real-time-auto-find-and-replace' ) . esc_html( $wpdb->last_error ) );
			}
		}

		// add db version to db
		add_option( 'rtafar_db_version', CS_RTAFAR_DB_VERSION );
		add_option( 'rtafar_plugin_version', CS_RTAFAR_VERSION );
		add_option( 'rtafar_plugin_install_date', gmdate( 'Y-m-d H:i:s' ) );
	}

	/**
	 * Check DB Status
	 *
	 * @return void
	 */
	public static function check_db_status() {
		global $wpdb;
		$import_old_settings          = false;
		$get_installed_db_version     = get_site_option( 'rtafar_db_version' );
		$get_installed_plugin_version = get_site_option( 'rtafar_plugin_version' );

		if ( empty( $get_installed_db_version ) ) {
			self::on_activate();
			$import_old_settings = true;
		} elseif ( \version_compare( $get_installed_db_version, CS_RTAFAR_DB_VERSION, '!=' ) ) {

			$update_sqls = array();

			if ( \version_compare( $get_installed_db_version, '1.0.1', '<' ) ) {
				$update_sqls = array(
					"ALTER TABLE `{$wpdb->prefix}rtafar_rules` ADD COLUMN delay FLOAT DEFAULT 0 AFTER type",
					"ALTER TABLE `{$wpdb->prefix}rtafar_rules` ADD COLUMN tag_selector mediumtext AFTER delay",
				);
			}

			if ( \version_compare( $get_installed_db_version, '1.0.2', '<' ) ) {
				$update_sqls = array_merge_recursive(
					$update_sqls,
					array(
						"ALTER TABLE `{$wpdb->prefix}rtafar_rules` DROP COLUMN tag_selector",
					)
				);
			}

			if ( \version_compare( $get_installed_db_version, '1.0.3', '<' ) ) {
				$update_sqls = array_merge_recursive(
					$update_sqls,
					array(
						"ALTER TABLE `{$wpdb->prefix}rtafar_rules` ADD COLUMN html_charset char(20)  AFTER delay",
					)
				);
			}

			// update db
			if ( ! empty( $update_sqls ) ) {
				require_once ABSPATH . 'wp-admin/includes/upgrade.php';
				foreach ( $update_sqls as $sql ) {
					dbDelta( $sql );

					// Log any errors from dbDelta
					if ( ! empty( $wpdb->last_error ) ) {
						wp_die( esc_html__( 'BFR Database update failed. Error: ', 'real-time-auto-find-and-replace' ) . esc_html( $wpdb->last_error ) );
					}
				}
			}

			// dbDelta ignores raw ALTER, so these run directly.
			if ( \version_compare( $get_installed_db_version, '1.0.4', '<' ) ) {
				self::ensure_flags_column();
				self::strip_legacy_slashes_in_rules();
			}

			// update plugin db version
			update_option( 'rtafar_db_version', CS_RTAFAR_DB_VERSION );

		}

		if ( true === $import_old_settings ) {
			self::import_old_settings();
		}

		// update plugin version
		update_option( 'rtafar_plugin_version', CS_RTAFAR_VERSION );
	}

	/** Adds the `flags` column to the rules table if missing (idempotent). */
	private static function ensure_flags_column() {
		global $wpdb;

		$table = $wpdb->prefix . 'rtafar_rules';

		$exists = $wpdb->get_var(
			$wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				"SHOW COLUMNS FROM `{$table}` LIKE %s",
				'flags'
			)
		);

		if ( $exists ) {
			return;
		}

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$wpdb->query( "ALTER TABLE `{$table}` ADD COLUMN `flags` varchar(8) AFTER `html_charset`" );

		if ( ! empty( $wpdb->last_error ) && defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( 'rtafar: failed to add flags column — ' . $wpdb->last_error );
		}
	}

	/** Strips literal backslashes from rule rows saved before 1.0.4. */
	private static function strip_legacy_slashes_in_rules() {
		global $wpdb;

		$table = $wpdb->prefix . 'rtafar_rules';

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rows = $wpdb->get_results( "SELECT id, find, replace FROM `{$table}`" );

		if ( ! $rows ) {
			return;
		}

		$failed = 0;
		foreach ( $rows as $row ) {
			$find_clean    = stripslashes( $row->find );
			$replace_clean = stripslashes( $row->replace );
			if ( $find_clean === $row->find && $replace_clean === $row->replace ) {
				continue;
			}
			$updated = $wpdb->update(
				$table,
				array(
					'find'    => $find_clean,
					'replace' => $replace_clean,
				),
				array( 'id' => $row->id ),
				array( '%s', '%s' ),
				array( '%d' )
			);
			if ( false === $updated ) {
				$failed++;
			}
		}

		if ( $failed > 0 && defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( sprintf( 'rtafar: 1.0.4 migration left %d rule row(s) un-cleaned', $failed ) );
		}
	}

	/**
	 * Import old settings
	 *
	 * @return void
	 */
	private static function import_old_settings() {
		$get_Rtfar = get_option( 'rtafar_settings' );
		if ( ! empty( $get_Rtfar ) && is_array( $get_Rtfar ) ) {
			$Masking = new Masking();
			foreach ( $get_Rtfar as $find => $replace ) {
				// Args (in order): find, replace, type, replace_where, id,
				// delay_time, user_query — all required since 1.9.0.
				$Masking->insert_masking_rules( $find, $replace, 'plain', 'all', '', 0, array() );
			}
			delete_option( 'rtafar_settings' );
		}

		return true;
	}


	/**
	 * Remove custom urls on deactivate
	 *
	 * @return void
	 */
	public static function on_deactivate() {
		// remove notice status
		delete_option( CS_NOTICE_ID . 'ed_Activated' );
		delete_option( CS_NOTICE_ID . 'ed_Feedback' );
		return true;
	}

	/**
	 * show notices
	 *
	 * @return void
	 */
	public static function onUpgrade() {
		// remove notice status
		if ( ! get_option( CS_NOTICE_ID . 'ed_Feedback_offPerm' ) ) {
			delete_option( CS_NOTICE_ID . 'ed_Feedback' );
		}

		return true;
	}
}
