<?php namespace RealTimeAutoFindReplace\admin\options\pages\AdvScreenOptions;

use RealTimeAutoFindReplace\lib\Util;

/**
 * Class: Screen options
 *
 * @package Options
 * @since 1.3.8
 * @author M.Tuhin <info@codesolz.net>
 */

if ( ! defined( 'CS_RTAFAR_VERSION' ) ) {
	die();
}


class ScOptnAllMaskRules {

	private static $amr_per_page_optn_id = 'bfrp_all_masking_list_per_page';

	/**
	 * screen option
	 *
	 * @return void
	 */
	public static function rtafar_screen_options() {
		\add_screen_option(
			'per_page',
			array(
				'label'   => __( 'Number of items per page : ', 'real-time-auto-find-and-replace' ),
				'default' => 10,
				'option'  => self::$amr_per_page_optn_id,
			)
		);

		$screen = \get_current_screen();
		if ( self::help_tabs() ) {
			// foreach ( self::help_tabs() as $tab ) {
			// $tab = (object) $tab;
			// $screen->add_help_tab(
			// array(
			// 'id'       => $tab->id,
			// 'title'    => $tab->title,
			// 'content'  => $tab->content,
			// 'callback' => $tab->callback,
			// 'priority' => $tab->priority,
			// )
			// );
			// }
		}
		$screen->set_help_sidebar( self::amr_help_sidebar_content() );
	}


	/**
	 * Set amr per page
	 *
	 * @param [type] $status
	 * @param [type] $option
	 * @param [type] $value
	 * @return void
	 */
	public static function rtafar_set_amr_per_page( $status, $option, $value ) {
		return $value;
	}

	/**
	 * Get amr per page item number
	 *
	 * @return void
	 */
	public static function rtafar_get_amr_per_page() {
		$current_user_id = Util::bfar_get_current_user_id();
		if ( $current_user_id ) {
			return \get_user_meta( $current_user_id, self::$amr_per_page_optn_id, true );
			return false;
		}

		return false;
	}

	/**
	 * Help tabs
	 *
	 * @return void
	 */
	public static function help_tabs() {
		return array(
			array(
				'id'       => 'overview',
				'title'    => __( 'Overview', 'real-time-auto-find-and-replace' ),
				'content'  => '',
				'callback' => array( __class__, 'amr_overview' ),
				'priority' => 1,
			),
			array(
				'id'       => 'screen_content',
				'title'    => __( 'Screen Content', 'real-time-auto-find-and-replace' ),
				'content'  => '',
				'callback' => array( __class__, 'amr_screen_content' ),
				'priority' => 2,
			),
			array(
				'id'       => 'available_actions',
				'title'    => __( 'Available Actions', 'real-time-auto-find-and-replace' ),
				'content'  => '',
				'callback' => array( __class__, 'amr_available_actions' ),
				'priority' => 3,
			),
			array(
				'id'       => 'bulk_actions',
				'title'    => __( 'Bulk Actions', 'real-time-auto-find-and-replace' ),
				'content'  => '',
				'callback' => array( __class__, 'amr_bulk_actions' ),
				'priority' => 4,
			),
		);
	}

	/**
	 * Overview
	 *
	 * @return void
	 */
	public static function amr_overview() {
		echo \sprintf(
			__( '%1$s This screen provides the  the functionalities to add new rule. You can add real-time rules as well as some specific rules in Database. After installing the pro version, these muted pro features will be activated automatically. %2$s', 'real-time-auto-find-and-replace' ),
			'<p>',
			'</p>'
		);
	}


	/**
	 * available actions
	 *
	 * @return void
	 */
	public static function amr_available_actions() {
		?>
			<p>
				<?php _e( 'Hovering over a row in the item list will display action links that allow you to manage your item. You can perform the following actions:', 'real-time-auto-find-and-replace' ); ?>
			</p>
			<ul>
				<li>
					<?php echo sprintf( __( '%1$s Edit %2$s takes you to the editing screen for that rule. ', 'real-time-auto-find-and-replace' ), '<strong>', '</strong>' ); ?>
				</li>
			</ul>
		<?php
		echo do_action( 'bfar_amr_available_actions_content' );
	}

	/**
	 * Screen content tab
	 *
	 * @return void
	 */
	public static function amr_screen_content() {
		ob_start();
		?>
			<p>
				<?php _e( 'You can customize the display of this screen’s contents in a number of ways:', 'real-time-auto-find-and-replace' ); ?>
			</p>
			<ul>
				<li>
					<?php _e( 'You can decide how many item to list per screen using the Screen Options tab.', 'real-time-auto-find-and-replace' ); ?>
				</li>
			</ul>
			<?php
			$html = ob_get_clean();

			echo $html;
	}

	public static function amr_bulk_actions() {
		?>
			<p>
				<?php _e( 'You can also delete one or multiple item(s) at once. Select the item(s) you want to act on using the checkboxes, then select the action you want to take from the Bulk actions menu and click Apply.', 'real-time-auto-find-and-replace' ); ?>
			</p>
		
		<?php
	}

	/**
	 * Help Sidebar Content
	 *
	 * @return void
	 */
	public static function amr_help_sidebar_content() {
		ob_start();
		?>
			<p><strong><?php _e( 'For more information: ', 'real-time-auto-find-and-replace' ); ?></strong></p>
			<p>
				<?php _e( 'Looking for features details? Check plugin\'s ', 'real-time-auto-find-and-replace' ); ?>
				<a href="https://docs.codesolz.net/better-find-and-replace/" target="_blank"><?php _e( 'Documentation', 'real-time-auto-find-and-replace' ); ?></a></p>
			<p><a href="https://codesolz.net/our-products/wordpress-plugin/real-time-auto-find-and-replace/" target="_blank"><?php _e( 'Support', 'real-time-auto-find-and-replace' ); ?></a></p>					
		<?php
		$html = ob_get_clean();

		return $html;
	}


}
