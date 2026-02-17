<?php
$image = !empty($event['featured_image']) ? $event['featured_image'] : plugin_dir_url(__DIR__) . 'img/default.png';
$event_type = $event['event_type'] ?? 'event';
$title = $event['title'] ?? '';
$description = !empty($event['description']) ? $event['description'] : '';
$events_page_url = site_url('/' . get_option('seamless_event_list_endpoint', 'events') . '/');
// Handle start/end date for both event and group_event
// Use WordPress timezone settings
$timezone_obj = function_exists('wp_timezone') ? wp_timezone() : new DateTimeZone(wp_timezone_string());

if ($event_type === 'group_event') {
  $startDate = $event['formatted_start_date'] ?? '';
  $endDate = $event['formatted_end_date'] ?? '';
} else {
  $startDate = $event['start_date'] ?? '';
  $endDate = $event['end_date'] ?? '';
}

$startTime = '';
$endTime = '';
$dateDisplay = '';
$timeDisplay = '';
$timezoneAbbr = '';

if ($startDate) {
  try {
    if ($event_type === 'group_event') {
      $startDt = new DateTime($startDate, $timezone_obj);
    } else {
      // Regular events are UTC
      $startDt = new DateTime($startDate, new DateTimeZone('UTC'));
      $startDt->setTimezone($timezone_obj);
    }
  } catch (Exception $e) {
    $startDt = new DateTime($startDate);
    $startDt->setTimezone($timezone_obj);
  }

  $endDt = null;
  if ($endDate) {
    try {
      if ($event_type === 'group_event') {
        $endDt = new DateTime($endDate, $timezone_obj);
      } else {
        $endDt = new DateTime($endDate, new DateTimeZone('UTC'));
        $endDt->setTimezone($timezone_obj);
      }
    } catch (Exception $e) {
      $endDt = new DateTime($endDate);
      $endDt->setTimezone($timezone_obj);
    }
  }

  $timezoneAbbr = $startDt->format('T');

  if ($endDt) {
    $sameDay       = $startDt->format('Y-m-d') === $endDt->format('Y-m-d');
    $sameMonthYear = $startDt->format('Y-m') === $endDt->format('Y-m');
    $sameYear      = $startDt->format('Y') === $endDt->format('Y');

    if ($sameDay) {
      $dateDisplay = $startDt->format('l, F j, Y');
    } elseif ($sameMonthYear) {
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
    $dateDisplay = $startDt->format('D, M j, Y');
    $startTime = $startDt->format('g:i A');
    $timeDisplay = $startTime;
  }
}

// Venue details
$venue = $event['venue'] ?? [];
$venue_parts = [];
if (!empty($venue['name'])) $venue_parts[] = $venue['name'];
if (!empty($venue['address_line_1'])) $venue_parts[] = $venue['address_line_1'];
elseif (!empty($venue['address'])) $venue_parts[] = $venue['address'];
if (!empty($venue['city'])) $venue_parts[] = $venue['city'];
if (!empty($venue['state'])) $venue_parts[] = $venue['state'];
if (!empty($venue['zip_code'])) $venue_parts[] = $venue['zip_code'];
$venue_location = implode(', ', $venue_parts);

$total_capacity = 0;
$has_tickets = false;

if ($event_type === 'group_event' && !empty($event['associated_events'])) {
  foreach ($event['associated_events'] as $associated_event) {
    if (!empty($associated_event['tickets'])) {
      $has_tickets = true;
      foreach ($associated_event['tickets'] as $ticket) {
        $total_capacity += intval($ticket['inventory'] ?? 0);
      }
    }
  }
} else {
  if (!empty($event['tickets'])) {
    $has_tickets = true;
    foreach ($event['tickets'] as $ticket) {
      $total_capacity += intval($ticket['inventory'] ?? 0);
    }
  }
}

// Check if event is past
$is_past_event = false;
if ($endDate) {
  $event_end = new DateTime($endDate);
  $today = new DateTime();
  $today->setTime(0, 0, 0);
  $is_past_event = $event_end < $today;
} elseif ($startDate) {
  $event_start = new DateTime($startDate);
  $today = new DateTime();
  $today->setTime(0, 0, 0);
  $is_past_event = $event_start < $today;
}

// Register Button Logic
$register_url = '';

if (!empty($event['registration_url'])) {
  $register_url = $event['registration_url'];
} elseif ($event_type === 'group_event' && !empty($event['slug'])) {
  $client_domain = get_option('seamless_client_domain', '');
  if ($client_domain) {
    $client_domain = rtrim($client_domain, '/');
    $register_url = $client_domain . '/events/' . $event['slug'] . '/register';
  } else {
    $register_url = site_url('/event/' . $event['slug'] . '/register');
  }
}

// Prefer event-level fields, but fall back to tickets: earliest start, latest end
$registration_start_raw = $event['registration_start_date'] ?? ($event['registration_start'] ?? null);
$registration_end_raw   = $event['registration_end_date'] ?? ($event['registration_end'] ?? null);

if ((!$registration_start_raw || !$registration_end_raw) && !empty($event['tickets']) && is_array($event['tickets'])) {
  $earliest_start = null;
  $latest_end    = null;

  foreach ($event['tickets'] as $ticket) {
    $ticket_start = $ticket['registration_start_date'] ?? null;
    $ticket_end   = $ticket['registration_end_date'] ?? null;

    if ($ticket_start) {
      $ts = strtotime($ticket_start);
      if ($ts !== false && ($earliest_start === null || $ts < $earliest_start)) {
        $earliest_start = $ts;
      }
    }

    if ($ticket_end) {
      $te = strtotime($ticket_end);
      if ($te !== false && ($latest_end === null || $te > $latest_end)) {
        $latest_end = $te;
      }
    }
  }

  if (!$registration_start_raw && $earliest_start !== null) {
    $registration_start_raw = date('Y-m-d H:i:s', $earliest_start);
  }
  if (!$registration_end_raw && $latest_end !== null) {
    $registration_end_raw = date('Y-m-d H:i:s', $latest_end);
  }
}

$registration_start_dt = null;
$registration_end_dt   = null;

if ($registration_start_raw) {
  try {
    // Registration dates are in UTC, convert to event timezone
    $registration_start_dt = new DateTime($registration_start_raw, new DateTimeZone('UTC'));
    $registration_start_dt->setTimezone($timezone_obj);
  } catch (Exception $e) {
    $registration_start_dt = null;
  }
}

if ($registration_end_raw) {
  try {
    // Registration dates are in UTC, convert to event timezone
    $registration_end_dt = new DateTime($registration_end_raw, new DateTimeZone('UTC'));
    $registration_end_dt->setTimezone($timezone_obj);
  } catch (Exception $e) {
    $registration_end_dt = null;
  }
}

$now_dt = new DateTime();

$is_before_registration = $registration_start_dt ? ($now_dt < $registration_start_dt) : false;
$is_after_registration  = $registration_end_dt ? ($now_dt > $registration_end_dt) : false;

$registration_message = '';
$registration_ends_text = '';

// Before registration window: show start (and end if available)
if ($is_before_registration && $registration_start_dt && $registration_end_dt) {
  $registration_message = sprintf(
    'Registration starts on %s and ends on %s.',
    $registration_start_dt->format('M j, Y \a\t g:i A'),
    $registration_end_dt->format('M j, Y \a\t g:i A')
  );
} elseif ($is_before_registration && $registration_start_dt) {
  $registration_message = sprintf(
    'Registration starts on %s.',
    $registration_start_dt->format('M j, Y \a\t g:i A')
  );
  // After registration window: show closed message
} elseif ($is_after_registration && $registration_end_dt) {
  $registration_message = sprintf(
    'Registration closed at %s on %s.',
    $registration_end_dt->format('g:i A'),
    $registration_end_dt->format('m/d/y')
  );
  // During registration window: show only ends text (to be displayed above button)
} elseif (!$is_before_registration && !$is_after_registration && $registration_end_dt) {
  $registration_ends_text = sprintf(
    'Registration ends on %s.',
    $registration_end_dt->format('M j, Y g:i A')
  );
}

$all_schedules = [];
if ($event_type === 'group_event' && !empty($event['associated_events'])) {
  foreach ($event['associated_events'] as $associated_event) {
    if (!empty($associated_event['schedules'])) {
      foreach ($associated_event['schedules'] as $schedule) {
        $all_schedules[] = $schedule;
      }
    }
  }
  usort($all_schedules, fn($a, $b) => strtotime($a['start_date_display']) - strtotime($b['start_date_display']));
} else {
  $all_schedules = $event['schedules'] ?? [];
}

// Prepare calendar data
$calendar_name = $event['title'] ?? '';
$calendar_description = !empty($event['except_description'])
  ? wp_strip_all_tags($event['except_description'])
  : wp_strip_all_tags($event['description'] ?? '');

// Limit description length for calendar
if (strlen($calendar_description) > 200) {
  $calendar_description = substr($calendar_description, 0, 197) . '...';
}

// Format dates for add-to-calendar-button (YYYY-MM-DD format)
$calendar_start_date = '';
$calendar_start_time = '';
$calendar_end_date = '';
$calendar_end_time = '';
$calendar_timezone = 'America/Chicago'; // Default timezone

if ($startDate) {
  try {
    if ($event_type === 'group_event') {
      $calStartDt = new DateTime($startDate, $timezone_obj);
    } else {
      $calStartDt = new DateTime($startDate, new DateTimeZone('UTC'));
      $calStartDt->setTimezone($timezone_obj);
    }

    $calendar_start_date = $calStartDt->format('Y-m-d');
    $calendar_start_time = $calStartDt->format('H:i');
    $calendar_timezone = $calStartDt->getTimezone()->getName();

    if ($endDate) {
      if ($event_type === 'group_event') {
        $calEndDt = new DateTime($endDate, $timezone_obj);
      } else {
        $calEndDt = new DateTime($endDate, new DateTimeZone('UTC'));
        $calEndDt->setTimezone($timezone_obj);
      }
      $calendar_end_date = $calEndDt->format('Y-m-d');
      $calendar_end_time = $calEndDt->format('H:i');
    } else {
      // If no end date, assume event is 2 hours long
      $calEndDt = clone $calStartDt;
      $calEndDt->modify('+2 hours');
      $calendar_end_date = $calEndDt->format('Y-m-d');
      $calendar_end_time = $calEndDt->format('H:i');
    }
  } catch (Exception $e) {
    // If date parsing fails, calendar button won't show
    $calendar_start_date = '';
  }
}

?>
<div class="seamless-breadcrumbs-container">
  <div class="seamless-breadcrumbs">
    <a href="<?= esc_url(site_url()); ?>">Home</a> <span class="seamless-breadcrumb-separator">»</span>
    <a href="<?= esc_url($events_page_url); ?>">Events</a> <span class="seamless-breadcrumb-separator">»</span>
    <span><?= esc_html($title); ?></span>
  </div>
</div>

<div class="single-event-layout">
  <div class="event-main-content">
    <div class="event-header">
      <div class="event-image">
        <img src="<?= esc_html($image) ?>" alt="<?= esc_attr($title) ?>">
      </div>
      <div class="event-header-text">
        <h1 class="event-title"><?= esc_html($title); ?></h1>
        <?php if (!empty($event['except_description'])) : ?>
          <div class="event-except-description">
            <?= wp_kses_post($event['except_description']); ?>
          </div>
        <?php endif; ?>
      </div>
    </div>

    <?php if (!empty($description)) : ?>
      <div class="event-description-block">
        <?= wp_kses_post($description); ?>
      </div>
    <?php endif; ?>

    <div class="accordion-item-container">
      <?php if (!empty($all_schedules)) : ?>
        <div class="accordion-item">
          <button class="accordion-header">
            <i class="fa fa-chevron-down"></i>
            Schedule
          </button>
          <div class="accordion-body">
            <table class="event-schedule-table">
              <thead>
                <tr>
                  <th><?= $event_type === 'group_event' ? 'Date & Time' : 'Time' ?></th>
                  <th>Description</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($all_schedules as $schedule) : ?>
                  <tr>
                    <td>
                      <?php if ($event_type === 'group_event') : ?>
                        <?= esc_html(date('M j, Y', strtotime($schedule['start_date_display']))) ?><br>
                        <strong><?= esc_html(date('h:i A', strtotime($schedule['start_date_display']))) ?></strong>
                      <?php else : ?>
                        <strong><?= esc_html(date('h:i A', strtotime($schedule['start_date_display']))) ?></strong>
                      <?php endif; ?>
                    </td>
                    <td><?= wp_kses_post($schedule['description']) ?></td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        </div>
      <?php endif; ?>

      <?php if (!empty($event['additional_details'])): ?>
        <?php foreach ($event['additional_details'] as $detail): ?>
          <div class="accordion-item">
            <button class="accordion-header">
              <i class="fa fa-chevron-down"></i>
              <?= esc_html($detail['name']); ?>
            </button>
            <div class="accordion-body">
              <div class="additional-detail-value">
                <?= wp_kses_post($detail['value']); ?>
              </div>
            </div>
          </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>

    <?php if (!empty($event['sponsors']) && is_array($event['sponsors']) && count($event['sponsors']) > 0) : ?>
      <div class="event-sponsors-section">
        <h3 class="sponsors-title">Thank You, Partners!</h3>
        <p class="sponsors-description">We’re grateful for our health care partners and their support of this series and Minnesota’s family physicians.
        </p>
        <div class="sponsors-carousel">
          <?php foreach ($event['sponsors'] as $index => $sponsor_url) : ?>
            <div class="sponsor-slide">
              <img src="<?= esc_url($sponsor_url); ?>" alt="Sponsor <?= $index + 1; ?>" loading="lazy">
            </div>
          <?php endforeach; ?>
        </div>
      </div>
    <?php endif; ?>
  </div>

  <div class="event-sidebar">
    <div class="event-info-card">
      <div class="event-info-item">
        <i class="fa-regular fa-calendar"></i>
        <div class="event-info-content">
          <div class="event-info-label">Date</div>
          <div class="event-info-value">
            <?= $dateDisplay ? esc_html($dateDisplay) : '-'; ?>
          </div>
        </div>
      </div>

      <div class="event-info-item">
        <i class="fa-regular fa-clock"></i>
        <div class="event-info-content">
          <div class="event-info-label">Time</div>
          <div class="event-info-value">
            <?php if ($timeDisplay): ?>
              <?= esc_html($timeDisplay . ($timezoneAbbr ? ' ' . $timezoneAbbr : '')); ?>
            <?php else: ?>
              -
            <?php endif; ?>
          </div>
        </div>
      </div>

      <?php if ($total_capacity > 0): ?>
        <div class="event-info-item">
          <i class="fa-solid fa-users"></i>
          <div class="event-info-content">
            <div class="event-info-label">Capacity</div>
            <div class="event-info-value"><?= esc_html($total_capacity); ?> capacity</div>
          </div>
        </div>
      <?php endif; ?>

      <?php
      $virtual_link = $event['virtual_meeting_link'] ?? '';
      $location_html = '';

      $venue_name = $venue['name'] ?? '';
      $google_map_url = $venue['google_map_url'] ?? '';

      if (!empty($venue_name)) {
        if (!empty($google_map_url)) {
          $location_html .= '<a href="' . esc_url($google_map_url) . '" target="_blank" rel="noopener noreferrer" class="venue-link" style="text-decoration: none; color: inherit;">' . esc_html($venue_name) . ' <i class="fa-solid fa-arrow-up-right-from-square" style="font-size: 0.8em; margin-left: 5px; color: var(--seamless-secondary-color);"></i></a>';
        } else {
          $location_html .= esc_html($venue_name);
        }

        if (!empty($virtual_link)) {
          $location_html .= ' + Online';
        }
        $location_html .= '<br>';
      } elseif (!empty($virtual_link)) {
        $location_html .= 'Online<br>';
      } else {
        $location_html .= 'Online<br>';
      }

      $venue_address_1 = $venue['address_line_1'] ?? ($venue['address'] ?? '');
      if (!empty($venue_address_1)) {
        $location_html .= esc_html($venue_address_1) . ',<br>';
      }
      $city = $venue['city'] ?? '';
      $state = $venue['state'] ?? '';
      $zip = $venue['zip_code'] ?? '';

      $city_state_parts = [];
      if (!empty($city)) $city_state_parts[] = $city;
      if (!empty($state)) $city_state_parts[] = $state;

      if (!empty($city_state_parts)) {
        $location_html .= esc_html('(' . implode(', ', $city_state_parts) . ')');
      }

      if (!empty($zip)) {
        $location_html .= ' ' . esc_html($zip);
      }
      if (empty($location_html)) {
        $location_html = 'TBA';
      }

      // Build location string for calendar (after venue variables are defined)
      $calendar_location = '';
      if (!empty($venue_name)) {
        $calendar_location = $venue_name;
        if (!empty($venue_address_1)) {
          $calendar_location .= ', ' . $venue_address_1;
        }
        if (!empty($city)) {
          $calendar_location .= ', ' . $city;
        }
        if (!empty($state)) {
          $calendar_location .= ', ' . $state;
        }
        if (!empty($zip)) {
          $calendar_location .= ' ' . $zip;
        }
      } elseif (!empty($virtual_link)) {
        $calendar_location = 'Online Event';
      }
      ?>
      <div class="event-info-item">
        <i class="fa-solid fa-location-dot"></i>
        <div class="event-info-content">
          <div class="event-info-label">Location</div>
          <div class="event-info-value">
            <?= wp_kses_post($location_html); ?>
          </div>
        </div>
      </div>

      <?php if (!empty($calendar_start_date)) : ?>
        <div class="event-info-item event-calendar-button-wrapper">
          <div class="event-info-content">
            <add-to-calendar-button
              name="<?= esc_attr($calendar_name); ?>"
              description="<?= esc_attr($calendar_description); ?>"
              startDate="<?= esc_attr($calendar_start_date); ?>"
              startTime="<?= esc_attr($calendar_start_time); ?>"
              endDate="<?= esc_attr($calendar_end_date); ?>"
              endTime="<?= esc_attr($calendar_end_time); ?>"
              timeZone="<?= esc_attr($calendar_timezone); ?>"
              location="<?= esc_attr($calendar_location); ?>"
              options="'Apple','Google','iCal','Microsoft365','Outlook.com','Yahoo'"
              lightMode="bodyScheme"
              size="3"
              buttonStyle="round"
              hideIconButton
              hideBackground
              hidebranding
              styleLight="--btn-background: transparent; --btn-text: var(--seamless-secondary-color); --font: inherit; --btn-border-radius: 8px; --btn-border: var(--seamless-secondary-color); --btn-shadow: none; --btn-hover-background: var(--seamless-secondary-color); --btn-hover-text: #ffffff; --btn-hover-border: none; --btn-hover-shadow: none; padding: var(--btn-padding-y) var(--btn-padding-x) !important; --btn-active-shadow: none;"></add-to-calendar-button>
          </div>
        </div>
      <?php endif; ?>
    </div>

    <div class="event-info-card">
      <div class="event-tickets-section">
        <h3 class="ticket-label">Tickets</h3>
        <?php if (!empty($event['tickets'])) : ?>
          <?php foreach ($event['tickets'] as $ticket) : ?>
            <div class="ticket-item">
              <div class="ticket-title"><?= esc_html($ticket['label']); ?></div>
              <div class="ticket-details">
                <span class="ticket-price">
                  <?php
                  $price = $ticket['price'];
                  echo (is_numeric($price) && floatval($price) == 0) ? 'Free' : '$' . esc_html($price);
                  ?>
                </span>
              </div>
            </div>
          <?php endforeach; ?>
        <?php else : ?>
          <div class="ticket-item">
            <div class="ticket-title">No tickets available</div>
          </div>
        <?php endif; ?>
      </div>

      <?php if ($is_past_event): ?>
        <button class="event-past-btn">
          Event has passed!
        </button>
      <?php elseif ($registration_message && $is_before_registration): ?>
        <div class="event-registration-message">
          <?= esc_html($registration_message); ?>
        </div>
      <?php elseif ($registration_message && $is_after_registration): ?>
        <div class="event-registration-message">
          <?= esc_html($registration_message); ?>
        </div>
      <?php elseif (!$has_tickets): ?>
        <button class="event-coming-soon-btn">
          Event ticket coming soon
        </button>
      <?php elseif ($register_url): ?>
        <?php if (!empty($registration_ends_text)): ?>
          <div class="event-registration-message">
            <?= esc_html($registration_ends_text); ?>
          </div>
        <?php endif; ?>
        <a href="<?= esc_url($register_url); ?>" class="event-register-btn" target="_blank" rel="noopener">
          Register Now
        </a>
      <?php else: ?>
        <button class="event-register-btn">
          Registration unavailable
        </button>
      <?php endif; ?>
    </div>
  </div>
</div>
<script type="text/javascript">
  (function() {
    function initAccordion() {
      const accordionHeaders = document.querySelectorAll(".accordion-header");

      accordionHeaders.forEach(header => {
        // Remove any existing listeners
        const newHeader = header.cloneNode(true);
        header.parentNode.replaceChild(newHeader, header);

        // Add click event
        newHeader.addEventListener("click", function(e) {
          e.preventDefault();
          const item = this.parentElement;
          item.classList.toggle("active");
        });
      });
    }

    // Initialize when DOM is ready
    if (document.readyState === "loading") {
      document.addEventListener("DOMContentLoaded", initAccordion);
    } else {
      initAccordion();
    }
  })();
</script>