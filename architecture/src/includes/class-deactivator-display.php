<?php

declare(strict_types=1);

namespace SagaManagerDisplay;

/**
 * Handles plugin deactivation for Saga Manager Display
 */
final class Deactivator
{
    /**
     * Run deactivation tasks
     */
    public static function deactivate(): void
    {
        // Clear template cache
        self::clearCache();

        // Flush rewrite rules
        flush_rewrite_rules();

        // Log deactivation
        update_option('saga_display_deactivated', time());
    }

    /**
     * Clear all display plugin cache
     */
    private static function clearCache(): void
    {
        global $wpdb;

        // Clear transients
        $wpdb->query(
            "DELETE FROM {$wpdb->options}
             WHERE option_name LIKE '_transient_saga_display_%'
                OR option_name LIKE '_transient_timeout_saga_display_%'
                OR option_name LIKE '_transient_saga_api_%'
                OR option_name LIKE '_transient_timeout_saga_api_%'"
        );

        // Clear object cache
        wp_cache_flush();
    }
}
