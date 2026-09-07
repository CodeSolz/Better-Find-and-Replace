<?php namespace RealTimeAutoFindReplace\Maintenance\Admin;

use RealTimeAutoFindReplace\admin\builders\AdminPageBuilder;
use RealTimeAutoFindReplace\Maintenance\CodeInsert\Guard;
use RealTimeAutoFindReplace\Maintenance\CodeInsert\Snippets;
use RealTimeAutoFindReplace\Maintenance\CodeInsert\Validator;
use RealTimeAutoFindReplace\Maintenance\Support\Capabilities;
use RealTimeAutoFindReplace\Maintenance\Support\Entitlements;
use RealTimeAutoFindReplace\Maintenance\Admin\LockedTab;

/**
 * The Code Inserts screen.
 *
 * Three textareas and a warning. The only interesting thing about it is what it
 * does on the way in and on the way out.
 *
 * **On the way out**, every snippet goes through `esc_textarea()`. Skipping that
 * would make this screen the stored-XSS the printer was careful not to be: the
 * content is raw markup by design, and printing it back into a `<textarea>`
 * unescaped means a `</textarea>` in a snippet ends the field and everything
 * after it becomes live markup in the admin. The printer is allowed to be
 * unescaped because that is the feature; this is not.
 *
 * **On the way in**, a nonce and `Guard::can_edit()`, which is stricter than the
 * capability that got the user here. A user who can view but not save sees the
 * fields, disabled, and a sentence saying which permission is missing - not a
 * Save button that 403s.
 *
 * @package Maintenance
 * @since 1.10.0
 * @author M.Tuhin <info@codesolz.net>
 */

if ( ! defined( 'CS_RTAFAR_VERSION' ) ) {
	exit;
}

class CodeInsertScreen {

	/** The page slug. */
	const SLUG = 'cs-bfar-code-inserts';

	/** Nonce action for the save form. */
	const NONCE = 'bfar_code_inserts_save';

	/**
	 * The hook suffix, so assets load on this screen only.
	 *
	 * @var string
	 */
	private $hook = '';

	/**
	 * Findings from the last save, keyed by slot.
	 *
	 * @var array
	 */
	private $findings = array();

	/**
	 * What to tell the user about the last save.
	 *
	 * @var string
	 */
	private $notice = '';

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

		// The menu entry stays whether or not the licence carries the feature -
		// somebody who cannot see that this exists cannot decide they want it.
		// No marker in the label: the admin menu is a list of destinations, and
		// a badge in it competes with WordPress's own update counts for the same
		// few pixels. The screen itself says where the feature lives.
		$this->hook = add_submenu_page(
			CS_RTAFAR_PLUGIN_IDENTIFIER,
			__( 'Code Inserts', 'real-time-auto-find-and-replace' ),
			__( 'Code Inserts', 'real-time-auto-find-and-replace' ),
			Capabilities::for_module( 'code_inserts' ),
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
	 * The save happens here rather than in render(), so a successful save can
	 * still emit headers and so the page that follows shows stored state rather
	 * than submitted state.
	 *
	 * @return void
	 */
	public function on_load() {
		add_action( 'admin_enqueue_scripts', array( $this, 'assets' ) );

		ScreenNotices::suppress();

		$this->maybe_save();
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
	 * Handle a submitted form.
	 *
	 * @return void
	 */
	private function maybe_save() {
		if ( empty( $_POST['bfar_code_inserts_submit'] ) ) {
			return;
		}

		$nonce = isset( $_POST['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ) : '';

		if ( ! wp_verify_nonce( $nonce, self::NONCE ) ) {
			$this->notice = __( 'That form had expired. Nothing was saved - please try again.', 'real-time-auto-find-and-replace' );

			return;
		}

		// Stricter than the capability that opened this page, and checked here
		// rather than only in the markup: a disabled field is a courtesy, not a
		// permission check.
		if ( ! Guard::can_edit() ) {
			$this->notice = Guard::reason();

			return;
		}

		$saved = 0;

		foreach ( Snippets::slots() as $slot ) {
			$field = 'bfar_code_' . $slot;

			if ( ! isset( $_POST[ $field ] ) ) {
				continue;
			}

			// Deliberately NOT sanitised. The whole feature is storing markup as
			// typed - see Snippets - and any sanitiser that made it safe would
			// also make it not work. wp_unslash only reverses what PHP added.
			// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
			$content = (string) wp_unslash( $_POST[ $field ] );
			$enabled = ! empty( $_POST[ 'bfar_code_' . $slot . '_enabled' ] );

			$result = Snippets::save( $slot, $content, $enabled );

			if ( empty( $result['ok'] ) ) {
				$this->notice = $result['message'];

				continue;
			}

			++$saved;

			$findings = Validator::check( $content );

			if ( ! empty( $findings ) ) {
				$this->findings[ $slot ] = $findings;
			}
		}

		if ( '' === $this->notice && $saved > 0 ) {
			$this->notice = __( 'Saved.', 'real-time-auto-find-and-replace' );
		}
	}

	/**
	 * Render the page.
	 *
	 * @return void
	 */
	public function render() {
		if ( ! Guard::can_view() ) {
			wp_die( esc_html__( 'You do not have permission to view this page.', 'real-time-auto-find-and-replace' ) );
		}

		$page = array(
			'title'     => __( 'Code Inserts', 'real-time-auto-find-and-replace' ),
			'sub_title' => __( 'Add HTML, JavaScript or CSS to the header, the start of the body, or the footer of every page.', 'real-time-auto-find-and-replace' ),
		);

		if ( ! Entitlements::can( 'code_insert.global' ) ) {
			// No editor at all, rather than a disabled one. A textarea somebody
			// can type into and not save is worse than an honest explanation.
			$page['content'] = self::locked_html();
		} else {
			$page['well']    = $this->warning_html();
			$page['content'] = $this->form_html();
		}

		$builder = new AdminPageBuilder();

		// The builder assembles trusted markup: every value interpolated into
		// it is escaped at the point it is built, below.
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo $builder->generate_page( $page );
	}

	/**
	 * The screen, for an install without the feature.
	 *
	 * The figure is real where there is one: a site that had pro and lapsed
	 * still has its snippets stored, and telling somebody "your three snippets
	 * are still here" is both true and the most useful thing to say.
	 *
	 * @return string
	 */
	public static function locked_html() {
		$stored = 0;

		foreach ( Snippets::all() as $snippet ) {
			if ( '' !== trim( $snippet['content'] ) ) {
				++$stored;
			}
		}

		$measure = $stored > 0
			? sprintf(
				/* translators: %d: number of snippets already saved */
				_n(
					'%d snippet is still saved on this site and will start working again as soon as Pro is active.',
					'%d snippets are still saved on this site and will start working again as soon as Pro is active.',
					$stored,
					'real-time-auto-find-and-replace'
				),
				$stored
			)
			: '';

		return LockedTab::panel(
			array(
				'title'   => __( 'Code Inserts', 'real-time-auto-find-and-replace' ),
				'measure' => $measure,
				'body'    => __( 'Add tracking tags, verification codes, chat widgets or your own CSS to every page of your site - in the header, the start of the body, or the footer - without touching your theme.', 'real-time-auto-find-and-replace' ),
				'points'  => array(
					__( 'Survives a theme update, because it is not in your theme', 'real-time-auto-find-and-replace' ),
					__( 'Paste a tag exactly as your provider gave it to you', 'real-time-auto-find-and-replace' ),
					__( 'Choose which pages a snippet loads on, and which it does not', 'real-time-auto-find-and-replace' ),
				),
			)
		);
	}

	/**
	 * The warning that sits above the fields.
	 *
	 * Unmissable on purpose. Somebody pasting a snippet from an email deserves
	 * to be told, in one sentence, that this runs on every page of their site.
	 *
	 * @return string
	 */
	private function warning_html() {
		$out = '<div class="notice notice-warning inline"><p><strong>'
			. esc_html__( 'Anything you put here runs on every page of your site, for every visitor.', 'real-time-auto-find-and-replace' )
			. '</strong> '
			. esc_html__( 'Only paste code you understand or that came from a source you trust. HTML, JavaScript and CSS only - PHP is never run here.', 'real-time-auto-find-and-replace' )
			. '</p></div>';

		if ( ! Guard::can_edit() ) {
			$out .= '<div class="notice notice-info inline"><p>' . esc_html( Guard::reason() ) . '</p></div>';
		}

		if ( '' !== $this->notice ) {
			$out .= '<div class="notice notice-info inline"><p>' . esc_html( $this->notice ) . '</p></div>';
		}

		return $out;
	}

	/**
	 * The three fields.
	 *
	 * @return string
	 */
	private function form_html() {
		$can    = Guard::can_edit();
		$labels = Snippets::labels();
		$all    = Snippets::all();

		$out  = '<form method="post" action="' . esc_url( menu_page_url( self::SLUG, false ) ) . '">';
		$out .= wp_nonce_field( self::NONCE, '_wpnonce', true, false );

		foreach ( Snippets::slots() as $slot ) {
			$snippet = $all[ $slot ];
			$field   = 'bfar_code_' . $slot;

			$out .= '<div class="bfrmaint-code-slot">';
			$out .= '<h3>' . esc_html( isset( $labels[ $slot ] ) ? $labels[ $slot ] : $slot ) . '</h3>';
			$out .= '<p>' . esc_html( $this->slot_note( $slot ) ) . '</p>';

			$out .= sprintf(
				'<p><label><input type="checkbox" name="%1$s_enabled" value="1"%2$s%3$s /> %4$s</label></p>',
				esc_attr( $field ),
				checked( $snippet['enabled'], true, false ),
				$can ? '' : ' disabled="disabled"',
				esc_html__( 'Enabled', 'real-time-auto-find-and-replace' )
			);

			$out .= sprintf(
				'<p><textarea name="%1$s" id="%1$s" rows="8" class="large-text code" spellcheck="false"%2$s>%3$s</textarea></p>',
				esc_attr( $field ),
				$can ? '' : ' readonly="readonly"',
				// The one escaping that matters on this screen. Without it a
				// </textarea> inside a snippet ends the field and the rest
				// becomes live markup in the admin.
				esc_textarea( $snippet['content'] )
			);

			$out .= $this->findings_html( $slot );
			$out .= '</div>';
		}

		if ( $can ) {
			$out .= '<p><button type="submit" name="bfar_code_inserts_submit" value="1" class="btn btn-custom-submit">'
				. esc_html__( 'Save code', 'real-time-auto-find-and-replace' )
				. '</button></p>';
		}

		return $out . '</form>';
	}

	/**
	 * What this slot is for, in one sentence.
	 *
	 * @param string $slot One of Snippets::slots().
	 * @return string
	 */
	private function slot_note( $slot ) {
		if ( Snippets::HEADER === $slot ) {
			return __( 'Printed in the <head> of every page. Most analytics and verification tags go here.', 'real-time-auto-find-and-replace' );
		}

		if ( Snippets::BODY === $slot ) {
			return __( 'Printed immediately after the opening <body> tag, if your theme supports it. Tag manager no-script fallbacks go here.', 'real-time-auto-find-and-replace' );
		}

		return __( 'Printed just before the closing </body> tag. Chat widgets and anything that can wait until the page has loaded go here.', 'real-time-auto-find-and-replace' );
	}

	/**
	 * Advisory notes from the last save, for one slot.
	 *
	 * @param string $slot One of Snippets::slots().
	 * @return string
	 */
	private function findings_html( $slot ) {
		if ( empty( $this->findings[ $slot ] ) ) {
			return '';
		}

		$out = '';

		foreach ( $this->findings[ $slot ] as $finding ) {
			$class = Validator::ERROR === $finding['level'] ? 'notice-error' : 'notice-warning';

			$out .= '<div class="notice ' . esc_attr( $class ) . ' inline"><p>'
				. esc_html( $finding['message'] )
				. '</p></div>';
		}

		// Said explicitly, because a red box that did not stop the save is
		// confusing unless somebody tells you it was not meant to.
		return $out . '<p class="description">'
			. esc_html__( 'Your code was saved exactly as you typed it. These notes are advice, not changes.', 'real-time-auto-find-and-replace' )
			. '</p>';
	}
}
