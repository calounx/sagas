<?php
declare(strict_types=1);

namespace SagaManager\Presentation\Admin;

use SagaManager\Domain\Repository\EntityRepositoryInterface;
use SagaManager\Presentation\Admin\ListTable\EntityListTable;
use SagaManager\Presentation\Admin\ListTable\SagaListTable;

/**
 * Admin Menu Manager
 *
 * Handles WordPress admin menu registration and page routing.
 * Depends on repository interfaces rather than implementations.
 */
class AdminMenuManager
{
    private const CAPABILITY = 'manage_options';
    private const MENU_SLUG = 'saga-manager';

    private EntityRepositoryInterface $entityRepository;

    public function __construct(EntityRepositoryInterface $entityRepository)
    {
        $this->entityRepository = $entityRepository;
    }

    /**
     * Register admin menu hooks
     */
    public function register(): void
    {
        add_action('admin_menu', [$this, 'registerMenus']);
        add_action('admin_enqueue_scripts', [$this, 'enqueueAssets']);
        add_action('admin_init', [$this, 'handleFormSubmissions']);

        // AJAX handlers
        add_action('wp_ajax_saga_get_entity', [$this, 'ajaxGetEntity']);
        add_action('wp_ajax_saga_search_entities', [$this, 'ajaxSearchEntities']);
        add_action('wp_ajax_saga_get_sagas', [$this, 'ajaxGetSagas']);
        add_action('wp_ajax_saga_get_attribute_definitions', [$this, 'ajaxGetAttributeDefinitions']);
    }

    /**
     * Register admin menu pages
     */
    public function registerMenus(): void
    {
        // Top-level menu
        add_menu_page(
            __('Saga Manager', 'saga-manager'),
            __('Saga Manager', 'saga-manager'),
            self::CAPABILITY,
            self::MENU_SLUG,
            [$this, 'renderDashboard'],
            'dashicons-book-alt',
            26
        );

        // Dashboard (same as parent)
        add_submenu_page(
            self::MENU_SLUG,
            __('Dashboard', 'saga-manager'),
            __('Dashboard', 'saga-manager'),
            self::CAPABILITY,
            self::MENU_SLUG,
            [$this, 'renderDashboard']
        );

        // Sagas
        add_submenu_page(
            self::MENU_SLUG,
            __('Sagas', 'saga-manager'),
            __('Sagas', 'saga-manager'),
            self::CAPABILITY,
            'saga-manager-sagas',
            [$this, 'renderSagasPage']
        );

        // Entities
        add_submenu_page(
            self::MENU_SLUG,
            __('Entities', 'saga-manager'),
            __('Entities', 'saga-manager'),
            self::CAPABILITY,
            'saga-manager-entities',
            [$this, 'renderEntitiesPage']
        );

        // Relationships
        add_submenu_page(
            self::MENU_SLUG,
            __('Relationships', 'saga-manager'),
            __('Relationships', 'saga-manager'),
            self::CAPABILITY,
            'saga-manager-relationships',
            [$this, 'renderRelationshipsPage']
        );

        // Timeline Events
        add_submenu_page(
            self::MENU_SLUG,
            __('Timeline Events', 'saga-manager'),
            __('Timeline', 'saga-manager'),
            self::CAPABILITY,
            'saga-manager-timeline',
            [$this, 'renderTimelinePage']
        );

        // Settings
        add_submenu_page(
            self::MENU_SLUG,
            __('Settings', 'saga-manager'),
            __('Settings', 'saga-manager'),
            self::CAPABILITY,
            'saga-manager-settings',
            [$this, 'renderSettingsPage']
        );
    }

    /**
     * Enqueue admin assets
     */
    public function enqueueAssets(string $hook): void
    {
        // Only load on our plugin pages
        if (strpos($hook, 'saga-manager') === false) {
            return;
        }

        wp_enqueue_style(
            'saga-manager-admin',
            SAGA_MANAGER_PLUGIN_URL . 'assets/css/admin.css',
            [],
            SAGA_MANAGER_VERSION
        );

        wp_enqueue_script(
            'saga-manager-admin',
            SAGA_MANAGER_PLUGIN_URL . 'assets/js/admin.js',
            ['jquery', 'wp-util'],
            SAGA_MANAGER_VERSION,
            true
        );

        wp_localize_script('saga-manager-admin', 'sagaManagerAdmin', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('saga_manager_ajax'),
            'strings' => [
                'confirmDelete' => __('Are you sure you want to delete this item?', 'saga-manager'),
                'confirmBulkDelete' => __('Are you sure you want to delete the selected items?', 'saga-manager'),
                'saving' => __('Saving...', 'saga-manager'),
                'saved' => __('Saved', 'saga-manager'),
                'error' => __('An error occurred', 'saga-manager'),
            ],
        ]);
    }

    /**
     * Handle form submissions
     */
    public function handleFormSubmissions(): void
    {
        if (!isset($_POST['saga_manager_action'])) {
            return;
        }

        if (!current_user_can(self::CAPABILITY)) {
            wp_die(__('You do not have permission to perform this action.', 'saga-manager'));
        }

        $action = sanitize_key($_POST['saga_manager_action']);

        switch ($action) {
            case 'create_entity':
                $this->handleCreateEntity();
                break;
            case 'update_entity':
                $this->handleUpdateEntity();
                break;
            case 'delete_entity':
                $this->handleDeleteEntity();
                break;
            case 'bulk_action':
                $this->handleBulkAction();
                break;
            case 'create_saga':
                $this->handleCreateSaga();
                break;
            case 'update_saga':
                $this->handleUpdateSaga();
                break;
            case 'delete_saga':
                $this->handleDeleteSaga();
                break;
        }
    }

    /**
     * Render dashboard page
     */
    public function renderDashboard(): void
    {
        $this->renderView('dashboard');
    }

    /**
     * Render sagas list/edit page
     */
    public function renderSagasPage(): void
    {
        $action = isset($_GET['action']) ? sanitize_key($_GET['action']) : 'list';

        switch ($action) {
            case 'add':
                $this->renderView('saga-form', ['saga' => null, 'action' => 'create']);
                break;
            case 'edit':
                $sagaId = isset($_GET['id']) ? absint($_GET['id']) : 0;
                $saga = $this->getSagaById($sagaId);
                if (!$saga) {
                    $this->addAdminNotice(__('Saga not found.', 'saga-manager'), 'error');
                    $this->renderView('sagas-list');
                } else {
                    $this->renderView('saga-form', ['saga' => $saga, 'action' => 'update']);
                }
                break;
            default:
                $this->renderView('sagas-list');
        }
    }

    /**
     * Render entities list/edit page
     */
    public function renderEntitiesPage(): void
    {
        $action = isset($_GET['action']) ? sanitize_key($_GET['action']) : 'list';

        switch ($action) {
            case 'add':
                $this->renderView('entity-form', ['entity' => null, 'action' => 'create']);
                break;
            case 'edit':
                $entityId = isset($_GET['id']) ? absint($_GET['id']) : 0;
                $entity = $this->getEntityById($entityId);
                if (!$entity) {
                    $this->addAdminNotice(__('Entity not found.', 'saga-manager'), 'error');
                    $this->renderView('entities-list');
                } else {
                    $this->renderView('entity-form', ['entity' => $entity, 'action' => 'update']);
                }
                break;
            default:
                $this->renderView('entities-list');
        }
    }

    /**
     * Render relationships page
     */
    public function renderRelationshipsPage(): void
    {
        $this->renderView('relationships-list');
    }

    /**
     * Render timeline events page
     */
    public function renderTimelinePage(): void
    {
        $this->renderView('timeline-list');
    }

    /**
     * Render settings page
     */
    public function renderSettingsPage(): void
    {
        $this->renderView('settings');
    }

    /**
     * AJAX: Get entity by ID
     */
    public function ajaxGetEntity(): void
    {
        check_ajax_referer('saga_manager_ajax', 'nonce');

        if (!current_user_can(self::CAPABILITY)) {
            wp_send_json_error(['message' => __('Permission denied', 'saga-manager')]);
        }

        $entityId = isset($_POST['entity_id']) ? absint($_POST['entity_id']) : 0;

        if (!$entityId) {
            wp_send_json_error(['message' => __('Invalid entity ID', 'saga-manager')]);
        }

        $entity = $this->getEntityById($entityId);

        if (!$entity) {
            wp_send_json_error(['message' => __('Entity not found', 'saga-manager')]);
        }

        wp_send_json_success([
            'entity' => [
                'id' => $entity['id'],
                'saga_id' => $entity['saga_id'],
                'entity_type' => $entity['entity_type'],
                'canonical_name' => $entity['canonical_name'],
                'slug' => $entity['slug'],
                'importance_score' => $entity['importance_score'],
            ],
        ]);
    }

    /**
     * AJAX: Search entities
     */
    public function ajaxSearchEntities(): void
    {
        check_ajax_referer('saga_manager_ajax', 'nonce');

        if (!current_user_can(self::CAPABILITY)) {
            wp_send_json_error(['message' => __('Permission denied', 'saga-manager')]);
        }

        $search = isset($_POST['search']) ? sanitize_text_field($_POST['search']) : '';
        $sagaId = isset($_POST['saga_id']) ? absint($_POST['saga_id']) : 0;
        $type = isset($_POST['entity_type']) ? sanitize_key($_POST['entity_type']) : '';

        global $wpdb;
        $table = $wpdb->prefix . 'saga_entities';

        $sql = "SELECT id, canonical_name, entity_type, importance_score FROM {$table} WHERE 1=1";
        $params = [];

        if ($sagaId) {
            $sql .= " AND saga_id = %d";
            $params[] = $sagaId;
        }

        if ($type) {
            $sql .= " AND entity_type = %s";
            $params[] = $type;
        }

        if ($search) {
            $sql .= " AND canonical_name LIKE %s";
            $params[] = '%' . $wpdb->esc_like($search) . '%';
        }

        $sql .= " ORDER BY importance_score DESC, canonical_name ASC LIMIT 50";

        if (!empty($params)) {
            $sql = $wpdb->prepare($sql, ...$params);
        }

        $entities = $wpdb->get_results($sql, ARRAY_A);

        wp_send_json_success(['entities' => $entities]);
    }

    /**
     * AJAX: Get all sagas
     */
    public function ajaxGetSagas(): void
    {
        check_ajax_referer('saga_manager_ajax', 'nonce');

        if (!current_user_can(self::CAPABILITY)) {
            wp_send_json_error(['message' => __('Permission denied', 'saga-manager')]);
        }

        global $wpdb;
        $table = $wpdb->prefix . 'saga_sagas';

        $sagas = $wpdb->get_results(
            "SELECT id, name, universe FROM {$table} ORDER BY name ASC",
            ARRAY_A
        );

        wp_send_json_success(['sagas' => $sagas]);
    }

    /**
     * AJAX: Get attribute definitions for an entity type
     */
    public function ajaxGetAttributeDefinitions(): void
    {
        check_ajax_referer('saga_manager_ajax', 'nonce');

        if (!current_user_can(self::CAPABILITY)) {
            wp_send_json_error(['message' => __('Permission denied', 'saga-manager')]);
        }

        $entityType = isset($_POST['entity_type']) ? sanitize_key($_POST['entity_type']) : '';

        if (!$entityType) {
            wp_send_json_error(['message' => __('Entity type is required', 'saga-manager')]);
        }

        global $wpdb;
        $table = $wpdb->prefix . 'saga_attribute_definitions';

        $attributes = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$table} WHERE entity_type = %s ORDER BY display_name ASC",
            $entityType
        ), ARRAY_A);

        wp_send_json_success(['attributes' => $attributes ?: []]);
    }

    /**
     * Handle create entity form submission
     */
    private function handleCreateEntity(): void
    {
        if (!wp_verify_nonce($_POST['_saga_nonce'] ?? '', 'saga_create_entity')) {
            wp_die(__('Security check failed.', 'saga-manager'));
        }

        $handler = new EntityFormHandler($this->entityRepository);
        $result = $handler->create($_POST);

        if (is_wp_error($result)) {
            $this->addAdminNotice($result->get_error_message(), 'error');
            return;
        }

        $this->addAdminNotice(__('Entity created successfully.', 'saga-manager'), 'success');

        wp_safe_redirect(admin_url('admin.php?page=saga-manager-entities'));
        exit;
    }

    /**
     * Handle update entity form submission
     */
    private function handleUpdateEntity(): void
    {
        if (!wp_verify_nonce($_POST['_saga_nonce'] ?? '', 'saga_update_entity')) {
            wp_die(__('Security check failed.', 'saga-manager'));
        }

        $entityId = absint($_POST['entity_id'] ?? 0);
        if (!$entityId) {
            $this->addAdminNotice(__('Invalid entity ID.', 'saga-manager'), 'error');
            return;
        }

        $handler = new EntityFormHandler($this->entityRepository);
        $result = $handler->update($entityId, $_POST);

        if (is_wp_error($result)) {
            $this->addAdminNotice($result->get_error_message(), 'error');
            return;
        }

        $this->addAdminNotice(__('Entity updated successfully.', 'saga-manager'), 'success');

        wp_safe_redirect(admin_url('admin.php?page=saga-manager-entities'));
        exit;
    }

    /**
     * Handle delete entity
     */
    private function handleDeleteEntity(): void
    {
        if (!wp_verify_nonce($_POST['_saga_nonce'] ?? '', 'saga_delete_entity')) {
            wp_die(__('Security check failed.', 'saga-manager'));
        }

        $entityId = absint($_POST['entity_id'] ?? 0);
        if (!$entityId) {
            $this->addAdminNotice(__('Invalid entity ID.', 'saga-manager'), 'error');
            return;
        }

        $handler = new EntityFormHandler($this->entityRepository);
        $result = $handler->delete($entityId);

        if (is_wp_error($result)) {
            $this->addAdminNotice($result->get_error_message(), 'error');
            return;
        }

        $this->addAdminNotice(__('Entity deleted successfully.', 'saga-manager'), 'success');

        wp_safe_redirect(admin_url('admin.php?page=saga-manager-entities'));
        exit;
    }

    /**
     * Handle bulk actions
     */
    private function handleBulkAction(): void
    {
        if (!wp_verify_nonce($_POST['_saga_nonce'] ?? '', 'saga_bulk_action')) {
            wp_die(__('Security check failed.', 'saga-manager'));
        }

        $bulkAction = sanitize_key($_POST['bulk_action'] ?? '');
        $ids = isset($_POST['entity_ids']) ? array_map('absint', (array) $_POST['entity_ids']) : [];

        if (empty($ids)) {
            $this->addAdminNotice(__('No items selected.', 'saga-manager'), 'warning');
            return;
        }

        $handler = new EntityFormHandler($this->entityRepository);

        switch ($bulkAction) {
            case 'delete':
                $result = $handler->bulkDelete($ids);
                break;
            case 'increase_importance':
                $result = $handler->bulkUpdateImportance($ids, 10);
                break;
            case 'decrease_importance':
                $result = $handler->bulkUpdateImportance($ids, -10);
                break;
            default:
                $this->addAdminNotice(__('Invalid bulk action.', 'saga-manager'), 'error');
                return;
        }

        if (is_wp_error($result)) {
            $this->addAdminNotice($result->get_error_message(), 'error');
            return;
        }

        $this->addAdminNotice(
            sprintf(__('%d items updated.', 'saga-manager'), count($ids)),
            'success'
        );

        wp_safe_redirect(admin_url('admin.php?page=saga-manager-entities'));
        exit;
    }

    /**
     * Handle create saga
     */
    private function handleCreateSaga(): void
    {
        if (!wp_verify_nonce($_POST['_saga_nonce'] ?? '', 'saga_create_saga')) {
            wp_die(__('Security check failed.', 'saga-manager'));
        }

        global $wpdb;
        $table = $wpdb->prefix . 'saga_sagas';

        $data = [
            'name' => sanitize_text_field($_POST['name'] ?? ''),
            'universe' => sanitize_text_field($_POST['universe'] ?? ''),
            'calendar_type' => sanitize_key($_POST['calendar_type'] ?? 'absolute'),
            'calendar_config' => wp_json_encode([
                'epoch' => sanitize_text_field($_POST['calendar_epoch'] ?? ''),
            ]),
        ];

        if (empty($data['name'])) {
            $this->addAdminNotice(__('Saga name is required.', 'saga-manager'), 'error');
            return;
        }

        $result = $wpdb->insert($table, $data);

        if ($result === false) {
            $this->addAdminNotice(__('Failed to create saga.', 'saga-manager'), 'error');
            return;
        }

        $this->addAdminNotice(__('Saga created successfully.', 'saga-manager'), 'success');

        wp_safe_redirect(admin_url('admin.php?page=saga-manager-sagas'));
        exit;
    }

    /**
     * Handle update saga
     */
    private function handleUpdateSaga(): void
    {
        if (!wp_verify_nonce($_POST['_saga_nonce'] ?? '', 'saga_update_saga')) {
            wp_die(__('Security check failed.', 'saga-manager'));
        }

        $sagaId = absint($_POST['saga_id'] ?? 0);
        if (!$sagaId) {
            $this->addAdminNotice(__('Invalid saga ID.', 'saga-manager'), 'error');
            return;
        }

        global $wpdb;
        $table = $wpdb->prefix . 'saga_sagas';

        $data = [
            'name' => sanitize_text_field($_POST['name'] ?? ''),
            'universe' => sanitize_text_field($_POST['universe'] ?? ''),
            'calendar_type' => sanitize_key($_POST['calendar_type'] ?? 'absolute'),
            'calendar_config' => wp_json_encode([
                'epoch' => sanitize_text_field($_POST['calendar_epoch'] ?? ''),
            ]),
        ];

        if (empty($data['name'])) {
            $this->addAdminNotice(__('Saga name is required.', 'saga-manager'), 'error');
            return;
        }

        $result = $wpdb->update($table, $data, ['id' => $sagaId]);

        if ($result === false) {
            $this->addAdminNotice(__('Failed to update saga.', 'saga-manager'), 'error');
            return;
        }

        $this->addAdminNotice(__('Saga updated successfully.', 'saga-manager'), 'success');

        wp_safe_redirect(admin_url('admin.php?page=saga-manager-sagas'));
        exit;
    }

    /**
     * Handle delete saga
     */
    private function handleDeleteSaga(): void
    {
        if (!wp_verify_nonce($_POST['_saga_nonce'] ?? '', 'saga_delete_saga')) {
            wp_die(__('Security check failed.', 'saga-manager'));
        }

        $sagaId = absint($_POST['saga_id'] ?? 0);
        if (!$sagaId) {
            $this->addAdminNotice(__('Invalid saga ID.', 'saga-manager'), 'error');
            return;
        }

        global $wpdb;
        $table = $wpdb->prefix . 'saga_sagas';

        $result = $wpdb->delete($table, ['id' => $sagaId], ['%d']);

        if ($result === false) {
            $this->addAdminNotice(__('Failed to delete saga.', 'saga-manager'), 'error');
            return;
        }

        $this->addAdminNotice(__('Saga deleted successfully.', 'saga-manager'), 'success');

        wp_safe_redirect(admin_url('admin.php?page=saga-manager-sagas'));
        exit;
    }

    /**
     * Get entity by ID (raw array)
     */
    private function getEntityById(int $id): ?array
    {
        global $wpdb;
        $table = $wpdb->prefix . 'saga_entities';

        return $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM {$table} WHERE id = %d", $id),
            ARRAY_A
        );
    }

    /**
     * Get saga by ID
     */
    private function getSagaById(int $id): ?array
    {
        global $wpdb;
        $table = $wpdb->prefix . 'saga_sagas';

        return $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM {$table} WHERE id = %d", $id),
            ARRAY_A
        );
    }

    /**
     * Render a view file
     */
    private function renderView(string $view, array $data = []): void
    {
        $viewFile = SAGA_MANAGER_PLUGIN_DIR . "src/Presentation/Admin/views/{$view}.php";

        if (!file_exists($viewFile)) {
            echo '<div class="error"><p>' . esc_html__('View not found: ', 'saga-manager') . esc_html($view) . '</p></div>';
            return;
        }

        // Make data available to the view
        extract($data, EXTR_SKIP);

        // Pass utilities to view
        $entityRepository = $this->entityRepository;

        include $viewFile;
    }

    /**
     * Add admin notice
     */
    private function addAdminNotice(string $message, string $type = 'info'): void
    {
        $notices = get_transient('saga_admin_notices') ?: [];
        $notices[] = [
            'message' => $message,
            'type' => $type,
        ];
        set_transient('saga_admin_notices', $notices, 60);
    }
}
