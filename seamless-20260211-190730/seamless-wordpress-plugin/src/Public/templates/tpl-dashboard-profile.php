<?php

/**
 * Template Part: Dashboard Profile
 */

$pf_first = $profile['first_name'] ?? '';
$pf_last = $profile['last_name'] ?? '';
$pf_email = $profile['email'] ?? '';
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
            <h4 class="seamless-user-dashboard-section-title"><?php _e('Personal Information', 'seamless-addon'); ?></h4>
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
                <div class="seamless-user-dashboard-profile-field">
                    <label><?php _e('City', 'seamless-addon'); ?></label>
                    <div class="seamless-user-dashboard-profile-value"><?php echo esc_html($pf_city ?: '—'); ?></div>
                </div>
            </div>
            <div class="seamless-user-dashboard-profile-grid">
                <div class="seamless-user-dashboard-profile-field">
                    <label><?php _e('State', 'seamless-addon'); ?></label>
                    <div class="seamless-user-dashboard-profile-value"><?php echo esc_html($pf_state ?: '—'); ?></div>
                </div>
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
            <form id="seamless-user-dashboard-form-<?php echo esc_attr($widget_id ?? 'default'); ?>" class="seamless-user-dashboard-edit-profile-form">
                <div class="seamless-user-dashboard-profile-grid">
                    <div class="seamless-user-dashboard-form-group">
                        <label for="seamless-user-dashboard-first-name-<?php echo esc_attr($widget_id ?? 'default'); ?>">
                            <?php _e('First Name', 'seamless-addon'); ?> <span class="required">*</span>
                        </label>
                        <input type="text" id="seamless-user-dashboard-first-name-<?php echo esc_attr($widget_id ?? 'default'); ?>" name="first_name" value="<?php echo esc_attr($pf_first); ?>" required>
                    </div>
                    <div class="seamless-user-dashboard-form-group">
                        <label for="seamless-user-dashboard-last-name-<?php echo esc_attr($widget_id ?? 'default'); ?>">
                            <?php _e('Last Name', 'seamless-addon'); ?> <span class="required">*</span>
                        </label>
                        <input type="text" id="seamless-user-dashboard-last-name-<?php echo esc_attr($widget_id ?? 'default'); ?>" name="last_name" value="<?php echo esc_attr($pf_last); ?>" required>
                    </div>
                    <div class="seamless-user-dashboard-form-group">
                        <label for="seamless-user-dashboard-email-<?php echo esc_attr($widget_id ?? 'default'); ?>">
                            <?php _e('Email Address', 'seamless-addon'); ?> <span class="required">*</span>
                        </label>
                        <input type="email" id="seamless-user-dashboard-email-<?php echo esc_attr($widget_id ?? 'default'); ?>" name="email" value="<?php echo esc_attr($pf_email); ?>" required>
                    </div>
                </div>
                <div class="seamless-user-dashboard-profile-grid">
                    <div class="seamless-user-dashboard-form-group">
                        <label for="seamless-user-dashboard-phone-<?php echo esc_attr($widget_id ?? 'default'); ?>">
                            <?php _e('Phone Number', 'seamless-addon'); ?>
                        </label>
                        <input type="tel" id="seamless-user-dashboard-phone-<?php echo esc_attr($widget_id ?? 'default'); ?>" name="phone" value="<?php echo esc_attr($pf_phone); ?>">
                    </div>
                    <div class="seamless-user-dashboard-form-group">
                        <label for="seamless-user-dashboard-phone-type-<?php echo esc_attr($widget_id ?? 'default'); ?>">
                            <?php _e('Phone Type', 'seamless-addon'); ?>
                        </label>
                        <select id="seamless-user-dashboard-phone-type-<?php echo esc_attr($widget_id ?? 'default'); ?>" name="phone_type">
                            <option value="mobile" <?php selected($pf_phone_type, 'mobile'); ?>><?php _e('Mobile', 'seamless-addon'); ?></option>
                            <option value="home" <?php selected($pf_phone_type, 'home'); ?>><?php _e('Home', 'seamless-addon'); ?></option>
                            <option value="work" <?php selected($pf_phone_type, 'work'); ?>><?php _e('Work', 'seamless-addon'); ?></option>
                            <option value="other" <?php selected($pf_phone_type, 'other'); ?>><?php _e('Other', 'seamless-addon'); ?></option>
                        </select>
                    </div>
                </div>

                <div class="seamless-user-dashboard-profile-separator">
                    <h4 class="seamless-user-dashboard-subsection-title"><?php _e('Address Information', 'seamless-addon'); ?></h4>
                </div>

                <div class="seamless-user-dashboard-profile-grid">
                    <div class="seamless-user-dashboard-form-group full-width">
                        <label for="seamless-user-dashboard-address-1-<?php echo esc_attr($widget_id ?? 'default'); ?>">
                            <?php _e('Address Line 1', 'seamless-addon'); ?>
                        </label>
                        <input type="text" id="seamless-user-dashboard-address-1-<?php echo esc_attr($widget_id ?? 'default'); ?>" name="address_line_1" value="<?php echo esc_attr($pf_address_1); ?>">
                    </div>
                    <div class="seamless-user-dashboard-form-group full-width">
                        <label for="seamless-user-dashboard-address-2-<?php echo esc_attr($widget_id ?? 'default'); ?>">
                            <?php _e('Address Line 2', 'seamless-addon'); ?>
                        </label>
                        <input type="text" id="seamless-user-dashboard-address-2-<?php echo esc_attr($widget_id ?? 'default'); ?>" name="address_line_2" value="<?php echo esc_attr($pf_address_2); ?>">
                    </div>
                    <div class="seamless-user-dashboard-form-group">
                        <label for="seamless-user-dashboard-city-<?php echo esc_attr($widget_id ?? 'default'); ?>">
                            <?php _e('City', 'seamless-addon'); ?>
                        </label>
                        <input type="text" id="seamless-user-dashboard-city-<?php echo esc_attr($widget_id ?? 'default'); ?>" name="city" value="<?php echo esc_attr($pf_city); ?>">
                    </div>
                </div>

                <div class="seamless-user-dashboard-profile-grid">
                    <div class="seamless-user-dashboard-form-group">
                        <label for="seamless-user-dashboard-state-<?php echo esc_attr($widget_id ?? 'default'); ?>">
                            <?php _e('State', 'seamless-addon'); ?>
                        </label>
                        <input type="text" id="seamless-user-dashboard-state-<?php echo esc_attr($widget_id ?? 'default'); ?>" name="state" value="<?php echo esc_attr($pf_state); ?>">
                    </div>
                    <div class="seamless-user-dashboard-form-group">
                        <label for="seamless-user-dashboard-zip-<?php echo esc_attr($widget_id ?? 'default'); ?>">
                            <?php _e('Zip Code', 'seamless-addon'); ?>
                        </label>
                        <input type="text" id="seamless-user-dashboard-zip-<?php echo esc_attr($widget_id ?? 'default'); ?>" name="zip_code" value="<?php echo esc_attr($pf_zip); ?>">
                    </div>
                    <div class="seamless-user-dashboard-form-group">
                        <label for="seamless-user-dashboard-country-<?php echo esc_attr($widget_id ?? 'default'); ?>">
                            <?php _e('Country', 'seamless-addon'); ?>
                        </label>
                        <input type="text" id="seamless-user-dashboard-country-<?php echo esc_attr($widget_id ?? 'default'); ?>" name="country" value="<?php echo esc_attr($pf_country); ?>">
                    </div>
                </div>

                <div class="seamless-user-dashboard-form-message" style="display: none;"></div>

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