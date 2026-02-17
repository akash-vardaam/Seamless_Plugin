<?php

/**
 * Template for [seamless_events] shortcode - List View
 * 
 * Available variables:
 * @var array $events Array of event objects
 * @var bool $show_featured_image Whether to display featured images
 * @var array $shortcode_atts Original shortcode attributes
 */

if (empty($events)) {
    echo '<p class="seamless-no-events">' . esc_html__('No events found.', 'seamless') . '</p>';
    return;
}
?>

<div class="seamless-events-shortcode seamless-events-list">
    <?php foreach ($events as $event) :
        if (($event['event_type'] ?? '') === 'group_event' && empty($event['associated_events'])) {
            continue;
        }

        $image = !empty($event['featured_image']) ? $event['featured_image'] : plugin_dir_url(__DIR__) . 'img/default.png';
        $event_type = $event['event_type'] ?? 'event';
        $title = $event['title'] ?? '';
        $description = !empty($event['description']) ? $event['description'] : '';

        // Handle start/end date for both event and group_event
        if ($event_type === 'group_event') {
            $startDate = $event['formatted_start_date'] ?? '';
            $endDate = $event['formatted_end_date'] ?? '';
        } else {
            $startDate = $event['formatted_start_date'] ?? $event['start_date'] ?? '';
            $endDate = $event['formatted_end_date'] ?? $event['end_date'] ?? '';
            $startDay = $startDate ? date('l, F j, Y', strtotime($startDate)) : '';
            $endDay = $endDate ? date('l, F j, Y', strtotime($endDate)) : '';
            $startTime = $startDate ? date('h:i A', strtotime($startDate)) : '';
            $endTime = $endDate ? date('h:i A', strtotime($endDate)) : '';
        }

        // Venue details
        // Venue details - extract from associated_events for group events
        if ($event_type === 'group_event' && !empty($event['associated_events'][0]['venue'])) {
            $venue = $event['associated_events'][0]['venue'];
        } else {
            $venue = $event['venue'] ?? [];
        }
        $venue_parts = [];
        if (!empty($venue['name'])) $venue_parts[] = $venue['name'];
        if (!empty($venue['address_line_1'])) $venue_parts[] = $venue['address_line_1'];
        elseif (!empty($venue['address'])) $venue_parts[] = $venue['address'];
        if (!empty($venue['city'])) $venue_parts[] = $venue['city'];
        if (!empty($venue['state'])) $venue_parts[] = $venue['state'];
        if (!empty($venue['zip_code'])) $venue_parts[] = $venue['zip_code'];
        $venue_location = implode(', ', $venue_parts);

        $slug = get_option('seamless_single_event_endpoint', 'event');
        $details_url = site_url('/' . $slug . '/' . esc_html($event['slug'] ?? ''));
        $details_url .= '?type=' . ($event_type === 'group_event' ? 'group_event' : 'event');
    ?>
        <div class="seamless-event-item">
            <?php if ($show_featured_image) : ?>
                <div class="seamless-event-image-container">
                    <div class="loader"></div>
                    <img src="<?php echo esc_url($image); ?>" alt="<?php echo esc_attr($title); ?>" class="seamless-event-image" style="display:none;" onload="imageLoaded(this)" />
                </div>
            <?php endif; ?>

            <div class="seamless-event-details">
                <h3 class="seamless-event-title">
                    <a href="<?= esc_url($details_url) ?>" class="seamless-event-title-link">
                        <?= esc_html($title); ?>
                    </a>
                </h3>

                <div class="seamless-event-meta">
                    <div class="seamless-event-meta-item">
                        <i class="fa-regular fa-calendar-days"></i>
                        <span class="seamless-event-date">
                            <?php
                            if ($startDay && $endDay && $startDay !== $endDay) {
                                echo esc_html($startDay) . ' - ' . esc_html($endDay);
                            } elseif ($startDay) {
                                echo esc_html($startDay);
                            } else {
                                echo '-';
                            }
                            ?>
                        </span>
                    </div>

                    <div class="seamless-event-meta-item">
                        <i class="fa-regular fa-clock"></i>
                        <span class="seamless-event-time">
                            <?php
                            if ($startTime && $endTime) {
                                echo esc_html($startTime) . ' - ' . esc_html($endTime);
                            } elseif ($startTime) {
                                echo esc_html($startTime);
                            } else {
                                echo '-';
                            }
                            ?>
                        </span>
                    </div>

                    <?php if ($venue_location): ?>
                        <div class="seamless-event-meta-item">
                            <i class="fa-solid fa-location-dot"></i>
                            <span class="seamless-event-location"><?= esc_html($venue_location); ?></span>
                        </div>
                    <?php endif; ?>
                </div>

                <?php if (!empty($description)) : ?>
                    <p class="seamless-event-description"><?php echo esc_html(wp_trim_words($description, 30)); ?></p>
                <?php endif; ?>

                <a href="<?= esc_url($details_url) ?>" class="seamless-event-link">SEE DETAILS</a>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<script>
    function imageLoaded(img) {
        if (!img) return;
        img.style.display = 'block';
        if (img.previousElementSibling) {
            img.previousElementSibling.style.display = 'none';
        }
    }
</script>