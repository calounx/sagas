<?php
declare(strict_types=1);

namespace SagaManager\Infrastructure\WordPress;

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
        if (!get_option('saga_manager_settings')) {
            add_option('saga_manager_settings', [
                'embedding_api_url' => '',
                'cache_ttl' => 300,
                'max_search_results' => 50,
            ]);
        }

        // Flush rewrite rules for custom post types
        flush_rewrite_rules();
    }
}
