<?php

namespace Seamless\Operations;

use Seamless\Auth\Helper;
use Seamless\Auth\SeamlessAuth;
use WP_Error;

class Membership
{
    const SSO_PREFIX = 'seamless_sso';
    const CACHE_KEY = 'seamless_membership_plans_list';
    const CACHE_TIMEOUT = 3600; // 1 hour
    private string $domain;

    public function __construct()
    {
        $this->domain = rtrim(get_option('seamless_client_domain', ''), '/');
    }

    private function log(string $function, string $message): void
    {
        Helper::log(__CLASS__ . " | {$function} | " . $message);
    }

    public function user_has_required_plan(int $user_id, array $required_plans): bool
    {

        if (empty($required_plans)) {
            $this->log('user_has_required_plan', 'No plans required. Access granted.');
            return true;
        }

        $access_token = get_user_meta($user_id, 'seamless_access_token', true);

        if (empty($access_token)) {
            $this->log('user_has_required_plan', 'No access token found for user.');
            return false;
        }

        $user_api_url = "{$this->domain}/api/user";
        $response = wp_remote_get($user_api_url, [
            'headers' => [
                'Authorization' => 'Bearer ' . $access_token,
                'Accept' => 'application/json',
            ],
            'timeout' => 20,
        ]);

        if (is_wp_error($response)) {
            $this->log('user_has_required_plan', 'API request failed: ' . $response->get_error_message());
            $user_memberships = get_user_meta($user_id, 'seamless_active_memberships', true);
        } else {
            $body = json_decode(wp_remote_retrieve_body($response), true);
            if (!empty($body['data']['active_memberships'])) {
                $user_memberships = $body['data']['active_memberships'];
                update_user_meta($user_id, 'seamless_active_memberships', $user_memberships);
                update_user_meta($user_id, 'seamless_memberships_updated', current_time('mysql'));
            } else {
                $user_memberships = get_user_meta($user_id, 'seamless_active_memberships', true);
            }
        }

        if (empty($user_memberships) || !is_array($user_memberships)) {
            $this->log('user_has_required_plan', 'User has no memberships data.');
            return false;
        }

        // Extract plan IDs from user's active memberships
        $user_plan_ids = [];
        foreach ($user_memberships as $membership) {

            if (isset($membership['status']) && $membership['status'] === 'active') {
                // Check if membership is not expired
                if (isset($membership['expiry_date'])) {
                    $expiry_date = strtotime($membership['expiry_date']);
                    $current_time = time();

                    if ($expiry_date <= $current_time) {
                        $this->log('user_has_required_plan', 'Membership expired, skipping.');
                        continue;
                    }
                }

                if (isset($membership['plan']['id'])) {
                    $user_plan_ids[] = $membership['plan']['id'];
                    $this->log('user_has_required_plan', 'Added plan ID: ' . $membership['plan']['id']);
                } elseif (isset($membership['id'])) {
                    $user_plan_ids[] = $membership['id'];
                    $this->log('user_has_required_plan', 'Added direct plan ID: ' . $membership['id']);
                }
            }
        }

        if (empty($user_plan_ids)) {
            $this->log('user_has_required_plan', 'No active, non-expired plans found after processing.');
            return false;
        }

        $intersection = array_intersect($required_plans, $user_plan_ids);

        if (!empty($intersection)) {
            $this->log('user_has_required_plan', 'Access granted - matching plans found: ' . implode(',', $intersection));
            return true;
        }

        $this->log('user_has_required_plan', 'Access denied - no matching plans found');
        return false;
    }

    /**
     * Get user's active membership plans with expiration check
     */
    public function get_user_active_plans(int $user_id): array
    {
        $user_memberships = get_user_meta($user_id, 'seamless_active_memberships', true);

        if (empty($user_memberships) || !is_array($user_memberships)) {
            return [];
        }

        $active_plans = [];
        $current_time = time();

        foreach ($user_memberships as $membership) {
            if (isset($membership['status']) && $membership['status'] === 'active') {
                // Check if membership is not expired
                $is_active = true;
                if (isset($membership['expiry_date']) && $membership['expiry_date']) {
                    $expiry_time = strtotime($membership['expiry_date']);
                    if ($expiry_time < $current_time) {
                        $is_active = false;
                    }
                }

                if ($is_active && isset($membership['plan'])) {
                    $active_plans[] = $membership['plan'];
                }
            }
        }

        return $active_plans;
    }

    /**
     * Check if user membership data needs refreshing (older than 1 hour)
     */
    public function should_refresh_user_data(int $user_id): bool
    {
        $last_updated = get_user_meta($user_id, 'seamless_memberships_updated', true);

        if (empty($last_updated)) {
            return true;
        }

        $last_updated_time = strtotime($last_updated);
        $one_hour_ago = time() - 3600;

        return $last_updated_time < $one_hour_ago;
    }

    /**
     * Store user session data for quick access
     */
    public function store_user_session_data(int $user_id): void
    {
        $active_plans = $this->get_user_active_plans($user_id);
        $plan_ids = array_column($active_plans, 'id');

        // Store in session for quick access during the current session
        if (!session_id()) {
            session_start();
        }

        $_SESSION['seamless_user_plans'] = $plan_ids;
        $_SESSION['seamless_plans_updated'] = time();
    }
}
