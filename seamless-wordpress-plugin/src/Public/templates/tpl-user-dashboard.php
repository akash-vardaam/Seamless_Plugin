<?php

/**
 * User Dashboard Widget Template
 * 
 * Variables available:
 * @var array $profile User profile data
 * @var array $current_memberships Active memberships
 * @var array $membership_history Membership history
 * @var array $orders Order history
 * @var array $enrolled_courses Enrolled courses
 * @var array $included_courses Courses included in membership
 * @var string $client_domain Client domain URL
 * @var array $settings Widget settings
 * @var string $widget_id Widget ID
 * @var int $orders_per_page Orders per page for pagination
 * @var int $total_orders Total number of orders
 * @var int $total_pages Total pages for orders
 */

if (!defined('ABSPATH')) {
  exit;
}

$show_membership = $settings['show_membership_tab'] === 'yes';
$show_orders = $settings['show_orders_tab'] === 'yes';
$show_courses = $settings['show_courses_tab'] === 'yes';
$show_profile = $settings['show_profile_tab'] === 'yes';
$client_domain = rtrim(get_option('seamless_client_domain', ''), '/');

// User info
$pf_first = $profile['first_name'] ?? '';
$pf_last = $profile['last_name'] ?? '';
$pf_name = trim(($pf_first . ' ' . $pf_last)) ?: ($profile['name'] ?? ($profile['full_name'] ?? 'MAFP'));
$pf_email = $profile['email'] ?? '';

// Membership counts
$active_count = count($current_memberships);
$expired_count = count($membership_history);
?>

<div class="seamless-user-dashboard" data-widget-id="<?php echo esc_attr($widget_id); ?>">
  <aside class="seamless-user-dashboard-sidebar">
    <div class="seamless-user-dashboard-profile-card">
      <div class="seamless-user-dashboard-profile-name"><?php echo esc_html($pf_name); ?></div>
      <div class="seamless-user-dashboard-profile-email">Email: <?php echo esc_html($pf_email); ?></div>
    </div>

    <nav class="seamless-user-dashboard-nav">
      <?php if ($show_profile): ?>
        <button class="seamless-user-dashboard-nav-item active" data-view="profile">
          <span><?php _e('Profile', 'seamless-addon'); ?></span>
          <svg width="20" height="20" viewBox="0 0 20 20" fill="none">
            <path d="M7.5 15L12.5 10L7.5 5" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
          </svg>
        </button>
      <?php endif; ?>
      <?php if ($show_membership || $show_history): ?>
        <button class="seamless-user-dashboard-nav-item" data-view="memberships">
          <span><?php _e('Memberships', 'seamless-addon'); ?></span>
          <svg width="20" height="20" viewBox="0 0 20 20" fill="none">
            <path d="M7.5 15L12.5 10L7.5 5" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
          </svg>
        </button>
      <?php endif; ?>

      <?php if ($show_courses): ?>
        <button class="seamless-user-dashboard-nav-item" data-view="courses">
          <span><?php _e('Courses', 'seamless-addon'); ?></span>
          <svg width="20" height="20" viewBox="0 0 20 20" fill="none">
            <path d="M7.5 15L12.5 10L7.5 5" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
          </svg>
        </button>
      <?php endif; ?>

      <?php if ($show_orders): ?>
        <button class="seamless-user-dashboard-nav-item" data-view="orders">
          <span><?php _e('Orders', 'seamless-addon'); ?></span>
          <svg width="20" height="20" viewBox="0 0 20 20" fill="none">
            <path d="M7.5 15L12.5 10L7.5 5" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
          </svg>
        </button>
      <?php endif; ?>

      <?php if (function_exists('wp_logout_url')): ?>
        <a href="<?php echo esc_url(wp_logout_url(get_permalink())); ?>" class="seamless-user-dashboard-nav-item seamless-user-dashboard-nav-logout">
          <span><?php _e('Logout', 'seamless-addon'); ?></span>
          <svg width="20" height="20" viewBox="0 0 20 20" fill="none">
            <path d="M7.5 15L12.5 10L7.5 5" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
          </svg>
        </a>
      <?php endif; ?>
    </nav>
  </aside>

  <!-- Main Content -->
  <main class="seamless-user-dashboard-main">
    <?php if ($show_membership || $show_history): ?>
      <div class="seamless-user-dashboard-view" data-view="memberships">
        <?php
        // Helper function to format dates
        function format_membership_date($date_string)
        {
          if (empty($date_string)) {
            return '—';
          }
          try {
            $date = new DateTime($date_string);
            return $date->format('M d, Y'); // e.g., "Feb 02, 2026"
          } catch (Exception $e) {
            return $date_string;
          }
        }

        // Get the latest active membership for the current membership card
        $current_membership = null;
        if (!empty($current_memberships)) {
          // Sort by start_date descending to get the most recent
          usort($current_memberships, function ($a, $b) {
            $date_a = $a['start_date'] ?? $a['started_at'] ?? $a['created_at'] ?? '';
            $date_b = $b['start_date'] ?? $b['started_at'] ?? $b['created_at'] ?? '';
            return strcmp($date_b, $date_a);
          });
          $current_membership = $current_memberships[0];
        }
        ?>

        <div class="seamless-user-dashboard-summary-grid">
          <div class="seamless-user-dashboard-summary-card">
            <div class="seamless-user-dashboard-summary-value"><?php echo esc_html($active_count); ?></div>
            <div class="seamless-user-dashboard-summary-label"><?php _e('Total Memberships', 'seamless-addon'); ?></div>
          </div>

          <?php if ($current_membership):
            $plan_label = $current_membership['plan']['label'] ?? ($current_membership['label'] ?? ($current_membership['name'] ?? 'Membership'));
            $expiry_date = $current_membership['expiry_date'] ?? ($current_membership['expires_at'] ?? '');
            $status = $current_membership['status'] ?? 'active';
            $is_upgraded = !empty($current_membership['plan']['upgraded_from_id']);
            $card_class = $is_upgraded ? 'seamless-user-dashboard-current-membership-card upgraded' : 'seamless-user-dashboard-current-membership-card';
            $is_cancelled = isset($current_membership['status']) && $current_membership['status'] === 'cancelled';

          ?>
            <div class="<?php echo esc_attr($card_class); ?>">
              <div class="seamless-user-dashboard-current-membership-header">
                <h3><?php echo esc_html($plan_label); ?></h3>
                <span class="seamless-user-dashboard-badge seamless-user-dashboard-badge-<?php echo esc_attr(strtolower($status)); ?>">
                  <?php echo esc_html(ucfirst($status)); ?>
                </span>
              </div>
              <div class="seamless-user-dashboard-current-membership-body">
                <div class="seamless-user-dashboard-current-membership-expiry">
                  <?php if ($is_cancelled && !empty($expiry_date)): ?>
                    <div class="seamless-user-dashboard-membership-cancelled-notice">
                      <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <circle cx="12" cy="12" r="10"></circle>
                        <line x1="12" y1="16" x2="12" y2="12"></line>
                        <line x1="12" y1="8" x2="12.01" y2="8"></line>
                      </svg>
                      <span><?php printf(__('Your membership will end on %s', 'seamless-addon'), '<strong>' . esc_html(format_membership_date($expiry_date)) . '</strong>'); ?></span>
                    </div>
                  <?php elseif (!empty($current_membership['has_pending_transition']) && !empty($current_membership['pending_transition'])): ?>
                    <?php
                    $pending = $current_membership['pending_transition'];
                    $effective_date = !empty($pending['effective_on']) ? $pending['effective_on'] : $expiry_date;
                    $notes = !empty($pending['notes']) ? $pending['notes'] : '';
                    ?>
                    <div class="seamless-user-dashboard-membership-scheduled-notice">
                      <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <circle cx="12" cy="12" r="10"></circle>
                        <line x1="12" y1="16" x2="12" y2="12"></line>
                        <line x1="12" y1="8" x2="12.01" y2="8"></line>
                      </svg>
                      <div class="seamless-user-dashboard-scheduled-content">
                        <span><?php _e('This plan will be downgraded after the current term ends.', 'seamless-addon'); ?></span>
                        <span class="seamless-user-dashboard-scheduled-date"><?php printf(__('Effective on %s', 'seamless-addon'), '<strong>' . esc_html(format_membership_date($effective_date)) . '</strong>'); ?></span>
                        <button
                          class="seamless-user-dashboard-cancel-scheduled-btn"
                          data-membership-id="<?php echo esc_attr($current_membership['id']); ?>"
                          data-order-id="<?php echo esc_attr($pending['order_id'] ?? ''); ?>">
                          <?php _e('Cancel Scheduled Downgrade', 'seamless-addon'); ?>
                        </button>
                      </div>
                    </div>
                  <?php else: ?>
                    <?php _e('Expires on:', 'seamless-addon'); ?>
                    <strong><?php echo esc_html(format_membership_date($expiry_date)); ?></strong>
                  <?php endif; ?>
                </div>
              </div>
            </div>
          <?php else: ?>
            <div class="seamless-user-dashboard-current-membership-card seamless-user-dashboard-empty-membership">
              <div class="seamless-user-dashboard-summary-message">
                <strong><?php _e('No Active Membership', 'seamless-addon'); ?></strong>
                <p><?php _e('You can renew an expired plan from your history.', 'seamless-addon'); ?></p>
              </div>
            </div>
          <?php endif; ?>
        </div>

        <div class="seamless-user-dashboard-tabs-wrapper">
          <div class="seamless-user-dashboard-tabs-header">
            <button class="seamless-user-dashboard-tab active" data-tab="active">
              <?php _e('Active Memberships', 'seamless-addon'); ?>
              <?php if ($active_count > 0): ?>
                <span class="seamless-user-dashboard-tab-count"><?php echo esc_html($active_count); ?></span>
              <?php endif; ?>
            </button>
            <button class="seamless-user-dashboard-tab" data-tab="expired">
              <?php _e('Expired Memberships', 'seamless-addon'); ?>
              <?php if ($expired_count > 0): ?>
                <span class="seamless-user-dashboard-tab-count"><?php echo esc_html($expired_count); ?></span>
              <?php endif; ?>
            </button>
          </div>

          <div class="seamless-user-dashboard-tabs-content">
            <div class="seamless-user-dashboard-tab-content active" data-tab-content="active">
              <?php if (!empty($current_memberships)): ?>
                <div class="seamless-user-dashboard-membership-list">
                  <?php foreach ($current_memberships as $m):
                    $label = $m['plan']['label'] ?? ($m['label'] ?? ($m['name'] ?? 'Membership'));
                    $purchased = $m['start_date'] ?? ($m['started_at'] ?? ($m['created_at'] ?? ''));
                    $expiry = $m['expiry_date'] ?? ($m['expires_at'] ?? '');
                  ?>
                    <div class="seamless-user-dashboard-membership-card">
                      <div class="seamless-user-dashboard-membership-content">
                        <h3 class="seamless-user-dashboard-membership-title"><?php echo esc_html($label); ?></h3>
                        <div class="seamless-user-dashboard-membership-actions">
                          <?php
                          // Check for upgrade/downgrade options at membership level
                          $has_upgrades = !empty($m['upgradable_to']) && is_array($m['upgradable_to']) && count($m['upgradable_to']) > 0;
                          $has_downgrades = !empty($m['downgradable_to']) && is_array($m['downgradable_to']) && count($m['downgradable_to']) > 0;
                          $is_cancelled = isset($m['status']) && $m['status'] === 'cancelled';
                          $is_active = $m['status'] === 'active';

                          // Show three-dot menu if there are actions available
                          if (!$is_cancelled && ($has_upgrades || $has_downgrades || $is_active)): ?>
                            <div class="seamless-user-dashboard-menu-container">
                              <button class="seamless-user-dashboard-menu-button" data-membership-id="<?php echo esc_attr($m['id']); ?>">
                                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                  <circle cx="12" cy="12" r="1"></circle>
                                  <circle cx="12" cy="5" r="1"></circle>
                                  <circle cx="12" cy="19" r="1"></circle>
                                </svg>
                              </button>
                              <div class="seamless-user-dashboard-menu-dropdown">
                                <?php if ($has_upgrades): ?>
                                  <button class="seamless-user-dashboard-menu-item seamless-user-dashboard-badge-upgrade"
                                    data-membership-id="<?php echo esc_attr($m['id']); ?>"
                                    data-action-type="upgrade"
                                    data-membership-data="<?php echo esc_attr(json_encode($m)); ?>">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                      <path d="M18 15l-6-6-6 6" />
                                    </svg>
                                    <?php _e('Upgrade', 'seamless-addon'); ?>
                                  </button>
                                <?php endif; ?>

                                <?php if ($has_downgrades): ?>
                                  <button class="seamless-user-dashboard-menu-item seamless-user-dashboard-badge-downgrade"
                                    data-membership-id="<?php echo esc_attr($m['id']); ?>"
                                    data-action-type="downgrade"
                                    data-membership-data="<?php echo esc_attr(json_encode($m)); ?>">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                      <path d="M6 9l6 6 6-6" />
                                    </svg>
                                    <?php _e('Downgrade', 'seamless-addon'); ?>
                                  </button>
                                <?php endif; ?>

                                <?php if ($is_active): ?>
                                  <button class="seamless-user-dashboard-menu-item seamless-user-dashboard-badge-cancel"
                                    data-membership-id="<?php echo esc_attr($m['id']); ?>"
                                    data-plan-label="<?php echo esc_attr($label); ?>"
                                    data-plan-price="<?php echo esc_attr($m['plan']['price'] ?? '0'); ?>"
                                    data-membership-data="<?php echo esc_attr(json_encode($m)); ?>">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                      <line x1="18" y1="6" x2="6" y2="18"></line>
                                      <line x1="6" y1="6" x2="18" y2="18"></line>
                                    </svg>
                                    <?php _e('Cancel', 'seamless-addon'); ?>
                                  </button>
                                <?php endif; ?>
                              </div>
                            </div>
                          <?php endif; ?>

                          <?php if ($is_cancelled): ?>
                            <span class="seamless-user-dashboard-badge seamless-user-dashboard-badge-cancelled"><?php _e('Cancelled', 'seamless-addon'); ?></span>
                          <?php elseif (!$has_upgrades && !$has_downgrades && !$is_active): ?>
                            <span class="seamless-user-dashboard-badge seamless-user-dashboard-badge-active"><?php _e('Active', 'seamless-addon'); ?></span>
                          <?php endif; ?>
                        </div>
                        <div class="seamless-user-dashboard-membership-body">
                          <?php if (!empty($purchased)): ?>
                            <div class="seamless-user-dashboard-membership-meta">
                              <strong><?php _e('Purchased:', 'seamless-addon'); ?></strong>
                              <span><?php echo esc_html(format_membership_date($purchased)); ?></span>
                            </div>
                          <?php endif; ?>
                          <?php if (!empty($expiry)): ?>
                            <div class="seamless-user-dashboard-membership-meta">
                              <strong><?php _e('Expires:', 'seamless-addon'); ?></strong>
                              <span><?php echo esc_html(format_membership_date($expiry)); ?></span>
                            </div>
                          <?php endif; ?>
                        </div>
                      </div>
                    </div>
                  <?php endforeach; ?>
                </div>
              <?php else: ?>
                <p class="seamless-user-dashboard-empty"><?php _e('No active memberships found.', 'seamless-addon'); ?></p>
              <?php endif; ?>
            </div>

            <div class="seamless-user-dashboard-tab-content" data-tab-content="expired">
              <?php if (!empty($membership_history)): ?>
                <div class="seamless-user-dashboard-membership-list">
                  <?php foreach ($membership_history as $m):
                    $label = $m['plan']['label'] ?? ($m['label'] ?? ($m['name'] ?? 'Membership'));
                    $purchased = $m['start_date'] ?? ($m['started_at'] ?? ($m['created_at'] ?? ''));
                    $expired = $m['expiry_date'] ?? ($m['ended_at'] ?? '');
                  ?>
                    <div class="seamless-user-dashboard-membership-card">
                      <div class="seamless-user-dashboard-membership-content">
                        <h3 class="seamless-user-dashboard-membership-title"><?php echo esc_html($label); ?></h3>
                        <div class="seamless-user-dashboard-membership-actions">
                          <span class="seamless-user-dashboard-badge seamless-user-dashboard-badge-expired"><?php _e('Expired', 'seamless-addon'); ?></span>
                          <!-- <button class="seamless-user-dashboard-btn seamless-user-dashboard-btn-renew"><?php //_e('Renew Plan', 'seamless-addon'); 
                                                                                                              ?></button> -->
                        </div>
                        <div class="seamless-user-dashboard-membership-body">
                          <?php if (!empty($purchased)): ?>
                            <div class="seamless-user-dashboard-membership-meta">
                              <strong><?php _e('Purchased:', 'seamless-addon'); ?></strong>
                              <span><?php echo esc_html(format_membership_date($purchased)); ?></span>
                            </div>
                          <?php endif; ?>
                          <?php if (!empty($expired)): ?>
                            <div class="seamless-user-dashboard-membership-meta">
                              <strong><?php _e('Expired:', 'seamless-addon'); ?></strong>
                              <span><?php echo esc_html(format_membership_date($expired)); ?></span>
                            </div>
                          <?php endif; ?>
                        </div>
                      </div>
                    </div>
                  <?php endforeach; ?>
                </div>
              <?php else: ?>
                <p class="seamless-user-dashboard-empty"><?php _e('No expired memberships found.', 'seamless-addon'); ?></p>
              <?php endif; ?>
            </div>
          </div>
        </div>
      </div>
    <?php endif; ?>

    <?php if ($show_courses):
      $enrolled_count = count($enrolled_courses);
      $included_count = count($included_courses);
    ?>
      <div class="seamless-user-dashboard-view" data-view="courses">
        <div class="seamless-user-dashboard-tabs-wrapper">
          <div class="seamless-user-dashboard-tabs-header">
            <button class="seamless-user-dashboard-tab seamless-user-dashboard-course-tab active" data-tab="enrolled">
              <?php _e('Enrolled Courses', 'seamless-addon'); ?>
              <?php if ($enrolled_count > 0): ?>
                <span class="seamless-user-dashboard-tab-count"><?php echo esc_html($enrolled_count); ?></span>
              <?php endif; ?>
            </button>
            <button class="seamless-user-dashboard-tab seamless-user-dashboard-course-tab" data-tab="included">
              <?php _e('Included in Membership', 'seamless-addon'); ?>
              <?php if ($included_count > 0): ?>
                <span class="seamless-user-dashboard-tab-count"><?php echo esc_html($included_count); ?></span>
              <?php endif; ?>
            </button>
          </div>

          <div class="seamless-user-dashboard-tabs-content">
            <div class="seamless-user-dashboard-tab-content active" data-tab-content="enrolled">
              <?php if (!empty($enrolled_courses)): ?>
                <div class="seamless-user-dashboard-courses-grid">
                  <?php foreach ($enrolled_courses as $course):
                    $course_id = $course['id'] ?? '';
                    $course_title = $course['title'] ?? $course['name'] ?? 'Course';
                    $course_slug = $course['slug'] ?? '';
                    $course_image = $course['image'] ?? '';

                    // Get course URL
                    $course_url = !empty($course_slug) ? ($client_domain . '/courses/' . $course_slug) : '#';

                    // Fetch course progress from API
                    $progress_data = null;
                    $completed_lessons = 0;
                    $total_lessons = 0;
                    $progress_percent = 0;

                    if (!empty($course_id)) {
                      $user_profile = new \Seamless\Operations\UserProfile();
                      $progress_result = $user_profile->get_course_progress($course_id, $pf_email);

                      if ($progress_result['success'] && !empty($progress_result['data'])) {
                        $progress_data = $progress_result['data'];
                        $completed_lessons = intval($progress_data['completed_lessons'] ?? 0);
                        $total_lessons = intval($progress_data['total_lessons'] ?? 0);
                        $progress_percent = floatval($progress_data['progress'] ?? 0);

                        // Cap progress at 100%
                        if ($progress_percent > 100) {
                          $progress_percent = 100;
                        }
                      }
                    }

                    $is_completed = $progress_percent >= 100;
                  ?>
                    <div class="seamless-user-dashboard-course-card">
                      <?php if (!empty($course_image)): ?>
                        <div class="seamless-user-dashboard-course-image">
                          <img src="<?php echo esc_url($course_image); ?>" alt="<?php echo esc_attr($course_title); ?>">
                        </div>
                      <?php else: ?>
                        <div class="seamless-user-dashboard-course-image seamless-user-dashboard-course-image-placeholder">
                          <svg width="60" height="60" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path d="M12 2L2 7L12 12L22 7L12 2Z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                            <path d="M2 17L12 22L22 17" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                            <path d="M2 12L12 17L22 12" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                          </svg>
                        </div>
                      <?php endif; ?>

                      <div class="seamless-user-dashboard-course-content">
                        <h4 class="seamless-user-dashboard-course-title"><?php echo esc_html($course_title); ?></h4>

                        <div class="seamless-user-dashboard-course-progress">
                          <span class="seamless-user-dashboard-course-lessons">
                            <?php echo esc_html($completed_lessons . '/' . $total_lessons); ?> <?php _e('lessons', 'seamless-addon'); ?>
                          </span>
                        </div>

                        <?php if (!$is_completed): ?>
                          <a href="<?php echo esc_url($course_url); ?>" target="_blank" class="seamless-user-dashboard-course-continue">
                            <?php _e('Continue', 'seamless-addon'); ?>
                            <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" fill="none" viewBox="0 0 24 24">
                              <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 12H8m12 0-4 4m4-4-4-4M9 4H7a3 3 0 0 0-3 3v10a3 3 0 0 0 3 3h2" />
                            </svg>
                          </a>
                        <?php else: ?>
                          <a href="<?php echo esc_url($course_url); ?>" target="_blank" class="seamless-user-dashboard-course-completed">
                            <?php _e('Completed', 'seamless-addon'); ?> <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                              <path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"></path>
                              <polyline points="15 3 21 3 21 9"></polyline>
                              <line x1="10" y1="14" x2="21" y2="3"></line>
                            </svg>
                          </a>
                        <?php endif; ?>
                      </div>
                    </div>
                  <?php endforeach; ?>
                </div>
              <?php else: ?>
                <p class="seamless-user-dashboard-empty"><?php _e('You have not enrolled in any courses yet.', 'seamless-addon'); ?></p>
              <?php endif; ?>
            </div>

            <div class="seamless-user-dashboard-tab-content" data-tab-content="included">
              <?php if (!empty($included_courses)): ?>
                <div class="seamless-user-dashboard-courses-grid">
                  <?php foreach ($included_courses as $course):
                    $course_id = $course['id'] ?? '';
                    $course_title = $course['title'] ?? $course['name'] ?? 'Course';
                    $course_slug = $course['slug'] ?? '';
                    $course_image = $course['image'] ?? '';

                    // Get course URL
                    $course_url = !empty($course_slug) ? ($client_domain . '/courses/' . $course_slug) : '#';

                    // Fetch course progress from API
                    $progress_data = null;
                    $completed_lessons = 0;
                    $total_lessons = 0;
                    $progress_percent = 0;

                    if (!empty($course_id)) {
                      $user_profile = new \Seamless\Operations\UserProfile();
                      $progress_result = $user_profile->get_course_progress($course_id, $pf_email);

                      if ($progress_result['success'] && !empty($progress_result['data'])) {
                        $progress_data = $progress_result['data'];
                        $completed_lessons = intval($progress_data['completed_lessons'] ?? 0);
                        $total_lessons = intval($progress_data['total_lessons'] ?? 0);
                        $progress_percent = floatval($progress_data['progress'] ?? 0);

                        // Cap progress at 100%
                        if ($progress_percent > 100) {
                          $progress_percent = 100;
                        }
                      }
                    }

                    $is_completed = $progress_percent >= 100;
                  ?>
                    <div class="seamless-user-dashboard-course-card">
                      <?php if (!empty($course_image)): ?>
                        <div class="seamless-user-dashboard-course-image">
                          <img src="<?php echo esc_url($course_image); ?>" alt="<?php echo esc_attr($course_title); ?>">
                        </div>
                      <?php else: ?>
                        <div class="seamless-user-dashboard-course-image seamless-user-dashboard-course-image-placeholder">
                          <svg width="60" height="60" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path d="M12 2L2 7L12 12L22 7L12 2Z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                            <path d="M2 17L12 22L22 17" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                            <path d="M2 12L12 17L22 12" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                          </svg>
                        </div>
                      <?php endif; ?>

                      <div class="seamless-user-dashboard-course-content">
                        <h4 class="seamless-user-dashboard-course-title"><?php echo esc_html($course_title); ?></h4>

                        <div class="seamless-user-dashboard-course-progress">
                          <span class="seamless-user-dashboard-course-lessons">
                            <?php echo esc_html($completed_lessons . '/' . $total_lessons); ?> <?php _e('lessons', 'seamless-addon'); ?>
                          </span>
                        </div>

                        <?php if (!$is_completed): ?>
                          <a href="<?php echo esc_url($course_url); ?>" target="_blank" class="seamless-user-dashboard-course-continue">
                            <?php _e('Start Course', 'seamless-addon'); ?>
                            <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" fill="none" viewBox="0 0 24 24">
                              <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 12H8m12 0-4 4m4-4-4-4M9 4H7a3 3 0 0 0-3 3v10a3 3 0 0 0 3 3h2" />
                            </svg>
                          </a>
                        <?php else: ?>
                          <span class="seamless-user-dashboard-course-completed">
                            <?php _e('Completed', 'seamless-addon'); ?>
                          </span>
                        <?php endif; ?>
                      </div>
                    </div>
                  <?php endforeach; ?>
                </div>
              <?php else: ?>
                <p class="seamless-user-dashboard-empty"><?php _e('No included courses from memberships at this time.', 'seamless-addon'); ?></p>
              <?php endif; ?>
            </div>
          </div>
        </div>
      </div>
    <?php endif; ?>

    <?php if ($show_orders): ?>
      <div class="seamless-user-dashboard-view" data-view="orders">
        <h3 class="seamless-user-dashboard-view-title"><?php _e('Order History', 'seamless-addon'); ?></h3>

        <?php if (!empty($orders)): ?>
          <div class="seamless-user-dashboard-orders-container" data-per-page="<?php echo esc_attr($orders_per_page); ?>" data-total-pages="<?php echo esc_attr($total_pages); ?>">
            <div class="seamless-user-dashboard-order-table-container">
              <table class="seamless-user-dashboard-order-table">
                <thead>
                  <tr>
                    <th class="seamless-user-dashboard-col-customer"><?php _e('Customer', 'seamless-addon'); ?></th>
                    <th class="seamless-user-dashboard-col-items"><?php _e('No. Of Products', 'seamless-addon'); ?></th>
                    <th class="seamless-user-dashboard-col-products"><?php _e('Ordered Products', 'seamless-addon'); ?></th>
                    <th class="seamless-user-dashboard-col-status"><?php _e('Status', 'seamless-addon'); ?></th>
                    <th class="seamless-user-dashboard-col-total"><?php _e('Total', 'seamless-addon'); ?></th>
                    <th class="seamless-user-dashboard-col-date"><?php _e('Date Added', 'seamless-addon'); ?></th>
                    <th class="seamless-user-dashboard-col-action"><?php _e('Invoice', 'seamless-addon'); ?></th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($orders as $index => $o):
                    // Customer name
                    $first = $o['billing_info']['first_name'] ?? ($o['customer']['first_name'] ?? ($o['user']['first_name'] ?? ($o['billing_first_name'] ?? '')));
                    $last = $o['billing_info']['last_name'] ?? ($o['customer']['last_name'] ?? ($o['user']['last_name'] ?? ($o['billing_last_name'] ?? '')));
                    $cust = trim(($first . ' ' . $last)) ?: ($o['customer']['name'] ?? ($o['user']['name'] ?? '—'));

                    // Items
                    $items = $o['items'] ?? ($o['products'] ?? ($o['order_items'] ?? ($o['lines'] ?? ($o['memberships'] ?? []))));
                    if (!is_array($items)) $items = [];

                    $names = [];
                    foreach ($items as $it) {
                      $names[] = $it['name'] ?? ($it['title'] ?? ($it['plan']['label'] ?? ($it['product']['name'] ?? 'Item')));
                    }
                    $product_list = implode(', ', array_map('esc_html', array_filter($names)));
                    $count_items = count($items);

                    // Status
                    $status = $o['status'] ?? ($o['order_status'] ?? '—');
                    $status_key = strtolower((string)$status);
                    $st_class = 'neutral';
                    if (in_array($status_key, ['completed', 'paid', 'successful', 'success'])) {
                      $st_class = 'success';
                    } elseif (in_array($status_key, ['pending', 'processing', 'on-hold'])) {
                      $st_class = 'warning';
                    } elseif (in_array($status_key, ['failed', 'cancelled', 'canceled', 'refunded'])) {
                      $st_class = 'danger';
                    }

                    // Total
                    $total_amount = $o['total_amount'] ?? ($o['total'] ?? ($o['amount'] ?? ($o['grand_total'] ?? '')));
                    $net_amount = $o['net_amount'] ?? '';
                    $refunded_amount = $o['refunded_amount'] ?? 0;
                    $has_refunds = !empty($o['has_refunds']);
                    $total_fmt = '—';
                    if ($total_amount !== '' || $net_amount !== '') {
                      $ta = is_numeric($total_amount) ? number_format((float)$total_amount, 2) : (string)$total_amount;
                      $na = $net_amount !== '' ? (is_numeric($net_amount) ? number_format((float)$net_amount, 2) : (string)$net_amount) : '';
                      if ($has_refunds || ((string)$na !== '' && (string)$na !== (string)$ta)) {
                        $ref = is_numeric($refunded_amount) ? number_format((float)$refunded_amount, 2) : (string)$refunded_amount;
                        $total_fmt = '<span class="seamless-user-dashboard-strike">$' . esc_html($ta) . '</span> <span class="seamless-user-dashboard-amount-green">$' . esc_html($na) . '</span>' . ($refunded_amount ? ' <span class="seamless-user-dashboard-refunded">(Refunded: $' . esc_html($ref) . ')</span>' : '');
                      } else {
                        $total_fmt = '<span class="seamless-user-dashboard-amount-green">$' . esc_html($ta) . '</span>';
                      }
                    }

                    // Date and invoice
                    $date = $o['date'] ?? ($o['created_at'] ?? ($o['purchased_at'] ?? ''));
                    $order_id = $o['id'] ?? ($o['order_id'] ?? '');
                    $invoice_url = !empty($client_domain) && $order_id !== '' ? trailingslashit($client_domain) . rawurlencode((string)$order_id) . '/pdf/download' : '';
                  ?>
                    <tr class="seamless-user-dashboard-order-row" data-order-index="<?php echo esc_attr($index); ?>">
                      <td class="seamless-user-dashboard-col-customer" data-label="<?php _e('Customer', 'seamless-addon'); ?>">
                        <?php echo esc_html($cust); ?>
                      </td>
                      <td class="seamless-user-dashboard-col-items" data-label="<?php _e('Items', 'seamless-addon'); ?>">
                        <span class="seamless-user-dashboard-count-badge"><?php echo esc_html($count_items); ?></span>
                      </td>
                      <td class="seamless-user-dashboard-col-products seamless-user-dashboard-ellipsis" data-label="<?php _e('Products', 'seamless-addon'); ?>" title="<?php echo esc_attr($product_list !== '' ? $product_list : '—'); ?>">
                        <?php echo $product_list !== '' ? $product_list : '—'; ?>
                      </td>
                      <td class="seamless-user-dashboard-col-status" data-label="<?php _e('Status', 'seamless-addon'); ?>">
                        <span class="seamless-user-dashboard-chip seamless-user-dashboard-chip-<?php echo esc_attr($st_class); ?>">
                          <?php echo esc_html(ucfirst((string)$status)); ?>
                        </span>
                      </td>
                      <td class="seamless-user-dashboard-col-total" data-label="<?php _e('Total', 'seamless-addon'); ?>">
                        <?php echo $total_fmt; ?>
                      </td>
                      <td class="seamless-user-dashboard-col-date" data-label="<?php _e('Date', 'seamless-addon'); ?>">
                        <span class="seamless-user-dashboard-muted"><?php echo esc_html((string)$date); ?></span>
                      </td>
                      <td class="seamless-user-dashboard-col-action" data-label="<?php _e('Action', 'seamless-addon'); ?>">
                        <?php if ($invoice_url): ?>
                          <a class="seamless-user-dashboard-btn seamless-user-dashboard-btn-invoice" href="<?php echo esc_url($invoice_url); ?>" target="_blank" rel="noopener">
                            <?php _e('Invoice', 'seamless-addon'); ?>
                          </a>
                        <?php else: ?>
                          —
                        <?php endif; ?>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>

            <?php if ($total_pages > 1): ?>
              <div class="seamless-user-dashboard-pagination">
                <button class="seamless-user-dashboard-pagination-btn seamless-user-dashboard-pagination-prev" disabled>
                  <svg width="20" height="20" viewBox="0 0 20 20" fill="none">
                    <path d="M12.5 15L7.5 10L12.5 5" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                  </svg>
                  <?php _e('Previous', 'seamless-addon'); ?>
                </button>
                <span class="seamless-user-dashboard-pagination-info">
                  <?php _e('Page', 'seamless-addon'); ?> <span class="seamless-user-dashboard-current-page">1</span> <?php _e('of', 'seamless-addon'); ?> <span class="seamless-user-dashboard-total-pages"><?php echo esc_html($total_pages); ?></span>
                </span>
                <button class="seamless-user-dashboard-pagination-btn seamless-user-dashboard-pagination-next">
                  <?php _e('Next', 'seamless-addon'); ?>
                  <svg width="20" height="20" viewBox="0 0 20 20" fill="none">
                    <path d="M7.5 15L12.5 10L7.5 5" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                  </svg>
                </button>
              </div>
            <?php endif; ?>
          </div>
        <?php else: ?>
          <p class="seamless-user-dashboard-empty"><?php _e('No orders found.', 'seamless-addon'); ?></p>
        <?php endif; ?>
      </div>
    <?php endif; ?>

    <!-- Profile View -->
    <?php if ($show_profile): ?>
      <div class="seamless-user-dashboard-view active" data-view="profile">
        <?php
        $pf_phone = $profile['phone'] ?? '';
        $pf_phone_type = $profile['phone_type'] ?? '';
        $pf_address_1 = $profile['address_line_1'] ?? '';
        $pf_address_2 = $profile['address_line_2'] ?? '';
        $pf_city = $profile['city'] ?? '';
        $pf_state = $profile['state'] ?? '';
        $pf_country = $profile['country'] ?? '';
        $pf_zip = $profile['zip_code'] ?? '';
        ?>

        <div class="seamless-user-dashboard-profile-container">
          <div class="seamless-user-dashboard-profile-section">
            <div class="seamless-user-dashboard-profile-header">
              <h3 class="seamless-user-dashboard-section-title"><?php _e('Personal Information', 'seamless-addon'); ?></h3>
              <button class="seamless-user-dashboard-btn seamless-user-dashboard-btn-edit" data-email="<?php echo esc_attr($pf_email); ?>">
                <?php _e('EDIT', 'seamless-addon'); ?>
              </button>
            </div>

            <div class="seamless-user-dashboard-profile-view-mode">
              <div class="seamless-user-dashboard-profile-grid">
                <div class="seamless-user-dashboard-profile-field">
                  <label><?php _e('First Name', 'seamless-addon'); ?></label>
                  <div class="seamless-user-dashboard-profile-value"><?php echo esc_html($pf_first ?: '—'); ?></div>
                </div>
                <div class="seamless-user-dashboard-profile-field">
                  <label><?php _e('Last Name', 'seamless-addon'); ?></label>
                  <div class="seamless-user-dashboard-profile-value"><?php echo esc_html($pf_last ?: '—'); ?></div>
                </div>
                <div class="seamless-user-dashboard-profile-field">
                  <label><?php _e('Email Address', 'seamless-addon'); ?></label>
                  <div class="seamless-user-dashboard-profile-value"><?php echo esc_html($pf_email ?: '—'); ?></div>
                </div>
              </div>
              <div class="seamless-user-dashboard-profile-grid">
                <div class="seamless-user-dashboard-profile-field">
                  <label><?php _e('Phone Number', 'seamless-addon'); ?></label>
                  <div class="seamless-user-dashboard-profile-value">
                    <?php
                    $phone_display = $pf_phone ?: '—';
                    if ($pf_phone && $pf_phone_type) {
                      $phone_display .= ' (' . ucfirst($pf_phone_type) . ')';
                    }
                    echo esc_html($phone_display);
                    ?>
                  </div>
                </div>
              </div>

              <div class="seamless-user-dashboard-profile-separator">
                <h4 class="seamless-user-dashboard-subsection-title"><?php _e('Address Information', 'seamless-addon'); ?></h4>
              </div>

              <div class="seamless-user-dashboard-profile-grid">
                <div class="seamless-user-dashboard-profile-field">
                  <label><?php _e('Address Line 1', 'seamless-addon'); ?></label>
                  <div class="seamless-user-dashboard-profile-value"><?php echo esc_html($pf_address_1 ?: '—'); ?></div>
                </div>
                <div class="seamless-user-dashboard-profile-field">
                  <label><?php _e('Address Line 2', 'seamless-addon'); ?></label>
                  <div class="seamless-user-dashboard-profile-value"><?php echo esc_html($pf_address_2 ?: '—'); ?></div>
                </div>
              </div>
              <div class="seamless-user-dashboard-profile-grid">
                <div class="seamless-user-dashboard-profile-field">
                  <label><?php _e('City', 'seamless-addon'); ?></label>
                  <div class="seamless-user-dashboard-profile-value"><?php echo esc_html($pf_city ?: '—'); ?></div>
                </div>
                <div class="seamless-user-dashboard-profile-field">
                  <label><?php _e('State', 'seamless-addon'); ?></label>
                  <div class="seamless-user-dashboard-profile-value"><?php echo esc_html($pf_state ?: '—'); ?></div>
                </div>
              </div>
              <div class="seamless-user-dashboard-profile-grid">
                <div class="seamless-user-dashboard-profile-field">
                  <label><?php _e('Zip Code', 'seamless-addon'); ?></label>
                  <div class="seamless-user-dashboard-profile-value"><?php echo esc_html($pf_zip ?: '—'); ?></div>
                </div>
                <div class="seamless-user-dashboard-profile-field">
                  <label><?php _e('Country', 'seamless-addon'); ?></label>
                  <div class="seamless-user-dashboard-profile-value"><?php echo esc_html($pf_country ?: '—'); ?></div>
                </div>
              </div>
            </div>

            <div class="seamless-user-dashboard-profile-edit-mode" style="display: none;">
              <form id="seamless-user-dashboard-form-<?php echo esc_attr($widget_id); ?>" class="seamless-user-dashboard-edit-profile-form">
                <div class="seamless-user-dashboard-profile-grid">
                  <div class="seamless-user-dashboard-form-group">
                    <label for="seamless-user-dashboard-first-name-<?php echo esc_attr($widget_id); ?>">
                      <?php _e('First Name', 'seamless-addon'); ?> <span class="required">*</span>
                    </label>
                    <input type="text" id="seamless-user-dashboard-first-name-<?php echo esc_attr($widget_id); ?>" name="first_name" value="<?php echo esc_attr($pf_first); ?>" required>
                  </div>
                  <div class="seamless-user-dashboard-form-group">
                    <label for="seamless-user-dashboard-last-name-<?php echo esc_attr($widget_id); ?>">
                      <?php _e('Last Name', 'seamless-addon'); ?> <span class="required">*</span>
                    </label>
                    <input type="text" id="seamless-user-dashboard-last-name-<?php echo esc_attr($widget_id); ?>" name="last_name" value="<?php echo esc_attr($pf_last); ?>" required>
                  </div>
                  <div class="seamless-user-dashboard-form-group">
                    <label for="seamless-user-dashboard-email-<?php echo esc_attr($widget_id); ?>">
                      <?php _e('Email Address', 'seamless-addon'); ?> <span class="required">*</span>
                    </label>
                    <input type="email" id="seamless-user-dashboard-email-<?php echo esc_attr($widget_id); ?>" name="email" value="<?php echo esc_attr($pf_email); ?>" required>
                  </div>
                </div>
                <div class="seamless-user-dashboard-profile-grid">
                  <div class="seamless-user-dashboard-form-group">
                    <label for="seamless-user-dashboard-phone-<?php echo esc_attr($widget_id); ?>">
                      <?php _e('Phone Number', 'seamless-addon'); ?>
                    </label>
                    <input type="tel" id="seamless-user-dashboard-phone-<?php echo esc_attr($widget_id); ?>" name="phone" value="<?php echo esc_attr($pf_phone); ?>">
                  </div>
                  <div class="seamless-user-dashboard-form-group">
                    <label for="seamless-user-dashboard-phone-type-<?php echo esc_attr($widget_id); ?>">
                      <?php _e('Phone Type', 'seamless-addon'); ?>
                    </label>
                    <select id="seamless-user-dashboard-phone-type-<?php echo esc_attr($widget_id); ?>" name="phone_type">
                      <option value=""><?php _e('Select Type', 'seamless-addon'); ?></option>
                      <option value="home" <?php selected($pf_phone_type, 'Home'); ?>><?php _e('Home', 'seamless-addon'); ?></option>
                      <option value="mobile" <?php selected($pf_phone_type, 'Mobile'); ?>><?php _e('Mobile', 'seamless-addon'); ?></option>
                      <option value="work" <?php selected($pf_phone_type, 'Work'); ?>><?php _e('Work', 'seamless-addon'); ?></option>
                    </select>
                  </div>
                </div>

                <div class="seamless-user-dashboard-profile-separator">
                  <h4 class="seamless-user-dashboard-subsection-title"><?php _e('Address Information', 'seamless-addon'); ?></h4>
                </div>

                <div class="seamless-user-dashboard-profile-grid">
                  <div class="seamless-user-dashboard-form-group">
                    <label for="seamless-user-dashboard-address-1-<?php echo esc_attr($widget_id); ?>">
                      <?php _e('Address Line 1', 'seamless-addon'); ?>
                    </label>
                    <input type="text" id="seamless-user-dashboard-address-1-<?php echo esc_attr($widget_id); ?>" name="address_line_1" value="<?php echo esc_attr($pf_address_1); ?>">
                  </div>
                  <div class="seamless-user-dashboard-form-group">
                    <label for="seamless-user-dashboard-address-2-<?php echo esc_attr($widget_id); ?>">
                      <?php _e('Address Line 2', 'seamless-addon'); ?>
                    </label>
                    <input type="text" id="seamless-user-dashboard-address-2-<?php echo esc_attr($widget_id); ?>" name="address_line_2" value="<?php echo esc_attr($pf_address_2); ?>">
                  </div>
                  <div class="seamless-user-dashboard-form-group">
                    <label for="seamless-user-dashboard-city-<?php echo esc_attr($widget_id); ?>">
                      <?php _e('City', 'seamless-addon'); ?>
                    </label>
                    <input type="text" id="seamless-user-dashboard-city-<?php echo esc_attr($widget_id); ?>" name="city" value="<?php echo esc_attr($pf_city); ?>">
                  </div>
                </div>
                <div class="seamless-user-dashboard-profile-grid">
                  <div class="seamless-user-dashboard-form-group">
                    <label for="seamless-user-dashboard-state-<?php echo esc_attr($widget_id); ?>">
                      <?php _e('State', 'seamless-addon'); ?>
                    </label>
                    <input type="text" id="seamless-user-dashboard-state-<?php echo esc_attr($widget_id); ?>" name="state" value="<?php echo esc_attr($pf_state); ?>">
                  </div>
                  <div class="seamless-user-dashboard-form-group">
                    <label for="seamless-user-dashboard-zip-<?php echo esc_attr($widget_id); ?>">
                      <?php _e('Zip Code', 'seamless-addon'); ?>
                    </label>
                    <input type="text" id="seamless-user-dashboard-zip-<?php echo esc_attr($widget_id); ?>" name="zip_code" value="<?php echo esc_attr($pf_zip); ?>">
                  </div>
                  <div class="seamless-user-dashboard-form-group">
                    <label for="seamless-user-dashboard-country-<?php echo esc_attr($widget_id); ?>">
                      <?php _e('Country', 'seamless-addon'); ?>
                    </label>
                    <input type="text" id="seamless-user-dashboard-country-<?php echo esc_attr($widget_id); ?>" name="country" value="<?php echo esc_attr($pf_country); ?>">
                  </div>
                </div>

                <input type="hidden" name="widget_id" value="<?php echo esc_attr($widget_id); ?>">
                <div class="seamless-user-dashboard-form-message"></div>
                <div class="seamless-user-dashboard-profile-actions">
                  <button type="button" class="seamless-user-dashboard-btn seamless-user-dashboard-btn-cancel">
                    <?php _e('Cancel', 'seamless-addon'); ?>
                  </button>
                  <button type="submit" class="seamless-user-dashboard-btn seamless-user-dashboard-btn-save">
                    <?php _e('Save Changes', 'seamless-addon'); ?>
                  </button>
                </div>
              </form>
            </div>
          </div>
        </div>
      </div>
    <?php endif; ?>
  </main>
</div>

<div class="seamless-user-dashboard-upgrade-modal" id="seamless-upgrade-modal" style="display: none;">
  <div class="seamless-user-dashboard-modal-overlay"></div>
  <div class="seamless-user-dashboard-modal-container">
    <div class="seamless-user-dashboard-modal-header">
      <h3 class="seamless-user-dashboard-modal-title"></h3>
      <button class="seamless-user-dashboard-modal-close" aria-label="Close">&times;</button>
    </div>

    <div class="seamless-user-dashboard-modal-body">
      <!-- Scheduled Downgrade Info (shown when proration disabled) -->
      <div class="seamless-user-dashboard-scheduled-info" id="seamless-scheduled-info" style="display: none;">
        <div class="seamless-user-dashboard-info-message">
          <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <circle cx="12" cy="12" r="10"></circle>
            <line x1="12" y1="16" x2="12" y2="12"></line>
            <line x1="12" y1="8" x2="12.01" y2="8"></line>
          </svg>
          <div>
            <p id="seamless-scheduled-message"></p>
          </div>
        </div>
      </div>
      <div class="seamless-user-dashboard-modal-columns">
        <div class="seamless-user-dashboard-modal-left">
          <h3><?php _e('Available Plans', 'seamless-addon'); ?></h3>
          <div class="seamless-user-dashboard-plans-list" id="seamless-plans-list">
          </div>
          <div class="seamless-user-dashboard-proration" id="seamless-proration" style="display: none;">
            <h4><?php _e('Pricing Breakdown', 'seamless-addon'); ?></h4>
            <div class="seamless-user-dashboard-proration-item">
              <span><?php _e('New Plan Charge:', 'seamless-addon'); ?></span>
              <span class="seamless-user-dashboard-proration-charge">$0.00</span>
            </div>
            <div class="seamless-user-dashboard-proration-item">
              <span><?php _e('Current Plan Credit:', 'seamless-addon'); ?></span>
              <span class="seamless-user-dashboard-proration-credit">$0.00</span>
            </div>
            <div class="seamless-user-dashboard-proration-item seamless-user-dashboard-proration-total">
              <span><?php _e('Amount to Pay:', 'seamless-addon'); ?></span>
              <span class="seamless-user-dashboard-proration-amount">$0.00</span>
            </div>
            <p class="seamless-user-dashboard-proration-note">
              <?php _e('Prorated for', 'seamless-addon'); ?> <span class="seamless-user-dashboard-remaining-days">0</span> <?php _e('remaining days', 'seamless-addon'); ?>
            </p>
          </div>
        </div>

        <div class="seamless-user-dashboard-modal-right">
          <h3 class="seamless-user-dashboard-selected-plan-name"><?php _e('Select a plan', 'seamless-addon'); ?></h3>
          <div class="seamless-user-dashboard-plan-perks" id="seamless-plan-perks">
            <p class="seamless-user-dashboard-empty-perks"><?php _e('Select a plan to view its features', 'seamless-addon'); ?></p>
          </div>
        </div>
      </div>
    </div>

    <div class="seamless-user-dashboard-modal-footer">
      <div class="seamless-user-dashboard-modal-actions">
        <button class="seamless-user-dashboard-btn seamless-user-dashboard-btn-cancel seamless-user-dashboard-modal-cancel">
          <?php _e('Cancel', 'seamless-addon'); ?>
        </button>
        <button class="seamless-user-dashboard-btn seamless-user-dashboard-btn-save seamless-user-dashboard-modal-upgrade" disabled>
          <span class="seamless-user-dashboard-modal-upgrade-text"><?php _e('Upgrade Plan', 'seamless-addon'); ?></span>
          <!-- <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" class="sparkle">
            <path class="path" stroke-linejoin="round" stroke-linecap="round" stroke="currentColor" fill="currentColor" d="M14.187 8.096L15 5.25L15.813 8.096C16.0231 8.83114 16.4171 9.50062 16.9577 10.0413C17.4984 10.5819 18.1679 10.9759 18.903 11.186L21.75 12L18.904 12.813C18.1689 13.0231 17.4994 13.4171 16.9587 13.9577C16.4181 14.4984 16.0241 15.1679 15.814 15.903L15 18.75L14.187 15.904C13.9769 15.1689 13.5829 14.4994 13.0423 13.9587C12.5016 13.4181 11.8321 13.0241 11.097 12.814L8.25 12L11.096 11.187C11.8311 10.9769 12.5006 10.5829 13.0413 10.0423C13.5819 9.50162 13.9759 8.83214 14.186 8.097L14.187 8.096Z"></path>
            <path class="path" stroke-linejoin="round" stroke-linecap="round" stroke="currentColor" fill="currentColor" d="M6 14.25L5.741 15.285C5.59267 15.8785 5.28579 16.4206 4.85319 16.8532C4.42059 17.2858 3.87853 17.5927 3.285 17.741L2.25 18L3.285 18.259C3.87853 18.4073 4.42059 18.7142 4.85319 19.1468C5.28579 19.5794 5.59267 20.1215 5.741 20.715L6 21.75L6.259 20.715C6.40725 20.1216 6.71398 19.5796 7.14639 19.147C7.5788 18.7144 8.12065 18.4075 8.714 18.259L9.75 18L8.714 17.741C8.12065 17.5925 7.5788 17.2856 7.14639 16.853C6.71398 16.4204 6.40725 15.8784 6.259 15.285L6 14.25Z"></path>
            <path class="path" stroke-linejoin="round" stroke-linecap="round" stroke="currentColor" fill="currentColor" d="M6.5 4L6.303 4.5915C6.24777 4.75718 6.15472 4.90774 6.03123 5.03123C5.90774 5.15472 5.75718 5.24777 5.5915 5.303L5 5.5L5.5915 5.697C5.75718 5.75223 5.90774 5.84528 6.03123 5.96877C6.15472 6.09226 6.24777 6.24282 6.303 6.4085L6.5 7L6.697 6.4085C6.75223 6.24282 6.84528 6.09226 6.96877 5.96877C7.09226 5.84528 7.24282 5.75223 7.4085 5.697L8 5.5L7.4085 5.303C7.24282 5.24777 7.09226 5.15472 6.96877 5.03123C6.84528 4.90774 6.75223 4.75718 6.697 4.5915L6.5 4Z"></path>
          </svg> -->
        </button>
      </div>
    </div>
  </div>
</div>

<!-- Cancel Membership Modal -->
<div class="seamless-user-dashboard-cancel-modal" id="seamless-cancel-modal" style="display: none;">
  <div class="seamless-user-dashboard-modal-overlay"></div>
  <div class="seamless-user-dashboard-modal-container">
    <div class="seamless-user-dashboard-modal-header">
      <h3><?php _e('Cancel Your Membership', 'seamless-addon'); ?></h3>
    </div>

    <div class="seamless-user-dashboard-modal-body">
      <div class="seamless-user-dashboard-cancel-content">
        <p class="seamless-user-dashboard-cancel-question">
          <?php _e('Are you sure you want to cancel your', 'seamless-addon'); ?>
          <strong id="seamless-cancel-plan-name"></strong> <?php _e('membership?', 'seamless-addon'); ?>
        </p>

        <!-- Refund Section (shown when refundable = true) -->
        <div id="seamless-cancel-refund-section">
          <div class="seamless-user-dashboard-cancel-policy">
            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <circle cx="12" cy="12" r="10"></circle>
              <line x1="12" y1="16" x2="12" y2="12"></line>
              <line x1="12" y1="8" x2="12.01" y2="8"></line>
            </svg>
            <div>
              <h4><?php _e('Refund Policy:', 'seamless-addon'); ?></h4>
              <p id="seamless-cancel-proration-message"><?php _e('Full refund available as per plan policy.', 'seamless-addon'); ?></p>
            </div>
          </div>

          <!-- Warnings for refundable plans -->
          <div class="seamless-user-dashboard-cancel-warnings">
            <div class="seamless-user-dashboard-cancel-warning">
              <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <circle cx="12" cy="12" r="10"></circle>
                <line x1="12" y1="8" x2="12" y2="12"></line>
                <line x1="12" y1="16" x2="12.01" y2="16"></line>
              </svg>
              <span><?php _e('You will lose access to all features and content.', 'seamless-addon'); ?></span>
            </div>
            <div class="seamless-user-dashboard-cancel-warning">
              <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <circle cx="12" cy="12" r="10"></circle>
                <line x1="12" y1="8" x2="12" y2="12"></line>
                <line x1="12" y1="16" x2="12.01" y2="16"></line>
              </svg>
              <span><?php _e('This action cannot be undone.', 'seamless-addon'); ?></span>
            </div>
          </div>
        </div>

        <!-- Period End Section (shown when refundable = false) -->
        <div id="seamless-cancel-period-end-section" style="display: none;">
          <div class="seamless-user-dashboard-cancel-no-refund">
            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <circle cx="12" cy="12" r="10"></circle>
              <line x1="12" y1="16" x2="12" y2="12"></line>
              <line x1="12" y1="8" x2="12.01" y2="8"></line>
            </svg>
            <p><?php _e('No refund is available for this cancellation based on your plan\'s policy.', 'seamless-addon'); ?></p>
          </div>

          <div class="seamless-user-dashboard-cancel-period-end">
            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <circle cx="12" cy="12" r="10"></circle>
              <line x1="12" y1="16" x2="12" y2="12"></line>
              <line x1="12" y1="8" x2="12.01" y2="8"></line>
            </svg>
            <p id="seamless-cancel-period-end-message"></p>
          </div>
        </div>

      </div>
    </div>
    <div class="seamless-user-dashboard-modal-footer">
      <div class="seamless-user-dashboard-modal-actions">
        <button class="seamless-user-dashboard-btn seamless-user-dashboard-btn-secondary seamless-user-dashboard-modal-keep">
          <?php _e('Keep Membership', 'seamless-addon'); ?>
        </button>
        <button class="seamless-user-dashboard-btn seamless-user-dashboard-btn-danger seamless-user-dashboard-modal-confirm-cancel">
          <span class="seamless-user-dashboard-modal-confirm-cancel-text"><?php _e('Request Refund', 'seamless-addon'); ?></span>
        </button>
      </div>
    </div>
  </div>
</div>

<!-- Cancel Scheduled Change Modal -->
<div class="seamless-user-dashboard-cancel-modal" id="seamless-cancel-scheduled-modal" style="display: none;">
  <div class="seamless-user-dashboard-modal-overlay"></div>
  <div class="seamless-user-dashboard-modal-container">
    <div class="seamless-user-dashboard-modal-header">
      <h3><?php _e('Cancel Scheduled Change', 'seamless-addon'); ?></h3>
    </div>

    <div class="seamless-user-dashboard-modal-body">
      <div class="seamless-user-dashboard-modal-content">
        <div class="seamless-user-dashboard-modal-warning">
          <svg xmlns="http://www.w3.org/2000/svg" width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"></path>
            <line x1="12" y1="9" x2="12" y2="13"></line>
            <line x1="12" y1="17" x2="12.01" y2="17"></line>
          </svg>
          <p><?php _e('Are you sure you want to cancel this scheduled plan change? This action cannot be undone. Your current membership will continue without any changes.', 'seamless-addon'); ?></p>
        </div>
      </div>
    </div>

    <div class="seamless-user-dashboard-modal-footer">
      <div class="seamless-user-dashboard-modal-actions">
        <button class="seamless-user-dashboard-btn seamless-user-dashboard-btn-secondary seamless-user-dashboard-modal-keep-scheduled">
          <?php _e('Keep Schedule', 'seamless-addon'); ?>
        </button>
        <button class="seamless-user-dashboard-btn seamless-user-dashboard-btn-danger seamless-user-dashboard-modal-confirm-cancel-scheduled">
          <span class="seamless-user-dashboard-modal-confirm-cancel-scheduled-text"><?php _e('Yes, Cancel Change', 'seamless-addon'); ?></span>
        </button>
      </div>
    </div>
  </div>
</div>

<script type="text/javascript">
  var seamlessUserDashboard = {
    ajaxUrl: '<?php echo esc_url(admin_url('admin-ajax.php')); ?>',
    upgradeNonce: '<?php echo wp_create_nonce('seamless_upgrade_membership'); ?>',
    downgradeNonce: '<?php echo wp_create_nonce('seamless_downgrade_membership'); ?>',
    cancelNonce: '<?php echo wp_create_nonce('seamless_cancel_membership'); ?>',
    cancelScheduledNonce: '<?php echo wp_create_nonce('seamless_cancel_scheduled_change'); ?>',
    profileNonce: '<?php echo wp_create_nonce('seamless_update_profile'); ?>',
    userEmail: '<?php echo esc_js($pf_email); ?>',
    memberships: {
      current: <?php echo json_encode($current_memberships); ?>
    }
  };
</script>