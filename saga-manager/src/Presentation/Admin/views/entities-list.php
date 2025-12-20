<?php
declare(strict_types=1);

/**
 * Entities List View
 *
 * @var SagaManager\Infrastructure\Repository\MariaDBEntityRepository $entityRepository
 */

use SagaManager\Presentation\Admin\ListTable\EntityListTable;

if (!defined('ABSPATH')) {
    exit;
}

// Display admin notices
$notices = get_transient('saga_admin_notices') ?: [];
delete_transient('saga_admin_notices');

// Check for success messages from URL params
if (isset($_GET['deleted']) && $_GET['deleted'] === '1') {
    $notices[] = ['message' => __('Entity deleted successfully.', 'saga-manager'), 'type' => 'success'];
}

// Create list table instance
$listTable = new EntityListTable();
$listTable->process_bulk_action();
$listTable->prepare_items();
?>

<div class="wrap saga-manager-entities">
    <h1 class="wp-heading-inline"><?php esc_html_e('Entities', 'saga-manager'); ?></h1>
    <a href="<?php echo esc_url(admin_url('admin.php?page=saga-manager-entities&action=add')); ?>" class="page-title-action">
        <?php esc_html_e('Add New', 'saga-manager'); ?>
    </a>
    <hr class="wp-header-end">

    <?php foreach ($notices as $notice): ?>
        <div class="notice notice-<?php echo esc_attr($notice['type']); ?> is-dismissible">
            <p><?php echo esc_html($notice['message']); ?></p>
        </div>
    <?php endforeach; ?>

    <form id="entities-filter" method="get">
        <input type="hidden" name="page" value="saga-manager-entities" />

        <?php
        $listTable->views();
        $listTable->search_box(__('Search Entities', 'saga-manager'), 'saga-entity');
        ?>

        <input type="hidden" name="saga_manager_action" value="bulk_action" />
        <?php wp_nonce_field('saga_bulk_action', '_saga_nonce'); ?>

        <?php $listTable->display(); ?>
    </form>
</div>

<script type="text/html" id="tmpl-saga-entity-quick-edit">
    <div class="saga-quick-edit-form">
        <h4><?php esc_html_e('Quick Edit', 'saga-manager'); ?></h4>
        <label>
            <span><?php esc_html_e('Name:', 'saga-manager'); ?></span>
            <input type="text" name="canonical_name" value="{{ data.canonical_name }}" />
        </label>
        <label>
            <span><?php esc_html_e('Importance:', 'saga-manager'); ?></span>
            <input type="number" name="importance_score" value="{{ data.importance_score }}" min="0" max="100" />
        </label>
        <div class="saga-quick-edit-actions">
            <button type="button" class="button button-primary saga-quick-edit-save"><?php esc_html_e('Save', 'saga-manager'); ?></button>
            <button type="button" class="button saga-quick-edit-cancel"><?php esc_html_e('Cancel', 'saga-manager'); ?></button>
        </div>
    </div>
</script>
