<?php

namespace Seamless\Auth;

use Seamless\Operations\Membership;

/**
 * Class AccessController
 * Enforces content restriction based on post meta and user membership.
 */
class AccessController
{
    const SSO_PREFIX = 'seamless_sso';

    public function __construct()
    {
        add_action('template_redirect', [$this, 'enforce_full_access']);
        add_filter('template_include', [$this, 'load_restriction_template'], 99);
    }

    /**
     * Simple logging function for debugging.
     * @param string $function
     * @param string $message
     */
    private function log(string $function, string $message): void
    {
        error_log(self::SSO_PREFIX . " | AccessController | {$function} | " . $message);
    }

    /**
     * The primary function hooked into template_redirect to enforce access control.
     * Sets flags for restriction but doesn't exit yet.
     */
    public function enforce_full_access(): void
    {
        if (!is_singular()) {
            return;
        }

        global $post;
        $post_type = $post->post_type;

        // Check if the current post type is configured for protection
        $protected_post_types_str = get_option('seamless_protected_post_types', '');
        $protected_post_types = array_filter(array_map('trim', explode(',', $protected_post_types_str)));

        if (!in_array($post_type, $protected_post_types, true)) {
            return;
        }

        // Add admin override
        if (current_user_can('administrator')) {
            $this->log('enforce_full_access', 'User is administrator. Access granted.');
            return;
        }

        $required_plans = get_post_meta($post->ID, 'seamless_required_membership_plans', true);

        $this->log('enforce_full_access', 'Post ID ' . $post->ID . ' has required plans meta: ' . print_r($required_plans, true));

        if (!is_array($required_plans)) {
            $required_plans = [];
        }

        // If no plans are selected in the meta box, grant access.
        if (empty($required_plans)) {
            $this->log('enforce_full_access', 'No membership plans selected on post meta. Access granted.');
            return;
        }

        $this->log('enforce_full_access', 'Post ID ' . $post->ID . ' requires plans: ' . implode(',', $required_plans));

        // Access Evaluation
        $user_id = get_current_user_id();
        $membership_handler = new Membership();

        if (!is_user_logged_in()) {
            $this->log('enforce_full_access', 'User is logged out. Restricting access.');
            $this->set_restriction_flag(false);
            return;
        }

        // If the user is logged in, check if they have one of the required plans
        if (!$membership_handler->user_has_required_plan($user_id, $required_plans)) {
            $this->log('enforce_full_access', 'Logged in user ' . $user_id . ' denied access due to insufficient plan.');
            $this->set_restriction_flag(true);
            return;
        }

        $this->log('enforce_full_access', 'Logged in user ' . $user_id . ' granted access.');
    }

    /**
     * Sets a global flag to indicate content should be restricted
     * @param bool $is_logged_in True if the user is logged in but lacks membership.
     */
    private function set_restriction_flag(bool $is_logged_in): void
    {
        global $wp_query;

        // Set custom query var to flag restriction
        set_query_var('seamless_restrict_content', true);
        set_query_var('seamless_is_logged_in', $is_logged_in);

        // Set status to 403 but keep it as a valid singular page
        status_header(403);
        $wp_query->is_singular = true;
        $wp_query->is_404 = false;
    }

    /**
     * Filters the template to load our restriction template when needed
     * This hook fires AFTER wp_head() so all styles are properly loaded
     */
    public function load_restriction_template($template)
    {
        if (get_query_var('seamless_restrict_content')) {
            $restriction_template = plugin_dir_path(__FILE__) . '../Public/templates/tpl-content-restriction.php';

            if (file_exists($restriction_template)) {
                return $restriction_template;
            }
        }

        return $template;
    }
}
