<?php namespace RealTimeAutoFindReplace\Maintenance\Admin;

use RealTimeAutoFindReplace\admin\builders\AdminPageBuilder;
use RealTimeAutoFindReplace\Maintenance\Data\IssueQuery;
use RealTimeAutoFindReplace\Maintenance\Admin\LockedTab;
use RealTimeAutoFindReplace\Maintenance\Data\ScanRunRepository;
use RealTimeAutoFindReplace\Maintenance\Data\Schema\Tables;
use RealTimeAutoFindReplace\Maintenance\LinkHealth\Scanner;
use RealTimeAutoFindReplace\Maintenance\Support\Capabilities;
use RealTimeAutoFindReplace\Maintenance\Support\Entitlements;

/**
 * The Content Health screen.
 *
 * Rendered through the plugin's own AdminPageBuilder, so it is the same panel,
 * heading, body and footer as Replace in Database and every other screen here -
 * a new page that invents its own markup reads as a different product bolted on
 * to this one.
 *
 * The builder's slots are used as intended rather than worked around:
 *   header_actions - the scan control, on the title's own row
 *   content        - the tab strip, a module's own controls, and the list
 *   before_footer  - the honest note about what Pro adds
 *
 * It also enqueues rtafar-admin-style.min.css itself. That stylesheet is what
 * gives .panel and .panel-heading their appearance, and it is normally attached
 * by Scripts_Settings::load_admin_settings_scripts(), which only runs for pages
 * RTAFAR_RegisterMenu registered. This screen registers its own submenu, so it
 * has to bring the house styles with it or the house markup renders bare.
 *
 * Registers at admin_menu priority 15, the same technique the pro plugin uses,
 * so the shipped RTAFAR_RegisterMenu.php needs no edit.
 *
 * @package Maintenance
 * @since 1.10.0
 * @author M.Tuhin <info@codesolz.net>
 */

if ( ! defined( 'CS_RTAFAR_VERSION' ) ) {
	exit;
}

class Screen {

	/** The page slug. */
	const SLUG = 'cs-bfar-content-health';

	/**
	 * The hook suffix, so assets load on this screen only.
	 *
	 * @var string
	 */
	private $hook = '';

	/**
	 * Register the menu hook. Nothing else happens here.
	 */
	public function __construct() {
		add_action( 'admin_menu', array( $this, 'register' ), 15 );
	}

	/**
	 * Add the submenu.
	 *
	 * @return void
	 */
	public function register() {
		if ( ! defined( 'CS_RTAFAR_PLUGIN_IDENTIFIER' ) ) {
			return;
		}

		$this->hook = add_submenu_page(
			CS_RTAFAR_PLUGIN_IDENTIFIER,
			__( 'Content Health', 'real-time-auto-find-and-replace' ),
			__( 'Content Health', 'real-time-auto-find-and-replace' ),
			Capabilities::for_module( 'content_health' ),
			self::SLUG,
			array( $this, 'render' )
		);

		if ( $this->hook ) {
			add_action( 'load-' . $this->hook, array( $this, 'on_load' ) );
		}
	}

	/**
	 * Screen setup, before any output.
	 *
	 * @return void
	 */
	public function on_load() {
		add_action( 'admin_enqueue_scripts', array( $this, 'assets' ) );

		// This is a working surface - a list being triaged, a form being filled
		// in. Notices do not belong in the middle of it. See ScreenNotices.
		ScreenNotices::suppress();
	}

	/**
	 * Load this screen's assets, and only on this screen.
	 *
	 * @param string $hook Current admin page hook suffix.
	 * @return void
	 */
	public function assets( $hook ) {
		if ( $hook !== $this->hook ) {
			return;
		}

		// The house panel styles. Same handle Scripts_Settings uses, so if both
		// ever run on one screen WordPress registers it once.
		wp_enqueue_style(
			'wapg',
			CS_RTAFAR_PLUGIN_ASSET_URI . 'css/rtafar-admin-style.min.css',
			array(),
			CS_RTAFAR_VERSION
		);

		wp_enqueue_style(
			'bfr-maintenance',
			CS_RTAFAR_PLUGIN_ASSET_URI . 'css/maintenance.css',
			array( 'wapg' ),
			CS_RTAFAR_VERSION
		);

		// The house dialog library, bundled rather than fetched: the row
		// actions here change somebody's published content, and a browser
		// confirm() box is not a good enough place to explain that.
		wp_enqueue_script(
			'sweetalert2',
			CS_RTAFAR_PLUGIN_ASSET_URI . 'plugins/sweetalert/dist/sweetalert2.all.min.js',
			array(),
			'11.21.2',
			true
		);

		wp_enqueue_script(
			'bfr-maintenance',
			CS_RTAFAR_PLUGIN_ASSET_URI . 'js/maintenance.js',
			array( 'jquery', 'sweetalert2' ),
			CS_RTAFAR_VERSION,
			true
		);

		wp_localize_script(
			'bfr-maintenance',
			'bfrMaintenance',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'token'   => wp_create_nonce( SECURE_AUTH_SALT ),
				'i18n'    => self::script_strings(),
			)
		);
	}

	/**
	 * Everything the script says out loud.
	 *
	 * Kept in one place, and in one voice: each dialog says what will change,
	 * where, and what happens to the rest of the page - because every one of
	 * these actions edits content somebody published.
	 *
	 * @return array
	 */
	private static function script_strings() {
		return array(
			'working'        => __( 'Working...', 'real-time-auto-find-and-replace' ),
			'failed'         => __( 'Something went wrong. Please try again.', 'real-time-auto-find-and-replace' ),
			'cancel'         => __( 'Cancel', 'real-time-auto-find-and-replace' ),

			// The scan.
			'scanStarted'    => __( 'Scan started. It runs in the background - you can carry on working, or leave this page and come back.', 'real-time-auto-find-and-replace' ),
			'scanFinished'   => __( 'Scan finished. Refreshing the list...', 'real-time-auto-find-and-replace' ),
			/* translators: 1: URLs checked so far, 2: URLs to check in total */
			'scanProgress'   => __( '%1$s of %2$s URLs scanned', 'real-time-auto-find-and-replace' ),

			// Row actions.
			'ignoreTitle'    => __( 'Ignore this link?', 'real-time-auto-find-and-replace' ),
			'ignoreText'     => __( 'It moves to the Ignored list and future scans will not report it again. Nothing on your site changes.', 'real-time-auto-find-and-replace' ),
			'ignoreConfirm'  => __( 'Ignore it', 'real-time-auto-find-and-replace' ),

			'unignoreTitle'  => __( 'Put this link back on the list?', 'real-time-auto-find-and-replace' ),
			'unignoreText'   => __( 'It goes back to Needs attention and future scans will report it again.', 'real-time-auto-find-and-replace' ),
			'unignoreConfirm' => __( 'Put it back', 'real-time-auto-find-and-replace' ),

			'recheckTitle'   => __( 'Check this link again?', 'real-time-auto-find-and-replace' ),
			'recheckText'    => __( 'The URL is checked again and the row is updated with what comes back. Nothing on your site is changed.', 'real-time-auto-find-and-replace' ),
			'recheckConfirm' => __( 'Check it now', 'real-time-auto-find-and-replace' ),

			'unlinkTitle'    => __( 'Remove this link?', 'real-time-auto-find-and-replace' ),
			'unlinkText'     => __( 'The link is removed and its text stays exactly where it is. You will see what changes before anything is written.', 'real-time-auto-find-and-replace' ),
			'unlinkConfirm'  => __( 'Remove the link', 'real-time-auto-find-and-replace' ),

			// Replace URL.
			'replaceTitle'   => __( 'Replace this URL', 'real-time-auto-find-and-replace' ),
			'replaceCurrent' => __( 'Broken URL', 'real-time-auto-find-and-replace' ),
			'replaceLabel'   => __( 'Replace it with', 'real-time-auto-find-and-replace' ),
			'replaceHint'    => __( 'A full address, or a path on this site such as /about-us/. You will see what changes before anything is written.', 'real-time-auto-find-and-replace' ),
			'replaceConfirm' => __( 'Preview the change', 'real-time-auto-find-and-replace' ),
			'replaceEmpty'   => __( 'Enter the URL this link should point at.', 'real-time-auto-find-and-replace' ),
			'replaceInvalid' => __( 'That does not look like a URL or a path. Try https://example.com/page or /page/.', 'real-time-auto-find-and-replace' ),
			'replaceSame'    => __( 'That is the URL that is already there.', 'real-time-auto-find-and-replace' ),

			// The preview, shown before anything is written.
			'previewTitle'   => __( 'Apply this change?', 'real-time-auto-find-and-replace' ),
			'previewFrom'    => __( 'Now', 'real-time-auto-find-and-replace' ),
			'previewTo'      => __( 'After', 'real-time-auto-find-and-replace' ),
			'previewApply'   => __( 'Apply the change', 'real-time-auto-find-and-replace' ),
			'previewNote'    => __( 'This edits the post it was found in. It can be undone from Restore Database.', 'real-time-auto-find-and-replace' ),
		);
	}

	/**
	 * Render the page.
	 *
	 * @return void
	 */
	public function render() {
		if ( ! Capabilities::current_user_can( 'content_health' ) ) {
			wp_die( esc_html__( 'You do not have permission to view this page.', 'real-time-auto-find-and-replace' ) );
		}

		$page = array(
			'title'      => __( 'Content Health', 'real-time-auto-find-and-replace' ),
			'sub_title'  => __( 'Find links in your content that no longer go anywhere, and fix them safely.', 'real-time-auto-find-and-replace' ),

			// The tab strip is this section's top edge, so the section meets
			// the heading rather than sitting a margin below it.
			'body_class' => 'bfrmaint-body',
		);

		if ( ! Tables::installed() ) {
			$page['content'] = $this->unavailable_html();
		} else {
			$tab = $this->current_tab();

			// Our own markup is the default the filters are given, so a module
			// that owns another tab replaces it outright, one that owns no tab
			// leaves it alone, and one that needs to interrupt this tab for a
			// single request - a confirmation step before something
			// destructive - can do that without a page of its own.
			$content = 'links' === $tab ? $this->table_html() : '';

			// The scan is the one control that belongs to the screen rather
			// than to any row of it, so the button sits in the heading, on the
			// title's row. Its progress and its result do not: a message that
			// appears and disappears beside the title drags the title around
			// with it, so those are reported under the heading instead, on the
			// tab strip's own row.
			$actions = 'links' === $tab ? $this->scan_button_html() : '';
			$status  = 'links' === $tab ? $this->scan_status_html() : '';

			$page['header_actions'] = (string) apply_filters( 'bfr_maintenance_screen_header_actions', $actions, $tab );

			// A tab that starts work of its own says how that work is going in
			// the same place this one does - the far end of the strip - rather
			// than inventing a second place to look.
			$status = (string) apply_filters( 'bfr_maintenance_screen_status', $status, $tab );

			// Where the script reports on work it started. Between the heading
			// and the section, so a message about the whole screen is never
			// inside the list it is talking about.
			$page['after_header'] = $this->banner_html();

			$body = (string) apply_filters( 'bfr_maintenance_screen_content', $content, $tab );

			// A module's own controls: the same filter it always was, rendered
			// inside the section its tab opens rather than in the builder's
			// well above the strip, which would hang one tab's controls over
			// every tab's row.
			$well = (string) apply_filters( 'bfr_maintenance_screen_well', '', $tab );

			if ( '' !== trim( $well ) ) {
				$well = '<div class="well">' . $well . '</div>';
			}

			// Nothing answered for this tab: either pro is absent, or the licence
			// does not carry the feature. Either way the tab explains itself
			// rather than opening on nothing.
			$page['content'] = $this->tabs_html( $tab, $status ) . $well . ( '' !== $body ? $body : self::locked_html( $tab ) );

			// Only when there is a note to make: the wrapper on its own is an
			// empty bordered box above the footer.
			$note = $this->pro_note_html();

			if ( '' !== $note ) {
				$page['before_footer']         = $note;
				$page['before_footer_wrapper'] = true;
			}
		}

		$builder = new AdminPageBuilder();

		// The builder assembles trusted markup: every value interpolated into it
		// is escaped at the point it is built, below.
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo $builder->generate_page( $page );
	}

	/**
	 * The tab strip, on the section's own top edge.
	 *
	 * The strip is the lid of the section rather than a row floating above it:
	 * the open tab is the same white as what is under it, with no line between
	 * the two, so clicking a tab reads as opening that section. Anything the
	 * tab has to say about itself - a scan's progress, its result - rides the
	 * far end of the same row.
	 *
	 * @param string $current The tab being viewed.
	 * @param string $meta    Markup for the far end of the strip, optional.
	 * @return string
	 */
	private function tabs_html( $current = 'links', $meta = '' ) {
		$tabs = self::tabs();
		$html = '';

		if ( count( $tabs ) > 1 ) {
			$locks = self::tab_entitlements();
			$html  = '<div class="nav-tab-wrapper bfrmaint-tabs">';

			foreach ( $tabs as $key => $label ) {
				$html .= sprintf(
					'<a href="%s" class="nav-tab%s">%s</a>',
					esc_url( add_query_arg( 'tab', $key, menu_page_url( self::SLUG, false ) ) ),
					$key === $current ? ' nav-tab-active' : '',
					LockedTab::label( $label, isset( $locks[ $key ] ) ? $locks[ $key ] : '' )
				);
			}

			$html .= '</div>';
		}

		$meta = (string) $meta;

		if ( '' === $html && '' === trim( $meta ) ) {
			return '';
		}

		if ( '' !== trim( $meta ) ) {
			$html .= '<div class="bfrmaint-tabstrip-meta">' . $meta . '</div>';
		}

		return '<div class="bfrmaint-tabstrip">' . $html . '</div>';
	}

	/**
	 * A tab this install cannot render.
	 *
	 * Both figures are real: free's scanner finds missing media and files it
	 * as `missing_media` whether or not the tab that shows it is available, so
	 * the count is a fact about this site.
	 *
	 * @param string $tab Tab key.
	 * @return string
	 */
	public static function locked_html( $tab ) {
		if ( 'media' === $tab ) {
			$open = IssueQuery::count(
				array(
					'type'   => 'missing_media',
					'status' => 'open',
				)
			);

			return LockedTab::panel(
				array(
					'title'   => __( 'Missing media', 'real-time-auto-find-and-replace' ),
					'measure' => $open > 0
						? sprintf(
							/* translators: %d: number of missing media files */
							_n(
								'%d missing image or file was found on this site.',
								'%d missing images or files were found on this site.',
								$open,
								'real-time-auto-find-and-replace'
							),
							$open
						)
						: '',
					'body'    => __( 'Pictures and files that are referenced on your pages but are no longer there. Your visitors see a blank space where the image should be.', 'real-time-auto-find-and-replace' ),
					'points'  => array(
						__( 'See every page a missing file appears on', 'real-time-auto-find-and-replace' ),
						__( 'Get a replacement suggested from your Media Library', 'real-time-auto-find-and-replace' ),
						__( 'Swap one file for another everywhere it appears, at once', 'real-time-auto-find-and-replace' ),
					),
				)
			);
		}

		if ( 'external' === $tab ) {
			return LockedTab::panel(
				array(
					'title'  => __( 'External links', 'real-time-auto-find-and-replace' ),
					'body'   => __( 'The free version checks links that point at your own site, because it can answer those from your database without making a single request. Checking links to other sites means actually visiting them, which Pro does politely and on a schedule.', 'real-time-auto-find-and-replace' ),
					'points' => array(
						__( 'Find dead links to other sites before your readers do', 'real-time-auto-find-and-replace' ),
						__( 'Rate-limited per host, so nobody is hammered', 'real-time-auto-find-and-replace' ),
						__( 'A link is only reported broken after it fails twice', 'real-time-auto-find-and-replace' ),
					),
				)
			);
		}

		return LockedTab::panel(
			array(
				'title' => __( 'This is a Pro feature', 'real-time-auto-find-and-replace' ),
				'body'  => __( 'It is part of Better Find and Replace Pro.', 'real-time-auto-find-and-replace' ),
			)
		);
	}

	/**
	 * Every tab on this screen, in display order.
	 *
	 * @return array Tab key => label.
	 */
	public static function tabs() {
		// Every tab, always. Pro registers 'external' through the filter below
		// and MediaScreen registers 'media'; declaring them here as well is what
		// makes them visible - and locked - on a free install.
		$tabs = array(
			'links'    => __( 'Broken Links', 'real-time-auto-find-and-replace' ),
			'media'    => __( 'Missing Media', 'real-time-auto-find-and-replace' ),
			'external' => __( 'External Links', 'real-time-auto-find-and-replace' ),
		);

		/**
		 * Filter the Content Health tabs.
		 *
		 * Later milestones and the pro plugin add their own here. A tab whose
		 * module has not shipped is simply absent - never a disabled tab.
		 *
		 * @param array $tabs Tab key => label.
		 */
		$tabs = apply_filters( 'bfr_maintenance_screens', $tabs );

		return is_array( $tabs ) && isset( $tabs['links'] ) ? $tabs : $tabs + array( 'links' => __( 'Broken Links', 'real-time-auto-find-and-replace' ) );
	}

	/**
	 * Which capability each tab needs.
	 *
	 * A tab absent from this map is free.
	 *
	 * @return array Tab key => entitlement.
	 */
	public static function tab_entitlements() {
		return array(
			'media'    => 'media_health.internal',
			'external' => 'link_health.external',
		);
	}

	/**
	 * Which tab is being viewed.
	 *
	 * Validated against the registered tabs, so a stale bookmark from a
	 * deactivated module lands on the built-in list rather than on a blank
	 * panel. Read-only navigation, so there is nothing here to nonce.
	 *
	 * @return string
	 */
	public static function current_tab() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$tab = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'links';

		return isset( self::tabs()[ $tab ] ) ? $tab : 'links';
	}

	/**
	 * The scan control, for the builder's heading row.
	 *
	 * @return string
	 */
	private function scan_button_html() {
		$run     = ScanRunRepository::latest( Scanner::SCAN_TYPE );
		$running = ScanRunRepository::is_alive( $run );

		return sprintf(
			'<div class="bfrmaint-scanbar">
				<button type="button" class="btn btn-custom-submit" id="bfrmaint-scan"%1$s>%2$s</button>
			</div>',
			$running ? ' disabled="disabled"' : '',
			esc_html__( 'Scan for broken links', 'real-time-auto-find-and-replace' )
		);
	}

	/**
	 * The banner the script writes into, empty until it has something to say.
	 *
	 * Rendered server-side and left hidden rather than created by the script,
	 * so the space it will occupy is decided by the stylesheet once, and a
	 * message arriving cannot reflow the page under somebody's cursor.
	 *
	 * @return string
	 */
	private function banner_html() {
		return '<div id="bfrmaint-banner" class="bfrmaint-banner" role="status" aria-live="polite" hidden></div>';
	}

	/**
	 * What the last scan did, and what this one is doing.
	 *
	 * Deliberately away from the button: the script writes into both of these
	 * while a scan runs, and text that grows and shrinks inside the heading
	 * moves the title with it.
	 *
	 * @return string
	 */
	private function scan_status_html() {
		$run     = ScanRunRepository::latest( Scanner::SCAN_TYPE );
		$running = ScanRunRepository::is_alive( $run );

		if ( $running ) {
			$status = sprintf(
				/* translators: 1: URLs scanned so far, 2: URLs to scan in total */
				esc_html__( '%1$s of %2$s URLs scanned', 'real-time-auto-find-and-replace' ),
				esc_html( number_format_i18n( (int) $run->processed_items ) ),
				esc_html( number_format_i18n( max( 1, (int) $run->total_items ) ) )
			);
		} elseif ( $run && ScanRunRepository::STATUS_COMPLETED === $run->status && ! empty( $run->completed_at ) ) {
			$status = sprintf(
				/* translators: %s: human-readable time difference */
				esc_html__( 'Last scanned %s ago.', 'real-time-auto-find-and-replace' ),
				esc_html( human_time_diff( strtotime( $run->completed_at . ' UTC' ), time() ) )
			);
		} elseif ( $run ) {
			// A run that exists but did not complete: stopped, failed, or
			// still marked running with a heartbeat too old to believe.
			// "No scan has run yet" would be a lie, and reporting a time it
			// never finished at would be a worse one.
			$status = esc_html__( 'The last scan stopped before it finished.', 'real-time-auto-find-and-replace' );
		} else {
			$status = esc_html__( 'No scan has run yet.', 'real-time-auto-find-and-replace' );
		}

		return sprintf(
			'<span id="bfrmaint-scan-status" class="bfrmaint-status">%1$s</span>',
			$status
		);
	}

	/**
	 * The issues list.
	 *
	 * @return string
	 */
	private function table_html() {
		$table = new IssuesTable();
		$table->prepare_items();

		ob_start();

		echo '<form method="get">';
		printf( '<input type="hidden" name="page" value="%s" />', esc_attr( self::SLUG ) );

		$table->views();
		$table->search_box( __( 'Search links', 'real-time-auto-find-and-replace' ), 'bfr-issue-search' );
		$table->display();

		echo '</form>';

		return (string) ob_get_clean();
	}

	/**
	 * The honest pro note.
	 *
	 * Shows the real number, says plainly what free does and what pro adds. No
	 * fake control, no nag, and nothing at all when there is nothing to act on.
	 *
	 * @return string
	 */
	private function pro_note_html() {
		if ( Entitlements::can( 'link_health.bulk_fix' ) ) {
			return '';
		}

		$open = IssueQuery::count(
			array(
				'type'   => Scanner::ISSUE_TYPE,
				'status' => 'open',
			)
		);

		if ( $open < 1 ) {
			return '';
		}

		return sprintf(
			'<div class="bfrmaint-pro-note"><p><strong>%1$s</strong></p><p>%2$s</p></div>',
			esc_html(
				sprintf(
					/* translators: %d: number of broken links found */
					_n( '%d broken link found.', '%d broken links found.', $open, 'real-time-auto-find-and-replace' ),
					$open
				)
			),
			esc_html__( 'You can fix them one at a time here, for free. Pro finds broken external links and images too, fixes every occurrence of a URL across the whole site at once, re-checks them automatically, and can scan on a schedule.', 'real-time-auto-find-and-replace' )
		);
	}

	/**
	 * Explain why the screen has nothing to show.
	 *
	 * @return string
	 */
	private function unavailable_html() {
		$error = Tables::last_error();

		$html = '<div class="notice notice-warning inline"><p>'
			. esc_html__( 'Content Health needs a few database tables that could not be created on this site. Find and replace is unaffected.', 'real-time-auto-find-and-replace' )
			. '</p>';

		if ( '' !== $error ) {
			$html .= '<p><code>' . esc_html( $error ) . '</code></p>';
		}

		return $html . '</div>';
	}
}
