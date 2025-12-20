<?php
declare(strict_types=1);

namespace SagaManager\Presentation\Admin\ListTable;

use SagaManager\Domain\Entity\EntityType;

if (!class_exists('WP_List_Table')) {
    require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

/**
 * Entity List Table
 *
 * WP_List_Table implementation for saga entities
 */
class EntityListTable extends \WP_List_Table
{
    private string $tablePrefix;

    public function __construct()
    {
        global $wpdb;
        $this->tablePrefix = $wpdb->prefix . 'saga_';

        parent::__construct([
            'singular' => 'entity',
            'plural' => 'entities',
            'ajax' => true,
        ]);
    }

    /**
     * Get table columns
     */
    public function get_columns(): array
    {
        return [
            'cb' => '<input type="checkbox" />',
            'canonical_name' => __('Name', 'saga-manager'),
            'entity_type' => __('Type', 'saga-manager'),
            'saga_name' => __('Saga', 'saga-manager'),
            'importance_score' => __('Importance', 'saga-manager'),
            'created_at' => __('Created', 'saga-manager'),
        ];
    }

    /**
     * Get sortable columns
     */
    public function get_sortable_columns(): array
    {
        return [
            'canonical_name' => ['canonical_name', false],
            'entity_type' => ['entity_type', false],
            'saga_name' => ['saga_name', false],
            'importance_score' => ['importance_score', true], // Default descending
            'created_at' => ['created_at', false],
        ];
    }

    /**
     * Get bulk actions
     */
    public function get_bulk_actions(): array
    {
        return [
            'delete' => __('Delete', 'saga-manager'),
            'increase_importance' => __('Increase Importance (+10)', 'saga-manager'),
            'decrease_importance' => __('Decrease Importance (-10)', 'saga-manager'),
        ];
    }

    /**
     * Checkbox column
     */
    public function column_cb($item): string
    {
        return sprintf(
            '<input type="checkbox" name="entity_ids[]" value="%d" />',
            absint($item['id'])
        );
    }

    /**
     * Name column with row actions
     */
    public function column_canonical_name($item): string
    {
        $editUrl = add_query_arg([
            'page' => 'saga-manager-entities',
            'action' => 'edit',
            'id' => $item['id'],
        ], admin_url('admin.php'));

        $deleteUrl = wp_nonce_url(
            add_query_arg([
                'page' => 'saga-manager-entities',
                'action' => 'delete',
                'id' => $item['id'],
            ], admin_url('admin.php')),
            'saga_delete_entity_' . $item['id']
        );

        $actions = [
            'edit' => sprintf(
                '<a href="%s">%s</a>',
                esc_url($editUrl),
                __('Edit', 'saga-manager')
            ),
            'delete' => sprintf(
                '<a href="%s" class="saga-delete-entity" data-id="%d" onclick="return confirm(\'%s\');">%s</a>',
                esc_url($deleteUrl),
                absint($item['id']),
                esc_js(__('Are you sure you want to delete this entity?', 'saga-manager')),
                __('Delete', 'saga-manager')
            ),
        ];

        return sprintf(
            '<strong><a href="%s" class="row-title">%s</a></strong> <span class="saga-slug">(%s)</span>%s',
            esc_url($editUrl),
            esc_html($item['canonical_name']),
            esc_html($item['slug']),
            $this->row_actions($actions)
        );
    }

    /**
     * Entity type column
     */
    public function column_entity_type($item): string
    {
        try {
            $type = EntityType::from($item['entity_type']);
            $badgeClass = 'saga-type-badge saga-type-' . $type->value;
            return sprintf(
                '<span class="%s">%s</span>',
                esc_attr($badgeClass),
                esc_html($type->label())
            );
        } catch (\ValueError $e) {
            return esc_html($item['entity_type']);
        }
    }

    /**
     * Saga name column
     */
    public function column_saga_name($item): string
    {
        if (empty($item['saga_name'])) {
            return '<em>' . esc_html__('Unknown Saga', 'saga-manager') . '</em>';
        }

        $filterUrl = add_query_arg([
            'page' => 'saga-manager-entities',
            'saga_id' => $item['saga_id'],
        ], admin_url('admin.php'));

        return sprintf(
            '<a href="%s">%s</a>',
            esc_url($filterUrl),
            esc_html($item['saga_name'])
        );
    }

    /**
     * Importance score column with visual indicator
     */
    public function column_importance_score($item): string
    {
        $score = absint($item['importance_score']);
        $percentage = min(100, $score);

        // Determine color based on score
        if ($score >= 75) {
            $color = '#00a32a'; // Green
        } elseif ($score >= 50) {
            $color = '#dba617'; // Yellow
        } elseif ($score >= 25) {
            $color = '#d63638'; // Red
        } else {
            $color = '#787c82'; // Gray
        }

        return sprintf(
            '<div class="saga-importance-bar">
                <div class="saga-importance-fill" style="width: %d%%; background-color: %s;"></div>
            </div>
            <span class="saga-importance-value">%d</span>',
            $percentage,
            esc_attr($color),
            $score
        );
    }

    /**
     * Created at column
     */
    public function column_created_at($item): string
    {
        $datetime = new \DateTime($item['created_at']);
        return sprintf(
            '<abbr title="%s">%s</abbr>',
            esc_attr($datetime->format('Y-m-d H:i:s')),
            esc_html($datetime->format(get_option('date_format')))
        );
    }

    /**
     * Default column handler
     */
    public function column_default($item, $column_name): string
    {
        return isset($item[$column_name]) ? esc_html($item[$column_name]) : '';
    }

    /**
     * Get views (filter links)
     */
    public function get_views(): array
    {
        global $wpdb;

        $entitiesTable = $this->tablePrefix . 'entities';
        $currentType = isset($_GET['entity_type']) ? sanitize_key($_GET['entity_type']) : '';
        $currentSagaId = isset($_GET['saga_id']) ? absint($_GET['saga_id']) : 0;

        $views = [];

        // All entities
        $totalCount = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$entitiesTable}");
        $allClass = empty($currentType) && empty($currentSagaId) ? 'current' : '';
        $views['all'] = sprintf(
            '<a href="%s" class="%s">%s <span class="count">(%d)</span></a>',
            esc_url(admin_url('admin.php?page=saga-manager-entities')),
            $allClass,
            __('All', 'saga-manager'),
            $totalCount
        );

        // By entity type
        foreach (EntityType::cases() as $type) {
            $count = (int) $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$entitiesTable} WHERE entity_type = %s",
                $type->value
            ));

            if ($count > 0) {
                $class = $currentType === $type->value ? 'current' : '';
                $views[$type->value] = sprintf(
                    '<a href="%s" class="%s">%s <span class="count">(%d)</span></a>',
                    esc_url(add_query_arg(['page' => 'saga-manager-entities', 'entity_type' => $type->value], admin_url('admin.php'))),
                    $class,
                    esc_html($type->label()),
                    $count
                );
            }
        }

        return $views;
    }

    /**
     * Display extra table navigation (filters)
     */
    public function extra_tablenav($which): void
    {
        if ($which !== 'top') {
            return;
        }

        global $wpdb;
        $sagasTable = $this->tablePrefix . 'sagas';

        $sagas = $wpdb->get_results("SELECT id, name FROM {$sagasTable} ORDER BY name ASC", ARRAY_A);
        $currentSagaId = isset($_GET['saga_id']) ? absint($_GET['saga_id']) : 0;
        $currentType = isset($_GET['entity_type']) ? sanitize_key($_GET['entity_type']) : '';

        ?>
        <div class="alignleft actions">
            <select name="saga_id" id="filter-by-saga">
                <option value=""><?php esc_html_e('All Sagas', 'saga-manager'); ?></option>
                <?php foreach ($sagas as $saga): ?>
                    <option value="<?php echo absint($saga['id']); ?>" <?php selected($currentSagaId, (int) $saga['id']); ?>>
                        <?php echo esc_html($saga['name']); ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <select name="entity_type" id="filter-by-type">
                <option value=""><?php esc_html_e('All Types', 'saga-manager'); ?></option>
                <?php foreach (EntityType::cases() as $type): ?>
                    <option value="<?php echo esc_attr($type->value); ?>" <?php selected($currentType, $type->value); ?>>
                        <?php echo esc_html($type->label()); ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <?php submit_button(__('Filter', 'saga-manager'), '', 'filter_action', false); ?>
        </div>
        <?php
    }

    /**
     * Message for no items
     */
    public function no_items(): void
    {
        esc_html_e('No entities found.', 'saga-manager');
    }

    /**
     * Prepare items for display
     */
    public function prepare_items(): void
    {
        global $wpdb;

        $entitiesTable = $this->tablePrefix . 'entities';
        $sagasTable = $this->tablePrefix . 'sagas';

        // Pagination
        $perPage = $this->get_items_per_page('saga_entities_per_page', 20);
        $currentPage = $this->get_pagenum();
        $offset = ($currentPage - 1) * $perPage;

        // Build WHERE clause
        $where = ['1=1'];
        $params = [];

        // Filter by saga
        if (!empty($_GET['saga_id'])) {
            $where[] = 'e.saga_id = %d';
            $params[] = absint($_GET['saga_id']);
        }

        // Filter by entity type
        if (!empty($_GET['entity_type'])) {
            $where[] = 'e.entity_type = %s';
            $params[] = sanitize_key($_GET['entity_type']);
        }

        // Search
        if (!empty($_GET['s'])) {
            $search = '%' . $wpdb->esc_like(sanitize_text_field($_GET['s'])) . '%';
            $where[] = '(e.canonical_name LIKE %s OR e.slug LIKE %s)';
            $params[] = $search;
            $params[] = $search;
        }

        $whereClause = implode(' AND ', $where);

        // Ordering
        $orderby = isset($_GET['orderby']) ? sanitize_key($_GET['orderby']) : 'importance_score';
        $order = isset($_GET['order']) && strtoupper($_GET['order']) === 'ASC' ? 'ASC' : 'DESC';

        // Validate orderby column
        $allowedColumns = ['canonical_name', 'entity_type', 'saga_name', 'importance_score', 'created_at'];
        if (!in_array($orderby, $allowedColumns, true)) {
            $orderby = 'importance_score';
        }

        // Map column to table alias
        $orderbyColumn = match ($orderby) {
            'saga_name' => 's.name',
            default => 'e.' . $orderby,
        };

        // Count total items
        $countSql = "SELECT COUNT(*) FROM {$entitiesTable} e LEFT JOIN {$sagasTable} s ON e.saga_id = s.id WHERE {$whereClause}";
        if (!empty($params)) {
            $countSql = $wpdb->prepare($countSql, ...$params);
        }
        $totalItems = (int) $wpdb->get_var($countSql);

        // Get items
        $sql = "SELECT e.*, s.name as saga_name
                FROM {$entitiesTable} e
                LEFT JOIN {$sagasTable} s ON e.saga_id = s.id
                WHERE {$whereClause}
                ORDER BY {$orderbyColumn} {$order}
                LIMIT %d OFFSET %d";

        $params[] = $perPage;
        $params[] = $offset;

        $sql = $wpdb->prepare($sql, ...$params);
        $this->items = $wpdb->get_results($sql, ARRAY_A);

        // Set up pagination
        $this->set_pagination_args([
            'total_items' => $totalItems,
            'per_page' => $perPage,
            'total_pages' => ceil($totalItems / $perPage),
        ]);

        // Set column headers
        $this->_column_headers = [
            $this->get_columns(),
            [], // Hidden columns
            $this->get_sortable_columns(),
        ];
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

        // Single delete via row action
        if ($action === 'delete' && isset($_GET['id'])) {
            $entityId = absint($_GET['id']);

            if (!wp_verify_nonce($_GET['_wpnonce'] ?? '', 'saga_delete_entity_' . $entityId)) {
                wp_die(__('Security check failed.', 'saga-manager'));
            }

            global $wpdb;
            $table = $wpdb->prefix . 'saga_entities';

            $wpdb->delete($table, ['id' => $entityId], ['%d']);

            wp_safe_redirect(add_query_arg([
                'page' => 'saga-manager-entities',
                'deleted' => 1,
            ], admin_url('admin.php')));
            exit;
        }
    }
}
