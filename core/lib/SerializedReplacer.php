<?php namespace RealTimeAutoFindReplace\lib;

/**
 * Safe helpers for replacing inside PHP-serialized data.
 *
 * The DB replacer must never run a raw str_replace over a serialized blob:
 * changing the byte length of any inner string invalidates its `s:N:` length
 * prefix and makes the whole value un-`unserialize()`-able. These helpers
 * unserialize first (recurse + re-serialize handled by the caller) and refuse
 * to touch anything they can't decode safely.
 *
 * @package Library
 * @since 1.9.1
 * @author M.Tuhin <info@codesolz.net>
 */

if ( ! defined( 'CS_RTAFAR_VERSION' ) ) {
	exit;
}

class SerializedReplacer {

	/** Hard cap on recursion depth — guards against pathological / hostile nesting. */
	const MAX_DEPTH = 30;

	/**
	 * Is the value a PHP-serialized string (array/object/scalar or serialized string)?
	 *
	 * @param mixed $value
	 * @return bool
	 */
	public static function is_php_serialized( $value ) {
		if ( ! is_string( $value ) ) {
			return false;
		}
		return \is_serialized( $value ) || \is_serialized_string( $value );
	}

	/**
	 * Unserialize without instantiating arbitrary classes.
	 *
	 * Returns array( bool $ok, mixed $value ). $ok is false when:
	 *   - the string can't be decoded (malformed / truncated), or
	 *   - the decoded data contains an object of a class other than stdClass.
	 * In both cases the caller MUST leave the original value untouched — never
	 * raw-replace a serialized blob.
	 *
	 * Only stdClass is allowed: it is the data-only object WordPress stores, and
	 * allowing it avoids turning legitimate stdClass values into
	 * __PHP_Incomplete_Class. Any other class becomes __PHP_Incomplete_Class,
	 * which we detect and reject (object-injection mitigation + we could not
	 * re-serialize it without corrupting the class name).
	 *
	 * @param string $str
	 * @return array{0:bool,1:mixed}
	 */
	public static function safe_unserialize( $str ) {
		if ( ! is_string( $str ) || '' === $str ) {
			return array( false, null );
		}

		// @ suppresses the warning unserialize() emits on malformed input.
		$decoded = @\unserialize( $str, array( 'allowed_classes' => array( 'stdClass' ) ) );

		// unserialize() returns false on failure AND for the literal serialized
		// boolean false ("b:0;") — only the former is an error.
		if ( false === $decoded && \serialize( false ) !== $str ) {
			return array( false, null );
		}

		if ( self::contains_incomplete_class( $decoded ) ) {
			return array( false, null );
		}

		return array( true, $decoded );
	}

	/**
	 * Recursively detect __PHP_Incomplete_Class anywhere in a structure.
	 *
	 * @param mixed $data
	 * @param int   $depth
	 * @return bool
	 */
	public static function contains_incomplete_class( $data, $depth = 0 ) {
		if ( $depth > self::MAX_DEPTH ) {
			return false;
		}

		if ( $data instanceof \__PHP_Incomplete_Class ) {
			return true;
		}

		if ( is_array( $data ) ) {
			foreach ( $data as $value ) {
				if ( self::contains_incomplete_class( $value, $depth + 1 ) ) {
					return true;
				}
			}
		} elseif ( is_object( $data ) ) {
			foreach ( \get_object_vars( $data ) as $value ) {
				if ( self::contains_incomplete_class( $value, $depth + 1 ) ) {
					return true;
				}
			}
		}

		return false;
	}
}
