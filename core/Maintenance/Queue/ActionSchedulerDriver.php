<?php namespace RealTimeAutoFindReplace\Maintenance\Queue;

/**
 * Action Scheduler, used only when the site already has it.
 *
 * WooCommerce and several other widely-installed plugins ship it, and where it
 * exists it is strictly better than WP-Cron for this workload: it has its own
 * runner, it survives a quiet site, and it does not lose work when a request
 * times out mid-tick.
 *
 * We never bundle it. A free plugin with this many installs that ships its own
 * copy of a library other plugins also ship is how version conflicts start.
 *
 * @package Maintenance
 * @since 1.10.0
 * @author M.Tuhin <info@codesolz.net>
 */

if ( ! defined( 'CS_RTAFAR_VERSION' ) ) {
	exit;
}

class ActionSchedulerDriver implements QueueDriverInterface {

	/** The hook a tick fires on. Shared with CronDriver, so the runner is unchanged. */
	const HOOK = CronDriver::HOOK;

	/** Action Scheduler group, so a site owner can see whose actions these are. */
	const GROUP = 'bfr-maintenance';

	/**
	 * Only usable when the site already loaded Action Scheduler.
	 *
	 * @return bool
	 */
	public function is_available() {
		return function_exists( 'as_schedule_single_action' )
			&& function_exists( 'as_next_scheduled_action' )
			&& function_exists( 'as_unschedule_all_actions' );
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

		$delay = max( 0, (int) $delay );

		if ( 0 === $delay && function_exists( 'as_enqueue_async_action' ) ) {
			as_enqueue_async_action( self::HOOK, array(), self::GROUP );

			return true;
		}

		as_schedule_single_action( time() + $delay, self::HOOK, array(), self::GROUP );

		return true;
	}

	/**
	 * Is a tick already booked?
	 *
	 * @return bool
	 */
	public function is_scheduled() {
		return $this->is_available() && false !== as_next_scheduled_action( self::HOOK, null, self::GROUP );
	}

	/**
	 * Forget any booked tick.
	 *
	 * @return void
	 */
	public function cancel() {
		if ( $this->is_available() ) {
			as_unschedule_all_actions( self::HOOK, array(), self::GROUP );
		}
	}

	/**
	 * Short name, for diagnostics.
	 *
	 * @return string
	 */
	public function name() {
		return 'action-scheduler';
	}
}
