<?php
declare(strict_types=1);

/**
 * WP-Cron Jobs for Analytics
 *
 * @package Saga_Manager_Theme
 */

/**
 * Schedule analytics cron jobs
 */
function saga_schedule_analytics_cron(): void {
    // Hourly score updates
    if (!wp_next_scheduled('saga_hourly_score_update')) {
        wp_schedule_event(time(), 'hourly', 'saga_hourly_score_update');
    }

    // Daily cleanup
    if (!wp_next_scheduled('saga_daily_analytics_cleanup')) {
        wp_schedule_event(time(), 'daily', 'saga_daily_analytics_cleanup');
    }

    // Weekly trending cache refresh
    if (!wp_next_scheduled('saga_weekly_trending_refresh')) {
        wp_schedule_event(time(), 'weekly', 'saga_weekly_trending_refresh');
    }
}
add_action('wp', 'saga_schedule_analytics_cron');

/**
 * Unschedule cron jobs on theme deactivation
 */
function saga_unschedule_analytics_cron(): void {
    $timestamp = wp_next_scheduled('saga_hourly_score_update');
    if ($timestamp) {
        wp_unschedule_event($timestamp, 'saga_hourly_score_update');
    }

    $timestamp = wp_next_scheduled('saga_daily_analytics_cleanup');
    if ($timestamp) {
        wp_unschedule_event($timestamp, 'saga_daily_analytics_cleanup');
    }

    $timestamp = wp_next_scheduled('saga_weekly_trending_refresh');
    if ($timestamp) {
        wp_unschedule_event($timestamp, 'saga_weekly_trending_refresh');
    }
}
add_action('switch_theme', 'saga_unschedule_analytics_cron');

/**
 * Hourly: Update popularity scores
 *
 * Updates scores for entities with recent activity
 */
function saga_cron_hourly_score_update(): void {
    $start_time = microtime(true);

    // Update scores in batches
    $updated = Saga_Popularity::batch_update_scores(100);

    $duration = microtime(true) - $start_time;

    error_log(sprintf(
        '[SAGA][CRON] Hourly score update: %d entities updated in %.2f seconds',
        $updated,
        $duration
    ));
}
add_action('saga_hourly_score_update', 'saga_cron_hourly_score_update');

/**
 * Daily: Cleanup old logs and optimize
 *
 * - Delete logs older than 90 days (GDPR compliance)
 * - Optimize tables
 * - Clear stale caches
 */
function saga_cron_daily_cleanup(): void {
    $start_time = microtime(true);

    // Cleanup old logs
    $deleted = Saga_Analytics_DB::cleanup_old_logs();

    // Optimize tables
    global $wpdb;
    $tables = [
        $wpdb->prefix . 'saga_entity_stats',
        $wpdb->prefix . 'saga_view_log',
        $wpdb->prefix . 'saga_trending_cache',
    ];

    foreach ($tables as $table) {
        $wpdb->query("OPTIMIZE TABLE {$table}");
    }

    // Clear trending cache
    $wpdb->delete($wpdb->prefix . 'saga_trending_cache', [
        '1' => '1',
    ]);

    // Clear object cache
    wp_cache_flush_group('saga_analytics');

    $duration = microtime(true) - $start_time;

    error_log(sprintf(
        '[SAGA][CRON] Daily cleanup: %d logs deleted, tables optimized in %.2f seconds',
        $deleted,
        $duration
    ));
}
add_action('saga_daily_analytics_cleanup', 'saga_cron_daily_cleanup');

/**
 * Weekly: Refresh trending cache
 *
 * Pre-calculate trending entities for all periods
 */
function saga_cron_weekly_trending_refresh(): void {
    $start_time = microtime(true);

    $periods = ['hourly', 'daily', 'weekly'];
    $total = 0;

    foreach ($periods as $period) {
        // Force cache refresh by clearing first
        global $wpdb;
        $wpdb->delete(
            $wpdb->prefix . 'saga_trending_cache',
            ['period' => $period],
            ['%s']
        );

        // Get trending (will repopulate cache)
        $trending = Saga_Popularity::get_trending(20, $period);
        $total += count($trending);
    }

    $duration = microtime(true) - $start_time;

    error_log(sprintf(
        '[SAGA][CRON] Weekly trending refresh: %d cache entries created in %.2f seconds',
        $total,
        $duration
    ));
}
add_action('saga_weekly_trending_refresh', 'saga_cron_weekly_trending_refresh');

/**
 * Add custom cron schedule intervals
 */
function saga_add_cron_intervals($schedules): array {
    // 15 minutes interval for high-traffic sites
    $schedules['fifteen_minutes'] = [
        'interval' => 900,
        'display' => __('Every 15 Minutes', 'saga-manager'),
    ];

    // Weekly interval
    $schedules['weekly'] = [
        'interval' => 604800,
        'display' => __('Once Weekly', 'saga-manager'),
    ];

    return $schedules;
}
add_filter('cron_schedules', 'saga_add_cron_intervals');

/**
 * Admin notice for cron status
 */
function saga_analytics_cron_admin_notice(): void {
    if (!current_user_can('manage_options')) {
        return;
    }

    $screen = get_current_screen();
    if (!$screen || $screen->id !== 'toplevel_page_saga-analytics') {
        return;
    }

    // Check if cron is running
    $next_run = wp_next_scheduled('saga_hourly_score_update');

    if (!$next_run) {
        ?>
        <div class="notice notice-warning">
            <p>
                <strong><?php esc_html_e('Saga Analytics:', 'saga-manager'); ?></strong>
                <?php esc_html_e('Automated score updates are not scheduled. Scores may not update automatically.', 'saga-manager'); ?>
            </p>
        </div>
        <?php
        return;
    }

    // Check if WP-Cron is disabled
    if (defined('DISABLE_WP_CRON') && DISABLE_WP_CRON) {
        ?>
        <div class="notice notice-info">
            <p>
                <strong><?php esc_html_e('Saga Analytics:', 'saga-manager'); ?></strong>
                <?php esc_html_e('WP-Cron is disabled. Make sure you have set up a system cron job to trigger wp-cron.php.', 'saga-manager'); ?>
            </p>
        </div>
        <?php
    }
}
add_action('admin_notices', 'saga_analytics_cron_admin_notice');

/**
 * Display cron status in dashboard widget
 */
function saga_add_cron_status_widget(): void {
    wp_add_dashboard_widget(
        'saga_analytics_cron_status',
        __('Saga Analytics Cron Status', 'saga-manager'),
        'saga_render_cron_status_widget'
    );
}
add_action('wp_dashboard_setup', 'saga_add_cron_status_widget');

/**
 * Render cron status widget
 */
function saga_render_cron_status_widget(): void {
    $crons = [
        'saga_hourly_score_update' => __('Hourly Score Update', 'saga-manager'),
        'saga_daily_analytics_cleanup' => __('Daily Cleanup', 'saga-manager'),
        'saga_weekly_trending_refresh' => __('Weekly Trending Refresh', 'saga-manager'),
    ];

    echo '<table class="widefat">';
    echo '<thead><tr><th>' . esc_html__('Job', 'saga-manager') . '</th><th>' . esc_html__('Next Run', 'saga-manager') . '</th></tr></thead>';
    echo '<tbody>';

    foreach ($crons as $hook => $label) {
        $next_run = wp_next_scheduled($hook);
        $status = $next_run ? human_time_diff($next_run) : __('Not scheduled', 'saga-manager');

        echo '<tr>';
        echo '<td>' . esc_html($label) . '</td>';
        echo '<td>';
        if ($next_run) {
            echo '<span style="color: #10b981;">✓</span> ';
            echo esc_html(sprintf(__('In %s', 'saga-manager'), $status));
        } else {
            echo '<span style="color: #ef4444;">✗</span> ';
            echo esc_html($status);
        }
        echo '</td>';
        echo '</tr>';
    }

    echo '</tbody></table>';

    // Summary stats
    $summary = Saga_Popularity::get_summary_stats();
    echo '<div style="margin-top: 1rem; padding-top: 1rem; border-top: 1px solid #e5e7eb;">';
    echo '<p><strong>' . esc_html__('Last 24 hours:', 'saga-manager') . '</strong> ';
    echo esc_html(number_format($summary['views_last_24h'])) . ' ' . esc_html__('views', 'saga-manager');
    echo '</p>';
    echo '<p><strong>' . esc_html__('Trending now:', 'saga-manager') . '</strong> ';
    echo esc_html($summary['trending_count']) . ' ' . esc_html__('entities', 'saga-manager');
    echo '</p>';
    echo '</div>';
}

/**
 * Manual trigger for testing (admin only)
 */
function saga_manual_trigger_cron(): void {
    if (!current_user_can('manage_options')) {
        wp_die('Unauthorized');
    }

    $job = sanitize_text_field($_GET['job'] ?? '');

    $valid_jobs = [
        'saga_hourly_score_update',
        'saga_daily_analytics_cleanup',
        'saga_weekly_trending_refresh',
    ];

    if (!in_array($job, $valid_jobs, true)) {
        wp_die('Invalid job');
    }

    // Trigger the job
    do_action($job);

    wp_redirect(add_query_arg([
        'page' => 'saga-analytics',
        'cron_triggered' => '1',
    ], admin_url('admin.php')));
    exit;
}
add_action('admin_post_saga_trigger_cron', 'saga_manual_trigger_cron');
