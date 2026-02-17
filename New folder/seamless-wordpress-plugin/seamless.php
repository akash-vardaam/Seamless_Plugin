<?php

/**
 * Plugin Name:       Seamless
 * Plugin URI:        https://seamlessams.com
 * Description:       Editable endpoints and shortcodes for event/donation with authentication.
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
 * Initialize the plugin.
 *
 * Instantiates the main plugin class to bootstrap all functionality.
 * Uses plugins_loaded hook to ensure all WordPress core is available.
 */
function seamless_init()
{
    load_plugin_textdomain('seamless', false, dirname(SEAMLESS_PLUGIN_BASENAME) . '/languages');

    SeamlessAutoLoader::getInstance();
    
    // Load React Events integration (doesn't conflict with existing functionality)
    if ( file_exists( SEAMLESS_PLUGIN_DIR . 'includes/react-events.php' ) ) {
        require_once SEAMLESS_PLUGIN_DIR . 'includes/react-events.php';
    }
}
add_action('plugins_loaded', 'seamless_init');

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
