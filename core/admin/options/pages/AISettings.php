<?php namespace RealTimeAutoFindReplace\admin\options\pages;

/**
 * Multi-provider AI configuration page.
 *
 * @package Admin
 * @since 1.0.0
 * @author CodeSolz <info@codesolz.net>
 */

if ( ! defined( 'CS_RTAFAR_VERSION' ) ) {
	die();
}

use RealTimeAutoFindReplace\admin\builders\FormBuilder;
use RealTimeAutoFindReplace\admin\builders\AdminPageBuilder;
use RealTimeAutoFindReplace\admin\functions\ai\Settings as AiProviderSettings;
use RealTimeAutoFindReplace\admin\functions\ai\ProviderRegistry;
use RealTimeAutoFindReplace\admin\functions\ai\PromptTemplates;
use RealTimeAutoFindReplace\admin\functions\ai\OAuth\Manager as OAuthManager;
use RealTimeAutoFindReplace\actions\RTAFAR_AiOauth;

class AISettings {

	private $Admin_Page_Generator;
	private $Form_Generator;

	public function __construct( AdminPageBuilder $AdminPageGenerator ) {
		$this->Admin_Page_Generator = $AdminPageGenerator;
		$this->Form_Generator       = new FormBuilder();
	}

	public function generate_page( $args ) {
		$settings  = AiProviderSettings::get();
		$providers = ProviderRegistry::getProviders();

		$active_slug = $settings['active_provider'];
		if ( ! isset( $providers[ $active_slug ] ) ) {
			$active_slug = key( $providers );
		}

		$content  = $this->render_oauth_banner();
		$content .= $this->render_provider_grid( $providers, $active_slug );
		$content .= $this->render_provider_panels( $providers, $active_slug, $settings );
		$content .= $this->render_prompt_section( $settings );
		$content .= $this->render_active_provider_input( $active_slug );

		$args['content'] = $content;

		$hidden_fields = array(
			'method'           => array(
				'id'    => 'method',
				'type'  => 'hidden',
				'value' => 'aiHandler@saveSettings',
			),
			'swal_title'       => array(
				'id'    => 'swal_title',
				'type'  => 'hidden',
				'value' => 'Saving Settings..',
			),
			'swal_des'         => array(
				'id'    => 'swal_des',
				'type'  => 'hidden',
				'value' => __( 'Please wait a while...', 'real-time-auto-find-and-replace' ),
			),
			'swal_loading_gif' => array(
				'id'    => 'swal_loading_gif',
				'type'  => 'hidden',
				'value' => CS_RTAFAR_PLUGIN_ASSET_URI . 'img/loading-timer.gif',
			),
			'swal_error'       => array(
				'id'    => 'swal_error',
				'type'  => 'hidden',
				'value' => __( 'Something went wrong! Please try again by refreshing the page.', 'real-time-auto-find-and-replace' ),
			),
		);
		$args['hidden_fields'] = $this->Form_Generator->generate_hidden_fields( $hidden_fields );

		$args['btn_text']   = __( 'Save AI Settings', 'real-time-auto-find-and-replace' );
		$args['show_btn']   = true;
		$args['body_class'] = 'no-bottom-margin rtafar-ai-settings-body';

		$args['well'] = $this->render_intro_well();

		return $this->Admin_Page_Generator->generate_page( $args );
	}

	private function render_oauth_banner() {
		if ( empty( $_GET['oauth'] ) ) {
			return '';
		}

		$status   = sanitize_key( wp_unslash( $_GET['oauth'] ) );
		$slug     = isset( $_GET['provider'] ) ? sanitize_key( wp_unslash( $_GET['provider'] ) ) : '';
		$registry = $slug ? ProviderRegistry::get( $slug ) : null;
		$name     = $registry ? $registry['name'] : ucfirst( $slug );
		$msg      = isset( $_GET['msg'] ) ? sanitize_text_field( rawurldecode( wp_unslash( $_GET['msg'] ) ) ) : '';

		switch ( $status ) {
			case 'success':
				return sprintf(
					'<div class="notice notice-success rtafar-ai-oauth-banner"><p><strong>%s</strong> %s</p></div>',
					esc_html__( 'Connected.', 'real-time-auto-find-and-replace' ),
					/* Translators: %s: provider display name. */
					esc_html( sprintf( __( 'Signed in to %s — your key has been saved.', 'real-time-auto-find-and-replace' ), $name ) )
				);
			case 'disconnected':
				return sprintf(
					'<div class="notice notice-info rtafar-ai-oauth-banner"><p>%s</p></div>',
					/* Translators: %s: provider display name. */
					esc_html( sprintf( __( 'Disconnected from %s. The saved key was removed.', 'real-time-auto-find-and-replace' ), $name ) )
				);
			case 'cancelled':
				return '<div class="notice notice-warning rtafar-ai-oauth-banner"><p>' . esc_html__( 'Sign-in was cancelled.', 'real-time-auto-find-and-replace' ) . '</p></div>';
			case 'expired':
				return '<div class="notice notice-warning rtafar-ai-oauth-banner"><p>' . esc_html__( 'Sign-in session expired. Please try again.', 'real-time-auto-find-and-replace' ) . '</p></div>';
			case 'unsupported':
				return '<div class="notice notice-error rtafar-ai-oauth-banner"><p>' . esc_html__( 'OAuth is not supported for this provider yet.', 'real-time-auto-find-and-replace' ) . '</p></div>';
			case 'error':
				return sprintf(
					'<div class="notice notice-error rtafar-ai-oauth-banner"><p><strong>%s</strong> %s</p></div>',
					esc_html__( 'Sign-in failed.', 'real-time-auto-find-and-replace' ),
					esc_html( $msg )
				);
		}
		return '';
	}

	/**
	 * OAuth header for a provider's panel — shown before the API-key field.
	 */
	private function render_oauth_section( $slug, $cfg ) {
		if ( ! OAuthManager::supports( $slug ) ) {
			return '';
		}

		$start_url = wp_nonce_url(
			add_query_arg(
				array(
					'action'   => RTAFAR_AiOauth::ACTION_START,
					'provider' => $slug,
				),
				admin_url( 'admin-post.php' )
			),
			RTAFAR_AiOauth::NONCE_ACTION
		);

		$is_connected = ( ! empty( $cfg['api_key'] ) && isset( $cfg['auth_type'] ) && $cfg['auth_type'] === 'oauth' );

		if ( $is_connected ) {
			$meta         = OAuthManager::meta( $slug );
			$user_id      = isset( $meta['user_id'] ) ? $meta['user_id'] : '';
			$disconnect   = wp_nonce_url(
				add_query_arg(
					array(
						'action'   => RTAFAR_AiOauth::ACTION_DISCONNECT,
						'provider' => $slug,
					),
					admin_url( 'admin-post.php' )
				),
				RTAFAR_AiOauth::NONCE_ACTION
			);

			$user_line = $user_id
				? sprintf(
					/* Translators: %s: account identifier returned by the provider. */
					esc_html__( 'Signed in as %s.', 'real-time-auto-find-and-replace' ),
					'<code>' . esc_html( $user_id ) . '</code>'
				)
				: esc_html__( 'Signed in.', 'real-time-auto-find-and-replace' );

			return sprintf(
				'<div class="rtafar-ai-oauth-section is-connected">
					<div class="rtafar-ai-oauth-state">
						<span class="rtafar-ai-oauth-icon">&#10003;</span>
						<div>
							<strong>%1$s</strong>
							<p class="rtafar-ai-hint">%2$s</p>
						</div>
					</div>
					<a href="%3$s" class="button rtafar-ai-oauth-disconnect">%4$s</a>
				</div>',
				esc_html__( 'Connected via OAuth', 'real-time-auto-find-and-replace' ),
				$user_line,
				esc_url( $disconnect ),
				esc_html__( 'Disconnect', 'real-time-auto-find-and-replace' )
			);
		}

		return sprintf(
			'<div class="rtafar-ai-oauth-section">
				<a href="%1$s" class="button button-primary rtafar-ai-oauth-start">
					<span class="rtafar-ai-oauth-icon">&#128274;</span> %2$s
				</a>
				<p class="rtafar-ai-hint">%3$s</p>
			</div>',
			esc_url( $start_url ),
			esc_html__( 'Sign in with OAuth — no API key needed', 'real-time-auto-find-and-replace' ),
			esc_html__( 'Recommended. You log in on the provider\'s site and we receive a key automatically.', 'real-time-auto-find-and-replace' )
		);
	}

	private function render_active_provider_input( $active_slug ) {
		return sprintf(
			'<input type="hidden" name="cs_ai_config[active_provider]" id="rtafar-ai-active-provider" value="%s" />',
			esc_attr( $active_slug )
		);
	}

	private function render_intro_well() {
		return '<ul class="rtafar-ai-intro">
			<li><b>' . esc_html__( 'How it works', 'real-time-auto-find-and-replace' ) . '</b>
				<ol>
					<li>' . esc_html__( 'Pick a provider below. "Free" badges mark providers with no-cost options.', 'real-time-auto-find-and-replace' ) . '</li>
					<li>' . esc_html__( 'Click the "Get key" button — it opens the provider in a new tab.', 'real-time-auto-find-and-replace' ) . '</li>
					<li>' . esc_html__( 'Paste the key back, optionally test the connection, then save.', 'real-time-auto-find-and-replace' ) . '</li>
				</ol>
				<p class="highlight-red"><em>' . esc_html__( 'You are responsible for any charges on your provider account. No data is stored or shared outside your site.', 'real-time-auto-find-and-replace' ) . '</em></p>
			</li>
		</ul>';
	}

	private function render_provider_grid( $providers, $active_slug ) {
		$cards = '';
		foreach ( $providers as $slug => $p ) {
			$is_active = ( $slug === $active_slug ) ? 'is-active' : '';
			$badges    = '';

			if ( ! empty( $p['free'] ) ) {
				$badges .= '<span class="rtafar-ai-badge rtafar-ai-badge-free">' . esc_html( $p['free_label'] ) . '</span>';
			}
			if ( ! empty( $p['oauth'] ) ) {
				$badges .= '<span class="rtafar-ai-badge rtafar-ai-badge-oauth">OAuth</span>';
			}

			$initials = strtoupper( substr( preg_replace( '/[^A-Za-z]/', '', $p['name'] ), 0, 2 ) );

			$cards .= sprintf(
				'<button type="button" class="rtafar-ai-provider-card %1$s" data-provider="%2$s" aria-pressed="%3$s">
					<span class="rtafar-ai-provider-icon">%4$s</span>
					<span class="rtafar-ai-provider-name">%5$s</span>
					<span class="rtafar-ai-provider-badges">%6$s</span>
				</button>',
				esc_attr( $is_active ),
				esc_attr( $slug ),
				$is_active ? 'true' : 'false',
				esc_html( $initials ),
				esc_html( $p['name'] ),
				$badges
			);
		}

		return '<section class="rtafar-ai-section">
			<h3 class="rtafar-ai-section-title">' . esc_html__( 'Choose AI Provider', 'real-time-auto-find-and-replace' ) . '</h3>
			<p class="rtafar-ai-section-sub">' . esc_html__( 'Pick one. You can switch any time — your keys for each provider are remembered.', 'real-time-auto-find-and-replace' ) . '</p>
			<div class="rtafar-ai-provider-grid">' . $cards . '</div>
		</section>';
	}

	private function render_provider_panels( $providers, $active_slug, $settings ) {
		$panels = '';
		foreach ( $providers as $slug => $p ) {
			$cfg     = isset( $settings['providers'][ $slug ] ) ? $settings['providers'][ $slug ] : array();
			$panels .= $this->render_provider_panel( $slug, $p, $cfg, $slug === $active_slug );
		}

		return '<section class="rtafar-ai-section rtafar-ai-panels-wrap">
			<h3 class="rtafar-ai-section-title">' . esc_html__( 'Provider Configuration', 'real-time-auto-find-and-replace' ) . '</h3>
			' . $panels . '
		</section>';
	}

	private function render_provider_panel( $slug, $p, $cfg, $is_visible ) {
		$display     = $is_visible ? '' : 'style="display:none"';
		$name_prefix = sprintf( 'cs_ai_config[providers][%s]', $slug );

		$api_key  = isset( $cfg['api_key'] ) ? $cfg['api_key'] : '';
		$base_url = isset( $cfg['base_url'] ) && $cfg['base_url'] !== '' ? $cfg['base_url'] : ( isset( $p['base_url'] ) ? $p['base_url'] : '' );
		$model    = isset( $cfg['model'] ) && $cfg['model'] !== '' ? $cfg['model'] : ( isset( $p['default_model'] ) ? $p['default_model'] : '' );

		$header  = sprintf(
			'<div class="rtafar-ai-panel-header">
				<h4>%1$s</h4>
				<p class="rtafar-ai-panel-notes">%2$s</p>
			</div>',
			esc_html( $p['name'] ),
			esc_html( isset( $p['notes'] ) ? $p['notes'] : '' )
		);

		$oauth_section = $this->render_oauth_section( $slug, $cfg );

		$api_key_field = '';
		if ( $slug === 'ollama' ) {
			$api_key_field = '<div class="rtafar-ai-field">
				<label>' . esc_html__( 'Authentication', 'real-time-auto-find-and-replace' ) . '</label>
				<p class="rtafar-ai-hint">' . esc_html__( 'No key needed — Ollama runs on your machine. Make sure it is running.', 'real-time-auto-find-and-replace' ) . '</p>
			</div>';
		} else {
			$key_link = ! empty( $p['api_key_url'] ) ? sprintf(
				'<a href="%1$s" target="_blank" rel="noopener" class="button button-secondary rtafar-ai-get-key" data-provider="%2$s">%3$s</a>',
				esc_url( $p['api_key_url'] ),
				esc_attr( $slug ),
				! empty( $p['free'] ) ? esc_html__( 'Get free key', 'real-time-auto-find-and-replace' ) : esc_html__( 'Get API key', 'real-time-auto-find-and-replace' )
			) : '';

			$api_key_field = sprintf(
				'<div class="rtafar-ai-field">
					<label for="rtafar-ai-key-%1$s">%2$s</label>
					<div class="rtafar-ai-key-row">
						<input type="password" id="rtafar-ai-key-%1$s" name="%3$s[api_key]" value="%4$s" autocomplete="off" placeholder="%5$s" class="rtafar-ai-key-input" />
						<button type="button" class="button rtafar-ai-toggle-visibility" data-target="rtafar-ai-key-%1$s" title="%6$s" aria-label="%6$s">&#128065;</button>
						%7$s
					</div>
					<p class="rtafar-ai-hint">%8$s</p>
				</div>',
				esc_attr( $slug ),
				esc_html__( 'API Key', 'real-time-auto-find-and-replace' ),
				esc_attr( $name_prefix ),
				esc_attr( $api_key ),
				esc_attr__( 'Paste your API key', 'real-time-auto-find-and-replace' ),
				esc_attr__( 'Show / hide', 'real-time-auto-find-and-replace' ),
				$key_link,
				esc_html__( 'Stored in your WordPress options table. Never sent anywhere except this provider.', 'real-time-auto-find-and-replace' )
			);
		}

		// Preserves "oauth" so saving the form doesn't downgrade to api_key.
		$current_auth = ( isset( $cfg['auth_type'] ) && $cfg['auth_type'] === 'oauth' ) ? 'oauth' : 'api_key';
		$auth_field   = sprintf(
			'<input type="hidden" name="%s[auth_type]" value="%s" />',
			esc_attr( $name_prefix ),
			esc_attr( $current_auth )
		);

		$model_options = '';
		$models_static = isset( $p['models_static'] ) ? $p['models_static'] : array();
		if ( $model !== '' && ! isset( $models_static[ $model ] ) ) {
			$model_options .= sprintf(
				'<option value="%1$s" selected>%1$s</option>',
				esc_attr( $model )
			);
		}
		foreach ( $models_static as $id => $label ) {
			$model_options .= sprintf(
				'<option value="%1$s" %2$s>%3$s</option>',
				esc_attr( $id ),
				selected( $id, $model, false ),
				esc_html( $label )
			);
		}

		$model_field = sprintf(
			'<div class="rtafar-ai-field">
				<label for="rtafar-ai-model-%1$s">%2$s</label>
				<div class="rtafar-ai-model-row">
					<select id="rtafar-ai-model-%1$s" name="%3$s[model]" class="rtafar-ai-model-select">
						%4$s
					</select>
					<button type="button" class="button rtafar-ai-fetch-models" data-provider="%1$s">%5$s</button>
				</div>
				<p class="rtafar-ai-hint">%6$s</p>
			</div>',
			esc_attr( $slug ),
			esc_html__( 'Model', 'real-time-auto-find-and-replace' ),
			esc_attr( $name_prefix ),
			$model_options,
			esc_html__( 'Refresh from API', 'real-time-auto-find-and-replace' ),
			esc_html__( 'Click "Refresh from API" after entering a key to load the live model list.', 'real-time-auto-find-and-replace' )
		);

		$show_base_url  = ( $slug === 'ollama' );
		$base_url_field = '';
		if ( $show_base_url ) {
			$base_url_field = sprintf(
				'<div class="rtafar-ai-field">
					<label for="rtafar-ai-baseurl-%1$s">%2$s</label>
					<input type="text" id="rtafar-ai-baseurl-%1$s" name="%3$s[base_url]" value="%4$s" placeholder="%5$s" class="rtafar-ai-baseurl-input" />
					<p class="rtafar-ai-hint">%6$s</p>
				</div>',
				esc_attr( $slug ),
				esc_html__( 'Base URL', 'real-time-auto-find-and-replace' ),
				esc_attr( $name_prefix ),
				esc_attr( $base_url ),
				esc_attr( $p['base_url'] ),
				esc_html__( 'Default points at localhost:11434. Change if Ollama runs elsewhere.', 'real-time-auto-find-and-replace' )
			);
		}

		$test_field = sprintf(
			'<div class="rtafar-ai-field rtafar-ai-test-row">
				<button type="button" class="button button-primary rtafar-ai-test-connection" data-provider="%1$s">%2$s</button>
				<span class="rtafar-ai-test-status" data-provider="%1$s"></span>
			</div>',
			esc_attr( $slug ),
			esc_html__( 'Test Connection', 'real-time-auto-find-and-replace' )
		);

		return sprintf(
			'<div class="rtafar-ai-provider-panel" data-provider="%1$s" %2$s>%3$s%4$s%5$s%6$s%7$s%8$s%9$s</div>',
			esc_attr( $slug ),
			$display,
			$header,
			$oauth_section,
			$auth_field,
			$api_key_field,
			$model_field,
			$base_url_field,
			$test_field
		);
	}

	private function render_prompt_section( $settings ) {
		$current   = isset( $settings['prompt_template'] ) ? $settings['prompt_template'] : 'persuasive';
		$custom    = isset( $settings['custom_prompt'] ) ? $settings['custom_prompt'] : '';
		$templates = PromptTemplates::all();

		$options = '';
		foreach ( $templates as $key => $tpl ) {
			$options .= sprintf(
				'<option value="%1$s" %2$s>%3$s</option>',
				esc_attr( $key ),
				selected( $key, $current, false ),
				esc_html( $tpl['label'] )
			);
		}
		$options .= sprintf(
			'<option value="custom" %s>%s</option>',
			selected( 'custom', $current, false ),
			esc_html__( 'Custom prompt', 'real-time-auto-find-and-replace' )
		);

		$custom_display = ( $current === 'custom' ) ? '' : 'style="display:none"';

		return sprintf(
			'<section class="rtafar-ai-section">
				<h3 class="rtafar-ai-section-title">%1$s</h3>
				<p class="rtafar-ai-section-sub">%2$s</p>
				<div class="rtafar-ai-field">
					<label for="rtafar-ai-prompt-template">%3$s</label>
					<select id="rtafar-ai-prompt-template" name="cs_ai_config[prompt_template]">%4$s</select>
				</div>
				<div class="rtafar-ai-field rtafar-ai-custom-prompt-wrap" %5$s>
					<label for="rtafar-ai-custom-prompt">%6$s</label>
					<textarea id="rtafar-ai-custom-prompt" name="cs_ai_config[custom_prompt]" rows="3" placeholder="%7$s">%8$s</textarea>
					<p class="rtafar-ai-hint">%9$s</p>
				</div>
			</section>',
			esc_html__( 'Suggestion Style', 'real-time-auto-find-and-replace' ),
			esc_html__( 'How should the AI rewrite your text?', 'real-time-auto-find-and-replace' ),
			esc_html__( 'Template', 'real-time-auto-find-and-replace' ),
			$options,
			$custom_display,
			esc_html__( 'Your prompt', 'real-time-auto-find-and-replace' ),
			esc_attr__( 'Rewrite this for a children\'s book audience: "%s"', 'real-time-auto-find-and-replace' ),
			esc_textarea( $custom ),
			esc_html__( 'Use %s where the input text should appear. If you omit it, the input is appended automatically.', 'real-time-auto-find-and-replace' )
		);
	}
}
