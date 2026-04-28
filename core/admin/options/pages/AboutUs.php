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
				'title'        => 'Advanced AI Content Generator',
				'description'  => 'Create AI-powered WordPress content faster with smart generation workflows designed to help you draft, refine, and publish more efficiently from your dashboard.',
				'icon'         => '📝',
				'icon_class'   => 'rtafar-icon-green',
				'badge'        => 'AI-Powered',
				'badge_type'   => 'premium',
				'features'     => array(
					'Generate content with AI assistance',
					'Speed up drafting and publishing workflows',
					'Built for WordPress content creators',
				),
				'button_label' => 'View Plugin',
				'button_url'   => 'https://codesolz.net/our-products/wordpress-plugin/advanced-ai-content-generator/?utm_source=plugin-about&utm_medium=wp-admin&utm_campaign=about-page',
			),
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
				<div class="rtafar-hero-grid">
					<div class="rtafar-hero-copy">
						<div class="rtafar-hero-badge">Premium WordPress Plugin Suite</div>
						<h1 class="rtafar-hero-title">Tools That Make WordPress Work Faster, Smarter, and Better</h1>
						<p class="rtafar-hero-subtitle">
							CodeSolz builds premium-quality plugins for real websites. From AI-powered content and schema automation to safer search-and-replace, media organization, and WooCommerce enhancements, every product is crafted to save time and deliver clean results.
						</p>
						<div class="rtafar-hero-points">
							<span class="rtafar-hero-point">Built for agencies, publishers, and store owners</span>
							<span class="rtafar-hero-point">AI-powered workflows with practical controls</span>
							<span class="rtafar-hero-point">Performance-focused tools without the bloat</span>
						</div>
						<div class="rtafar-hero-actions">
							<a href="<?php echo esc_url( $products_url ); ?>" target="_blank" rel="noopener noreferrer" class="rtafar-hero-cta">
								Browse Our Plugins <span aria-hidden="true">&#8599;</span>
							</a>
							<a href="<?php echo esc_url( $website_url ); ?>" target="_blank" rel="noopener noreferrer" class="rtafar-hero-link">
								Visit CodeSolz
							</a>
						</div>
					</div>
					<div class="rtafar-hero-showcase">
						<div class="rtafar-hero-panel">
							<div class="rtafar-hero-panel-label">Why Users Explore More</div>
							<h2 class="rtafar-hero-panel-title">A focused toolkit for modern WordPress teams</h2>
							<div class="rtafar-hero-feature-list">
								<div class="rtafar-hero-feature">
									<strong>AI Content and Schema</strong>
									<span>Generate content faster and automate structured data with practical AI workflows.</span>
								</div>
								<div class="rtafar-hero-feature">
									<strong>Safe Site Cleanup</strong>
									<span>Handle search, replace, and content updates with tools built for real production work.</span>
								</div>
								<div class="rtafar-hero-feature">
									<strong>Growth and Organization</strong>
									<span>Improve media management, user engagement, and WooCommerce experiences from one ecosystem.</span>
								</div>
							</div>
							<div class="rtafar-hero-proof">
								<div class="rtafar-hero-proof-item">
									<span class="rtafar-hero-proof-value">50k+</span>
									<span class="rtafar-hero-proof-label">Active installs for Better Find &amp; Replace on WordPress.org</span>
								</div>
								<div class="rtafar-hero-proof-item">
									<span class="rtafar-hero-proof-value">Practical</span>
									<span class="rtafar-hero-proof-label">Built to solve everyday WordPress problems with premium polish</span>
								</div>
							</div>
						</div>
					</div>
				</div>
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
