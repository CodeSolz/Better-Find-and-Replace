<?php namespace RealTimeAutoFindReplace\Maintenance\Admin;

use RealTimeAutoFindReplace\admin\builders\AdminPageBuilder;
use RealTimeAutoFindReplace\admin\builders\FormBuilder;
use RealTimeAutoFindReplace\Maintenance\Data\NotFoundRepository;
use RealTimeAutoFindReplace\Maintenance\Admin\LockedTab;
use RealTimeAutoFindReplace\Maintenance\Data\RedirectRepository;
use RealTimeAutoFindReplace\Maintenance\Data\Schema\Tables;
use RealTimeAutoFindReplace\Maintenance\Redirects\Validator;
use RealTimeAutoFindReplace\Maintenance\Admin\SectionCard;
use RealTimeAutoFindReplace\Maintenance\Support\Capabilities;
use RealTimeAutoFindReplace\Maintenance\Support\Entitlements;

/**
 * The Redirects screen.
 *
 * Same house furniture as every other page here: AdminPageBuilder for the panel
 * and FormBuilder for the fields, so the inputs look and behave like the ones on
 * Replace in Database rather than like a second design language.
 *
 * Because FormBuilder assigns its own sequential field ids, the form is driven
 * from field NAMES in JavaScript. That is deliberate - the alternative was to
 * hand-roll the markup and lose the house styling, which is exactly the problem
 * this screen was rewritten to fix.
 *
 * Loading this page also recounts the front-end guard option. Only
 * RedirectRepository is supposed to write redirects, but a stale guard silently
 * disables every redirect on the site, and that failure is invisible from the
 * admin - so the one screen whose job is redirects repairs it on every load.
 *
 * @package Maintenance
 * @since 1.10.0
 * @author M.Tuhin <info@codesolz.net>
 */

if ( ! defined( 'CS_RTAFAR_VERSION' ) ) {
	exit;
}

class RedirectsScreen {

	/** The page slug. */
	const SLUG = 'cs-bfar-redirects';

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
			__( 'Redirects', 'real-time-auto-find-and-replace' ),
			__( 'Redirects', 'real-time-auto-find-and-replace' ),
			Capabilities::for_module( 'redirects' ),
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

		// Self-heal the front-end guard. Cheap, and the alternative is a site
		// whose redirects silently stopped working with nothing to suggest why.
		if ( Tables::installed() ) {
			RedirectRepository::refresh_guard();
		}
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

		// The house panel and form styles - see the note in Screen::assets().
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

		wp_enqueue_script(
			'bfr-maintenance-redirects',
			CS_RTAFAR_PLUGIN_ASSET_URI . 'js/maintenance-redirects.js',
			array( 'jquery' ),
			CS_RTAFAR_VERSION,
			true
		);

		wp_localize_script(
			'bfr-maintenance-redirects',
			'bfrRedirects',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'token'   => wp_create_nonce( SECURE_AUTH_SALT ),
				'i18n'    => array(
					'confirmDelete' => __( 'Delete this redirect?', 'real-time-auto-find-and-replace' ),
					'failed'        => __( 'Something went wrong. Please try again.', 'real-time-auto-find-and-replace' ),
					'saved'         => __( 'Redirect saved.', 'real-time-auto-find-and-replace' ),
					'useSuggested'  => __( 'The destination is itself redirected. Use the final destination instead?', 'real-time-auto-find-and-replace' ),
				),
			)
		);
	}

	/**
	 * Render the page.
	 *
	 * @return void
	 */
	public function render() {
		if ( ! Capabilities::current_user_can( 'redirects' ) ) {
			wp_die( esc_html__( 'You do not have permission to view this page.', 'real-time-auto-find-and-replace' ) );
		}

		$page = array(
			'title'      => __( 'Redirects', 'real-time-auto-find-and-replace' ),
			'sub_title'  => __( 'Send visitors from an old URL to the right page, so a moved or renamed page never ends in a 404.', 'real-time-auto-find-and-replace' ),

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
				. esc_html__( 'Redirects need a database table that could not be created on this site. Find and replace is unaffected.', 'real-time-auto-find-and-replace' )
				. '</p></div>';
		} elseif ( 'replace-redirect' === self::current_tab() && ! LockedTab::unlocked( 'replace_redirect.single' ) ) {
			$page['content'] = $this->tabs_html() . self::locked_html( 'replace-redirect' );
		} elseif ( 'not-found' === self::current_tab() && ! LockedTab::unlocked( 'not_found.basic' ) ) {
			$page['content'] = $this->tabs_html() . self::locked_html( 'not-found' );
		} elseif ( 'replace-redirect' === self::current_tab() ) {
			$page['sub_title'] = __( 'Moved a page? Update every link to it and redirect the old URL, in one step.', 'real-time-auto-find-and-replace' );
			$page['content']   = $this->tabs_html() . $this->replace_redirect_html();

			$this->add_pro_note( $page, $this->replace_redirect_pro_note_html() );
		} elseif ( 'not-found' === self::current_tab() ) {
			$page['sub_title'] = __( 'See which missing URLs people are actually asking for, and send them somewhere useful.', 'real-time-auto-find-and-replace' );

			// The tab's own control, inside the section its tab opens rather
			// than in the builder's well above the strip - which would hang one
			// tab's controls over every tab's row.
			$page['content'] = $this->tabs_html()
				. SectionCard::stack(
					$this->monitor_card_html()
					. $this->not_found_table_html()
				);

			$this->add_pro_note( $page, $this->not_found_pro_note_html() );
		} else {
			$tab = self::current_tab();

			// Our own markup is what the filters are handed, so a module that
			// owns another tab replaces it, one that owns no tab leaves it
			// alone, and one that needs to interrupt this tab for a single
			// request can do that without a page of its own.
			$content = 'redirects' === $tab
				? SectionCard::stack( $this->form_html() . $this->table_html() )
				: '';

			// Same filter it always was, rendered inside the section the tab
			// opens rather than in the builder's well above the strip.
			$well = (string) apply_filters( 'bfr_redirect_screen_well', '', $tab );
			$body = (string) apply_filters( 'bfr_redirect_screen_content', $content, $tab );

			// Nothing answered: pro is absent, or the licence does not carry it.
			$page['content'] = $this->tabs_html()
				. self::well_html( $well )
				. ( '' !== $body ? $body : self::locked_html( $tab ) );

			$this->add_pro_note( $page, $this->pro_note_html() );
		}

		$builder = new AdminPageBuilder();

		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo $builder->generate_page( $page );
	}

	/**
	 * The add-a-redirect form, built with the house form builder.
	 *
	 * @return string
	 */
	private function form_html() {
		$form   = new FormBuilder();
		$types  = Validator::all_types();
		$fields = array(
			'bfr_redirect[source]'      => array(
				'title'       => __( 'Redirect from', 'real-time-auto-find-and-replace' ),
				'type'        => 'text',
				'class'       => 'form-control',
				'required'    => true,
				'value'       => '',
				'placeholder' => '/old-page/',
				'desc_tip'    => __( 'A path on this site, such as /old-page/. The home page, wp-admin and the login screen cannot be redirected.', 'real-time-auto-find-and-replace' ),
			),
			'bfr_redirect[destination]' => array(
				'title'       => __( 'Redirect to', 'real-time-auto-find-and-replace' ),
				'type'        => 'text',
				'class'       => 'form-control',
				'required'    => true,
				'value'       => '',
				'placeholder' => '/new-page/',
				'desc_tip'    => __( 'A path on this site, or a full https:// address.', 'real-time-auto-find-and-replace' ),
			),
		);

		if ( count( $types ) > 1 ) {
			$options = array();

			foreach ( $types as $type ) {
				$options[ (string) $type ] = (string) $type;
			}

			$fields['bfr_redirect[type]'] = array(
				'title'    => __( 'Type', 'real-time-auto-find-and-replace' ),
				'type'     => 'select',
				'class'    => 'form-control',
				'options'  => $options,
				'value'    => '301',
				'desc_tip' => __( '301 is permanent and passes ranking to the new URL. 302 and 307 are temporary.', 'real-time-auto-find-and-replace' ),
			);
		} else {
			$fields['bfr_redirect[type]'] = array(
				'title'             => __( 'Type', 'real-time-auto-find-and-replace' ),
				'type'              => 'text',
				'class'             => 'form-control',
				'value'             => '301',
				'custom_attributes' => array( 'readonly' => 'readonly' ),
				'desc_tip'          => __( 'Permanent. Search engines transfer the old URL\'s ranking to the new one. Temporary types are available in Pro.', 'real-time-auto-find-and-replace' ),
			);
		}

		return SectionCard::render(
			array(
				'icon'    => 'randomize',
				'eyebrow' => __( 'Redirect Manager', 'real-time-auto-find-and-replace' ),
				'title'   => __( 'Add a redirect', 'real-time-auto-find-and-replace' ),
				'desc'    => __( 'Point one address on this site at another. It takes effect as soon as you save it.', 'real-time-auto-find-and-replace' ),
				'body'    => '<div class="bfrmaint-redirect-form">'
					. (string) $form->generate_html_fields( $fields )
					. '</div>',
				'foot'    => sprintf(
					'<button type="button" class="btn btn-custom-submit" id="bfr-redirect-save">%s</button>
					<span id="bfr-redirect-notice" class="bfrmaint-notice" role="status" aria-live="polite"></span>',
					esc_html__( 'Add redirect', 'real-time-auto-find-and-replace' )
				),
			)
		);
	}

	/**
	 * The list of existing redirects.
	 *
	 * Its own card, not a heading in the same column as the form above it. The
	 * two are different kinds of thing - one is a control you fill in, the
	 * other is the state of the site - and running them together was the whole
	 * reason this screen read as one undifferentiated page.
	 *
	 * @return string
	 */
	private function table_html() {
		$table = new RedirectsTable();
		$table->prepare_items();

		$total = (int) $table->get_pagination_arg( 'total_items' );

		ob_start();
		$table->search_box( __( 'Search redirects', 'real-time-auto-find-and-replace' ), 'bfr-redirect-search' );
		$search = (string) ob_get_clean();

		ob_start();
		$table->display();
		$list = (string) ob_get_clean();

		// The search box has to be inside a form, and so the whole card is: it
		// is one control acting on one list, and a form wrapped around the
		// table alone would leave the search box outside it.
		return sprintf(
			'<form method="get"><input type="hidden" name="page" value="%1$s" />%2$s</form>',
			esc_attr( self::SLUG ),
			SectionCard::render(
				array(
					'icon'       => 'list-view',
					'eyebrow'    => __( 'On this site', 'real-time-auto-find-and-replace' ),
					'title'      => __( 'Your redirects', 'real-time-auto-find-and-replace' ),
					'desc'       => __( 'Every rule currently in force, with how often each one has been followed.', 'real-time-auto-find-and-replace' ),
					// The count and the search go in the head, not above the
					// table: they are how you find your way around this card,
					// and the head is where the card says what it is.
					'meta'       => sprintf(
						'<span class="bfrmaint-pill%1$s">%2$s</span>',
						$total > 0 ? '' : ' is-off',
						esc_html(
							sprintf(
								/* translators: %s: number of redirects */
								_n( '%s rule', '%s rules', $total, 'real-time-auto-find-and-replace' ),
								number_format_i18n( $total )
							)
						)
					) . $search,
					'body'       => $list,
					'body_class' => self::list_body_class( $table ),
				)
			)
		);
	}

	/**
	 * How a list-table card's body is dressed.
	 *
	 * The top table nav carries nothing but the row count - which the card head
	 * already states - and the pager. On a single page that leaves an empty
	 * band between the head and the first row, so it is only asked for when
	 * there is actually a page to turn.
	 *
	 * @param \WP_List_Table $table A prepared table.
	 * @return string
	 */
	private static function list_body_class( $table ) {
		$pages = (int) $table->get_pagination_arg( 'total_pages' );

		return $pages > 1 ? 'is-flush has-tablenav' : 'is-flush';
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
			'not-found'        => 'not_found.basic',
			'replace-redirect' => 'replace_redirect.single',
			'tools'            => 'redirects.import_export',
		);
	}

	/**
	 * Which tab is being viewed.
	 *
	 * Validated against what is actually registered, so a tab another module
	 * added is honoured and a bookmark from when it was installed falls back to
	 * the list rather than to a blank panel.
	 *
	 * @return string
	 */
	public static function current_tab() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$tab = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'redirects';

		return isset( self::tabs()[ $tab ] ) ? $tab : 'redirects';
	}

	/**
	 * The builder's well, moved into the section.
	 *
	 * Nothing at all when there is nothing to put in it: the wrapper on its own
	 * is an empty tinted band under the tab strip.
	 *
	 * @param string $html Markup for the well.
	 * @return string
	 */
	private static function well_html( $html ) {
		$html = (string) $html;

		return '' !== trim( $html ) ? '<div class="well">' . $html . '</div>' : '';
	}

	/**
	 * Attach the pro note, when there is one.
	 *
	 * The wrapper is only asked for alongside a note, because on its own it
	 * renders as an empty bordered box above the footer.
	 *
	 * @param array  $page Page args, by reference.
	 * @param string $note The note, possibly empty.
	 * @return void
	 */
	private function add_pro_note( array &$page, $note ) {
		$note = (string) $note;

		if ( '' === trim( $note ) ) {
			return;
		}

		$page['before_footer']         = $note;
		$page['before_footer_wrapper'] = true;
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
	 * A tab this install cannot render.
	 *
	 * The 404 figure is real: free's monitor records unhandled 404s whether or
	 * not the tab that lists them is available, so the count describes this
	 * site rather than the product.
	 *
	 * @param string $tab Tab key.
	 * @return string
	 */
	public static function locked_html( $tab ) {
		if ( 'not-found' === $tab ) {
			$new = NotFoundRepository::count( NotFoundRepository::STATUS_NEW );

			return LockedTab::panel(
				array(
					'title'   => __( '404 Monitor', 'real-time-auto-find-and-replace' ),
					'measure' => $new > 0
						? sprintf(
							/* translators: %d: number of unhandled 404s */
							_n(
								'%d address on this site has been requested and returned nothing.',
								'%d addresses on this site have been requested and returned nothing.',
								$new,
								'real-time-auto-find-and-replace'
							),
							$new
						)
						: '',
					'body'    => __( 'Every address somebody asked for and did not get, with how often and where they came from - so you can send the ones that matter somewhere useful.', 'real-time-auto-find-and-replace' ),
					'points'  => array(
						__( 'See which missing pages are actually costing you visits', 'real-time-auto-find-and-replace' ),
						__( 'Turn a 404 into a redirect in one click', 'real-time-auto-find-and-replace' ),
						__( 'Group the near-identical ones instead of scrolling past them', 'real-time-auto-find-and-replace' ),
					),
				)
			);
		}

		if ( 'replace-redirect' === $tab ) {
			return LockedTab::panel(
				array(
					'title'  => __( 'Replace + Redirect', 'real-time-auto-find-and-replace' ),
					'body'   => __( 'Change a URL everywhere it appears in your content and set up a redirect from the old one, in a single step - so nothing breaks for anybody who saved the old link.', 'real-time-auto-find-and-replace' ),
					'points' => array(
						__( 'One action instead of a replace and then a redirect', 'real-time-auto-find-and-replace' ),
						__( 'See exactly what will change before it changes', 'real-time-auto-find-and-replace' ),
						__( 'The old address keeps working for search engines and bookmarks', 'real-time-auto-find-and-replace' ),
					),
				)
			);
		}

		if ( 'tools' === $tab ) {
			return LockedTab::panel(
				array(
					'title'  => __( 'Redirect tools', 'real-time-auto-find-and-replace' ),
					'body'   => __( 'The free version handles one address going to one other address. Tools adds the patterns, the bulk operations and the history.', 'real-time-auto-find-and-replace' ),
					'points' => array(
						__( 'Match a whole folder, or a pattern, instead of one URL at a time', 'real-time-auto-find-and-replace' ),
						__( 'Import and export your redirects as a spreadsheet', 'real-time-auto-find-and-replace' ),
						__( 'See which redirects are actually being used, and retire the rest', 'real-time-auto-find-and-replace' ),
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
		// Every tab, always. Pro registers 'tools' through the filter below;
		// declaring it here as well is what makes it visible - and locked - on
		// a free install.
		$tabs = array(
			'redirects'        => __( 'Redirect Manager', 'real-time-auto-find-and-replace' ),
			'not-found'        => __( '404 Monitor', 'real-time-auto-find-and-replace' ),
			'replace-redirect' => __( 'Replace + Redirect', 'real-time-auto-find-and-replace' ),
			'tools'            => __( 'Tools', 'real-time-auto-find-and-replace' ),
		);

		/**
		 * Filter the Redirects tabs.
		 *
		 * Pro adds import/export here without a new menu item.
		 *
		 * @param array $tabs Tab key => label.
		 */
		$tabs = apply_filters( 'bfr_redirect_screens', $tabs );

		return is_array( $tabs ) && isset( $tabs['redirects'] )
			? $tabs
			: array( 'redirects' => __( 'Redirects', 'real-time-auto-find-and-replace' ) ) + (array) $tabs;
	}

	/**
	 * The monitor on/off control and its explanation.
	 *
	 * Recording visitor requests is opt-in, so the control that turns it on is
	 * the first thing on the tab rather than buried in a settings page.
	 *
	 * @return string
	 */
	private function monitor_card_html() {
		$enabled = NotFoundRepository::is_enabled();

		$body = '<p class="bfrmaint-metric-note">' . (
			$enabled
				? esc_html(
					sprintf(
						/* translators: 1: daily row budget, 2: retention in days */
						__( 'Recording. Up to %1$d new URLs a day are kept, for %2$d days. Crawlers and asset requests are skipped.', 'real-time-auto-find-and-replace' ),
						NotFoundRepository::daily_budget(),
						NotFoundRepository::retention_days()
					)
				)
				: esc_html__( 'Not recording. Turn this on to find out which missing URLs people are asking for.', 'real-time-auto-find-and-replace' )
		) . '</p>';

		return SectionCard::render(
			array(
				'icon'    => 'visibility',
				'eyebrow' => __( '404 Monitor', 'real-time-auto-find-and-replace' ),
				'title'   => __( 'Recording missing URLs', 'real-time-auto-find-and-replace' ),
				// State-neutral: the pill and the line in the body say whether
				// it is running, and a description that only reads correctly
				// in one of the two states is worse than none.
				'desc'    => __( 'Keeps a record of the addresses people ask for that do not exist on this site.', 'real-time-auto-find-and-replace' ),
				'meta'    => sprintf(
					'<span class="bfrmaint-pill %1$s">%2$s</span>',
					$enabled ? 'is-on' : 'is-off',
					$enabled
						? esc_html__( 'On', 'real-time-auto-find-and-replace' )
						: esc_html__( 'Off', 'real-time-auto-find-and-replace' )
				),
				'body'    => $body,
				'foot'    => sprintf(
					'<div class="bfrmaint-scanbar">
						<button type="button" class="btn btn-custom-submit" id="bfr-404-toggle" data-enabled="%1$d">%2$s</button>
						<span id="bfr-404-notice" class="bfrmaint-notice" role="status" aria-live="polite"></span>
					</div>',
					$enabled ? 1 : 0,
					$enabled
						? esc_html__( 'Stop recording 404s', 'real-time-auto-find-and-replace' )
						: esc_html__( 'Start recording 404s', 'real-time-auto-find-and-replace' )
				),
			)
		);
	}

	/**
	 * The 404 list.
	 *
	 * @return string
	 */
	private function not_found_table_html() {
		$table = new NotFoundTable();
		$table->prepare_items();

		$total = (int) $table->get_pagination_arg( 'total_items' );

		ob_start();
		$table->search_box( __( 'Search URLs', 'real-time-auto-find-and-replace' ), 'bfr-404-search' );
		$search = (string) ob_get_clean();

		ob_start();
		$table->display();
		$list = (string) ob_get_clean();

		return sprintf(
			'<form method="get"><input type="hidden" name="page" value="%1$s" /><input type="hidden" name="tab" value="not-found" />%2$s</form>',
			esc_attr( self::SLUG ),
			SectionCard::render(
				array(
					'icon'       => 'warning',
					'eyebrow'    => __( 'Recorded', 'real-time-auto-find-and-replace' ),
					'title'      => __( 'Missing URLs', 'real-time-auto-find-and-replace' ),
					'desc'       => __( 'Addresses somebody asked for that returned nothing, most recent first.', 'real-time-auto-find-and-replace' ),
					'meta'       => sprintf(
						'<span class="bfrmaint-pill%1$s">%2$s</span>',
						$total > 0 ? ' is-warn' : ' is-off',
						esc_html(
							sprintf(
								/* translators: %s: number of recorded URLs */
								_n( '%s URL', '%s URLs', $total, 'real-time-auto-find-and-replace' ),
								number_format_i18n( $total )
							)
						)
					) . $search,
					'body'       => $list,
					'body_class' => self::list_body_class( $table ),
				)
			)
		);
	}

	/**
	 * The Replace + Redirect form.
	 *
	 * Two fields and a preview. The preview is not optional decoration: this
	 * operation rewrites content across the whole site, so nothing is applied
	 * until the person has seen what it would touch.
	 *
	 * @return string
	 */
	private function replace_redirect_html() {
		$form   = new FormBuilder();
		$fields = array(
			'bfr_rr[from]' => array(
				'title'       => __( 'Old URL', 'real-time-auto-find-and-replace' ),
				'type'        => 'text',
				'class'       => 'form-control',
				'required'    => true,
				'value'       => '',
				'placeholder' => home_url( '/old-page/' ),
				'desc_tip'    => __( 'The URL that is moving. Every link to it will be updated.', 'real-time-auto-find-and-replace' ),
			),
			'bfr_rr[to]'   => array(
				'title'       => __( 'New URL', 'real-time-auto-find-and-replace' ),
				'type'        => 'text',
				'class'       => 'form-control',
				'required'    => true,
				'value'       => '',
				'placeholder' => home_url( '/new-page/' ),
				'desc_tip'    => __( 'Where it lives now. A 301 redirect is created from the old URL to this one.', 'real-time-auto-find-and-replace' ),
			),
		);

		$card = SectionCard::render(
			array(
				'icon'    => 'admin-links',
				'eyebrow' => __( 'Replace + Redirect', 'real-time-auto-find-and-replace' ),
				'title'   => __( 'Move a URL', 'real-time-auto-find-and-replace' ),
				'desc'    => __( 'Every link to the old address is rewritten and the old address is redirected. Nothing changes until you have seen the preview.', 'real-time-auto-find-and-replace' ),
				'body'    => '<div class="bfrmaint-redirect-form">'
					. (string) $form->generate_html_fields( $fields )
					. '</div>',
				'foot'    => sprintf(
					'<button type="button" class="btn btn-custom-submit" id="bfr-rr-preview">%1$s</button>
					<span id="bfr-rr-notice" class="bfrmaint-notice" role="status" aria-live="polite"></span>',
					esc_html__( 'Preview changes', 'real-time-auto-find-and-replace' )
				),
			)
		);

		return SectionCard::stack(
			$card . '<div id="bfr-rr-result" class="bfrmaint-rr-result" hidden></div>'
		);
	}

	/**
	 * The honest pro note for the Replace + Redirect tab.
	 *
	 * @return string
	 */
	private function replace_redirect_pro_note_html() {
		if ( Entitlements::can( 'replace_redirect.bulk' ) ) {
			return '';
		}

		return sprintf(
			'<div class="bfrmaint-pro-note"><p><strong>%1$s</strong></p><p>%2$s</p></div>',
			esc_html__( 'Free moves one URL at a time, with a preview before anything changes.', 'real-time-auto-find-and-replace' ),
			esc_html__( 'Pro runs a whole list of moves in one go, suggests destinations for URLs that are already 404ing, and re-checks each one afterwards.', 'real-time-auto-find-and-replace' )
		);
	}
	/**
	 * The honest pro note for the 404 tab.
	 *
	 * @return string
	 */
	private function not_found_pro_note_html() {
		if ( Entitlements::can( 'not_found.ai_destination' ) ) {
			return '';
		}

		return sprintf(
			'<div class="bfrmaint-pro-note"><p><strong>%1$s</strong></p><p>%2$s</p></div>',
			esc_html__( 'Free records the 404s and lets you redirect them one at a time.', 'real-time-auto-find-and-replace' ),
			esc_html__( 'Pro groups similar URLs, suggests the best destination for each with AI, finds the pages still linking to them, and creates the redirects in bulk.', 'real-time-auto-find-and-replace' )
		);
	}
	/**
	 * The honest pro note.
	 *
	 * @return string
	 */
	private function pro_note_html() {
		if ( Entitlements::can( 'redirects.regex' ) ) {
			return '';
		}

		return sprintf(
			'<div class="bfrmaint-pro-note"><p><strong>%1$s</strong></p><p>%2$s</p></div>',
			esc_html__( 'Free covers permanent (301) redirects for exact URLs.', 'real-time-auto-find-and-replace' ),
			esc_html__( 'Pro adds temporary (302 and 307) redirects, wildcard and pattern matching, automatic redirects when you change a post slug, bulk import and export, and hit analytics.', 'real-time-auto-find-and-replace' )
		);
	}
}
