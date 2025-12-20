<?php

declare(strict_types=1);

namespace SagaManagerCore\Presentation\Admin\ListTable;

use SagaManager\Contract\EntityTypes;
use SagaManagerCore\Application\Service\EntityService;
use SagaManagerCore\Presentation\Admin\AdminMenuManager;

// Load WP_List_Table if not available
if (!class_exists('WP_List_Table')) {
    require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

/**
 * Custom list table for entities using WP_List_Table
 */
final class EntityListTable extends \WP_List_Table
{
    private EntityService $entityService;
    private int $sagaId;

    public function __construct(EntityService $entityService, int $sagaId = 0)
    {
        parent::__construct([
            'singular' => 'entity',
            'plural' => 'entities',
            'ajax' => true,
        ]);

        $this->entityService = $entityService;
        $this->sagaId = $sagaId;
    }

    /**
     * Define table columns
     */
    public function get_columns(): array
    {
        return [
            'cb' => '<input type="checkbox" />',
            'canonical_name' => __('Name', 'saga-manager-core'),
            'entity_type' => __('Type', 'saga-manager-core'),
            'saga' => __('Saga', 'saga-manager-core'),
            'importance_score' => __('Importance', 'saga-manager-core'),
            'relationships' => __('Relationships', 'saga-manager-core'),
            'updated_at' => __('Last Updated', 'saga-manager-core'),
        ];
    }

    /**
     * Define sortable columns
     */
    public function get_sortable_columns(): array
    {
        return [
            'canonical_name' => ['canonical_name', true],
            'entity_type' => ['entity_type', false],
            'importance_score' => ['importance_score', false],
            'updated_at' => ['updated_at', false],
        ];
    }

    /**
     * Checkbox column
     */
    public function column_cb($item): string
    {
        return sprintf(
            '<input type="checkbox" name="entity[]" value="%d" />',
            $item['id']
        );
    }

    /**
     * Name column with row actions
     */
    public function column_canonical_name($item): string
    {
        $editUrl = AdminMenuManager::getUrl('entities', [
            'action' => 'edit',
            'id' => $item['id'],
        ]);

        $deleteUrl = wp_nonce_url(
            AdminMenuManager::getUrl('entities', [
                'action' => 'delete',
                'id' => $item['id'],
            ]),
            'delete_entity_' . $item['id']
        );

        $actions = [
            'edit' => sprintf(
                '<a href="%s">%s</a>',
                esc_url($editUrl),
                __('Edit', 'saga-manager-core')
            ),
            'relationships' => sprintf(
                '<a href="%s">%s</a>',
                esc_url(AdminMenuManager::getUrl('relationships', ['entity_id' => $item['id']])),
                __('Relationships', 'saga-manager-core')
            ),
            'delete' => sprintf(
                '<a href="%s" class="saga-delete-entity" data-id="%d">%s</a>',
                esc_url($deleteUrl),
                $item['id'],
                __('Delete', 'saga-manager-core')
            ),
        ];

        return sprintf(
            '<strong><a href="%s">%s</a></strong>%s',
            esc_url($editUrl),
            esc_html($item['canonical_name']),
            $this->row_actions($actions)
        );
    }

    /**
     * Entity type column with badge
     */
    public function column_entity_type($item): string
    {
        $type = $item['entity_type'];
        $label = EntityTypes::getLabel($type);

        $colorMap = [
            'character' => '#2271b1',
            'location' => '#1e8e3e',
            'event' => '#d63638',
            'faction' => '#9b59b6',
            'artifact' => '#e67e22',
            'concept' => '#3498db',
        ];

        $color = $colorMap[$type] ?? '#666';

        return sprintf(
            '<span class="saga-type-badge" style="background-color: %s;">%s</span>',
            esc_attr($color),
            esc_html($label)
        );
    }

    /**
     * Saga column
     */
    public function column_saga($item): string
    {
        if (empty($item['saga_name'])) {
            return '—';
        }

        return sprintf(
            '<a href="%s">%s</a>',
            esc_url(AdminMenuManager::getUrl('entities', ['saga_id' => $item['saga_id']])),
            esc_html($item['saga_name'])
        );
    }

    /**
     * Importance score column with visual bar
     */
    public function column_importance_score($item): string
    {
        $score = (int) $item['importance_score'];

        $colorClass = match (true) {
            $score >= 80 => 'high',
            $score >= 50 => 'medium',
            default => 'low',
        };

        return sprintf(
            '<div class="saga-importance-bar %s">
                <div class="saga-importance-fill" style="width: %d%%;"></div>
                <span class="saga-importance-value">%d</span>
            </div>',
            esc_attr($colorClass),
            $score,
            $score
        );
    }

    /**
     * Relationships count column
     */
    public function column_relationships($item): string
    {
        $count = (int) ($item['relationship_count'] ?? 0);

        if ($count === 0) {
            return '<span class="saga-count-zero">0</span>';
        }

        return sprintf(
            '<a href="%s" class="saga-count-badge">%d</a>',
            esc_url(AdminMenuManager::getUrl('relationships', ['entity_id' => $item['id']])),
            $count
        );
    }

    /**
     * Updated at column
     */
    public function column_updated_at($item): string
    {
        $timestamp = strtotime($item['updated_at']);
        $diff = human_time_diff($timestamp, current_time('timestamp'));

        return sprintf(
            '<span title="%s">%s</span>',
            esc_attr(date_i18n(get_option('date_format') . ' ' . get_option('time_format'), $timestamp)),
            sprintf(__('%s ago', 'saga-manager-core'), $diff)
        );
    }

    /**
     * Default column handler
     */
    public function column_default($item, $column_name): string
    {
        return esc_html($item[$column_name] ?? '');
    }

    /**
     * Bulk actions
     */
    public function get_bulk_actions(): array
    {
        return [
            'delete' => __('Delete', 'saga-manager-core'),
            'increase_importance' => __('Increase Importance +10', 'saga-manager-core'),
            'decrease_importance' => __('Decrease Importance -10', 'saga-manager-core'),
        ];
    }

    /**
     * Extra table nav (filters)
     */
    public function extra_tablenav($which): void
    {
        if ($which !== 'top') {
            return;
        }

        echo '<div class="alignleft actions">';

        // Saga filter
        $this->renderSagaDropdown();

        // Entity type filter
        $this->renderTypeDropdown();

        submit_button(__('Filter', 'saga-manager-core'), '', 'filter_action', false);

        echo '</div>';
    }

    private function renderSagaDropdown(): void
    {
        global $wpdb;

        $sagas = $wpdb->get_results(
            "SELECT id, name FROM {$wpdb->prefix}saga_sagas ORDER BY name",
            ARRAY_A
        );

        $currentSaga = absint($_GET['saga_id'] ?? 0);

        echo '<select name="saga_id">';
        echo '<option value="">' . esc_html__('All Sagas', 'saga-manager-core') . '</option>';

        foreach ($sagas as $saga) {
            printf(
                '<option value="%d" %s>%s</option>',
                (int) $saga['id'],
                selected($currentSaga, (int) $saga['id'], false),
                esc_html($saga['name'])
            );
        }

        echo '</select>';
    }

    private function renderTypeDropdown(): void
    {
        $currentType = sanitize_key($_GET['entity_type'] ?? '');

        echo '<select name="entity_type">';
        echo '<option value="">' . esc_html__('All Types', 'saga-manager-core') . '</option>';

        foreach (EntityTypes::ALL as $type) {
            printf(
                '<option value="%s" %s>%s</option>',
                esc_attr($type),
                selected($currentType, $type, false),
                esc_html(EntityTypes::getLabel($type))
            );
        }

        echo '</select>';
    }

    /**
     * Prepare items for display
     */
    public function prepare_items(): void
    {
        // Set column headers
        $this->_column_headers = [
            $this->get_columns(),
            [],
            $this->get_sortable_columns(),
        ];

        // Process bulk actions
        $this->process_bulk_action();

        // Get query parameters
        $perPage = $this->get_items_per_page('saga_entities_per_page', 20);
        $currentPage = $this->get_pagenum();
        $orderBy = sanitize_key($_GET['orderby'] ?? 'canonical_name');
        $order = strtoupper($_GET['order'] ?? 'ASC') === 'DESC' ? 'DESC' : 'ASC';
        $search = sanitize_text_field($_GET['s'] ?? '');
        $sagaId = absint($_GET['saga_id'] ?? $this->sagaId);
        $entityType = sanitize_key($_GET['entity_type'] ?? '');

        // Fetch data
        $result = $this->entityService->listEntities(
            sagaId: $sagaId ?: null,
            type: $entityType ?: null,
            search: $search ?: null,
            page: $currentPage,
            perPage: $perPage,
            orderBy: $orderBy,
            order: $order
        );

        $this->items = array_map(fn($dto) => $dto->toArray(), $result['items']);

        // Set pagination
        $this->set_pagination_args([
            'total_items' => $result['total'],
            'per_page' => $perPage,
            'total_pages' => ceil($result['total'] / $perPage),
        ]);
    }

    /**
     * Process bulk actions
     */
    public function process_bulk_action(): void
    {
        $action = $this->current_action();

        if (!$action) {
            return;
        }

        $ids = array_map('absint', $_REQUEST['entity'] ?? []);

        if (empty($ids)) {
            return;
        }

        // Verify nonce
        check_admin_referer('bulk-entities');

        switch ($action) {
            case 'delete':
                foreach ($ids as $id) {
                    $this->entityService->deleteEntity($id);
                }
                break;

            case 'increase_importance':
                foreach ($ids as $id) {
                    $this->entityService->adjustImportance($id, 10);
                }
                break;

            case 'decrease_importance':
                foreach ($ids as $id) {
                    $this->entityService->adjustImportance($id, -10);
                }
                break;
        }
    }

    /**
     * No items message
     */
    public function no_items(): void
    {
        esc_html_e('No entities found.', 'saga-manager-core');
    }
}
