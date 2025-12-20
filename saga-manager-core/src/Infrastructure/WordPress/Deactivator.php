<?php
declare(strict_types=1);

namespace SagaManagerCore\Infrastructure\WordPress;

/**
 * Plugin Deactivator
 *
 * Handles plugin deactivation tasks
 */
class Deactivator
{
    public static function deactivate(): void
    {
        // Clear all caches
        wp_cache_flush();

        // Clear scheduled cron jobs
        wp_clear_scheduled_hook('saga_core_daily_maintenance');
        wp_clear_scheduled_hook('saga_core_hourly_cache_cleanup');

        // Log deactivation
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('[SAGA][CORE] Plugin deactivated');
        }
    }
}
