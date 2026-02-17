<?php

/**
 * Template Part: Dashboard Memberships
 */

$active_count = count($current_memberships);
$expired_count = count($membership_history);

// Helper function to format dates
if (!function_exists('seamless_format_membership_date')) {
    function seamless_format_membership_date($date_string)
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
}

// Get the latest active membership for the current membership card
// Priority: 1) Upgraded membership (nearest to expiry), 2) Most recently purchased
$current_membership = null;
if (!empty($current_memberships)) {
    // First, try to find upgraded memberships
    $upgraded_memberships = array_filter($current_memberships, function ($m) {
        return !empty($m['plan']['upgraded_from_id']);
    });

    if (!empty($upgraded_memberships)) {
        // Sort upgraded memberships by expiry date (nearest to expiry first)
        usort($upgraded_memberships, function ($a, $b) {
            $expiry_a = $a['expiry_date'] ?? $a['expires_at'] ?? '';
            $expiry_b = $b['expiry_date'] ?? $b['expires_at'] ?? '';
            return strcmp($expiry_a, $expiry_b);
        });
        $current_membership = $upgraded_memberships[0];
    } else {
        // No upgraded memberships, sort by start_date descending to get the most recent
        usort($current_memberships, function ($a, $b) {
            $date_a = $a['start_date'] ?? $a['started_at'] ?? $a['created_at'] ?? '';
            $date_b = $b['start_date'] ?? $b['started_at'] ?? $b['created_at'] ?? '';
            return strcmp($date_b, $date_a);
        });
        $current_membership = $current_memberships[0];
    }
}
?>

<div class="seamless-user-dashboard-summary-grid">
    <div class="seamless-user-dashboard-summary-card">
        <div class="seamless-user-dashboard-summary-value"><?php echo esc_html($active_count); ?></div>
        <div class="seamless-user-dashboard-summary-label"><?php _e('Total Active Memberships', 'seamless-addon'); ?></div>
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
            <?php if ($is_upgraded): ?>
                <div class="seamless-upgraded-badge-container">
                    <div class="seamless-upgraded-badge">
                        <span class="seamless-badge-sparkle seamless-sparkle-1"></span>
                        <span class="seamless-badge-sparkle seamless-sparkle-2"></span>
                        <span class="seamless-badge-sparkle seamless-sparkle-3"></span>
                        <span class="seamless-badge-sparkle seamless-sparkle-4"></span>
                        <span class="seamless-badge-sparkle seamless-sparkle-5"></span>
                        <span class="seamless-badge-text">Upgraded</span>
                    </div>
                </div>
                <div class="seamless-user-dashboard-badge">Active</div>
            <?php endif; ?>
            <div class="seamless-user-dashboard-current-membership-header">
                <h3><?php echo esc_html($plan_label); ?></h3>
                <?php if (!$is_upgraded): ?>
                    <span class="seamless-user-dashboard-badge seamless-user-dashboard-badge-<?php echo esc_attr(strtolower($status)); ?>">
                        <?php echo esc_html(ucfirst($status)); ?>
                    </span>
                <?php endif; ?>
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
                            <span><?php printf(__('Your membership will end on %s', 'seamless-addon'), '<strong>' . esc_html(seamless_format_membership_date($expiry_date)) . '</strong>'); ?></span>
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
                                <span class="seamless-user-dashboard-scheduled-date"><?php printf(__('Effective on %s', 'seamless-addon'), '<strong>' . esc_html(seamless_format_membership_date($effective_date)) . '</strong>'); ?></span>
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
                        <strong><?php echo esc_html(seamless_format_membership_date($expiry_date)); ?></strong>
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
            <?php if (!empty($current_memberships)):
                $memberships_per_page = 5;
                $total_active = count($current_memberships);
                $total_active_pages = $total_active > 0 ? ceil($total_active / $memberships_per_page) : 1;
            ?>
                <div class="seamless-user-dashboard-memberships-container" data-per-page="<?php echo esc_attr($memberships_per_page); ?>" data-total-pages="<?php echo esc_attr($total_active_pages); ?>">
                    <div class="seamless-user-dashboard-membership-list">
                        <?php foreach ($current_memberships as $index => $m):
                            $label = $m['plan']['label'] ?? ($m['label'] ?? ($m['name'] ?? 'Membership'));
                            $purchased = $m['start_date'] ?? ($m['started_at'] ?? ($m['created_at'] ?? ''));
                            $expiry = $m['expiry_date'] ?? ($m['expires_at'] ?? '');
                        ?>
                            <div class="seamless-user-dashboard-membership-card" data-membership-index="<?php echo esc_attr($index); ?>">
                                <div class="seamless-user-dashboard-membership-content">
                                    <h3 class="seamless-user-dashboard-membership-title"><?php echo esc_html($label); ?></h3>
                                    <div class="seamless-user-dashboard-membership-actions">
                                        <?php
                                        // Check for upgrade/downgrade options at membership level
                                        $has_upgrades = !empty($m['upgradable_to']) && is_array($m['upgradable_to']) && count($m['upgradable_to']) > 0;
                                        $has_downgrades = !empty($m['downgradable_to']) && is_array($m['downgradable_to']) && count($m['downgradable_to']) > 0;
                                        $is_cancelled = isset($m['status']) && $m['status'] === 'cancelled';
                                        $is_active = $m['status'] === 'active';
                                        $is_upgraded_membership = !empty($m['plan']['upgraded_from_id']);
                                        $is_downgraded_membership = !empty($m['plan']['downgraded_from_id']);

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
                                                            <svg class="h-5 w-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true" data-slot="icon">
                                                                <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 18 9 11.25l4.306 4.306a11.95 11.95 0 0 1 5.814-5.518l2.74-1.22m0 0-5.94-2.281m5.94 2.28-2.28 5.941"></path>
                                                            </svg>
                                                            <?php _e('Upgrade', 'seamless-addon'); ?>
                                                        </button>
                                                    <?php endif; ?>

                                                    <?php if ($has_downgrades): ?>
                                                        <button class="seamless-user-dashboard-menu-item seamless-user-dashboard-badge-downgrade"
                                                            data-membership-id="<?php echo esc_attr($m['id']); ?>"
                                                            data-action-type="downgrade"
                                                            data-membership-data="<?php echo esc_attr(json_encode($m)); ?>">
                                                            <svg class="h-5 w-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true" data-slot="icon">
                                                                <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 6 9 12.75l4.286-4.286a11.948 11.948 0 0 1 4.306 6.43l.776 2.898m0 0 3.182-5.511m-3.182 5.51-5.511-3.181"></path>
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
                                        <?php elseif ($is_downgraded_membership): ?>
                                            <span class="seamless-user-dashboard-badge seamless-user-dashboard-badge-downgraded"><?php _e('Downgraded', 'seamless-addon'); ?></span>
                                        <?php elseif ($is_upgraded_membership): ?>
                                            <span class="seamless-user-dashboard-badge seamless-user-dashboard-badge-upgraded"><?php _e('Upgraded', 'seamless-addon'); ?></span>
                                        <?php elseif (!$has_upgrades && !$has_downgrades && !$is_active): ?>
                                            <span class="seamless-user-dashboard-badge seamless-user-dashboard-badge-active"><?php _e('Active', 'seamless-addon'); ?></span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="seamless-user-dashboard-membership-body">
                                        <?php if (!empty($purchased)): ?>
                                            <div class="seamless-user-dashboard-membership-meta">
                                                <strong><?php _e('Purchased:', 'seamless-addon'); ?></strong>
                                                <span><?php echo esc_html(seamless_format_membership_date($purchased)); ?></span>
                                            </div>
                                        <?php endif; ?>
                                        <?php if (!empty($expiry)): ?>
                                            <div class="seamless-user-dashboard-membership-meta">
                                                <strong><?php _e('Expires:', 'seamless-addon'); ?></strong>
                                                <span><?php echo esc_html(seamless_format_membership_date($expiry)); ?></span>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <?php if ($total_active_pages > 1): ?>
                        <div class="seamless-user-dashboard-pagination">
                            <button class="seamless-user-dashboard-pagination-btn seamless-user-dashboard-pagination-prev" disabled>
                                <svg width="20" height="20" viewBox="0 0 20 20" fill="none">
                                    <path d="M12.5 15L7.5 10L12.5 5" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                                </svg>
                                <?php _e('Previous', 'seamless-addon'); ?>
                            </button>
                            <span class="seamless-user-dashboard-pagination-info">
                                <?php _e('Page', 'seamless-addon'); ?> <span class="seamless-user-dashboard-current-page">1</span> <?php _e('of', 'seamless-addon'); ?> <span class="seamless-user-dashboard-total-pages"><?php echo esc_html($total_active_pages); ?></span>
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
                <p class="seamless-user-dashboard-empty"><?php _e('No active memberships found.', 'seamless-addon'); ?></p>
            <?php endif; ?>
        </div>

        <div class="seamless-user-dashboard-tab-content" data-tab-content="expired">
            <?php if (!empty($membership_history)):
                $memberships_per_page = 5;
                $total_expired = count($membership_history);
                $total_expired_pages = $total_expired > 0 ? ceil($total_expired / $memberships_per_page) : 1;
            ?>
                <div class="seamless-user-dashboard-memberships-container" data-per-page="<?php echo esc_attr($memberships_per_page); ?>" data-total-pages="<?php echo esc_attr($total_expired_pages); ?>">
                    <div class="seamless-user-dashboard-membership-list">
                        <?php foreach ($membership_history as $index => $m):
                            $label = $m['plan']['label'] ?? ($m['label'] ?? ($m['name'] ?? 'Membership'));
                            $purchased = $m['start_date'] ?? ($m['started_at'] ?? ($m['created_at'] ?? ''));
                            $expired = $m['expiry_date'] ?? ($m['ended_at'] ?? '');

                            // Check if this expired plan is currently active (same plan_id in current_memberships)
                            $expired_plan_id = $m['plan']['id'] ?? '';
                            $is_currently_active = false;
                            if (!empty($expired_plan_id) && !empty($current_memberships)) {
                                foreach ($current_memberships as $active_m) {
                                    if (($active_m['plan']['id'] ?? '') === $expired_plan_id) {
                                        $is_currently_active = true;
                                        break;
                                    }
                                }
                            }
                        ?>
                            <div class="seamless-user-dashboard-membership-card" data-membership-index="<?php echo esc_attr($index); ?>">
                                <div class="seamless-user-dashboard-membership-content">
                                    <div class="seamless-user-dashboard-membership-header">
                                        <h3 class="seamless-user-dashboard-membership-title"><?php echo esc_html($label); ?></h3>
                                    </div>

                                    <div class="seamless-user-dashboard-membership-actions">
                                        <?php
                                        // Get membership status
                                        $membership_status = strtolower($m['status'] ?? 'expired');
                                        $is_cancelled = $membership_status === 'cancelled';
                                        $is_expired = $membership_status === 'expired';
                                        ?>

                                        <?php if ($is_cancelled): ?>
                                            <span class="seamless-user-dashboard-badge seamless-user-dashboard-badge-cancelled"><?php _e('Cancelled', 'seamless-addon'); ?></span>
                                        <?php elseif ($is_expired): ?>
                                            <span class="seamless-user-dashboard-badge seamless-user-dashboard-badge-expired"><?php _e('Expired', 'seamless-addon'); ?></span>
                                        <?php endif; ?>
                                        <!-- Only show renew button for expired (not cancelled) memberships that are not currently active -->
                                        <?php if (!empty($m['plan']['id']) && !$is_currently_active && $is_expired):
                                        ?>
                                            <button class="seamless-user-dashboard-btn seamless-user-dashboard-btn-sm seamless-user-dashboard-btn-primary seamless-user-dashboard-renew-btn"
                                                data-plan-id="<?php echo esc_attr($m['plan']['id']); ?>"
                                                data-plan-data="<?php echo esc_attr(json_encode($m['plan'])); ?>">
                                                <?php _e('Renew', 'seamless-addon'); ?>
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                    <div class="seamless-user-dashboard-membership-body">
                                        <?php if (!empty($purchased)): ?>
                                            <div class="seamless-user-dashboard-membership-meta">
                                                <strong><?php _e('Purchased:', 'seamless-addon'); ?></strong>
                                                <span><?php echo esc_html(seamless_format_membership_date($purchased)); ?></span>
                                            </div>
                                        <?php endif; ?>
                                        <?php if (!empty($expired)): ?>
                                            <div class="seamless-user-dashboard-membership-meta">
                                                <strong><?php _e('Expired:', 'seamless-addon'); ?></strong>
                                                <span><?php echo esc_html(seamless_format_membership_date($expired)); ?></span>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <?php if ($total_expired_pages > 1): ?>
                        <div class="seamless-user-dashboard-pagination">
                            <button class="seamless-user-dashboard-pagination-btn seamless-user-dashboard-pagination-prev" disabled>
                                <svg width="20" height="20" viewBox="0 0 20 20" fill="none">
                                    <path d="M12.5 15L7.5 10L12.5 5" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                                </svg>
                                <?php _e('Previous', 'seamless-addon'); ?>
                            </button>
                            <span class="seamless-user-dashboard-pagination-info">
                                <?php _e('Page', 'seamless-addon'); ?> <span class="seamless-user-dashboard-current-page">1</span> <?php _e('of', 'seamless-addon'); ?> <span class="seamless-user-dashboard-total-pages"><?php echo esc_html($total_expired_pages); ?></span>
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
                <p class="seamless-user-dashboard-empty"><?php _e('No expired memberships found.', 'seamless-addon'); ?></p>
            <?php endif; ?>
        </div>
    </div>
</div>
<div class="seamless-user-dashboard-upgrade-modal" id="seamless-upgrade-modal" style="display: none;">
    <div class="seamless-user-dashboard-modal-overlay"></div>
    <div class="seamless-user-dashboard-modal-container">
        <div class="seamless-user-dashboard-modal-header">
            <h3 class="seamless-user-dashboard-modal-title"></h3>
            <button class="seamless-user-dashboard-modal-close" aria-label="Close">&times;</button>
        </div>

        <div class="seamless-user-dashboard-modal-body">
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

<!-- Renew Membership Modal -->
<div class="seamless-user-dashboard-renew-modal" id="seamless-renew-modal" style="display: none;">
    <div class="seamless-user-dashboard-modal-overlay"></div>
    <div class="seamless-user-dashboard-modal-container">
        <div class="seamless-user-dashboard-modal-header">
            <h3 class="seamless-user-dashboard-modal-title"><?php _e('Renew Membership', 'seamless-addon'); ?></h3>
            <button class="seamless-user-dashboard-modal-close" aria-label="Close">&times;</button>
        </div>

        <div class="seamless-user-dashboard-modal-body">
            <div class="seamless-user-dashboard-modal-columns">
                <div class="seamless-user-dashboard-modal-left">
                    <h3><?php _e('Plan Details', 'seamless-addon'); ?></h3>
                    <div class="seamless-user-dashboard-renew-plan-info" id="seamless-renew-plan-info">
                        <div class="seamless-user-dashboard-renew-plan-card">
                            <div class="seamless-user-dashboard-renew-header">
                                <h4 class="seamless-user-dashboard-renew-plan-name"></h4>
                                <div class="seamless-user-dashboard-renew-plan-price"></div>
                            </div>
                        </div>
                    </div>
                    <div class="seamless-user-dashboard-proration" id="seamless-renew-pricing">
                        <h4><?php _e('Pricing Breakdown', 'seamless-addon'); ?></h4>
                        <div class="seamless-user-dashboard-proration-item">
                            <span>
                                <?php _e('Renewal Plan Charge:', 'seamless-addon'); ?>
                                <span class="seamless-user-dashboard-info-icon" id="seamless-renew-subsequent-icon" style="display: none;" data-tooltip="This is subsequent renewal price">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                        <circle cx="12" cy="12" r="10"></circle>
                                        <line x1="12" y1="16" x2="12" y2="12"></line>
                                        <line x1="12" y1="8" x2="12.01" y2="8"></line>
                                    </svg>
                                </span>
                            </span>
                            <span class="seamless-user-dashboard-renew-charge">$0.00</span>
                        </div>
                        <div class="seamless-user-dashboard-proration-item">
                            <span><?php _e('Sign-up Fee:', 'seamless-addon'); ?></span>
                            <span class="seamless-user-dashboard-renew-signup-fee">$0.00</span>
                        </div>
                        <div class="seamless-user-dashboard-proration-item seamless-user-dashboard-proration-total">
                            <span><?php _e('Total Amount:', 'seamless-addon'); ?></span>
                            <span class="seamless-user-dashboard-renew-total">$0.00</span>
                        </div>
                    </div>
                </div>

                <div class="seamless-user-dashboard-modal-right">
                    <h3 class="seamless-user-dashboard-selected-plan-name" id="seamless-renew-plan-title"><?php _e('Plan Features', 'seamless-addon'); ?></h3>
                    <div class="seamless-user-dashboard-plan-perks" id="seamless-renew-plan-perks">
                        <p class="seamless-user-dashboard-empty-perks"><?php _e('Loading plan features...', 'seamless-addon'); ?></p>
                    </div>
                </div>
            </div>
        </div>

        <div class="seamless-user-dashboard-modal-footer">
            <div class="seamless-user-dashboard-modal-actions">
                <button class="seamless-user-dashboard-btn seamless-user-dashboard-btn-cancel seamless-user-dashboard-modal-cancel">
                    <?php _e('Cancel', 'seamless-addon'); ?>
                </button>
                <button class="seamless-user-dashboard-btn seamless-user-dashboard-btn-save seamless-user-dashboard-modal-renew">
                    <span class="seamless-user-dashboard-modal-renew-text"><?php _e('Renew Plan', 'seamless-addon'); ?></span>
                </button>
            </div>
        </div>
    </div>
</div>

<script type="text/javascript">
    (function($) {
        if (typeof seamlessUserDashboard !== 'undefined') {
            seamlessUserDashboard.memberships = seamlessUserDashboard.memberships || {};
            seamlessUserDashboard.memberships.current = <?php echo json_encode($current_memberships); ?>;
        }
    })(jQuery);
</script>