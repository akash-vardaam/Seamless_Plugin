<?php

namespace Seamless\Admin;

use Seamless\Operations\Membership;
use WP_Error;

/**
 * Class ContentRestrictionMeta
 * Manages the custom meta box for content restriction using native WordPress functions.
 */
class ContentRestrictionMeta
{
    const META_KEY = 'seamless_required_membership_plans';

    public function __construct()
    {
        add_action('add_meta_boxes', [$this, 'add_custom_meta_box']);
        add_action('save_post', [$this, 'save_post_meta']);
    }

    /**
     * Adds the 'Seamless Content Access' meta box to configured post types.
     */
    public function add_custom_meta_box(): void
    {
        $protected_post_types_str = get_option('seamless_protected_post_types', '');
        $protected_post_types = array_filter(array_map('trim', explode(',', $protected_post_types_str)));

        if (empty($protected_post_types)) {
            return;
        }

        foreach ($protected_post_types as $post_type) {
            add_meta_box(
                'seamless_content_access_meta_box',
                'Seamless Content Access',
                [$this, 'render_meta_box_content'],
                $post_type,
                'side',
                'default'
            );
        }
    }

    /**
     * Renders the HTML content for the meta box (the checklist).
     * @param \WP_Post $post The current post object.
     */
    public function render_meta_box_content(\WP_Post $post): void
    {
        // Add a nonce field for security
        wp_nonce_field(basename(__FILE__), 'seamless_content_access_nonce');

        // Get currently saved plans
        $saved_plans = get_post_meta($post->ID, self::META_KEY, true);
        if (!is_array($saved_plans)) {
            $saved_plans = [];
        }

        // Get dynamic membership plans from the SSO API
        $membership_handler = new Membership();
        $api_result = $membership_handler->get_membership_plans();

        if (is_wp_error($api_result)) {
            echo '<p style="color: red;">' . esc_html('Error fetching plans: ' . $api_result->get_error_message()) . '</p>';
            echo '<p>Please check your SSO Domain configuration.</p>';
            return;
        }

        // --- FIX START: Extract the plans array from the result wrapper ---
        // Your Membership class returns: ['success' => bool, 'data' => array of plans]
        $plans_list = $api_result['data'] ?? [];

        if (empty($plans_list) || !is_array($plans_list)) {
            $error_message = empty($plans_list) ? 'No membership plans found in API response data.' : 'Error: API data format is incorrect.';
            echo '<p style="color: red;">' . esc_html($error_message) . '</p>';
            return;
        }
        // --- FIX END ---

        // The display now uses the extracted $plans_list
        echo '<p>Select the membership plans required to access this content. If none are selected, the content is public.</p>';

        // Checklist implementation (acts as multi-select)
        echo '<ul style="max-height: 200px; overflow-y: auto; margin: 0; padding-left: 15px;">';

        foreach ($plans_list as $plan) {
            $plan_id = $plan['id'] ?? null;
            $plan_label = $plan['label'] ?? ($plan['name'] ?? ('Plan ID ' . $plan_id)); // Added 'name' fallback

            if ($plan_id) {
                $checked = in_array((string)$plan_id, $saved_plans) ? 'checked' : ''; // Ensure type consistency for comparison
                echo '<li style="margin-bottom: 5px;">';
                echo '<label style="display: block;">';
                echo '<input type="checkbox" name="' . esc_attr(self::META_KEY) . '[]" value="' . esc_attr($plan_id) . '" ' . $checked . '/> ';
                echo esc_html($plan_label);
                echo '</label>';
                echo '</li>';
            }
        }
        echo '</ul>';
    }

    /**
     * Saves the meta box data when a post is saved.
     * @param int $post_id The ID of the post being saved.
     */
    public function save_post_meta(int $post_id): void
    {
        // Check nonce
        if (!isset($_POST['seamless_content_access_nonce']) || !wp_verify_nonce($_POST['seamless_content_access_nonce'], basename(__FILE__))) {
            return;
        }

        // Check user permissions
        if (!current_user_can('edit_post', $post_id)) {
            return;
        }

        // Check if data was submitted
        if (isset($_POST[self::META_KEY])) {
            $new_plans = array_map('sanitize_text_field', (array)$_POST[self::META_KEY]);
            update_post_meta($post_id, self::META_KEY, $new_plans);
        } else {
            // If the checkbox field is empty, it means all plans were unchecked. Save an empty array.
            update_post_meta($post_id, self::META_KEY, []);
        }
    }
}
