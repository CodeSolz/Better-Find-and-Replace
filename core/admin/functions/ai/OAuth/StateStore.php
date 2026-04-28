<?php namespace RealTimeAutoFindReplace\admin\functions\ai\OAuth;

/**
 * Transient-backed OAuth state storage. Verifier is keyed by a one-time
 * random `state` token round-tripped through the auth URL.
 *
 * @package AI
 * @since 1.9.0
 */

if ( ! defined( 'CS_RTAFAR_VERSION' ) ) {
	die();
}

class StateStore {

	const PREFIX = 'rtafar_ai_oauth_';
	const TTL    = 600; // 10 minutes

	public static function put( $state, array $payload ) {
		$payload['_user'] = get_current_user_id();
		set_transient( self::PREFIX . $state, $payload, self::TTL );
	}

	/** Reads, deletes, and returns the payload — null if missing or cross-user. */
	public static function consume( $state ) {
		$key  = self::PREFIX . self::sanitize( $state );
		$data = get_transient( $key );
		if ( ! is_array( $data ) ) {
			return null;
		}
		delete_transient( $key );

		if ( isset( $data['_user'] ) && (int) $data['_user'] !== get_current_user_id() ) {
			return null;
		}
		return $data;
	}

	private static function sanitize( $state ) {
		return preg_replace( '/[^a-z0-9]/i', '', (string) $state );
	}
}
