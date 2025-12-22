<?php
/**
 * Plugin Name:       Saga Manager Core
 * Plugin URI:        https://example.com/saga-manager
 * Description:       Backend engine for multi-tenant saga management. Provides data storage, business logic, and REST API.
 * Version:           1.0.0
 * Requires at least: 6.0
 * Requires PHP:      8.2
 * Author:            Your Name
 * Author URI:        https://example.com
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       saga-manager-core
 * Domain Path:       /languages
 */

declare(strict_types=1);

namespace SagaManagerCore;

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Plugin constants
define('SAGA_CORE_VERSION', '1.2.0');
define('SAGA_CORE_MIN_WP_VERSION', '6.0');
define('SAGA_CORE_MIN_PHP_VERSION', '8.2');
define('SAGA_CORE_PLUGIN_FILE', __FILE__);
define('SAGA_CORE_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('SAGA_CORE_PLUGIN_URL', plugin_dir_url(__FILE__));
define('SAGA_CORE_PLUGIN_BASENAME', plugin_basename(__FILE__));

// API namespace for REST routes
define('SAGA_API_NAMESPACE', 'saga/v1');

// Autoloader
require_once SAGA_CORE_PLUGIN_DIR . 'vendor/autoload.php';

/**
 * Main plugin class - Singleton pattern
 */
final class SagaManagerCore
{
    private static ?self $instance = null;

    private function __construct()
    {
        $this->checkRequirements();
        $this->init();
    }

    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function checkRequirements(): void
    {
        if (version_compare(PHP_VERSION, SAGA_CORE_MIN_PHP_VERSION, '<')) {
            add_action('admin_notices', function(): void {
                $message = sprintf(
                    __('Saga Manager Core requires PHP %s or higher. You are running PHP %s.', 'saga-manager-core'),
                    SAGA_CORE_MIN_PHP_VERSION,
                    PHP_VERSION
                );
                echo '<div class="notice notice-error"><p>' . esc_html($message) . '</p></div>';
            });
            return;
        }

        global $wp_version;
        if (version_compare($wp_version, SAGA_CORE_MIN_WP_VERSION, '<')) {
            add_action('admin_notices', function() use ($wp_version): void {
                $message = sprintf(
                    __('Saga Manager Core requires WordPress %s or higher. You are running WordPress %s.', 'saga-manager-core'),
                    SAGA_CORE_MIN_WP_VERSION,
                    $wp_version
                );
                echo '<div class="notice notice-error"><p>' . esc_html($message) . '</p></div>';
            });
        }
    }

    private function init(): void
    {
        // Load text domain
        add_action('init', [$this, 'loadTextDomain']);

        // Initialize components
        add_action('plugins_loaded', [$this, 'initializeComponents'], 5);

        // Register REST API
        add_action('rest_api_init', [$this, 'registerRestRoutes']);

        // Admin hooks
        if (is_admin()) {
            add_action('admin_menu', [$this, 'registerAdminMenu']);
            add_action('admin_enqueue_scripts', [$this, 'enqueueAdminAssets']);
        }
    }

    public function loadTextDomain(): void
    {
        load_plugin_textdomain(
            'saga-manager-core',
            false,
            dirname(SAGA_CORE_PLUGIN_BASENAME) . '/languages'
        );
    }

    public function initializeComponents(): void
    {
        // Initialize dependency injection container
        $container = $this->buildContainer();

        // Store container for access
        $GLOBALS['saga_container'] = $container;

        // Fire action for frontend plugin to hook into
        do_action('saga_core_loaded', $container);
    }

    private function buildContainer(): Container
    {
        $container = new Container();

        // Register repositories
        $container->singleton(
            Domain\Repository\EntityRepositoryInterface::class,
            Infrastructure\Repository\MariaDBEntityRepository::class
        );
        $container->singleton(
            Domain\Repository\SagaRepositoryInterface::class,
            Infrastructure\Repository\MariaDBSagaRepository::class
        );
        $container->singleton(
            Domain\Repository\RelationshipRepositoryInterface::class,
            Infrastructure\Repository\MariaDBRelationshipRepository::class
        );

        // Register services
        $container->singleton(
            Application\Service\EntityService::class
        );
        $container->singleton(
            Application\Service\SearchService::class
        );

        return $container;
    }

    public function registerRestRoutes(): void
    {
        $restManager = new Presentation\Rest\RestApiManager();
        $restManager->registerRoutes();
    }

    public function registerAdminMenu(): void
    {
        $menuManager = new Presentation\Admin\AdminMenuManager();
        $menuManager->register();
    }

    public function enqueueAdminAssets(string $hook): void
    {
        // Only load on our admin pages
        if (strpos($hook, 'saga-manager') === false) {
            return;
        }

        wp_enqueue_style(
            'saga-manager-admin',
            SAGA_CORE_PLUGIN_URL . 'assets/admin/css/admin.css',
            [],
            SAGA_CORE_VERSION
        );

        wp_enqueue_script(
            'saga-manager-admin',
            SAGA_CORE_PLUGIN_URL . 'assets/admin/js/admin.js',
            ['jquery', 'wp-util'],
            SAGA_CORE_VERSION,
            true
        );

        wp_localize_script('saga-manager-admin', 'sagaAdmin', [
            'apiUrl' => rest_url(SAGA_API_NAMESPACE),
            'nonce' => wp_create_nonce('wp_rest'),
            'i18n' => [
                'confirmDelete' => __('Are you sure you want to delete this item?', 'saga-manager-core'),
                'saving' => __('Saving...', 'saga-manager-core'),
                'saved' => __('Saved', 'saga-manager-core'),
            ],
        ]);
    }

    /**
     * Check if the core plugin is ready for frontend consumption
     */
    public static function isReady(): bool
    {
        return did_action('saga_core_loaded') > 0;
    }

    /**
     * Get the API base URL for frontend plugins
     */
    public static function getApiUrl(): string
    {
        return rest_url(SAGA_API_NAMESPACE);
    }

    /**
     * Get plugin version for cache busting
     */
    public static function getVersion(): string
    {
        return SAGA_CORE_VERSION;
    }
}

// Activation hook
register_activation_hook(__FILE__, function(): void {
    require_once SAGA_CORE_PLUGIN_DIR . 'includes/class-activator.php';
    Activator::activate();
});

// Deactivation hook
register_deactivation_hook(__FILE__, function(): void {
    require_once SAGA_CORE_PLUGIN_DIR . 'includes/class-deactivator.php';
    Deactivator::deactivate();
});

// Initialize plugin
SagaManagerCore::getInstance();
