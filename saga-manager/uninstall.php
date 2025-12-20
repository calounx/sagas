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
$schema = new \SagaManager\Infrastructure\WordPress\DatabaseSchema();
$schema->dropTables();

// Delete all plugin options
delete_option('saga_manager_settings');
delete_option('saga_manager_db_version');

// Clear all caches
wp_cache_flush();
