<?php namespace RealTimeAutoFindReplace\Maintenance\Admin;

/**
 * The card every Maintenance screen is built out of.
 *
 * A tinted head carrying an icon, an eyebrow, a title and one sentence about
 * what the card is; a white body; and, where the card has a primary action, a
 * footer bar the button lives on. That is the whole component.
 *
 * It exists because the screens under the tab strip used to be a flat column of
 * <h3> and <p>. On Redirects the add form and the list of saved rules ran
 * together with nothing but a heading between them; on Site Health the score,
 * the counts and the log were three unrelated things in one undivided box.
 * Nothing on either screen told you where one thing ended and the next began.
 *
 * The head is what does the separating. A border on its own is not enough
 * against a white panel body, and the sentence under the title is where the
 * explanation goes that used to be a loose paragraph in the flow.
 *
 * Styled from .bfrmaint-section in maintenance.css. Pro renders the same markup
 * on the tabs it owns, through a class of its own with the same signature - so
 * the class names here are a contract between the two plugins, not an internal
 * detail.
 *
 * @package Maintenance
 * @since 1.10.0
 * @author M.Tuhin <info@codesolz.net>
 */

if ( ! defined( 'CS_RTAFAR_VERSION' ) ) {
	exit;
}

class SectionCard {

	/**
	 * One card.
	 *
	 * $meta, $body and $foot are markup and are used as given; everything else
	 * is plain text and is escaped here.
	 *
	 * @param array $args {
	 *     @type string $icon       Dashicon name, without the prefix.
	 *     @type string $eyebrow    Small label above the title.
	 *     @type string $title      What this card is.
	 *     @type string $desc       One sentence about it.
	 *     @type string $meta       Markup for the far end of the head.
	 *     @type string $body       Markup for the body.
	 *     @type string $body_class Extra class on the body.
	 *     @type string $foot       Markup for the action bar.
	 *     @type string $class      Extra class on the card itself.
	 * }
	 * @return string
	 */
	public static function render( array $args ) {
		$args = array_merge(
			array(
				'icon'       => 'admin-generic',
				'eyebrow'    => '',
				'title'      => '',
				'desc'       => '',
				'meta'       => '',
				'body'       => '',
				'body_class' => '',
				'foot'       => '',
				'class'      => '',
			),
			$args
		);

		$html = sprintf(
			'<section class="bfrmaint-section %s"><div class="bfrmaint-section-head">',
			esc_attr( $args['class'] )
		);

		// Decoration. Every card says what it is in words immediately beside
		// this, so there is nothing here for a screen reader to miss.
		$html .= sprintf(
			'<span class="bfrmaint-section-icon" aria-hidden="true"><span class="dashicons dashicons-%s"></span></span>',
			esc_attr( $args['icon'] )
		);

		$html .= '<div class="bfrmaint-section-heading">';

		if ( '' !== $args['eyebrow'] ) {
			$html .= '<p class="bfrmaint-section-eyebrow">' . esc_html( $args['eyebrow'] ) . '</p>';
		}

		$html .= '<h3 class="bfrmaint-section-title">' . esc_html( $args['title'] ) . '</h3>';

		if ( '' !== $args['desc'] ) {
			$html .= '<p class="bfrmaint-section-desc">' . esc_html( $args['desc'] ) . '</p>';
		}

		$html .= '</div>';

		if ( '' !== $args['meta'] ) {
			$html .= '<div class="bfrmaint-section-meta">' . $args['meta'] . '</div>';
		}

		$html .= '</div>';

		$html .= sprintf(
			'<div class="bfrmaint-section-body %s">%s</div>',
			esc_attr( $args['body_class'] ),
			$args['body']
		);

		if ( '' !== trim( (string) $args['foot'] ) ) {
			$html .= '<div class="bfrmaint-section-foot">' . $args['foot'] . '</div>';
		}

		return $html . '</section>';
	}

	/**
	 * The column the cards sit in, which is what puts air between them.
	 *
	 * @param string $html One or more cards.
	 * @return string
	 */
	public static function stack( $html ) {
		$html = (string) $html;

		return '' !== trim( $html ) ? '<div class="bfrmaint-stack">' . $html . '</div>' : '';
	}

	/**
	 * A status chip for a card head.
	 *
	 * Never the only carrier of meaning: whatever a chip says, the card says in
	 * a sentence as well.
	 *
	 * @param string $text Plain text.
	 * @param string $tone '', 'is-on', 'is-off' or 'is-warn'.
	 * @return string
	 */
	public static function pill( $text, $tone = '' ) {
		return sprintf(
			'<span class="bfrmaint-pill %1$s">%2$s</span>',
			esc_attr( $tone ),
			esc_html( $text )
		);
	}
}
