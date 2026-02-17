<?php

/**
 * Template for Event List
 * Rendered via Client-Side API
 */
$layout = get_option('seamless_list_view_layout', 'option_1');
?>
<div class="seamless-event-list seamless-event-list-container" data-view="list" data-layout="<?php echo esc_attr($layout); ?>">
    <div class="seamless-loader-container">
        <div class="loader"></div>
    </div>
</div>