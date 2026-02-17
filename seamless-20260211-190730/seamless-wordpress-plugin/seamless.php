<?php

/**
 * Plugin Name:       Seamless
 * Plugin URI:        https://seamlessams.com
 * Description:       Editable endpoints and shortcodes for events/Memberships with authentication.
 * Version:           1.0.0
 * Author:            Actualize Studio
 * Author URI:        https://vardaam.com
 * License:           GPL2
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       seamless
 * Domain Path:       /languages
 * Requires at least: 6.0
 * Tested up to:      6.3
 * Requires PHP:      7.4
 */

// If this file is called directly, abort.
if (! defined('WPINC')) {
    die;
}

/**
 * Plugin version.
 */
define('SEAMLESS_VERSION', '1.0.0');

/**
 * Plugin file path.
 */
define('SEAMLESS_PLUGIN_FILE', __FILE__);

/**
 * Plugin directory path.
 */
define('SEAMLESS_PLUGIN_DIR', plugin_dir_path(__FILE__));

/**
 * Plugin directory URL.
 */
define('SEAMLESS_PLUGIN_URL', plugin_dir_url(__FILE__));

/**
 * Plugin basename.
 */
define('SEAMLESS_PLUGIN_BASENAME', plugin_basename(__FILE__));

/**
 * Check plugin compatibility requirements.
 *
 * @return bool True if compatible, false otherwise.
 */
function seamless_check_compatibility()
{
    $php_min_version = '7.4';
    $wp_min_version  = '6.0';
    $is_compatible   = true;

    // Check PHP version.
    if (version_compare(PHP_VERSION, $php_min_version, '<')) {
        add_action('admin_notices', 'seamless_php_version_notice');
        $is_compatible = false;
    }

    // Check WordPress version.
    global $wp_version;
    if (version_compare($wp_version, $wp_min_version, '<')) {
        add_action('admin_notices', 'seamless_wp_version_notice');
        $is_compatible = false;
    }

    return $is_compatible;
}

/**
 * Display PHP version compatibility notice.
 */
function seamless_php_version_notice()
{
    $php_min_version = '7.4';
?>
    <div class="notice notice-error">
        <p>
            <?php
            printf(
                /* translators: 1: Required PHP version, 2: Current PHP version */
                esc_html__('Seamless plugin requires PHP %1$s or higher. Your current PHP version is %2$s. Please update PHP.', 'seamless'),
                esc_html($php_min_version),
                esc_html(PHP_VERSION)
            );
            ?>
        </p>
    </div>
<?php
}

/**
 * Display WordPress version compatibility notice.
 */
function seamless_wp_version_notice()
{
    $wp_min_version = '6.0';
    global $wp_version;
?>
    <div class="notice notice-error">
        <p>
            <?php
            printf(
                /* translators: 1: Required WordPress version, 2: Current WordPress version */
                esc_html__('Seamless plugin requires WordPress %1$s or higher. Your current WordPress version is %2$s. Please update WordPress.', 'seamless'),
                esc_html($wp_min_version),
                esc_html($wp_version)
            );
            ?>
        </p>
    </div>
<?php
}

// Early exit if compatibility check fails.
if (! seamless_check_compatibility()) {
    return;
}

// Autoload classes via Composer.
require_once SEAMLESS_PLUGIN_DIR . 'vendor/autoload.php';

use Seamless\SeamlessAutoLoader;

/**
 * Register React Event Shortcode
 *
 * Registers the [seamless_react_events] shortcode for displaying the React event calendar
 *
 * @since 1.0.0
 */
function seamless_register_react_event_shortcode()
{
    add_shortcode('seamless_react_events', 'seamless_react_event_render');
}
add_action('init', 'seamless_register_react_event_shortcode', 5);

/**
 * Render React Event Shortcode
 *
 * Outputs the container element where the React app will mount
 * Enqueues necessary scripts and styles
 *
 * @return string HTML container for React app
 * @since 1.0.0
 */
function seamless_react_event_render()
{
    // Enqueue React Event scripts and styles
    seamless_enqueue_react_event_assets();
    
    // Return the container where React will mount
    return '<div id="seamless-react-root" class="seamless-react-container"></div>';
}

/**
 * Enqueue React Event Assets
 *
 * Loads the React app JavaScript and CSS files
 * Only loads when shortcode is used
 *
 * @since 1.0.0
 */
function seamless_enqueue_react_event_assets()
{
    static $assets_enqueued = false;
    
    // Prevent double enqueuing
    if ($assets_enqueued) {
        return;
    }
    $assets_enqueued = true;
    
    $plugin_url = SEAMLESS_PLUGIN_URL;
    $plugin_dir = SEAMLESS_PLUGIN_DIR;
    
    // Define the path to the React app dist folder
    $dist_folder = $plugin_dir . 'src/Public/assets/react-build/dist/';
    $dist_url = $plugin_url . 'src/Public/assets/react-build/dist/';
    
    // Check if dist folder exists
    if (!is_dir($dist_folder)) {
        // Log error for debugging
        error_log('Seamless React Event: dist folder not found at ' . $dist_folder);
        return;
    }
    
    // Enqueue CSS file - dynamically find the asset file
    $assets_folder = $dist_folder . 'assets/';
    if (is_dir($assets_folder)) {
        $files = scandir($assets_folder);
        
        // Find CSS file
        foreach ($files as $file) {
            if (strpos($file, 'index-') === 0 && strpos($file, '.css') !== false) {
                $css_path = $assets_folder . $file;
                wp_enqueue_style(
                    'seamless-react-event-css',
                    $dist_url . 'assets/' . $file,
                    array(),
                    filemtime($css_path),
                    'all'
                );
                break;
            }
        }
        
        // Find JavaScript file
        foreach ($files as $file) {
            if (strpos($file, 'index-') === 0 && strpos($file, '.js') !== false) {
                $js_path = $assets_folder . $file;
                wp_enqueue_script(
                    'seamless-react-event-js',
                    $dist_url . 'assets/' . $file,
                    array(),
                    filemtime($js_path),
                    true
                );
                break;
            }
        }
    } else {
        error_log('Seamless React Event: assets folder not found at ' . $assets_folder);
    }
}

/**
 * Initialize the plugin.
 *
 * Instantiates the main plugin class to bootstrap all functionality.
 * Uses plugins_loaded hook to ensure all WordPress core is available.
 */
function seamless_init()
{
    load_plugin_textdomain('seamless', false, dirname(SEAMLESS_PLUGIN_BASENAME) . '/languages');

    SeamlessAutoLoader::getInstance();
}
add_action('plugins_loaded', 'seamless_init');

/**
 * Register REST API Routes for React Events
 *
 * Creates WordPress REST API endpoints for the React app to fetch events data
 *
 * @since 1.0.0
 */
function seamless_register_react_events_api_routes()
{
    // Events list endpoint
    register_rest_route('seamless/v1', '/events', array(
        'methods' => 'GET',
        'callback' => 'seamless_api_get_events',
        'permission_callback' => '__return_true',
        'args' => array(
            'page' => array(
                'required' => false,
                'type' => 'integer',
                'default' => 1,
            ),
            'limit' => array(
                'required' => false,
                'type' => 'integer',
                'default' => 10,
            ),
            'category' => array(
                'required' => false,
                'type' => 'string',
            ),
            'search' => array(
                'required' => false,
                'type' => 'string',
            ),
        ),
    ));
    
    // Single event endpoint
    register_rest_route('seamless/v1', '/events/(?P<id>[a-zA-Z0-9\-]+)', array(
        'methods' => 'GET',
        'callback' => 'seamless_api_get_single_event',
        'permission_callback' => '__return_true',
    ));
}
add_action('rest_api_init', 'seamless_register_react_events_api_routes');

/**
 * API Handler: Get Events List
 *
 * Fetches events from the external API and returns them to React app
 *
 * @param WP_REST_Request $request The REST request object
 * @return WP_REST_Response
 * @since 1.0.0
 */
function seamless_api_get_events($request)
{
    $api_endpoint = 'https://mafp.seamlessams.com/api';
    
    // Build query parameters from request
    $params = array(
        'page' => $request->get_param('page') ?: 1,
        'limit' => $request->get_param('limit') ?: 10,
    );
    
    $category = $request->get_param('category');
    if ($category) {
        $params['category'] = $category;
    }
    
    $search = $request->get_param('search');
    if ($search) {
        $params['search'] = $search;
    }
    
    // Make request to third-party API
    $url = $api_endpoint . '/events/';
    
    $response = wp_remote_get(add_query_arg($params, $url), array(
        'timeout' => 10,
        'sslverify' => false,
    ));
    
    // Handle errors
    if (is_wp_error($response)) {
        return new WP_REST_Response(
            array('error' => $response->get_error_message()),
            500
        );
    }
    
    $http_code = wp_remote_retrieve_response_code($response);
    $body = wp_remote_retrieve_body($response);
    
    if (200 !== $http_code) {
        return new WP_REST_Response(
            array('error' => 'API Error', 'status' => $http_code),
            $http_code
        );
    }
    
    $data = json_decode($body, true);
    return new WP_REST_Response($data, 200);
}

/**
 * API Handler: Get Single Event
 *
 * Fetches a single event from the external API
 *
 * @param WP_REST_Request $request The REST request object
 * @return WP_REST_Response
 * @since 1.0.0
 */
function seamless_api_get_single_event($request)
{
    $api_endpoint = 'https://mafp.seamlessams.com/api';
    $event_id = $request->get_param('id');
    
    if (empty($event_id)) {
        return new WP_REST_Response(
            array('error' => 'Event ID is required'),
            400
        );
    }
    
    $url = $api_endpoint . '/events/' . $event_id;
    
    $response = wp_remote_get($url, array(
        'timeout' => 10,
        'sslverify' => false,
    ));
    
    // Handle errors
    if (is_wp_error($response)) {
        return new WP_REST_Response(
            array('error' => $response->get_error_message()),
            500
        );
    }
    
    $http_code = wp_remote_retrieve_response_code($response);
    $body = wp_remote_retrieve_body($response);
    
    if (200 !== $http_code) {
        return new WP_REST_Response(
            array('error' => 'API Error', 'status' => $http_code),
            $http_code
        );
    }
    
    $data = json_decode($body, true);
    return new WP_REST_Response($data, 200);
}

/**
 * Plugin activation hook.
 *
 * Registers custom rewrite rules and flushes permalinks.
 */
function seamless_activate()
{
    require_once SEAMLESS_PLUGIN_DIR . 'vendor/autoload.php';

    // Set default endpoint if not already set
    if (!get_option('seamless_ams_content_endpoint')) {
        add_option('seamless_ams_content_endpoint', 'ams-content');
    }

    $endpoints = new \Seamless\Endpoints\Endpoints();
    $endpoints->register_rewrite_rules();
    flush_rewrite_rules();
}
register_activation_hook(__FILE__, 'seamless_activate');

/**
 * Plugin deactivation hook.
 *
 * Flushes permalinks to remove custom rewrite rules.
 */
function seamless_deactivate()
{
    // Flush rewrite rules to clean up custom endpoints.
    flush_rewrite_rules();
}
register_deactivation_hook(__FILE__, 'seamless_deactivate');
