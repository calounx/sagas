<?php
declare(strict_types=1);

namespace SagaManager\Presentation\Admin\ListTable;

if (!class_exists('WP_List_Table')) {
    require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

/**
 * Saga List Table
 *
 * WP_List_Table implementation for sagas
 */
class SagaListTable extends \WP_List_Table
{
    private string $tablePrefix;

    public function __construct()
    {
        global $wpdb;
        $this->tablePrefix = $wpdb->prefix . 'saga_';

        parent::__construct([
            'singular' => 'saga',
            'plural' => 'sagas',
            'ajax' => false,
        ]);
    }

    /**
     * Get table columns
     */
    public function get_columns(): array
    {
        return [
            'cb' => '<input type="checkbox" />',
            'name' => __('Name', 'saga-manager'),
            'universe' => __('Universe', 'saga-manager'),
            'calendar_type' => __('Calendar', 'saga-manager'),
            'entity_count' => __('Entities', 'saga-manager'),
            'created_at' => __('Created', 'saga-manager'),
        ];
    }

    /**
     * Get sortable columns
     */
    public function get_sortable_columns(): array
    {
        return [
            'name' => ['name', false],
            'universe' => ['universe', false],
            'calendar_type' => ['calendar_type', false],
            'entity_count' => ['entity_count', true],
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
        ];
    }

    /**
     * Checkbox column
     */
    public function column_cb($item): string
    {
        return sprintf(
            '<input type="checkbox" name="saga_ids[]" value="%d" />',
            absint($item['id'])
        );
    }

    /**
     * Name column with row actions
     */
    public function column_name($item): string
    {
        $editUrl = add_query_arg([
            'page' => 'saga-manager-sagas',
            'action' => 'edit',
            'id' => $item['id'],
        ], admin_url('admin.php'));

        $deleteUrl = wp_nonce_url(
            add_query_arg([
                'page' => 'saga-manager-sagas',
                'action' => 'delete',
                'id' => $item['id'],
            ], admin_url('admin.php')),
            'saga_delete_saga_' . $item['id']
        );

        $viewEntitiesUrl = add_query_arg([
            'page' => 'saga-manager-entities',
            'saga_id' => $item['id'],
        ], admin_url('admin.php'));

        $actions = [
            'edit' => sprintf(
                '<a href="%s">%s</a>',
                esc_url($editUrl),
                __('Edit', 'saga-manager')
            ),
            'view_entities' => sprintf(
                '<a href="%s">%s</a>',
                esc_url($viewEntitiesUrl),
                __('View Entities', 'saga-manager')
            ),
            'delete' => sprintf(
                '<a href="%s" class="saga-delete-saga" data-id="%d" onclick="return confirm(\'%s\');">%s</a>',
                esc_url($deleteUrl),
                absint($item['id']),
                esc_js(__('Are you sure you want to delete this saga? All associated entities will also be deleted.', 'saga-manager')),
                __('Delete', 'saga-manager')
            ),
        ];

        return sprintf(
            '<strong><a href="%s" class="row-title">%s</a></strong>%s',
            esc_url($editUrl),
            esc_html($item['name']),
            $this->row_actions($actions)
        );
    }

    /**
     * Universe column
     */
    public function column_universe($item): string
    {
        if (empty($item['universe'])) {
            return '<em>' . esc_html__('Not specified', 'saga-manager') . '</em>';
        }

        return sprintf(
            '<span class="saga-universe-badge">%s</span>',
            esc_html($item['universe'])
        );
    }

    /**
     * Calendar type column
     */
    public function column_calendar_type($item): string
    {
        $labels = [
            'absolute' => __('Absolute', 'saga-manager'),
            'epoch_relative' => __('Epoch Relative', 'saga-manager'),
            'age_based' => __('Age Based', 'saga-manager'),
        ];

        $type = $item['calendar_type'] ?? 'absolute';
        $label = $labels[$type] ?? $type;

        // Get epoch from config if applicable
        $config = json_decode($item['calendar_config'] ?? '{}', true);
        $epoch = $config['epoch'] ?? '';

        if ($type === 'epoch_relative' && $epoch) {
            return sprintf(
                '%s <span class="saga-epoch">(%s)</span>',
                esc_html($label),
                esc_html($epoch)
            );
        }

        return esc_html($label);
    }

    /**
     * Entity count column
     */
    public function column_entity_count($item): string
    {
        $count = absint($item['entity_count'] ?? 0);

        if ($count === 0) {
            return '<span class="saga-count-zero">0</span>';
        }

        $viewUrl = add_query_arg([
            'page' => 'saga-manager-entities',
            'saga_id' => $item['id'],
        ], admin_url('admin.php'));

        return sprintf(
            '<a href="%s" class="saga-entity-count">%s</a>',
            esc_url($viewUrl),
            number_format_i18n($count)
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
     * Display extra table navigation (filters)
     */
    public function extra_tablenav($which): void
    {
        if ($which !== 'top') {
            return;
        }

        global $wpdb;
        $sagasTable = $this->tablePrefix . 'sagas';

        // Get unique universes
        $universes = $wpdb->get_col("SELECT DISTINCT universe FROM {$sagasTable} WHERE universe IS NOT NULL AND universe != '' ORDER BY universe ASC");
        $currentUniverse = isset($_GET['universe']) ? sanitize_text_field($_GET['universe']) : '';

        ?>
        <div class="alignleft actions">
            <select name="universe" id="filter-by-universe">
                <option value=""><?php esc_html_e('All Universes', 'saga-manager'); ?></option>
                <?php foreach ($universes as $universe): ?>
                    <option value="<?php echo esc_attr($universe); ?>" <?php selected($currentUniverse, $universe); ?>>
                        <?php echo esc_html($universe); ?>
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
        esc_html_e('No sagas found. Create your first saga to get started!', 'saga-manager');
    }

    /**
     * Prepare items for display
     */
    public function prepare_items(): void
    {
        global $wpdb;

        $sagasTable = $this->tablePrefix . 'sagas';
        $entitiesTable = $this->tablePrefix . 'entities';

        // Pagination
        $perPage = $this->get_items_per_page('saga_sagas_per_page', 20);
        $currentPage = $this->get_pagenum();
        $offset = ($currentPage - 1) * $perPage;

        // Build WHERE clause
        $where = ['1=1'];
        $params = [];

        // Filter by universe
        if (!empty($_GET['universe'])) {
            $where[] = 's.universe = %s';
            $params[] = sanitize_text_field($_GET['universe']);
        }

        // Search
        if (!empty($_GET['s'])) {
            $search = '%' . $wpdb->esc_like(sanitize_text_field($_GET['s'])) . '%';
            $where[] = '(s.name LIKE %s OR s.universe LIKE %s)';
            $params[] = $search;
            $params[] = $search;
        }

        $whereClause = implode(' AND ', $where);

        // Ordering
        $orderby = isset($_GET['orderby']) ? sanitize_key($_GET['orderby']) : 'name';
        $order = isset($_GET['order']) && strtoupper($_GET['order']) === 'DESC' ? 'DESC' : 'ASC';

        // Validate orderby column
        $allowedColumns = ['name', 'universe', 'calendar_type', 'entity_count', 'created_at'];
        if (!in_array($orderby, $allowedColumns, true)) {
            $orderby = 'name';
        }

        // Map column for ORDER BY
        $orderbyColumn = match ($orderby) {
            'entity_count' => 'entity_count',
            default => 's.' . $orderby,
        };

        // Count total items
        $countSql = "SELECT COUNT(*) FROM {$sagasTable} s WHERE {$whereClause}";
        if (!empty($params)) {
            $countSql = $wpdb->prepare($countSql, ...$params);
        }
        $totalItems = (int) $wpdb->get_var($countSql);

        // Get items with entity count
        $sql = "SELECT s.*,
                       (SELECT COUNT(*) FROM {$entitiesTable} e WHERE e.saga_id = s.id) as entity_count
                FROM {$sagasTable} s
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
            $sagaId = absint($_GET['id']);

            if (!wp_verify_nonce($_GET['_wpnonce'] ?? '', 'saga_delete_saga_' . $sagaId)) {
                wp_die(__('Security check failed.', 'saga-manager'));
            }

            global $wpdb;
            $table = $wpdb->prefix . 'saga_sagas';

            $wpdb->delete($table, ['id' => $sagaId], ['%d']);

            wp_safe_redirect(add_query_arg([
                'page' => 'saga-manager-sagas',
                'deleted' => 1,
            ], admin_url('admin.php')));
            exit;
        }
    }
}
