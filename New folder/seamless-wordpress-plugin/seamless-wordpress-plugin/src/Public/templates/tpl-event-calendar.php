<?php
/**
 * Template for Calendar View
 * File: templates/tpl-calendar-event.php
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="seamless-calendar-container">
    <div class="calendar-header">
        <div class="calendar-title">
            <div class="date-info">
                <div class="month-abbr" id="currentMonth"><?php echo strtoupper(date('M')); ?></div>
                <div class="day-number" id="currentDay"><?php echo date('j'); ?></div>
            </div>
            <div>
                <h1 id="calendarTitle"><?php echo date('F Y'); ?></h1>
                <div class="date-range" id="dateRange"><?php echo date('M j, Y') . ' — ' . date('M t, Y'); ?></div>
            </div>
        </div>
        
        <div class="calendar-controls">
            <!-- <div class="search-container">
                <input type="text" class="search-input" id="searchInput" placeholder="<?php //esc_attr_e('Search events...', 'seamless'); ?>">
                <i class="fas fa-search search-icon"></i>
            </div> -->
            
            <div class="calendar-navigation">
                <button class="nav-button" id="prevBtn">
                    <i class="fas fa-chevron-left"></i>
                </button>
                <button class="nav-button today-button" id="todayBtn"><?php esc_html_e('Today', 'seamless'); ?></button>
                <button class="nav-button" id="nextBtn">
                    <i class="fas fa-chevron-right"></i>
                </button>
            </div>
            
            <div class="view-selector">
                <button class="view-button" data-view="month"><?php esc_html_e('Month', 'seamless'); ?></button>
                <button class="view-button" data-view="week"><?php esc_html_e('Week', 'seamless'); ?></button>
                <button class="view-button" data-view="day"><?php esc_html_e('Day', 'seamless'); ?></button>
            </div>
        </div>
    </div>

    <div id="seamlessCalendar"></div>
</div>
<script type="text/javascript">
    jQuery(document).ready(function($) {
        window.seamlessCalendar = new SeamlessCalendar({
            ajaxUrl: '<?php echo admin_url('admin-ajax.php'); ?>',
            nonce: '<?php echo wp_create_nonce('seamless_nonce'); ?>',
            events: <?php echo json_encode($events); ?>,
            slug: '<?php echo get_option('seamless_single_event_endpoint', 'event'); ?>'
        });
    });
</script>