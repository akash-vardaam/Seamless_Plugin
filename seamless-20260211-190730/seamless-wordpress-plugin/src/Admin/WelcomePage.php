<?php

namespace Seamless\Admin;

use Seamless\Auth\SeamlessAuth as Auth;

class WelcomePage
{
	private Auth $auth;

	public function __construct()
	{
		$this->auth = new Auth();
		// Enqueue admin styles early to prevent FOUC
		add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_styles']);
	}

	/**
	 * Render the welcome page with sticky navigation
	 */
	public function render(): void
	{
		$this->remove_all_notices();
		$this->render_styles();
		// Always show settings as the main Seamless admin view
		$active_view = 'settings';
?>
		<div class="seamless-dashboard-wrapper">
			<?php $this->render_top_navigation($active_view); ?>

			<!-- Main Dashboard Content -->
			<div class="seamless-dashboard-container">
				<?php $this->render_settings_view(); ?>
			</div>
		</div>

	<?php
		$this->render_scripts();
	}

	/**
	 * Render overview (welcome) view
	 */
	private function render_overview_view(): void
	{
	?>
		<?php $this->render_welcome_header(); ?>
		<?php $this->render_feature_grid(); ?>
	<?php
	}

	/**
	 * Render settings view
	 */
	private function render_settings_view(): void
	{
		// Get the settings page instance to render settings
		$settings_page = new \Seamless\Admin\SettingsPage();
	?>
		<div class="seamless-settings-content">
			<?php $settings_page->render_settings_content(); ?>
		</div>
	<?php
	}

	/**
	 * Render sticky top navigation bar
	 */
	private function render_top_navigation(string $active_view = 'overview'): void
	{
		$logo_url = $this->get_logo_url();
	?>
		<div class="seamless-top-nav">
			<div class="seamless-top-nav-content">
				<div class="seamless-top-nav-left">
					<div class="seamless-logo-container">
						<img src="<?php echo esc_url($logo_url); ?>" alt="Seamless" class="seamless-logo-img" height="50" style="height: 50px; width: auto;" />
					</div>
				</div>
				<div class="seamless-top-nav-right">
					<?php if (class_exists('SeamlessAddon\Services\CacheService')): ?>
						<form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="margin: 0;">
							<?php wp_nonce_field('seamless_addon_clear_cache', 'seamless_addon_nonce'); ?>
							<input type="hidden" name="action" value="seamless_addon_clear_cache">
							<input type="hidden" name="cache_type" value="all">
							<button type="submit" class="button button-secondary seamless-clear-cache-btn">
								<span class="dashicons dashicons-update"></span>
								Clear All Cache
							</button>
						</form>
					<?php endif; ?>
				</div>
			</div>
		</div>
	<?php
	}

	/**
	 * Render welcome header section
	 */
	private function render_welcome_header(): void
	{
	?>
		<div class="seamless-dashboard-header">
			<h1 class="seamless-dashboard-title">Seamless Overview</h1>
			<p class="seamless-dashboard-subtitle">Real-time management of events, memberships, and advanced features</p>
		</div>
	<?php
	}

	/**
	 * Render feature cards grid
	 */
	private function render_feature_grid(): void
	{
		$feature_cards = $this->get_feature_cards_data();
	?>
		<div class="seamless-feature-grid">
			<?php foreach ($feature_cards as $card): ?>
				<?php $this->render_feature_card($card); ?>
			<?php endforeach; ?>
		</div>
	<?php
	}

	/**
	 * Get feature cards data configuration
	 * 
	 * @return array Array of feature card configurations
	 */
	private function get_feature_cards_data(): array
	{
		return [
			[
				'id' => 'connection-status',
				'title' => 'Real-time Connection Status',
				'description' => 'Securely connect WordPress to Seamless using client credentials and verify authentication status.',
				'icon_class' => 'dashicons-admin-network',
				'link_url' => admin_url('admin.php?page=seamless&view=settings&tab=authentication'),
				'link_text' => 'Manage Connection',
				'is_available' => true
			],
			[
				'id' => 'event-sync',
				'title' => 'Event Synchronization',
				'description' => 'Seamlessly sync and display events from your Seamless account with automatic updates.',
				'icon_class' => 'dashicons-calendar-alt',
				'link_url' => admin_url('admin.php?page=seamless&view=settings&tab=events'),
				'link_text' => 'Configure Events',
				'is_available' => true
			],
			[
				'id' => 'membership-sync',
				'title' => 'Membership Sync',
				'description' => 'Sync Seamless memberships to power access control and content restrictions.',
				'icon_class' => 'dashicons-groups',
				'link_url' => admin_url('admin.php?page=seamless&view=settings&tab=membership'),
				'link_text' => 'Manage Memberships',
				'is_available' => true
			],
			[
				'id' => 'sso-integration',
				'title' => 'Single Sign-On Integration',
				'description' => 'Enable seamless user authentication with secure SSO login capabilities.',
				'icon_class' => 'dashicons-admin-users',
				'link_url' => admin_url('admin.php?page=seamless&view=settings&tab=sso'),
				'link_text' => 'Setup SSO',
				'is_available' => true
			],
			[
				'id' => 'content-restriction',
				'title' => 'Content Restriction',
				'description' => 'Restrict WordPress content based on synced membership access.',
				'icon_class' => 'dashicons-lock',
				'link_url' => admin_url('admin.php?page=seamless&view=settings&tab=restriction'),
				'link_text' => 'Configure Restrictions',
				'is_available' => true
			]
		];
	}

	/**
	 * Render individual feature card
	 * 
	 * @param array $card_data Feature card configuration data
	 */
	private function render_feature_card(array $card_data): void
	{
		$id = $card_data['id'] ?? '';
		$title = $card_data['title'] ?? '';
		$description = $card_data['description'] ?? '';
		$icon_class = $card_data['icon_class'] ?? 'dashicons-admin-generic';
		$link_url = $card_data['link_url'] ?? '#';
		$link_text = $card_data['link_text'] ?? 'Learn More';
		$is_available = $card_data['is_available'] ?? true;

		$card_class = 'seamless-feature-card';
		if (!$is_available) {
			$card_class .= ' seamless-feature-card-disabled';
		}
	?>
		<div class="<?php echo esc_attr($card_class); ?>" data-feature-id="<?php echo esc_attr($id); ?>">
			<div class="seamless-feature-card-icon">
				<span class="dashicons <?php echo esc_attr($icon_class); ?>"></span>
			</div>
			<div class="seamless-feature-card-content">
				<h3 class="seamless-feature-card-title"><?php echo esc_html($title); ?></h3>
				<p class="seamless-feature-card-description"><?php echo esc_html($description); ?></p>
				<?php if ($is_available): ?>
					<a href="<?php echo esc_url($link_url); ?>" class="seamless-feature-card-link">
						<?php echo esc_html($link_text); ?> &rarr;
					</a>
				<?php else: ?>
					<span class="seamless-feature-card-disabled-text">Coming Soon</span>
				<?php endif; ?>
			</div>
		</div>
	<?php
	}

	/**
	 * Get logo URL
	 * 
	 * @return string Logo URL
	 */
	private function get_logo_url(): string
	{
		// Check for different logo formats in assets folder
		$logo_files = ['seamless-logo.svg', 'seamless-logo.png', 'logo.svg', 'logo.png'];
		$assets_dir = dirname(__FILE__) . '/assets/';

		foreach ($logo_files as $file) {
			if (file_exists($assets_dir . $file)) {
				return plugins_url('assets/' . $file, __FILE__);
			}
		}

		// Fallback to external logo
		return 'https://mafpnew.flywheelsites.com/wp-content/uploads/2025/09/seamless.png';
	}

	/**
	 * Remove all admin notices on this page
	 */
	private function remove_all_notices(): void
	{
		remove_all_actions('admin_notices');
		remove_all_actions('all_admin_notices');
	}

	/**
	 * Enqueue admin styles early to prevent FOUC
	 */
	public function enqueue_admin_styles($hook)
	{
		// Only load on our admin pages
		if (strpos($hook, 'seamless') === false) {
			return;
		}

		// Add inline styles in the head
		wp_add_inline_style('wp-admin', $this->get_admin_css());
	}

	/**
	 * Get admin CSS as a string
	 */
	private function get_admin_css()
	{
		ob_start();
		$this->render_styles();
		return ob_get_clean();
	}

	/**
	 * Render page styles
	 */
	private function render_styles(): void
	{
	?>
		<style>
			/* Hide all notices on welcome page */
			.seamless-dashboard-wrapper .notice:not(.seamless-notice) {
				display: none !important;
			}

			/* Dashboard Wrapper */
			.seamless-dashboard-wrapper {
				margin-left: -20px;
				margin-top: -10px;
				background: #f5f5f5;
				min-height: 100vh;
			}

			.seamless-dashboard-wrapper a:active,
			.seamless-dashboard-wrapper a:hover {
				color: #6c5ce7;
			}

			/* Sticky Top Navigation */
			.seamless-top-nav {
				position: sticky;
				top: 32px;
				background: #ffffff;
				border-bottom: 1px solid #e5e5e5;
				z-index: 999;
				padding: 0 32px;
			}

			@media screen and (max-width: 782px) {
				.seamless-top-nav {
					top: 46px;
				}
			}

			.seamless-top-nav-content {
				/* max-width: 1400px; */
				margin: 0 auto;
				display: flex;
				align-items: center;
				justify-content: space-between;
				height: 64px;
			}

			.seamless-top-nav-left {
				display: flex;
				align-items: center;
				gap: 48px;
			}

			.seamless-top-nav-right {
				display: flex;
				align-items: center;
				gap: 16px;
			}

			.seamless-clear-cache-btn {
				display: inline-flex !important;
				align-items: center !important;
				gap: 6px !important;
				padding: 8px 16px !important;
				height: auto !important;
				font-size: 13px !important;
			}

			.seamless-clear-cache-btn .dashicons {
				font-size: 16px;
				width: 16px;
				height: 16px;
			}

			/* Logo */
			.seamless-logo-container {
				display: flex;
				align-items: center;
				gap: 12px;
			}

			.seamless-logo-img {
				height: 50px;
				width: auto;
				max-width: 150px;
				object-fit: contain;
			}

			/* Navigation Tabs */
			.seamless-nav-tabs {
				display: flex;
				gap: 8px;
			}

			.seamless-nav-tab {
				display: flex;
				align-items: center;
				gap: 8px;
				padding: 8px 16px;
				border-radius: 6px;
				text-decoration: none;
				color: #6b7280;
				font-size: 14px;
				font-weight: 500;
				transition: all 0.2s ease;
			}

			.seamless-nav-tab .dashicons {
				font-size: 18px;
				width: 18px;
				height: 18px;
			}

			.seamless-nav-tab:hover {
				background: #f3f4f6;
				color: #374151;
			}

			.seamless-nav-tab.active {
				background: #eff6ff;
				color: #6c5ce7;
			}

			/* Dashboard Container */
			.seamless-dashboard-container {
				max-width: 1400px;
				/* margin: 0 auto; */
				padding: 48px 32px;
			}

			/* Dashboard Header */
			.seamless-dashboard-header,
			.seamless-settings-header {
				text-align: center;
				margin: 60px auto;
			}

			.seamless-dashboard-title {
				font-size: 36px;
				font-weight: 700;
				color: #1a1a1a;
				margin: 0 0 30px 0;
			}

			.seamless-dashboard-subtitle {
				font-size: 18px;
				color: #6b7280;
				margin: 0;
			}

			/* Feature Grid */
			.seamless-feature-grid {
				display: grid;
				grid-template-columns: repeat(3, 1fr);
				gap: 24px;
				margin-top: 32px;
			}

			@media screen and (max-width: 1024px) {
				.seamless-feature-grid {
					grid-template-columns: repeat(2, 1fr);
				}
			}

			@media screen and (max-width: 640px) {
				.seamless-feature-grid {
					grid-template-columns: 1fr;
				}
			}

			/* Feature Card */
			.seamless-feature-card {
				background: #ffffff;
				border: 1px solid #e5e5e5;
				border-radius: 12px;
				padding: 24px;
				transition: all 0.3s ease;
				cursor: pointer;
			}

			.seamless-feature-card:hover {
				/* transform: translateY(-4px); */
				box-shadow: 0 12px 24px rgba(0, 0, 0, 0.1);
				border-color: #6c5ce7;
			}

			.seamless-feature-card-disabled {
				opacity: 0.6;
				cursor: not-allowed;
			}

			.seamless-feature-card-disabled:hover {
				transform: none;
				box-shadow: none;
				border-color: #e5e5e5;
			}

			.seamless-feature-card-icon {
				width: 48px;
				height: 48px;
				background: #eff6ff;
				border-radius: 10px;
				display: flex;
				align-items: center;
				justify-content: center;
				margin-bottom: 16px;
			}

			.seamless-feature-card-icon .dashicons {
				font-size: 24px;
				width: 24px;
				height: 24px;
				color: #6c5ce7;
			}

			.seamless-feature-card-title {
				font-size: 18px;
				font-weight: 600;
				color: #1a1a1a;
				margin: 0 0 8px 0;
			}

			.seamless-feature-card-description {
				font-size: 14px;
				color: #6b7280;
				line-height: 1.6;
				margin: 0 0 16px 0;
			}

			.seamless-feature-card-link {
				display: inline-flex;
				align-items: center;
				gap: 4px;
				color: #6c5ce7;
				text-decoration: none;
				font-size: 14px;
				font-weight: 500;
				transition: gap 0.2s ease;
			}

			.seamless-feature-card-link:hover {
				gap: 8px;
			}

			.seamless-feature-card-disabled-text {
				color: #9ca3af;
				font-size: 14px;
				font-weight: 500;
			}

			/* Settings Content Styling - inherits from SettingsPage.php */
			.seamless-settings-content {
				background: transparent;
				border-radius: 0;
				padding: 0;
				box-shadow: none;
			}

			/* Ensure proper rounded corners on tabs */
			.seamless-settings-content .nav-tab-wrapper {
				border-radius: 12px 12px 0 0;
				border: 1px solid #e1e5e9;
			}

			.seamless-settings-content .seamless-tab-content {
				padding: 0;
			}
		</style>
	<?php
	}

	/**
	 * Render page scripts
	 */
	private function render_scripts(): void
	{
	?>
		<script>
			jQuery(document).ready(function($) {
				// Smooth navigation between views
				$('.seamless-nav-tab').on('click', function(e) {
					// Let the link work normally
				});

				// Feature card interactions
				$('.seamless-feature-card').on('click', function(e) {
					if (!$(this).hasClass('seamless-feature-card-disabled')) {
						var link = $(this).find('.seamless-feature-card-link');
						if (link.length && e.target.tagName !== 'A') {
							window.location.href = link.attr('href');
						}
					}
				});
			});
		</script>
<?php
	}
}
