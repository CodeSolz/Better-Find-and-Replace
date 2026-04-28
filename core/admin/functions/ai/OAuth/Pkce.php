<?php namespace RealTimeAutoFindReplace\admin\functions\ai\OAuth;

/**
 * PKCE helpers (RFC 7636).
 *
 * @package AI
 * @since 1.9.0
 * @author M.Tuhin <info@codesolz.net>
 */

if ( ! defined( 'CS_RTAFAR_VERSION' ) ) {
	die();
}

class Pkce {

	public static function verifier() {
		return self::base64url( random_bytes( 48 ) );
	}

	public static function challenge( $verifier ) {
		return self::base64url( hash( 'sha256', $verifier, true ) );
	}

	public static function base64url( $bin ) {
		return rtrim( strtr( base64_encode( $bin ), '+/', '-_' ), '=' );
	}

	public static function state( $bytes = 16 ) {
		return bin2hex( random_bytes( $bytes ) );
	}
}
