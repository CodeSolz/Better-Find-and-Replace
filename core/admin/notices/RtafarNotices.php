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

if ( ! \class_exists( 'RtafarNotices' ) ) {

	class RtafarNotices {

		/**
		 * Activated Notice
		 *
		 * @return String
		 */
		public static function activated() {
			$notice        = NoticeBuilder::get_instance();
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
		public static function feedback() {
			$notice        = NoticeBuilder::get_instance();
			$message       = __( 'You are using our plugin more then 2 weeks. If you are enjoying it, would you mind to give us a 5 stars (%s) review?
								It will inspire us to make it more better.', 'real-time-auto-find-and-replace' );
			$register_link = admin_url( 'admin.php?page=cs-igt-test-url-slug-settings' );
			$default_link  = site_url( '' );
			$message       = sprintf(
				$message,
				'<span class="dashicons dashicons-star-filled">
				</span><span class="dashicons dashicons-star-filled">
				</span><span class="dashicons dashicons-star-filled"></span>
				<span class="dashicons dashicons-star-filled"></span>
				<span class="dashicons dashicons-star-filled"></span>',
				'<a href="' . $register_link . '"><strong>',
				'</strong></a>',
				'<a target="_blank" href="' . $default_link . '"><strong>',
				'</strong></a>'
			);
			$notice->info( $message, 'Feedback' );
		}


	}

}
