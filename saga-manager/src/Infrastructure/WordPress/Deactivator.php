<?php
declare(strict_types=1);

namespace SagaManager\Infrastructure\WordPress;

/**
 * Plugin Deactivator
 *
 * Handles plugin deactivation tasks
 */
class Deactivator
{
    public static function deactivate(): void
    {
        // Flush rewrite rules
        flush_rewrite_rules();

        // Clear scheduled cron jobs
        $timestamp = wp_next_scheduled('saga_daily_quality_check');
        if ($timestamp) {
            wp_unschedule_event($timestamp, 'saga_daily_quality_check');
        }
    }
}
