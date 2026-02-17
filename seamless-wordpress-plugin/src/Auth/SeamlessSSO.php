<?php

namespace Seamless\Auth;

use Seamless\Auth\SeamlessCallbackHandler;
use Exception;
use Random\RandomException;
use WP_Error;
use WP_REST_Request;
use WP_User;
use Seamless\Auth\Helper;

/**
 * Class SeamlessSSO
 * Manages WordPress integration for Seamless SSO, including URL generation.
 */
class SeamlessSSO
{
    const SSO_PREFIX = 'seamless_sso';
    const REST_NAMESPACE = 'seamless-oauth/v1';
    const NONCE_ACTION = 'seamless_sso_state';
    const LOGIN_ROUTE = 'auth/login';

    private string $client_domain;
    private string $sso_client_id;

    public function __construct()
    {
        $this->client_domain = rtrim(get_option('seamless_client_domain', ''), '/');
        $this->sso_client_id = get_option('seamless_sso_client_id', '');

        add_action('init', [$this, 'register_login_rewrite_rule']);
        add_action('query_vars', [$this, 'add_login_query_vars']);
        add_action('template_redirect', [$this, 'handle_login_redirect']);
        add_action('rest_api_init', [$this, 'register_rest_routes']);
        add_shortcode('seamless_login_button', [$this, 'render_login_button']);
    }

    public function register_login_rewrite_rule(): void
    {
        add_rewrite_rule('^' . self::LOGIN_ROUTE . '/?$', 'index.php?sso_login_redirect=1', 'top');
    }

    public function add_login_query_vars(array $vars): array
    {
        $vars[] = 'sso_login_redirect';
        return $vars;
    }

    public function handle_login_redirect(): void
    {
        if (!get_query_var('sso_login_redirect')) {
            return;
        }

        $this->ensure_session_started();

        $return_to = self::get_return_to_url();
        $login_url = $this->get_login_url($return_to);

        wp_redirect($login_url);
        exit;
    }

    // --- REST API Callback ---

    public function register_rest_routes(): void
    {
        register_rest_route(self::REST_NAMESPACE, '/callback', [
            'methods' => 'GET',
            'callback' => [$this, 'handle_oauth_callback'],
            'permission_callback' => '__return_true',
        ]);
    }

    private function ensure_session_started(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
    }

    public static function get_return_to_url(): string
    {
        $return_to = $_GET['return_to'] ?? null;
        if ($return_to) {
            return esc_url_raw(wp_unslash($return_to));
        }

        return isset($_SERVER['HTTP_REFERER'])
            ? esc_url_raw($_SERVER['HTTP_REFERER'])
            : home_url('/');
    }

    /**
     * Generates the SSO login URL with PKCE and stores PKCE verifier + return URL in PHP session keyed by state.
     *
     * @param string|null $return_to Optional return URL.
     * @return string
     * @throws RandomException
     */
    public function get_login_url(?string $return_to = null): string
    {
        $scope = '';

        $code_verifier = bin2hex(random_bytes(64));
        $code_challenge = strtr(rtrim(
            base64_encode(hash('sha256', $code_verifier, true)),
            '='
        ), '+/', '-_');

        $return_to = $return_to ?: home_url('/');
        $return_to = esc_url_raw($return_to);

        $encoded_return_to = base64_encode($return_to);

        $nonce = wp_create_nonce(self::NONCE_ACTION);
        $state = $nonce . '|' . $encoded_return_to;

        // Helper::log('SeamlessSSO | get_login_url | State nonce=' . $nonce . ', return_to=' . $return_to);

        $_SESSION[self::SSO_PREFIX]['pkce'][$nonce] = [
            'verifier'   => $code_verifier,
            'created_at' => time(),
        ];

        // Helper::log('SeamlessSSO | get_login_url | Stored PKCE in session with nonce=' . $nonce . ', session_id=' . session_id());

        // Build the URL
        $redirect_uri = rest_url(self::REST_NAMESPACE . '/callback');
        $query = http_build_query([
            'client_id'             => $this->sso_client_id,
            'redirect_uri'          => $redirect_uri,
            'response_type'         => 'code',
            'scope'                 => $scope,
            'state'                 => $state,
            'code_challenge'        => $code_challenge,
            'code_challenge_method' => 'S256',
        ]);

        $final_url = "{$this->client_domain}/oauth/authorize?{$query}";

        return $final_url;
    }

    public function handle_oauth_callback(WP_REST_Request $req): void
    {
        $this->ensure_session_started();
        // Helper::log('SeamlessSSO | handle_oauth_callback | Received OAuth callback request.');

        $handler = new SeamlessCallbackHandler();

        $result = $handler->handle($req);

        if (is_wp_error($result)) {
            Helper::log('SeamlessSSO | handle_oauth_callback | OAuth callback failed: ' . $result->get_error_message());
            wp_die($result);
        }
    }

    /**
     * Renders the SSO login button with the generated query URL.
     * @param array $atts Shortcode attributes.
     * @return string
     * @throws RandomException
     */
    public function render_login_button(array $atts): string
    {
        // Helper::log('SeamlessSSO | render_login_button | Rendering login button shortcode.');

        $atts = shortcode_atts([
            'text'          => 'Login',
            'class'         => 'button seamless-sso-login-btn',
            'logout_text'   => 'Logout',
            'logout_class'  => 'button seamless-sso-logout-btn',
        ], $atts);

        if (empty($this->sso_client_id) || empty($this->client_domain)) {
            return '<p>Seamless SSO is not configured.</p>';
        }

        if (is_user_logged_in()) {
            $logout_url = wp_logout_url(home_url());

            return sprintf(
                '<a class="%s" href="%s">%s</a>',
                esc_attr($atts['logout_class']),
                esc_url($logout_url),
                esc_html($atts['logout_text'])
            );
        }

        $url = home_url(self::LOGIN_ROUTE);

        return sprintf(
            '<a class="%s" href="%s">%s</a>',
            esc_attr($atts['class']),
            esc_url($url),
            esc_html($atts['text'])
        );
    }

    // --- Token Refresh ---

    public function seamless_refresh_token_if_needed(int $user_id): string|false
    {
        $expires_at = (int) get_user_meta($user_id, 'seamless_token_expires', true);
        $access_token = get_user_meta($user_id, 'seamless_access_token', true);

        if ($access_token && $expires_at > time() + 30) {
            return $access_token;
        }

        $refresh_token = get_user_meta($user_id, 'seamless_refresh_token', true);
        if (empty($refresh_token)) {
            return false;
        }

        $body = [
            'grant_type' => 'refresh_token',
            'client_id' => $this->sso_client_id,
            'refresh_token' => $refresh_token,
        ];

        $response = wp_remote_post("{$this->client_domain}/oauth/token", [
            'body' => $body,
            'headers' => ['Accept' => 'application/json'],
            'timeout' => 20,
        ]);

        // Error handling
        if (is_wp_error($response)) {
            Helper::log('SeamlessSSO | seamless_refresh_token_if_needed | Refresh request failed: ' . $response->get_error_message());
            return false;
        }

        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        if (empty($data['access_token'])) {
            return false;
        }

        // Save new tokens
        update_user_meta($user_id, 'seamless_access_token', sanitize_text_field($data['access_token']));
        if (!empty($data['refresh_token'])) {
            update_user_meta($user_id, 'seamless_refresh_token', sanitize_text_field($data['refresh_token']));
        }
        $expires_in = (int)($data['expires_in'] ?? 3600);
        update_user_meta($user_id, 'seamless_token_expires', time() + $expires_in);

        return $data['access_token'];
    }
}
