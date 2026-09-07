<?php namespace RealTimeAutoFindReplace\actions;

use RealTimeAutoFindReplace\Maintenance\Data\Schema\Tables;
use RealTimeAutoFindReplace\Maintenance\Queue\CronDriver;
use RealTimeAutoFindReplace\Maintenance\Queue\Runner;
use RealTimeAutoFindReplace\Maintenance\Support\Logger;

/**
 * Maintenance platform boot.
 *
 * The single entry point for broken links, redirects, the 404 monitor, media
 * health and everything else built on the shared issue model. The plugin's
 * DirectoryIterator loader instantiates every class in core/actions, so adding
 * this file was enough to boot the platform - the main plugin file, the menu
 * registrar and the AJAX dispatcher are all untouched.
 *
 * The constructor registers hooks and does nothing else. No queries, no option
 * reads, no class loading beyond what registering needs. A site that never
 * opens a maintenance screen pays for this file and nothing further.
 *
 * @package Action
 * @since 1.10.0
 * @author M.Tuhin <info@codesolz.net>
 */

if ( ! defined( 'CS_RTAFAR_VERSION' ) ) {
	exit;
}

class RTAFAR_Maintenance {

	/**
	 * Register the platform's hooks. Nothing else happens here.
	 */
	public function __construct() {
		// Schema. Cheap after the first run: one autoloaded option read and a
		// version comparison. Admin-side only - a visitor's request must never
		// be the thing that runs dbDelta.
		add_action( 'admin_init', array( $this, 'bfr_maintenance_install' ) );

		// A new site on a multisite network needs its own tables, because the
		// tables are per site, keyed on $wpdb->prefix.
		add_action( 'wp_initialize_site', array( $this, 'bfr_maintenance_new_site' ), 20 );

		// The queue tick. Both drivers fire the same hook, so the runner does
		// not care which one scheduled it.
		add_action( CronDriver::HOOK, array( $this, 'bfr_maintenance_queue_tick' ) );

		// Job handlers, one per module. Registered through the filter rather
		// than a hard-coded map so pro can add its own without this file
		// knowing pro exists.
		add_filter(
			'bfr_maintenance_job_handlers',
			array( '\RealTimeAutoFindReplace\Maintenance\LinkHealth\Scanner', 'register_handler' )
		);

		add_filter(
			'bfr_maintenance_job_handlers',
			array( '\RealTimeAutoFindReplace\Maintenance\NotFound\Retention', 'register_handler' )
		);

		// AJAX rides the free plugin's hardened dispatcher - nonce, per-method
		// capability and a try/catch are already enforced there. Never the pro
		// plugin's, which accepts nopriv requests and checks no capability.
		add_filter(
			'rtafar_allowed_methods',
			array( '\RealTimeAutoFindReplace\Maintenance\Admin\AjaxHandler', 'register' )
		);

		// The dashboard's cached figures are invalidated by issue and scan
		// events, which fire from the queue as well as from the admin - so this
		// is registered outside the is_admin gate below.
		\RealTimeAutoFindReplace\Maintenance\Data\Summary::register();

		// Screens register their own menu entries at priority 15, the way the
		// pro plugin already injects its pages - so RTAFAR_RegisterMenu.php
		// needs no edit. The menu re-order routine keeps slugs it does not
		// know about in their relative order, so the entries still land
		// sensibly.
		if ( is_admin() ) {
			new \RealTimeAutoFindReplace\Maintenance\Admin\DashboardScreen();
			new \RealTimeAutoFindReplace\Maintenance\Admin\Screen();
			new \RealTimeAutoFindReplace\Maintenance\Admin\RedirectsScreen();

			// Missing Media is a tab on Content Health, not a screen: it
			// registers through the same filters pro tabs use and adds no
			// menu entry.
			new \RealTimeAutoFindReplace\Maintenance\Admin\MediaScreen();

			new \RealTimeAutoFindReplace\Maintenance\Admin\CodeInsertScreen();

			// Duplicate lives on the post list rather than a screen of its own.
			new \RealTimeAutoFindReplace\Maintenance\Duplicate\Actions();
		} else {
			// The platform's two front-end hooks. Both answer "nothing to do"
			// from a single autoloaded option before loading anything else.
			//
			// The redirect executor runs at priority 1, ahead of the plugin's
			// output-buffering filter at 10. The 404 capture runs at 999, after
			// everything - including the executor, because a request that gets
			// redirected is not a 404.
			$executor = new \RealTimeAutoFindReplace\Maintenance\Redirects\Executor();
			$executor->register();

			$not_found = new \RealTimeAutoFindReplace\Maintenance\NotFound\Capture();
			$not_found->register();

			// The third front-end hook, and the cheapest: a site with no code
			// snippets answers from one autoloaded option and prints nothing.
			$code = new \RealTimeAutoFindReplace\Maintenance\CodeInsert\Printer();
			$code->register();
		}

		// Tell the admin why the platform is unavailable, rather than letting
		// screens fail silently.
		add_action( 'admin_notices', array( $this, 'bfr_maintenance_schema_notice' ) );
	}

	/**
	 * Create or upgrade the platform's tables when needed.
	 *
	 * @return void
	 */
	public function bfr_maintenance_install() {
		if ( ! class_exists( '\RealTimeAutoFindReplace\Maintenance\Data\Schema\Tables' ) ) {
			return;
		}

		Tables::maybe_install();

		// Queue the daily prune. The job key is the date, so this enqueues one
		// job per day however many admin pages get loaded.
		\RealTimeAutoFindReplace\Maintenance\NotFound\Retention::ensure_scheduled();
	}

	/**
	 * Install the schema on a newly created network site.
	 *
	 * @param mixed $site WP_Site for the new site.
	 * @return void
	 */
	public function bfr_maintenance_new_site( $site ) {
		if ( ! is_multisite() || ! isset( $site->blog_id ) ) {
			return;
		}

		switch_to_blog( (int) $site->blog_id );
		Tables::maybe_install();
		restore_current_blog();
	}

	/**
	 * Drain the queue.
	 *
	 * @return void
	 */
	public function bfr_maintenance_queue_tick() {
		if ( ! class_exists( '\RealTimeAutoFindReplace\Maintenance\Queue\Runner' ) ) {
			return;
		}

		$result = Runner::tick();

		if ( $result['processed'] > 0 || $result['failed'] > 0 ) {
			Logger::log(
				'queue.tick',
				array(
					'processed' => $result['processed'],
					'failed'    => $result['failed'],
					'remaining' => $result['remaining'],
				)
			);
		}
	}

	/**
	 * Say so when the tables could not be created.
	 *
	 * Some hosts refuse CREATE TABLE to the site user. That must not break find
	 * and replace, and it must not present as a screen that silently shows
	 * nothing - so the platform disables itself and explains why.
	 *
	 * @return void
	 */
	public function bfr_maintenance_schema_notice() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$error = Tables::last_error();

		if ( '' === $error ) {
			return;
		}

		printf(
			'<div class="notice notice-warning is-dismissible"><p><strong>%s</strong> %s</p><p><code>%s</code></p></div>',
			esc_html__( 'Better Find and Replace:', 'real-time-auto-find-and-replace' ),
			esc_html__( 'the maintenance tables could not be created, so Content Health, Redirects and the 404 Monitor are unavailable. Find and replace is unaffected.', 'real-time-auto-find-and-replace' ),
			esc_html( $error )
		);
	}
}
