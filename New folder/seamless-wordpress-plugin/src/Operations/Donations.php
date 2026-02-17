<?php

namespace Seamless\Operations;
use Seamless\Auth\SeamlessAuth;

class Donations
{

    private string $domain;

    public function __construct()
    {
        $this->domain = rtrim(get_option('seamless_client_domain', ''), '/'); // remove trailing slash
    }

    private function fetch_data(string $endpoint)
    {
        try {
            $url = $this->domain . '/api/' . $endpoint;

            if (!class_exists(\Seamless\Auth\SeamlessAuth::class)) {
                throw new \Exception('Authentication helper not available');
            }

            $token = SeamlessAuth::get_token();
            if (!$token) {
                throw new \Exception('Unable to authenticate with API (no valid token)');
            }

            $response = wp_remote_get($url, [
                'sslverify' => false,
                'timeout'   => 30,
                'headers'   => [
                    'Authorization' => 'Bearer ' . $token,
                    'Accept'        => 'application/json',
                ],
            ]);

            if (is_wp_error($response)) {
                throw new \Exception($response->get_error_message());
            }

            $body = json_decode(wp_remote_retrieve_body($response), true);
            $http_code = wp_remote_retrieve_response_code($response);

            if ($http_code !== 200 || !is_array($body)) {
                $message = $body['message'] ?? 'An unknown API error occurred. Code: ' . $http_code;
                throw new \Exception($message);
            }

            return ['success' => true, 'data' => $body['data'] ?? $body];
        } catch (\Exception $e) {
            error_log('Seamless API Errors (' . $endpoint . '): ' . $e->getMessage());
            return ['success' => false, 'message' => $e->getMessage(), 'data' => []];
        }
    }

    public function get_donation($donation_id)
    {
        $transient_key = 'seamless_donation_' . md5($donation_id);
        $cached_data = get_transient($transient_key);

        if (false !== $cached_data) {
            return $cached_data;
        }

        $result = $this->fetch_data('donations/' . $donation_id);

        set_transient($transient_key, $result, 0.5 * HOUR_IN_SECONDS);

        return $result;
    }

    public function get_donations(int $page = 1, string $search = '')
    {
        $transient_key = 'seamless_donations_' . md5($page . $search);
        $cached_data = get_transient($transient_key);

        if (false !== $cached_data) {
            return $cached_data;
        }

        $params = ['page' => $page, 'search' => $search];
        $query = http_build_query(array_filter($params));
        $result = $this->fetch_data('donations?' . $query);

        if ($result['success']) {
            // Normalize API response to a flat list with optional pagination, matching admin UI expectations
            if (isset($result['data']['donations'])) {
                $normalized = [
                    'success' => true,
                    'data' => $result['data']['donations'] ?? [],
                ];
                if (isset($result['data']['pagination'])) {
                    $normalized['pagination'] = $result['data']['pagination'];
                }
                $result = $normalized;
            } elseif (is_array($result['data'])) {
                // If API already returns a flat list
                $result = [
                    'success' => true,
                    'data' => $result['data'],
                ];
            }
        } else {
            // Ensure consistent shape on failure
            $result = [
                'success' => false,
                'message' => $result['message'] ?? 'Failed to fetch donations',
                'data' => [],
            ];
        }

        set_transient($transient_key, $result, 0.5 * HOUR_IN_SECONDS);

        return $result;
    }
}
