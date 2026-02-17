<?php
/**
 * Template: React Event Calendar Application
 * 
 * This template loads the React event calendar application into the WordPress frontend.
 * The React app is self-contained and manages its own state and rendering.
 * 
 * @package Seamless
 * @subpackage Public/Templates
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}
?>

<div id="seamless-react-root" class="seamless-react-container"></div>

<script>
    // Wait for the React app bundle to load and initialize
    document.addEventListener('DOMContentLoaded', function() {
        // The React app will mount itself to the #seamless-react-root element
        // This is handled by the main.tsx entry point of the React application
    });
</script>
