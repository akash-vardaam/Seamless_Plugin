<div class="event-grid">
    <?php
    if (empty($events)) {
        $sortBy = $_GET['sort'] ?? 'all';
        $message = 'No events found.';
        if ($sortBy !== 'all') {
            $message = "No " . esc_html($sortBy) . " events found.";
        }
        echo '<p class="text-center text-red-500">' . $message . '</p>';
    } else {
        $slug = get_option('seamless_single_event_endpoint', 'event');
        foreach ($events as $event) :
            // Skip group events without associated events
            if (($event['event_type'] ?? '') === 'group_event' && empty($event['associated_events'])) {
                continue;
            }

            $image = !empty($event['featured_image']) ? $event['featured_image'] : plugin_dir_url(__DIR__) . 'img/default.png';
            $event_type = $event['event_type'] ?? 'event';

            // Handle start/end date for both event and group_event
            if ($event_type === 'group_event') {
                $startDate = $event['formatted_start_date'] ?? '';
                $endDate = $event['formatted_end_date'] ?? '';
            } else {
                $startDate = $event['formatted_start_date'] ?? $event['start_date'] ?? '';
                $endDate = $event['formatted_end_date'] ?? $event['end_date'] ?? '';
            }

            // Normalize date/time using WordPress timezone and prepare display strings
            $startTime = '';
            $endTime = '';
            $dateDisplay = '';
            $timeDisplay = '';
            $timezoneAbbr = '';

            if ($startDate) {
                $wpTz = function_exists('wp_timezone') ? wp_timezone() : new DateTimeZone(wp_timezone_string());

                try {
                    $startDt = new DateTime($startDate, $wpTz);
                } catch (Exception $e) {
                    $startDt = new DateTime($startDate);
                    $startDt->setTimezone($wpTz);
                }

                $endDt = null;
                if ($endDate) {
                    try {
                        $endDt = new DateTime($endDate, $wpTz);
                    } catch (Exception $e) {
                        $endDt = new DateTime($endDate);
                        $endDt->setTimezone($wpTz);
                    }
                }

                $timezoneAbbr = $startDt->format('T');

                // Build date display with weekday and ranges
                if ($endDt) {
                    $sameDay       = $startDt->format('Y-m-d') === $endDt->format('Y-m-d');
                    $sameMonthYear = $startDt->format('Y-m') === $endDt->format('Y-m');
                    $sameYear      = $startDt->format('Y') === $endDt->format('Y');

                    if ($sameDay) {
                        // Example: Thursday, July 30, 2026
                        $dateDisplay = $startDt->format('l, F j, Y');
                    } elseif ($sameMonthYear) {
                        // Example: Thursday - Saturday, July 30 - 31, 2026
                        $dateDisplay = sprintf(
                            '%s - %s, %s %d - %d, %s',
                            $startDt->format('l'),
                            $endDt->format('l'),
                            $startDt->format('F'),
                            (int) $startDt->format('j'),
                            (int) $endDt->format('j'),
                            $startDt->format('Y')
                        );
                    } else {
                        if ($sameYear) {
                            // Example: Thursday - Saturday, July 30 - August 1, 2026
                            $dateDisplay = sprintf(
                                '%s - %s, %s %d - %s %d, %s',
                                $startDt->format('l'),
                                $endDt->format('l'),
                                $startDt->format('F'),
                                (int) $startDt->format('j'),
                                $endDt->format('F'),
                                (int) $endDt->format('j'),
                                $startDt->format('Y')
                            );
                        } else {
                            // Cross-year range: Thursday, December 31, 2026 - Saturday, January 2, 2027
                            $dateDisplay = sprintf(
                                '%s, %s %d, %s - %s, %s %d, %s',
                                $startDt->format('l'),
                                $startDt->format('F'),
                                (int) $startDt->format('j'),
                                $startDt->format('Y'),
                                $endDt->format('l'),
                                $endDt->format('F'),
                                (int) $endDt->format('j'),
                                $endDt->format('Y')
                            );
                        }
                    }

                    $startTime = $startDt->format('g:i A');
                    $endTime = $endDt->format('g:i A');
                    $timeDisplay = sprintf('%s – %s', $startTime, $endTime);
                } else {
                    // Single date only
                    $dateDisplay = $startDt->format('l, F j, Y');
                    $startTime = $startDt->format('g:i A');
                    $timeDisplay = $startTime;
                }
            }

            $details_url = site_url('/' . $slug . '/' . esc_html($event['slug'] ?? ''));
            // $details_url .= '?type=' . ($event_type === 'group_event' ? 'group_event' : 'event');
    ?>
            <div class="event-card">
                <a href="<?php echo esc_url($details_url); ?>" class="event-link">
                    <div class="image-container">
                        <div class="loader"></div>
                        <img src="<?php echo esc_url($image); ?>" alt="<?php echo esc_attr($event['title']); ?>" class="event-image" style="display:none;" onload="imageLoaded(this)">
                    </div>
                </a>
                <div class="event-details">
                    <h3 class="event-title"><a href="<?php echo esc_url($details_url); ?>" class="event-title-link"><?php echo esc_html($event['title']); ?></a></h3>
                    <div class="event-time-details">
                        <div class="event-time-loc">
                            <p class="event-date">
                                <?= $dateDisplay ? esc_html($dateDisplay) : '-'; ?>
                            </p>
                        </div>
                        <div class="event-time-loc">
                            <p class="event-time-range">
                                <?php if ($timeDisplay): ?>
                                    <?= esc_html($timeDisplay . ($timezoneAbbr ? ' ' . $timezoneAbbr : '')); ?>
                                <?php else: ?>
                                    -
                                <?php endif; ?>
                            </p>
                        </div>
                    </div>
                    <a href="<?php echo esc_url($details_url); ?>" class="event-link">SEE DETAILS</a>
                </div>
            </div>
    <?php
        endforeach;
    }
    ?>
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