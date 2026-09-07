<?php namespace RealTimeAutoFindReplace\Maintenance\Queue;

/**
 * WP-Cron scheduling. Always available, so it is the fallback.
 *
 * WP-Cron is not a scheduler, it is a "run overdue tasks on the next page
 * view" mechanism. On a quiet site a tick can be late by however long it takes
 * somebody to visit. That is acceptable here because every job is resumable
 * and nothing user-facing waits on the queue - but it is why the runner also
 * schedules the next tick itself rather than relying on a recurring event.
 *
 * @package Maintenance
 * @since 1.10.0
 * @author M.Tuhin <info@codesolz.net>
 */

if ( ! defined( 'CS_RTAFAR_VERSION' ) ) {
	exit;
}

class CronDriver implements QueueDriverInterface {

	/** The single-event hook a tick fires on. */
	const HOOK = 'bfr_maintenance_queue_tick';

	/**
	 * WP-Cron is part of WordPress, so this is only false in exotic setups.
	 *
	 * @return bool
	 */
	public function is_available() {
		return function_exists( 'wp_schedule_single_event' );
	}

	/**
	 * Ask for a tick, unless one is already booked.
	 *
	 * @param int $delay Seconds to wait.
	 * @return bool
	 */
	public function schedule( $delay = 0 ) {
		if ( ! $this->is_available() ) {
			return false;
		}

		if ( $this->is_scheduled() ) {
			return true;
		}

		$when = time() + max( 0, (int) $delay );

		return (bool) wp_schedule_single_event( $when, self::HOOK );
	}

	/**
	 * Is a tick already booked?
	 *
	 * @return bool
	 */
	public function is_scheduled() {
		return function_exists( 'wp_next_scheduled' ) && (bool) wp_next_scheduled( self::HOOK );
	}

	/**
	 * Forget any booked tick.
	 *
	 * @return void
	 */
	public function cancel() {
		if ( function_exists( 'wp_clear_scheduled_hook' ) ) {
			wp_clear_scheduled_hook( self::HOOK );
		}
	}

	/**
	 * Short name, for diagnostics.
	 *
	 * @return string
	 */
	public function name() {
		return 'cron';
	}
}
