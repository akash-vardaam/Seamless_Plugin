<?php

/**
 * Template Name: Seamless AMS Content
 * Description: Template for displaying AMS content
 */

get_header();
?>

<main>
    <?php
    /**
     * Filter: seamless_ams_content
     * 
     * Allows modification of the AMS content before display.
     * 
     * @param string $content The AMS content to display
     */
    $ams_content = apply_filters('seamless_ams_content', '{{seamlessams-content}}');

    ?>
    <?php echo wp_kses_post($ams_content); ?>
</main>

<?php
get_footer();
