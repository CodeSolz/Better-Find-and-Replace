<?php namespace RealTimeAutoFindReplace\admin\options\pages\AdvScreenOptions;

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


class ScOptnAddNewRule {

	/**
	 * screen option
	 *
	 * @return void
	 */
	public static function rtafar_arr_screen_options() {

		$screen = get_current_screen();

		$screen->add_help_tab(
			array(
				'id'      => 'rtafar_support_tab',
				'title'   => __( 'Help &amp; Support', 'real-time-auto-find-and-replace' ),
				'content' =>
					'<h2>' . __( 'Help &amp; Support', 'real-time-auto-find-and-replace' ) . '</h2>' .
					'<p>' . sprintf(
						/* translators: %s: Documentation URL */
						__( 'If you need assistance with understanding or using Better Find and Replace, please read our <a href="%s" target="_blank" >documentation</a>. It offers a variety of resources, including code snippets, written & video tutorials, and much more.', 'real-time-auto-find-and-replace' ),
						'https://docs.codesolz.net/better-find-and-replace/'
					) . '</p>' .
					'<p>' . sprintf(
						/* translators: %s: Forum URL */
						__( 'For additional support with the BFR, please visit WordPress default <a href="%1$s" target="_blank" >community forum </a> or our <a href="%2$s" target="_blank">dedicated community forum</a>. If you need assistance with premium extensions purchased from <a href="%3$s" target="_blank">CodeSolz.net</a>, kindly submit a support request through live chat on <a href="%3$s" target="_blank">CodeSolz.net</a> website or via support email.', 'real-time-auto-find-and-replace' ),
						'https://wordpress.org/support/plugin/real-time-auto-find-and-replace/',
						'https://codesolz.net/forum/viewforum.php?f=3&utm_source=helptab&utm_medium=product',
						'https://codesolz.net/our-products/wordpress-plugin/real-time-auto-find-and-replace/?utm_source=helptab&utm_medium=product&utm_content=support&utm_campaign=bfrPlugin'
					) . '</p>' .
					'<p>' . __( 'We encourage you to review the documentation and tutorials before seeking assistance.', 'real-time-auto-find-and-replace' ) . '</p>' .
					'<p> <a target="_blank" href="https://codesolz.net/our-products/wordpress-plugin/real-time-auto-find-and-replace/?utm_source=helptab&utm_medium=product&utm_content=liveSupport&utm_campaign=bfrPlugin" class="button button-primary">' . __( 'Live Support', 'real-time-auto-find-and-replace' ) . '</a> <a target="_blank" href="https://codesolz.net/forum/viewforum.php?f=3" class="button ">' . __( 'BFR Dedicated Forum', 'real-time-auto-find-and-replace' ) . '</a> <a target="_blank" href="https://wordpress.org/support/plugin/real-time-auto-find-and-replace/" class="button">' . __( 'Community Forum', 'real-time-auto-find-and-replace' ) . '</a></p>',
			)
		);

		$screen->add_help_tab(
			array(
				'id'      => 'rtafar_bugs_tab',
				'title'   => __( 'Found a bug?', 'real-time-auto-find-and-replace' ),
				'content' =>
					'<h2>' . __( 'Found a bug?', 'real-time-auto-find-and-replace' ) . '</h2>' .
					/* translators: 1: GitHub issues URL 2: GitHub contribution guide URL 3: System status report URL */
					'<p>' . sprintf( __('If you have discovered a bug or issue with the plugin, we wouldd appreciate your help in improving it. You can create a ticket via GitHub Issues to report the problem, and we will look into it as soon as possible. Please include any relevant details or steps to reproduce the issue here: <a href="%1$s" target="_blank">GitHub Issues</a>.Thank you for your support!', 'real-time-auto-find-and-replace' ), 'https://github.com/CodeSolz/Better-Find-and-Replace/issues?state=open' ) . '</p>' .
					'<p><a target="_blank" href="https://github.com/CodeSolz/Better-Find-and-Replace/issues/new?assignees=&labels=&template=1-bug-report.yml" class="button button-primary">' . __( 'Report a bug', 'real-time-auto-find-and-replace' ) . '</a></p>',

			)
		);

		$screen->set_help_sidebar(
			'<p><strong>' . __( 'For more information:', 'real-time-auto-find-and-replace' ) . '</strong></p>' .
			'<p><a href="https://codesolz.net/our-products/wordpress-plugin/real-time-auto-find-and-replace/?utm_source=helptab&utm_medium=product&utm_content=about&utm_campaign=bfrPlugin" target="_blank">' . __( 'About Better Find & Replace', 'real-time-auto-find-and-replace' ) . '</a></p>' .
			'<p><a href="https://wordpress.org/plugins/real-time-auto-find-and-replace/" target="_blank">' . __( 'WordPress.org project', 'real-time-auto-find-and-replace' ) . '</a></p>' .
			'<p><a href="https://github.com/CodeSolz/Better-Find-and-Replace" target="_blank">' . __( 'GitHub project', 'real-time-auto-find-and-replace' ) . '</a></p>' .
			'<p><a href="https://wordpress.org/plugins/real-time-auto-find-and-replace/#developers" target="_blank">' . __( 'Changelog', 'real-time-auto-find-and-replace' ) . '</a></p>' .
			'<p><a href="https://docs.codesolz.net/better-find-and-replace/" target="_blank">' . __( 'Documentation', 'real-time-auto-find-and-replace' ) . '</a></p>' .
			'<p><a href="https://codesolz-plugins.dev/wp-admin/plugin-install.php?s=codesolz&tab=search&type=author" target="_blank">' . __( 'Useful Free Plugins', 'real-time-auto-find-and-replace' ) . '</a></p>' 
		);	
	}
	
}