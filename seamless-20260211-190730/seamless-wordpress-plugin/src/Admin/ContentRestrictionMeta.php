<?php

namespace Seamless\Admin;

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
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_scripts']);
    }

    /**
     * Enqueue scripts for the admin area
     */
    public function enqueue_admin_scripts($hook)
    {
        // Only enqueue on post edit screens
        if (!in_array($hook, ['post.php', 'post-new.php'])) {
            return;
        }

        // Check if this post type has our meta box
        $screen = get_current_screen();
        if (!$screen) {
            return;
        }

        $protected_post_types_str = get_option('seamless_protected_post_types', '');
        $protected_post_types = array_filter(array_map('trim', explode(',', $protected_post_types_str)));

        if (!in_array($screen->post_type, $protected_post_types)) {
            return;
        }

        // Enqueue the API client
        wp_enqueue_script(
            'seamless-api-client',
            plugin_dir_url(dirname(dirname(__FILE__))) . 'src/Public/assets/js/seamless-api-client.js',
            [],
            '1.0.0',
            true
        );

        // Pass API domain to JavaScript
        wp_localize_script('seamless-api-client', 'seamless_ajax', [
            'api_domain' => get_option('seamless_client_domain', '')
        ]);
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

        // Output container for plans
?>
        <p>Select the membership plans required to access this content. If none are selected, the content is public.</p>

        <div id="seamless-plans-loading" style="padding: 10px;">
            <span class="spinner is-active" style="float: none; margin: 0;"></span>
            <span style="margin-left: 10px;">Loading membership plans...</span>
        </div>

        <div id="seamless-plans-error" style="display: none; color: red; padding: 10px;"></div>

        <div id="seamless-plans-container" style="display: none;">
            <ul id="seamless-plans-list" style="max-height: 200px; overflow-y: auto; margin: 0; padding-left: 15px;"></ul>
        </div>

        <script type="text/javascript">
            (function() {
                const savedPlans = <?php echo json_encode($saved_plans); ?>;
                const metaKey = '<?php echo esc_js(self::META_KEY); ?>';

                async function loadMembershipPlans() {
                    const loadingEl = document.getElementById('seamless-plans-loading');
                    const errorEl = document.getElementById('seamless-plans-error');
                    const containerEl = document.getElementById('seamless-plans-container');
                    const listEl = document.getElementById('seamless-plans-list');

                    try {
                        // Check if API domain is configured
                        if (!window.seamless_ajax || !window.seamless_ajax.api_domain) {
                            throw new Error('API domain is not configured. Please set the Client Domain in WordPress Admin → Seamless → Authentication');
                        }

                        // Wait for SeamlessAPI to be available
                        if (typeof window.SeamlessAPI === 'undefined') {
                            await new Promise(resolve => {
                                let attempts = 0;
                                const checkAPI = setInterval(() => {
                                    attempts++;
                                    if (typeof window.SeamlessAPI !== 'undefined') {
                                        console.log('[Seamless] SeamlessAPI loaded');
                                        clearInterval(checkAPI);
                                        resolve();
                                    } else if (attempts > 50) { // 5 seconds timeout
                                        clearInterval(checkAPI);
                                        throw new Error('SeamlessAPI failed to load');
                                    }
                                }, 100);
                            });
                        }

                        // Fetch membership plans
                        const result = await window.SeamlessAPI.getMembershipPlans(1, '');

                        if (!result || !result.data || !Array.isArray(result.data)) {
                            throw new Error('Invalid API response format');
                        }

                        const plans = result.data;

                        if (plans.length === 0) {
                            errorEl.textContent = 'No membership plans found.';
                            errorEl.style.display = 'block';
                            loadingEl.style.display = 'none';
                            return;
                        }

                        // Render plans
                        listEl.innerHTML = '';
                        plans.forEach(plan => {
                            const planId = plan.id;
                            const planLabel = plan.label || plan.name || `Plan ID ${planId}`;
                            const isChecked = savedPlans.includes(String(planId));

                            const li = document.createElement('li');
                            li.style.marginBottom = '5px';

                            const label = document.createElement('label');
                            label.style.display = 'block';

                            const checkbox = document.createElement('input');
                            checkbox.type = 'checkbox';
                            checkbox.name = metaKey + '[]';
                            checkbox.value = planId;
                            checkbox.checked = isChecked;

                            label.appendChild(checkbox);
                            label.appendChild(document.createTextNode(' ' + planLabel));
                            li.appendChild(label);
                            listEl.appendChild(li);
                        });

                        // Show container, hide loading
                        loadingEl.style.display = 'none';
                        containerEl.style.display = 'block';

                    } catch (error) {
                        errorEl.textContent = 'Error loading membership plans: ' + error.message;
                        errorEl.style.display = 'block';
                        loadingEl.style.display = 'none';
                    }
                }

                // Load plans when DOM is ready
                if (document.readyState === 'loading') {
                    document.addEventListener('DOMContentLoaded', loadMembershipPlans);
                } else {
                    loadMembershipPlans();
                }
            })();
        </script>
<?php
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
