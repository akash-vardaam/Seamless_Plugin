<?php

namespace Seamless\Auth;

class SeamlessAuth
{
    private static $option_key = 'wp_seamless_event_api_token';
    private string $domain;


    public function __construct()
    {
        $this->domain        = rtrim(get_option('seamless_client_domain', ''), '/');

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
        if (empty($this->domain)) {
            error_log('Seamless Auth: Missing domain');
            return false;
        }

        if (!filter_var($this->domain, FILTER_VALIDATE_URL)) {
            error_log('Seamless Auth: Invalid domain format: ' . $this->domain);
            return false;
        }

        // Verify the domain by hitting a public endpoint (e.g. events)
        $verify_url = $this->domain . '/api/events?per_page=1';

        $response = wp_remote_get($verify_url, [
            'timeout' => 10,
            'headers' => [
                'Accept' => 'application/json'
            ],
            'sslverify' => false // For testing, maybe make this optional or default true in prod
        ]);

        if (is_wp_error($response)) {
            error_log('Seamless Auth: Verification failed for ' . $verify_url . ' - ' . $response->get_error_message());
            update_option('seamless_last_auth_error', 'Connection failed: ' . $response->get_error_message());
            return false;
        }

        $status = wp_remote_retrieve_response_code($response);
        if ($status < 200 || $status >= 300) {
            error_log('Seamless Auth: Verification endpoint returned ' . $status);
            update_option('seamless_last_auth_error', 'API endpoint returned status ' . $status . '. Please check the domain.');
            return false;
        }

        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        // Basic validation that we got JSON
        if (json_last_error() !== JSON_ERROR_NONE) {
            error_log('Seamless Auth: Invalid JSON from verification endpoint');
            update_option('seamless_last_auth_error', 'Invalid response from API. Is this a Seamless endpoint?');
            return false;
        }

        // Check if structure looks somewhat correct (optional, but good for safety)
        // Usually expect 'data' or 'events' top level or paginated response
        // If it's just an [] or {} it might be fine, but we at least confirmed it's JSON and 200 OK.

        // Create a dummy token to mark as authenticated
        $token_data = [
            'access_token' => 'verified_domain_connection',
            'token_type' => 'Bearer',
            'expires_in' => 31536000,
            'expires_at' => time() + 31536000,
            'scope' => 'read',
            'created_at' => time()
        ];

        update_option(self::$option_key, $token_data);
        delete_option('seamless_last_auth_error');
        delete_option('seamless_manual_disconnect');


        return $token_data;
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
        if (empty($instance->domain)) {
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
            'credentials_set' => !empty($this->domain)
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
