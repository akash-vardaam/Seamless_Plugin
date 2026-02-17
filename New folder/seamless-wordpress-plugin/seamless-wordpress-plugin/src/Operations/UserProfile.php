<?php

namespace Seamless\Operations;

use Seamless\Auth\SeamlessAuth;

/**
 * UserProfile Operations Class
 * 
 * Handles all user-related API operations including profile fetching,
 * membership data, order history, and profile updates.
 */
class UserProfile
{
    private string $domain;

    public function __construct()
    {
        $this->domain = rtrim(get_option('seamless_client_domain', ''), '/');
    }

    /**
     * Get access token for current user
     *
     * @return string|null Access token or null
     */
    private function get_access_token()
    {
        if (!is_user_logged_in()) {
            return null;
        }

        $uid = get_current_user_id();
        $access_token = get_user_meta($uid, 'seamless_access_token', true);

        // Try to refresh token if empty
        if (empty($access_token) && class_exists('Seamless\Auth\SeamlessSSO')) {
            $sso = new \Seamless\Auth\SeamlessSSO();
            if (method_exists($sso, 'seamless_refresh_token_if_needed')) {
                $access_token = $sso->seamless_refresh_token_if_needed($uid) ?: '';
            }
        }

        return $access_token ?: null;
    }

    /**
     * Fetch data from API using GET request
     *
     * @param string $endpoint API endpoint
     * @param array $params Query parameters
     * @return array Response with success status and data
     */
    private function fetch_data(string $endpoint, array $params = [])
    {
        try {
            $access_token = $this->get_access_token();
            if (!$access_token) {
                throw new \Exception('User not authenticated or token unavailable');
            }

            $url = $this->domain . '/api/' . $endpoint;
            if (!empty($params)) {
                $url .= '?' . http_build_query($params);
            }

            $response = wp_remote_get($url, [
                'sslverify' => false,
                'timeout'   => 10, // Reduced from 20s to 10s for faster response
                'headers'   => [
                    'Authorization' => 'Bearer ' . $access_token,
                    'Accept'        => 'application/json',
                ],
            ]);

            if (is_wp_error($response)) {
                throw new \Exception($response->get_error_message());
            }

            $body = json_decode(wp_remote_retrieve_body($response), true);

            // error_log('Seamless UserProfile API Response (' . $endpoint . '): ' . print_r($body, true));
            $http_code = wp_remote_retrieve_response_code($response);

            if ($http_code !== 200 || !is_array($body)) {
                $message = $body['message'] ?? 'API error occurred. Code: ' . $http_code;
                throw new \Exception($message);
            }

            return ['success' => true, 'data' => $body['data'] ?? $body];
        } catch (\Exception $e) {
            error_log('Seamless UserProfile API Error (' . $endpoint . '): ' . $e->getMessage());
            return ['success' => false, 'message' => $e->getMessage(), 'data' => []];
        }
    }

    /**
     * Post data to API using POST request
     *
     * @param string $endpoint API endpoint
     * @param array $data Post data
     * @return array Response with success status and data
     */
    private function post_data(string $endpoint, array $data = [])
    {
        try {
            $access_token = $this->get_access_token();
            if (!$access_token) {
                throw new \Exception('User not authenticated or token unavailable');
            }

            $url = $this->domain . '/api/' . $endpoint;

            $response = wp_remote_request($url, [
                'sslverify' => false,
                'timeout'   => 10, // Reduced from 20s to 10s for faster response
                'method'    => 'PUT',
                'headers'   => [
                    'Authorization' => 'Bearer ' . $access_token,
                    'Accept'        => 'application/json',
                    'Content-Type'  => 'application/json',
                ],
                'body' => json_encode($data),
            ]);

            if (is_wp_error($response)) {
                throw new \Exception($response->get_error_message());
            }

            $body = json_decode(wp_remote_retrieve_body($response), true);
            $http_code = wp_remote_retrieve_response_code($response);

            if (!in_array($http_code, [200, 201]) || !is_array($body)) {
                $message = $body['message'] ?? 'API error occurred. Code: ' . $http_code;
                throw new \Exception($message);
            }

            return ['success' => true, 'data' => $body['data'] ?? $body, 'message' => $body['message'] ?? 'Success'];
        } catch (\Exception $e) {
            error_log('Seamless UserProfile API Error (' . $endpoint . '): ' . $e->getMessage());
            return ['success' => false, 'message' => $e->getMessage(), 'data' => []];
        }
    }

    /**
     * Get user profile data
     *
     * @return array User profile data
     */
    public function get_user_profile()
    {
        $result = $this->fetch_data('user');

        if ($result['success'] && isset($result['data']['user'])) {
            return ['success' => true, 'data' => $result['data']['user']];
        }

        return $result;
    }

    /**
     * Get user memberships (current and history)
     *
     * @param string $email User email
     * @return array Membership data with current and history
     */
    public function get_user_memberships(string $email)
    {
        if (empty($email)) {
            return ['success' => false, 'message' => 'Email is required', 'data' => []];
        }
        $result = $this->fetch_data('dashboard/memberships', ['email' => $email]);

        // error_log('Seamless UserProfile API Response (dashboard/memberships): ' . print_r($result, true));

        // $result = $this->fetch_data('dashboard/memberships/upgrade/019b2698-a09f-72fa-b5f5-80284e6bc5c8', ['email' => $email]);

        // error_log('Seamless UserProfile API Response (dashboard/memberships/upgrade): ' . print_r($result, true));

        // $result = $this->fetch_data('dashboard/courses/enrolled', ['email' => $email]);

        // error_log('Seamless UserProfile API Response (dashboard/courses/enrolled): ' . print_r($result, true));

        // $result = $this->fetch_data('dashboard/courses/enrolled', ['email' => $email]);

        // error_log('Seamless UserProfile API Response (dashboard/courses/enrolled): ' . print_r($result, true));

        // $result = $this->fetch_data('dashboard/membership-plans/upgrades', ['email' => $email]);

        // error_log('Seamless UserProfile API Response (dashboard/membership-plans/upgrades): ' . print_r($result, true));

        if ($result['success']) {
            $memData = $result['data'];
            $foundMemberships = [];

            // Handle collection response - find matching email
            if (is_array($memData) && isset($memData[0]['user'])) {
                foreach ($memData as $row) {
                    if (!empty($row['user']['email']) && $row['user']['email'] === $email) {
                        $foundMemberships = $row['memberships'] ?? [];
                        break;
                    }
                }
                // Use found memberships or empty array
                $memData = $foundMemberships;
            }

            // Parse different response structures
            $current = [];
            $history = [];

            if (isset($memData['current'])) {
                $current = $memData['current'];
                $history = $memData['history'] ?? [];
            } elseif (isset($memData['active_memberships'])) {
                $current = $memData['active_memberships'] ?? [];
                $history = $memData['membership_history'] ?? [];
            } elseif (is_array($memData)) {
                foreach ($memData as $m) {
                    if (!is_array($m)) {
                        continue;
                    }
                    if (!empty($m['status']) && ($m['status'] === 'active' || $m['status'] === 'cancelled')) {
                        $current[] = $m;
                    } else {
                        $history[] = $m;
                    }
                }
            }

            // Filter expired memberships from current to history
            // Keep cancelled non-refundable memberships as active until they expire
            $active_filtered = [];
            $now = time();
            foreach ((array) $current as $m) {
                $status = $m['status'] ?? '';
                $expiry = $m['expiry_date'] ?? ($m['expires_at'] ?? null);
                $is_expired = false;

                if (!empty($expiry)) {
                    $ts = strtotime((string)$expiry);
                    if ($ts !== false && $ts < $now) {
                        $is_expired = true;
                    }
                }

                // Check if plan is non-refundable
                $plan = $m['plan'] ?? [];
                $is_refundable = !empty($plan['refundable']) && ($plan['refundable'] == 1 || $plan['refundable'] === true);
                $has_proration = !empty($plan['prorate_on_refund']) && ($plan['prorate_on_refund'] == 1 || $plan['prorate_on_refund'] === true);
                $is_non_refundable = !$is_refundable && !$has_proration;

                $is_cancelled = strtolower((string)$status) === 'cancelled';
                $is_active = strtolower((string)$status) === 'active';

                if (!$is_expired && ($is_active || ($is_cancelled && $is_non_refundable))) {
                    $active_filtered[] = $m;
                } else {
                    $history[] = $m;
                }
            }

            return [
                'success' => true,
                'data' => [
                    'current' => $active_filtered,
                    'history' => $history,
                ]
            ];
        }

        return $result;
    }

    /**
     * Get user order history
     *
     * @param string $email User email
     * @return array Order history data
     */
    public function get_user_orders(string $email)
    {
        if (empty($email)) {
            return ['success' => false, 'message' => 'Email is required', 'data' => []];
        }

        $result = $this->fetch_data('users/order-history', ['email' => $email]);

        // error_log('Seamless Order History Response - Body: ' . print_r($result, true));

        if ($result['success']) {
            $ordersData = $result['data'];
            $foundOrders = [];

            // Handle collection response - find matching email
            if (isset($ordersData[0]['user'])) {
                foreach ($ordersData as $row) {
                    if (!empty($row['user']['email']) && $row['user']['email'] === $email) {
                        $foundOrders = $row['orders'] ?? [];
                        break;
                    }
                }
                // Use found orders or empty array
                $ordersData = $foundOrders;
                // error_log('Seamless Order History Response - Body: ' . print_r($ordersData, true));
            }

            return ['success' => true, 'data' => $ordersData];
        }

        return $result;
    }

    /**
     * Get all user dashboard data (profile, memberships, orders)
     * Uses transient caching to improve performance
     *
     * @param bool $force_refresh Force refresh cache
     * @return array Complete dashboard data
     */
    public function get_dashboard_data($force_refresh = false)
    {
        $user = wp_get_current_user();
        $email = $user && !empty($user->user_email) ? $user->user_email : '';
        $user_id = get_current_user_id();

        // Check cache first (1 minute cache)
        $cache_key = 'seamless_dashboard_' . $user_id;
        if (!$force_refresh) {
            $cached_data = get_transient($cache_key);
            if ($cached_data !== false) {
                return $cached_data;
            }
        }

        // Default data
        $profile = [
            'name'  => $user->display_name,
            'email' => $user->user_email,
        ];
        $current_memberships = [];
        $membership_history = [];
        $orders = [];

        // Fetch profile
        $profileResult = $this->get_user_profile();
        if ($profileResult['success']) {
            $profile = $profileResult['data'];
        }

        // Fetch memberships
        if (!empty($email)) {
            $membershipResult = $this->get_user_memberships($email);
            if ($membershipResult['success']) {
                $current_memberships = $membershipResult['data']['current'] ?? [];
                $membership_history = $membershipResult['data']['history'] ?? [];
            }

            // Fetch orders
            $ordersResult = $this->get_user_orders($email);
            if ($ordersResult['success']) {
                $orders = $ordersResult['data'];
            }
        }

        $result = [
            'success' => true,
            'data' => [
                'profile' => $profile,
                'current_memberships' => $current_memberships,
                'membership_history' => $membership_history,
                'orders' => $orders,
                'client_domain' => $this->domain,
            ]
        ];

        // Cache for 1 minute (60 seconds)
        set_transient($cache_key, $result, 30);

        return $result;
    }

    /**
     * Update user profile
     *
     * @param string $email User email
     * @param array $data Profile data to update
     * @return array Update result
     */
    public function update_user_profile(string $email, array $data)
    {
        if (empty($email)) {
            return ['success' => false, 'message' => 'Email is required'];
        }

        // Validate current user can update this profile
        $current_user = wp_get_current_user();
        if ($current_user->user_email !== $email && !current_user_can('administrator')) {
            return ['success' => false, 'message' => 'Unauthorized to update this profile'];
        }

        // Sanitize data
        $sanitized_data = [];
        $allowed_fields = ['first_name', 'last_name', 'email', 'phone', 'phone_type', 'address_line_1', 'address_line_2', 'city', 'state', 'country', 'zip_code'];

        foreach ($allowed_fields as $field) {
            if (isset($data[$field])) {
                $sanitized_data[$field] = sanitize_text_field($data[$field]);
            }
        }

        if (empty($sanitized_data)) {
            return ['success' => false, 'message' => 'No valid fields to update'];
        }

        // Make API request
        $result = $this->post_data('dashboard/profile/edit', $sanitized_data);

        // Clear all dashboard-related caches on successful update
        if ($result['success']) {
            $user_id = get_current_user_id();
            delete_transient('seamless_dashboard_' . $user_id);
            delete_transient('seamless_enrolled_courses_' . $user_id);
            delete_transient('seamless_included_courses_' . $user_id);
        }

        return $result;
    }

    /**
     * Get user enrolled courses
     * Uses transient caching to improve performance
     *
     * @return array Enrolled courses data
     */
    public function get_enrolled_courses()
    {
        $user_id = get_current_user_id();
        $cache_key = 'seamless_enrolled_courses_' . $user_id;

        // Check cache first
        $cached_data = get_transient($cache_key);
        if ($cached_data !== false) {
            return $cached_data;
        }

        $result = $this->fetch_data('dashboard/courses/enrolled');

        if ($result['success']) {
            $data = ['success' => true, 'data' => $result['data'] ?? []];
            // Cache for 1 minute
            set_transient($cache_key, $data, 30);
            return $data;
        }

        return $result;
    }

    /**
     * Get courses included in user's membership
     * Uses transient caching to improve performance
     *
     * @return array Included courses data
     */
    public function get_included_courses()
    {
        $user_id = get_current_user_id();
        $cache_key = 'seamless_included_courses_' . $user_id;

        // Check cache first
        $cached_data = get_transient($cache_key);
        if ($cached_data !== false) {
            return $cached_data;
        }

        $result = $this->fetch_data('dashboard/courses/included');

        if ($result['success']) {
            $data = ['success' => true, 'data' => $result['data'] ?? []];
            // Cache for 1 minute
            set_transient($cache_key, $data, 30);
            return $data;
        }

        return $result;
    }

    /**
     * Get course progress for a specific course
     *
     * @param string $course_id Course ID
     * @param string $email User email
     * @return array Course progress data
     */
    public function get_course_progress(string $course_id, string $email)
    {
        if (empty($course_id) || empty($email)) {
            return ['success' => false, 'message' => 'Course ID and email are required', 'data' => []];
        }

        $result = $this->fetch_data("dashboard/courses/{$course_id}/progress", ['email' => $email]);

        if ($result['success']) {
            return ['success' => true, 'data' => $result['data'] ?? []];
        }

        return $result;
    }

    /**
     * Upgrade user membership
     *
     * @param string $new_plan_id New plan ID to upgrade to
     * @param string $membership_id Current membership ID
     * @param string $email User email
     * @return array Upgrade result with Stripe checkout URL
     */
    public function upgrade_membership(string $new_plan_id, string $membership_id, string $email)
    {
        // error_log('UserProfile::upgrade_membership - new_plan_id: ' . $new_plan_id);
        // error_log('UserProfile::upgrade_membership - membership_id: ' . $membership_id);

        if (empty($new_plan_id) || empty($email)) {
            return ['success' => false, 'message' => 'Plan ID and email are required'];
        }

        // Validate current user can upgrade this membership
        $current_user = wp_get_current_user();
        if ($current_user->user_email !== $email && !current_user_can('administrator')) {
            return ['success' => false, 'message' => 'Unauthorized to upgrade this membership'];
        }

        // Prepare data for API
        $data = [
            'email' => $email,
        ];

        if (!empty($membership_id)) {
            $data['membership_id'] = $membership_id;
        }

        error_log('UserProfile::upgrade_membership - data: ' . print_r($data, true));

        // Make API request using POST
        try {
            $access_token = $this->get_access_token();
            if (!$access_token) {
                throw new \Exception('User not authenticated or token unavailable');
            }

            $url = $this->domain . '/api/dashboard/memberships/upgrade/' . $new_plan_id;

            // error_log('Seamless UserProfile API Request (dashboard/memberships/upgrade): ' . print_r($data, true));

            $response = wp_remote_post($url, [
                'sslverify' => false,
                'timeout'   => 15,
                'method'    => 'POST',
                'headers'   => [
                    'Authorization' => 'Bearer ' . $access_token,
                    'Accept'        => 'application/json',
                    'Content-Type'  => 'application/json',
                ],
                'body' => json_encode($data),
            ]);

            if (is_wp_error($response)) {
                throw new \Exception($response->get_error_message());
            }

            $body = json_decode(wp_remote_retrieve_body($response), true);
            $http_code = wp_remote_retrieve_response_code($response);

            // Log the full response for debugging
            error_log('Seamless Upgrade Membership Response - HTTP Code: ' . $http_code);
            error_log('Seamless Upgrade Membership Response - Body: ' . print_r($body, true));

            if (!in_array($http_code, [200, 201]) || !is_array($body)) {
                $message = $body['message'] ?? 'API error occurred. Code: ' . $http_code;
                error_log('Seamless Upgrade Membership Error - ' . $message);
                throw new \Exception($message);
            }

            // Clear dashboard cache on successful upgrade initiation
            $user_id = get_current_user_id();
            delete_transient('seamless_dashboard_' . $user_id);

            return [
                'success' => true,
                'data' => $body['data'] ?? $body,
                'message' => $body['message'] ?? 'Upgrade initiated successfully'
            ];
        } catch (\Exception $e) {
            error_log('Seamless UserProfile API Error (upgrade membership): ' . $e->getMessage());
            return ['success' => false, 'message' => 'Upgrade failed: ' . $e->getMessage(), 'data' => []];
        }
    }

    /**
     * Downgrade user membership
     *
     * @param string $new_plan_id New plan ID to downgrade to
     * @param string $membership_id Current plan ID
     * @param string $email User email
     * @return array Downgrade result
     */
    public function downgrade_membership(string $new_plan_id, string $membership_id, string $email)
    {
        if (empty($new_plan_id) || empty($email)) {
            return ['success' => false, 'message' => 'Plan ID and email are required'];
        }

        // Validate current user can downgrade this membership
        $current_user = wp_get_current_user();
        if ($current_user->user_email !== $email && !current_user_can('administrator')) {
            return ['success' => false, 'message' => 'Unauthorized to downgrade this membership'];
        }

        // Prepare data for API
        $data = [
            'email' => $email,
        ];

        if (!empty($membership_id)) {
            $data['membership_id'] = $membership_id;
        }

        error_log('UserProfile::downgrade_membership - data: ' . print_r($data, true));

        // Make API request using POST
        try {
            $access_token = $this->get_access_token();
            if (!$access_token) {
                throw new \Exception('User not authenticated or token unavailable');
            }

            $url = $this->domain . '/api/dashboard/memberships/downgrade/' . $new_plan_id;

            $response = wp_remote_post($url, [
                'sslverify' => false,
                'timeout'   => 15,
                'method'    => 'POST',
                'headers'   => [
                    'Authorization' => 'Bearer ' . $access_token,
                    'Accept'        => 'application/json',
                    'Content-Type'  => 'application/json',
                ],
                'body' => json_encode($data),
            ]);

            if (is_wp_error($response)) {
                throw new \Exception($response->get_error_message());
            }

            $body = json_decode(wp_remote_retrieve_body($response), true);
            $http_code = wp_remote_retrieve_response_code($response);

            // Log the full response for debugging
            error_log('Seamless Downgrade Membership Response - HTTP Code: ' . $http_code);
            error_log('Seamless Downgrade Membership Response - Body: ' . print_r($body, true));

            if (!in_array($http_code, [200, 201]) || !is_array($body)) {
                $message = $body['message'] ?? 'API error occurred. Code: ' . $http_code;
                error_log('Seamless Downgrade Membership Error - ' . $message);
                throw new \Exception($message);
            }

            // Clear dashboard cache on successful downgrade initiation
            $user_id = get_current_user_id();
            delete_transient('seamless_dashboard_' . $user_id);

            return [
                'success' => true,
                'data' => $body['data'] ?? $body,
                'message' => $body['message'] ?? 'Downgrade initiated successfully'
            ];
        } catch (\Exception $e) {
            error_log('Seamless UserProfile API Error (downgrade membership): ' . $e->getMessage());
            return ['success' => false, 'message' => 'Downgrade failed: ' . $e->getMessage(), 'data' => []];
        }
    }

    /**
     * Cancel membership
     *
     * @param string $membership_id Membership ID to cancel
     * @param string $email User email
     * @return array Cancellation result
     */
    public function cancel_membership(string $membership_id, string $email)
    {
        if (empty($membership_id) || empty($email)) {
            return ['success' => false, 'message' => 'Membership ID and email are required'];
        }

        // Validate current user can cancel this membership
        $current_user = wp_get_current_user();
        if ($current_user->user_email !== $email && !current_user_can('administrator')) {
            return ['success' => false, 'message' => 'Unauthorized to cancel this membership'];
        }

        // Prepare data for API
        $data = [
            'email' => $email,
        ];

        // Make API request using POST
        try {
            $access_token = $this->get_access_token();
            if (!$access_token) {
                throw new \Exception('User not authenticated or token unavailable');
            }

            $url = $this->domain . '/api/dashboard/memberships/cancel/' . $membership_id;

            error_log('Seamless Cancel Membership Request - URL: ' . $url);
            error_log('Seamless Cancel Membership Request - Data: ' . print_r($data, true));

            $response = wp_remote_post($url, [
                'sslverify' => false,
                'timeout'   => 15,
                'method'    => 'POST',
                'headers'   => [
                    'Authorization' => 'Bearer ' . $access_token,
                    'Accept'        => 'application/json',
                    'Content-Type'  => 'application/json',
                ],
                'body' => json_encode($data),
            ]);

            if (is_wp_error($response)) {
                error_log('Seamless Cancel Membership Error - WP Error: ' . $response->get_error_message());
                throw new \Exception($response->get_error_message());
            }

            $body = json_decode(wp_remote_retrieve_body($response), true);
            $http_code = wp_remote_retrieve_response_code($response);

            error_log('Seamless Cancel Membership Response - HTTP Code: ' . $http_code);
            error_log('Seamless Cancel Membership Response - Body: ' . print_r($body, true));

            if (!in_array($http_code, [200, 201]) || !is_array($body)) {
                $message = $body['message'] ?? 'API error occurred. Code: ' . $http_code;
                error_log('Seamless Cancel Membership Error - ' . $message);
                throw new \Exception($message);
            }

            // Clear dashboard cache on successful cancellation
            $user_id = get_current_user_id();
            delete_transient('seamless_dashboard_' . $user_id);

            error_log('Seamless Cancel Membership Success - Membership cancelled: ' . $membership_id);

            return [
                'success' => true,
                'data' => $body['data'] ?? $body,
                'message' => $body['message'] ?? 'Membership cancelled successfully'
            ];
        } catch (\Exception $e) {
            error_log('Seamless UserProfile API Error (cancel membership): ' . $e->getMessage());
            return ['success' => false, 'message' => $e->getMessage(), 'data' => []];
        }
    }

    /**
     * Cancel scheduled membership change
     *
     * @param string $membership_id Membership ID
     * @param string $email User email
     * @return array Cancellation result
     */
    public function cancel_scheduled_change(string $membership_id, string $email)
    {
        if (empty($membership_id) || empty($email)) {
            return ['success' => false, 'message' => 'Membership ID and email are required'];
        }

        // Validate current user can cancel this scheduled change
        $current_user = wp_get_current_user();
        if ($current_user->user_email !== $email && !current_user_can('administrator')) {
            return ['success' => false, 'message' => 'Unauthorized to cancel this scheduled change'];
        }

        try {
            $access_token = $this->get_access_token();
            if (!$access_token) {
                throw new \Exception('User not authenticated or token unavailable');
            }
            $api_url = $this->domain . '/api/dashboard/memberships/cancel-scheduled-change';

            $data = [
                'email' => $email,
                'membership_id' => $membership_id,
            ];

            error_log('Seamless Cancel Scheduled Change Request - URL: ' . $api_url);
            error_log('Seamless Cancel Scheduled Change Request - Data: ' . print_r($data, true));

            // Make API request
            $response = wp_remote_post($api_url, [
                'headers'   => [
                    'Authorization' => 'Bearer ' . $access_token,
                    'Accept'        => 'application/json',
                    'Content-Type'  => 'application/json',
                ],
                'body' => json_encode($data),
                'timeout' => 30,
            ]);

            if (is_wp_error($response)) {
                throw new \Exception($response->get_error_message());
            }

            $body = json_decode(wp_remote_retrieve_body($response), true);
            $http_code = wp_remote_retrieve_response_code($response);

            error_log('Seamless Cancel Scheduled Change Response - HTTP Code: ' . $http_code);
            error_log('Seamless Cancel Scheduled Change Response - Body: ' . print_r($body, true));

            if (!in_array($http_code, [200, 201]) || !is_array($body)) {
                $message = $body['message'] ?? 'API error occurred. Code: ' . $http_code;
                error_log('Seamless Cancel Scheduled Change Error - ' . $message);
                throw new \Exception($message);
            }

            // Clear dashboard cache on successful cancellation
            $user_id = get_current_user_id();
            delete_transient('seamless_dashboard_' . $user_id);

            error_log('Seamless Cancel Scheduled Change Success - Membership: ' . $membership_id);

            return [
                'success' => true,
                'data' => $body['data'] ?? $body,
                'message' => $body['message'] ?? 'Scheduled change cancelled successfully'
            ];
        } catch (\Exception $e) {
            error_log('Seamless UserProfile API Error (cancel scheduled change): ' . $e->getMessage());
            return ['success' => false, 'message' => $e->getMessage(), 'data' => []];
        }
    }
}
