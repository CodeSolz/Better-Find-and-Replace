<?php namespace RealTimeAutoFindReplace\Maintenance\CodeInsert;

/**
 * Telling somebody what looks wrong, and changing nothing.
 *
 * **Every finding here is advisory.** Nothing this class returns blocks a save
 * and nothing it returns edits the content. That is a deliberate and slightly
 * uncomfortable choice, so it is worth writing down why.
 *
 * The snippets that go in this box are tracking tags, consent banners, ad
 * network loaders and schema blocks - code somebody was handed by a third party
 * and told to paste in unchanged. A validator that "helpfully" closed a tag or
 * stripped a comment would produce a snippet that looks right, saves cleanly,
 * and silently does not work, and the site owner would have no way to tell that
 * we were the reason. A warning they can ignore is worth more than a correction
 * they cannot see.
 *
 * So this finds the five things that are nearly always mistakes - three tags
 * that never close, a document.write, and a pasted PHP block - says so plainly,
 * and gets out of the way.
 *
 * @package Maintenance
 * @since 1.10.0
 * @author M.Tuhin <info@codesolz.net>
 */

if ( ! defined( 'CS_RTAFAR_VERSION' ) ) {
	exit;
}

class Validator {

	/** Something that will not work. */
	const ERROR = 'error';

	/** Something that usually indicates a mistake. */
	const WARNING = 'warning';

	/**
	 * Look at a snippet.
	 *
	 * @param string $content Raw snippet.
	 * @return array List of array( level, code, message ). Empty when nothing looks wrong.
	 */
	public static function check( $content ) {
		$content = (string) $content;
		$out     = array();

		if ( '' === trim( $content ) ) {
			return $out;
		}

		// Written out rather than dispatched through call_user_func(). The
		// dynamic version was two lines shorter and read perfectly well, but
		// this module's one hard rule is that snippet content is never
		// executed - so it should contain no mechanism for calling a function
		// by name at all, and a reviewer should not have to check where the
		// name came from. tests/maintenance-code.php asserts the absence.
		$checks = array(
			self::unclosed_script( $content ),
			self::unclosed_comment( $content ),
			self::unclosed_style( $content ),
			self::document_write( $content ),
			self::php_tag( $content ),
		);

		foreach ( $checks as $found ) {
			if ( $found ) {
				$out[] = $found;
			}
		}

		/**
		 * Filter the findings for one snippet.
		 *
		 * Advisory by contract: a listener may add or remove a note, and none
		 * of them can stop a save.
		 *
		 * @param array  $out     Findings.
		 * @param string $content The snippet.
		 */
		return (array) apply_filters( 'bfr_code_insert_findings', $out, $content );
	}

	/**
	 * Are any findings serious?
	 *
	 * Used only to decide how loudly the screen says something. Even an ERROR
	 * saves.
	 *
	 * @param array $findings Output of check().
	 * @return bool
	 */
	public static function has_errors( array $findings ) {
		foreach ( $findings as $finding ) {
			if ( isset( $finding['level'] ) && self::ERROR === $finding['level'] ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * A `<script>` that never closes swallows the rest of the page.
	 *
	 * The most damaging of the four by a distance: the browser treats
	 * everything after it as script source, so the visible result is a blank
	 * page rather than a broken snippet.
	 *
	 * @param string $content Snippet.
	 * @return array|null
	 */
	private static function unclosed_script( $content ) {
		$open  = preg_match_all( '/<script\b/i', $content );
		$close = preg_match_all( '#</script\s*>#i', $content );

		if ( $open <= $close ) {
			return null;
		}

		return self::finding(
			self::ERROR,
			'unclosed_script',
			__( 'A <script> tag is never closed. Everything after it on the page will be treated as script and the page will look blank.', 'real-time-auto-find-and-replace' )
		);
	}

	/**
	 * An HTML comment that never closes hides everything after it.
	 *
	 * @param string $content Snippet.
	 * @return array|null
	 */
	private static function unclosed_comment( $content ) {
		$open  = substr_count( $content, '<!--' );
		$close = substr_count( $content, '-->' );

		if ( $open <= $close ) {
			return null;
		}

		return self::finding(
			self::ERROR,
			'unclosed_comment',
			__( 'An HTML comment is never closed. Everything after it on the page will be hidden.', 'real-time-auto-find-and-replace' )
		);
	}

	/**
	 * A `<style>` that never closes does the same as an open script.
	 *
	 * @param string $content Snippet.
	 * @return array|null
	 */
	private static function unclosed_style( $content ) {
		$open  = preg_match_all( '/<style\b/i', $content );
		$close = preg_match_all( '#</style\s*>#i', $content );

		if ( $open <= $close ) {
			return null;
		}

		return self::finding(
			self::ERROR,
			'unclosed_style',
			__( 'A <style> tag is never closed. The rest of the page will be treated as CSS.', 'real-time-auto-find-and-replace' )
		);
	}

	/**
	 * `document.write` after the page has parsed erases it.
	 *
	 * A warning rather than an error, because in a header snippet it is merely
	 * old-fashioned, and some ad tags still legitimately use it.
	 *
	 * @param string $content Snippet.
	 * @return array|null
	 */
	private static function document_write( $content ) {
		if ( ! preg_match( '/document\s*\.\s*write\s*\(/i', $content ) ) {
			return null;
		}

		return self::finding(
			self::WARNING,
			'document_write',
			__( 'This uses document.write(). In a footer snippet that can blank the page, because the document has already finished loading.', 'real-time-auto-find-and-replace' )
		);
	}

	/**
	 * A PHP tag will be printed as text, not run.
	 *
	 * Worth saying out loud rather than leaving somebody to discover it: this
	 * module does not execute PHP and is never going to, so a pasted PHP
	 * snippet is not a security problem here - it is a snippet that will appear
	 * on the page as literal text.
	 *
	 * @param string $content Snippet.
	 * @return array|null
	 */
	private static function php_tag( $content ) {
		if ( false === strpos( $content, '<?php' ) && false === strpos( $content, '<?=' ) ) {
			return null;
		}

		return self::finding(
			self::WARNING,
			'php_tag',
			__( 'This looks like PHP. Code snippets are HTML, JavaScript and CSS only - PHP is never run here, so it would be printed on the page as text.', 'real-time-auto-find-and-replace' )
		);
	}

	/**
	 * One finding, in the shape the screen expects.
	 *
	 * @param string $level   ERROR or WARNING.
	 * @param string $code    Machine-readable identifier.
	 * @param string $message What to show.
	 * @return array
	 */
	private static function finding( $level, $code, $message ) {
		return array(
			'level'   => (string) $level,
			'code'    => (string) $code,
			'message' => (string) $message,
		);
	}
}
