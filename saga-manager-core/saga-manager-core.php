<?php
declare(strict_types=1);

/**
 * Plugin Name: Saga Manager Core
 * Plugin URI: https://github.com/saga-manager/saga-manager-core
 * Description: Multi-tenant saga management system for complex fictional universes - Core API and data layer
 * Version: 1.0.0
 * Requires at least: 6.0
 * Requires PHP: 8.2
 * Author: Saga Manager Team
 * Author URI: https://github.com/saga-manager
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: saga-manager-core
 * Domain Path: /languages
 */

namespace SagaManagerCore;

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Plugin constants
define('SAGA_MANAGER_CORE_VERSION', '1.0.0');
define('SAGA_MANAGER_CORE_PLUGIN_FILE', __FILE__);
define('SAGA_MANAGER_CORE_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('SAGA_MANAGER_CORE_PLUGIN_URL', plugin_dir_url(__FILE__));
define('SAGA_MANAGER_CORE_PLUGIN_BASENAME', plugin_basename(__FILE__));

// Composer autoload
if (file_exists(SAGA_MANAGER_CORE_PLUGIN_DIR . 'vendor/autoload.php')) {
    require_once SAGA_MANAGER_CORE_PLUGIN_DIR . 'vendor/autoload.php';
}

// Activation hook
register_activation_hook(__FILE__, function(): void {
    require_once SAGA_MANAGER_CORE_PLUGIN_DIR . 'src/Infrastructure/WordPress/Activator.php';
    Infrastructure\WordPress\Activator::activate();
});

// Deactivation hook
register_deactivation_hook(__FILE__, function(): void {
    require_once SAGA_MANAGER_CORE_PLUGIN_DIR . 'src/Infrastructure/WordPress/Deactivator.php';
    Infrastructure\WordPress\Deactivator::deactivate();
});

// Initialize plugin
add_action('plugins_loaded', function(): void {
    // Check PHP version
    if (version_compare(PHP_VERSION, '8.2.0', '<')) {
        add_action('admin_notices', function(): void {
            echo '<div class="error"><p>';
            echo esc_html__('Saga Manager Core requires PHP 8.2 or higher.', 'saga-manager-core');
            echo '</p></div>';
        });
        return;
    }

    // Initialize plugin
    if (class_exists('SagaManagerCore\Infrastructure\WordPress\Plugin')) {
        $plugin = new Infrastructure\WordPress\Plugin();
        $plugin->init();
    }
}, 5); // Priority 5 to load before display plugin

/**
 * Get the main plugin instance
 */
function saga_manager_core(): ?Infrastructure\WordPress\Plugin
{
    static $instance = null;

    if ($instance === null && class_exists('SagaManagerCore\Infrastructure\WordPress\Plugin')) {
        $instance = new Infrastructure\WordPress\Plugin();
    }

    return $instance;
}

/**
 * Check if the core plugin is active
 */
function is_saga_manager_core_active(): bool
{
    return defined('SAGA_MANAGER_CORE_VERSION');
}
