<?php

declare(strict_types=1);

namespace SagaManagerCore;

/**
 * Handles plugin deactivation for Saga Manager Core
 */
final class Deactivator
{
    /**
     * Run deactivation tasks
     *
     * Note: We do NOT delete tables on deactivation.
     * Table deletion only happens on uninstall.php
     */
    public static function deactivate(): void
    {
        // Clear scheduled cron jobs
        self::clearCronJobs();

        // Flush rewrite rules
        flush_rewrite_rules();

        // Clear cache
        self::clearCache();

        // Log deactivation
        update_option('saga_core_deactivated', time());

        // Fire action for frontend plugin to react
        do_action('saga_core_deactivating');
    }

    /**
     * Clear all scheduled cron jobs
     */
    private static function clearCronJobs(): void
    {
        $events = [
            'saga_daily_quality_check',
            'saga_hourly_cache_cleanup',
            'saga_embedding_sync',
        ];

        foreach ($events as $event) {
            $timestamp = wp_next_scheduled($event);
            if ($timestamp) {
                wp_unschedule_event($timestamp, $event);
            }
        }
    }

    /**
     * Clear plugin cache
     */
    private static function clearCache(): void
    {
        global $wpdb;

        // Clear WordPress object cache for our group
        wp_cache_flush();

        // Clear transients
        $wpdb->query(
            "DELETE FROM {$wpdb->options}
             WHERE option_name LIKE '_transient_saga_%'
                OR option_name LIKE '_transient_timeout_saga_%'"
        );
    }
}
