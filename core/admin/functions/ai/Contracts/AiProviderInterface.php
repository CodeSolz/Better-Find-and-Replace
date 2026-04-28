<?php namespace RealTimeAutoFindReplace\admin\functions\ai\Contracts;

/**
 * AI Provider contract.
 *
 * @package AI
 * @since 1.9.0
 * @author M.Tuhin <info@codesolz.net>
 */

if ( ! defined( 'CS_RTAFAR_VERSION' ) ) {
	die();
}

interface AiProviderInterface {

	public function getSlug();

	public function getName();

	/** Returns { status, suggestion?, error?, http_code? }. $userPromptFormat takes one %s. */
	public function getSuggestion( $text, $systemPrompt, $userPromptFormat );

	/** Returns { status, message }. */
	public function testConnection();

	/** Returns { status, models?: id => label, error? }. */
	public function listModels();
}
