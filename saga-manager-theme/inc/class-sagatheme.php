<?php
declare(strict_types=1);

namespace SagaTheme;

/**
 * Main Theme Orchestrator
 *
 * Orchestrates theme functionality using dependency injection
 * Follows SOLID principles with single responsibility pattern
 *
 * @package SagaTheme
 */
class SagaTheme
{
    private SagaHelpers $helpers;
    private SagaQueries $queries;
    private SagaHooks $hooks;
    private SagaAjaxHandler $ajaxHandler;
    private SagaCache $cache;

    /**
     * Constructor with dependency injection
     *
     * @param SagaHelpers $helpers Helper service
     * @param SagaQueries $queries Query service
     * @param SagaHooks $hooks Hook manager
     * @param SagaAjaxHandler $ajaxHandler AJAX handler
     * @param SagaCache $cache Cache layer
     */
    public function __construct(
        SagaHelpers $helpers,
        SagaQueries $queries,
        SagaHooks $hooks,
        SagaAjaxHandler $ajaxHandler,
        SagaCache $cache
    ) {
        $this->helpers = $helpers;
        $this->queries = $queries;
        $this->hooks = $hooks;
        $this->ajaxHandler = $ajaxHandler;
        $this->cache = $cache;
    }

    /**
     * Initialize theme
     *
     * @return void
     */
    public function init(): void
    {
        // Check if Saga Manager plugin is active
        if (!$this->helpers->isSagaManagerActive()) {
            add_action('admin_notices', [$this, 'showPluginDependencyNotice']);
            return;
        }

        // Register hooks
        $this->hooks->registerHooks();

        // Register AJAX endpoints
        $this->ajaxHandler->registerEndpoints();

        // Enqueue scripts and styles
        add_action('wp_enqueue_scripts', [$this, 'enqueueAssets']);

        // Add theme support
        add_action('after_setup_theme', [$this, 'addThemeSupport']);

        // Add keyboard shortcuts help overlay to footer
        add_action('wp_footer', [$this, 'addShortcutsHelp']);
    }

    /**
     * Enqueue theme assets (CSS and JavaScript)
     *
     * @return void
     */
    public function enqueueAssets(): void
    {
        $themeVersion = wp_get_theme()->get('Version');
        $themeUri = get_stylesheet_directory_uri();

        // Enqueue custom CSS
        wp_enqueue_style(
            'saga-manager-theme',
            $themeUri . '/assets/css/saga-manager.css',
            ['generate-style'], // Depend on GeneratePress parent styles
            $themeVersion
        );

        // Enqueue search form CSS
        wp_enqueue_style(
            'saga-searchform',
            $themeUri . '/assets/css/searchform.css',
            ['saga-manager-theme'],
            $themeVersion
        );

        // Enqueue autocomplete CSS
        wp_enqueue_style(
            'saga-autocomplete',
            $themeUri . '/assets/css/autocomplete-search.css',
            ['saga-searchform'],
            $themeVersion
        );

        // Enqueue command palette CSS
        wp_enqueue_style(
            'saga-command-palette',
            $themeUri . '/assets/css/command-palette.css',
            ['saga-manager-theme'],
            $themeVersion
        );

        // Enqueue custom JavaScript
        wp_enqueue_script(
            'saga-manager-theme',
            $themeUri . '/assets/js/saga-manager.js',
            ['jquery'],
            $themeVersion,
            true // Load in footer
        );

        // Enqueue autocomplete JavaScript (vanilla JS, no dependencies)
        wp_enqueue_script(
            'saga-autocomplete',
            $themeUri . '/assets/js/autocomplete-search.js',
            [], // No dependencies
            $themeVersion,
            true // Load in footer
        );

        // Enqueue keyboard shortcuts JavaScript
        wp_enqueue_script(
            'saga-keyboard-shortcuts',
            $themeUri . '/assets/js/keyboard-shortcuts.js',
            [], // No dependencies
            $themeVersion,
            true // Load in footer
        );

        // Enqueue command palette JavaScript
        wp_enqueue_script(
            'saga-command-palette',
            $themeUri . '/assets/js/command-palette.js',
            ['saga-keyboard-shortcuts'], // Depends on shortcuts
            $themeVersion,
            true // Load in footer
        );

        // Register commands from command registry
        \SagaManagerTheme\Commands\CommandRegistry::enqueue_commands();

        // Localize script with AJAX URL and nonces
        wp_localize_script('saga-manager-theme', 'sagaAjax', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonces' => [
                'filter' => wp_create_nonce('saga_filter'),
                'search' => wp_create_nonce('saga_search'),
                'relationships' => wp_create_nonce('saga_relationships'),
            ],
            'strings' => [
                'loading' => __('Loading...', 'saga-manager-theme'),
                'error' => __('An error occurred. Please try again.', 'saga-manager-theme'),
                'noResults' => __('No results found.', 'saga-manager-theme'),
            ],
        ]);

        // Localize autocomplete script
        wp_localize_script('saga-autocomplete', 'sagaAutocomplete', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('saga_autocomplete'),
            'strings' => [
                'searching' => __('Searching...', 'saga-manager-theme'),
                'noResults' => __('No results found', 'saga-manager-theme'),
                'recentSearches' => __('Recent Searches', 'saga-manager-theme'),
                'clearRecent' => __('Clear', 'saga-manager-theme'),
                'error' => __('Search failed. Please try again.', 'saga-manager-theme'),
            ],
        ]);
    }

    /**
     * Add theme support for various features
     *
     * @return void
     */
    public function addThemeSupport(): void
    {
        // Add custom logo support
        add_theme_support('custom-logo', [
            'height' => 100,
            'width' => 400,
            'flex-height' => true,
            'flex-width' => true,
        ]);

        // Add post thumbnail support
        add_theme_support('post-thumbnails');

        // Add custom image sizes for entity thumbnails
        add_image_size('saga-entity-card', 400, 300, true);
        add_image_size('saga-entity-thumbnail', 150, 150, true);

        // Add HTML5 support
        add_theme_support('html5', [
            'search-form',
            'comment-form',
            'comment-list',
            'gallery',
            'caption',
        ]);

        // Add title tag support
        add_theme_support('title-tag');

        // Add custom background support
        add_theme_support('custom-background', [
            'default-color' => 'ffffff',
        ]);
    }

    /**
     * Add keyboard shortcuts help overlay to footer
     *
     * @return void
     */
    public function addShortcutsHelp(): void
    {
        get_template_part('template-parts/shortcuts-help');
    }

    /**
     * Show admin notice when Saga Manager plugin is not active
     *
     * @return void
     */
    public function showPluginDependencyNotice(): void
    {
        ?>
        <div class="notice notice-error">
            <p>
                <strong><?php esc_html_e('Saga Manager Theme:', 'saga-manager-theme'); ?></strong>
                <?php esc_html_e('This theme requires the Saga Manager plugin to be installed and activated.', 'saga-manager-theme'); ?>
            </p>
        </div>
        <?php
    }

    /**
     * Get cache instance (for external access if needed)
     *
     * @return SagaCache Cache instance
     */
    public function getCache(): SagaCache
    {
        return $this->cache;
    }

    /**
     * Get helpers instance (for external access if needed)
     *
     * @return SagaHelpers Helpers instance
     */
    public function getHelpers(): SagaHelpers
    {
        return $this->helpers;
    }

    /**
     * Get queries instance (for external access if needed)
     *
     * @return SagaQueries Queries instance
     */
    public function getQueries(): SagaQueries
    {
        return $this->queries;
    }
}
