<?php
declare(strict_types=1);

/**
 * Uninstall Script
 *
 * Handles cleanup when the plugin is deleted
 */

// Exit if uninstall not called from WordPress
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

// Load database schema class
require_once plugin_dir_path(__FILE__) . 'src/Infrastructure/WordPress/WordPressTablePrefixAware.php';
require_once plugin_dir_path(__FILE__) . 'src/Infrastructure/WordPress/DatabaseSchema.php';

// Drop all tables
$schema = new \SagaManagerCore\Infrastructure\WordPress\DatabaseSchema();
$schema->dropTables();

// Delete all plugin options
delete_option('saga_manager_core_settings');
delete_option('saga_manager_core_db_version');

// Clear scheduled cron jobs
wp_clear_scheduled_hook('saga_core_daily_maintenance');
wp_clear_scheduled_hook('saga_core_hourly_cache_cleanup');

// Clear all caches
wp_cache_flush();
