<?php

/**
 * User Dashboard Widget Template (Shell)
 * 
 * Variables available:
 * @var array $settings Widget settings
 * @var string $widget_id Widget ID
 */

if (!defined('ABSPATH')) {
  exit;
}

$show_membership = $settings['show_membership_tab'] === 'yes';
$show_orders = $settings['show_orders_tab'] === 'yes';
$show_courses = $settings['show_courses_tab'] === 'yes';
$show_profile = $settings['show_profile_tab'] === 'yes';

// Get current user basic info for the sidebar card (fast, no API call needed if possible)
$current_user = wp_get_current_user();
$pf_name = $current_user->first_name . ' ' . $current_user->last_name;
$pf_email = $current_user->user_email;

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
      <?php if ($show_membership): ?>
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

    <!-- Profile View -->
    <?php if ($show_profile): ?>
      <div class="seamless-user-dashboard-view active" data-view="profile">
        <div id="seamless-dashboard-profile-container" class="seamless-dashboard-content-container">
          <div class="seamless-dashboard-loader">
            <div class="seamless-spinner"></div>
            <p><?php _e('Loading profile...', 'seamless-addon'); ?></p>
          </div>
        </div>
      </div>
    <?php endif; ?>

    <!-- Memberships View -->
    <?php if ($show_membership): ?>
      <div class="seamless-user-dashboard-view" data-view="memberships">
        <div id="seamless-dashboard-memberships-container" class="seamless-dashboard-content-container">
          <div class="seamless-dashboard-loader">
            <div class="seamless-spinner"></div>
            <p><?php _e('Loading memberships...', 'seamless-addon'); ?></p>
          </div>
        </div>
      </div>
    <?php endif; ?>

    <!-- Courses View -->
    <?php if ($show_courses): ?>
      <div class="seamless-user-dashboard-view" data-view="courses">
        <div id="seamless-dashboard-courses-container" class="seamless-dashboard-content-container">
          <div class="seamless-dashboard-loader">
            <div class="seamless-spinner"></div>
            <p><?php _e('Loading courses...', 'seamless-addon'); ?></p>
          </div>
        </div>
      </div>
    <?php endif; ?>

    <!-- Orders View -->
    <?php if ($show_orders): ?>
      <div class="seamless-user-dashboard-view" data-view="orders">
        <div id="seamless-dashboard-orders-container" class="seamless-dashboard-content-container">
          <div class="seamless-dashboard-loader">
            <div class="seamless-spinner"></div>
            <p><?php _e('Loading orders...', 'seamless-addon'); ?></p>
          </div>
        </div>
      </div>
    <?php endif; ?>

  </main>
</div>

<script type="text/javascript">
  var seamlessUserDashboard = {
    ajaxUrl: '<?php echo esc_url(admin_url('admin-ajax.php')); ?>',
    upgradeNonce: '<?php echo wp_create_nonce('seamless_upgrade_membership'); ?>',
    downgradeNonce: '<?php echo wp_create_nonce('seamless_downgrade_membership'); ?>',
    cancelNonce: '<?php echo wp_create_nonce('seamless_cancel_membership'); ?>',
    renewNonce: '<?php echo wp_create_nonce('seamless_renew_membership'); ?>',
    cancelScheduledNonce: '<?php echo wp_create_nonce('seamless_cancel_scheduled_change'); ?>',
    profileNonce: '<?php echo wp_create_nonce('seamless_update_profile'); ?>',
    userEmail: '<?php echo esc_js($pf_email); ?>',
    ordersPerPage: <?php echo (int)($settings['orders_per_page'] ?? 6); ?>,
    memberships: {
      current: []
    }
  };
</script>
<script type="text/javascript">
  (function() {
    try {
      // Immediate tab restoration to prevent flash of wrong tab
      var widgetId = document.querySelector('.seamless-user-dashboard') ? document.querySelector('.seamless-user-dashboard').getAttribute('data-widget-id') : '';
      var activeView = sessionStorage.getItem('seamless-user-dashboard-active-view-' + widgetId);

      if (activeView) {
        // Remove defaults
        var defaultActiveNav = document.querySelectorAll('.seamless-user-dashboard-nav-item.active');
        for (var i = 0; i < defaultActiveNav.length; i++) {
          defaultActiveNav[i].classList.remove('active');
        }
        var defaultActiveView = document.querySelectorAll('.seamless-user-dashboard-view.active');
        for (var i = 0; i < defaultActiveView.length; i++) {
          defaultActiveView[i].classList.remove('active');
        }

        // Activate saved
        var targetNav = document.querySelector('.seamless-user-dashboard-nav-item[data-view="' + activeView + '"]');
        if (targetNav) targetNav.classList.add('active');

        var targetView = document.querySelector('.seamless-user-dashboard-view[data-view="' + activeView + '"]');
        if (targetView) targetView.classList.add('active');
      }
    } catch (e) {
      console.error('Tab restore error:', e);
    }
  })();
</script>

<style>
  /* Simple Loader Styles */
  .seamless-dashboard-loader {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    padding: 40px;
    color: #666;
  }

  .seamless-spinner {
    border: 3px solid rgba(0, 0, 0, 0.1);
    border-radius: 50%;
    border-top: 3px solid #6c5ce7;
    width: 30px;
    height: 30px;
    -webkit-animation: seamless-spin 1s linear infinite;
    /* Safari */
    animation: seamless-spin 1s linear infinite;
    margin-bottom: 10px;
  }

  @keyframes seamless-spin {
    0% {
      transform: rotate(0deg);
    }

    100% {
      transform: rotate(360deg);
    }
  }
</style>