<?php namespace RealTimeAutoFindReplace\Maintenance\Queue;

use RealTimeAutoFindReplace\Maintenance\Data\Schema\Tables;
use RealTimeAutoFindReplace\Maintenance\Support\Logger;

/**
 * Drains the queue, a bounded batch at a time.
 *
 * Two budgets, whichever runs out first: a job count and a wall-clock limit.
 * The clock matters more - a scan batch that hits max_execution_time takes its
 * claimed jobs down with it, and the whole point of claiming is that those jobs
 * come back. Stopping early and rescheduling costs one extra request; being
 * killed costs ten minutes of stale claims.
 *
 * A handler is registered per job type through the bfr_maintenance_job_handlers
 * filter. Anything a handler throws is caught here: one bad job must not take
 * the batch, and it must not take the queue.
 *
 * @package Maintenance
 * @since 1.10.0
 * @author M.Tuhin <info@codesolz.net>
 */

if ( ! defined( 'CS_RTAFAR_VERSION' ) ) {
	exit;
}

class Runner {

	/** Jobs per tick, before filtering. */
	const DEFAULT_BATCH = 20;

	/** Seconds of wall clock per tick, before filtering. */
	const DEFAULT_TIME_BUDGET = 15;

	/**
	 * Guards against a tick re-entering itself within one request.
	 *
	 * @var bool
	 */
	private static $running = false;

	/**
	 * Run one tick.
	 *
	 * @return array {
	 *     @type int  $processed Jobs that ran.
	 *     @type int  $failed    Jobs that threw.
	 *     @type int  $remaining Jobs still waiting afterwards.
	 *     @type bool $rescheduled Whether another tick was requested.
	 * }
	 */
	public static function tick() {
		$result = array(
			'processed'   => 0,
			'failed'      => 0,
			'remaining'   => 0,
			'rescheduled' => false,
		);

		if ( self::$running || ! Tables::installed() ) {
			return $result;
		}

		self::$running = true;

		try {
			JobRepository::reclaim_stale();

			$batch  = self::batch_size();
			$budget = self::time_budget();
			$start  = microtime( true );

			$handlers = self::handlers();
			$jobs     = JobRepository::claim( $batch );

			foreach ( $jobs as $job ) {
				if ( ( microtime( true ) - $start ) >= $budget ) {
					// Out of time. Hand back what we have not started so the
					// next tick can claim it immediately rather than waiting
					// for the stale-claim timeout. release(), not fail(): this
					// job has not been attempted, and burning a retry on it
					// would eventually park work that never ran.
					JobRepository::release( $job );
					continue;
				}

				$type = isset( $job->job_type ) ? (string) $job->job_type : '';

				if ( '' === $type || ! isset( $handlers[ $type ] ) || ! is_callable( $handlers[ $type ] ) ) {
					JobRepository::fail( $job, 'no_handler' );
					++$result['failed'];
					continue;
				}

				$payload = array();

				if ( isset( $job->payload ) && '' !== $job->payload ) {
					$decoded = json_decode( (string) $job->payload, true );
					$payload = is_array( $decoded ) ? $decoded : array();
				}

				try {
					call_user_func( $handlers[ $type ], $payload, $job );
					JobRepository::complete( $job->id );
					++$result['processed'];
				} catch ( \Throwable $e ) {
					// A handler that throws must not take the batch with it.
					JobRepository::fail( $job, $e->getMessage() );
					++$result['failed'];

					Logger::log(
						'queue.job_failed',
						array(
							'type' => $type,
							'id'   => isset( $job->id ) ? (int) $job->id : 0,
						)
					);
				}
			}

			$result['remaining'] = JobRepository::pending_count();

			if ( $result['remaining'] > 0 ) {
				$result['rescheduled'] = self::driver()->schedule( self::next_delay() );
			}
		} catch ( \Throwable $e ) {
			// Never let a queue tick surface as a fatal on somebody's admin
			// page. The jobs are claimed, so they come back.
			Logger::log( 'queue.tick_failed', array( 'error' => $e->getMessage() ) );
		}

		self::$running = false;

		return $result;
	}

	/**
	 * Ask for a tick.
	 *
	 * @param int $delay Seconds to wait.
	 * @return bool
	 */
	public static function schedule( $delay = 0 ) {
		return self::driver()->schedule( $delay );
	}

	/**
	 * The driver this site will use.
	 *
	 * Action Scheduler when the site already has it, WP-Cron otherwise.
	 *
	 * @return QueueDriverInterface
	 */
	public static function driver() {
		static $driver = null;

		if ( null !== $driver ) {
			return $driver;
		}

		$action_scheduler = new ActionSchedulerDriver();
		$driver           = $action_scheduler->is_available() ? $action_scheduler : new CronDriver();

		if ( function_exists( 'apply_filters' ) ) {
			/**
			 * Filter the queue driver.
			 *
			 * @param QueueDriverInterface $driver Chosen driver.
			 */
			$filtered = apply_filters( 'bfr_maintenance_queue_driver', $driver );

			if ( $filtered instanceof QueueDriverInterface && $filtered->is_available() ) {
				$driver = $filtered;
			}
		}

		return $driver;
	}

	/**
	 * Job type => callable.
	 *
	 * @return array
	 */
	public static function handlers() {
		if ( ! function_exists( 'apply_filters' ) ) {
			return array();
		}

		/**
		 * Register queue job handlers.
		 *
		 * @param array $handlers job type => callable( array $payload, object $job ).
		 */
		$handlers = apply_filters( 'bfr_maintenance_job_handlers', array() );

		return is_array( $handlers ) ? $handlers : array();
	}

	/**
	 * Jobs per tick.
	 *
	 * @return int
	 */
	private static function batch_size() {
		$size = self::DEFAULT_BATCH;

		if ( function_exists( 'apply_filters' ) ) {
			/**
			 * Filter how many jobs one tick claims.
			 *
			 * @param int $size Jobs per tick.
			 */
			$size = (int) apply_filters( 'bfr_maintenance_queue_batch', $size );
		}

		return max( 1, min( 50, $size ) );
	}

	/**
	 * Wall-clock seconds per tick.
	 *
	 * Capped against the host's own limit where PHP reports one, because a
	 * budget larger than max_execution_time is not a budget.
	 *
	 * @return int
	 */
	private static function time_budget() {
		$budget = self::DEFAULT_TIME_BUDGET;

		if ( function_exists( 'apply_filters' ) ) {
			/**
			 * Filter the per-tick time budget in seconds.
			 *
			 * @param int $budget Seconds.
			 */
			$budget = (int) apply_filters( 'bfr_maintenance_queue_time_budget', $budget );
		}

		$budget = max( 5, min( 120, $budget ) );

		$limit = (int) ini_get( 'max_execution_time' );

		if ( $limit > 0 && $budget > ( $limit - 5 ) ) {
			$budget = max( 5, $limit - 5 );
		}

		return $budget;
	}

	/**
	 * How long before the next tick.
	 *
	 * Zero would be ideal, but WP-Cron will not run two single events for the
	 * same hook in the same second, so a small gap keeps the chain moving.
	 *
	 * @return int
	 */
	private static function next_delay() {
		$delay = 1;

		if ( function_exists( 'apply_filters' ) ) {
			/**
			 * Filter the gap between queue ticks, in seconds.
			 *
			 * @param int $delay Seconds.
			 */
			$delay = (int) apply_filters( 'bfr_maintenance_queue_next_delay', $delay );
		}

		return max( 0, min( 300, $delay ) );
	}
}
