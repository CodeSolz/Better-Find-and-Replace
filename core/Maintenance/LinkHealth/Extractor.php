<?php namespace RealTimeAutoFindReplace\Maintenance\LinkHealth;

/**
 * Pulls every URL out of a piece of post content.
 *
 * Regex rather than DOMDocument, deliberately. Post content is a fragment, not
 * a document; it is full of shortcodes, block comments and half-closed markup
 * that DOMDocument rewrites on parse. Rewriting is unacceptable here because
 * the byte offset this class records is what a later anchored replacement uses
 * to find the URL again - an offset into a document the parser reflowed points
 * at the wrong bytes.
 *
 * Everything is recorded, nothing is judged. Whether a URL is internal, broken,
 * or worth reporting is decided further along the pipeline; a class that also
 * had opinions would be impossible to test and impossible to reuse for media
 * health later.
 *
 * No WordPress functions, so the unit suite exercises it directly.
 *
 * @package Maintenance
 * @since 1.10.0
 * @author M.Tuhin <info@codesolz.net>
 */

if ( ! defined( 'CS_RTAFAR_VERSION' ) ) {
	exit;
}

class Extractor {

	/**
	 * Content larger than this is skipped rather than scanned.
	 *
	 * A 2 MB post is either a page-builder dump or an import artefact. Running
	 * half a dozen backtracking patterns over it risks hitting PCRE's limits
	 * mid-scan, and a scanner that dies on one post takes the batch with it.
	 */
	const MAX_CONTENT_BYTES = 2097152;

	/**
	 * Tags whose src/href we care about, beyond the anchor.
	 *
	 * @var array
	 */
	private static $media_tags = array( 'img', 'iframe', 'video', 'audio', 'source', 'embed', 'track', 'object' );

	/**
	 * Attribute values that are never links to a page.
	 *
	 * Two families: values that go nowhere at all (an in-page anchor, an inline
	 * data URI, a script handler) and values that open something other than a
	 * web page (mail, telephone, messaging). Neither can be broken in a way
	 * this scanner could detect or a user could fix, so they are dropped at
	 * extraction rather than carried through the pipeline to be skipped later.
	 *
	 * @var array
	 */
	private static $ignored_prefixes = array(
		'#',
		'data:',
		'javascript:',
		'about:',
		'vbscript:',
		'file:',
		'mailto:',
		'tel:',
		'sms:',
		'callto:',
		'skype:',
		'whatsapp:',
		'{{',
		'%7B%7B',
	);

	/**
	 * Every URL occurrence in a piece of content.
	 *
	 * @param string $content Raw post content.
	 * @return array List of occurrences, each:
	 *               url    - the URL, HTML-entity decoded, ready to normalise
	 *               raw    - the exact substring as it appears in $content
	 *               offset - byte offset of `raw` within $content
	 *               type   - link | image | media | block | embed
	 *               anchor - anchor text, for links only
	 */
	public static function extract( $content ) {
		$content = (string) $content;

		if ( '' === $content || strlen( $content ) > self::MAX_CONTENT_BYTES ) {
			return array();
		}

		$found = array_merge(
			self::anchors( $content ),
			self::media( $content ),
			self::blocks( $content ),
			self::embeds( $content )
		);

		// A URL can be found twice - a Gutenberg image block carries the URL in
		// both its JSON comment and its rendered <img>. Same bytes, same
		// offset, one occurrence.
		//
		// Shapes are filled in here rather than at each of the five call sites,
		// so a caller never has to guess whether a key is present.
		$defaults = array(
			'url'          => '',
			'raw'          => '',
			'offset'       => 0,
			'type'         => 'link',
			'anchor'       => '',
			'outer_offset' => -1,
			'outer_length' => 0,
			'inner'        => '',
		);

		$seen   = array();
		$unique = array();

		foreach ( $found as $one ) {
			$key = $one['offset'] . ':' . $one['raw'];

			if ( isset( $seen[ $key ] ) ) {
				continue;
			}

			$seen[ $key ] = true;
			$unique[]     = array_merge( $defaults, $one );
		}

		usort(
			$unique,
			function ( $a, $b ) {
				return $a['offset'] - $b['offset'];
			}
		);

		return $unique;
	}

	/**
	 * Group occurrences by URL.
	 *
	 * The issue model records one row per (post, URL) with a count, not one row
	 * per occurrence - twelve links to the same dead page are one problem with
	 * one fix.
	 *
	 * @param array $occurrences Output of extract().
	 * @return array url => array( count, type, anchors, first_offset, occurrences )
	 */
	public static function group( array $occurrences ) {
		$groups = array();

		foreach ( $occurrences as $one ) {
			$url = $one['url'];

			if ( ! isset( $groups[ $url ] ) ) {
				$groups[ $url ] = array(
					'url'          => $url,
					'count'        => 0,
					'type'         => $one['type'],
					'anchors'      => array(),
					'first_offset' => $one['offset'],
					'occurrences'  => array(),
				);
			}

			++$groups[ $url ]['count'];
			$groups[ $url ]['occurrences'][] = $one;

			if ( '' !== $one['anchor'] && ! in_array( $one['anchor'], $groups[ $url ]['anchors'], true ) ) {
				$groups[ $url ]['anchors'][] = $one['anchor'];
			}
		}

		return $groups;
	}

	/**
	 * <a href="..."> plus its anchor text.
	 *
	 * @param string $content Raw content.
	 * @return array
	 */
	private static function anchors( $content ) {
		$out = array();

		if ( ! preg_match_all( '/<a\b([^>]*)>(.*?)<\/a>/is', $content, $matches, PREG_OFFSET_CAPTURE | PREG_SET_ORDER ) ) {
			return $out;
		}

		foreach ( $matches as $match ) {
			$attrs        = $match[1][0];
			$attrs_offset = $match[1][1];
			$href         = self::attribute( $attrs, 'href' );

			if ( null === $href || ! self::is_interesting( $href['value'] ) ) {
				continue;
			}

			$out[] = array(
				'url'          => self::decode( $href['value'] ),
				'raw'          => $href['value'],
				'offset'       => $attrs_offset + $href['offset'],
				'type'         => 'link',
				'anchor'       => self::anchor_text( $match[2][0] ),

				// The whole <a ...>...</a>, which is what unlinking removes.
				// Recorded here because only this pass knows where the element
				// starts and ends; recovering it later would mean parsing twice
				// and risking a different answer the second time.
				'outer_offset' => $match[0][1],
				'outer_length' => strlen( $match[0][0] ),
				'inner'        => $match[2][0],
			);
		}

		return $out;
	}

	/**
	 * Media tag attributes: src, srcset, poster and data.
	 *
	 * @param string $content Raw content.
	 * @return array
	 */
	private static function media( $content ) {
		$out     = array();
		$pattern = '/<(' . implode( '|', self::$media_tags ) . ')\b([^>]*)>/is';

		if ( ! preg_match_all( $pattern, $content, $matches, PREG_OFFSET_CAPTURE | PREG_SET_ORDER ) ) {
			return $out;
		}

		foreach ( $matches as $match ) {
			$tag          = strtolower( $match[1][0] );
			$attrs        = $match[2][0];
			$attrs_offset = $match[2][1];
			$type         = 'img' === $tag ? 'image' : 'media';

			foreach ( array( 'src', 'poster', 'data' ) as $name ) {
				$attr = self::attribute( $attrs, $name );

				if ( null === $attr || ! self::is_interesting( $attr['value'] ) ) {
					continue;
				}

				$out[] = array(
					'url'    => self::decode( $attr['value'] ),
					'raw'    => $attr['value'],
					'offset' => $attrs_offset + $attr['offset'],
					'type'   => $type,
					'anchor' => '',
				);
			}

			$srcset = self::attribute( $attrs, 'srcset' );

			if ( null !== $srcset ) {
				foreach ( self::srcset_urls( $srcset['value'] ) as $candidate ) {
					if ( ! self::is_interesting( $candidate['value'] ) ) {
						continue;
					}

					$out[] = array(
						'url'    => self::decode( $candidate['value'] ),
						'raw'    => $candidate['value'],
						'offset' => $attrs_offset + $srcset['offset'] + $candidate['offset'],
						'type'   => $type,
						'anchor' => '',
					);
				}
			}
		}

		return $out;
	}

	/**
	 * URLs inside Gutenberg block attributes.
	 *
	 * Block comments carry JSON, and JSON escapes every forward slash, so the
	 * bytes in the document are `https:\/\/example.com\/x`. The offset recorded
	 * is of those escaped bytes - which is correct, because that is what a
	 * replacement has to rewrite.
	 *
	 * @param string $content Raw content.
	 * @return array
	 */
	private static function blocks( $content ) {
		$out = array();

		if ( false === strpos( $content, '<!-- wp:' ) ) {
			return $out;
		}

		if ( ! preg_match_all( '/<!--\s*wp:[a-z0-9\/_-]+\s+(\{.*?\})\s*\/?-->/is', $content, $matches, PREG_OFFSET_CAPTURE | PREG_SET_ORDER ) ) {
			return $out;
		}

		foreach ( $matches as $match ) {
			$json        = $match[1][0];
			$json_offset = $match[1][1];

			// Locate URL-shaped values by scanning the raw JSON, so the offset
			// stays true to the document rather than to a decoded copy.
			if ( ! preg_match_all( '/"((?:https?:)?\\\\\/\\\\\/[^"]+|\\\\\/[^"]*)"/i', $json, $urls, PREG_OFFSET_CAPTURE | PREG_SET_ORDER ) ) {
				continue;
			}

			foreach ( $urls as $url ) {
				$raw = $url[1][0];

				if ( ! self::is_interesting( stripslashes( $raw ) ) ) {
					continue;
				}

				$out[] = array(
					'url'    => self::decode( stripslashes( $raw ) ),
					'raw'    => $raw,
					'offset' => $json_offset + $url[1][1],
					'type'   => 'block',
					'anchor' => '',
				);
			}
		}

		return $out;
	}

	/**
	 * [embed]URL[/embed] and bare URLs on their own line.
	 *
	 * WordPress auto-embeds a URL that sits alone on a line, so those are real
	 * links even though no markup surrounds them.
	 *
	 * @param string $content Raw content.
	 * @return array
	 */
	private static function embeds( $content ) {
		$out = array();

		if ( preg_match_all( '/\[embed[^\]]*\](.*?)\[\/embed\]/is', $content, $matches, PREG_OFFSET_CAPTURE | PREG_SET_ORDER ) ) {
			foreach ( $matches as $match ) {
				$url = trim( $match[1][0] );

				if ( '' === $url || ! self::is_interesting( $url ) ) {
					continue;
				}

				$out[] = array(
					'url'    => self::decode( $url ),
					'raw'    => $match[1][0],
					'offset' => $match[1][1],
					'type'   => 'embed',
					'anchor' => '',
				);
			}
		}

		if ( preg_match_all( '/^[ \t]*(https?:\/\/[^\s<>"\']+)[ \t]*$/im', $content, $matches, PREG_OFFSET_CAPTURE | PREG_SET_ORDER ) ) {
			foreach ( $matches as $match ) {
				$out[] = array(
					'url'    => self::decode( $match[1][0] ),
					'raw'    => $match[1][0],
					'offset' => $match[1][1],
					'type'   => 'embed',
					'anchor' => '',
				);
			}
		}

		return $out;
	}

	/**
	 * One attribute's value and where it sits.
	 *
	 * @param string $attrs Attribute string from inside a tag.
	 * @param string $name  Attribute name.
	 * @return array|null array( value, offset ) - offset is relative to $attrs.
	 */
	private static function attribute( $attrs, $name ) {
		$pattern = '/(?:^|\s)' . preg_quote( $name, '/' ) . '\s*=\s*("([^"]*)"|\'([^\']*)\'|([^\s"\'>]+))/i';

		if ( ! preg_match( $pattern, $attrs, $match, PREG_OFFSET_CAPTURE ) ) {
			return null;
		}

		// Whichever quoting style matched is the group that holds the value.
		foreach ( array( 2, 3, 4 ) as $group ) {
			if ( isset( $match[ $group ] ) && -1 !== $match[ $group ][1] ) {
				return array(
					'value'  => $match[ $group ][0],
					'offset' => $match[ $group ][1],
				);
			}
		}

		return null;
	}

	/**
	 * Split a srcset into its URLs, keeping each one's position.
	 *
	 * @param string $srcset Raw srcset value.
	 * @return array List of array( value, offset ) relative to $srcset.
	 */
	private static function srcset_urls( $srcset ) {
		$out = array();

		if ( ! preg_match_all( '/([^\s,]+)(?:\s+[0-9.]+[wx])?/i', $srcset, $matches, PREG_OFFSET_CAPTURE | PREG_SET_ORDER ) ) {
			return $out;
		}

		foreach ( $matches as $match ) {
			$value = $match[1][0];

			if ( '' === trim( $value ) || preg_match( '/^[0-9.]+[wx]$/i', $value ) ) {
				continue;
			}

			$out[] = array(
				'value'  => $value,
				'offset' => $match[1][1],
			);
		}

		return $out;
	}

	/**
	 * Anchor text, with any nested markup removed.
	 *
	 * @param string $inner Inner HTML of the anchor.
	 * @return string
	 */
	private static function anchor_text( $inner ) {
		$text = preg_replace( '/<[^>]*>/', '', (string) $inner );
		$text = self::decode( $text );
		$text = preg_replace( '/\s+/u', ' ', $text );
		$text = trim( (string) $text );

		if ( strlen( $text ) > 255 ) {
			$text = substr( $text, 0, 255 );
		}

		return $text;
	}

	/**
	 * Is this attribute value a link to something we could check?
	 *
	 * @param string $value Raw attribute value.
	 * @return bool
	 */
	private static function is_interesting( $value ) {
		$value = trim( (string) $value );

		if ( '' === $value ) {
			return false;
		}

		foreach ( self::$ignored_prefixes as $prefix ) {
			if ( 0 === stripos( $value, $prefix ) ) {
				return false;
			}
		}

		// A merge tag or template placeholder is not a URL anybody can fix.
		if ( false !== strpos( $value, '{{' ) || false !== strpos( $value, '%%' ) ) {
			return false;
		}

		return true;
	}

	/**
	 * Decode HTML entities without needing WordPress.
	 *
	 * &amp; in an href is one ampersand as far as the server is concerned, so
	 * comparing or fetching the raw text would be wrong.
	 *
	 * @param string $value Raw value.
	 * @return string
	 */
	private static function decode( $value ) {
		return html_entity_decode( (string) $value, ENT_QUOTES | ENT_HTML5, 'UTF-8' );
	}
}
