<?php
declare(strict_types=1);

namespace SagaManagerCore\Infrastructure\WordPress;

/**
 * Plugin Activator
 *
 * Handles plugin activation tasks including database setup
 */
class Activator
{
    public static function activate(): void
    {
        // Create database tables
        $schema = new DatabaseSchema();
        $schema->createTables();

        // Add foreign key constraints (must be done after tables exist)
        $schema->addForeignKeys();

        // Set default options
        if (!get_option('saga_manager_core_settings')) {
            add_option('saga_manager_core_settings', [
                'embedding_api_url' => '',
                'cache_ttl' => 300,
                'max_search_results' => 50,
                'api_rate_limit' => 60,
            ]);
        }

        // Clear any cached data
        wp_cache_flush();

        // Log activation
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('[SAGA][CORE] Plugin activated successfully');
        }
    }
}
