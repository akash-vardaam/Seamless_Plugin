<?php

namespace Seamless\Auth;

class SeamlessAuth
{
    private static $option_key = 'wp_seamless_event_api_token';
    private string $domain;
    private string $client_id;
    private string $client_secret;

    public function __construct()
    {
        $this->domain        = rtrim(get_option('seamless_client_domain', ''), '/');
        $this->client_id     = get_option('seamless_client_id', '');
        $this->client_secret = get_option('seamless_client_secret', '');

        add_action('wp_ajax_seamless_disconnect', [$this, 'handle_disconnect']);
        add_action('wp_ajax_seamless_test_connection', [$this, 'handle_test_connection']);
    }

    public static function get_token()
    {
        $token = get_option(self::$option_key);
        error_log('Seamless Auth: Retrieved token from options: ' . print_r($token, true));
        $instance = new self();

        if (!$token || empty($token['access_token'])) {
            $token = $instance->fetch_token();
        }

        if ($token && isset($token['expires_at']) && time() >= $token['expires_at']) {
            $token = $instance->fetch_token();
        }

        return $token ? $token['access_token'] : false;
    }

    public function fetch_token()
    {
        if (empty($this->domain) || empty($this->client_id) || empty($this->client_secret)) {
            error_log('Seamless Auth: Missing credentials - Domain: ' . $this->domain . ', Client ID: ' . $this->client_id);
            return false;
        }

        if (!filter_var($this->domain, FILTER_VALIDATE_URL)) {
            error_log('Seamless Auth: Invalid domain format: ' . $this->domain);
            return false;
        }

        $token_url = $this->domain . '/oauth/token';

        $response = wp_remote_post($token_url, [
            'body' => [
                'grant_type'    => 'client_credentials',
                'client_id'     => $this->client_id,
                'client_secret' => $this->client_secret,
                'scope'         => 'read',
            ],
            'timeout' => 30,
            'headers' => [
                'Accept' => 'application/json',
                'Content-Type' => 'application/x-www-form-urlencoded'
            ],
            'sslverify' => true
        ]);

        error_log('Seamless Auth Request URL: ' . $token_url);
        error_log('Seamless Auth Request Body: ' . print_r([
            'grant_type' => 'client_credentials',
            'client_id' => $this->client_id,
            'client_secret' => substr($this->client_secret, 0, 4) . '***', // Don't log full secret
            'scope' => 'read'
        ], true));

        if (is_wp_error($response)) {
            error_log('Seamless Auth WP Error: ' . $response->get_error_message());
            return false;
        }

        $status = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        $headers = wp_remote_retrieve_headers($response);

        error_log('Seamless Auth Response: Status=' . $status . ', Body=' . $body);

        if ($status !== 200) {
            error_log('Seamless Auth Error: Status ' . $status . ' - ' . $body);
            // Store the error for display
            update_option('seamless_last_auth_error', $body);
            return false;
        }

        $data = json_decode($body, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            error_log('Seamless Auth: Invalid JSON response - ' . json_last_error_msg());
            return false;
        }

        if (!empty($data['access_token'])) {
            $expires_in = isset($data['expires_in']) ? (int)$data['expires_in'] : 3600;
            $token_data = [
                'access_token' => $data['access_token'],
                'token_type' => $data['token_type'] ?? 'Bearer',
                'expires_in' => $expires_in,
                'expires_at' => time() + $expires_in - 60,
                'scope' => $data['scope'] ?? 'read',
                'created_at' => time()
            ];

            update_option(self::$option_key, $token_data);
            delete_option('seamless_last_auth_error');
            // Clear manual disconnect flag on successful authentication
            delete_option('seamless_manual_disconnect');

            error_log('Seamless Auth: Token successfully stored, expires at ' . date('Y-m-d H:i:s', $token_data['expires_at']));
            return $token_data;
        }

        error_log('Seamless Auth Failed: No access_token in response - ' . $body);
        update_option('seamless_last_auth_error', 'No access token received');
        return false;
    }

    public static function is_authenticated()
    {
        $manual_disconnect = get_option('seamless_manual_disconnect');
        if (!empty($manual_disconnect)) {
            return false;
        }

        $token = get_option(self::$option_key);

        if ($token && is_array($token) && !empty($token['access_token']) && isset($token['expires_at']) && time() < $token['expires_at']) {
            return true;
        }

        $instance = new self();
        if (empty($instance->domain) || empty($instance->client_id) || empty($instance->client_secret)) {
            return false;
        }
        $new_token = $instance->fetch_token();

        if ($new_token && !empty($new_token['access_token']) && isset($new_token['expires_at']) && time() < $new_token['expires_at']) {
            return true;
        }

        return false;
    }

    public function disconnect()
    {
        delete_option(self::$option_key);
        delete_option('seamless_last_auth_error');
        update_option('seamless_manual_disconnect', 1);
        return true;
    }

    public function get_auth_status()
    {
        $token = get_option(self::$option_key);
        $last_error = get_option('seamless_last_auth_error');

        return [
            'is_authenticated' => self::is_authenticated(),
            'token_exists' => !empty($token),
            'token_expired' => $token && isset($token['expires_at']) && time() >= $token['expires_at'],
            'expires_at' => $token['expires_at'] ?? null,
            'last_error' => $last_error,
            'credentials_set' => !empty($this->domain) && !empty($this->client_id) && !empty($this->client_secret)
        ];
    }

    public function handle_disconnect()
    {
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'seamless_disconnect')) {
            wp_send_json_error('Invalid nonce');
        }

        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
        }

        $this->disconnect();
        wp_send_json_success('Disconnected successfully');
    }

    // AJAX handler for testing connection
    public function handle_test_connection()
    {
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'seamless_test_connection')) {
            wp_send_json_error('Invalid nonce');
        }

        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
        }

        $token = $this->fetch_token();

        if ($token) {
            wp_send_json_success('Connection successful');
        } else {
            $error = get_option('seamless_last_auth_error', 'Unknown error');
            wp_send_json_error('Connection failed: ' . $error);
        }
    }
}
