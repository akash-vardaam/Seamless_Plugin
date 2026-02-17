<?php

/**
 * Template Part: Dashboard Courses
 */

// If $profile is not available in local scope, this might fail unless passed in includes. 
// Assuming $profile is available or passed. The AJAX handler will ensure it is.
$current_user_email = $profile['email'] ?? '';

$enrolled_count = count($enrolled_courses);
$included_count = count($included_courses);

// Calculate course statistics for summary - ONLY for enrolled courses
$all_courses = array_merge($enrolled_courses, $included_courses);
$total_courses = count($all_courses);
$completed_courses_count = 0;
$recent_course = null;
$recent_course_progress = 0;
$recent_course_completed_lessons = 0;
$recent_course_total_lessons = 0;
$recent_course_url = '#';
$recent_course_title = '';

// PERFORMANCE OPTIMIZATION: Cache all progress data to avoid redundant API calls
$progress_cache = [];

// Get progress ONLY for enrolled courses and find recently started
if (!empty($enrolled_courses) && !empty($current_user_email)) {
    $user_profile_ops = new \Seamless\Operations\UserProfile();
    $recent_start_time = 0;

    foreach ($enrolled_courses as $course) {
        $course_id = $course['id'] ?? '';
        if (empty($course_id)) continue;

        $progress_result = $user_profile_ops->get_course_progress($course_id, $current_user_email);

        if ($progress_result['success'] && !empty($progress_result['data'])) {
            $progress_data = $progress_result['data'];

            // CACHE the progress data for reuse in the course list below
            $progress_cache[$course_id] = $progress_data;

            $progress_percent = floatval($progress_data['progress'] ?? 0);

            // Cap at 100%
            if ($progress_percent > 100) $progress_percent = 100;

            // Count completed courses
            if ($progress_percent >= 100) {
                $completed_courses_count++;
            }

            // Find most recently started course (progress > 0 and < 100)
            if ($progress_percent > 0 && $progress_percent < 100) {
                // Use last_accessed for most recent, fallback to started_at
                $last_accessed = $progress_data['last_accessed'] ?? $progress_data['started_at'] ?? $progress_data['updated_at'] ?? '';

                if (!empty($last_accessed)) {
                    $access_timestamp = strtotime($last_accessed);
                    if ($access_timestamp > $recent_start_time) {
                        $recent_start_time = $access_timestamp;
                        $recent_course = $course;
                        $recent_course_progress = $progress_percent;
                        $recent_course_completed_lessons = intval($progress_data['completed_lessons'] ?? 0);
                        $recent_course_total_lessons = intval($progress_data['total_lessons'] ?? 0);
                        $recent_course_title = $course['title'] ?? $course['name'] ?? 'Course';
                        $course_slug = $course['slug'] ?? '';
                        $recent_course_url = !empty($course_slug) ? ($client_domain . '/courses/' . $course_slug) : '#';
                    }
                }
            }
        }
    }
}

// Also fetch progress for included courses to get lesson counts
// (We don't track their progress, but we need total_lessons for display)
if (!empty($included_courses) && !empty($current_user_email)) {
    if (!isset($user_profile_ops)) {
        $user_profile_ops = new \Seamless\Operations\UserProfile();
    }

    foreach ($included_courses as $course) {
        $course_id = $course['id'] ?? '';
        if (empty($course_id)) continue;

        // Skip if already cached (shouldn't happen, but just in case)
        if (isset($progress_cache[$course_id])) continue;

        $progress_result = $user_profile_ops->get_course_progress($course_id, $current_user_email);

        if ($progress_result['success'] && !empty($progress_result['data'])) {
            // Cache only for lesson count - we don't display progress for included courses
            $progress_cache[$course_id] = $progress_result['data'];
        }
    }
}
?>
<div class="seamless-user-dashboard-summary-grid">
    <div class="seamless-user-dashboard-summary-card">
        <div class="seamless-user-dashboard-summary-value"><?php echo esc_html($total_courses); ?></div>
        <div class="seamless-user-dashboard-summary-label"><?php _e('Total Courses', 'seamless-addon'); ?></div>
    </div>

    <div class="seamless-user-dashboard-summary-card">
        <div class="seamless-user-dashboard-summary-value"><?php echo esc_html($completed_courses_count); ?></div>
        <div class="seamless-user-dashboard-summary-label"><?php _e('Completed', 'seamless-addon'); ?></div>
    </div>

    <?php if (!empty($recent_course)): ?>
        <div class="seamless-user-dashboard-current-course-card">
            <div class="seamless-user-dashboard-current-course-header">
                <div>
                    <h3><?php echo esc_html($recent_course_title); ?></h3>
                    <span class="seamless-user-dashboard-course-status"><?php _e('In Progress', 'seamless-addon'); ?></span>
                </div>
                <a href="<?php echo esc_url($recent_course_url); ?>" target="_blank" class="seamless-user-dashboard-course-continue-btn">
                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <polygon points="5 3 19 12 5 21 5 3"></polygon>
                    </svg>
                    <?php _e('Continue', 'seamless-addon'); ?>
                </a>
            </div>
            <div class="seamless-user-dashboard-current-course-body">
                <div class="seamless-user-dashboard-course-progress-section">
                    <div class="seamless-user-dashboard-progress-header">
                        <span class="seamless-user-dashboard-progress-label"><?php _e('Progress', 'seamless-addon'); ?></span>
                        <span class="seamless-user-dashboard-progress-text"><?php echo esc_html($recent_course_completed_lessons . '/' . $recent_course_total_lessons); ?> <?php _e('lessons', 'seamless-addon'); ?></span>
                    </div>
                    <div class="seamless-user-dashboard-progress-bar">
                        <div class="seamless-user-dashboard-progress-fill" style="width: <?php echo esc_attr($recent_course_progress); ?>%"></div>
                    </div>
                </div>
            </div>
        </div>
    <?php else: ?>
        <div class="seamless-user-dashboard-current-course-card seamless-user-dashboard-empty-course-card">
            <div class="seamless-user-dashboard-empty-course-content">
                <svg xmlns="http://www.w3.org/2000/svg" width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M12 2L2 7L12 12L22 7L12 2Z"></path>
                    <path d="M2 17L12 22L22 17"></path>
                    <path d="M2 12L12 17L22 12"></path>
                </svg>
                <p><?php _e('No courses in progress', 'seamless-addon'); ?></p>
            </div>
        </div>
    <?php endif; ?>
</div>

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
            <?php if (!empty($enrolled_courses)):
                $courses_per_page = 8;
                $total_enrolled = count($enrolled_courses);
                $total_enrolled_pages = $total_enrolled > 0 ? ceil($total_enrolled / $courses_per_page) : 1;
            ?>
                <div class="seamless-user-dashboard-courses-container" data-per-page="<?php echo esc_attr($courses_per_page); ?>" data-total-pages="<?php echo esc_attr($total_enrolled_pages); ?>">
                    <div class="seamless-user-dashboard-courses-grid">
                        <?php foreach ($enrolled_courses as $index => $course):

                            $course_id = $course['id'] ?? '';
                            $course_title = $course['title'] ?? $course['name'] ?? 'Course';
                            $course_slug = $course['slug'] ?? '';
                            $course_image = $course['image'] ?? '';

                            // Get course URL
                            $course_url = !empty($course_slug) ? ($client_domain . '/courses/' . $course_slug) : '#';

                            // USE CACHED progress data (already fetched above)
                            $completed_lessons = 0;
                            $total_lessons = 0;
                            $progress_percent = 0;

                            if (!empty($course_id) && isset($progress_cache[$course_id])) {
                                $progress_data = $progress_cache[$course_id];
                                $completed_lessons = intval($progress_data['completed_lessons'] ?? 0);
                                $total_lessons = intval($progress_data['total_lessons'] ?? 0);
                                $progress_percent = floatval($progress_data['progress'] ?? 0);

                                // Cap progress at 100%
                                if ($progress_percent > 100) {
                                    $progress_percent = 100;
                                }
                            }

                            $is_completed = $progress_percent >= 100;
                        ?>
                            <div class="seamless-user-dashboard-course-card" data-course-index="<?php echo esc_attr($index); ?>">
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

                                    <div class="seamless-user-dashboard-course-progress-section">
                                        <div class="seamless-user-dashboard-progress-header">
                                            <span class="seamless-user-dashboard-progress-label"><?php _e('Progress', 'seamless-addon'); ?></span>
                                            <span class="seamless-user-dashboard-progress-text"><?php echo esc_html($completed_lessons . '/' . $total_lessons); ?> <?php _e('lessons', 'seamless-addon'); ?></span>
                                        </div>
                                        <div class="seamless-user-dashboard-progress-bar">
                                            <div class="seamless-user-dashboard-progress-fill" style="width: <?php echo esc_attr($progress_percent); ?>%"></div>
                                        </div>
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

                    <?php if ($total_enrolled_pages > 1): ?>
                        <div class="seamless-user-dashboard-pagination">
                            <button class="seamless-user-dashboard-pagination-btn seamless-user-dashboard-pagination-prev" disabled>
                                <svg width="20" height="20" viewBox="0 0 20 20" fill="none">
                                    <path d="M12.5 15L7.5 10L12.5 5" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                                </svg>
                                <?php _e('Previous', 'seamless-addon'); ?>
                            </button>
                            <span class="seamless-user-dashboard-pagination-info">
                                <?php _e('Page', 'seamless-addon'); ?> <span class="seamless-user-dashboard-current-page">1</span> <?php _e('of', 'seamless-addon'); ?> <span class="seamless-user-dashboard-total-pages"><?php echo esc_html($total_enrolled_pages); ?></span>
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
                <p class="seamless-user-dashboard-empty"><?php _e('You have not enrolled in any courses yet.', 'seamless-addon'); ?></p>
            <?php endif; ?>
        </div>

        <div class="seamless-user-dashboard-tab-content" data-tab-content="included">
            <?php if (!empty($included_courses)):
                $courses_per_page = 8;
                $total_included = count($included_courses);
                $total_included_pages = $total_included > 0 ? ceil($total_included / $courses_per_page) : 1;
            ?>
                <div class="seamless-user-dashboard-courses-container" data-per-page="<?php echo esc_attr($courses_per_page); ?>" data-total-pages="<?php echo esc_attr($total_included_pages); ?>">
                    <div class="seamless-user-dashboard-courses-grid">
                        <?php foreach ($included_courses as $index => $course):
                            $course_id = $course['id'] ?? '';
                            $course_title = $course['title'] ?? $course['name'] ?? 'Course';
                            $course_slug = $course['slug'] ?? '';
                            $course_image = $course['image'] ?? '';
                            $course_duration = $course['duration_minutes'] ?? '';

                            // Get course URL
                            $course_url = !empty($course_slug) ? ($client_domain . '/courses/' . $course_slug) : '#';

                            // Get lesson count from cached progress data (course API doesn't include this)
                            $total_lessons = 0;
                            if (!empty($course_id) && isset($progress_cache[$course_id])) {
                                $total_lessons = intval($progress_cache[$course_id]['total_lessons'] ?? 0);
                            }
                        ?>
                            <div class="seamless-user-dashboard-course-card" data-course-index="<?php echo esc_attr($index); ?>">
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

                                    <?php if ($total_lessons > 0): ?>
                                        <div class="seamless-user-dashboard-course-info">
                                            <span class="seamless-user-dashboard-course-lessons-simple">
                                                <svg class="w-4 h-4 mr-3 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253">
                                                    </path>
                                                </svg>
                                                <?php echo esc_html($total_lessons); ?> <?php _e('lessons', 'seamless-addon'); ?>
                                            </span>
                                            <span class="seamless-user-dashboard-course-lessons-simple">
                                                <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="feather feather-clock">
                                                    <circle cx="12" cy="12" r="10"></circle>
                                                    <polyline points="12 6 12 12 16 14"></polyline>
                                                </svg>
                                                <?php echo esc_html($course_duration); ?> <?php _e('minutes', 'seamless-addon'); ?>
                                            </span>
                                        </div>
                                    <?php endif; ?>

                                    <a href="<?php echo esc_url($course_url); ?>" target="_blank" class="seamless-user-dashboard-course-continue">
                                        <?php _e('Start Course', 'seamless-addon'); ?>
                                        <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" fill="none" viewBox="0 0 24 24">
                                            <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 12H8m12 0-4 4m4-4-4-4M9 4H7a3 3 0 0 0-3 3v10a3 3 0 0 0 3 3h2" />
                                        </svg>
                                    </a>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <?php if ($total_included_pages > 1): ?>
                        <div class="seamless-user-dashboard-pagination">
                            <button class="seamless-user-dashboard-pagination-btn seamless-user-dashboard-pagination-prev" disabled>
                                <svg width="20" height="20" viewBox="0 0 20 20" fill="none">
                                    <path d="M12.5 15L7.5 10L12.5 5" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                                </svg>
                                <?php _e('Previous', 'seamless-addon'); ?>
                            </button>
                            <span class="seamless-user-dashboard-pagination-info">
                                <?php _e('Page', 'seamless-addon'); ?> <span class="seamless-user-dashboard-current-page">1</span> <?php _e('of', 'seamless-addon'); ?> <span class="seamless-user-dashboard-total-pages"><?php echo esc_html($total_included_pages); ?></span>
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
                <p class="seamless-user-dashboard-empty"><?php _e('No included courses from memberships at this time.', 'seamless-addon'); ?></p>
            <?php endif; ?>
        </div>
    </div>
</div>