<?php namespace RealTimeAutoFindReplace\admin\functions\ai\OAuth;

/**
 * Contract for a per-provider OAuth flow.
 *
 * @package AI
 * @since 1.9.0
 * @author M.Tuhin <info@codesolz.net>
 */

if ( ! defined( 'CS_RTAFAR_VERSION' ) ) {
	die();
}

interface FlowInterface {

	public function slug();

	/** Returns { url, state, statePayload } — caller stashes statePayload by state. */
	public function buildAuthUrl( $callbackUrl );

	/** Returns { status, api_key?, error?, meta? }. */
	public function exchangeCode( array $statePayload, $code );
}
