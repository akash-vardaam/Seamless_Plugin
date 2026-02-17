<?php

/**
 * Modern Overlay Template for Restricted Content
 * 
 * This template is loaded through WordPress template hierarchy,
 * ensuring all theme styles and scripts are properly loaded.
 */

// Get restriction context from query vars
$is_logged_in = get_query_var('seamless_is_logged_in', false);
$custom_message = get_option('seamless_restriction_message', 'You must have an active membership to view this content.');
$purchase_url = get_option('seamless_membership_purchase_url', home_url('/memberships'));

if (empty($purchase_url)) {
    $purchase_url = get_option('seamless_sso_endpoint', home_url('/memberships'));
}

// Load header with all theme styles
get_header();
?>

<div id="primary" class="content-area">
    <main id="main" class="site-main">
        <!-- Restriction Container -->
        <div class="seamless-restriction-container">
            <div class="seamless-restriction-modal">
                <div class="seamless-restriction-content">
                    <div class="seamless-restriction-header">
                        <div class="seamless-restriction-icon">
                            <?php if ($is_logged_in): ?>
                                <!-- Crown icon for membership upgrade -->
                                <svg viewBox="0 0 24 24" fill="currentColor">
                                    <path d="M5 16L3 6l2.5 2L12 4l6.5 4L21 6l-2 10H5zm2.7-2h8.6l.9-4.4L12 12l-5.2-2.4L7.7 14z" />
                                </svg>
                            <?php else: ?>
                                <!-- Lock icon for login required -->
                                <svg viewBox="0 0 24 24" fill="currentColor">
                                    <path d="M18 8h-1V6c0-2.76-2.24-5-5-5S7 3.24 7 6v2H6c-1.1 0-2 .9-2 2v10c0 1.1.9 2 2 2h12c1.1 0 2-.9 2-2V10c0-1.1-.9-2-2-2zm-6 9c-1.1 0-2-.9-2-2s.9-2 2-2 2 .9 2 2-.9 2-2 2zm3.1-9H8.9V6c0-1.71 1.39-3.1 3.1-3.1 1.71 0 3.1 1.39 3.1 3.1v2z" />
                                </svg>
                            <?php endif; ?>
                        </div>
                        <h2 class="seamless-restriction-title">
                            <?php if ($is_logged_in): ?>
                                Upgraded Membership Required
                            <?php else: ?>
                                Access Restricted
                            <?php endif; ?>
                        </h2>
                        <p class="seamless-restriction-subtitle">
                            <?php if ($is_logged_in): ?>
                                Upgrade your membership to access this exclusive content
                            <?php else: ?>
                                Please sign in to access this premium content
                            <?php endif; ?>
                        </p>
                    </div>

                    <?php if ($is_logged_in): ?>
                        <div class="seamless-restriction-message">
                            <p><?php echo wp_kses_post($custom_message); ?></p>
                        </div>
                    <?php endif; ?>

                    <?php if ($is_logged_in): ?>
                        <!-- Membership upgrade section -->
                        <div class="seamless-restriction-plans">
                            <a href="<?php echo esc_url($purchase_url); ?>" class="seamless-premium-btn seamless-upgrade-btn">
                                Upgrade Your Membership Now
                            </a>
                        </div>
                    <?php else: ?>
                        <!-- Login section -->
                        <div class="seamless-restriction-actions">
                            <?php
                            $sso_client_id = get_option('seamless_sso_client_id');
                            if (!empty($sso_client_id)) :
                                echo do_shortcode('[seamless_login_button text="Sign In to Continue" class="seamless-premium-btn seamless-login-btn"]');
                            else :
                            ?>
                                <a href="<?php echo esc_url(wp_login_url(get_permalink())); ?>" class="seamless-premium-btn seamless-login-btn">
                                    Sign In to Continue
                                </a>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>

                    <div class="seamless-restriction-info">
                        <p>
                            <?php if ($is_logged_in): ?>
                                Choose the plan that works best for you and unlock all premium features.
                            <?php else: ?>
                                New to our platform? You can create an account through the sign-in process.
                            <?php endif; ?>
                        </p>
                    </div>
                </div>

                <div class="seamless-restriction-decoration">
                    <div class="seamless-decoration-circle seamless-circle-1"></div>
                    <div class="seamless-decoration-circle seamless-circle-2"></div>
                    <div class="seamless-decoration-circle seamless-circle-3"></div>
                </div>
            </div>
        </div>
    </main>
</div>

<?php
get_footer();
