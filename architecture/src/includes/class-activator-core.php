<?php

declare(strict_types=1);

namespace SagaManagerCore;

use SagaManagerCore\Infrastructure\Database\Schema;
use SagaManagerCore\Infrastructure\Database\Migrator;

/**
 * Handles plugin activation for Saga Manager Core
 */
final class Activator
{
    /**
     * Run activation tasks
     */
    public static function activate(): void
    {
        // Check PHP version
        if (version_compare(PHP_VERSION, SAGA_CORE_MIN_PHP_VERSION, '<')) {
            deactivate_plugins(SAGA_CORE_PLUGIN_BASENAME);
            wp_die(sprintf(
                __('Saga Manager Core requires PHP %s or higher.', 'saga-manager-core'),
                SAGA_CORE_MIN_PHP_VERSION
            ));
        }

        // Create/update database tables
        self::createTables();

        // Run migrations
        self::runMigrations();

        // Set up capabilities
        self::addCapabilities();

        // Schedule cron jobs
        self::scheduleCron();

        // Set default options
        self::setDefaultOptions();

        // Flush rewrite rules
        flush_rewrite_rules();

        // Store activation time and version
        update_option('saga_core_activated', time());
        update_option('saga_core_version', SAGA_CORE_VERSION);

        // Clear any cached data
        wp_cache_flush();
    }

    /**
     * Create database tables using dbDelta
     */
    private static function createTables(): void
    {
        global $wpdb;

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $charset_collate = $wpdb->get_charset_collate();
        $prefix = $wpdb->prefix . 'saga_';

        // Get SQL statements
        $sql = Schema::getCreateTableStatements($prefix, $charset_collate);

        // Run dbDelta for each table
        foreach ($sql as $table => $statement) {
            $result = dbDelta($statement);

            if (!empty($wpdb->last_error)) {
                error_log("[SAGA][ACTIVATE] Error creating table {$table}: " . $wpdb->last_error);
            }
        }

        // Store database version
        update_option('saga_db_version', Schema::VERSION);
    }

    /**
     * Run database migrations
     */
    private static function runMigrations(): void
    {
        $migrator = new Migrator();
        $migrator->run();
    }

    /**
     * Add custom capabilities to roles
     */
    private static function addCapabilities(): void
    {
        $admin = get_role('administrator');
        $editor = get_role('editor');

        if ($admin) {
            $admin->add_cap('manage_saga_settings');
            $admin->add_cap('edit_saga_entities');
            $admin->add_cap('delete_saga_entities');
            $admin->add_cap('manage_saga_relationships');
            $admin->add_cap('import_saga_data');
            $admin->add_cap('export_saga_data');
        }

        if ($editor) {
            $editor->add_cap('edit_saga_entities');
            $editor->add_cap('manage_saga_relationships');
        }
    }

    /**
     * Schedule cron jobs
     */
    private static function scheduleCron(): void
    {
        // Quality check - daily
        if (!wp_next_scheduled('saga_daily_quality_check')) {
            wp_schedule_event(time(), 'daily', 'saga_daily_quality_check');
        }

        // Cache cleanup - hourly
        if (!wp_next_scheduled('saga_hourly_cache_cleanup')) {
            wp_schedule_event(time(), 'hourly', 'saga_hourly_cache_cleanup');
        }

        // Embedding sync - twice daily
        if (!wp_next_scheduled('saga_embedding_sync')) {
            wp_schedule_event(time(), 'twicedaily', 'saga_embedding_sync');
        }
    }

    /**
     * Set default plugin options
     */
    private static function setDefaultOptions(): void
    {
        $defaults = [
            'saga_embedding_api_url' => '',
            'saga_cache_ttl' => 300,
            'saga_default_per_page' => 20,
            'saga_enable_semantic_search' => false,
            'saga_quality_threshold' => 70,
        ];

        foreach ($defaults as $option => $value) {
            if (get_option($option) === false) {
                add_option($option, $value);
            }
        }
    }
}
