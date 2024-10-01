<?php namespace RealTimeAutoFindReplace\admin\notices;

/**
 * Admin Notice
 *
 * @package Notices
 * @since 1.0.0
 * @author M.Tuhin <tuhin@codesolz.net>
 */

if ( ! defined( 'CS_RTAFAR_VERSION' ) ) {
	exit;
}

use RealTimeAutoFindReplace\lib\Util;
use RealTimeAutoFindReplace\admin\builders\NoticeBuilder;


class RtafarNotices {

	public static function init() {
		$notice = NoticeBuilder::get_instance();
		self::activated( $notice );
		self::feedback( $notice );
	}

	/**
	 * Activated Notice
	 *
	 * @return String
	 */
	public static function activated( $notice ) {
		$message       = __( 'Thank you for choosing us. Let\'s %1$s set some find & replace rules. %2$s', 'real-time-auto-find-and-replace' );
		$register_link = admin_url( 'admin.php?page=cs-add-replacement-rule' );
		$default_link  = site_url( '' );
		$message       = sprintf(
			$message,
			'<a href="' . $register_link . '"><strong>',
			'</strong></a>',
			'<a target="_blank" href="' . $default_link . '"><strong>',
			'</strong></a>'
		);
		$notice->info( $message, 'Activated' );
	}

	/**
	 * Feedback
	 *
	 * @return void
	 */
	public static function feedback( $notice ) {
		// check installed time
		$installedOn = get_option( 'rtafar_plugin_install_date' );
		if ( empty( $installedOn ) ) {
			add_option( 'rtafar_plugin_install_date', date( 'Y-m-d H:i:s' ) );
			return false;
		}

		$date1 = new \DateTime( \date( 'Y-m-d', \strtotime( $installedOn ) ) );
		$date2 = new \DateTime( \date( 'Y-m-d' ) );
		if ( $date1->diff( $date2 )->days < 14 ) {
			return false;
		}
		$timeDiff    = \human_time_diff( \strtotime( $installedOn ), current_time( 'U' ) );
		$message     = __(
			'You are using the plugin quite a while! If you are enjoying it, %3$splease consider giving us a 5-star (%5$s) rating.%4$s 
			%1$s Your valuable review %2$s will %1$s inspire us %2$s to make it more better.',
			'real-time-auto-find-and-replace'
		);
		$review_link = 'https://login.wordpress.org/?redirect_to=https%3A%2F%2Fwordpress.org%2Fsupport%2Fplugin%2Freal-time-auto-find-and-replace%2Freviews%2F%23new-post&locale=en_US';
		$message     = sprintf(
			$message,
			'<b>',
			'</b>',
			'<a href="' . $review_link . '" target="_blank"><strong>',
			'</strong></a>',
			'<span class="dashicons dashicons-star-filled">
			</span><span class="dashicons dashicons-star-filled">
			</span><span class="dashicons dashicons-star-filled">
			</span><span class="dashicons dashicons-star-filled">
			</span><span class="dashicons dashicons-star-filled"></span>'
		);

		$message .= '<div class="cs-notice-action-btn-holder"><button class="button-primary bfar-review-now">Let\'s do it now! </button> '
		. '<button type="button" class="button-secondary bfar-review-never">I\'ve already done it!</button> '
			. '<button type="button" class="button-secondary bfar-review-later">I\'ll do it later!</button> '
			. '<button type="button" class="button-secondary bfar-review-never">Please don\'t bother me again :(</button> </div>';

		$notice->info( $message, 'Feedback' );
	}
}
