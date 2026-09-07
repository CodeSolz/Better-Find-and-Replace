<?php namespace RealTimeAutoFindReplace\Maintenance\Admin;

use RealTimeAutoFindReplace\Maintenance\Data\IssueQuery;
use RealTimeAutoFindReplace\Maintenance\Data\ScanRunRepository;
use RealTimeAutoFindReplace\Maintenance\LinkHealth\Scanner;
use RealTimeAutoFindReplace\Maintenance\MediaHealth\Classifier;
use RealTimeAutoFindReplace\Maintenance\Support\Entitlements;
use RealTimeAutoFindReplace\Maintenance\Admin\LockedTab;

/**
 * The Missing Media tab.
 *
 * Not a screen, despite the name and the neighbours: it is a tab on Content
 * Health, registered through the same two filters pro's tabs use. No menu
 * entry, no page callback, no capability of its own - if it needed any of
 * those it would be a fourth thing for a user to find, and "a picture is
 * broken" is the same errand as "a link is broken".
 *
 * There is also no second scan. The link scan has always walked every `<img
 * src>` in the content; M11 only changed what it calls what it finds. So the
 * button on this tab starts the same run as the button on the other one, and
 * says so.
 *
 * @package Maintenance
 * @since 1.10.0
 * @author M.Tuhin <info@codesolz.net>
 */

if ( ! defined( 'CS_RTAFAR_VERSION' ) ) {
	exit;
}

class MediaScreen {

	/** The tab key, in the query string and in the tab registry. */
	const TAB = 'media';

	/**
	 * Register the tab and its two slots.
	 */
	public function __construct() {
		add_filter( 'bfr_maintenance_screens', array( $this, 'register_tab' ) );
		add_filter( 'bfr_maintenance_screen_content', array( $this, 'content' ), 10, 2 );
		add_filter( 'bfr_maintenance_screen_header_actions', array( $this, 'header_actions' ), 10, 2 );
		add_filter( 'bfr_maintenance_screen_status', array( $this, 'status' ), 10, 2 );
	}

	/**
	 * Add the tab.
	 *
	 * @param array $tabs Tab key => label.
	 * @return array
	 */
	public function register_tab( $tabs ) {
		if ( ! is_array( $tabs ) ) {
			return $tabs;
		}

		$tabs[ self::TAB ] = __( 'Missing Media', 'real-time-auto-find-and-replace' );

		return $tabs;
	}

	/**
	 * The list, when this tab is the one being viewed.
	 *
	 * @param string $content Markup another module produced.
	 * @param string $tab     Current tab.
	 * @return string
	 */
	public function content( $content, $tab ) {
		if ( self::TAB !== $tab ) {
			return $content;
		}

		// The tab is always shown; the list behind it is not always available.
		// Returning what we were handed - nothing - lets Screen render the
		// locked panel instead, so there is one place that decides what a
		// locked tab looks like.
		if ( ! LockedTab::unlocked( 'media_health.internal' ) ) {
			return $content;
		}

		$table = new IssuesTable( Classifier::MEDIA );
		$table->prepare_items();

		ob_start();

		// Said here rather than beside the button, because it is about what
		// this list is, not about the control: one scan fills both tabs.

		echo '<form method="get">';
		printf( '<input type="hidden" name="page" value="%s" />', esc_attr( Screen::SLUG ) );
		printf( '<input type="hidden" name="tab" value="%s" />', esc_attr( self::TAB ) );

		$table->views();
		$table->search_box( __( 'Search media', 'real-time-auto-find-and-replace' ), 'bfr-media-search' );
		$table->display();

		echo '</form>';

		return (string) ob_get_clean() . $this->pro_note_html();
	}

	/**
	 * The scan control, in the heading row.
	 *
	 * Deliberately the same run as the Broken Links tab starts - one scan reads
	 * the content once and files what it finds under two types. The button id
	 * is shared with that tab because the admin script binds to it, and a
	 * second id would mean a second code path for one behaviour: the click
	 * disables the button, the banner reports that the scan is running, and
	 * the button comes back when it stops. Identical on both tabs, because it
	 * is identical work.
	 *
	 * @param string $actions Markup another module produced.
	 * @param string $tab     Current tab.
	 * @return string
	 */
	public function header_actions( $actions, $tab ) {
		if ( self::TAB !== $tab || ! LockedTab::unlocked( 'media_health.internal' ) ) {
			return $actions;
		}

		return sprintf(
			'<div class="bfrmaint-scanbar">
				<button type="button" class="btn btn-custom-submit" id="bfrmaint-scan"%1$s>%2$s</button>
			</div>',
			self::scan_is_running() ? ' disabled="disabled"' : '',
			esc_html__( 'Scan content for missing media', 'real-time-auto-find-and-replace' )
		);
	}

	/**
	 * How the scan is going, at the far end of the tab strip.
	 *
	 * The same element id as the links tab, so the same script updates it.
	 *
	 * @param string $status Markup another module produced.
	 * @param string $tab    Current tab.
	 * @return string
	 */
	public function status( $status, $tab ) {
		if ( self::TAB !== $tab || ! LockedTab::unlocked( 'media_health.internal' ) ) {
			return $status;
		}

		$run = ScanRunRepository::latest( Scanner::SCAN_TYPE );

		if ( ScanRunRepository::is_alive( $run ) ) {
			$text = sprintf(
				/* translators: 1: URLs scanned so far, 2: URLs to scan in total */
				esc_html__( '%1$s of %2$s URLs scanned', 'real-time-auto-find-and-replace' ),
				esc_html( number_format_i18n( (int) $run->processed_items ) ),
				esc_html( number_format_i18n( max( 1, (int) $run->total_items ) ) )
			);
		} elseif ( $run && ScanRunRepository::STATUS_COMPLETED === $run->status && ! empty( $run->completed_at ) ) {
			$text = sprintf(
				/* translators: %s: human-readable time difference */
				esc_html__( 'Last scanned %s ago.', 'real-time-auto-find-and-replace' ),
				esc_html( human_time_diff( strtotime( $run->completed_at . ' UTC' ), time() ) )
			);
		} elseif ( $run ) {
			$text = esc_html__( 'The last scan stopped before it finished.', 'real-time-auto-find-and-replace' );
		} else {
			$text = esc_html__( 'No scan has run yet.', 'real-time-auto-find-and-replace' );
		}

		return sprintf( '<span id="bfrmaint-scan-status" class="bfrmaint-status">%s</span>', $text );
	}

	/**
	 * Is a scan running right now?
	 *
	 * @return bool
	 */
	private static function scan_is_running() {
		return ScanRunRepository::is_alive( ScanRunRepository::latest( Scanner::SCAN_TYPE ) );
	}

	/**
	 * The honest pro note.
	 *
	 * Same rules as the links tab: the real number, plainly what free does and
	 * what pro adds, nothing at all when there is nothing to act on.
	 *
	 * @return string
	 */
	private function pro_note_html() {
		if ( Entitlements::can( 'media_health.bulk' ) ) {
			return '';
		}

		$open = IssueQuery::count(
			array(
				'type'   => Classifier::MEDIA,
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
					/* translators: %d: number of missing media files found */
					_n( '%d missing media file found.', '%d missing media files found.', $open, 'real-time-auto-find-and-replace' ),
					$open
				)
			),
			esc_html__( 'You can replace them one at a time here, for free. Pro checks images hosted elsewhere, suggests a replacement from your Media Library, and can swap one file for another everywhere it appears.', 'real-time-auto-find-and-replace' )
		);
	}
}
