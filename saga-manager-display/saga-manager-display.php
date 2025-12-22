<?php
/**
 * Plugin Name: Saga Manager Display
 * Plugin URI: https://example.com/saga-manager-display
 * Description: Frontend display components for the Saga Manager system - shortcodes, Gutenberg blocks, and widgets.
 * Version: 1.2.0
 * Requires at least: 6.0
 * Requires PHP: 8.2
 * Author: Saga Manager Team
 * Author URI: https://example.com
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: saga-manager-display
 * Domain Path: /languages
 */

declare(strict_types=1);

namespace SagaManagerDisplay;

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Plugin constants
define('SAGA_DISPLAY_VERSION', '1.2.0');
define('SAGA_DISPLAY_PLUGIN_FILE', __FILE__);
define('SAGA_DISPLAY_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('SAGA_DISPLAY_PLUGIN_URL', plugin_dir_url(__FILE__));
define('SAGA_DISPLAY_API_NAMESPACE', 'saga/v1');

/**
 * Main plugin class using singleton pattern.
 */
final class SagaManagerDisplay
{
    private static ?self $instance = null;
    private bool $corePluginActive = false;
    private ?API\SagaApiClient $apiClient = null;
    private ?Template\TemplateEngine $templateEngine = null;

    /**
     * Get singleton instance.
     */
    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Private constructor for singleton.
     */
    private function __construct()
    {
        $this->loadDependencies();
        $this->checkCorePlugin();
        $this->registerHooks();
    }

    /**
     * Prevent cloning.
     */
    private function __clone(): void {}

    /**
     * Prevent unserialization.
     */
    public function __wakeup(): void
    {
        throw new \Exception('Cannot unserialize singleton');
    }

    /**
     * Load required files.
     */
    private function loadDependencies(): void
    {
        require_once SAGA_DISPLAY_PLUGIN_DIR . 'src/API/SagaApiClient.php';
        require_once SAGA_DISPLAY_PLUGIN_DIR . 'src/Template/TemplateEngine.php';
        require_once SAGA_DISPLAY_PLUGIN_DIR . 'src/Shortcode/AbstractShortcode.php';
        require_once SAGA_DISPLAY_PLUGIN_DIR . 'src/Shortcode/EntityShortcode.php';
        require_once SAGA_DISPLAY_PLUGIN_DIR . 'src/Shortcode/TimelineShortcode.php';
        require_once SAGA_DISPLAY_PLUGIN_DIR . 'src/Shortcode/SearchShortcode.php';
        require_once SAGA_DISPLAY_PLUGIN_DIR . 'src/Shortcode/RelationshipsShortcode.php';
        require_once SAGA_DISPLAY_PLUGIN_DIR . 'src/Widget/RecentEntitiesWidget.php';
        require_once SAGA_DISPLAY_PLUGIN_DIR . 'src/Widget/EntitySearchWidget.php';
        require_once SAGA_DISPLAY_PLUGIN_DIR . 'src/Block/BlockRegistrar.php';
    }

    /**
     * Check if core plugin is active.
     */
    private function checkCorePlugin(): void
    {
        $this->corePluginActive = $this->isCorePluginActive();
    }

    /**
     * Determine if the Saga Manager Core plugin is active.
     */
    private function isCorePluginActive(): bool
    {
        // Check if core plugin constant is defined (most reliable check)
        if (defined('SAGA_MANAGER_CORE_VERSION')) {
            return true;
        }

        // Check if core plugin class exists
        if (class_exists('SagaManagerCore\\Infrastructure\\WordPress\\Plugin')) {
            return true;
        }

        // Check if plugin is in active plugins list
        $active_plugins = get_option('active_plugins', []);
        foreach ($active_plugins as $plugin) {
            if (strpos($plugin, 'saga-manager-core') !== false) {
                return true;
            }
        }

        return false;
    }

    /**
     * Register all WordPress hooks.
     */
    private function registerHooks(): void
    {
        // Admin notices
        add_action('admin_notices', [$this, 'displayAdminNotices']);

        // Initialize components
        add_action('init', [$this, 'init']);

        // Enqueue assets
        add_action('wp_enqueue_scripts', [$this, 'enqueueAssets']);
        add_action('admin_enqueue_scripts', [$this, 'enqueueAdminAssets']);

        // Register widgets
        add_action('widgets_init', [$this, 'registerWidgets']);

        // Register blocks
        add_action('init', [$this, 'registerBlocks']);

        // Load text domain
        add_action('plugins_loaded', [$this, 'loadTextDomain']);

        // Add REST API availability check
        add_action('rest_api_init', [$this, 'validateApiAvailability']);
    }

    /**
     * Initialize plugin components.
     */
    public function init(): void
    {
        // Initialize API client
        $this->apiClient = new API\SagaApiClient();

        // Initialize template engine
        $this->templateEngine = new Template\TemplateEngine();

        // Register shortcodes
        $this->registerShortcodes();

        // Allow filtering of components
        do_action('saga_display_init', $this);
    }

    /**
     * Register all shortcodes.
     */
    private function registerShortcodes(): void
    {
        $shortcodes = [
            'saga_entity' => new Shortcode\EntityShortcode($this->apiClient, $this->templateEngine),
            'saga_timeline' => new Shortcode\TimelineShortcode($this->apiClient, $this->templateEngine),
            'saga_search' => new Shortcode\SearchShortcode($this->apiClient, $this->templateEngine),
            'saga_relationships' => new Shortcode\RelationshipsShortcode($this->apiClient, $this->templateEngine),
        ];

        foreach ($shortcodes as $tag => $handler) {
            add_shortcode($tag, [$handler, 'render']);
        }

        // Allow adding custom shortcodes
        do_action('saga_display_register_shortcodes', $this->apiClient, $this->templateEngine);
    }

    /**
     * Register Gutenberg blocks.
     */
    public function registerBlocks(): void
    {
        if (!function_exists('register_block_type')) {
            return;
        }

        $registrar = new Block\BlockRegistrar($this->apiClient, $this->templateEngine);
        $registrar->register();
    }

    /**
     * Register WordPress widgets.
     */
    public function registerWidgets(): void
    {
        register_widget(Widget\RecentEntitiesWidget::class);
        register_widget(Widget\EntitySearchWidget::class);
    }

    /**
     * Enqueue frontend assets.
     */
    public function enqueueAssets(): void
    {
        // Main stylesheet
        wp_enqueue_style(
            'saga-display-main',
            SAGA_DISPLAY_PLUGIN_URL . 'assets/css/main.css',
            [],
            SAGA_DISPLAY_VERSION
        );

        // Component-specific styles
        wp_enqueue_style(
            'saga-display-components',
            SAGA_DISPLAY_PLUGIN_URL . 'assets/css/components.css',
            ['saga-display-main'],
            SAGA_DISPLAY_VERSION
        );

        // Main JavaScript
        wp_enqueue_script(
            'saga-display-main',
            SAGA_DISPLAY_PLUGIN_URL . 'assets/js/main.js',
            [],
            SAGA_DISPLAY_VERSION,
            true
        );

        // Localize script with API data
        wp_localize_script('saga-display-main', 'sagaDisplayConfig', [
            'apiUrl' => rest_url(SAGA_DISPLAY_API_NAMESPACE),
            'nonce' => wp_create_nonce('wp_rest'),
            'i18n' => [
                'loading' => __('Loading...', 'saga-manager-display'),
                'error' => __('An error occurred', 'saga-manager-display'),
                'noResults' => __('No results found', 'saga-manager-display'),
                'searchPlaceholder' => __('Search entities...', 'saga-manager-display'),
            ],
        ]);

        // Conditionally load search component
        if ($this->shouldLoadSearchAssets()) {
            wp_enqueue_script(
                'saga-display-search',
                SAGA_DISPLAY_PLUGIN_URL . 'assets/js/search.js',
                ['saga-display-main'],
                SAGA_DISPLAY_VERSION,
                true
            );
        }

        // Conditionally load timeline component
        if ($this->shouldLoadTimelineAssets()) {
            wp_enqueue_script(
                'saga-display-timeline',
                SAGA_DISPLAY_PLUGIN_URL . 'assets/js/timeline.js',
                ['saga-display-main'],
                SAGA_DISPLAY_VERSION,
                true
            );
        }

        // Conditionally load relationships graph
        if ($this->shouldLoadRelationshipsAssets()) {
            wp_enqueue_script(
                'saga-display-relationships',
                SAGA_DISPLAY_PLUGIN_URL . 'assets/js/relationships.js',
                ['saga-display-main'],
                SAGA_DISPLAY_VERSION,
                true
            );
        }
    }

    /**
     * Enqueue admin assets.
     */
    public function enqueueAdminAssets(): void
    {
        $screen = get_current_screen();
        if (!$screen) {
            return;
        }

        // Only load on block editor
        if ($screen->is_block_editor()) {
            wp_enqueue_style(
                'saga-display-editor',
                SAGA_DISPLAY_PLUGIN_URL . 'assets/css/editor.css',
                [],
                SAGA_DISPLAY_VERSION
            );
        }
    }

    /**
     * Check if search assets should be loaded.
     */
    private function shouldLoadSearchAssets(): bool
    {
        global $post;

        if (!$post) {
            return false;
        }

        return has_shortcode($post->post_content, 'saga_search')
            || has_block('saga-manager/search', $post);
    }

    /**
     * Check if timeline assets should be loaded.
     */
    private function shouldLoadTimelineAssets(): bool
    {
        global $post;

        if (!$post) {
            return false;
        }

        return has_shortcode($post->post_content, 'saga_timeline')
            || has_block('saga-manager/timeline', $post);
    }

    /**
     * Check if relationships assets should be loaded.
     */
    private function shouldLoadRelationshipsAssets(): bool
    {
        global $post;

        if (!$post) {
            return false;
        }

        return has_shortcode($post->post_content, 'saga_relationships')
            || has_block('saga-manager/relationships', $post);
    }

    /**
     * Display admin notices.
     */
    public function displayAdminNotices(): void
    {
        if (!$this->corePluginActive) {
            $this->displayCorePluginMissingNotice();
        }

        // Check for API connectivity issues
        $api_status = get_transient('saga_display_api_status');
        if ($api_status === 'unavailable') {
            $this->displayApiUnavailableNotice();
        }
    }

    /**
     * Display notice when core plugin is missing.
     */
    private function displayCorePluginMissingNotice(): void
    {
        ?>
        <div class="notice notice-warning is-dismissible">
            <p>
                <strong><?php esc_html_e('Saga Manager Display', 'saga-manager-display'); ?>:</strong>
                <?php esc_html_e('The Saga Manager Core plugin is required for full functionality. Some features may not work correctly.', 'saga-manager-display'); ?>
            </p>
        </div>
        <?php
    }

    /**
     * Display notice when API is unavailable.
     */
    private function displayApiUnavailableNotice(): void
    {
        ?>
        <div class="notice notice-error is-dismissible">
            <p>
                <strong><?php esc_html_e('Saga Manager Display', 'saga-manager-display'); ?>:</strong>
                <?php esc_html_e('Unable to connect to the Saga Manager API. Please check that the core plugin is properly configured.', 'saga-manager-display'); ?>
            </p>
        </div>
        <?php
    }

    /**
     * Validate API availability on REST init.
     */
    public function validateApiAvailability(): void
    {
        // This is called on rest_api_init, so we can validate the API
        $cached_status = get_transient('saga_display_api_status');

        if ($cached_status === false) {
            $response = wp_remote_get(rest_url(SAGA_DISPLAY_API_NAMESPACE . '/health'), [
                'timeout' => 5,
            ]);

            if (is_wp_error($response) || wp_remote_retrieve_response_code($response) !== 200) {
                set_transient('saga_display_api_status', 'unavailable', 5 * MINUTE_IN_SECONDS);
            } else {
                set_transient('saga_display_api_status', 'available', HOUR_IN_SECONDS);
            }
        }
    }

    /**
     * Load plugin text domain.
     */
    public function loadTextDomain(): void
    {
        load_plugin_textdomain(
            'saga-manager-display',
            false,
            dirname(plugin_basename(__FILE__)) . '/languages'
        );
    }

    /**
     * Get the API client instance.
     */
    public function getApiClient(): ?API\SagaApiClient
    {
        return $this->apiClient;
    }

    /**
     * Get the template engine instance.
     */
    public function getTemplateEngine(): ?Template\TemplateEngine
    {
        return $this->templateEngine;
    }

    /**
     * Check if core plugin is active.
     */
    public function isCoreActive(): bool
    {
        return $this->corePluginActive;
    }
}

// Initialize the plugin
function saga_display_init(): SagaManagerDisplay
{
    return SagaManagerDisplay::getInstance();
}

// Start the plugin
add_action('plugins_loaded', __NAMESPACE__ . '\\saga_display_init', 20);

// Hook to detect when core plugin is deactivated
add_action('deactivated_plugin', function (string $plugin): void {
    // If core plugin is being deactivated, show warning
    if (strpos($plugin, 'saga-manager-core') !== false) {
        // Add a transient to show warning on next page load
        set_transient('saga_display_core_deactivated_warning', true, 60);
    }
});

// Show warning if core was just deactivated
add_action('admin_notices', function (): void {
    if (get_transient('saga_display_core_deactivated_warning')) {
        delete_transient('saga_display_core_deactivated_warning');
        echo '<div class="notice notice-warning is-dismissible">';
        echo '<p><strong>' . esc_html__('Warning:', 'saga-manager-display') . '</strong> ';
        echo esc_html__('Saga Manager Core has been deactivated. Saga Manager Display will not function correctly without it.', 'saga-manager-display');
        echo '</p></div>';
    }
});

// Activation hook
register_activation_hook(__FILE__, function (): void {
    // Check if Saga Manager Core is active (most important check)
    if (!defined('SAGA_MANAGER_CORE_VERSION')) {
        // Check if it's in the active plugins list
        $active_plugins = get_option('active_plugins', []);
        $core_active = false;
        foreach ($active_plugins as $plugin) {
            if (strpos($plugin, 'saga-manager-core') !== false) {
                $core_active = true;
                break;
            }
        }

        if (!$core_active) {
            deactivate_plugins(plugin_basename(__FILE__));
            wp_die(
                esc_html__('Saga Manager Display requires Saga Manager Core to be installed and activated first.', 'saga-manager-display') .
                '<br><br><strong>' . esc_html__('Please activate Saga Manager Core before activating this plugin.', 'saga-manager-display') . '</strong>',
                esc_html__('Plugin Dependency Error', 'saga-manager-display'),
                ['back_link' => true]
            );
        }
    }

    // Check PHP version
    if (version_compare(PHP_VERSION, '8.2', '<')) {
        deactivate_plugins(plugin_basename(__FILE__));
        wp_die(
            esc_html__('Saga Manager Display requires PHP 8.2 or higher.', 'saga-manager-display'),
            esc_html__('Plugin Activation Error', 'saga-manager-display'),
            ['back_link' => true]
        );
    }

    // Check WordPress version
    if (version_compare(get_bloginfo('version'), '6.0', '<')) {
        deactivate_plugins(plugin_basename(__FILE__));
        wp_die(
            esc_html__('Saga Manager Display requires WordPress 6.0 or higher.', 'saga-manager-display'),
            esc_html__('Plugin Activation Error', 'saga-manager-display'),
            ['back_link' => true]
        );
    }

    // Flush rewrite rules
    flush_rewrite_rules();

    // Set activation flag
    update_option('saga_display_activated', true);
});

// Deactivation hook
register_deactivation_hook(__FILE__, function (): void {
    // Clear transients
    delete_transient('saga_display_api_status');

    // Flush rewrite rules
    flush_rewrite_rules();

    // Remove activation flag
    delete_option('saga_display_activated');
});
