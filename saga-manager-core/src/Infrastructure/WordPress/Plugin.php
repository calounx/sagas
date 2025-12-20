<?php
declare(strict_types=1);

namespace SagaManagerCore\Infrastructure\WordPress;

use SagaManagerCore\Infrastructure\Repository\MariaDBEntityRepository;
use SagaManagerCore\Presentation\API\EntityController;
use SagaManagerCore\Presentation\API\SagaController;
use SagaManagerCore\Presentation\API\SearchController;
use SagaManagerCore\Presentation\Admin\AdminMenu;

/**
 * Main Plugin Class
 *
 * Orchestrates plugin initialization and dependency injection.
 * This is the core plugin - no CPT, no frontend display.
 */
class Plugin
{
    private ?MariaDBEntityRepository $entityRepository = null;
    private ?EntityController $entityController = null;

    public function init(): void
    {
        // Load text domain for translations
        add_action('init', [$this, 'loadTextDomain']);

        // Initialize admin interface
        if (is_admin()) {
            $this->initAdmin();
        }

        // Register REST API routes
        add_action('rest_api_init', [$this, 'registerRestRoutes']);

        // Schedule maintenance tasks
        add_action('wp', [$this, 'scheduleCronJobs']);

        // Allow other plugins to hook into initialization
        do_action('saga_manager_core_init', $this);
    }

    public function loadTextDomain(): void
    {
        load_plugin_textdomain(
            'saga-manager-core',
            false,
            dirname(plugin_basename(SAGA_MANAGER_CORE_PLUGIN_FILE)) . '/languages'
        );
    }

    public function registerRestRoutes(): void
    {
        $controller = $this->getEntityController();
        $controller->registerRoutes();

        // Allow other controllers to register routes
        do_action('saga_manager_core_register_routes');
    }

    private function initAdmin(): void
    {
        // Register admin menu
        add_action('admin_menu', [$this, 'registerAdminMenu']);

        // Register admin assets
        add_action('admin_enqueue_scripts', [$this, 'enqueueAdminAssets']);
    }

    public function registerAdminMenu(): void
    {
        add_menu_page(
            __('Saga Manager', 'saga-manager-core'),
            __('Saga Manager', 'saga-manager-core'),
            'manage_options',
            'saga-manager',
            [$this, 'renderAdminDashboard'],
            'dashicons-book-alt',
            30
        );

        add_submenu_page(
            'saga-manager',
            __('Dashboard', 'saga-manager-core'),
            __('Dashboard', 'saga-manager-core'),
            'manage_options',
            'saga-manager',
            [$this, 'renderAdminDashboard']
        );

        add_submenu_page(
            'saga-manager',
            __('Sagas', 'saga-manager-core'),
            __('Sagas', 'saga-manager-core'),
            'manage_options',
            'saga-manager-sagas',
            [$this, 'renderSagasPage']
        );

        add_submenu_page(
            'saga-manager',
            __('Entities', 'saga-manager-core'),
            __('Entities', 'saga-manager-core'),
            'manage_options',
            'saga-manager-entities',
            [$this, 'renderEntitiesPage']
        );

        add_submenu_page(
            'saga-manager',
            __('Settings', 'saga-manager-core'),
            __('Settings', 'saga-manager-core'),
            'manage_options',
            'saga-manager-settings',
            [$this, 'renderSettingsPage']
        );
    }

    public function renderAdminDashboard(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die(__('Unauthorized access', 'saga-manager-core'));
        }

        echo '<div class="wrap">';
        echo '<h1>' . esc_html__('Saga Manager Dashboard', 'saga-manager-core') . '</h1>';
        echo '<div id="saga-manager-dashboard"></div>';
        echo '</div>';
    }

    public function renderSagasPage(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die(__('Unauthorized access', 'saga-manager-core'));
        }

        echo '<div class="wrap">';
        echo '<h1>' . esc_html__('Manage Sagas', 'saga-manager-core') . '</h1>';
        echo '<div id="saga-manager-sagas"></div>';
        echo '</div>';
    }

    public function renderEntitiesPage(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die(__('Unauthorized access', 'saga-manager-core'));
        }

        echo '<div class="wrap">';
        echo '<h1>' . esc_html__('Manage Entities', 'saga-manager-core') . '</h1>';
        echo '<div id="saga-manager-entities"></div>';
        echo '</div>';
    }

    public function renderSettingsPage(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die(__('Unauthorized access', 'saga-manager-core'));
        }

        // Handle form submission
        if (isset($_POST['saga_manager_settings_nonce']) &&
            wp_verify_nonce($_POST['saga_manager_settings_nonce'], 'saga_manager_settings')) {

            $settings = [
                'embedding_api_url' => sanitize_url($_POST['embedding_api_url'] ?? ''),
                'cache_ttl' => absint($_POST['cache_ttl'] ?? 300),
                'max_search_results' => absint($_POST['max_search_results'] ?? 50),
                'api_rate_limit' => absint($_POST['api_rate_limit'] ?? 60),
            ];

            update_option('saga_manager_core_settings', $settings);
            echo '<div class="notice notice-success"><p>' .
                 esc_html__('Settings saved successfully.', 'saga-manager-core') .
                 '</p></div>';
        }

        $settings = get_option('saga_manager_core_settings', []);
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('Saga Manager Settings', 'saga-manager-core'); ?></h1>
            <form method="post" action="">
                <?php wp_nonce_field('saga_manager_settings', 'saga_manager_settings_nonce'); ?>
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="embedding_api_url">
                                <?php esc_html_e('Embedding API URL', 'saga-manager-core'); ?>
                            </label>
                        </th>
                        <td>
                            <input type="url" id="embedding_api_url" name="embedding_api_url"
                                   value="<?php echo esc_attr($settings['embedding_api_url'] ?? ''); ?>"
                                   class="regular-text" />
                            <p class="description">
                                <?php esc_html_e('URL for the semantic embedding service (optional)', 'saga-manager-core'); ?>
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="cache_ttl">
                                <?php esc_html_e('Cache TTL (seconds)', 'saga-manager-core'); ?>
                            </label>
                        </th>
                        <td>
                            <input type="number" id="cache_ttl" name="cache_ttl"
                                   value="<?php echo esc_attr($settings['cache_ttl'] ?? 300); ?>"
                                   min="0" max="86400" class="small-text" />
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="max_search_results">
                                <?php esc_html_e('Max Search Results', 'saga-manager-core'); ?>
                            </label>
                        </th>
                        <td>
                            <input type="number" id="max_search_results" name="max_search_results"
                                   value="<?php echo esc_attr($settings['max_search_results'] ?? 50); ?>"
                                   min="10" max="200" class="small-text" />
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="api_rate_limit">
                                <?php esc_html_e('API Rate Limit (per minute)', 'saga-manager-core'); ?>
                            </label>
                        </th>
                        <td>
                            <input type="number" id="api_rate_limit" name="api_rate_limit"
                                   value="<?php echo esc_attr($settings['api_rate_limit'] ?? 60); ?>"
                                   min="10" max="1000" class="small-text" />
                        </td>
                    </tr>
                </table>
                <?php submit_button(); ?>
            </form>
        </div>
        <?php
    }

    public function enqueueAdminAssets(string $hook): void
    {
        // Only load on our admin pages
        if (strpos($hook, 'saga-manager') === false) {
            return;
        }

        wp_enqueue_style(
            'saga-manager-admin',
            SAGA_MANAGER_CORE_PLUGIN_URL . 'assets/css/admin.css',
            [],
            SAGA_MANAGER_CORE_VERSION
        );

        wp_enqueue_script(
            'saga-manager-admin',
            SAGA_MANAGER_CORE_PLUGIN_URL . 'assets/js/admin.js',
            ['jquery', 'wp-api-fetch'],
            SAGA_MANAGER_CORE_VERSION,
            true
        );

        wp_localize_script('saga-manager-admin', 'sagaManagerCore', [
            'apiUrl' => rest_url('saga/v1'),
            'nonce' => wp_create_nonce('wp_rest'),
            'i18n' => [
                'confirmDelete' => __('Are you sure you want to delete this item?', 'saga-manager-core'),
                'saving' => __('Saving...', 'saga-manager-core'),
                'saved' => __('Saved!', 'saga-manager-core'),
                'error' => __('An error occurred', 'saga-manager-core'),
            ],
        ]);
    }

    public function scheduleCronJobs(): void
    {
        if (!wp_next_scheduled('saga_core_daily_maintenance')) {
            wp_schedule_event(time(), 'daily', 'saga_core_daily_maintenance');
        }
    }

    /**
     * Get entity repository instance (lazy initialization)
     */
    public function getEntityRepository(): MariaDBEntityRepository
    {
        if ($this->entityRepository === null) {
            $this->entityRepository = new MariaDBEntityRepository();
        }

        return $this->entityRepository;
    }

    /**
     * Get entity controller instance (lazy initialization)
     */
    private function getEntityController(): EntityController
    {
        if ($this->entityController === null) {
            $this->entityController = new EntityController(
                $this->getEntityRepository()
            );
        }

        return $this->entityController;
    }
}
