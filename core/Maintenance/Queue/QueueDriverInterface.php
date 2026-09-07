<?php namespace RealTimeAutoFindReplace\Maintenance\Queue;

/**
 * How the next queue tick gets scheduled.
 *
 * One of the two places in this platform where an interface earns its keep,
 * because two real implementations exist: WP-Cron, which every site has, and
 * Action Scheduler, which many sites already have through WooCommerce and
 * which handles long queues far better.
 *
 * Action Scheduler is used when it is already present and never bundled.
 * Shipping a copy inside a plugin with this many installs is how two plugins
 * end up fighting over which version of it loads.
 *
 * @package Maintenance
 * @since 1.10.0
 * @author M.Tuhin <info@codesolz.net>
 */

if ( ! defined( 'CS_RTAFAR_VERSION' ) ) {
	exit;
}

interface QueueDriverInterface {

	/**
	 * Can this driver be used on this site right now?
	 *
	 * @return bool
	 */
	public function is_available();

	/**
	 * Ask for a queue tick.
	 *
	 * Implementations must be idempotent: calling this twice before the tick
	 * runs must not produce two ticks.
	 *
	 * @param int $delay Seconds to wait.
	 * @return bool True when a tick is scheduled (by this call or already).
	 */
	public function schedule( $delay = 0 );

	/**
	 * Is a tick already scheduled?
	 *
	 * @return bool
	 */
	public function is_scheduled();

	/**
	 * Cancel any scheduled tick.
	 *
	 * @return void
	 */
	public function cancel();

	/**
	 * Short name, for diagnostics.
	 *
	 * @return string
	 */
	public function name();
}
