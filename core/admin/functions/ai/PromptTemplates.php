<?php namespace RealTimeAutoFindReplace\admin\functions\ai;

/**
 * Built-in prompt templates for AI text suggestions.
 *
 * @package AI
 * @since 1.9.0
 * @author M.Tuhin <info@codesolz.net>
 */

if ( ! defined( 'CS_RTAFAR_VERSION' ) ) {
	die();
}

class PromptTemplates {

	/** template_key => [label, system_prompt, user_format with one %s]. */
	public static function all() {
		return array(
			'persuasive' => array(
				'label'         => __( 'Persuasive & SEO-friendly', 'real-time-auto-find-and-replace' ),
				'system_prompt' => 'You are a helpful assistant that rewrites text to be more persuasive and SEO-friendly. Reply with only the rewritten phrase, no preamble, no quotes.',
				'user_format'   => 'Rewrite this phrase for a website: "%s"',
			),
			'concise'    => array(
				'label'         => __( 'Concise & punchy', 'real-time-auto-find-and-replace' ),
				'system_prompt' => 'You rewrite text to be shorter and punchier without losing meaning. Reply with only the rewritten phrase.',
				'user_format'   => 'Rewrite concisely: "%s"',
			),
			'formal'     => array(
				'label'         => __( 'Formal / professional', 'real-time-auto-find-and-replace' ),
				'system_prompt' => 'You rewrite text in a formal, professional tone. Reply with only the rewritten phrase.',
				'user_format'   => 'Rewrite formally: "%s"',
			),
			'friendly'   => array(
				'label'         => __( 'Friendly & casual', 'real-time-auto-find-and-replace' ),
				'system_prompt' => 'You rewrite text in a friendly, conversational tone. Reply with only the rewritten phrase.',
				'user_format'   => 'Rewrite in a friendly tone: "%s"',
			),
			'fix'        => array(
				'label'         => __( 'Fix grammar & spelling', 'real-time-auto-find-and-replace' ),
				'system_prompt' => 'You correct grammar and spelling without changing meaning or tone. Reply with only the corrected phrase.',
				'user_format'   => 'Correct: "%s"',
			),
		);
	}

	public static function keys() {
		return array_keys( self::all() );
	}

	public static function get( $key ) {
		$all = self::all();
		return isset( $all[ $key ] ) ? $all[ $key ] : null;
	}

	/** Returns [system_prompt, user_format] for the chosen template. */
	public static function resolve( array $settings ) {
		$key = isset( $settings['prompt_template'] ) ? $settings['prompt_template'] : 'persuasive';

		if ( $key === 'custom' && ! empty( $settings['custom_prompt'] ) ) {
			$custom = $settings['custom_prompt'];
			// Append input as a user line if the prompt has no %s placeholder.
			$user_format = strpos( $custom, '%s' ) !== false ? $custom : $custom . "\n\nText: \"%s\"";
			return array(
				'system_prompt' => 'You are a helpful writing assistant. Reply with only the result, no preamble.',
				'user_format'   => $user_format,
			);
		}

		$tpl = self::get( $key );
		if ( ! $tpl ) {
			$tpl = self::get( 'persuasive' );
		}

		return array(
			'system_prompt' => $tpl['system_prompt'],
			'user_format'   => $tpl['user_format'],
		);
	}
}
