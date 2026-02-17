<?php

/**
 * Seamless React Events Integration
 *
 * This file handles the integration of the React Events App into WordPress.
 * It registers the [seamless_react_events] shortcode and enqueues necessary assets.
 *
 * @package Seamless
 * @subpackage React_Events
 * @since 1.0.0
 */

// Prevent direct access to this file
if ( ! defined( 'WPINC' ) ) {
    die;
}

/**
 * Register React Events Shortcode
 *
 * Adds the [seamless_react_events] shortcode to WordPress
 * This shortcode displays the React-based events interface
 *
 * @since 1.0.0
 */
function seamless_register_react_events_shortcode() {
    add_shortcode( 'seamless_react_events', 'seamless_react_events_render' );
}
add_action( 'init', 'seamless_register_react_events_shortcode' );

/**
 * Render React Events Shortcode
 *
 * Outputs the container element where the React app will mount
 * Enqueues necessary scripts and styles
 *
 * @return string HTML container for React app
 * @since 1.0.0
 */
function seamless_react_events_render() {
    // Enqueue React Events scripts and styles
    seamless_enqueue_react_events_assets();
    
    // Return the container where React will mount
    return '<div id="events-react-root"></div>';
}

/**
 * Enqueue React Events Assets
 *
 * Loads the React app JavaScript and CSS files
 * Only loads when shortcode is used (called from seamless_react_events_render)
 *
 * @since 1.0.0
 */
function seamless_enqueue_react_events_assets() {
    $plugin_url = SEAMLESS_PLUGIN_URL;
    $plugin_dir = SEAMLESS_PLUGIN_DIR;
    
    // Define the path to the React app dist folder
    $dist_folder = $plugin_dir . 'react-app/dist/';
    $dist_url = $plugin_url . 'react-app/dist/';
    
    // Check if dist folder exists
    if ( ! is_dir( $dist_folder ) ) {
        // Log error for debugging
        if ( current_user_can( 'manage_options' ) && WP_DEBUG ) {
            error_log( 'Seamless React Events: dist folder not found at ' . $dist_folder );
        }
        return;
    }
    
    // Enqueue CSS file
    $css_files = glob( $dist_folder . 'assets/*.css' );
    if ( ! empty( $css_files ) ) {
        $css_file = basename( $css_files[0] );
        wp_enqueue_style(
            'seamless-react-events',
            $dist_url . 'assets/' . $css_file,
            array(),
            SEAMLESS_VERSION,
            'all'
        );
    }
    
    // Enqueue Google Fonts (Merriweather and Montserrat)
    wp_enqueue_style(
        'seamless-google-fonts',
        'https://fonts.googleapis.com/css2?family=Merriweather:wght@400;500;700&family=Montserrat:wght@400;500;600;700&display=swap',
        array(),
        SEAMLESS_VERSION
    );
    
    // Enqueue JavaScript file
    $js_files = glob( $dist_folder . 'assets/*.js' );
    if ( ! empty( $js_files ) ) {
        $js_file = basename( $js_files[0] );
        wp_enqueue_script(
            'seamless-react-events',
            $dist_url . 'assets/' . $js_file,
            array(),
            SEAMLESS_VERSION,
            true
        );
    }
}

/**
 * Get API Endpoint
 *
 * Returns the API endpoint URL for the React app to use
 * Can be configured via WordPress admin settings
 *
 * @return string API endpoint URL
 * @since 1.0.0
 */
function seamless_get_react_api_endpoint() {
    return 'https://mafp.seamlessams.com/api';
}

/**
 * Output API Endpoint as JavaScript Variable
 *
 * Makes the site URL available to the React app via window object
 * React will use WordPress REST API proxy to fetch events
 *
 * @since 1.0.0
 */
function seamless_output_react_config() {
    if ( ! has_shortcode( get_post()->post_content, 'seamless_react_events' ) ) {
        return;
    }
    
    ?>
    <script type="text/javascript">
        window.seamlessReactConfig = {
            siteUrl: <?php echo wp_json_encode( site_url() ); ?>
        };
    </script>
    <?php
}
add_action( 'wp_head', 'seamless_output_react_config' );

/**
 * Register REST API Proxy Endpoints
 *
 * Creates WordPress REST API endpoints that act as proxies
 * to the third-party events API, avoiding CORS issues
 *
 * @since 1.0.0
 */
function seamless_register_api_proxy_routes() {
    // Events list endpoint
    register_rest_route( 'seamless/v1', '/events', array(
        'methods' => 'GET',
        'callback' => 'seamless_proxy_events_list',
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
    ) );
    
    // Single event endpoint
    register_rest_route( 'seamless/v1', '/events/(?P<id>\\d+)', array(
        'methods' => 'GET',
        'callback' => 'seamless_proxy_events_single',
        'permission_callback' => '__return_true',
    ) );
}
add_action( 'rest_api_init', 'seamless_register_api_proxy_routes' );

/**
 * Proxy Events List Request
 *
 * Fetches events from third-party API and returns them to React app
 * Handles pagination, filtering, and search parameters
 *
 * @param WP_REST_Request $request REST API request object
 * @return WP_REST_Response API response
 * @since 1.0.0
 */
function seamless_proxy_events_list( $request ) {
    $api_endpoint = 'https://mafp.seamlessams.com/api';
    
    // Build query parameters from request
    $params = array(
        'page' => $request->get_param( 'page' ) ?: 1,
        'limit' => $request->get_param( 'limit' ) ?: 10,
    );
    
    $category = $request->get_param( 'category' );
    if ( $category ) {
        $params['category'] = $category;
    }
    
    $search = $request->get_param( 'search' );
    if ( $search ) {
        $params['search'] = $search;
    }
    
    // Make request to third-party API
    $url = $api_endpoint . '/events/';
    
    $response = wp_remote_get( add_query_arg( $params, $url ), array(
        'timeout' => 10,
        'sslverify' => true,
    ) );
    
    // Handle errors
    if ( is_wp_error( $response ) ) {
        return new WP_REST_Response(
            array( 'error' => $response->get_error_message() ),
            500
        );
    }
    
    $http_code = wp_remote_retrieve_response_code( $response );
    $body = wp_remote_retrieve_body( $response );
    
    if ( 200 !== $http_code ) {
        return new WP_REST_Response(
            array( 'error' => 'API Error', 'status' => $http_code ),
            $http_code
        );
    }
    
    $data = json_decode( $body, true );
    return new WP_REST_Response( $data, 200 );
}

/**
 * Proxy Single Event Request
 *
 * Fetches a single event from third-party API
 *
 * @param WP_REST_Request $request REST API request object
 * @return WP_REST_Response API response
 * @since 1.0.0
 */
function seamless_proxy_events_single( $request ) {
    $api_endpoint = 'https://mafp.seamlessams.com/api';
    $id = $request->get_param( 'id' );
    
    // Make request to third-party API
    $url = $api_endpoint . '/events/' . absint( $id ) . '/';
    
    $response = wp_remote_get( $url, array(
        'timeout' => 10,
        'sslverify' => true,
    ) );
    
    // Handle errors
    if ( is_wp_error( $response ) ) {
        return new WP_REST_Response(
            array( 'error' => $response->get_error_message() ),
            500
        );
    }
    
    $http_code = wp_remote_retrieve_response_code( $response );
    $body = wp_remote_retrieve_body( $response );
    
    if ( 200 !== $http_code ) {
        return new WP_REST_Response(
            array( 'error' => 'Event not found' ),
            404
        );
    }
    
    $data = json_decode( $body, true );
    return new WP_REST_Response( $data, 200 );
}



?>
