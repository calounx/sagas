<?php
declare(strict_types=1);

/**
 * Relationships List View
 *
 * @var SagaManager\Infrastructure\Repository\MariaDBEntityRepository $entityRepository
 */

if (!defined('ABSPATH')) {
    exit;
}

global $wpdb;

// Display admin notices
$notices = get_transient('saga_admin_notices') ?: [];
delete_transient('saga_admin_notices');

// Get filter values
$sagaId = isset($_GET['saga_id']) ? absint($_GET['saga_id']) : 0;
$relationshipType = isset($_GET['relationship_type']) ? sanitize_text_field($_GET['relationship_type']) : '';
$search = isset($_GET['s']) ? sanitize_text_field($_GET['s']) : '';

// Table names
$entitiesTable = $wpdb->prefix . 'saga_entities';
$relationshipsTable = $wpdb->prefix . 'saga_entity_relationships';
$sagasTable = $wpdb->prefix . 'saga_sagas';

// Pagination
$perPage = 20;
$currentPage = isset($_GET['paged']) ? absint($_GET['paged']) : 1;
$offset = ($currentPage - 1) * $perPage;

// Build WHERE clause
$where = ['1=1'];
$params = [];

if ($sagaId) {
    $where[] = 'source.saga_id = %d';
    $params[] = $sagaId;
}

if ($relationshipType) {
    $where[] = 'r.relationship_type = %s';
    $params[] = $relationshipType;
}

if ($search) {
    $searchLike = '%' . $wpdb->esc_like($search) . '%';
    $where[] = '(source.canonical_name LIKE %s OR target.canonical_name LIKE %s)';
    $params[] = $searchLike;
    $params[] = $searchLike;
}

$whereClause = implode(' AND ', $where);

// Count total
$countSql = "SELECT COUNT(*) FROM {$relationshipsTable} r
             JOIN {$entitiesTable} source ON r.source_entity_id = source.id
             JOIN {$entitiesTable} target ON r.target_entity_id = target.id
             WHERE {$whereClause}";
if (!empty($params)) {
    $countSql = $wpdb->prepare($countSql, ...$params);
}
$totalItems = (int) $wpdb->get_var($countSql);
$totalPages = ceil($totalItems / $perPage);

// Get relationships
$sql = "SELECT r.*,
               source.canonical_name as source_name,
               source.entity_type as source_type,
               target.canonical_name as target_name,
               target.entity_type as target_type,
               s.name as saga_name
        FROM {$relationshipsTable} r
        JOIN {$entitiesTable} source ON r.source_entity_id = source.id
        JOIN {$entitiesTable} target ON r.target_entity_id = target.id
        LEFT JOIN {$sagasTable} s ON source.saga_id = s.id
        WHERE {$whereClause}
        ORDER BY r.created_at DESC
        LIMIT %d OFFSET %d";

$params[] = $perPage;
$params[] = $offset;

$sql = $wpdb->prepare($sql, ...$params);
$relationships = $wpdb->get_results($sql, ARRAY_A);

// Get unique relationship types for filter
$relationshipTypes = $wpdb->get_col("SELECT DISTINCT relationship_type FROM {$relationshipsTable} ORDER BY relationship_type ASC");

// Get sagas for filter
$sagas = $wpdb->get_results("SELECT id, name FROM {$sagasTable} ORDER BY name ASC", ARRAY_A);
?>

<div class="wrap saga-manager-relationships">
    <h1 class="wp-heading-inline"><?php esc_html_e('Relationships', 'saga-manager'); ?></h1>
    <hr class="wp-header-end">

    <?php foreach ($notices as $notice): ?>
        <div class="notice notice-<?php echo esc_attr($notice['type']); ?> is-dismissible">
            <p><?php echo esc_html($notice['message']); ?></p>
        </div>
    <?php endforeach; ?>

    <div class="notice notice-info">
        <p>
            <?php esc_html_e('Relationships are currently managed through the REST API or direct database operations. A visual relationship editor is planned for a future release.', 'saga-manager'); ?>
        </p>
    </div>

    <form method="get">
        <input type="hidden" name="page" value="saga-manager-relationships" />

        <p class="search-box">
            <label class="screen-reader-text" for="relationship-search-input"><?php esc_html_e('Search Relationships', 'saga-manager'); ?></label>
            <input type="search" id="relationship-search-input" name="s" value="<?php echo esc_attr($search); ?>" />
            <input type="submit" id="search-submit" class="button" value="<?php esc_attr_e('Search', 'saga-manager'); ?>" />
        </p>

        <div class="tablenav top">
            <div class="alignleft actions">
                <select name="saga_id" id="filter-by-saga">
                    <option value=""><?php esc_html_e('All Sagas', 'saga-manager'); ?></option>
                    <?php foreach ($sagas as $saga): ?>
                        <option value="<?php echo absint($saga['id']); ?>" <?php selected($sagaId, (int) $saga['id']); ?>>
                            <?php echo esc_html($saga['name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>

                <select name="relationship_type" id="filter-by-type">
                    <option value=""><?php esc_html_e('All Types', 'saga-manager'); ?></option>
                    <?php foreach ($relationshipTypes as $type): ?>
                        <option value="<?php echo esc_attr($type); ?>" <?php selected($relationshipType, $type); ?>>
                            <?php echo esc_html(ucfirst(str_replace('_', ' ', $type))); ?>
                        </option>
                    <?php endforeach; ?>
                </select>

                <input type="submit" class="button" value="<?php esc_attr_e('Filter', 'saga-manager'); ?>" />
            </div>

            <div class="tablenav-pages">
                <span class="displaying-num">
                    <?php printf(
                        esc_html(_n('%s item', '%s items', $totalItems, 'saga-manager')),
                        number_format_i18n($totalItems)
                    ); ?>
                </span>
                <?php if ($totalPages > 1): ?>
                    <span class="pagination-links">
                        <?php
                        $pageLinks = paginate_links([
                            'base' => add_query_arg('paged', '%#%'),
                            'format' => '',
                            'prev_text' => '&laquo;',
                            'next_text' => '&raquo;',
                            'total' => $totalPages,
                            'current' => $currentPage,
                        ]);
                        echo $pageLinks;
                        ?>
                    </span>
                <?php endif; ?>
            </div>
        </div>

        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th scope="col" class="manage-column column-source"><?php esc_html_e('Source Entity', 'saga-manager'); ?></th>
                    <th scope="col" class="manage-column column-type"><?php esc_html_e('Relationship', 'saga-manager'); ?></th>
                    <th scope="col" class="manage-column column-target"><?php esc_html_e('Target Entity', 'saga-manager'); ?></th>
                    <th scope="col" class="manage-column column-strength"><?php esc_html_e('Strength', 'saga-manager'); ?></th>
                    <th scope="col" class="manage-column column-dates"><?php esc_html_e('Valid Period', 'saga-manager'); ?></th>
                    <th scope="col" class="manage-column column-saga"><?php esc_html_e('Saga', 'saga-manager'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($relationships)): ?>
                    <tr>
                        <td colspan="6"><?php esc_html_e('No relationships found.', 'saga-manager'); ?></td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($relationships as $rel): ?>
                        <tr>
                            <td class="column-source">
                                <a href="<?php echo esc_url(admin_url('admin.php?page=saga-manager-entities&action=edit&id=' . $rel['source_entity_id'])); ?>">
                                    <?php echo esc_html($rel['source_name']); ?>
                                </a>
                                <span class="saga-type-badge saga-type-<?php echo esc_attr($rel['source_type']); ?>">
                                    <?php echo esc_html(ucfirst($rel['source_type'])); ?>
                                </span>
                            </td>
                            <td class="column-type">
                                <span class="saga-relationship-type">
                                    <?php echo esc_html(ucfirst(str_replace('_', ' ', $rel['relationship_type']))); ?>
                                </span>
                            </td>
                            <td class="column-target">
                                <a href="<?php echo esc_url(admin_url('admin.php?page=saga-manager-entities&action=edit&id=' . $rel['target_entity_id'])); ?>">
                                    <?php echo esc_html($rel['target_name']); ?>
                                </a>
                                <span class="saga-type-badge saga-type-<?php echo esc_attr($rel['target_type']); ?>">
                                    <?php echo esc_html(ucfirst($rel['target_type'])); ?>
                                </span>
                            </td>
                            <td class="column-strength">
                                <?php
                                $strength = absint($rel['strength']);
                                $color = $strength >= 75 ? '#00a32a' : ($strength >= 50 ? '#dba617' : ($strength >= 25 ? '#d63638' : '#787c82'));
                                ?>
                                <div class="saga-strength-bar">
                                    <div class="saga-strength-fill" style="width: <?php echo $strength; ?>%; background-color: <?php echo esc_attr($color); ?>;"></div>
                                </div>
                                <span class="saga-strength-value"><?php echo $strength; ?>%</span>
                            </td>
                            <td class="column-dates">
                                <?php if ($rel['valid_from'] || $rel['valid_until']): ?>
                                    <?php
                                    $from = $rel['valid_from'] ?: '...';
                                    $until = $rel['valid_until'] ?: '...';
                                    echo esc_html("{$from} to {$until}");
                                    ?>
                                <?php else: ?>
                                    <em><?php esc_html_e('Perpetual', 'saga-manager'); ?></em>
                                <?php endif; ?>
                            </td>
                            <td class="column-saga">
                                <?php echo esc_html($rel['saga_name'] ?? 'Unknown'); ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>

        <div class="tablenav bottom">
            <div class="tablenav-pages">
                <?php if ($totalPages > 1): ?>
                    <span class="pagination-links">
                        <?php echo $pageLinks; ?>
                    </span>
                <?php endif; ?>
            </div>
        </div>
    </form>
</div>
