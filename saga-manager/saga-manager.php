<?php
declare(strict_types=1);

/**
 * Plugin Name: Saga Manager
 * Plugin URI: https://github.com/saga-manager/saga-manager
 * Description: Multi-tenant saga management system for complex fictional universes
 * Version: 1.2.2
 * Requires at least: 6.0
 * Requires PHP: 8.2
 * Author: Saga Manager Team
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: saga-manager
 */

namespace SagaManager;

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Plugin constants
define('SAGA_MANAGER_VERSION', '1.2.2');
define('SAGA_MANAGER_PLUGIN_FILE', __FILE__);
define('SAGA_MANAGER_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('SAGA_MANAGER_PLUGIN_URL', plugin_dir_url(__FILE__));

// Composer autoload
if (file_exists(SAGA_MANAGER_PLUGIN_DIR . 'vendor/autoload.php')) {
    require_once SAGA_MANAGER_PLUGIN_DIR . 'vendor/autoload.php';
}

// Activation hook
register_activation_hook(__FILE__, function() {
    require_once SAGA_MANAGER_PLUGIN_DIR . 'src/Infrastructure/WordPress/Activator.php';
    Infrastructure\WordPress\Activator::activate();
});

// Deactivation hook
register_deactivation_hook(__FILE__, function() {
    require_once SAGA_MANAGER_PLUGIN_DIR . 'src/Infrastructure/WordPress/Deactivator.php';
    Infrastructure\WordPress\Deactivator::deactivate();
});

// Initialize plugin
add_action('plugins_loaded', function() {
    // Check PHP version
    if (version_compare(PHP_VERSION, '8.2.0', '<')) {
        add_action('admin_notices', function() {
            echo '<div class="error"><p>';
            echo esc_html__('Saga Manager requires PHP 8.2 or higher.', 'saga-manager');
            echo '</p></div>';
        });
        return;
    }

    // Initialize plugin
    if (class_exists('SagaManager\Infrastructure\WordPress\Plugin')) {
        $plugin = new Infrastructure\WordPress\Plugin();
        $plugin->init();
    }
});
