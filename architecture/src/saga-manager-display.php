<?php
/**
 * Plugin Name:       Saga Manager Display
 * Plugin URI:        https://example.com/saga-manager
 * Description:       Frontend display for Saga Manager. Provides shortcodes, Gutenberg blocks, and widgets.
 * Version:           1.0.0
 * Requires at least: 6.0
 * Requires PHP:      8.2
 * Author:            Your Name
 * Author URI:        https://example.com
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       saga-manager-display
 * Domain Path:       /languages
 *
 * Requires Plugins:  saga-manager-core
 */

declare(strict_types=1);

namespace SagaManagerDisplay;

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Plugin constants
define('SAGA_DISPLAY_VERSION', '1.0.0');
define('SAGA_DISPLAY_MIN_CORE_VERSION', '1.0.0');
define('SAGA_DISPLAY_PLUGIN_FILE', __FILE__);
define('SAGA_DISPLAY_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('SAGA_DISPLAY_PLUGIN_URL', plugin_dir_url(__FILE__));
define('SAGA_DISPLAY_PLUGIN_BASENAME', plugin_basename(__FILE__));

// Autoloader
if (file_exists(SAGA_DISPLAY_PLUGIN_DIR . 'vendor/autoload.php')) {
    require_once SAGA_DISPLAY_PLUGIN_DIR . 'vendor/autoload.php';
}

/**
 * Dependency checker for backend plugin
 */
final class DependencyChecker
{
    private const REQUIRED_PLUGIN = 'saga-manager-core/saga-manager-core.php';
    private const REQUIRED_CLASS = '\SagaManagerCore\SagaManagerCore';

    private static bool $dependencyMet = false;

    public static function check(): bool
    {
        // Check if the backend plugin class exists
        if (!class_exists(self::REQUIRED_CLASS)) {
            add_action('admin_notices', [self::class, 'showMissingPluginNotice']);
            add_action('admin_init', [self::class, 'deactivateSelf']);
            return false;
        }

        // Check version compatibility
        if (!self::checkVersion()) {
            add_action('admin_notices', [self::class, 'showVersionMismatchNotice']);
            return false;
        }

        // Verify core is ready
        if (!call_user_func([self::REQUIRED_CLASS, 'isReady'])) {
            add_action('admin_notices', [self::class, 'showCoreNotReadyNotice']);
            return false;
        }

        self::$dependencyMet = true;
        return true;
    }

    private static function checkVersion(): bool
    {
        if (!defined('SAGA_CORE_VERSION')) {
            return false;
        }

        return version_compare(
            SAGA_CORE_VERSION,
            SAGA_DISPLAY_MIN_CORE_VERSION,
            '>='
        );
    }

    public static function isDependencyMet(): bool
    {
        return self::$dependencyMet;
    }

    public static function showMissingPluginNotice(): void
    {
        $message = sprintf(
            __('%1$s requires %2$s to be installed and activated.', 'saga-manager-display'),
            '<strong>Saga Manager Display</strong>',
            '<strong>Saga Manager Core</strong>'
        );

        $installUrl = admin_url('plugin-install.php?s=saga-manager-core&tab=search&type=term');

        echo '<div class="notice notice-error">';
        echo '<p>' . wp_kses_post($message) . '</p>';
        echo '<p><a href="' . esc_url($installUrl) . '" class="button button-primary">';
        echo esc_html__('Install Saga Manager Core', 'saga-manager-display');
        echo '</a></p>';
        echo '</div>';
    }

    public static function showVersionMismatchNotice(): void
    {
        $message = sprintf(
            __('%1$s requires %2$s version %3$s or higher. Please update.', 'saga-manager-display'),
            '<strong>Saga Manager Display</strong>',
            '<strong>Saga Manager Core</strong>',
            SAGA_DISPLAY_MIN_CORE_VERSION
        );

        echo '<div class="notice notice-error"><p>' . wp_kses_post($message) . '</p></div>';
    }

    public static function showCoreNotReadyNotice(): void
    {
        $message = __(
            'Saga Manager Core is installed but not properly initialized. Please check for errors.',
            'saga-manager-display'
        );

        echo '<div class="notice notice-warning"><p>' . esc_html($message) . '</p></div>';
    }

    public static function deactivateSelf(): void
    {
        if (!current_user_can('activate_plugins')) {
            return;
        }

        deactivate_plugins(SAGA_DISPLAY_PLUGIN_BASENAME);

        // Remove the "Plugin activated" notice
        if (isset($_GET['activate'])) {
            unset($_GET['activate']);
        }
    }
}

/**
 * Main plugin class
 */
final class SagaManagerDisplay
{
    private static ?self $instance = null;
    private ?ApiClient\SagaApiClient $apiClient = null;

    private function __construct()
    {
        // Wait for plugins_loaded to check dependencies
        add_action('plugins_loaded', [$this, 'init'], 10);
    }

    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function init(): void
    {
        // Check dependencies first
        if (!DependencyChecker::check()) {
            return;
        }

        // Load text domain
        add_action('init', [$this, 'loadTextDomain']);

        // Initialize API client after core is loaded
        add_action('saga_core_loaded', [$this, 'initializeApiClient']);

        // Register shortcodes
        add_action('init', [$this, 'registerShortcodes']);

        // Register blocks
        add_action('init', [$this, 'registerBlocks']);

        // Register widgets
        add_action('widgets_init', [$this, 'registerWidgets']);

        // Enqueue frontend assets
        add_action('wp_enqueue_scripts', [$this, 'enqueueFrontendAssets']);

        // Enqueue block editor assets
        add_action('enqueue_block_editor_assets', [$this, 'enqueueBlockEditorAssets']);
    }

    public function loadTextDomain(): void
    {
        load_plugin_textdomain(
            'saga-manager-display',
            false,
            dirname(SAGA_DISPLAY_PLUGIN_BASENAME) . '/languages'
        );
    }

    public function initializeApiClient($container): void
    {
        $this->apiClient = new ApiClient\SagaApiClient(
            \SagaManagerCore\SagaManagerCore::getApiUrl()
        );

        // Cache the API client globally for template access
        $GLOBALS['saga_api'] = $this->apiClient;
    }

    public function registerShortcodes(): void
    {
        if (!DependencyChecker::isDependencyMet()) {
            return;
        }

        $shortcodeManager = new Shortcode\ShortcodeManager($this->getApiClient());
        $shortcodeManager->register();
    }

    public function registerBlocks(): void
    {
        if (!DependencyChecker::isDependencyMet()) {
            return;
        }

        $blockManager = new Block\BlockManager($this->getApiClient());
        $blockManager->register();
    }

    public function registerWidgets(): void
    {
        if (!DependencyChecker::isDependencyMet()) {
            return;
        }

        register_widget(Widget\RecentEntitiesWidget::class);
        register_widget(Widget\SagaListWidget::class);
        register_widget(Widget\SearchWidget::class);
    }

    public function enqueueFrontendAssets(): void
    {
        // Only load when shortcode or block is present
        if (!$this->shouldLoadAssets()) {
            return;
        }

        wp_enqueue_style(
            'saga-manager-frontend',
            SAGA_DISPLAY_PLUGIN_URL . 'assets/css/frontend.css',
            [],
            SAGA_DISPLAY_VERSION
        );

        wp_enqueue_script(
            'saga-manager-frontend',
            SAGA_DISPLAY_PLUGIN_URL . 'assets/js/frontend.js',
            ['jquery'],
            SAGA_DISPLAY_VERSION,
            true
        );

        wp_localize_script('saga-manager-frontend', 'sagaDisplay', [
            'apiUrl' => \SagaManagerCore\SagaManagerCore::getApiUrl(),
            'nonce' => wp_create_nonce('wp_rest'),
            'i18n' => [
                'loading' => __('Loading...', 'saga-manager-display'),
                'noResults' => __('No results found.', 'saga-manager-display'),
                'error' => __('An error occurred. Please try again.', 'saga-manager-display'),
            ],
        ]);
    }

    public function enqueueBlockEditorAssets(): void
    {
        $asset_file = SAGA_DISPLAY_PLUGIN_DIR . 'build/index.asset.php';

        if (!file_exists($asset_file)) {
            return;
        }

        $asset = require $asset_file;

        wp_enqueue_script(
            'saga-manager-blocks',
            SAGA_DISPLAY_PLUGIN_URL . 'build/index.js',
            $asset['dependencies'],
            $asset['version'],
            true
        );

        wp_enqueue_style(
            'saga-manager-blocks-editor',
            SAGA_DISPLAY_PLUGIN_URL . 'build/index.css',
            [],
            $asset['version']
        );

        // Pass API data to blocks
        wp_localize_script('saga-manager-blocks', 'sagaBlocks', [
            'apiUrl' => \SagaManagerCore\SagaManagerCore::getApiUrl(),
        ]);
    }

    private function shouldLoadAssets(): bool
    {
        global $post;

        if (!$post) {
            return false;
        }

        // Check for shortcodes
        $shortcodes = ['saga_entity', 'saga_entities', 'saga_timeline', 'saga_search', 'saga_relationships'];
        foreach ($shortcodes as $shortcode) {
            if (has_shortcode($post->post_content, $shortcode)) {
                return true;
            }
        }

        // Check for blocks
        $blocks = ['saga-manager/entity-card', 'saga-manager/timeline', 'saga-manager/search'];
        foreach ($blocks as $block) {
            if (has_block($block, $post)) {
                return true;
            }
        }

        return false;
    }

    public function getApiClient(): ApiClient\SagaApiClient
    {
        if ($this->apiClient === null) {
            throw new \RuntimeException('API client not initialized. Is Saga Manager Core active?');
        }

        return $this->apiClient;
    }
}

// Activation hook
register_activation_hook(__FILE__, function(): void {
    // Check dependencies during activation
    if (!class_exists('\SagaManagerCore\SagaManagerCore')) {
        wp_die(
            esc_html__('Saga Manager Display requires Saga Manager Core to be installed and activated first.', 'saga-manager-display'),
            esc_html__('Plugin Dependency Error', 'saga-manager-display'),
            ['back_link' => true]
        );
    }

    require_once SAGA_DISPLAY_PLUGIN_DIR . 'includes/class-activator.php';
    Activator::activate();
});

// Deactivation hook
register_deactivation_hook(__FILE__, function(): void {
    require_once SAGA_DISPLAY_PLUGIN_DIR . 'includes/class-deactivator.php';
    Deactivator::deactivate();
});

// Initialize plugin
SagaManagerDisplay::getInstance();
