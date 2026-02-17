<?php

namespace Seamless\Auth;

use Seamless\Auth\Helper;
use Exception;
use WP_Error;
use WP_REST_Request;
use WP_User;

/**
 * Handles the token exchange and user provisioning (auth, create, and update user) logic.
 */
class SeamlessCallbackHandler
{
    const SSO_PREFIX = 'seamless_sso';
    const NONCE_ACTION = 'seamless_sso_state';
    private string $client_domain;
    private string $sso_client_id;

    public function __construct()
    {
        $this->client_domain = rtrim(get_option('seamless_client_domain', ''), '/');
        $this->sso_client_id = get_option('seamless_sso_client_id', '');
    }

    /**
     * Main handler for the OAuth callback.
     *
     * @param WP_REST_Request $req
     * @return WP_Error|WP_User
     */
    public function handle(WP_REST_Request $req): WP_Error|WP_User
    {

        $state = $req->get_param('state');
        $code = $req->get_param('code');

        if (empty($state) || empty($code)) {
            return new WP_Error('missing_params', 'Missing state or authorization code.');
        }

        list($nonce, $encoded_return_to) = explode('|', $state) + [null, null];
        if (!$nonce || !wp_verify_nonce($nonce, self::NONCE_ACTION)) {
            Helper::log("Invalid nonce in state: {$nonce}");
            return new WP_Error('invalid_state', 'Invalid or expired state.');
        }

        if (empty($_SESSION[self::SSO_PREFIX]['pkce'][$nonce]['verifier'])) {
            Helper::log('Expired or missing PKCE verifier for state');
            return new WP_Error('missing_verifier', 'Expired or missing PKCE verifier.');
        }

        $pkce_verifier = $_SESSION[self::SSO_PREFIX]['pkce'][$nonce]['verifier'];
        unset($_SESSION[self::SSO_PREFIX]['pkce'][$nonce]);

        $return_to = $encoded_return_to ? base64_decode($encoded_return_to) : home_url('/');

        try {
            $tokens = $this->exchange_code_for_token($code, $pkce_verifier);
            if (is_wp_error($tokens)) {
                return $tokens;
            }

            $user_data = $this->fetch_user_profile($tokens['access_token']);
            if (is_wp_error($user_data)) {
                return $user_data;
            }

            $wp_user = $this->insert_update_wp_user($user_data, $tokens);
            if (is_wp_error($wp_user)) {
                return $wp_user;
            }

            wp_set_current_user($wp_user->ID);
            wp_set_auth_cookie($wp_user->ID, true);

            // Helper::log('SeamlessCallbackHandler | handle | User logged in successfully, redirecting to: ' . $return_to);
            wp_safe_redirect($return_to);
            exit;
        } catch (Exception $e) {
            Helper::log('SeamlessCallbackHandler | handle | Exception: ' . $e->getMessage());
            return new WP_Error('sso_exception', 'An unexpected error occurred during SSO: ' . $e->getMessage(), ['status' => 500]);
        }
    }

    /**
     * Exchanges the authorization code for access and refresh tokens.
     * EXACTLY MATCHING WORKING CODE PATTERN - NO CLIENT_SECRET
     * 
     * @param string $code
     * @param string $verifier
     * @return array|WP_Error
     */
    private function exchange_code_for_token(string $code, string $verifier): array|WP_Error
    {
        $redirect_uri = rest_url(SeamlessSSO::REST_NAMESPACE . '/callback');
        $token_url = "{$this->client_domain}/oauth/token";

        $body = [
            'grant_type'    => 'authorization_code',
            'client_id'     => $this->sso_client_id,
            'redirect_uri'  => $redirect_uri,
            'code'          => $code,
            'code_verifier' => $verifier,
        ];

        $response = wp_remote_post($token_url, [
            'body'      => $body,
            'headers'   => [
                'Accept' => 'application/json'
                // 'Content-Type' => 'application/x-www-form-urlencoded'
            ],
            'timeout'   => 20,
        ]);

        if (is_wp_error($response)) {
            Helper::log('SeamlessCallbackHandler | exchange_code_for_token | Token request WP_Error: ' . $response->get_error_message());
            return $response;
        }

        $response_code = wp_remote_retrieve_response_code($response);
        $response_body = wp_remote_retrieve_body($response);

        $data = json_decode($response_body, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            return new WP_Error('json_decode_failed', 'Failed to decode token response', ['status' => $response_code]);
        }

        if (empty($data['access_token'])) {
            $err = $data['error_description'] ?? ($data['error'] ?? 'No access token in response');
            return new WP_Error('token_exchange_failed', 'Token exchange failed: ' . $err, ['status' => $response_code]);
        }
        return $data;
    }

    /**
     * Fetches the user profile from the SSO API using the access token.
     * @param string $access_token
     * @return array|WP_Error
     */
    private function fetch_user_profile(string $access_token): array|WP_Error
    {
        $user_api_url = "{$this->client_domain}/api/user";

        $response = wp_remote_get($user_api_url, [
            'headers' => [
                'Authorization' => 'Bearer ' . $access_token,
                'Accept'        => 'application/json',
            ],
            'timeout' => 20,
        ]);

        if (is_wp_error($response)) {
            return $response;
        }

        $body = wp_remote_retrieve_body($response);

        $data = json_decode($body, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            return new WP_Error('json_decode_failed', 'Failed to decode user profile JSON.', [
                'status' => wp_remote_retrieve_response_code($response),
            ]);
        }

        $user = $data['data']['user'] ?? null;
        if (empty($user) || empty($user['email'])) {
            return new WP_Error('user_fetch_failed', 'Profile did not include an email address.', [
                'status' => wp_remote_retrieve_response_code($response),
            ]);
        }

        return $data;
    }

    /**
     * Inserts or updates the WordPress user and stores tokens.
     * @param array $user_data
     * @param array $tokens
     * @return WP_User|WP_Error
     */
    private function insert_update_wp_user(array $user_data, array $tokens): WP_User|WP_Error
    {
        $user = $user_data['data']['user'] ?? [];
        $email = sanitize_email($user['email'] ?? '');
        $role = $user['role'];


        $wp_user = get_user_by('email', $email);

        if (!$wp_user) {
            $username = sanitize_user($email, true);

            $user_id = wp_create_user($username, wp_generate_password(), $email);
            if (is_wp_error($user_id)) {
                Helper::log("Failed to create user. Error=" . $user_id->get_error_message());
                return $user_id;
            }
            $wp_user = get_user_by('ID', $user_id);

            $display_name = trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? ''));
            wp_update_user(['ID' => $wp_user->ID, 'display_name' => $display_name ?: $email]);
        }

        $target_role = ($role === 'admin') ? 'administrator' : 'subscriber';
        if (!in_array($target_role, (array) $wp_user->roles)) {
            $wp_user->set_role($target_role);
        }

        $fields_to_sync = [
            'first_name',
            'last_name',
            'phone',
            'address_line_1',
            'address_line_2',
            'city',
            'country',
            'user_type'
        ];

        foreach ($fields_to_sync as $field) {
            if (!empty($user[$field])) {
                update_user_meta($wp_user->ID, 'seamless_' . $field, sanitize_text_field($user[$field]));
            }
        }

        if (!empty($user['first_name'])) {
            update_user_meta($wp_user->ID, 'first_name', sanitize_text_field($user['first_name']));
        }
        if (!empty($user['last_name'])) {
            update_user_meta($wp_user->ID, 'last_name', sanitize_text_field($user['last_name']));
        }

        if (!empty($user_data['data']['active_memberships'])) {
            $active_memberships = $user_data['data']['active_memberships'];

            $clean_memberships = [];
            foreach ($active_memberships as $membership) {
                if (isset($membership['status']) && isset($membership['plan']['id'])) {
                    $clean_memberships[] = [
                        'id' => $membership['id'] ?? '',
                        'status' => $membership['status'],
                        'plan' => [
                            'id' => $membership['plan']['id'],
                            'label' => $membership['plan']['label'] ?? 'Unknown Plan',
                            'price' => $membership['plan']['price'] ?? '0.00'
                        ],
                        'start_date' => $membership['start_date'] ?? date('Y-m-d'),
                        'expiry_date' => $membership['expiry_date'] ?? null,
                        'auto_renews' => $membership['auto_renews'] ?? false
                    ];
                }
            }

            update_user_meta($wp_user->ID, 'seamless_active_memberships', $clean_memberships);
            update_user_meta($wp_user->ID, 'seamless_memberships_updated', current_time('mysql'));
        }

        if (!empty($tokens['access_token'])) {
            update_user_meta($wp_user->ID, 'seamless_access_token', sanitize_text_field($tokens['access_token']));
        }
        if (!empty($tokens['refresh_token'])) {
            update_user_meta($wp_user->ID, 'seamless_refresh_token', sanitize_text_field($tokens['refresh_token']));
        }
        $expires_in = (int)($tokens['expires_in'] ?? 3600);
        update_user_meta($wp_user->ID, 'seamless_token_expires', time() + $expires_in);

        return $wp_user;
    }
}
