<?php namespace RealTimeAutoFindReplace\Maintenance\NotFound;

use RealTimeAutoFindReplace\Maintenance\Data\ActivityLog;
use RealTimeAutoFindReplace\Maintenance\Data\IssueRepository;
use RealTimeAutoFindReplace\Maintenance\Data\NotFoundRepository;
use RealTimeAutoFindReplace\Maintenance\Data\Schema\Tables;
use RealTimeAutoFindReplace\Maintenance\Queue\JobRepository;
use RealTimeAutoFindReplace\Maintenance\Support\Logger;

/**
 * Keeps the platform's logs from growing without limit.
 *
 * A queue job rather than a cron callback of its own, so it inherits the batch
 * bounds, the claim and the retry behaviour the runner already provides - and
 * so a site whose cron never fires simply prunes late rather than not at all.
 *
 * Everything it deletes is something nobody acted on: unhandled 404s past the
 * retention window, resolved issues past theirs, and old activity rows. A 404
 * the user ignored or redirected is a decision and is kept, because deleting it
 * would make the same dead URL come back as new.
 *
 * @package Maintenance
 * @since 1.10.0
 * @author M.Tuhin <info@codesolz.net>
 */

if ( ! defined( 'CS_RTAFAR_VERSION' ) ) {
	exit;
}

class Retention {

	/** The queue job type. */
	const JOB_TYPE = 'maintenance_prune';

	/** How often the job re-queues itself, in seconds. */
	const INTERVAL = DAY_IN_SECONDS;

	/** Days of activity history kept. */
	const ACTIVITY_DAYS = 90;

	/**
	 * Register the job handler.
	 *
	 * @param array $handlers Existing handlers.
	 * @return array
	 */
	public static function register_handler( $handlers ) {
		if ( ! is_array( $handlers ) ) {
			$handlers = array();
		}

		$handlers[ self::JOB_TYPE ] = array( __CLASS__, 'handle' );

		return $handlers;
	}

	/** Records the day a prune was last queued, so this costs nothing to repeat. */
	const QUEUED_OPTION = 'bfr_maintenance_pruned_on';

	/**
	 * Make sure a prune is queued, at most once a day.
	 *
	 * Called from admin_init, so "at most once a day" has to mean it, and the
	 * job key alone is not enough: a duplicate enqueue is rejected by the unique
	 * index, but rejection still costs an INSERT on every single admin page
	 * load. An autoloaded date option answers the common case for free.
	 *
	 * The date is recorded when the job is queued rather than when it finishes.
	 * A prune that fails simply waits for tomorrow, which is the right cost for
	 * housekeeping.
	 *
	 * @return bool True when a job was queued by this call.
	 */
	public static function ensure_scheduled() {
		if ( ! function_exists( 'get_option' ) || ! Tables::installed() ) {
			return false;
		}

		$today = gmdate( 'Y-m-d' );

		if ( get_option( self::QUEUED_OPTION, '' ) === $today ) {
			return false;
		}

		update_option( self::QUEUED_OPTION, $today, true );

		$result = JobRepository::enqueue(
			self::JOB_TYPE,
			array( 'day' => $today ),
			array( 'delay' => 0 )
		);

		return (bool) $result['queued'];
	}

	/**
	 * Prune everything that is past its window.
	 *
	 * @param array $payload Job payload, unused.
	 * @return void
	 */
	public static function handle( array $payload = array() ) {
		unset( $payload );

		if ( ! Tables::installed() ) {
			return;
		}

		$not_found = NotFoundRepository::prune();
		$issues    = IssueRepository::prune_resolved( gmdate( 'Y-m-d H:i:s', time() - ( 90 * DAY_IN_SECONDS ) ) );
		$activity  = ActivityLog::prune( gmdate( 'Y-m-d H:i:s', time() - ( self::ACTIVITY_DAYS * DAY_IN_SECONDS ) ) );

		if ( $not_found > 0 || $issues > 0 || $activity > 0 ) {
			Logger::log(
				'retention.pruned',
				array(
					'not_found' => $not_found,
					'issues'    => $issues,
					'activity'  => $activity,
				)
			);
		}
	}
}
