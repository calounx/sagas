<?php
declare(strict_types=1);

/**
 * Saga Add/Edit Form View
 *
 * @var SagaManager\Infrastructure\Repository\MariaDBEntityRepository $entityRepository
 * @var array|null $saga Saga data array or null for new
 * @var string $action 'create' or 'update'
 */

if (!defined('ABSPATH')) {
    exit;
}

// Display admin notices
$notices = get_transient('saga_admin_notices') ?: [];
delete_transient('saga_admin_notices');

$isEdit = $action === 'update';
$pageTitle = $isEdit ? __('Edit Saga', 'saga-manager') : __('Add New Saga', 'saga-manager');
$submitText = $isEdit ? __('Update Saga', 'saga-manager') : __('Create Saga', 'saga-manager');
$nonceAction = $isEdit ? 'saga_update_saga' : 'saga_create_saga';
$formAction = $isEdit ? 'update_saga' : 'create_saga';

// Parse calendar config
$calendarConfig = [];
if ($saga && isset($saga['calendar_config'])) {
    $calendarConfig = json_decode($saga['calendar_config'], true) ?: [];
}

$calendarTypes = [
    'absolute' => __('Absolute (Real-world dates)', 'saga-manager'),
    'epoch_relative' => __('Epoch Relative (Before/After epoch)', 'saga-manager'),
    'age_based' => __('Age Based (Named ages/eras)', 'saga-manager'),
];
?>

<div class="wrap saga-manager-saga-form">
    <h1 class="wp-heading-inline"><?php echo esc_html($pageTitle); ?></h1>
    <a href="<?php echo esc_url(admin_url('admin.php?page=saga-manager-sagas')); ?>" class="page-title-action">
        <?php esc_html_e('Back to List', 'saga-manager'); ?>
    </a>
    <hr class="wp-header-end">

    <?php foreach ($notices as $notice): ?>
        <div class="notice notice-<?php echo esc_attr($notice['type']); ?> is-dismissible">
            <p><?php echo esc_html($notice['message']); ?></p>
        </div>
    <?php endforeach; ?>

    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" id="saga-saga-form" class="saga-form">
        <input type="hidden" name="action" value="saga_form_handler" />
        <input type="hidden" name="saga_manager_action" value="<?php echo esc_attr($formAction); ?>" />
        <?php wp_nonce_field($nonceAction, '_saga_nonce'); ?>

        <?php if ($isEdit): ?>
            <input type="hidden" name="saga_id" value="<?php echo absint($saga['id']); ?>" />
        <?php endif; ?>

        <div id="poststuff">
            <div id="post-body" class="metabox-holder columns-2">

                <!-- Main Content -->
                <div id="post-body-content">
                    <div id="titlediv">
                        <div id="titlewrap">
                            <label class="screen-reader-text" for="name"><?php esc_html_e('Saga Name', 'saga-manager'); ?></label>
                            <input type="text" name="name" id="name" class="saga-title-input"
                                   value="<?php echo esc_attr($saga['name'] ?? ''); ?>"
                                   placeholder="<?php esc_attr_e('Enter saga name', 'saga-manager'); ?>"
                                   required />
                        </div>
                    </div>

                    <div class="postbox">
                        <h2 class="hndle"><?php esc_html_e('Saga Details', 'saga-manager'); ?></h2>
                        <div class="inside">
                            <table class="form-table" role="presentation">
                                <tr>
                                    <th scope="row">
                                        <label for="universe"><?php esc_html_e('Universe', 'saga-manager'); ?></label>
                                    </th>
                                    <td>
                                        <input type="text" name="universe" id="universe" class="regular-text"
                                               value="<?php echo esc_attr($saga['universe'] ?? ''); ?>"
                                               placeholder="<?php esc_attr_e('e.g., Star Wars, Dune, Lord of the Rings', 'saga-manager'); ?>" />
                                        <p class="description">
                                            <?php esc_html_e('The fictional universe this saga belongs to.', 'saga-manager'); ?>
                                        </p>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row">
                                        <label for="calendar_type"><?php esc_html_e('Calendar Type', 'saga-manager'); ?></label>
                                    </th>
                                    <td>
                                        <select name="calendar_type" id="calendar_type" class="regular-text">
                                            <?php foreach ($calendarTypes as $value => $label): ?>
                                                <option value="<?php echo esc_attr($value); ?>"
                                                        <?php selected($saga['calendar_type'] ?? 'absolute', $value); ?>>
                                                    <?php echo esc_html($label); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                        <p class="description">
                                            <?php esc_html_e('How dates are represented in this saga.', 'saga-manager'); ?>
                                        </p>
                                    </td>
                                </tr>
                                <tr id="epoch-row" style="<?php echo ($saga['calendar_type'] ?? 'absolute') !== 'epoch_relative' ? 'display:none;' : ''; ?>">
                                    <th scope="row">
                                        <label for="calendar_epoch"><?php esc_html_e('Epoch Reference', 'saga-manager'); ?></label>
                                    </th>
                                    <td>
                                        <input type="text" name="calendar_epoch" id="calendar_epoch" class="regular-text"
                                               value="<?php echo esc_attr($calendarConfig['epoch'] ?? ''); ?>"
                                               placeholder="<?php esc_attr_e('e.g., BBY (Before Battle of Yavin)', 'saga-manager'); ?>" />
                                        <p class="description">
                                            <?php esc_html_e('The reference point for epoch-relative dates.', 'saga-manager'); ?>
                                        </p>
                                    </td>
                                </tr>
                            </table>
                        </div>
                    </div>

                    <?php if ($isEdit): ?>
                        <div class="postbox">
                            <h2 class="hndle"><?php esc_html_e('Saga Statistics', 'saga-manager'); ?></h2>
                            <div class="inside">
                                <?php
                                global $wpdb;
                                $entitiesTable = $wpdb->prefix . 'saga_entities';
                                $relationshipsTable = $wpdb->prefix . 'saga_entity_relationships';
                                $timelineTable = $wpdb->prefix . 'saga_timeline_events';

                                $entityCount = (int) $wpdb->get_var($wpdb->prepare(
                                    "SELECT COUNT(*) FROM {$entitiesTable} WHERE saga_id = %d",
                                    $saga['id']
                                ));

                                $relationshipCount = (int) $wpdb->get_var($wpdb->prepare(
                                    "SELECT COUNT(*) FROM {$relationshipsTable} r
                                     JOIN {$entitiesTable} e ON r.source_entity_id = e.id
                                     WHERE e.saga_id = %d",
                                    $saga['id']
                                ));

                                $timelineCount = (int) $wpdb->get_var($wpdb->prepare(
                                    "SELECT COUNT(*) FROM {$timelineTable} WHERE saga_id = %d",
                                    $saga['id']
                                ));

                                // Get entity breakdown by type
                                $entityBreakdown = $wpdb->get_results($wpdb->prepare(
                                    "SELECT entity_type, COUNT(*) as count FROM {$entitiesTable} WHERE saga_id = %d GROUP BY entity_type",
                                    $saga['id']
                                ), ARRAY_A);
                                ?>
                                <table class="widefat striped">
                                    <tbody>
                                        <tr>
                                            <td><strong><?php esc_html_e('Total Entities', 'saga-manager'); ?></strong></td>
                                            <td>
                                                <a href="<?php echo esc_url(admin_url('admin.php?page=saga-manager-entities&saga_id=' . $saga['id'])); ?>">
                                                    <?php echo number_format_i18n($entityCount); ?>
                                                </a>
                                            </td>
                                        </tr>
                                        <?php foreach ($entityBreakdown as $item): ?>
                                            <tr>
                                                <td style="padding-left: 30px;">
                                                    <?php echo esc_html(ucfirst($item['entity_type'])); ?>
                                                </td>
                                                <td>
                                                    <a href="<?php echo esc_url(admin_url('admin.php?page=saga-manager-entities&saga_id=' . $saga['id'] . '&entity_type=' . $item['entity_type'])); ?>">
                                                        <?php echo number_format_i18n((int) $item['count']); ?>
                                                    </a>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                        <tr>
                                            <td><strong><?php esc_html_e('Total Relationships', 'saga-manager'); ?></strong></td>
                                            <td><?php echo number_format_i18n($relationshipCount); ?></td>
                                        </tr>
                                        <tr>
                                            <td><strong><?php esc_html_e('Timeline Events', 'saga-manager'); ?></strong></td>
                                            <td><?php echo number_format_i18n($timelineCount); ?></td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Sidebar -->
                <div id="postbox-container-1" class="postbox-container">
                    <div id="submitdiv" class="postbox">
                        <h2 class="hndle"><?php esc_html_e('Publish', 'saga-manager'); ?></h2>
                        <div class="inside">
                            <div class="submitbox" id="submitpost">
                                <?php if ($isEdit): ?>
                                    <div id="misc-publishing-actions">
                                        <div class="misc-pub-section">
                                            <span class="dashicons dashicons-calendar-alt"></span>
                                            <?php esc_html_e('Created:', 'saga-manager'); ?>
                                            <strong>
                                                <?php
                                                $created = new DateTime($saga['created_at']);
                                                echo esc_html($created->format(get_option('date_format')));
                                                ?>
                                            </strong>
                                        </div>
                                        <div class="misc-pub-section">
                                            <span class="dashicons dashicons-edit"></span>
                                            <?php esc_html_e('Modified:', 'saga-manager'); ?>
                                            <strong>
                                                <?php
                                                $updated = new DateTime($saga['updated_at']);
                                                echo esc_html($updated->format(get_option('date_format')));
                                                ?>
                                            </strong>
                                        </div>
                                    </div>
                                <?php endif; ?>
                                <div id="major-publishing-actions">
                                    <?php if ($isEdit): ?>
                                        <div id="delete-action">
                                            <a href="<?php echo esc_url(wp_nonce_url(
                                                admin_url('admin.php?page=saga-manager-sagas&action=delete&id=' . $saga['id']),
                                                'saga_delete_saga_' . $saga['id']
                                            )); ?>" class="submitdelete deletion" onclick="return confirm('<?php esc_attr_e('Are you sure you want to delete this saga? All associated entities, relationships, and timeline events will also be deleted.', 'saga-manager'); ?>');">
                                                <?php esc_html_e('Delete', 'saga-manager'); ?>
                                            </a>
                                        </div>
                                    <?php endif; ?>
                                    <div id="publishing-action">
                                        <button type="submit" class="button button-primary button-large" id="saga-submit-btn">
                                            <?php echo esc_html($submitText); ?>
                                        </button>
                                    </div>
                                    <div class="clear"></div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="postbox">
                        <h2 class="hndle"><?php esc_html_e('Help', 'saga-manager'); ?></h2>
                        <div class="inside">
                            <h4><?php esc_html_e('Calendar Types', 'saga-manager'); ?></h4>
                            <ul class="saga-help-list">
                                <li>
                                    <strong><?php esc_html_e('Absolute:', 'saga-manager'); ?></strong>
                                    <?php esc_html_e('Real-world dates (years, months, days)', 'saga-manager'); ?>
                                </li>
                                <li>
                                    <strong><?php esc_html_e('Epoch Relative:', 'saga-manager'); ?></strong>
                                    <?php esc_html_e('Dates relative to a significant event (e.g., 5 BBY)', 'saga-manager'); ?>
                                </li>
                                <li>
                                    <strong><?php esc_html_e('Age Based:', 'saga-manager'); ?></strong>
                                    <?php esc_html_e('Named periods or ages (e.g., Third Age)', 'saga-manager'); ?>
                                </li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </form>
</div>

<script>
jQuery(document).ready(function($) {
    // Show/hide epoch field based on calendar type
    $('#calendar_type').on('change', function() {
        if ($(this).val() === 'epoch_relative') {
            $('#epoch-row').show();
        } else {
            $('#epoch-row').hide();
        }
    });
});
</script>
