<?php namespace RealTimeAutoFindReplace\Maintenance\Admin;

use RealTimeAutoFindReplace\admin\builders\AdminPageBuilder;
use RealTimeAutoFindReplace\Maintenance\Data\ActivityLog;
use RealTimeAutoFindReplace\Maintenance\Data\Schema\Tables;
use RealTimeAutoFindReplace\Maintenance\Data\Summary;
use RealTimeAutoFindReplace\Maintenance\Admin\LockedTab;
use RealTimeAutoFindReplace\Maintenance\Admin\SectionCard;
use RealTimeAutoFindReplace\Maintenance\Support\Capabilities;
use RealTimeAutoFindReplace\Maintenance\Support\Entitlements;
use RealTimeAutoFindReplace\Maintenance\Support\HealthScore;

/**
 * The Content Health dashboard.
 *
 * Answers one question - what needs attention - and links to the screen that
 * handles each answer. It deliberately does not become a third place to fix
 * things: every card is a count and a link.
 *
 * The score is shown with its arithmetic. A number on a dashboard that cannot
 * be accounted for teaches people to ignore the whole screen, so the deduction
 * breakdown is on the page rather than hidden behind a tooltip.
 *
 * Activity is a tab here rather than a menu entry of its own. It is a log: you
 * go looking for it after something happened, not as a destination.
 *
 * @package Maintenance
 * @since 1.10.0
 * @author M.Tuhin <info@codesolz.net>
 */

if ( ! defined( 'CS_RTAFAR_VERSION' ) ) {
	exit;
}

class DashboardScreen {

	/** The page slug. */
	const SLUG = 'cs-bfar-maintenance';

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
			__( 'Site Health', 'real-time-auto-find-and-replace' ),
			__( 'Site Health', 'real-time-auto-find-and-replace' ),
			Capabilities::for_module( 'dashboard' ),
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

		// The house panel styles - see the note in Screen::assets().
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
	}

	/**
	 * Render the page.
	 *
	 * @return void
	 */
	public function render() {
		if ( ! Capabilities::current_user_can( 'dashboard' ) ) {
			wp_die( esc_html__( 'You do not have permission to view this page.', 'real-time-auto-find-and-replace' ) );
		}

		$page = array(
			'title'      => __( 'Site Health', 'real-time-auto-find-and-replace' ),
			'sub_title'  => __( 'What needs attention across your content, links and redirects.', 'real-time-auto-find-and-replace' ),

			// The tab strip is this section's top edge, so the section meets
			// the heading rather than sitting a margin below it - the same
			// treatment Content Health has.
			'body_class' => 'bfrmaint-body',
		);

		if ( ! Tables::installed() ) {
			// No strip on this branch, so the section keeps its own top border
			// rather than the edge the strip would have drawn for it.
			$page['body_class'] = '';
			$page['content']    = '<div class="notice notice-warning inline"><p>'
				. esc_html__( 'Site Health needs a few database tables that could not be created on this site. Find and replace is unaffected.', 'real-time-auto-find-and-replace' )
				. '</p></div>';
		} elseif ( 'activity' === self::current_tab() ) {
			$page['sub_title'] = __( 'What this plugin has changed, and who changed it.', 'real-time-auto-find-and-replace' );
			$page['content']   = $this->tabs_html() . (
				LockedTab::unlocked( 'activity.basic' )
					? $this->activity_html()
					: $this->locked_activity_html()
			);
		} elseif ( 'overview' !== self::current_tab() ) {
			// A tab another module registered. Our own markup is the default it
			// is handed, so a module that owns the tab replaces it outright and
			// one that has gone away leaves an empty panel rather than silently
			// rendering the Overview under its heading.
			//
			// This is the third screen in the project to need this. `Screen` and
			// `RedirectsScreen` both shipped a tab filter with a hard-coded
			// dispatch, and both rendered the wrong body for a registered tab.
			$tab = self::current_tab();

			$page['sub_title'] = (string) apply_filters( 'bfr_dashboard_screen_subtitle', '', $tab );

			// Pro answers this filter for the tabs it owns. When nothing does -
			// free, or a licence without the feature - the tab is not blank, it
			// explains itself.
			$body = (string) apply_filters( 'bfr_dashboard_screen_content', '', $tab );

			$page['content'] = $this->tabs_html() . ( '' !== $body ? $body : $this->locked_html( $tab ) );
		} else {
			$summary         = Summary::get();
			$page['content'] = $this->tabs_html() . SectionCard::stack(
				$this->score_html( $summary ) . $this->cards_html( $summary )
			);

			// Only when there is a note to make: the wrapper on its own is an
			// empty bordered box above the footer.
			$note = $this->pro_note_html();

			if ( '' !== $note ) {
				$page['before_footer']         = $note;
				$page['before_footer_wrapper'] = true;
			}
		}

		$builder = new AdminPageBuilder();

		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo $builder->generate_page( $page );
	}

	/**
	 * Every tab on this screen, in display order.
	 *
	 * @return array Tab key => label.
	 */
	public static function tabs() {
		// Every tab, always, whether or not the module behind it is available.
		// Pro registers the same keys through the filter below; declaring them
		// here as well is what makes them visible - and locked - in free.
		$tabs = array(
			'overview' => __( 'Overview', 'real-time-auto-find-and-replace' ),
			'activity' => __( 'Activity', 'real-time-auto-find-and-replace' ),
			'agent'    => __( 'Maintenance Agent', 'real-time-auto-find-and-replace' ),
		);

		/**
		 * Filter the Site Health tabs.
		 *
		 * @param array $tabs Tab key => label.
		 */
		$tabs = (array) apply_filters( 'bfr_dashboard_screens', $tabs );

		return isset( $tabs['overview'] )
			? $tabs
			: array( 'overview' => __( 'Overview', 'real-time-auto-find-and-replace' ) ) + $tabs;
	}

	/**
	 * The Activity tab, for an install that does not have it.
	 *
	 * The figure is real: free records activity all along, it simply does not
	 * show the log. So this can say how many entries are already waiting,
	 * which is a fact about this site rather than a claim about the product.
	 *
	 * @return string
	 */
	private function locked_activity_html() {
		$measure = '';
		$count   = ActivityLog::count();

		if ( $count > 0 ) {
			$measure = sprintf(
				/* translators: %d: number of recorded changes */
				_n(
					'%d change has already been recorded on this site.',
					'%d changes have already been recorded on this site.',
					$count,
					'real-time-auto-find-and-replace'
				),
				$count
			);
		}

		return LockedTab::panel(
			array(
				'title'   => __( 'Activity log', 'real-time-auto-find-and-replace' ),
				'measure' => $measure,
				'body'    => __( 'Every change this plugin makes is written down - what changed, on which page, and who did it. Pro shows you that history and lets you undo from it.', 'real-time-auto-find-and-replace' ),
				'points'  => array(
					__( 'See exactly what a bulk replace touched, page by page', 'real-time-auto-find-and-replace' ),
					__( 'Find out who made a change, and when', 'real-time-auto-find-and-replace' ),
					__( 'Undo an individual change from the log', 'real-time-auto-find-and-replace' ),
				),
			)
		);
	}

	/**
	 * Any other tab nothing rendered.
	 *
	 * @param string $tab Tab key.
	 * @return string
	 */
	private function locked_html( $tab ) {
		if ( 'agent' !== $tab ) {
			return LockedTab::panel(
				array(
					'title' => __( 'This is a Pro feature', 'real-time-auto-find-and-replace' ),
					'body'  => __( 'It is part of Better Find and Replace Pro.', 'real-time-auto-find-and-replace' ),
				)
			);
		}

		$summary = Summary::get();
		$open    = array_sum( (array) $summary['issues'] ) + (int) $summary['not_found'];
		$measure = '';

		if ( $open > 0 ) {
			$measure = sprintf(
				/* translators: %d: number of open issues */
				_n(
					'%d issue needs attention on this site right now.',
					'%d issues need attention on this site right now.',
					$open,
					'real-time-auto-find-and-replace'
				),
				$open
			);
		}

		return LockedTab::panel(
			array(
				'title'   => __( 'Maintenance Agent', 'real-time-auto-find-and-replace' ),
				'measure' => $measure,
				'body'    => __( 'Groups everything that is wrong with your site into a short plan, ranked by what is costing you most, and prepares the fixes. Nothing is changed until you approve it.', 'real-time-auto-find-and-replace' ),
				'points'  => array(
					__( 'Forty broken links to one dead page become one fix, not forty', 'real-time-auto-find-and-replace' ),
					__( 'Every suggestion explains why it is ranked where it is', 'real-time-auto-find-and-replace' ),
					__( 'Re-checks each change before it runs, and reports what is left', 'real-time-auto-find-and-replace' ),
				),
			)
		);
	}

	/**
	 * Which capability each tab needs.
	 *
	 * A tab absent from this map is free. Used for the Pro marker in the
	 * strip and nothing else - what a locked tab actually renders is
	 * decided in render().
	 *
	 * @return array Tab key => entitlement.
	 */
	public static function tab_entitlements() {
		return array(
			'activity' => 'activity.basic',
			'agent'    => 'maintenance_agent',
		);
	}

	/**
	 * Which tab is being viewed.
	 *
	 * Validated against the **registered** tabs rather than a hard-coded list,
	 * so a tab another module added is actually reachable and a stale bookmark
	 * from a deactivated one lands on the Overview.
	 *
	 * @return string
	 */
	public static function current_tab() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$tab = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'overview';

		return isset( self::tabs()[ $tab ] ) ? $tab : 'overview';
	}

	/**
	 * The tab strip, on the section's own top edge.
	 *
	 * The strip is the lid of the section rather than a row floating above it:
	 * the open tab is the same white as what is under it, with no line between
	 * the two, so clicking a tab reads as opening that section. Same markup and
	 * same stylesheet as Content Health, so the two screens are one design.
	 *
	 * @return string
	 */
	private function tabs_html() {
		// The filter lives in tabs(), so the strip and the dispatch cannot
		// disagree about which tabs exist - which is exactly how a registered
		// tab used to render the wrong body.
		$tabs    = self::tabs();
		$current = self::current_tab();
		$html    = '<div class="nav-tab-wrapper bfrmaint-tabs">';

		$locks = self::tab_entitlements();

		foreach ( $tabs as $key => $label ) {
			$html .= sprintf(
				'<a href="%s" class="nav-tab%s">%s</a>',
				esc_url( add_query_arg( 'tab', $key, menu_page_url( self::SLUG, false ) ) ),
				$key === $current ? ' nav-tab-active' : '',
				LockedTab::label( $label, isset( $locks[ $key ] ) ? $locks[ $key ] : '' )
			);
		}

		return '<div class="bfrmaint-tabstrip">' . $html . '</div></div>';
	}

	/**
	 * The score, with the arithmetic that produced it.
	 *
	 * The dial is not decoration: a bare number cannot say whether 72 is close
	 * to fine or close to bad, and the ring answers that before the reader has
	 * finished looking at it. The arithmetic sits beside it rather than behind a
	 * tooltip, because a score nobody can account for teaches people to ignore
	 * the whole screen.
	 *
	 * @param array $summary Summary::get() output.
	 * @return string
	 */
	private function score_html( array $summary ) {
		$health = $summary['health'];
		$labels = self::labels();
		$score  = (int) $health['score'];
		$band   = (string) $health['band'];

		$rows = '';

		foreach ( $health['deductions'] as $type => $points ) {
			$rows .= sprintf(
				'<li><span class="bfrmaint-deduction-label">%1$s</span><span class="bfrmaint-deduction-points">-%2$d</span></li>',
				esc_html( isset( $labels[ $type ] ) ? $labels[ $type ] : $type ),
				(int) $points
			);
		}

		if ( '' === $rows ) {
			$rows = '<li class="is-clear"><span class="bfrmaint-deduction-label">'
				. esc_html__( 'Nothing is deducting from your score.', 'real-time-auto-find-and-replace' )
				. '</span></li>';
		}

		$body = '<div class="bfrmaint-scorecard">'
			. self::gauge_html( $score, $band )
			. '<div class="bfrmaint-breakdown">'
			. '<p class="bfrmaint-breakdown-band">' . esc_html( self::band_text( $band ) ) . '</p>'
			. '<p class="bfrmaint-breakdown-lead">' . esc_html__( 'Where the missing points went:', 'real-time-auto-find-and-replace' ) . '</p>'
			. '<ul class="bfrmaint-deductions">' . $rows . '</ul>'
			. '</div></div>';

		return SectionCard::render(
			array(
				'icon'    => 'heart',
				'eyebrow' => __( 'Site health', 'real-time-auto-find-and-replace' ),
				'title'   => __( 'Content health score', 'real-time-auto-find-and-replace' ),
				'desc'    => __( 'Worked out from what the last scan found. Every deduction is named, so the number can always be accounted for.', 'real-time-auto-find-and-replace' ),
				'meta'    => SectionCard::pill( self::band_label( $band ), self::band_tone( $band ) ),
				'body'    => $body,
				'foot'    => sprintf(
					'<a class="bfrmaint-btn-quiet" href="%1$s">%2$s</a><span class="bfrmaint-foot-note">%3$s</span>',
					esc_url( menu_page_url( Screen::SLUG, false ) ),
					esc_html__( 'Open Content Health', 'real-time-auto-find-and-replace' ),
					esc_html__( 'The score only moves when a scan finds something new.', 'real-time-auto-find-and-replace' )
				),
			)
		);
	}

	/**
	 * The dial.
	 *
	 * Drawn inline rather than pulled from a charting library: it is one arc,
	 * and this screen has no other reason to load a script.
	 *
	 * @param int    $score 0-100.
	 * @param string $band  'good', 'fair' or 'poor'.
	 * @return string
	 */
	private static function gauge_html( $score, $band ) {
		$score = max( 0, min( 100, (int) $score ) );

		// 2 * pi * r, for r = 52. The arc is drawn by leaving the rest of the
		// circumference as dash offset.
		$length = 326.7;
		$offset = $length * ( 1 - ( $score / 100 ) );

		return sprintf(
			'<div class="bfrmaint-gauge bfrmaint-gauge-%1$s" role="img" aria-label="%2$s">
				<svg viewBox="0 0 120 120" aria-hidden="true" focusable="false">
					<circle class="bfrmaint-gauge-track" cx="60" cy="60" r="52" />
					<circle class="bfrmaint-gauge-arc" cx="60" cy="60" r="52" stroke-dasharray="%3$s" stroke-dashoffset="%4$s" />
				</svg>
				<span class="bfrmaint-gauge-value"><strong>%5$d</strong><span>%6$s</span></span>
			</div>',
			esc_attr( $band ),
			esc_attr(
				sprintf(
					/* translators: %d: the health score out of 100 */
					__( 'Health score: %d out of 100.', 'real-time-auto-find-and-replace' ),
					$score
				)
			),
			esc_attr( (string) $length ),
			esc_attr( (string) round( $offset, 2 ) ),
			$score,
			esc_html__( 'out of 100', 'real-time-auto-find-and-replace' )
		);
	}

	/**
	 * The needs-attention card.
	 *
	 * Each count is a link, and the whole tile is the target: a count you can
	 * see but not act on is a screen that tells you off without helping.
	 *
	 * @param array $summary Summary::get() output.
	 * @return string
	 */
	private function cards_html( array $summary ) {
		$labels = self::labels();
		$cards  = array();

		foreach ( $summary['issues'] as $type => $count ) {
			if ( $count < 1 ) {
				continue;
			}

			$cards[] = array(
				'label' => isset( $labels[ $type ] ) ? $labels[ $type ] : $type,
				'count' => (int) $count,
				'url'   => menu_page_url( Screen::SLUG, false ),
				'cta'   => __( 'Review', 'real-time-auto-find-and-replace' ),
			);
		}

		if ( $summary['not_found'] > 0 ) {
			$cards[] = array(
				'label' => __( 'Unhandled 404s', 'real-time-auto-find-and-replace' ),
				'count' => (int) $summary['not_found'],
				'url'   => add_query_arg( 'tab', 'not-found', menu_page_url( RedirectsScreen::SLUG, false ) ),
				'cta'   => __( 'Review', 'real-time-auto-find-and-replace' ),
			);
		}

		/**
		 * Filter the dashboard cards.
		 *
		 * Pro adds its own counts here - external links, stale content, media -
		 * without this screen knowing those modules exist.
		 *
		 * @param array $cards   Each: label, count, url, cta.
		 * @param array $summary The computed summary.
		 */
		$cards = (array) apply_filters( 'bfr_dashboard_cards', $cards, $summary );

		if ( empty( $cards ) ) {
			return SectionCard::render(
				array(
					'icon'    => 'yes-alt',
					'eyebrow' => __( 'Open issues', 'real-time-auto-find-and-replace' ),
					'title'   => __( 'Needs attention', 'real-time-auto-find-and-replace' ),
					'desc'    => __( 'Everything the last scan found, grouped by what kind of problem it is.', 'real-time-auto-find-and-replace' ),
					'meta'    => SectionCard::pill( __( 'All clear', 'real-time-auto-find-and-replace' ), 'is-on' ),
					'body'    => '<div class="bfrmaint-blank"><span class="dashicons dashicons-yes-alt" aria-hidden="true"></span><p>'
						. esc_html__( 'Nothing needs attention. Run a scan from Content Health to check again.', 'real-time-auto-find-and-replace' )
						. '</p></div>',
				)
			);
		}

		$total = 0;
		$tiles = '';

		foreach ( $cards as $card ) {
			$total += (int) $card['count'];

			$tiles .= sprintf(
				'<a class="bfrmaint-tile" href="%1$s">
					<span class="bfrmaint-tile-count">%2$s</span>
					<span class="bfrmaint-tile-label">%3$s</span>
					<span class="bfrmaint-tile-cta">%4$s<span class="dashicons dashicons-arrow-right-alt2" aria-hidden="true"></span></span>
				</a>',
				esc_url( $card['url'] ),
				esc_html( number_format_i18n( (int) $card['count'] ) ),
				esc_html( $card['label'] ),
				esc_html( $card['cta'] )
			);
		}

		return SectionCard::render(
			array(
				'icon'    => 'warning',
				'eyebrow' => __( 'Open issues', 'real-time-auto-find-and-replace' ),
				'title'   => __( 'Needs attention', 'real-time-auto-find-and-replace' ),
				'desc'    => __( 'Everything the last scan found, grouped by what kind of problem it is. Each one opens the screen that handles it.', 'real-time-auto-find-and-replace' ),
				'meta'    => SectionCard::pill(
					sprintf(
						/* translators: %s: number of open issues */
						_n( '%s open', '%s open', $total, 'real-time-auto-find-and-replace' ),
						number_format_i18n( $total )
					),
					'is-warn'
				),
				'body'    => '<div class="bfrmaint-tiles">' . $tiles . '</div>',
			)
		);
	}

	/**
	 * Recent activity.
	 *
	 * A timeline rather than a three-column table. This is a log read after
	 * something happened - what you want from it is *when*, in order, and a
	 * table of fifty rows makes the order the least visible thing on it.
	 *
	 * @return string
	 */
	private function activity_html() {
		$rows  = ActivityLog::recent( 50 );
		$total = ActivityLog::count();

		if ( empty( $rows ) ) {
			return SectionCard::stack(
				SectionCard::render(
					array(
						'icon'    => 'backup',
						'eyebrow' => __( 'History', 'real-time-auto-find-and-replace' ),
						'title'   => __( 'Recent activity', 'real-time-auto-find-and-replace' ),
						'desc'    => __( 'Every change this plugin makes is written down here - what changed, on which page, and who did it.', 'real-time-auto-find-and-replace' ),
						'meta'    => SectionCard::pill( __( 'Nothing yet', 'real-time-auto-find-and-replace' ), 'is-off' ),
						'body'    => '<div class="bfrmaint-blank"><span class="dashicons dashicons-clock" aria-hidden="true"></span><p>'
							. esc_html__( 'Nothing has been changed yet.', 'real-time-auto-find-and-replace' )
							. '</p></div>',
					)
				)
			);
		}

		$format = get_option( 'date_format' ) . ' ' . get_option( 'time_format' );
		$items  = '';

		foreach ( $rows as $row ) {
			$who = (int) $row->user_id > 0
				? get_the_author_meta( 'display_name', (int) $row->user_id )
				: __( 'Automatic', 'real-time-auto-find-and-replace' );

			$summary = '' !== $row->summary ? $row->summary : $row->action;
			$stamp   = strtotime( $row->created_at . ' UTC' );

			list( $tone, $icon ) = self::event_style( $row->action );

			$items .= sprintf(
				'<li class="bfrmaint-event">
					<span class="bfrmaint-event-mark %1$s" aria-hidden="true"><span class="dashicons dashicons-%2$s"></span></span>
					<div class="bfrmaint-event-body">
						<p class="bfrmaint-event-what">%3$s</p>
						<p class="bfrmaint-event-meta"><span class="bfrmaint-event-who">%4$s</span><time datetime="%5$s" title="%6$s">%7$s</time></p>
					</div>
				</li>',
				esc_attr( $tone ),
				esc_attr( $icon ),
				esc_html( $summary ),
				esc_html( (string) $who ),
				esc_attr( gmdate( 'c', $stamp ) ),
				// The exact moment, in the site's own timezone and format, for
				// anyone who needs more than "5 days ago".
				esc_attr( get_date_from_gmt( $row->created_at, $format ) ),
				esc_html(
					sprintf(
						/* translators: %s: human-readable time difference, e.g. "2 hours" */
						__( '%s ago', 'real-time-auto-find-and-replace' ),
						human_time_diff( $stamp, time() )
					)
				)
			);
		}

		$note = count( $rows ) < $total
			? sprintf(
				/* translators: 1: number shown, 2: total recorded */
				__( 'Showing the %1$s most recent of %2$s recorded changes.', 'real-time-auto-find-and-replace' ),
				number_format_i18n( count( $rows ) ),
				number_format_i18n( $total )
			)
			: '';

		return SectionCard::stack(
			SectionCard::render(
				array(
					'icon'    => 'backup',
					'eyebrow' => __( 'History', 'real-time-auto-find-and-replace' ),
					'title'   => __( 'Recent activity', 'real-time-auto-find-and-replace' ),
					'desc'    => __( 'Every change this plugin makes is written down here - what changed, on which page, and who did it.', 'real-time-auto-find-and-replace' ),
					'meta'    => SectionCard::pill(
						sprintf(
							/* translators: %s: number of recorded changes */
							_n( '%s change', '%s changes', $total, 'real-time-auto-find-and-replace' ),
							number_format_i18n( $total )
						)
					),
					'body'    => '<ul class="bfrmaint-timeline">' . $items . '</ul>',
					'foot'    => '' !== $note
						? '<span class="bfrmaint-foot-note">' . esc_html( $note ) . '</span>'
						: '',
				)
			)
		);
	}

	/**
	 * The mark for one log entry: what kind of change it was.
	 *
	 * Matched on the action slug rather than a lookup table, because the slugs
	 * are `<thing>_<verb>` by convention across both plugins and a table would
	 * silently lose its meaning the first time a module added an action nobody
	 * remembered to register.
	 *
	 * @param string $action The recorded action slug.
	 * @return array Tone class, then dashicon name.
	 */
	private static function event_style( $action ) {
		$action = (string) $action;

		if ( preg_match( '/deleted|removed|rolled_back/', $action ) ) {
			return array( 'is-remove', 'minus' );
		}

		if ( preg_match( '/created|added|imported|cloned/', $action ) ) {
			return array( 'is-add', 'plus-alt2' );
		}

		if ( preg_match( '/started/', $action ) ) {
			return array( 'is-start', 'controls-play' );
		}

		return array( 'is-change', 'update' );
	}

	/**
	 * The honest pro note.
	 *
	 * @return string
	 */
	private function pro_note_html() {
		if ( Entitlements::can( 'dashboard.history' ) ) {
			return '';
		}

		return sprintf(
			'<div class="bfrmaint-pro-note"><p><strong>%1$s</strong></p><p>%2$s</p></div>',
			esc_html__( 'Free shows you where the site stands right now.', 'real-time-auto-find-and-replace' ),
			esc_html__( 'Pro keeps the history, scans on a schedule so problems surface before you go looking, ranks what to fix first, and can email you when something important breaks.', 'real-time-auto-find-and-replace' )
		);
	}

	/**
	 * Human names for issue types.
	 *
	 * @return array
	 */
	private static function labels() {
		return array(
			'broken_link'   => __( 'Broken links', 'real-time-auto-find-and-replace' ),
			'not_found'     => __( 'Unhandled 404s', 'real-time-auto-find-and-replace' ),
			'missing_media' => __( 'Missing images', 'real-time-auto-find-and-replace' ),
			'stale_content' => __( 'Outdated content', 'real-time-auto-find-and-replace' ),
			'other'         => __( 'Other issues', 'real-time-auto-find-and-replace' ),
		);
	}

	/**
	 * A word for a score band, for the chip in the card head.
	 *
	 * @param string $band 'good', 'fair' or 'poor'.
	 * @return string
	 */
	private static function band_label( $band ) {
		if ( 'good' === $band ) {
			return __( 'Good', 'real-time-auto-find-and-replace' );
		}

		return 'fair' === $band
			? __( 'Fair', 'real-time-auto-find-and-replace' )
			: __( 'Needs work', 'real-time-auto-find-and-replace' );
	}

	/**
	 * Which chip colour a band gets.
	 *
	 * @param string $band 'good', 'fair' or 'poor'.
	 * @return string
	 */
	private static function band_tone( $band ) {
		if ( 'good' === $band ) {
			return 'is-on';
		}

		return 'fair' === $band ? 'is-warn' : 'is-bad';
	}

	/**
	 * A sentence for a score band.
	 *
	 * @param string $band 'good', 'fair' or 'poor'.
	 * @return string
	 */
	private static function band_text( $band ) {
		if ( 'good' === $band ) {
			return __( 'Your content is in good shape.', 'real-time-auto-find-and-replace' );
		}

		if ( 'fair' === $band ) {
			return __( 'A few things are worth fixing.', 'real-time-auto-find-and-replace' );
		}

		return __( 'Several things need attention.', 'real-time-auto-find-and-replace' );
	}
}
