<?php
declare(strict_types=1);

/**
 * Timeline Events List View
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
$search = isset($_GET['s']) ? sanitize_text_field($_GET['s']) : '';

// Table names
$timelineTable = $wpdb->prefix . 'saga_timeline_events';
$sagasTable = $wpdb->prefix . 'saga_sagas';
$entitiesTable = $wpdb->prefix . 'saga_entities';

// Pagination
$perPage = 20;
$currentPage = isset($_GET['paged']) ? absint($_GET['paged']) : 1;
$offset = ($currentPage - 1) * $perPage;

// Build WHERE clause
$where = ['1=1'];
$params = [];

if ($sagaId) {
    $where[] = 't.saga_id = %d';
    $params[] = $sagaId;
}

if ($search) {
    $searchLike = '%' . $wpdb->esc_like($search) . '%';
    $where[] = '(t.title LIKE %s OR t.description LIKE %s OR t.canon_date LIKE %s)';
    $params[] = $searchLike;
    $params[] = $searchLike;
    $params[] = $searchLike;
}

$whereClause = implode(' AND ', $where);

// Count total
$countSql = "SELECT COUNT(*) FROM {$timelineTable} t WHERE {$whereClause}";
if (!empty($params)) {
    $countSql = $wpdb->prepare($countSql, ...$params);
}
$totalItems = (int) $wpdb->get_var($countSql);
$totalPages = ceil($totalItems / $perPage);

// Get events
$sql = "SELECT t.*, s.name as saga_name, e.canonical_name as event_entity_name
        FROM {$timelineTable} t
        LEFT JOIN {$sagasTable} s ON t.saga_id = s.id
        LEFT JOIN {$entitiesTable} e ON t.event_entity_id = e.id
        WHERE {$whereClause}
        ORDER BY t.normalized_timestamp ASC
        LIMIT %d OFFSET %d";

$params[] = $perPage;
$params[] = $offset;

$sql = $wpdb->prepare($sql, ...$params);
$events = $wpdb->get_results($sql, ARRAY_A);

// Get sagas for filter
$sagas = $wpdb->get_results("SELECT id, name FROM {$sagasTable} ORDER BY name ASC", ARRAY_A);
?>

<div class="wrap saga-manager-timeline">
    <h1 class="wp-heading-inline"><?php esc_html_e('Timeline Events', 'saga-manager'); ?></h1>
    <hr class="wp-header-end">

    <?php foreach ($notices as $notice): ?>
        <div class="notice notice-<?php echo esc_attr($notice['type']); ?> is-dismissible">
            <p><?php echo esc_html($notice['message']); ?></p>
        </div>
    <?php endforeach; ?>

    <div class="notice notice-info">
        <p>
            <?php esc_html_e('Timeline events are currently managed through the REST API or direct database operations. A visual timeline editor is planned for a future release.', 'saga-manager'); ?>
        </p>
    </div>

    <form method="get">
        <input type="hidden" name="page" value="saga-manager-timeline" />

        <p class="search-box">
            <label class="screen-reader-text" for="timeline-search-input"><?php esc_html_e('Search Timeline', 'saga-manager'); ?></label>
            <input type="search" id="timeline-search-input" name="s" value="<?php echo esc_attr($search); ?>" />
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

                <input type="submit" class="button" value="<?php esc_attr_e('Filter', 'saga-manager'); ?>" />
            </div>

            <div class="tablenav-pages">
                <span class="displaying-num">
                    <?php printf(
                        esc_html(_n('%s event', '%s events', $totalItems, 'saga-manager')),
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
                    <th scope="col" class="manage-column column-date" style="width: 150px;"><?php esc_html_e('Canon Date', 'saga-manager'); ?></th>
                    <th scope="col" class="manage-column column-title"><?php esc_html_e('Title', 'saga-manager'); ?></th>
                    <th scope="col" class="manage-column column-description"><?php esc_html_e('Description', 'saga-manager'); ?></th>
                    <th scope="col" class="manage-column column-entity"><?php esc_html_e('Linked Event', 'saga-manager'); ?></th>
                    <th scope="col" class="manage-column column-participants"><?php esc_html_e('Participants', 'saga-manager'); ?></th>
                    <th scope="col" class="manage-column column-saga"><?php esc_html_e('Saga', 'saga-manager'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($events)): ?>
                    <tr>
                        <td colspan="6"><?php esc_html_e('No timeline events found.', 'saga-manager'); ?></td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($events as $event): ?>
                        <tr>
                            <td class="column-date">
                                <span class="saga-canon-date"><?php echo esc_html($event['canon_date']); ?></span>
                            </td>
                            <td class="column-title">
                                <strong><?php echo esc_html($event['title']); ?></strong>
                            </td>
                            <td class="column-description">
                                <?php
                                $description = $event['description'] ?? '';
                                if (strlen($description) > 100) {
                                    echo esc_html(substr($description, 0, 100)) . '...';
                                } else {
                                    echo esc_html($description);
                                }
                                ?>
                            </td>
                            <td class="column-entity">
                                <?php if ($event['event_entity_id']): ?>
                                    <a href="<?php echo esc_url(admin_url('admin.php?page=saga-manager-entities&action=edit&id=' . $event['event_entity_id'])); ?>">
                                        <?php echo esc_html($event['event_entity_name']); ?>
                                    </a>
                                <?php else: ?>
                                    <em><?php esc_html_e('None', 'saga-manager'); ?></em>
                                <?php endif; ?>
                            </td>
                            <td class="column-participants">
                                <?php
                                $participants = json_decode($event['participants'] ?? '[]', true);
                                if (!empty($participants)) {
                                    $participantCount = count($participants);
                                    printf(
                                        esc_html(_n('%d participant', '%d participants', $participantCount, 'saga-manager')),
                                        $participantCount
                                    );
                                } else {
                                    echo '<em>' . esc_html__('None', 'saga-manager') . '</em>';
                                }
                                ?>
                            </td>
                            <td class="column-saga">
                                <?php echo esc_html($event['saga_name'] ?? 'Unknown'); ?>
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
