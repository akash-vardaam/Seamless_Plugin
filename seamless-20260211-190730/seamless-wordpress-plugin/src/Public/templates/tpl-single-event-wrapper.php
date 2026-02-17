<?php get_header();
$event_slug = get_query_var('event_uuid');
$event_type = isset($_GET['type']) ? sanitize_text_field($_GET['type']) : 'event';
?>
<?php echo do_shortcode('[seamless_single_event slug="' . esc_attr($event_slug) . '" type="' . esc_attr($event_type) . '"]'); ?>
<?php get_footer(); ?>
