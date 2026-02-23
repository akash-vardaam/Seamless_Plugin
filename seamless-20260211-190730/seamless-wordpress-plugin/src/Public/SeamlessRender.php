<?php

namespace Seamless\Public;

use Carbon_Fields\Carbon_Fields;
use Seamless\Auth\SeamlessAuth as Auth;
use Seamless\Auth\SeamlessSSO as SSO;
use Seamless\Operations\Events;
use Seamless\Operations\Membership;
use Elementor\Plugin;

class SeamlessRender
{
	private Auth $auth;
	private SSO $sso;

	public function __construct()
	{
		$this->auth = new Auth();
		$this->sso = new SSO();

		add_action('wp_enqueue_scripts', [$this, 'enqueue_seamless_assets']);
		add_action('admin_enqueue_scripts', [$this, 'enqueue_seamless_assets']);
		add_filter('script_loader_tag', [$this, 'add_module_type_attribute'], 10, 3);

		// AJAX hooks
		// Event rendering hook (API data is fetched client-side, then rendered server-side)
		add_action('wp_ajax_render_event_template', [$this, 'ajax_render_event_template']);
		add_action('wp_ajax_nopriv_render_event_template', [$this, 'ajax_render_event_template']);
		add_action('wp_ajax_seamless_upgrade_membership', [$this, 'ajax_upgrade_membership']);
		add_action('wp_ajax_seamless_downgrade_membership', [$this, 'ajax_downgrade_membership']);
		add_action('wp_ajax_seamless_cancel_membership', [$this, 'ajax_cancel_membership']);
		add_action('wp_ajax_seamless_renew_membership', [$this, 'ajax_renew_membership']);
		add_action('wp_ajax_seamless_cancel_scheduled_change', [$this, 'ajax_cancel_scheduled_change']);
		add_action('wp_ajax_seamless_update_profile', [$this, 'ajax_update_profile']);
		// React token-refresh endpoint: returns a fresh AMS access token for the logged-in user.
		add_action('wp_ajax_seamless_refresh_token', [$this, 'ajax_refresh_token']);

		// Dashboard Async Loading Hooks
		add_action('wp_ajax_seamless_get_dashboard_profile', [$this, 'ajax_get_dashboard_profile']);
		add_action('wp_ajax_seamless_get_dashboard_memberships', [$this, 'ajax_get_dashboard_memberships']);
		add_action('wp_ajax_seamless_get_dashboard_courses', [$this, 'ajax_get_dashboard_courses']);
		add_action('wp_ajax_seamless_get_dashboard_orders', [$this, 'ajax_get_dashboard_orders']);

		add_action('wp_head', [$this, 'enqueue_dynamic_styles']);
		add_action('wp_head', [$this, 'add_wordpress_config_meta_tags']);

		add_action('rest_api_init', [$this->sso, 'register_rest_routes']);
		// add_filter('the_content', [$this, 'apply_content_restrictions']);
		// add_action('pre_get_posts', [$this, 'override_plugin_pages']);
		add_action('after_setup_theme', function () {
			Carbon_Fields::boot();
		});

		$this->register_shortcodes();
	}

	/**
	 * AJAX: Return a fresh AMS access token for the currently logged-in user.
	 *
	 * The React app calls this endpoint whenever it receives a 401 from the AMS
	 * API, so that it can silently refresh the token and retry the original request
	 * without redirecting the user.
	 *
	 * Request:  POST admin-ajax.php  action=seamless_refresh_token  nonce=<ajaxNonce>
	 * Response: { success: true, data: { token: "..." } }
	 *        or { success: false, data: { message: "..." } }
	 */
	public function ajax_refresh_token(): void
	{
		// Verify nonce (sent by the React app from window.seamlessReactConfig.ajaxNonce)
		check_ajax_referer('seamless_nonce', 'nonce');

		if (!is_user_logged_in()) {
			wp_send_json_error(['message' => 'Not authenticated.'], 401);
			return;
		}

		$uid   = get_current_user_id();
		$token = '';

		// Use the SSO helper: returns current token if still valid, otherwise calls
		// the refresh_token OAuth grant and saves the new tokens to user meta.
		if (class_exists('Seamless\\Auth\\SeamlessSSO')) {
			$sso   = new \Seamless\Auth\SeamlessSSO();
			$token = $sso->seamless_refresh_token_if_needed($uid) ?: '';
		}

		if (empty($token)) {
            // Do NOT fallback to the old token if the refresh failed.
            // If the SSO refresh fails, it means the refresh token is expired or invalid.
            // Returning the old token causes a 401 loop on the frontend.
            // Instead, tell the frontend the user needs to log in again.
			wp_send_json_error(['message' => 'Your session has expired. Please log in again.'], 401);
			return;
		}

		wp_send_json_success(['token' => $token]);
	}

	public function enqueue_dynamic_styles()
	{
		$color_scheme = get_option('seamless_color_scheme', 'theme');
		$styles = [];

		if ($color_scheme === 'plugin') {
			$styles['--seamless-primary-color'] = get_option('seamless_primary_color');
			$styles['--seamless-secondary-color'] = get_option('seamless_secondary_color');
			$styles['--seamless-link-color'] = get_option('seamless_secondary_color');
			$styles['--seamless-secondary-hover-color'] = $this->adjust_brightness(get_option('seamless_secondary_color'), -20);
		} else {
			// Elementor theme support
			if (did_action('elementor/loaded')) {
				$kit = \Elementor\Plugin::$instance->kits_manager->get_active_kit_for_frontend();
				$kit_settings = $kit->get_settings();

				if (!empty($kit_settings['system_colors'])) {
					$system_colors = $kit_settings['system_colors'];
					$color_map = [
						'primary' => '--seamless-primary-color',
						'secondary' => '--seamless-secondary-color',
						'accent' => '--seamless-accent-color',
						'text' => '--seamless-text-color',
					];

					foreach ($system_colors as $color) {
						if (isset($color_map[$color['_id']])) {
							$styles[$color_map[$color['_id']]] = $color['color'];
						}
					}

					// Set link and hover colors based on Elementor globals
					if (isset($styles['--seamless-primary-color'])) {
						$styles['--seamless-link-color'] = $styles['--seamless-primary-color'];
					}
					if (isset($styles['--seamless-secondary-color'])) {
						$styles['--seamless-secondary-hover-color'] = $this->adjust_brightness($styles['--seamless-secondary-color'], -20);
					}
				}
			}

			// General theme support (fallback)
			if (empty($styles)) {
				$font_family = get_theme_mod('body_font_family', get_theme_mod('body_font'));
				if ($font_family) {
					$styles['--seamless-font-family'] = $font_family;
				}

				$heading_color = get_theme_mod('heading_color');
				$primary_color = get_theme_mod('primary_color');
				$theme_primary_color = $heading_color ?: $primary_color;
				if ($theme_primary_color) {
					$styles['--seamless-primary-color'] = $theme_primary_color;
				}

				$link_color = get_theme_mod('link_color');
				if ($link_color) {
					$styles['--seamless-link-color'] = $link_color;
					$styles['--seamless-secondary-hover-color'] = $this->adjust_brightness($link_color, -20);
				} else if ($theme_primary_color) {
					$styles['--seamless-link-color'] = $theme_primary_color;
				}

				$accent_color = get_theme_mod('accent_color');
				if ($accent_color) {
					$styles['--seamless-secondary-color'] = $accent_color;
				} else if ($theme_primary_color) {
					$styles['--seamless-secondary-color'] = $theme_primary_color;
				}

				$text_color = get_theme_mod('text_color');
				if ($text_color) {
					$styles['--seamless-text-color'] = $text_color;
				}

				if (empty($theme_primary_color) && function_exists('get_theme_support') && current_theme_supports('editor-color-palette')) {
					$palette = get_theme_support('editor-color-palette')[0];
					if (!empty($palette)) {
						$styles['--seamless-primary-color']   = $palette[0]['color'] ?? '#26337a';
						$styles['--seamless-secondary-color'] = $palette[1]['color'] ?? $styles['--seamless-primary-color'];
						$styles['--seamless-accent-color']    = $palette[2]['color'] ?? $styles['--seamless-secondary-color'];
						$styles['--seamless-text-color']      = $palette[3]['color'] ?? '#222';

						// Optionally set link color and hover color
						$styles['--seamless-link-color'] = $styles['--seamless-primary-color'];
						$styles['--seamless-secondary-hover-color'] = $this->adjust_brightness($styles['--seamless-secondary-color'], -20);
					}
				}
			}
		}


		if (!empty($styles)) {
			echo "\n" . '<style id="seamless-dynamic-styles">' . "\n";
			echo ":root {\n";
			foreach ($styles as $key => $value) {
				$key = esc_attr(trim($key));
				$value = esc_attr(trim($value));
				echo "\t{$key}: {$value};\n";
			}
			echo "}\n";
			echo "</style>\n";
		}
	}

	private function adjust_brightness($hex, $steps)
	{
		$steps = max(-255, min(255, $steps));
		$hex = str_replace('#', '', $hex);
		if (strlen($hex) == 3) {
			$hex = str_repeat(substr($hex, 0, 1), 2) . str_repeat(substr($hex, 1, 1), 2) . str_repeat(substr($hex, 2, 1), 2);
		}
		$r = max(0, min(255, hexdec(substr($hex, 0, 2)) + $steps));
		$g = max(0, min(255, hexdec(substr($hex, 2, 2)) + $steps));
		$b = max(0, min(255, hexdec(substr($hex, 4, 2)) + $steps));
		return '#' . str_pad(dechex($r), 2, '0', STR_PAD_LEFT) . str_pad(dechex($g), 2, '0', STR_PAD_LEFT) . str_pad(dechex($b), 2, '0', STR_PAD_LEFT);
	}

	public function enqueue_seamless_assets()
	{
		$plugin_url = plugin_dir_url(__FILE__);
		$plugin_path = plugin_dir_path(__DIR__);
		$dist_path = plugin_dir_path(__FILE__) . 'dist/';

		wp_enqueue_style(
			'seamless-montserrat',
			'https://fonts.googleapis.com/css2?family=Montserrat:ital,wght@0,100..900;1,100..900&display=swap',
			[],
			null
		);

		wp_enqueue_style(
			'seamless-font-awesome-cdn',
			'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css',
			[],
			null
		);

		$toast_css_file = 'css/toastUICalendar.css';
		if (file_exists($dist_path . $toast_css_file)) {
			wp_enqueue_style(
				'seamless-toast-ui-calendar-css',
				$plugin_url . 'dist/' . $toast_css_file,
				[],
				filemtime($dist_path . $toast_css_file)
			);
		}

		$seamless_css_file = 'css/seamless.css';
		if (file_exists($dist_path . $seamless_css_file)) {
			wp_enqueue_style(
				'seamless-main-style',
				$plugin_url . 'dist/' . $seamless_css_file,
				[],
				filemtime($dist_path . $seamless_css_file)
			);
		}

		// Enqueue restriction template CSS
		$general_css_path = plugin_dir_path(__FILE__) . 'assets/css/general.css';
		if (file_exists($general_css_path)) {
			wp_enqueue_style(
				'seamless-restriction-style',
				$plugin_url . 'assets/css/general.css',
				['seamless-main-style'],
				filemtime($general_css_path)
			);
		}

		// Enqueue toast notification CSS
		$toast_css_path = plugin_dir_path(__FILE__) . 'assets/css/toast.css';
		if (file_exists($toast_css_path)) {
			wp_enqueue_style(
				'seamless-toast-style',
				$plugin_url . 'assets/css/toast.css',
				[],
				filemtime($toast_css_path)
			);
		}

		// Enqueue API Client JavaScript
		$api_client_js_path = plugin_dir_path(__FILE__) . 'assets/js/seamless-api-client.js';
		if (file_exists($api_client_js_path)) {
			wp_enqueue_script(
				'seamless-api-client-js',
				$plugin_url . 'assets/js/seamless-api-client.js',
				[],
				filemtime($api_client_js_path),
				true // Load in footer
			);

			wp_localize_script('seamless-api-client-js', 'seamless_ajax', [
				'ajax_url' => admin_url('admin-ajax.php'),
				'nonce'    => wp_create_nonce('seamless_nonce'),
				'list_view_layout' => get_option('seamless_list_view_layout', 'option_1'),
				'api_domain' => rtrim(get_option('seamless_client_domain', ''), '/'),
				'single_event_endpoint' => get_option('seamless_single_event_endpoint', 'event'),
			]);
		}

		$seamless_js_file = 'js/seamless.js';
		if (file_exists($dist_path . $seamless_js_file)) {
			wp_enqueue_script(
				'seamless-vite-main-js',
				$plugin_url . 'dist/' . $seamless_js_file,
				['jquery', 'seamless-api-client-js'],
				filemtime($dist_path . $seamless_js_file),
				true // Load in footer
			);
		}
	}

	public function add_module_type_attribute($tag, $handle, $src): mixed
	{
		if (!wp_script_is($handle, 'registered') && !wp_script_is($handle, 'enqueued')) {
			return $tag;
		}

		// Add your bundled script handle if you need module type
		if (in_array($handle, ['seamless-toast-ui-calendar-js', 'seamless-vite-main-js'])) {
			return str_replace('<script ', '<script type="module" ', $tag);
		}

		return $tag;
	}

	// {
	// 	// Add type="module" to your specific scripts
	// 	if (in_array($handle, ['seamless-toast-ui-calendar-js', 'seamless-vite-main-js'])) {
	// 		$tag = str_replace('<script ', '<script type="module" ', $tag);
	// 	}
	// 	return $tag;
	// }

	private function get_authentication_required_message(): string
	{
		return '<div class="seamless-authentication-required"><p>' . esc_html__('Please authenticate to fetch data. Open the Seamless Plugin Authentication tab, enter your credentials, and connect to continue.', 'seamless') . '</p></div>';
	}

	public function add_wordpress_config_meta_tags(): void
	{
		?>
		<meta name="wordpress-site-url" content="<?php echo esc_url(home_url()); ?>" />
		<meta name="rest-api-base-url" content="<?php echo esc_url(rest_url()); ?>" />
		<script>
			window.seamlessReactConfig = {
				siteUrl: '<?php echo esc_url(home_url()); ?>',
				restUrl: '<?php echo esc_url(rest_url()); ?>',
				nonce: '<?php echo wp_create_nonce('seamless'); ?>'
			};
		</script>
		<?php
	}

	private function register_shortcodes(): void
	{
		// React-powered shortcodes (replace old JS view layer)
		add_shortcode('seamless_events_list',   [$this, 'shortcode_react_events_list']);
		add_shortcode('seamless_single_event',  [$this, 'shortcode_react_single_event']);
		add_shortcode('seamless_memberships',   [$this, 'shortcode_react_memberships']);
		add_shortcode('seamless_courses',       [$this, 'shortcode_react_courses']);
		add_shortcode('seamless_dashboard',     [$this, 'shortcode_react_dashboard']);

		// Legacy aliases kept for backward compatibility
		add_shortcode('seamless_event_list',    [$this, 'shortcode_react_events_list']);
		add_shortcode('seamless_user_dashboard',[$this, 'shortcode_react_dashboard']);
		add_shortcode('seamless_events',        [$this, 'shortcode_react_events_list']);
	}

	private function seamless_get_template($template_name): string
	{
		return locate_template($template_name) ?: plugin_dir_path(__FILE__) . 'templates/' . $template_name;
	}

	// ─────────────────────────────────────────────────────────────────────────────
	// React Shortcode Renderers
	// Each shortcode mounts one independent React root on the page.
	// The React app detects `data-seamless-view` and renders the correct component.
	// ─────────────────────────────────────────────────────────────────────────────

	/**
	 * Helper: enqueue the React build assets once per page load.
	 * Called by every React shortcode renderer to ensure the JS/CSS are loaded.
	 */
	private function enqueue_react_assets(): void
	{
		static $react_assets_enqueued = false;
		if ($react_assets_enqueued) {
			return;
		}
		$react_assets_enqueued = true;

		$plugin_dir = plugin_dir_path(dirname(__DIR__));
		$plugin_url = plugin_dir_url(dirname(__DIR__));

		$dist_folder = $plugin_dir . 'src/Public/assets/react-build/dist/';
		$dist_url    = $plugin_url . 'src/Public/assets/react-build/dist/';

		if (!is_dir($dist_folder)) {
			error_log('Seamless React: build folder not found at ' . $dist_folder);
			return;
		}

		$assets_folder = $dist_folder . 'assets/';
		if (!is_dir($assets_folder)) {
			error_log('Seamless React: assets sub-folder not found at ' . $assets_folder);
			return;
		}

		$files = scandir($assets_folder);

		// Enqueue CSS
		foreach ($files as $file) {
			if (strpos($file, 'index-') === 0 && str_ends_with($file, '.css')) {
				$css_path = $assets_folder . $file;
				wp_enqueue_style(
					'seamless-react-css',
					$dist_url . 'assets/' . $file,
					[],
					filemtime($css_path)
				);
				break;
			}
		}

		// Enqueue JS (loaded as module so React 19 ESM bundles work)
		foreach ($files as $file) {
			if (strpos($file, 'index-') === 0 && str_ends_with($file, '.js')) {
				$js_path = $assets_folder . $file;
				wp_enqueue_script(
					'seamless-react-js',
					$dist_url . 'assets/' . $file,
					[],
					filemtime($js_path),
					true
				);
				break;
			}
		}

		// Resolve a fresh, valid access token to inject into the React app.
		// Uses the existing SeamlessSSO refresh logic: if the stored token is
		// still valid it is returned as-is; if it's missing or expired it is
		// refreshed via the refresh_token grant and saved to user meta.
		$access_token = '';
		if (is_user_logged_in()) {
			$uid = get_current_user_id();
			// Try the SSO refresh helper first (handles expiry automatically).
			if (class_exists('Seamless\\Auth\\SeamlessSSO')) {
				$sso          = new \Seamless\Auth\SeamlessSSO();
				$access_token = $sso->seamless_refresh_token_if_needed($uid) ?: '';
			}
			// Fallback: read whatever is stored in user meta if refresh failed.
			if (empty($access_token)) {
				$access_token = get_user_meta($uid, 'seamless_access_token', true) ?: '';
			}
		}

		// Pass WordPress config to the React app
		wp_localize_script('seamless-react-js', 'seamlessReactConfig', [
			'siteUrl'      => esc_url(home_url()),
			'restUrl'      => esc_url(rest_url()),
			'nonce'        => wp_create_nonce('seamless'),
			'ajaxUrl'      => admin_url('admin-ajax.php'),
			'ajaxNonce'    => wp_create_nonce('seamless_nonce'),
			'clientDomain' => rtrim(get_option('seamless_client_domain', ''), '/'),
			'isLoggedIn'   => is_user_logged_in(),
			'userEmail'    => is_user_logged_in() ? wp_get_current_user()->user_email : '',
			'accessToken'  => $access_token,
		]);
	}

	/**
	 * Helper: generate the mount-point HTML for a React shortcode.
	 *
	 * @param string $view   The value of data-seamless-view (matches App.tsx VIEW_ROUTES keys).
	 * @param array  $extras Extra data attributes to pass to the React app.
	 * @return string
	 */
	private function react_mount_html(string $view, array $extras = []): string
	{
		$this->enqueue_react_assets();

		$data_attrs  = 'data-seamless-view="' . esc_attr($view) . '"';
		$data_attrs .= ' data-site-url="' . esc_url(home_url()) . '"';

		foreach ($extras as $key => $value) {
			$data_attrs .= ' data-' . esc_attr($key) . '="' . esc_attr($value) . '"';
		}

		// Each shortcode gets a unique ID so multiple can coexist on one page.
		$uid = 'seamless-react-' . $view . '-' . uniqid();

		return sprintf(
			'<div id="%s" class="seamless-react-root" %s></div>',
			esc_attr($uid),
			$data_attrs
		);
	}

	// ── Shortcode: Events Listing ────────────────────────────────────────────

	/**
	 * [seamless_events_list] / [seamless_event_list] / [seamless_events]
	 * Renders the React-powered Events Listing page.
	 */
	public function shortcode_react_events_list($atts = []): string
	{
		if (!$this->auth->is_authenticated()) {
			return $this->get_authentication_required_message();
		}
		return $this->react_mount_html('events');
	}

	// ── Shortcode: Single Event ──────────────────────────────────────────────

	/**
	 * [seamless_single_event slug="my-event-slug" type="event|group_event"]
	 * Renders the React-powered Single Event page.
	 *
	 * Falls back gracefully: if the React build is not present the PHP renders
	 * the original single-event shell (the previous behaviour), maintaining
	 * backward compatibility.
	 */
	public function shortcode_react_single_event($atts = []): string
	{
		if (!$this->auth->is_authenticated()) {
			return $this->get_authentication_required_message();
		}

		$atts = shortcode_atts([
			'slug' => '',
			'type' => 'event',
		], $atts, 'seamless_single_event');

		$slug = sanitize_text_field($atts['slug']);
		$type = sanitize_text_field($atts['type']);

		// Check if React build exists; fall back to legacy shell if not.
		$plugin_dir  = plugin_dir_path(dirname(__DIR__));
		$dist_folder = $plugin_dir . 'src/Public/assets/react-build/dist/';

		if (!is_dir($dist_folder)) {
			// Legacy fallback – preserves old behaviour
			$loader_html = '<div class="loader-container"><div id="Seamlessloader" class="three-body hidden"><div class="three-body__dot"></div><div class="three-body__dot"></div><div class="three-body__dot"></div></div></div>';
			return '<div id="singleEventWrapper" class="single_event_container">'
				. $loader_html
				. '<div id="event_detail" data-event-slug="' . esc_attr($slug) . '" data-event-type="' . esc_attr($type) . '"></div>'
				. '</div>';
		}

		return $this->react_mount_html('single-event', [
			'seamless-slug' => $slug,
			'seamless-type' => $type,
		]);
	}

	// ── Shortcode: Memberships ───────────────────────────────────────────────

	/**
	 * [seamless_memberships]
	 * Renders the React-powered Membership plans page.
	 */
	public function shortcode_react_memberships($atts = []): string
	{
		if (!$this->auth->is_authenticated()) {
			return $this->get_authentication_required_message();
		}
		return $this->react_mount_html('memberships');
	}

	// ── Shortcode: Courses ───────────────────────────────────────────────────

	/**
	 * [seamless_courses]
	 * Renders the React-powered Courses page.
	 */
	public function shortcode_react_courses($atts = []): string
	{
		if (!$this->auth->is_authenticated()) {
			return $this->get_authentication_required_message();
		}
		return $this->react_mount_html('courses');
	}

	// ── Shortcode: Dashboard ─────────────────────────────────────────────────

	/**
	 * [seamless_dashboard] / [seamless_user_dashboard]
	 * Renders the React-powered User Dashboard.
	 * Requires the user to be logged in; shows a login prompt otherwise.
	 */
	public function shortcode_react_dashboard($atts = []): string
	{
		if (!is_user_logged_in()) {
			return do_shortcode('[seamless_login_button text="Sign in to view your dashboard" class="seamless-premium-btn seamless-login-btn"]');
		}
		return $this->react_mount_html('dashboard');
	}

	/**
	 * AJAX handler for membership upgrade
	 */
	public function ajax_upgrade_membership()
	{
		// Verify nonce
		check_ajax_referer('seamless_upgrade_membership', 'nonce');

		// Check if user is logged in
		if (!is_user_logged_in()) {
			wp_send_json_error(['message' => 'User not logged in']);
			return;
		}

		// Get parameters
		$new_plan_id = sanitize_text_field($_POST['new_plan_id'] ?? '');
		$membership_id = sanitize_text_field($_POST['membership_id'] ?? '');
		$email = sanitize_email($_POST['email'] ?? '');

		if (empty($new_plan_id) || empty($email)) {
			wp_send_json_error(['message' => 'Missing required parameters']);
			return;
		}

		// Call UserProfile operation
		$user_profile = new \Seamless\Operations\UserProfile();
		$result = $user_profile->upgrade_membership($new_plan_id, $membership_id, $email);

		if ($result['success']) {
			wp_send_json_success($result);
		} else {
			wp_send_json_error($result);
		}
	}

	/**
	 * AJAX handler for membership downgrade
	 */
	public function ajax_downgrade_membership()
	{
		// Verify nonce
		check_ajax_referer('seamless_downgrade_membership', 'nonce');

		// Check if user is logged in
		if (!is_user_logged_in()) {
			wp_send_json_error(['message' => 'User not logged in']);
			return;
		}

		// Get parameters
		$new_plan_id = sanitize_text_field($_POST['new_plan_id'] ?? '');
		$membership_id = sanitize_text_field($_POST['membership_id'] ?? '');
		$email = sanitize_email($_POST['email'] ?? '');

		if (empty($new_plan_id) || empty($email)) {
			wp_send_json_error(['message' => 'Missing required parameters']);
			return;
		}

		// Call UserProfile operation
		$user_profile = new \Seamless\Operations\UserProfile();
		$result = $user_profile->downgrade_membership($new_plan_id, $membership_id, $email);

		if ($result['success']) {
			wp_send_json_success($result);
		} else {
			wp_send_json_error($result);
		}
	}

	/**
	 * AJAX handler for membership cancellation
	 */
	public function ajax_cancel_membership()
	{
		// Verify nonce
		check_ajax_referer('seamless_cancel_membership', 'nonce');

		// Check if user is logged in
		if (!is_user_logged_in()) {
			wp_send_json_error(['message' => 'User not logged in']);
			return;
		}

		// Get parameters
		$membership_id = sanitize_text_field($_POST['membership_id'] ?? '');
		$email = sanitize_email($_POST['email'] ?? '');

		if (empty($membership_id) || empty($email)) {
			wp_send_json_error(['message' => 'Missing required parameters']);
			return;
		}

		// Call UserProfile operation
		$user_profile = new \Seamless\Operations\UserProfile();
		$result = $user_profile->cancel_membership($membership_id, $email);

		if ($result['success']) {
			wp_send_json_success($result);
		} else {
			wp_send_json_error($result);
		}
	}

	/**
	 * AJAX handler for membership renewal
	 */
	public function ajax_renew_membership()
	{
		// Verify nonce
		check_ajax_referer('seamless_renew_membership', 'nonce');

		// Check if user is logged in
		if (!is_user_logged_in()) {
			wp_send_json_error(['message' => 'User not logged in']);
			return;
		}

		// Get parameters
		$plan_id = sanitize_text_field($_POST['plan_id'] ?? '');
		$email = sanitize_email($_POST['email'] ?? '');

		if (empty($plan_id) || empty($email)) {
			wp_send_json_error(['message' => 'Missing required parameters']);
			return;
		}

		// Call UserProfile operation
		$user_profile = new \Seamless\Operations\UserProfile();
		$result = $user_profile->renew_membership($plan_id, $email);

		if ($result['success']) {
			wp_send_json_success($result);
		} else {
			wp_send_json_error($result);
		}
	}

	/**
	 * AJAX handler for cancelling scheduled membership changes
	 */
	public function ajax_cancel_scheduled_change()
	{
		// Verify nonce
		check_ajax_referer('seamless_cancel_scheduled_change', 'nonce');

		// Check if user is logged in
		if (!is_user_logged_in()) {
			wp_send_json_error(['message' => 'User not logged in']);
			return;
		}

		// Get parameters
		$membership_id = sanitize_text_field($_POST['membership_id'] ?? '');
		$email = sanitize_email($_POST['email'] ?? '');

		if (empty($membership_id) || empty($email)) {
			wp_send_json_error(['message' => 'Missing required parameters']);
			return;
		}

		// Call UserProfile operation
		$user_profile = new \Seamless\Operations\UserProfile();
		$result = $user_profile->cancel_scheduled_change($membership_id, $email);

		if ($result['success']) {
			wp_send_json_success($result);
		} else {
			wp_send_json_error($result);
		}
	}

	/**
	 * AJAX handler for profile update
	 */
	public function ajax_update_profile()
	{
		// Verify nonce
		check_ajax_referer('seamless_update_profile', 'nonce');

		// Check if user is logged in
		if (!is_user_logged_in()) {
			wp_send_json_error(['message' => 'User not logged in']);
			return;
		}

		// Get parameters
		$email = sanitize_email($_POST['email'] ?? '');
		$profile_data = $_POST['profile_data'] ?? [];

		if (empty($email) || empty($profile_data)) {
			wp_send_json_error(['message' => 'Missing required parameters']);
			return;
		}

		// Call UserProfile operation
		$user_profile = new \Seamless\Operations\UserProfile();
		$result = $user_profile->update_user_profile($email, $profile_data);

		if ($result['success']) {
			wp_send_json_success($result);
		} else {
			wp_send_json_error($result);
		}
	}

	/**
	 * AJAX handler for rendering event template with API-fetched data
	 */
	public function ajax_render_event_template()
	{
		// Verify nonce
		if (!check_ajax_referer('seamless_nonce', 'nonce', false)) {
			wp_send_json_error(['message' => 'Invalid nonce']);
			return;
		}

		// Get event data from POST
		$event_data_json = isset($_POST['event_data']) ? wp_unslash($_POST['event_data']) : '';
		$event_type = isset($_POST['event_type']) ? sanitize_text_field($_POST['event_type']) : 'event';

		if (empty($event_data_json)) {
			wp_send_json_error(['message' => 'No event data provided']);
			return;
		}

		// Decode event data
		$event = json_decode($event_data_json, true);
		if (json_last_error() !== JSON_ERROR_NONE) {
			wp_send_json_error(['message' => 'Invalid event data format']);
			return;
		}

		// Add event_type to event data
		$event['event_type'] = $event_type;

		// Render the template
		ob_start();
		include $this->seamless_get_template('tpl-single-event-detail.php');
		$html = ob_get_clean();

		wp_send_json_success($html);
	}

	/**
	 * Async Dashboard: Get Profile HTML
	 */
	public function ajax_get_dashboard_profile()
	{
		check_ajax_referer('seamless_nonce', 'nonce');

		if (!is_user_logged_in()) {
			wp_send_json_error(['message' => 'User not logged in']);
		}

		$user_profile = new \Seamless\Operations\UserProfile();
		$profileResult = $user_profile->get_user_profile();

		$profile = [];
		if ($profileResult['success']) {
			$profile = $profileResult['data'];
		} else {
			// Fallback to WP user data if API fails to minimal display
			$user = wp_get_current_user();
			$profile = [
				'first_name' => $user->first_name,
				'last_name' => $user->last_name,
				'email' => $user->user_email,
				'name' => $user->display_name
			];
		}

		$widget_id = sanitize_text_field($_POST['widget_id'] ?? 'default');

		ob_start();
		include $this->seamless_get_template('tpl-dashboard-profile.php');
		$html = ob_get_clean();

		wp_send_json_success(['html' => $html]);
	}

	/**
	 * Async Dashboard: Get Memberships HTML
	 */
	public function ajax_get_dashboard_memberships()
	{
		check_ajax_referer('seamless_nonce', 'nonce');

		if (!is_user_logged_in()) {
			wp_send_json_error(['message' => 'User not logged in']);
		}

		$user = wp_get_current_user();
		$email = $user->user_email;

		$user_profile = new \Seamless\Operations\UserProfile();
		$membershipResult = $user_profile->get_user_memberships($email);

		$current_memberships = [];
		$membership_history = [];

		if ($membershipResult['success']) {
			$current_memberships = $membershipResult['data']['current'] ?? [];
			$membership_history = $membershipResult['data']['history'] ?? [];
		}

		ob_start();
		include $this->seamless_get_template('tpl-dashboard-memberships.php');
		$html = ob_get_clean();

		wp_send_json_success(['html' => $html]);
	}

	/**
	 * Async Dashboard: Get Courses HTML
	 */
	public function ajax_get_dashboard_courses()
	{
		check_ajax_referer('seamless_nonce', 'nonce');

		if (!is_user_logged_in()) {
			wp_send_json_error(['message' => 'User not logged in']);
		}

		$user_profile = new \Seamless\Operations\UserProfile();

		// Fetch both concurrently? No, standard PHP blocking. But separate from other dashboard parts.
		$enrolled_result = $user_profile->get_enrolled_courses();
		$included_result = $user_profile->get_included_courses();

		$enrolled_courses = $enrolled_result['success'] ? ($enrolled_result['data'] ?? []) : [];
		$included_courses = $included_result['success'] ? ($included_result['data'] ?? []) : [];

		// Need profile email for progress fetching inside template
		$user = wp_get_current_user();
		$profile = ['email' => $user->user_email];
		$client_domain = rtrim(get_option('seamless_client_domain', ''), '/');

		ob_start();
		include $this->seamless_get_template('tpl-dashboard-courses.php');
		$html = ob_get_clean();

		wp_send_json_success(['html' => $html]);
	}

	/**
	 * Async Dashboard: Get Orders HTML
	 */
	public function ajax_get_dashboard_orders()
	{
		check_ajax_referer('seamless_nonce', 'nonce');

		if (!is_user_logged_in()) {
			wp_send_json_error(['message' => 'User not logged in']);
		}

		$user = wp_get_current_user();
		$email = $user->user_email;

		$user_profile = new \Seamless\Operations\UserProfile();
		$ordersResult = $user_profile->get_user_orders($email);

		$orders = $ordersResult['success'] ? ($ordersResult['data'] ?? []) : [];
		$client_domain = rtrim(get_option('seamless_client_domain', ''), '/');

		// Get orders_per_page from POST request (sent by JS), default to 6
		$orders_per_page = isset($_POST['orders_per_page']) ? (int)$_POST['orders_per_page'] : 6;

		ob_start();
		include $this->seamless_get_template('tpl-dashboard-orders.php');
		$html = ob_get_clean();

		wp_send_json_success(['html' => $html]);
	}
}
