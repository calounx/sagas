<?php
declare(strict_types=1);

/**
 * Dashboard View
 *
 * @var SagaManager\Infrastructure\Repository\MariaDBEntityRepository $entityRepository
 */

if (!defined('ABSPATH')) {
    exit;
}

global $wpdb;

// Get statistics
$sagasTable = $wpdb->prefix . 'saga_sagas';
$entitiesTable = $wpdb->prefix . 'saga_entities';
$relationshipsTable = $wpdb->prefix . 'saga_entity_relationships';
$timelineTable = $wpdb->prefix . 'saga_timeline_events';

$totalSagas = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$sagasTable}");
$totalEntities = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$entitiesTable}");
$totalRelationships = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$relationshipsTable}");
$totalTimelineEvents = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$timelineTable}");

// Get entity counts by type
$entityCounts = $wpdb->get_results(
    "SELECT entity_type, COUNT(*) as count FROM {$entitiesTable} GROUP BY entity_type ORDER BY count DESC",
    ARRAY_A
);

// Get recent entities
$recentEntities = $wpdb->get_results(
    "SELECT e.*, s.name as saga_name
     FROM {$entitiesTable} e
     LEFT JOIN {$sagasTable} s ON e.saga_id = s.id
     ORDER BY e.created_at DESC
     LIMIT 5",
    ARRAY_A
);

// Display admin notices
$notices = get_transient('saga_admin_notices') ?: [];
delete_transient('saga_admin_notices');
?>

<div class="wrap saga-manager-dashboard">
    <h1 class="wp-heading-inline"><?php esc_html_e('Saga Manager Dashboard', 'saga-manager'); ?></h1>
    <hr class="wp-header-end">

    <?php foreach ($notices as $notice): ?>
        <div class="notice notice-<?php echo esc_attr($notice['type']); ?> is-dismissible">
            <p><?php echo esc_html($notice['message']); ?></p>
        </div>
    <?php endforeach; ?>

    <div class="saga-dashboard-stats">
        <div class="saga-stat-box">
            <span class="saga-stat-icon dashicons dashicons-book-alt"></span>
            <div class="saga-stat-content">
                <span class="saga-stat-number"><?php echo number_format_i18n($totalSagas); ?></span>
                <span class="saga-stat-label"><?php esc_html_e('Sagas', 'saga-manager'); ?></span>
            </div>
            <a href="<?php echo esc_url(admin_url('admin.php?page=saga-manager-sagas')); ?>" class="saga-stat-link">
                <?php esc_html_e('View All', 'saga-manager'); ?>
            </a>
        </div>

        <div class="saga-stat-box">
            <span class="saga-stat-icon dashicons dashicons-groups"></span>
            <div class="saga-stat-content">
                <span class="saga-stat-number"><?php echo number_format_i18n($totalEntities); ?></span>
                <span class="saga-stat-label"><?php esc_html_e('Entities', 'saga-manager'); ?></span>
            </div>
            <a href="<?php echo esc_url(admin_url('admin.php?page=saga-manager-entities')); ?>" class="saga-stat-link">
                <?php esc_html_e('View All', 'saga-manager'); ?>
            </a>
        </div>

        <div class="saga-stat-box">
            <span class="saga-stat-icon dashicons dashicons-networking"></span>
            <div class="saga-stat-content">
                <span class="saga-stat-number"><?php echo number_format_i18n($totalRelationships); ?></span>
                <span class="saga-stat-label"><?php esc_html_e('Relationships', 'saga-manager'); ?></span>
            </div>
            <a href="<?php echo esc_url(admin_url('admin.php?page=saga-manager-relationships')); ?>" class="saga-stat-link">
                <?php esc_html_e('View All', 'saga-manager'); ?>
            </a>
        </div>

        <div class="saga-stat-box">
            <span class="saga-stat-icon dashicons dashicons-calendar-alt"></span>
            <div class="saga-stat-content">
                <span class="saga-stat-number"><?php echo number_format_i18n($totalTimelineEvents); ?></span>
                <span class="saga-stat-label"><?php esc_html_e('Timeline Events', 'saga-manager'); ?></span>
            </div>
            <a href="<?php echo esc_url(admin_url('admin.php?page=saga-manager-timeline')); ?>" class="saga-stat-link">
                <?php esc_html_e('View All', 'saga-manager'); ?>
            </a>
        </div>
    </div>

    <div class="saga-dashboard-columns">
        <div class="saga-dashboard-column saga-dashboard-main">
            <div class="saga-dashboard-widget">
                <h2><?php esc_html_e('Recent Entities', 'saga-manager'); ?></h2>
                <?php if (empty($recentEntities)): ?>
                    <p class="saga-empty-state">
                        <?php esc_html_e('No entities yet.', 'saga-manager'); ?>
                        <a href="<?php echo esc_url(admin_url('admin.php?page=saga-manager-entities&action=add')); ?>">
                            <?php esc_html_e('Create your first entity', 'saga-manager'); ?>
                        </a>
                    </p>
                <?php else: ?>
                    <table class="wp-list-table widefat fixed striped">
                        <thead>
                            <tr>
                                <th><?php esc_html_e('Name', 'saga-manager'); ?></th>
                                <th><?php esc_html_e('Type', 'saga-manager'); ?></th>
                                <th><?php esc_html_e('Saga', 'saga-manager'); ?></th>
                                <th><?php esc_html_e('Created', 'saga-manager'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recentEntities as $entity): ?>
                                <tr>
                                    <td>
                                        <a href="<?php echo esc_url(admin_url('admin.php?page=saga-manager-entities&action=edit&id=' . $entity['id'])); ?>">
                                            <?php echo esc_html($entity['canonical_name']); ?>
                                        </a>
                                    </td>
                                    <td>
                                        <span class="saga-type-badge saga-type-<?php echo esc_attr($entity['entity_type']); ?>">
                                            <?php echo esc_html(ucfirst($entity['entity_type'])); ?>
                                        </span>
                                    </td>
                                    <td><?php echo esc_html($entity['saga_name'] ?? 'Unknown'); ?></td>
                                    <td>
                                        <?php
                                        $datetime = new DateTime($entity['created_at']);
                                        echo esc_html($datetime->format(get_option('date_format')));
                                        ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>

        <div class="saga-dashboard-column saga-dashboard-sidebar">
            <div class="saga-dashboard-widget">
                <h2><?php esc_html_e('Entities by Type', 'saga-manager'); ?></h2>
                <?php if (empty($entityCounts)): ?>
                    <p class="saga-empty-state"><?php esc_html_e('No data available.', 'saga-manager'); ?></p>
                <?php else: ?>
                    <ul class="saga-entity-type-list">
                        <?php foreach ($entityCounts as $item): ?>
                            <li>
                                <a href="<?php echo esc_url(admin_url('admin.php?page=saga-manager-entities&entity_type=' . $item['entity_type'])); ?>">
                                    <span class="saga-type-badge saga-type-<?php echo esc_attr($item['entity_type']); ?>">
                                        <?php echo esc_html(ucfirst($item['entity_type'])); ?>
                                    </span>
                                    <span class="saga-type-count"><?php echo number_format_i18n((int) $item['count']); ?></span>
                                </a>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>

            <div class="saga-dashboard-widget">
                <h2><?php esc_html_e('Quick Actions', 'saga-manager'); ?></h2>
                <ul class="saga-quick-actions">
                    <li>
                        <a href="<?php echo esc_url(admin_url('admin.php?page=saga-manager-sagas&action=add')); ?>" class="button button-primary">
                            <span class="dashicons dashicons-plus-alt2"></span>
                            <?php esc_html_e('New Saga', 'saga-manager'); ?>
                        </a>
                    </li>
                    <li>
                        <a href="<?php echo esc_url(admin_url('admin.php?page=saga-manager-entities&action=add')); ?>" class="button button-primary">
                            <span class="dashicons dashicons-plus-alt2"></span>
                            <?php esc_html_e('New Entity', 'saga-manager'); ?>
                        </a>
                    </li>
                    <li>
                        <a href="<?php echo esc_url(admin_url('admin.php?page=saga-manager-settings')); ?>" class="button">
                            <span class="dashicons dashicons-admin-settings"></span>
                            <?php esc_html_e('Settings', 'saga-manager'); ?>
                        </a>
                    </li>
                </ul>
            </div>
        </div>
    </div>
</div>
