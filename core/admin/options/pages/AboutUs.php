<?php namespace RealTimeAutoFindReplace\admin\options\pages;

/**
 * Class: About Us – CodeSolz Plugin Ecosystem Showcase
 *
 * @package Admin
 * @since   1.8.1
 * @author  CodeSolz <info@codesolz.net>
 */

if ( ! defined( 'CS_RTAFAR_VERSION' ) ) {
	die();
}

use RealTimeAutoFindReplace\admin\builders\AdminPageBuilder;
use RealTimeAutoFindReplace\admin\functions\ProActions;

class AboutUs {

	/**
	 * @param AdminPageBuilder $AdminPageGenerator
	 */
	public function __construct( AdminPageBuilder $AdminPageGenerator ) {
		// Follows the constructor contract expected by AdminPageBuilder::getClass()
	}

	/**
	 * Plugin showcase data.
	 * Each entry supports: title, description, icon, icon_class, badge, badge_type,
	 * features (array), button_label, button_url.
	 *
	 * @return array
	 */
	private function get_plugins_data() {
		return array(
			array(
				'title'        => 'SchemaPilot AI',
				'description'  => 'Automatically generates, validates, and optimizes schema markup so your WordPress content appears with Google rich results. AI-powered, bulk-ready, WooCommerce compatible.',
				'icon'         => '🤖',
				'icon_class'   => 'rtafar-icon-blue',
				'badge'        => 'AI-Powered',
				'badge_type'   => 'premium',
				'features'     => array(
					'AI-powered schema generation',
					'Google rich results optimization',
					'WooCommerce compatible',
				),
				'button_label' => 'View Plugin',
				'button_url'   => 'https://codesolz.net/our-products/wordpress-plugin/schema-pilot-ai-schema-and-knowledge-graph/?utm_source=plugin-about&utm_medium=wp-admin&utm_campaign=about-page',
			),
			array(
				'title'        => 'Folderlyzer – Smart Media Library Folders',
				'description'  => 'Organize your WordPress media library with Folderlyzer. Create, sort, and manage folders effortlessly for faster file management.',
				'icon'         => '📁',
				'icon_class'   => 'rtafar-icon-purple',
				'badge'        => 'Free',
				'badge_type'   => 'free',
				'features'     => array(
					'Create & manage nested folders',
					'Sort media files effortlessly',
					'Fast, intuitive file management',
				),
				'button_label' => 'View Plugin',
				'button_url'   => 'https://codesolz.net/our-products/wordpress-plugin/folderlyzer-smart-media-library-folders/?utm_source=plugin-about&utm_medium=wp-admin&utm_campaign=about-page',
			),
			array(
				'title'        => 'Ultimate Push Notifications',
				'description'  => 'Re-engage your audience with powerful web push notifications. Send automated alerts for new posts, WooCommerce order updates, and custom marketing campaigns.',
				'icon'         => '🔔',
				'icon_class'   => 'rtafar-icon-orange',
				'badge'        => 'Premium',
				'badge_type'   => 'premium',
				'features'     => array(
					'Cross-browser push support',
					'WooCommerce & post triggers',
					'Subscriber list management',
				),
				'button_label' => 'View Plugin',
				'button_url'   => 'https://codesolz.net/our-products/wordpress-plugin/ultimate-push-notifications/?utm_source=plugin-about&utm_medium=wp-admin&utm_campaign=about-page',
			),
			array(
				'title'        => 'Variation Swatches Adjacent Products for WooCommerce',
				'description'  => 'Boost your WooCommerce store\'s sales with a visually appealing product gallery that smartly recommends multiple designs of products in the same color.',
				'icon'         => '🎨',
				'icon_class'   => 'rtafar-icon-pink',
				'badge'        => 'Free',
				'badge_type'   => 'free',
				'features'     => array(
					'Color, image & label swatches',
					'Smart product recommendations',
					'WooCommerce archive compatible',
				),
				'button_label' => 'View Plugin',
				'button_url'   => 'https://codesolz.net/our-products/wordpress-plugin/variation-swatches-adjacent-products-for-woocommerce/?utm_source=plugin-about&utm_medium=wp-admin&utm_campaign=about-page',
			),
		);
	}

	/**
	 * Render the About Us page and return the complete HTML.
	 *
	 * @return string
	 */
	public function generate_page() {
		$plugins            = $this->get_plugins_data();
		$products_url       = 'https://codesolz.net/our-products/wordpress-plugins/?utm_source=plugin-about&utm_medium=wp-admin&utm_campaign=about-page';
		$docs_url           = 'https://docs.codesolz.net/better-find-and-replace/?utm_source=plugin-about&utm_medium=wp-admin&utm_campaign=about-page';
		$website_url        = 'https://www.codesolz.net/?utm_source=plugin-about&utm_medium=wp-admin&utm_campaign=about-page';
		$support_url        = 'https://codesolz.net/?utm_source=plugin-about&utm_medium=wp-admin&utm_campaign=about-page';
		$wporg_url          = 'https://wordpress.org/plugins/real-time-auto-find-and-replace';
		$upgrade_url        = 'https://codesolz.net/our-products/wordpress-plugin/real-time-auto-find-and-replace/?utm_source=plugin-about&utm_medium=wp-admin&utm_campaign=about-page';
		$community_url      = 'https://codesolz.net/forum/?utm_source=plugin-about&utm_medium=wp-admin&utm_campaign=about-page';

		ob_start();
		?>
		<div class="wrap">
		<div class="rtafar-about-wrap">

			<!-- ================================================
			     HERO SECTION
			     ================================================ -->
			<div class="rtafar-about-hero">
				<div class="rtafar-hero-badge">✦ Trusted WordPress Plugins</div>
				<h1 class="rtafar-hero-title">Discover More Tools<br>from CodeSolz</h1>
				<p class="rtafar-hero-subtitle">
					We build practical, powerful WordPress plugins used by thousands of developers and site owners worldwide. Every tool is crafted for simplicity, performance, and real-world usefulness.
				</p>
				<a href="<?php echo esc_url( $products_url ); ?>" target="_blank" rel="noopener noreferrer" class="rtafar-hero-cta">
					Explore Our Full Ecosystem <span aria-hidden="true">↗</span>
				</a>
			</div>

			<!-- ================================================
			     FEATURED PLUGIN – BETTER FIND & REPLACE
			     ================================================ -->
			<div class="rtafar-section-header">
				<div class="rtafar-section-label">Currently Installed</div>
				<h2 class="rtafar-section-title">Your Active Plugin</h2>
				<p class="rtafar-section-desc">You are running Better Find &amp; Replace. Here is a quick overview of everything it can do.</p>
			</div>

			<div class="rtafar-featured-card">
				<div class="rtafar-featured-inner">

					<div class="rtafar-featured-icon-wrap" aria-hidden="true">🔍</div>

					<div class="rtafar-featured-body">
						<div class="rtafar-featured-meta">
							<span class="rtafar-badge featured-label"><?php esc_html_e( 'Featured', 'real-time-auto-find-and-replace' ); ?></span>
							<span class="rtafar-badge popular"><?php esc_html_e( 'Most Popular', 'real-time-auto-find-and-replace' ); ?></span>
							<span class="rtafar-badge active"><?php esc_html_e( 'Active', 'real-time-auto-find-and-replace' ); ?></span>
						</div>

						<h2 class="rtafar-featured-title"><?php esc_html_e( 'Better Find &amp; Replace', 'real-time-auto-find-and-replace' ); ?></h2>

						<p class="rtafar-featured-desc">
							<?php esc_html_e( 'The most powerful find & replace plugin for WordPress. Replace any text across your entire site in real-time — no code required. Supports plain text, regular expressions, database replacements, media file swaps, and AI-powered suggestions.', 'real-time-auto-find-and-replace' ); ?>
						</p>

						<ul class="rtafar-feature-list">
							<li><?php esc_html_e( 'Real-time text masking & replacement', 'real-time-auto-find-and-replace' ); ?></li>
							<li><?php esc_html_e( 'Permanent database find & replace', 'real-time-auto-find-and-replace' ); ?></li>
							<li><?php esc_html_e( 'Media file replacement', 'real-time-auto-find-and-replace' ); ?></li>
							<li><?php esc_html_e( 'AI-powered replacement suggestions', 'real-time-auto-find-and-replace' ); ?></li>
							<li><?php esc_html_e( 'Regex and plain text support', 'real-time-auto-find-and-replace' ); ?></li>
							<li><?php esc_html_e( 'Role-based access controls', 'real-time-auto-find-and-replace' ); ?></li>
						</ul>

						<div class="rtafar-btn-group">
							<a href="<?php echo esc_url( $docs_url ); ?>" target="_blank" rel="noopener noreferrer" class="rtafar-btn primary">
								<?php esc_html_e( 'Documentation', 'real-time-auto-find-and-replace' ); ?>
							</a>
							<a href="<?php echo esc_url( $wporg_url ); ?>" target="_blank" rel="noopener noreferrer" class="rtafar-btn secondary">
								<?php esc_html_e( 'WordPress.org Page', 'real-time-auto-find-and-replace' ); ?>
							</a>
							<?php if ( ! ProActions::hasPro() ) { ?>
								<a href="<?php echo esc_url( $upgrade_url ); ?>" target="_blank" rel="noopener noreferrer" class="rtafar-btn outline-purple">
									&#9733; <?php esc_html_e( 'Upgrade to Pro', 'real-time-auto-find-and-replace' ); ?>
								</a>
							<?php } ?>
						</div>
					</div>
				</div>
			</div>

			<!-- ================================================
			     PLUGIN GRID
			     ================================================ -->
			<div class="rtafar-section-header">
				<div class="rtafar-section-label"><?php esc_html_e( 'Plugin Ecosystem', 'real-time-auto-find-and-replace' ); ?></div>
				<h2 class="rtafar-section-title"><?php esc_html_e( 'More Smart WordPress Tools', 'real-time-auto-find-and-replace' ); ?></h2>
				<p class="rtafar-section-desc"><?php esc_html_e( 'Explore our other plugins — all built with the same commitment to quality, simplicity, and real-world value.', 'real-time-auto-find-and-replace' ); ?></p>
			</div>

			<div class="rtafar-plugins-grid">
				<?php foreach ( $plugins as $plugin ) : ?>
				<div class="rtafar-plugin-card">
					<div class="rtafar-card-top">
						<div class="rtafar-card-icon <?php echo esc_attr( $plugin['icon_class'] ); ?>" aria-hidden="true">
							<?php echo esc_html( $plugin['icon'] ); ?>
						</div>
						<span class="rtafar-badge <?php echo esc_attr( $plugin['badge_type'] ); ?>">
							<?php echo esc_html( $plugin['badge'] ); ?>
						</span>
					</div>
					<h3 class="rtafar-card-title"><?php echo esc_html( $plugin['title'] ); ?></h3>
					<p class="rtafar-card-desc"><?php echo esc_html( $plugin['description'] ); ?></p>
					<?php if ( ! empty( $plugin['features'] ) ) : ?>
					<ul class="rtafar-card-features">
						<?php foreach ( $plugin['features'] as $feature ) : ?>
						<li><?php echo esc_html( $feature ); ?></li>
						<?php endforeach; ?>
					</ul>
					<?php endif; ?>
					<div class="rtafar-card-footer">
						<a href="<?php echo esc_url( $plugin['button_url'] ); ?>" target="_blank" rel="noopener noreferrer" class="rtafar-btn secondary">
							<?php echo esc_html( $plugin['button_label'] ); ?> &#8599;
						</a>
					</div>
				</div>
				<?php endforeach; ?>
			</div>

			<!-- ================================================
			     TRUST / FOOTER SECTION
			     ================================================ -->
			<div class="rtafar-trust-section">
				<div class="rtafar-trust-logo">Code<span>Solz</span></div>
				<h2 class="rtafar-trust-tagline"><?php esc_html_e( 'Building WordPress Tools Developers Trust', 'real-time-auto-find-and-replace' ); ?></h2>
				<p class="rtafar-trust-desc">
					<?php esc_html_e( 'Since 2016, CodeSolz has been crafting practical, lightweight, and reliable WordPress plugins. Our focus is simple: powerful features with zero unnecessary bloat.', 'real-time-auto-find-and-replace' ); ?>
				</p>
				<div class="rtafar-trust-links">
					<a href="<?php echo esc_url( $website_url ); ?>" target="_blank" rel="noopener noreferrer" class="rtafar-btn primary">
						<?php esc_html_e( 'Visit Website', 'real-time-auto-find-and-replace' ); ?>
					</a>
					<a href="<?php echo esc_url( $docs_url ); ?>" target="_blank" rel="noopener noreferrer" class="rtafar-btn secondary">
						<?php esc_html_e( 'Documentation', 'real-time-auto-find-and-replace' ); ?>
					</a>
					<a href="<?php echo esc_url( $support_url ); ?>" target="_blank" rel="noopener noreferrer" class="rtafar-btn secondary">
						<?php esc_html_e( 'Get Support', 'real-time-auto-find-and-replace' ); ?>
					</a>
					<a href="<?php echo esc_url( $community_url ); ?>" target="_blank" rel="noopener noreferrer" class="rtafar-btn secondary">
						<?php esc_html_e( 'Our Community', 'real-time-auto-find-and-replace' ); ?>
					</a>
					<a href="<?php echo esc_url( $products_url ); ?>" target="_blank" rel="noopener noreferrer" class="rtafar-btn outline-purple">
						<?php esc_html_e( 'All Our Plugins', 'real-time-auto-find-and-replace' ); ?> &#8599;
					</a>
				</div>
			</div>

		</div><!-- .rtafar-about-wrap -->
		</div><!-- .wrap -->
		<?php
		return ob_get_clean();
	}
}
