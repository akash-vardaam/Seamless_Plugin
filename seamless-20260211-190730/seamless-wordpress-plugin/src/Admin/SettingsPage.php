<?php

namespace Seamless\Admin;

use Seamless\Operations\Events;
use Seamless\Operations\Donations;
use Seamless\Operations\Membership;
use Seamless\Auth\SeamlessAuth as Auth;
use Seamless\Auth\SeamlessSSO as SSO;

class SettingsPage
{
	private Auth $auth;
	private SSO $sso;

	public function __construct()
	{
		$this->auth = new Auth();
		$this->sso = new SSO();

		add_action('admin_menu', [$this, 'add_menu_page']);
		add_action('admin_menu', [$this, 'add_submenu_pages']);
		add_action('admin_menu', [$this, 'add_submenu_pages']);
		add_action('admin_init', [$this, 'register_settings']);
		add_action('wp_ajax_seamless_save_and_connect', [$this, 'handle_save_and_connect']);
		add_action('updated_option', [$this, 'maybe_flush_permalinks'], 10, 3);
		add_action('update_option_seamless_event_list_endpoint', [$this, 'schedule_rewrite_flush']);
		add_action('update_option_seamless_single_event_endpoint', [$this, 'schedule_rewrite_flush']);
		add_action('update_option_seamless_ams_content_endpoint', [$this, 'schedule_rewrite_flush']);
		add_action('update_option_seamless_client_domain', [$this, 'handle_domain_change'], 10, 3);
		add_action('wp_ajax_seamless_connect', [$this, 'handle_connect']);
		add_action('wp_ajax_seamless_disconnect', [$this, 'handle_disconnect']);

		add_action('admin_init', [$this, 'maybe_flush_rewrite_rules']);
		add_filter('wp_redirect', [$this, 'preserve_tab_on_settings_save'], 10, 2);
		// Enqueue admin styles early to prevent FOUC
		add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_styles']);
	}

	public function flush_permalinks(): void
	{
		flush_rewrite_rules();
	}

	public function add_menu_page(): void
	{
		add_menu_page(
			esc_html__('Seamless', 'seamless'),
			esc_html__('Seamless', 'seamless'),
			'manage_options',
			'seamless',
			[$this, 'render_welcome_page'],
			'dashicons-admin-generic',
			60
		);
	}

	public function add_submenu_pages(): void
	{
		add_submenu_page(
			'seamless',
			esc_html__('Welcome', 'seamless'),
			esc_html__('Welcome', 'seamless'),
			'manage_options',
			'seamless',
			[$this, 'render_welcome_page']
		);
	}

	public function register_settings(): void
	{
		register_setting('seamless_auth_group', 'seamless_client_domain', [
			'sanitize_callback' => 'sanitize_text_field'
		]);
		register_setting('seamless_auth_group', 'seamless_redirect_url', [
			'sanitize_callback' => 'sanitize_text_field'
		]);

		register_setting('seamless_endpoints_group', 'seamless_event_list_endpoint', [
			'sanitize_callback' => 'sanitize_title_with_dashes'
		]);
		register_setting('seamless_endpoints_group', 'seamless_single_event_endpoint', [
			'sanitize_callback' => 'sanitize_title_with_dashes'
		]);
		register_setting('seamless_endpoints_group', 'seamless_ams_content_endpoint', [
			'sanitize_callback' => 'sanitize_title_with_dashes'
		]);

		// Content restriction settings
		register_setting('seamless_content_restriction_group', 'seamless_protected_post_types', [
			'sanitize_callback' => 'sanitize_text_field',
			'type' => 'string'
		]);

		register_setting('seamless_content_restriction_group', 'seamless_restriction_message', [
			'sanitize_callback' => 'wp_kses_post',
			'type' => 'string'
		]);
		register_setting('seamless_content_restriction_group', 'seamless_sso_endpoint', [
			'sanitize_callback' => 'sanitize_text_field'
		]);

		// --- New Content Restriction Section ---
		add_settings_section(
			'seamless_content_restriction_section',
			'Content Restriction Settings',
			'__return_empty_string',
			'seamless-settings-page'
		);

		register_setting('seamless_content_restriction_group', 'seamless_protected_post_types', [
			'type' => 'string',
			'sanitize_callback' => 'sanitize_text_field',
			'default' => 'post,page',
		]);

		add_settings_field(
			'seamless_protected_post_types',
			'Protected Post Types',
			[$this, 'render_protected_post_types_field'],
			'seamless-settings-page',
			'seamless_content_restriction_section'
		);

		register_setting('seamless_content_restriction_group', 'seamless_membership_purchase_url', [
			'type' => 'string',
			'sanitize_callback' => 'esc_url_raw',
			'default' => home_url('/memberships'),
		]);

		add_settings_field(
			'seamless_membership_purchase_url',
			'Membership Purchase URL',
			[$this, 'render_membership_purchase_url_field'],
			'seamless-settings-page',
			'seamless_content_restriction_section'
		);

		// Advanced settings
		register_setting('seamless_advanced_group', 'seamless_color_scheme', [
			'sanitize_callback' => 'sanitize_text_field',
			'default'           => 'theme',
		]);
		register_setting('seamless_advanced_group', 'seamless_primary_color', [
			'sanitize_callback' => 'sanitize_hex_color',
			'default'           => '#26337a',
		]);
		register_setting('seamless_advanced_group', 'seamless_secondary_color', [
			'sanitize_callback' => 'sanitize_hex_color',
			'default'           => '#06b6d4',
		]);
		register_setting('seamless_advanced_group', 'seamless_list_view_layout', [
			'sanitize_callback' => 'sanitize_text_field',
			'default'           => 'option_1',
		]);

		// SSO settings
		register_setting('seamless_sso_settings_group', 'seamless_sso_enabled', [
			'sanitize_callback' => 'absint',
			'default' => 0
		]);

		// New settings for SSO client credentials
		register_setting('seamless_sso_settings_group', 'seamless_sso_client_id', [
			'sanitize_callback' => 'sanitize_text_field',
		]);

		// Store redirect URI when SSO is configured
		$redirect_uri = rest_url('seamless-oauth/v1/callback');
		update_option('seamless_redirect_uri', $redirect_uri);
	}

	public function render_admin_page(): void
	{
		$this->remove_all_notices();
		$active_tab = isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : 'authentication';
		$is_authenticated = $this->auth->is_authenticated();

?>
		<div class="wrap seamless-admin-wrap">
			<nav class="nav-tab-wrapper seamless-main-tabs">
				<a href="?page=seamless&tab=authentication" data-tab="authentication" class="nav-tab <?php if ($active_tab == 'authentication') echo 'nav-tab-active'; ?>">
					<span class="dashicons dashicons-admin-network"></span> Authentication
				</a>
				<a href="?page=seamless&tab=endpoints" data-tab="endpoints" class="nav-tab <?php if ($active_tab == 'endpoints') echo 'nav-tab-active'; ?>">
					<span class="dashicons dashicons-admin-links"></span> Endpoints
				</a>
				<a href="?page=seamless&tab=events" data-tab="events" class="nav-tab <?php if ($active_tab == 'events') echo 'nav-tab-active'; ?>">
					<span class="dashicons dashicons-calendar"></span> Events
				</a>
				<a href="?page=seamless&tab=membership" data-tab="membership" class="nav-tab <?php if ($active_tab == 'membership') echo 'nav-tab-active'; ?>">
					<span class="dashicons dashicons-groups"></span> Membership
				</a>
				<?php if ($is_authenticated): ?>
					<a href="?page=seamless&tab=sso" data-tab="sso" class="nav-tab <?php if ($active_tab == 'sso') echo 'nav-tab-active'; ?>">
						<span class="dashicons dashicons-admin-users"></span> SSO Login
					</a>
				<?php endif; ?>
				<?php if ($is_authenticated): ?>
					<a href="?page=seamless&tab=restriction" data-tab="restriction" class="nav-tab <?php if ($active_tab == 'restriction') echo 'nav-tab-active'; ?>">
						<span class="dashicons dashicons-lock"></span> Content Restriction
					</a>
				<?php endif; ?>
				<a href="?page=seamless&tab=advanced" data-tab="advanced" class="nav-tab <?php if ($active_tab == 'advanced') echo 'nav-tab-active'; ?>">
					<span class="dashicons dashicons-admin-generic"></span> Advanced
				</a>
				<?php
				// Allow addons to add custom tabs
				$custom_tabs = apply_filters('seamless_settings_tabs', []);
				foreach ($custom_tabs as $tab_key => $tab_data) {
					$tab_label = $tab_data['label'] ?? ucfirst($tab_key);
					$tab_icon = $tab_data['icon'] ?? 'dashicons-admin-generic';
					$tab_url = add_query_arg(['page' => 'seamless', 'tab' => $tab_key], admin_url('admin.php'));
					$is_active = ($active_tab === $tab_key) ? 'nav-tab-active' : '';
					printf(
						'<a href="%s" data-tab="%s" class="nav-tab %s"><span class="dashicons %s"></span> %s</a>',
						esc_url($tab_url),
						esc_attr($tab_key),
						esc_attr($is_active),
						esc_attr($tab_icon),
						esc_html($tab_label)
					);
				}
				?>
			</nav>

			<div class="seamless-tab-content">
				<div class="seamless-card">
					<div class="seamless-tab-panel <?php echo ($active_tab === 'authentication') ? 'is-active' : ''; ?>" data-tab="authentication">
						<?php $this->render_auth_tab(); ?>
					</div>
					<div class="seamless-tab-panel <?php echo ($active_tab === 'endpoints') ? 'is-active' : ''; ?>" data-tab="endpoints">
						<?php $this->render_endpoints_tab(); ?>
					</div>
					<div class="seamless-tab-panel <?php echo ($active_tab === 'events') ? 'is-active' : ''; ?>" data-tab="events">
						<?php $this->render_events_tab(); ?>
					</div>
					<div class="seamless-tab-panel <?php echo ($active_tab === 'membership') ? 'is-active' : ''; ?>" data-tab="membership">
						<?php $this->render_membership_tab(); ?>
					</div>
					<?php if ($is_authenticated): ?>
						<div class="seamless-tab-panel <?php echo ($active_tab === 'sso') ? 'is-active' : ''; ?>" data-tab="sso">
							<?php $this->render_sso_tab(); ?>
						</div>
						<div class="seamless-tab-panel <?php echo ($active_tab === 'restriction') ? 'is-active' : ''; ?>" data-tab="restriction">
							<?php $this->render_restriction_tab(); ?>
						</div>
					<?php endif; ?>
					<div class="seamless-tab-panel <?php echo ($active_tab === 'advanced') ? 'is-active' : ''; ?>" data-tab="advanced">
						<?php $this->render_advanced_tab(); ?>
					</div>
					<?php foreach ($custom_tabs as $tab_key => $tab_data): ?>
						<div class="seamless-tab-panel <?php echo ($active_tab === $tab_key) ? 'is-active' : ''; ?>" data-tab="<?php echo esc_attr($tab_key); ?>">
							<?php do_action('seamless_settings_tab_content_' . $tab_key); ?>
						</div>
					<?php endforeach; ?>
				</div>
			</div>
		</div>
	<?php
		$this->admin_js();
	}

	public function render_welcome_page(): void
	{
		$welcome_page = new WelcomePage();
		$welcome_page->render();
	}

	/**
	 * Render settings content without wrapper (for embedding in WelcomePage)
	 */
	public function render_settings_content(): void
	{
		$active_tab = isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : 'authentication';
		$is_authenticated = $this->auth->is_authenticated();
		$custom_tabs = apply_filters('seamless_settings_tabs', []);

	?>

		<nav class="nav-tab-wrapper seamless-main-tabs">
			<a href="#" data-tab="authentication" class="nav-tab <?php if ($active_tab == 'authentication') echo 'nav-tab-active'; ?>">
				<span class="dashicons dashicons-admin-network"></span> Authentication
			</a>
			<a href="#" data-tab="endpoints" class="nav-tab <?php if ($active_tab == 'endpoints') echo 'nav-tab-active'; ?>">
				<span class="dashicons dashicons-admin-links"></span> Endpoints
			</a>
			<a href="#" data-tab="events" class="nav-tab <?php if ($active_tab == 'events') echo 'nav-tab-active'; ?>">
				<span class="dashicons dashicons-calendar"></span> Events
			</a>
			<a href="#" data-tab="membership" class="nav-tab <?php if ($active_tab == 'membership') echo 'nav-tab-active'; ?>">
				<span class="dashicons dashicons-groups"></span> Membership
			</a>
			<?php if ($is_authenticated): ?>
				<a href="#" data-tab="sso" class="nav-tab <?php if ($active_tab == 'sso') echo 'nav-tab-active'; ?>">
					<span class="dashicons dashicons-admin-users"></span> SSO Login
				</a>
			<?php endif; ?>
			<?php if ($is_authenticated): ?>
				<a href="#" data-tab="restriction" class="nav-tab <?php if ($active_tab == 'restriction') echo 'nav-tab-active'; ?>">
					<span class="dashicons dashicons-lock"></span> Content Restriction
				</a>
			<?php endif; ?>
			<a href="#" data-tab="advanced" class="nav-tab <?php if ($active_tab == 'advanced') echo 'nav-tab-active'; ?>">
				<span class="dashicons dashicons-admin-generic"></span> Advanced
			</a>
			<?php
			foreach ($custom_tabs as $tab_key => $tab_data) {
				$tab_label = $tab_data['label'] ?? ucfirst($tab_key);
				$tab_icon = $tab_data['icon'] ?? 'dashicons-admin-generic';
				$is_active = ($active_tab === $tab_key) ? 'nav-tab-active' : '';
				printf(
					'<a href="#" data-tab="%s" class="nav-tab %s"><span class="dashicons %s"></span> %s</a>',
					\esc_attr($tab_key),
					\esc_attr($is_active),
					\esc_attr($tab_icon),
					\esc_html($tab_label)
				);
			}
			?>
		</nav>

		<div class="seamless-tab-content">
			<div class="seamless-card">
				<div class="seamless-tab-panel <?php echo ($active_tab === 'authentication') ? 'is-active' : ''; ?>" data-tab="authentication">
					<?php $this->render_auth_tab(); ?>
				</div>
				<div class="seamless-tab-panel <?php echo ($active_tab === 'endpoints') ? 'is-active' : ''; ?>" data-tab="endpoints">
					<?php $this->render_endpoints_tab(); ?>
				</div>
				<div class="seamless-tab-panel <?php echo ($active_tab === 'events') ? 'is-active' : ''; ?>" data-tab="events">
					<?php $this->render_events_tab(); ?>
				</div>
				<div class="seamless-tab-panel <?php echo ($active_tab === 'membership') ? 'is-active' : ''; ?>" data-tab="membership">
					<?php $this->render_membership_tab(); ?>
				</div>
				<?php if ($is_authenticated): ?>
					<div class="seamless-tab-panel <?php echo ($active_tab === 'sso') ? 'is-active' : ''; ?>" data-tab="sso">
						<?php $this->render_sso_tab(); ?>
					</div>
					<div class="seamless-tab-panel <?php echo ($active_tab === 'restriction') ? 'is-active' : ''; ?>" data-tab="restriction">
						<?php $this->render_restriction_tab(); ?>
					</div>
				<?php endif; ?>
				<div class="seamless-tab-panel <?php echo ($active_tab === 'advanced') ? 'is-active' : ''; ?>" data-tab="advanced">
					<?php $this->render_advanced_tab(); ?>
				</div>
				<?php foreach ($custom_tabs as $tab_key => $tab_data): ?>
					<div class="seamless-tab-panel <?php echo ($active_tab === $tab_key) ? 'is-active' : ''; ?>" data-tab="<?php echo \esc_attr($tab_key); ?>">
						<?php do_action('seamless_settings_tab_content_' . $tab_key); ?>
					</div>
				<?php endforeach; ?>
			</div>
		</div>
	<?php
		$this->admin_js();
	}

	public function render_auth_tab(): void
	{
		$is_authenticated = $this->auth->is_authenticated();
		$client_domain = get_option('seamless_client_domain', '');
		$is_sso_enabled = get_option('seamless_sso_enabled');

		$has_credentials = !empty($client_domain);

	?>
		<div class="seamless-auth-container">


			<?php if (!$is_authenticated): ?>
				<!-- Configuration Health Check Card -->
				<div class="seamless-health-check-card">
					<div class="seamless-health-header">
						<h3><span class="dashicons dashicons-admin-tools"></span> Configuration Health Check</h3>
						<div class="seamless-health-status needs-attention">
							<span class="dashicons dashicons-warning"></span>
							<span>Needs Attention</span>
							<button class="seamless-close-btn" type="button">×</button>
						</div>
					</div>
					<div class="seamless-health-subtitle">
						<span>Seamless Configuration Status</span>
					</div>

					<div class="seamless-health-items">
						<div class="seamless-health-item">
							<span class="seamless-health-label">Events Sync</span>
							<div class="seamless-health-value">
								<span class="seamless-status-badge inactive">
									<span class="dashicons dashicons-no-alt"></span>
									Inactive
								</span>
							</div>
						</div>

						<div class="seamless-health-item">
							<span class="seamless-health-label">Authentication Endpoints</span>
							<div class="seamless-health-value">
								<span class="seamless-status-badge inactive">
									<span class="dashicons dashicons-no-alt"></span>
									Inactive
								</span>
							</div>
						</div>
					</div>
				</div>


				<div class="seamless-auth-section">
					<div class="seamless-auth-setup">
						<h3><span class="dashicons dashicons-admin-settings"></span> Client Credentials Setup</h3>
						<!-- We use a regular form but intercept the submit for 'Save and Connect' -->
						<form method="post" action="options.php" id="seamless-auth-form">
							<input type="hidden" name="_seamless_return_tab" value="authentication">
							<?php settings_fields('seamless_auth_group'); ?>
							<table class="form-table">
								<tr>
									<th scope="row">
										<label for="seamless_client_domain">Client Domain</label>
									</th>
									<td>
										<input type="url"
											id="seamless_client_domain"
											name="seamless_client_domain"
											value="<?php echo esc_attr(get_option('seamless_client_domain', '')); ?>"
											class="regular-text"
											placeholder="https://yourdomain.com"
											required />
										<p class="description">Enter the base domain for API requests (public/read-only endpoints)</p>
									</td>
								</tr>
							</table>

							<div class="seamless-form-actions">
								<button type="button" id="seamless-save-connect-btn" class="button seamless-btn seamless-btn-primary">
									<span class="dashicons dashicons-admin-network"></span>
									Save and Connect
								</button>
							</div>
						</form>
					</div>

				</div>
			<?php else: ?>
				<!-- Connected State -->
				<div class="seamless-auth-section">
					<div class="seamless-auth-connected">
						<div class="seamless-connection-success">
							<div class="seamless-success-icon">
								<span class="dashicons dashicons-yes-alt"></span>
							</div>
							<div class="seamless-success-content">
								<h3>Successfully Connected!</h3>
								<p>Connected to <strong><?php echo esc_html($client_domain); ?></strong></p>
							</div>
						</div>

						<div class="seamless-connection-details">
							<div class="seamless-detail-item">
								<span class="seamless-detail-label">
									<span class="dashicons dashicons-admin-network"></span>
									Connection Status
								</span>
								<span class="seamless-status-badge connected">
									<span class="dashicons dashicons-yes-alt"></span>
									Connected
								</span>
							</div>

							<div class="seamless-detail-item">
								<span class="seamless-detail-label">
									<span class="dashicons dashicons-admin-site-alt3"></span>
									Domain
								</span>
								<span class="seamless-detail-value"><?php echo esc_html($client_domain); ?></span>
							</div>
						</div>

						<div class="seamless-connection-actions">
							<button type="button" class="seamless-btn seamless-btn-danger" id="seamless-disconnect-btn">
								<span class="dashicons dashicons-no"></span>
								<span>Disconnect</span>
							</button>
						</div>
					</div>
				</div>
			<?php endif; ?>
		</div>
	<?php
	}

	public function render_sso_tab(): void
	{
		$sso_client_id = get_option('seamless_sso_client_id', '');
		$redirect_uri = rest_url('seamless-oauth/v1/callback');
		$has_sso_credentials = !empty($sso_client_id);
	?>

		<div class="seamless-sso-section">
			<form method="post" action="options.php">
				<input type="hidden" name="_seamless_return_tab" value="sso">
				<?php settings_fields('seamless_sso_settings_group'); ?>
				<div class="seamless-sso-config">
					<h3>SSO Configuration</h3>
					<table class="form-table">
						<tr>
							<th scope="row">Client ID</th>
							<td>
								<input type="text" name="seamless_sso_client_id"
									value="<?php echo esc_attr($sso_client_id); ?>"
									class="regular-text" required />
							</td>
						</tr>
						<tr>
							<th scope="row">OAuth Redirect URI</th>
							<td>
								<div class="shortcode-container">
									<code class="seamless-code-block"><?php echo esc_html($redirect_uri); ?></code>
									<button type="button" class="copy-shortcode-btn" title="Copy shortcode" data-shortcode="<?php echo esc_attr($redirect_uri); ?>">
										<span class="dashicons dashicons-admin-page"></span>
									</button>
								</div>
								<p class="description">Add this exact URI to your Seamless OAuth application settings.</p>
							</td>
						</tr>
					</table>
					<?php submit_button('Save SSO Credentials'); ?>
				</div>
			</form>
			<?php if ($has_sso_credentials): ?>
				<div class="seamless-sso-shortcode-section">
					<h3>SSO Login Button</h3>
					<p>Use this shortcode to add a login button anywhere on your site:</p>
					<div class="shortcode-container">
						<?php $sso_shortcode = '[seamless_login_button]'; ?>
						<code class="shortcode-text seamless-code-block"><?php echo esc_html($sso_shortcode); ?></code>
						<button type="button" class="copy-shortcode-btn" title="Copy shortcode" data-shortcode="<?php echo esc_attr($sso_shortcode); ?>">
							<span class="dashicons dashicons-admin-page"></span>
						</button>
					</div>
				</div>
			<?php endif; ?>
		</div>
	<?php
	}

	public function render_restriction_tab(): void
	{
		$protected_post_types = get_option('seamless_protected_post_types', '');
		$custom_message = get_option('seamless_restriction_message', 'You must have an active membership to view this content.');
		$sso_endpoint = get_option('seamless_sso_endpoint', '');

		$public_post_types = get_post_types(['public' => true], 'objects');
		$protected_types_array = array_map('trim', explode(',', $protected_post_types));
	?>

		<form method="post" action="options.php">
			<input type="hidden" name="_seamless_return_tab" value="restriction">
			<?php settings_fields('seamless_content_restriction_group'); ?>
			<table class="form-table">
				<tr>
					<th scope="row">Protected Post Types</th>
					<td>
						<p class="description">Select the post types that require an active membership.</p>
						<?php foreach ($public_post_types as $post_type_obj):
							$checked = in_array($post_type_obj->name, $protected_types_array);
							if ($post_type_obj->name !== 'attachment'): // Exclude attachments
						?>
								<label>
									<input type="checkbox" name="seamless_protected_post_types_checkbox[]" value="<?php echo esc_attr($post_type_obj->name); ?>" <?php checked($checked); ?> />
									<?php echo esc_html($post_type_obj->labels->singular_name); ?>
								</label><br>
						<?php endif;
						endforeach; ?>
						<input type="hidden" name="seamless_protected_post_types" id="seamless-protected-post-types" value="<?php echo esc_attr($protected_post_types); ?>">
					</td>
				</tr>
				<tr>
					<th scope="row">Custom Restriction Message</th>
					<td>
						<textarea name="seamless_restriction_message" rows="5" cols="50" class="large-text"><?php echo esc_textarea($custom_message); ?></textarea>
						<p class="description">This message will be displayed to users who do not have a required membership.</p>
					</td>
				</tr>
				<tr>
					<th scope="row">Membership Page URL</th>
					<td>
						<input type="url" name="seamless_sso_endpoint" value="<?php echo esc_attr($sso_endpoint); ?>" class="regular-text" placeholder="e.g., https://yourdomain.com/memberships" />
						<p class="description">The URL where users can purchase or view membership plans.</p>
					</td>
				</tr>
			</table>
			<?php submit_button('Save Restrictions '); ?>
		</form>
		<script>
			jQuery(document).ready(function($) {
				$('input[name="seamless_protected_post_types_checkbox[]"]').on('change', function() {
					var selectedTypes = [];
					$('input[name="seamless_protected_post_types_checkbox[]"]:checked').each(function() {
						selectedTypes.push($(this).val());
					});
					$('#seamless-protected-post-types').val(selectedTypes.join(','));
				});
			});
		</script>
	<?php
	}

	public function handle_disconnect(): void
	{
		if (!wp_verify_nonce($_POST['nonce'] ?? '', 'seamless_disconnect')) {
			wp_send_json_error('Invalid nonce');
		}

		if (!current_user_can('manage_options')) {
			wp_send_json_error('Insufficient permissions');
		}

		// Disconnect and clear auth
		$this->auth->disconnect();

		// Also disable SSO when disconnecting
		update_option('seamless_sso_enabled', 0);

		wp_send_json_success('Disconnected successfully');
	}

	public function render_advanced_tab(): void
	{
		wp_enqueue_style('wp-color-picker');
		wp_enqueue_script('wp-color-picker');
	?>

		<form method="post" action="options.php">
			<input type="hidden" name="_seamless_return_tab" value="advanced">
			<?php settings_fields('seamless_advanced_group'); ?>
			<table class="form-table">
				<tr valign="top">
					<th scope="row">Color Scheme</th>
					<td>
						<fieldset>
							<p>
								<label class="seamless-radio-label">
									<input type="radio" name="seamless_color_scheme" value="theme" <?php checked(get_option('seamless_color_scheme', 'theme'), 'theme'); ?> />
									<span>Use active theme's colors</span>
								</label>
							<p class="description">Automatically inherit colors from your WordPress theme for a consistent look.</p>
							</p>
							<p>
								<label class="seamless-radio-label">
									<input type="radio" name="seamless_color_scheme" value="plugin" <?php checked(get_option('seamless_color_scheme'), 'plugin'); ?> />
									<span>Use plugin's default colors</span>
								</label>
							<p class="description">Use the default color scheme that comes with the Seamless plugin.</p>
							</p>
						</fieldset>
					</td>
				</tr>
				<tr valign="top" class="plugin-color-settings" style="display: none;">
					<th scope="row">Primary Color</th>
					<td>
						<input type="text" name="seamless_primary_color" value="<?php echo esc_attr(get_option('seamless_primary_color', '#26337a')); ?>" class="seamless-color-picker" />
						<p class="description">Main color for headings and important elements.</p>
					</td>
				</tr>
				<tr valign="top" class="plugin-color-settings" style="display: none;">
					<th scope="row">Secondary Color</th>
					<td>
						<input type="text" name="seamless_secondary_color" value="<?php echo esc_attr(get_option('seamless_secondary_color', '#06b6d4')); ?>" class="seamless-color-picker" />
						<p class="description">Color for links, buttons, and accents.</p>
					</td>
				</tr>
				</tr>
				<tr valign="top">
					<th scope="row">List View Layout</th>
					<td>
						<fieldset>
							<p>
								<label class="seamless-radio-label">
									<input type="radio" name="seamless_list_view_layout" value="option_1" <?php checked(get_option('seamless_list_view_layout', 'option_1'), 'option_1'); ?> />
									<span>Classic</span>
								</label>
							</p>
							<p>
								<label class="seamless-radio-label">
									<input type="radio" name="seamless_list_view_layout" value="option_2" <?php checked(get_option('seamless_list_view_layout'), 'option_2'); ?> />
									<span>Modern Card</span>
								</label>
							</p>
							<p class="description">Select the layout design for the event list view.</p>
						</fieldset>
					</td>
				</tr>
			</table>
			<?php submit_button('Save Advanced Settings'); ?>
		</form>
	<?php
	}

	public function render_endpoints_tab(): void
	{
	?>

		<form method="post" action="options.php">
			<input type="hidden" name="_seamless_return_tab" value="endpoints">
			<?php settings_fields('seamless_endpoints_group'); ?>
			<table class="form-table">
				<tr>
					<th scope="row">Event List Endpoint</th>
					<td>
						<code><?php echo esc_url(home_url('/')); ?></code>
						<input type="text" name="seamless_event_list_endpoint"
							value="<?php echo esc_attr(get_option('seamless_event_list_endpoint', 'events')); ?>"
							class="regular-text" />
					</td>
				</tr>
				<tr>
					<th scope="row">Single Event Endpoint</th>
					<td>
						<code><?php echo esc_url(home_url('/')); ?></code>
						<input type="text" name="seamless_single_event_endpoint"
							value="<?php echo esc_attr(get_option('seamless_single_event_endpoint', 'event')); ?>"
							class="regular-text" />
					</td>
				</tr>
				<tr>
					<th scope="row">Seamless AMS Content Endpoint</th>
					<td>
						<code><?php echo esc_url(home_url('/')); ?></code>
						<input type="text" name="seamless_ams_content_endpoint"
							value="<?php echo esc_attr(get_option('seamless_ams_content_endpoint', 'ams-content')); ?>"
							class="regular-text" />
						<p class="description">URL endpoint for displaying AMS content</p>
					</td>
				</tr>
				<!-- <tr>
					<th scope="row">Single Donation Endpoint</th>
					<td>
						<code><?php // echo esc_url(home_url('/')); 
								?></code>
						<input type="text" name="seamless_single_donation_endpoint"
							value="<?php //echo esc_attr(get_option('seamless_single_donation_endpoint', 'donation')); 
									?>"
							class="regular-text" />
					</td>
				</tr> -->
				<!-- <tr>
					<th scope="row">Membership List Endpoint</th>
					<td>
						<code><?php //echo esc_url(home_url('/')); 
								?></code>
						<input type="text" name="seamless_membership_list_endpoint"
							value="<?php //echo esc_attr(get_option('seamless_membership_list_endpoint', 'memberships')); 
									?>"
							class="regular-text" />
					</td>
				</tr> -->
				<!-- <tr>
					<th scope="row">Single Membership Endpoint</th>
					<td>
						<code><?php //echo esc_url(home_url('/')); 
								?></code>
						<input type="text" name="seamless_single_membership_endpoint"
							value="<?php //echo esc_attr(get_option('seamless_single_membership_endpoint', 'membership')); 
									?>"
							class="regular-text" />
					</td>
				</tr> -->
			</table>
			<?php submit_button('Save Endpoint Settings'); ?>
		</form>
	<?php
	}

	public function render_events_tab(): void
	{
		$page_param = isset($_GET['view']) ? 'seamless&tab=events' : 'seamless';
	?>


		<div class="seamless-section-container">
			<ul class="seamless-shortcodes-list">
				<li>
					<strong>Events with Filter:</strong>
					<span class="shortcode-container" style="display: inline-flex; vertical-align: middle;">
						<code class="seamless-code-block">[seamless_event_list]</code>
						<button type="button" class="copy-shortcode-btn" title="Copy shortcode" data-shortcode="[seamless_event_list]">
							<span class="dashicons dashicons-admin-page"></span>
						</button>
					</span>
				</li>
				<li>
					<strong>Event List View:</strong>
					<span class="shortcode-container" style="display: inline-flex; vertical-align: middle;">
						<code class="seamless-code-block">[seamless_events view="list"]</code>
						<button type="button" class="copy-shortcode-btn" title="Copy shortcode" data-shortcode='[seamless_events view="list"]'>
							<span class="dashicons dashicons-admin-page"></span>
						</button>
					</span>
				</li>
				<li>
					<strong>Event Grid View:</strong>
					<span class="shortcode-container" style="display: inline-flex; vertical-align: middle;">
						<code class="seamless-code-block">[seamless_events view="grid"]</code>
						<button type="button" class="copy-shortcode-btn" title="Copy shortcode" data-shortcode='[seamless_events view="grid"]'>
							<span class="dashicons dashicons-admin-page"></span>
						</button>
					</span>
				</li>
				<li>
					<strong>Single Event:</strong>
					<span class="shortcode-container" style="display: inline-flex; vertical-align: middle;">
						<code class="seamless-code-block">[seamless_single_event slug="my-event-slug"]</code>
						<button type="button" class="copy-shortcode-btn" title="Copy shortcode" data-shortcode='[seamless_single_event slug="my-event-slug"]'>
							<span class="dashicons dashicons-admin-page"></span>
						</button>
					</span>
				</li>
				<!-- <li><strong>Single Donation:</strong> <code>[seamless_single_donation id="1"]</code></li> -->
			</ul>
		</div>

		<?php if (!$this->auth->is_authenticated()): ?>
			<div class="seamless-api-notice" style="padding: 20px; background: #fff; border: 1px solid #ccd0d4; margin-top: 10px;border-radius: 12px;">
				<p>Please authenticate to view events.</p>
			</div>
		<?php else: ?>
			<div class="seamless-search-bar">
				<div class="seamless-search-input">
					<span class="dashicons dashicons-search"></span>
					<input type="text" id="seamless-events-search" placeholder="Search events..." class="seamless-search-field">
				</div>
				<button type="button" class="button seamless-search-reset" id="seamless-events-reset" style="display: none;">
					<span class="dashicons dashicons-dismiss"></span> Clear
				</button>
			</div>
			<div class="seamless-table-area">
				<table class="wp-list-table widefat striped seamless-table" id="seamless-admin-events-table">
					<thead>
						<tr>
							<th>No.</th>
							<th>Title</th>
							<th>Start Date</th>
							<th>End Date</th>
							<th>Type</th>
							<th>Shortcode</th>
						</tr>
					</thead>
					<tbody id="seamless-events-table-body">
						<!-- JS will populate -->
					</tbody>
				</table>
				<div id="seamless-events-pagination" class="seamless-pagination-wrapper"></div>
			</div>
		<?php endif; ?>
	<?php
	}



	public function render_membership_tab(): void
	{
		$page_param = isset($_GET['view']) ? 'seamless&tab=membership' : 'seamless';
	?>

		<div class="seamless-section-container">
			<ul class="seamless-shortcodes-list">
				<li><strong>Membership List:</strong>
					<span class="shortcode-container" style="display: inline-flex; vertical-align: middle;">
						<code class="seamless-code-block">[seamless_membership_list]</code>
						<button type="button" class="copy-shortcode-btn" title="Copy shortcode" data-shortcode="[seamless_membership_list]">
							<span class="dashicons dashicons-admin-page"></span>
						</button>
					</span>
				</li>
				<li><strong>Single Membership:</strong>
					<span class="shortcode-container" style="display: inline-flex; vertical-align: middle;">
						<code class="seamless-code-block">[seamless_single_membership id="1"]</code>
						<button type="button" class="copy-shortcode-btn" title="Copy shortcode" data-shortcode="[seamless_single_membership id='1']">
							<span class="dashicons dashicons-admin-page"></span>
						</button>
					</span>
				</li>
			</ul>
		</div>

		<?php if (!$this->auth->is_authenticated()): ?>
			<div class="seamless-api-notice" style="padding: 20px; background: #fff; border: 1px solid #ccd0d4; margin-top: 10px;border-radius: 12px;">
				<p>Please authenticate to view membership plans.</p>
			</div>
		<?php else: ?>
			<div class="seamless-search-bar">
				<div class="seamless-search-input">
					<span class="dashicons dashicons-search"></span>
					<input type="text" id="seamless-membership-search" placeholder="Search membership plans..." class="seamless-search-field">
				</div>
				<button type="button" class="button seamless-search-reset" id="seamless-membership-reset" style="display: none;">
					<span class="dashicons dashicons-dismiss"></span> Clear
				</button>
			</div>
			<div class="seamless-table-area">
				<table class="wp-list-table widefat striped seamless-table" id="seamless-admin-membership-table">
					<thead>
						<tr>
							<th>No.</th>
							<th>Label</th>
							<th>SKU</th>
							<th>Price</th>
							<th>Billing Cycle</th>
							<th>Trial Days</th>
							<th>Status</th>
							<th>Shortcode</th>
						</tr>
					</thead>
					<tbody id="seamless-membership-table-body">
						<!-- JS will populate -->
					</tbody>
				</table>
				<div id="seamless-membership-pagination" class="seamless-pagination-wrapper"></div>
			</div>
		<?php endif; ?>
	<?php
	}



	public function admin_js()
	{
	?>
		<script>
			jQuery(document).ready(function($) {
				// Toast notification system
				function showToast(message, type = 'success') {
					var toast = $('<div class="seamless-toast seamless-toast-' + type + '">' + message + '</div>');
					$('body').append(toast);

					setTimeout(function() {
						toast.addClass('show');
					}, 50);

					setTimeout(function() {
						toast.removeClass('show');
						setTimeout(function() {
							toast.remove();
						}, 300);
					}, 5000);
				}


				// Forcefully remove all admin notices
				function removeAllNotices() {
					$('.notice, .updated, .error, .settings-error, div.updated, div.error').not('.seamless-notice').remove();
					$('#wpbody-content > .notice, #wpbody-content > .updated, #wpbody-content > .error').remove();
				}

				// Remove notices immediately
				removeAllNotices();

				setTimeout(removeAllNotices, 500);

				// Helper to remove param from URL and update hidden referer inputs
				// This prevents the param from resurfacing after a form submission
				function clenseUrlParam(param) {
					var urlParams = new URLSearchParams(window.location.search);
					if (urlParams.has(param)) {
						urlParams.delete(param);
						var newQuery = urlParams.toString();
						var newUrl = window.location.pathname + (newQuery ? '?' + newQuery : '');

						window.history.replaceState({}, '', newUrl);

						// Critical: Update WP referer to match clean URL so redirects don't bring the param back
						$('input[name="_wp_http_referer"]').val(newUrl);
					}
				}

				// Check for settings updated
				var urlParams = new URLSearchParams(window.location.search);

				// Priority: Settings Saved
				if (urlParams.get('settings-updated') === 'true' || urlParams.get('settings-updated') === '1') {
					showToast('Settings saved successfully!', 'success');
					setTimeout(function() {
						clenseUrlParam('settings-updated');
					}, 3000);
				}
				// Only show these others if we didn't just save settings
				else {
					if (urlParams.get('auth_success')) {
						showToast('Authentication successful!', 'success');
						clenseUrlParam('auth_success');
					} else if (urlParams.get('auth_error')) {
						showToast('Authentication failed: ' + urlParams.get('auth_error'), 'error');
						clenseUrlParam('auth_error');
					} else if (urlParams.get('disconnected')) {
						showToast('Disconnected successfully!', 'success');
						clenseUrlParam('disconnected');
					} else if (urlParams.get('seamless_addon_cache_cleared')) {
						showToast('Cache cleared successfully!', 'success');
						clenseUrlParam('seamless_addon_cache_cleared');
					}
				}

				// Update all forms to include current tab
				var activeTab = new URLSearchParams(window.location.search).get('tab') || 'authentication';
				$('form[action="options.php"]').each(function() {
					var form = $(this);
					// Remove existing tab inputs to avoid duplicates
					form.find('input[name="_seamless_return_tab"]').remove();
					// Add hidden input for current tab
					form.append('<input type="hidden" name="_seamless_return_tab" value="' + activeTab + '">');
				});

				// Run tab-specific JS
				var activeTab = new URLSearchParams(window.location.search).get('tab');

				// Client-side tab switching for embedded Seamless main page
				var $mainTabs = $('.seamless-main-tabs');
				if ($mainTabs.length) {
					$mainTabs.on('click', '.nav-tab', function(e) {
						e.preventDefault();
						var targetTab = $(this).data('tab');
						if (!targetTab) {
							return;
						}

						// Work relative to the clicked nav wrapper
						var $nav = $(this).closest('.seamless-main-tabs');
						$nav.find('.nav-tab').removeClass('nav-tab-active');
						$(this).addClass('nav-tab-active');

						var $container = $nav.next('.seamless-tab-content');
						if ($container.length) {
							$container.find('.seamless-tab-panel').removeClass('is-active');
							$container.find('.seamless-tab-panel[data-tab="' + targetTab + '"]').addClass('is-active');
						}

						try {
							var url = new URL(window.location.href);
							url.searchParams.set('tab', targetTab);
							// Clear transient params from previous listings when switching top-level tabs
							url.searchParams.delete('refetch');
							url.searchParams.delete('show');
							// Clear search term when switching tabs to prevent bleed
							url.searchParams.delete('search');
							url.searchParams.delete('s_events');
							url.searchParams.delete('s_members');
							url.searchParams.delete('paged');
							window.history.replaceState(null, '', url.toString());
						} catch (err) {
							// Ignore URL update errors in older browsers
						}

						// Clear search input fields when switching tabs
						$('.seamless-search-field').val('');

						// Keep _seamless_return_tab in sync with the active tab for all settings forms
						$('form[action="options.php"]').each(function() {
							var form = $(this);
							form.find('input[name="_seamless_return_tab"]').remove();
							form.append('<input type="hidden" name="_seamless_return_tab" value="' + targetTab + '">');
						});

						// Ensure Advanced tab UI is initialized when activated via JS
						if (targetTab === 'advanced') {
							initAdvancedTabOnce();
						}
					});
				}

				// Advanced Tab JS - initialize once and reuse
				function initAdvancedTabOnce() {
					if (window.__seamlessAdvancedInitialized) {
						return;
					}
					window.__seamlessAdvancedInitialized = true;

					if ($('.seamless-color-picker').length) {
						$('.seamless-color-picker').wpColorPicker();
					}

					var color_scheme_radio = $('input[name="seamless_color_scheme"]');
					if (color_scheme_radio.length) {
						var plugin_color_settings = $('.plugin-color-settings');

						function toggle_color_settings() {
							if (color_scheme_radio.filter(':checked').val() === 'plugin') {
								plugin_color_settings.show();
							} else {
								plugin_color_settings.hide();
							}
						}

						toggle_color_settings();
						color_scheme_radio.on('change', toggle_color_settings);
					}
				}

				if (activeTab === 'advanced') {
					initAdvancedTabOnce();
				}

				// Scoped loader overlay helpers for table areas
				function ensureScopedLoaders() {
					$('.seamless-table-area').each(function() {
						var $area = $(this);
						if ($area.find('.seamless-admin-loader').length === 0) {
							$area.append('<div class="seamless-admin-loader hidden"><div class="seamless-admin-spinner"></div><div class="seamless-admin-loading-text">Loading latest data...</div></div>');
						}
						// Ensure the area can contain an absolutely positioned overlay
						if ($area.css('position') === 'static') {
							$area.css('position', 'relative');
						}
					});
				}

				function showScopedLoading() {
					ensureScopedLoaders();
					$('.seamless-table-area .seamless-admin-loader').removeClass('hidden');
				}

				function hideScopedLoading() {
					$('.seamless-table-area .seamless-admin-loader').addClass('hidden');
				}

				// If a previous action triggered a reload with loading intent, keep showing overlay until window load
				ensureScopedLoaders();
				if (sessionStorage.getItem('seamless_loading') === '1') {
					showScopedLoading();
					$(window).on('load', function() {
						hideScopedLoading();
						sessionStorage.removeItem('seamless_loading');
					});
				}

				// Search reset buttons - remove search & paged params and reload
				$('.seamless-card, .seamless-settings-content').on('click', '.seamless-search-reset', function(e) {
					e.preventDefault();
					var $form = $(this).closest('form');
					var url = new URL(window.location.href);
					url.searchParams.delete('search');
					url.searchParams.delete('paged');
					var page = $form.find('input[name="page"]').val() || 'seamless';
					var view = $form.find('input[name="view"]').val();
					var tab = $form.find('input[name="tab"]').val();
					var show = $form.find('input[name="show"]').val();
					url.searchParams.set('page', page);
					if (view) url.searchParams.set('view', view);
					if (tab) url.searchParams.set('tab', tab);
					if (show) url.searchParams.set('show', show);
					window.location.replace(url.toString());
				});

				// Shortcodes Tab JS - Use event delegation on a stable parent
				$('.seamless-card, .seamless-settings-content').on('click', '.copy-shortcode-btn', function(e) {
					e.preventDefault();
					var shortcode = $(this).data('shortcode');
					var button = $(this);

					if (navigator.clipboard && window.isSecureContext) {
						navigator.clipboard.writeText(shortcode).then(function() {
							show_copied_feedback(button);
						});
					} else {
						// Fallback for non-secure contexts
						var textarea = $('<textarea>');
						$('body').append(textarea);
						textarea.val(shortcode).select();
						document.execCommand('copy');
						textarea.remove();
						show_copied_feedback(button);
					}
				});

				$('.seamless-card, .seamless-settings-content').on('click', '.seamless-code-block', function() {
					$(this).siblings('.copy-shortcode-btn').click();
				});

				function show_copied_feedback(button) {
					var originalTitle = button.attr('title');
					button.attr('title', 'Copied!');
					button.addClass('copied');

					setTimeout(function() {
						button.attr('title', originalTitle);
						button.removeClass('copied');
					}, 2000);
				}

				// Shortcodes Tab JS - Fetch Latest Events functionality
				$('#fetch-latest-events-btn').on('click', function(e) {
					e.preventDefault();

					var button = $(this);
					var originalText = button.html();
					button.html('<span class="dashicons dashicons-update spin"></span> Fetching...').prop('disabled', true);
					showScopedLoading();

					$.post(ajaxurl, {
						action: 'seamless_fetch_latest_events',
						nonce: '<?php echo wp_create_nonce('seamless_fetch_events'); ?>'
					}).done(function(response) {
						if (response.success) {
							// Persist loading state across the reload so the overlay shows while the new page renders
							sessionStorage.setItem('seamless_loading', '1');
							var url = new URL(window.location.href);
							url.searchParams.set('refetch', '1');
							window.location.replace(url.toString());
						} else {
							alert('Failed to fetch latest events: ' + (response.data || 'Unknown error'));
							button.html(originalText).prop('disabled', false);
							hideScopedLoading();
						}
					}).fail(function() {
						alert('Failed to fetch latest events. Please try again.');
						button.html(originalText).prop('disabled', false);
						hideScopedLoading();
					});
				});

				// Toast Notification Helper Function
				function showToast(message, type) {
					type = type || 'success'; // 'success' or 'error'

					// Remove any existing toasts
					$('.seamless-toast').remove();

					var toast = $('<div class="seamless-toast seamless-toast-' + type + '">' + message + '</div>');
					$('body').append(toast);

					// Trigger animation
					setTimeout(function() {
						toast.addClass('show');
					}, 10);

					// Auto-hide after 4 seconds
					setTimeout(function() {
						toast.removeClass('show');
						setTimeout(function() {
							toast.remove();
						}, 300);
					}, 4000);
				}

				// Confirmation Toast Helper Function
				function showConfirmToast(message, onConfirm, onCancel) {
					// Remove any existing toasts
					$('.seamless-toast').remove();

					var toast = $('<div class="seamless-toast seamless-toast-confirm">' +
						'<div class="seamless-toast-message">' + message + '</div>' +
						'<div class="seamless-toast-actions">' +
						'<button class="seamless-toast-btn seamless-toast-btn-cancel">No</button>' +
						'<button class="seamless-toast-btn seamless-toast-btn-confirm">Yes</button>' +
						'</div>' +
						'</div>');

					$('body').append(toast);

					// Trigger animation
					setTimeout(function() {
						toast.addClass('show');
					}, 10);

					// Handle confirm button
					toast.find('.seamless-toast-btn-confirm').on('click', function() {
						toast.removeClass('show');
						setTimeout(function() {
							toast.remove();
						}, 300);
						if (onConfirm) onConfirm();
					});

					// Handle cancel button
					toast.find('.seamless-toast-btn-cancel').on('click', function() {
						toast.removeClass('show');
						setTimeout(function() {
							toast.remove();
						}, 300);
						if (onCancel) onCancel();
					});
				}

				// Authentication Tab JS - Save and Connect functionality
				$('#seamless-save-connect-btn').on('click', function(e) {
					e.preventDefault();

					var button = $(this);
					var domain = $('#seamless_client_domain').val();

					if (!domain) {
						showToast('Please enter a Client Domain.', 'error');
						return;
					}

					var originalText = button.html();
					button.html('<span class="dashicons dashicons-update spin"></span> Connecting...').prop('disabled', true);

					$.post(ajaxurl, {
						action: 'seamless_save_and_connect',
						nonce: '<?php echo wp_create_nonce('seamless_connect'); ?>',
						domain: domain
					}).done(function(response) {
						if (response.success) {
							var redirectUrl = window.location.href;
							// Ensure we show success message
							if (!redirectUrl.includes('auth_success=1')) {
								if (redirectUrl.includes('?')) {
									redirectUrl += '&auth_success=1';
								} else {
									redirectUrl += '?auth_success=1';
								}
							}
							window.location.href = redirectUrl;
						} else {
							showToast('Failed to connect: ' + (response.data || 'Unknown error'), 'error');
							button.html(originalText).prop('disabled', false);
						}
					}).fail(function() {
						showToast('Failed to connect. Please try again.', 'error');
						button.html(originalText).prop('disabled', false);
					});
				});

				// Old Connect button handler (removed in UI but kept in case of cached JS/stale page, optional)
				// $('#seamless-connect-btn').on('click', ...

				// Authentication Tab JS - Disconnect functionality
				$('#seamless-disconnect-btn').on('click', function(e) {
					e.preventDefault();

					var button = $(this);
					var originalText = button.html();

					showConfirmToast(
						'Are you sure you want to disconnect from Seamless? You will need to re-authenticate to access your data.',
						function() {
							// User confirmed - proceed with disconnect
							button.html('<span class="dashicons dashicons-update spin"></span> Disconnecting...').prop('disabled', true);

							$.post(ajaxurl, {
								action: 'seamless_disconnect',
								nonce: '<?php echo wp_create_nonce('seamless_disconnect'); ?>'
							}).done(function(response) {
								if (response.success) {
									var redirectUrl = window.location.href.includes('view=settings') ?
										'?page=seamless&tab=authentication&disconnected=1' :
										'?page=seamless&tab=authentication&disconnected=1';
									window.location.href = redirectUrl;
								} else {
									showToast('Failed to disconnect: ' + (response.data || 'Unknown error'), 'error');
									button.html(originalText).prop('disabled', false);
								}
							}).fail(function() {
								showToast('Failed to disconnect. Please try again.', 'error');
								button.html(originalText).prop('disabled', false);
							});
						},
						function() {
							// User cancelled - do nothing
						}
					);
				});

				// Password toggle functionality
				$('.seamless-toggle-password').on('click', function(e) {
					e.preventDefault();
					var input = $(this).siblings('input');
					var icon = $(this).find('.dashicons');

					if (input.attr('type') === 'password') {
						input.attr('type', 'text');
						icon.removeClass('dashicons-visibility').addClass('dashicons-hidden');
					} else {
						input.attr('type', 'password');
						icon.removeClass('dashicons-hidden').addClass('dashicons-visibility');
					}
				});

				// Membership Tab JS - Fetch Latest Membership Data functionality
				$('#fetch-latest-membership-data-btn').on('click', function(e) {
					e.preventDefault();

					var button = $(this);
					var originalText = button.html();
					button.html('<span class="dashicons dashicons-update spin"></span> Fetching...').prop('disabled', true);
					showScopedLoading();

					$.post(ajaxurl, {
						action: 'seamless_fetch_latest_membership_data',
						nonce: '<?php echo wp_create_nonce('seamless_fetch_membership_data'); ?>'
					}).done(function(response) {
						if (response.success) {
							// Persist loading state across the reload so the overlay shows while the new page renders
							sessionStorage.setItem('seamless_loading', '1');
							var url = new URL(window.location.href);
							url.searchParams.set('refetch', '1');
							window.location.replace(url.toString());
						} else {
							alert('Failed to fetch latest membership data: ' + (response.data || 'Unknown error'));
							button.html(originalText).prop('disabled', false);
							hideScopedLoading();
						}
					}).fail(function() {
						alert('Failed to fetch latest membership data. Please try again.');
						button.html(originalText).prop('disabled', false);
						hideScopedLoading();
					});
				});

				// Handle search form submissions for both events and membership
				$('.seamless-card, .seamless-settings-content').on('submit', '.seamless-search-bar', function(e) {
					// Allow the form to submit normally (GET request)
					// The search will be handled by the server-side rendering
					return true;
				});

				// Close health check card
				$('.seamless-close-btn').on('click', function(e) {
					e.preventDefault();
					$(this).closest('.seamless-health-check-card').fadeOut();
				});
			});
		</script>
	<?php
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

		// Ensure API Client is registered
		if (!wp_script_is('seamless-api-client-js', 'registered')) {
			$api_client_path = plugin_dir_path(dirname(__DIR__)) . 'src/Public/assets/js/seamless-api-client.js';
			// Fallback if structure differs
			if (!file_exists($api_client_path)) {
				$api_client_path = plugin_dir_path(dirname(__DIR__)) . 'Public/assets/js/seamless-api-client.js';
			}
			// One more try based on current file location (src/Admin/SettingsPage.php) -> src/Public/assets/js/
			if (!file_exists($api_client_path)) {
				$api_client_path = plugin_dir_path(__DIR__) . 'Public/assets/js/seamless-api-client.js';
			}

			if (file_exists($api_client_path)) {
				wp_register_script(
					'seamless-api-client-js',
					plugins_url('../Public/assets/js/seamless-api-client.js', __FILE__),
					[],
					filemtime($api_client_path),
					true
				);

				// Localize script
				wp_localize_script('seamless-api-client-js', 'seamless_ajax', [
					'ajax_url' => admin_url('admin-ajax.php'),
					'nonce'    => wp_create_nonce('seamless_nonce'),
					'list_view_layout' => get_option('seamless_list_view_layout', 'option_1'),
					'api_domain' => rtrim(get_option('seamless_client_domain', ''), '/'),
				]);
			}
		}

		// Enqueue scripts
		if (file_exists(plugin_dir_path(__FILE__) . 'assets/js/seamless-admin.js')) {
			wp_enqueue_script(
				'seamless-admin-js',
				plugin_dir_url(__FILE__) . 'assets/js/seamless-admin.js',
				['jquery', 'seamless-api-client-js'], // Depend on api client
				filemtime(plugin_dir_path(__FILE__) . 'assets/js/seamless-admin.js'),
				true
			);
		}
	}

	public function handle_save_and_connect(): void
	{
		if (!wp_verify_nonce($_POST['nonce'] ?? '', 'seamless_connect')) {
			wp_send_json_error('Invalid nonce');
		}

		if (!current_user_can('manage_options')) {
			wp_send_json_error('Insufficient permissions');
		}

		$domain = isset($_POST['domain']) ? sanitize_text_field($_POST['domain']) : '';
		if (empty($domain)) {
			wp_send_json_error('Domain is required');
		}

		// Update the option
		update_option('seamless_client_domain', $domain);

		// Trigger connection (auth fetch)
		try {
			// We might need to refresh auth instance properties as options changed
			$this->auth = new Auth();
			$token = $this->auth->fetch_token();

			if ($token) {
				wp_send_json_success('Saved and connected successfully');
			} else {
				$msg = get_option('seamless_last_auth_error', 'Unknown error');
				wp_send_json_error('Saved but unable to connect: ' . $msg);
			}
		} catch (\Throwable $e) {
			wp_send_json_error($e->getMessage());
		}
	}

	/**
	 * Get admin CSS as a string
	 */
	private function get_admin_css()
	{
		ob_start();
		$this->admin_css();
		return ob_get_clean();
	}

	public function admin_css()
	{
	?>
		<style>
			/* Hide other plugin notices and WordPress default notices */
			.seamless-dashboard-container .notice:not(.seamless-notice),
			.seamless-header .notice:not(.seamless-notice),
			.seamless-card .notice:not(.seamless-notice),
			.wrap .notice,
			.wrap .updated,
			.wrap .settings-error,
			#wpbody-content>.notice,
			#wpbody-content>.updated,
			#wpbody-content>.error,
			#wpbody-content>.settings-error,
			.notice.is-dismissible,
			div.updated,
			div.error {
				display: none !important;
			}

			.seamless-dashboard-wrapper a:active,
			.seamless-dashboard-wrapper a:hover {
				color: #6c5ce7;
			}

			/* General Wrapper */
			.seamless-admin-wrap {
				margin-top: 20px;
			}

			/* Header */
			.seamless-header {
				background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
				color: white;
				padding: 30px;
				margin-bottom: 20px;
				border-radius: 12px;
				box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
			}

			.seamless-header h1 {
				font-size: 28px;
				margin: 0 0 8px 0;
				font-weight: 600;
			}

			.seamless-header p {
				font-size: 16px;
				opacity: 0.9;
				margin: 0;
			}

			/* Embedded Seamless main page tab panels */
			.seamless-tab-panel {
				display: none;
			}

			.seamless-tab-panel.is-active {
				display: block;
			}

			/* Enhanced Tabs */
			.nav-tab-wrapper {
				padding: 0;
				margin-bottom: 0;
				background: #f8f9fa;
				border-radius: 12px 12px 0 0;
				overflow: hidden;
			}

			.nav-tab {
				display: inline-flex;
				align-items: center;
				font-size: 14px;
				font-weight: 500;
				padding: 14px 20px;
				background: transparent;
				border: none;
				margin: 0;
				color: #6c757d;
				text-decoration: none;
				transition: all 0.2s ease;
				position: relative;
				border-bottom: none !important;
			}

			.nav-tab .dashicons {
				margin-right: 8px;
				font-size: 16px;
				width: 16px;
				height: 16px;
			}

			.nav-tab:hover {
				background: rgba(108, 92, 231, 0.08);
				color: #6c5ce7;
			}

			.nav-tab-active {
				background: #fff !important;
				color: #6c5ce7 !important;
				border-bottom: none !important;
			}

			.nav-tab:focus {
				outline: none;
				box-shadow: none;
			}


			/* Shortcodes Section & Tabs Alignment */
			.seamless-section-container {
				background: #fff;
				border: 1px solid #e1e5e9;
				border-radius: 12px;
				padding: 20px;
			}

			.seamless-shortcodes-list {
				margin: 0 0 0 20px;
				list-style-type: disc;
				color: #4a5568;
			}

			.seamless-shortcodes-list li {
				line-height: 1.5;
			}

			.seamless-shortcodes-list code {
				background: #f1f5f9;
				padding: 2px 6px;
				border-radius: 4px;
				color: #e11d48;
				font-size: 13px;
				margin-left: 8px;
			}

			.seamless-tabs-actions-bar {
				display: flex;
				justify-content: space-between;
				align-items: flex-end;
				margin-top: 30px;
				border-bottom: 2px solid #e1e5e9;
				padding-bottom: 10px;
			}

			.seamless-tabs-actions-bar .nav-tab-wrapper {
				border-bottom: none;
				padding: 0;
				margin: 0;
				background: transparent;
				border-radius: 0;
			}

			.seamless-tabs-actions-bar .button {
				margin-bottom: 5px;
				margin-right: 5px;
			}

			/* Enhanced Card */
			.seamless-card {
				background: #fff;
				border: 1px solid #e1e5e9;
				box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
				padding: 0;
				margin-top: -1px;
				border-radius: 0 0 12px 12px;
				overflow: hidden;
			}

			.seamless-card>* {
				padding: 20px;
			}

			.seamless-card h2 {
				margin: 0 0 20px 0;
				font-size: 20px;
				color: #2d3748;
				font-weight: 600;
				display: flex;
				align-items: center;
				gap: 10px;
			}

			.seamless-card h2 .dashicons {
				color: #6c5ce7;
				font-size: 20px;
			}

			.seamless-card p {
				font-size: 14px;
				color: #4a5568;
				line-height: 1.6;
				padding: 0px;
				margin: 0px;
			}

			.seamless-card h3 {
				font-size: 16px;
				font-weight: 600;
				color: #2d3748;
				margin: 0 0 16px 0;
			}

			/* Authentication Container */
			.seamless-auth-container {
				padding: 0;
			}

			.seamless-content-header {
				background: linear-gradient(135deg, #f7fafc 0%, #edf2f7 100%);
				padding: 20px;
				border-bottom: 1px solid #e1e5e9;
			}

			.seamless-content-header h2 {
				margin: 0px;
				font-size: 21px;
				color: #2d3748;
				display: flex;
				align-items: center;
				gap: 12px;
				background: none;
				border: none;
				padding: 0;
			}

			.seamless-content-header p {
				margin: 8px 0px 0px 0px;
				color: #4a5568;
				font-size: 16px;
			}

			/* Health Check Card */
			.seamless-health-check-card {
				background: #fff;
				border: 1px solid #e1e5e9;
				border-radius: 12px;
				margin-bottom: 20px;
				box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
			}

			.seamless-health-header {
				display: flex;
				justify-content: space-between;
				align-items: center;
				padding: 16px 20px;
				border-bottom: 1px solid #e1e5e9;
			}

			.seamless-health-header h3 {
				margin: 0;
				font-size: 16px;
				font-weight: 600;
				color: #2d3748;
				display: flex;
				align-items: center;
				gap: 8px;
			}

			.seamless-health-status {
				display: flex;
				align-items: center;
				gap: 8px;
				padding: 8px 16px;
				border-radius: 20px;
				font-size: 14px;
				font-weight: 500;
			}

			.seamless-health-status.needs-attention {
				background: #fef5e7;
				color: #d69e2e;
				border: 1px solid #f6e05e;
			}

			.seamless-close-btn {
				background: none;
				border: none;
				font-size: 18px;
				cursor: pointer;
				color: #d69e2e;
			}

			.seamless-health-subtitle {
				padding: 12px 20px 0;
				color: #718096;
				font-size: 13px;
				font-weight: 500;
			}

			.seamless-health-items {
				padding: 12px 20px 20px;
			}

			.seamless-health-item {
				display: flex;
				justify-content: space-between;
				align-items: center;
				padding: 12px 0;
				border-bottom: 1px solid #f1f5f9;
			}

			.seamless-health-item:last-child {
				border-bottom: none;
			}

			.seamless-health-label {
				font-size: 14px;
				color: #4a5568;
				font-weight: 500;
			}

			/* Status Badges */
			.seamless-status-badge {
				display: inline-flex;
				align-items: center;
				gap: 6px;
				padding: 6px 12px;
				border-radius: 20px;
				font-size: 14px;
				font-weight: 500;
				line-height: 20px;
				color: #1e293b;
			}

			.seamless-status-badge.not-configured {
				background: #fee2e2;
				color: #dc2626;
				border: 1px solid #fecaca;
			}


			.seamless-status-badge.inactive span.dashicons.dashicons-no-alt {
				color: #dc2626;
			}

			/* Confirmation Toast Styles */
			.seamless-toast-confirm {
				border-left: 4px solid #f59e0b;
				color: #92400e;
				flex-direction: column;
				align-items: stretch;
				gap: 16px;
				min-width: 350px;
			}

			.seamless-toast-message {
				font-size: 14px;
				line-height: 1.5;
			}

			.seamless-toast-actions {
				display: flex;
				gap: 10px;
				justify-content: flex-end;
			}

			.seamless-toast-btn {
				padding: 8px 20px;
				border: none;
				border-radius: 6px;
				font-size: 13px;
				font-weight: 600;
				cursor: pointer;
				transition: all 0.2s ease;
				outline: none;
			}

			.seamless-toast-btn-cancel {
				background: #f3f4f6;
				color: #4b5563;
			}

			.seamless-toast-btn-cancel:hover {
				background: #e5e7eb;
				color: #1f2937;
			}

			.seamless-toast-btn-confirm {
				background: #ef4444;
				color: #fff;
			}

			.seamless-toast-btn-confirm:hover {
				background: #dc2626;
			}

			/* Form Table */
			.form-table {
				margin-top: 0;
			}

			.form-table th {
				width: 200px;
				font-weight: 600;
			}

			.form-table td {
				padding-top: 10px;
				padding-bottom: 10px;
			}

			.form-table input[type="text"],
			.form-table input[type="password"],
			.form-table input[type="url"] {
				width: 100%;
				max-width: 400px;
				padding: 8px 12px;
				border: 1px solid #e1e5e9;
				border-radius: 8px;
				font-size: 14px;
				transition: border-color 0.2s ease;
			}

			.form-table input[type="text"]:focus,
			.form-table input[type="password"]:focus,
			.form-table input[type="url"]:focus {
				border-color: #6c5ce7;
				outline: none;
				box-shadow: 0 0 0 3px rgba(108, 92, 231, 0.1);
			}

			.seamless-tab-panel .form-table .description {
				font-size: 13px;
				color: #666;
				margin-top: 0px;
			}

			.form-table code {
				padding: 3px 6px;
				border-radius: 6px;
			}

			.form-table textarea {
				width: 100%;
				max-width: 600px;
				padding: 10px 12px;
				border: 1px solid #e1e5e9;
				border-radius: 8px;
				font-size: 14px;
				transition: border-color 0.2s ease;
			}

			.seamless-tab-panel .form-table td p {
				margin-top: 10px;
			}

			.form-table textarea:focus {
				border-color: #6c5ce7;
				outline: none;
				box-shadow: 0 0 0 3px rgba(108, 92, 231, 0.1);
			}

			/* Radio Buttons */
			.seamless-radio-label {
				display: flex;
				align-items: center;
				margin-bottom: 5px;
			}

			.seamless-radio-label input {
				margin-right: 10px;
			}

			button.button.wp-color-result {
				padding: 0 0 0 30px !important;
			}

			.wp-color-result span.wp-color-result-text {
				border-radius: 0px 12px 12px 0px;
			}

			input.button.button-small.wp-picker-clear {
				line-height: 1;
			}

			/* Submit Button */
			.submit .button-primary,
			.button-primary.seamless-btn-primary,
			#seamless-save-connect-btn {
				background: #6c5ce7 !important;
				border-color: #6c5ce7 !important;
				box-shadow: none !important;
				text-shadow: none !important;
				padding: 10px 24px !important;
				height: auto !important;
				font-size: 14px !important;
				font-weight: 500 !important;
				border-radius: 12px !important;
				transition: all 0.2s ease !important;
				display: inline-flex !important;
				align-items: center !important;
				gap: 8px !important;
				line-height: 20px !important;
			}

			#seamless-save-connect-btn {
				color: #fff !important;
			}

			.submit .button-primary:hover,
			.button-primary.seamless-btn-primary:hover,
			#seamless-save-connect-btn:hover {
				background: #5849c3 !important;
				border-color: #5849c3 !important;
			}

			/* Button Styles */
			.seamless-btn {
				display: inline-flex !important;
				align-items: center !important;
				gap: 8px !important;
				padding: 10px 24px !important;
				border-radius: 6px !important;
				font-size: 14px !important;
				font-weight: 500 !important;
				border: none !important;
				cursor: pointer !important;
				transition: all 0.2s ease !important;
				text-decoration: none !important;
				box-shadow: none !important;
				text-shadow: none !important;
				height: auto !important;
			}

			.seamless-btn .dashicons {
				font-size: 18px;
				width: 18px;
				height: 18px;
			}

			.seamless-btn-success {
				background: #10b981 !important;
				color: #fff !important;
				border-color: #10b981 !important;
			}

			.seamless-btn-success:hover {
				background: #059669 !important;
				border-color: #059669 !important;
			}

			.seamless-btn-danger {
				background: #ef4444 !important;
				color: #fff !important;
				border-color: #ef4444 !important;
			}

			.seamless-btn-danger:hover {
				background: #dc2626 !important;
				border-color: #dc2626 !important;
			}

			.seamless-blur-message .seamless-btn {
				color: #6c5ce7;
			}

			#seamless-disconnect-btn {
				color: #fff !important;
				background: #ef4444 !important;
				border-color: #ef4444 !important;
				box-shadow: none !important;
				text-shadow: none !important;
				padding: 10px 24px !important;
				height: auto !important;
				font-size: 14px !important;
				font-weight: 500 !important;
				border-radius: 12px !important;
				transition: all 0.2s ease !important;
				display: inline-flex !important;
				align-items: center !important;
				gap: 8px !important;
			}

			#seamless-disconnect-btn:hover {
				background: #dc2626 !important;
				border-color: #dc2626 !important;
			}

			#seamless-connect-btn {
				color: #fff !important;
				background: #10b981 !important;
				border-color: #10b981 !important;
				box-shadow: none !important;
				text-shadow: none !important;
				padding: 10px 24px !important;
				height: auto !important;
				font-size: 14px !important;
				font-weight: 500 !important;
				border-radius: 6px !important;
				transition: all 0.2s ease !important;
				display: inline-flex !important;
				align-items: center !important;
				gap: 8px !important;
			}

			#seamless-connect-btn:hover {
				background: #059669 !important;
				border-color: #059669 !important;
			}

			span.dashicons.dashicons-no,
			span.dashicons.dashicons-admin-network {
				font-size: 18px;
				width: 18px;
				height: 18px;
			}

			/* Shortcodes Tab */
			.seamless-tab-content ul {
				list-style: none;
				padding: 0;
				margin: 0px;
			}

			.seamless-tab-content li {
				padding: 12px 16px;
				margin-bottom: 0px;
				background: #f7fafc;
				border-radius: 6px;
				border-left: 3px solid #6c5ce7;
			}

			.seamless-tab-content li:not(:last-child) {
				margin-bottom: 12px;
			}

			.seamless-tab-content li strong {
				color: #2d3748;
				font-weight: 600;
			}

			.seamless-tab-content li code {
				background: #fff;
				padding: 4px 8px;
				border-radius: 6px;
				border: 1px solid #e1e5e9;
				font-size: 13px;
				color: #6c5ce7;
			}

			/* Shortcode Container */
			.shortcode-container {
				display: flex;
				align-items: center;
				gap: 8px;
			}

			.copy-shortcode-btn {
				background: #fff;
				border: 1px solid #ccc;
				border-radius: 6px;
				padding: 6px 9px;
				cursor: pointer;
				transition: all 0.2s ease;
				font-size: 12px;
			}

			.copy-shortcode-btn:hover {
				background: #f7f7ff;
				border-color: #6c5ce7;
				color: #5849c3;
			}

			.copy-shortcode-btn.copied {
				background: #46b450;
				border-color: #46b450;
				color: #fff;
			}

			.copy-shortcode-btn.copied .dashicons {
				color: #fff;
			}

			.copy-shortcode-btn .dashicons {
				font-size: 14px;
				width: 14px;
				height: 14px;
				color: #6c5ce7;
			}

			/* Admin Pagination */
			.seamless-pagination-wrapper {
				margin-top: 1.5em;
				display: flex;
				justify-content: center;
				align-items: center;
			}

			.seamless-pagination-wrapper .page-numbers {
				padding: 8px 16px;
				margin: 0 5px;
				text-decoration: none;
				border: 1px solid #ccc;
				border-radius: 12px;
				color: #282828;
				background: #fff;
				transition: all 0.2s ease-in-out;
			}

			.seamless-pagination-wrapper .page-numbers:hover {
				background: #fff;
				border-color: #6c5ce7;
				color: #5849c3;
			}

			.seamless-pagination-wrapper .page-numbers.current {
				background: #6c5ce7;
				color: #fff;
				border-color: #6c5ce7;
				font-weight: bold;
			}

			.seamless-pagination-wrapper .page-numbers.dots {
				border: none;
				background: transparent;
			}

			.seamless-auth-setup {
				background: #fff;
				border: 1px solid #e1e5e9;
				border-radius: 12px;
				padding: 20px;
			}

			.seamless-auth-setup h3 {
				margin: 0 0 24px 0;
				font-size: 18px;
				font-weight: 600;
				color: #2d3748;
				display: flex;
				align-items: center;
				gap: 10px;
			}

			.seamless-form-grid {
				display: grid;
				grid-template-columns: repeat(2, 1fr);
				gap: 16px;
				margin-bottom: 20px;
			}

			.seamless-form-group {
				display: flex;
				flex-direction: column;
			}

			.seamless-form-group-full {
				grid-column: 1 / -1;
			}

			.seamless-form-label {
				display: flex;
				align-items: center;
				gap: 8px;
				font-size: 14px;
				font-weight: 600;
				color: #2d3748;
				margin-bottom: 8px;
			}

			.seamless-form-label .dashicons {
				font-size: 16px;
				width: 16px;
				height: 16px;
				color: #6c5ce7;
			}

			.seamless-form-input {
				width: 100%;
				padding: 10px 14px;
				border: 1px solid #e1e5e9;
				border-radius: 6px;
				font-size: 14px;
				transition: all 0.2s ease;
			}

			.seamless-form-input:focus {
				border-color: #6c5ce7;
				outline: none;
				box-shadow: 0 0 0 3px rgba(108, 92, 231, 0.1);
			}

			.seamless-form-description {
				font-size: 13px;
				color: #718096;
				margin-top: 6px;
			}

			.seamless-password-field {
				position: relative;
				display: flex;
				align-items: center;
				max-width: 400px;
			}

			.seamless-password-field input {
				padding-right: 45px;
			}

			.seamless-toggle-password {
				position: absolute;
				right: 10px;
				background: none;
				border: none;
				cursor: pointer;
				padding: 5px;
				color: #718096;
				transition: color 0.2s ease;
			}

			.seamless-toggle-password:hover {
				color: #6c5ce7;
			}

			.seamless-toggle-password .dashicons {
				font-size: 18px;
				width: 18px;
				height: 18px;
			}

			.seamless-form-actions {
				display: flex;
				gap: 10px;
				padding-top: 20px;
				border-top: 1px solid #e1e5e9;
			}

			.seamless-test-connection {
				margin-top: 20px;
			}

			.seamless-connection-divider {
				text-align: center;
				position: relative;
				margin: 20px 0 16px;
			}

			.seamless-connection-divider::before {
				content: '';
				position: absolute;
				top: 50%;
				left: 0;
				right: 0;
				height: 1px;
				background: #e1e5e9;
			}

			.seamless-connection-divider span {
				position: relative;
				background: #fff;
				padding: 0 16px;
				color: #718096;
				font-size: 14px;
				font-weight: 500;
			}

			.seamless-connection-content {
				text-align: center;
				padding: 20px;
				background: #f7fafc;
				border-radius: 6px;
			}

			.seamless-connection-content h4 {
				margin: 0 0 8px 0;
				font-size: 15px;
				font-weight: 600;
				color: #2d3748;
				display: flex;
				align-items: center;
				justify-content: center;
				gap: 8px;
			}

			.seamless-connection-content p {
				margin: 0 0 16px 0;
				color: #718096;
				font-size: 14px;
			}

			/* .seamless-auth-connected {
				background: #fff;
				border: 1px solid #e1e5e9;
				border-radius: 8px;
				padding: 20px;
			} */

			.seamless-connection-success {
				display: flex;
				align-items: center;
				gap: 14px;
				padding: 16px;
				background: #f0fdf4;
				border: 1px solid #bbf7d0;
				border-radius: 12px;
				margin-bottom: 20px;
			}

			.seamless-success-icon {
				width: 48px;
				height: 48px;
				background: #10b981;
				border-radius: 50%;
				display: flex;
				align-items: center;
				justify-content: center;
				flex-shrink: 0;
			}

			.seamless-success-icon .dashicons {
				font-size: 28px;
				width: 28px;
				height: 28px;
				color: #fff;
			}

			.seamless-success-content h3 {
				margin: 0 0 4px 0;
				font-size: 16px;
				font-weight: 600;
				color: #065f46;
			}

			.seamless-success-content p {
				margin: 0;
				color: #047857;
				font-size: 13px;
			}

			.seamless-connection-details {
				background: #f7fafc;
				border-radius: 12px;
				border: 1px solid #8573e7a8;
				padding: 8px 16px;
				margin-bottom: 20px;
			}

			.seamless-detail-item {
				display: flex;
				justify-content: space-between;
				align-items: center;
				padding: 10px 0;
				border-bottom: 1px solid #e1e5e9;
			}

			.seamless-detail-item:last-child {
				border-bottom: none;
			}

			.seamless-detail-label {
				display: flex;
				align-items: center;
				gap: 8px;
				font-size: 14px;
				font-weight: 500;
				color: #4a5568;
			}

			.seamless-detail-label .dashicons {
				font-size: 16px;
				width: 16px;
				height: 16px;
				color: #6c5ce7;
			}

			.seamless-detail-value {
				font-size: 14px;
				color: #2d3748;
				font-weight: 500;
			}

			.seamless-status-badge.connected {
				background: #d1fae5;
				color: #065f46;
				border: 1px solid #a7f3d0;
			}

			.seamless-connection-actions {
				display: flex;
				justify-content: flex-end;
			}

			/* Standardize all buttons */
			.button,
			.button-secondary,
			.button-primary {
				display: inline-flex !important;
				align-items: center !important;
				gap: 8px !important;
				padding: 8px 16px !important;
				border-radius: 12px !important;
				font-size: 14px !important;
				font-weight: 500 !important;
				height: auto !important;
				transition: all 0.2s ease !important;
				border: 1px solid !important;
			}

			.button .dashicons,
			.button-secondary .dashicons,
			.button-primary .dashicons {
				font-size: 16px;
				width: 16px;
				height: 16px;
			}

			.button-secondary {
				background: #fff !important;
				color: #6c5ce7 !important;
				border-color: #e1e5e9 !important;
				line-height: 1 !important;
			}

			.button-secondary:hover {
				background: #f7fafc !important;
				border-color: #6c5ce7 !important;
			}

			@keyframes spin {
				from {
					transform: rotate(0deg);
				}

				to {
					transform: rotate(360deg);
				}
			}

			.spin {
				animation: spin 1s linear infinite;
			}

			/* Scoped admin loader (as requested) */
			.seamless-admin-loader {
				position: fixed;
				top: 0;
				left: 0;
				right: 0;
				bottom: 0;
				background: rgba(255, 255, 255, 0.85);
				display: flex;
				flex-direction: column;
				align-items: center;
				justify-content: center;
			}

			.seamless-admin-loader.hidden {
				display: none;
			}

			.seamless-admin-spinner {
				width: 30px;
				height: 30px;
				border: 3px solid #e5e5e5;
				border-top-color: #6c5ce7;
				border-radius: 50%;
				animation: seamless-admin-spin 1s linear infinite;
			}

			.seamless-admin-loading-text {
				margin-top: 12px;
				font-weight: 600;
				color: #333;
			}

			@keyframes seamless-admin-spin {
				to {
					transform: rotate(360deg);
				}
			}

			/* Scope the loader to the table area only */
			.seamless-table-area {
				position: relative;
			}

			.seamless-table-area .seamless-admin-loader {
				position: absolute;
				/* overrides fixed for scoping */
				top: 0;
				left: 0;
				right: 0;
				bottom: 0;
			}

			/* Modern table + search styles */
			.seamless-search-bar {
				display: flex;
				align-items: center;
				gap: 10px;
				margin: 20px 0;
				flex-wrap: wrap;
			}

			.seamless-search-input {
				display: flex;
				align-items: center;
				gap: 8px;
				background: #fff;
				border: 1px solid #e5e5e5;
				border-radius: 12px;
				padding: 6px 10px;
				min-width: 350px;
				box-shadow: 0 1px 1px rgba(0, 0, 0, .04);
			}

			.seamless-search-input .dashicons {
				color: #6c5ce7;
			}

			.seamless-search-field {
				border: none !important;
				outline: none !important;
				box-shadow: none !important;
				width: 100%;
				border-radius: 8px !important;
			}

			.seamless-card .seamless-search-button {
				background: #6c5ce7 !important;
				border-color: #6c5ce7 !important;
				box-shadow: none !important;
				text-shadow: none !important;
				display: inline-flex;
				align-items: center;
				border-radius: 8px;
				gap: 6px;
				padding: 5px 15px;
				line-height: 20px;
			}

			.seamless-card .seamless-search-button:hover {
				background: #5849c3 !important;
			}

			/* Reset button styling */
			.seamless-card .seamless-search-reset {
				background: transparent !important;
				border: 1px solid #e5e5e5 !important;
				box-shadow: none !important;
				text-shadow: none !important;
				line-height: 26px !important;
				color: #6c5ce7 !important;
				display: inline-flex;
				align-items: center;
				border-radius: 8px;
				gap: 6px;
				padding: 5px 15px;
				transition: all 0.2s ease-in-out;
			}

			.seamless-card .seamless-search-reset:hover {
				border-color: #6c5ce7 !important;
				background: #f7f7ff !important;
				color: #5849c3 !important;
			}


			#fetch-latest-events-btn .dashicons,
			#fetch-latest-membership-data-btn .dashicons {
				font-size: 18px;
				width: 18px;
				height: 18px;
			}

			/* Spin animation for dashicons when fetching */
			.dashicons.spin {
				animation: seamless-spin 1s linear infinite;
			}

			@keyframes seamless-spin {
				from {
					transform: rotate(0deg);
				}

				to {
					transform: rotate(360deg);
				}
			}

			.seamless-table {
				border: 1px solid #e1e5e9;
				border-radius: 12px;
				overflow: hidden;
				box-shadow: 0 1px 3px rgba(0, 0, 0, .05);
			}

			.seamless-table thead th {
				/* background: linear-gradient(135deg, #f7fafc 0%, #edf2f7 100%); */
				background-color: #f7fafc;
				color: #2d3748;
				border-bottom: 2px solid #e1e5e9;
				font-weight: 600;
				padding: 14px 12px;
				font-size: 13px;
				text-transform: uppercase;
				letter-spacing: 0.5px;
			}

			.alternate,
			.striped>tbody>:nth-child(odd),
			ul.striped>:nth-child(odd) {
				background-color: #fff;
			}

			.seamless-table tbody td {
				padding: 14px 12px;
				border-bottom: 1px solid #f7fafc;
				font-size: 14px;
				color: #4a5568;
			}

			.seamless-table tbody tr {
				transition: background-color 0.2s ease;
			}

			.seamless-table tbody tr:hover {
				background: #edf2f7;
			}

			.seamless-table tbody tr:last-child td {
				border-bottom: none;
			}

			.seamless-blur-table .seamless-blur {
				filter: blur(3px);
				pointer-events: none;
				user-select: none;
				opacity: 0.7;
			}

			.seamless-blur-message {
				position: absolute;
				top: 40px;
				left: 0;
				right: 0;
				text-align: center;
				z-index: 2;
				font-size: 18px;
				font-weight: bold;
				color: #d63638;
			}

			.seamless-blur-wrapper {
				position: relative;
			}

			/* SSO Tab Styles */

			.seamless-sso-config {
				background: #fff;
				border: 1px solid #e1e5e9;
				border-radius: 12px;
				padding: 20px;
				margin-bottom: 20px;
			}

			.seamless-sso-config h3 {
				margin: 0 0 16px 0;
				font-size: 16px;
				font-weight: 600;
				color: #2d3748;
			}

			.seamless-sso-shortcode-section {
				background: #f7fafc;
				border: 1px solid #e1e5e9;
				border-radius: 12px;
				padding: 16px;
			}

			.seamless-sso-shortcode-section h3 {
				margin: 0 0 10px 0;
				font-size: 15px;
				font-weight: 600;
				color: #2d3748;
			}

			.seamless-sso-shortcode-section p {
				margin: 0 0 12px 0;
				color: #4a5568;
				font-size: 13px;
			}

			/* Toast Notifications */
			.seamless-toast {
				position: fixed;
				top: 32px;
				right: -400px;
				background: #fff;
				padding: 16px 20px;
				border-radius: 8px;
				box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
				z-index: 999999;
				min-width: 300px;
				max-width: 400px;
				transition: right 0.3s ease;
				display: flex;
				align-items: center;
				gap: 12px;
				font-size: 14px;
				font-weight: 500;
			}

			.seamless-toast.show {
				right: 20px;
			}

			.seamless-toast-success {
				border-left: 4px solid #10b981;
				color: #065f46;
			}

			.seamless-toast-success::before {
				content: "✓";
				display: inline-flex;
				align-items: center;
				justify-content: center;
				width: 24px;
				height: 24px;
				background: #10b981;
				color: #fff;
				border-radius: 50%;
				font-weight: bold;
				flex-shrink: 0;
			}

			.seamless-toast-error {
				border-left: 4px solid #ef4444;
				color: #991b1b;
			}

			.seamless-toast-error::before {
				content: "✕";
				display: inline-flex;
				align-items: center;
				justify-content: center;
				width: 24px;
				height: 24px;
				background: #ef4444;
				color: #fff;
				border-radius: 50%;
				font-weight: bold;
				flex-shrink: 0;
			}

			.seamless-code-block {
				display: inline-block;
				cursor: pointer;
				margin: 0 !important;
				background: #fff;
				padding: 6px 10px;
				border: 1px solid #e1e5e9;
				border-radius: 6px;
				font-family: 'Courier New', monospace;
				font-size: 12px;
				color: #6c5ce7;
				line-height: 18px;
				word-break: break-all;
				transition: background-color 0.2s ease;
			}

			.seamless-code-block:hover {
				background-color: #f7fafc;
			}


			/* Confirmation Toast Styles */
			.seamless-toast-confirm {
				border-left: 4px solid #f59e0b;
				color: #92400e;
				flex-direction: column;
				align-items: stretch;
				gap: 16px;
				min-width: 350px;
			}

			.seamless-toast-message {
				font-size: 14px;
				line-height: 1.5;
			}

			.seamless-toast-actions {
				display: flex;
				gap: 10px;
				justify-content: flex-end;
			}

			.seamless-toast-btn {
				padding: 8px 20px;
				border: none;
				border-radius: 6px;
				font-size: 13px;
				font-weight: 600;
				cursor: pointer;
				transition: all 0.2s ease;
				outline: none;
			}

			.seamless-toast-btn-cancel {
				background: #f3f4f6;
				color: #4b5563;
			}

			.seamless-toast-btn-cancel:hover {
				background: #e5e7eb;
				color: #1f2937;
			}

			.seamless-toast-btn-confirm {
				background: #ef4444;
				color: #fff;
			}

			.seamless-toast-btn-confirm:hover {
				background: #dc2626;
			}

			/* Responsive Design */
			@media screen and (max-width: 1024px) {
				.seamless-form-grid {
					grid-template-columns: 1fr;
				}
			}

			@media screen and (max-width: 760px) {

				.seamless-welcome-main,
				.seamless-welcome-features {
					grid-template-columns: auto;
				}

				.seamless-auth-section,
				.seamless-sso-section {
					padding: 0;
				}

				.seamless-auth-setup,
				.seamless-auth-connected,
				.seamless-sso-config {
					padding: 16px;
				}

				.seamless-card>* {
					padding: 16px;
				}

				.seamless-search-bar {
					flex-direction: column;
					align-items: stretch;
				}

				.seamless-search-input {
					min-width: 100%;
				}

				.nav-tab {
					padding: 12px 16px;
					font-size: 13px;
				}
			}

			@media screen and (max-width: 760px) {

				.seamless-welcome-main,
				.seamless-welcome-features {
					grid-template-columns: auto;
				}
			}
		</style>
<?php
	}

	public function maybe_flush_permalinks($option, $old_value, $value): void
	{
		$target_options = [
			'seamless_event_list_endpoint',
			'seamless_single_event_endpoint'
			// 'seamless_single_donation_endpoint',
			// 'seamless_membership_list_endpoint',
			// 'seamless_single_membership_endpoint',
		];
		if (in_array($option, $target_options, true)) {
			flush_rewrite_rules();
		}
	}

	private function remove_all_notices(): void
	{
		// Remove all actions that add notices
		remove_all_actions('admin_notices');
		remove_all_actions('all_admin_notices');
	}

	public function schedule_rewrite_flush(): void
	{
		update_option('seamless_flush_needed', true);
	}

	public function maybe_flush_rewrite_rules(): void
	{
		if (get_option('seamless_flush_needed')) {
			flush_rewrite_rules();
			delete_option('seamless_flush_needed');
		}
	}

	public function handle_domain_change($option, $old_value, $value): void
	{
		if ($old_value !== $value) {
			$this->auth->disconnect();
		}
	}

	public function handle_connect(): void
	{
		if (!wp_verify_nonce($_POST['nonce'] ?? '', 'seamless_connect')) {
			wp_send_json_error('Invalid nonce');
		}

		if (! current_user_can('manage_options')) {
			wp_send_json_error('Insufficient permissions');
		}

		try {
			$token = $this->auth->fetch_token();

			if ($token) {
				wp_send_json_success('Authenticated successfully');
			} else {
				wp_send_json_error('Unable to authenticate with provided credentials');
			}
		} catch (\Throwable $e) {
			wp_send_json_error($e->getMessage());
		}
	}





	public function preserve_tab_on_settings_save($location, $status): string
	{
		// Only modify redirects for settings pages
		if (strpos($location, 'page=seamless') === false) {
			return $location;
		}

		// Strip transient query params that should not persist across saves
		$location = \remove_query_arg(['refetch', 'show'], $location);

		// Check if we have a tab to preserve
		if (isset($_POST['_seamless_return_tab']) && !empty($_POST['_seamless_return_tab'])) {
			$tab = sanitize_text_field($_POST['_seamless_return_tab']);

			// Use add_query_arg to properly add/update the tab parameter
			$location = \add_query_arg('tab', $tab, $location);
		}

		return $location;
	}



	// Inside class SettingsPage
	public function render_protected_post_types_field(): void
	{
		$value = get_option('seamless_protected_post_types', 'post,page');
		echo '<input type="text" name="seamless_protected_post_types" value="' . esc_attr($value) . '" class="regular-text" placeholder="post, page, my_custom_cpt">';
		echo '<p class="description">Comma-separated list of post type slugs (e.g., <code>post, page, product</code>) where the restriction fields should be enforced.</p>';
	}

	public function render_membership_purchase_url_field(): void
	{
		$value = get_option('seamless_membership_purchase_url', home_url('/membership'));
		echo '<input type="url" name="seamless_membership_purchase_url" value="' . esc_attr($value) . '" class="regular-text">';
		echo '<p class="description">The URL where logged-in users are redirected to purchase or upgrade their membership.</p>';
	}
}
