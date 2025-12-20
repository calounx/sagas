<?php
declare(strict_types=1);

/**
 * Sagas List View
 *
 * @var SagaManager\Infrastructure\Repository\MariaDBEntityRepository $entityRepository
 */

use SagaManager\Presentation\Admin\ListTable\SagaListTable;

if (!defined('ABSPATH')) {
    exit;
}

// Display admin notices
$notices = get_transient('saga_admin_notices') ?: [];
delete_transient('saga_admin_notices');

// Check for success messages from URL params
if (isset($_GET['deleted']) && $_GET['deleted'] === '1') {
    $notices[] = ['message' => __('Saga deleted successfully.', 'saga-manager'), 'type' => 'success'];
}

// Create list table instance
$listTable = new SagaListTable();
$listTable->process_bulk_action();
$listTable->prepare_items();
?>

<div class="wrap saga-manager-sagas">
    <h1 class="wp-heading-inline"><?php esc_html_e('Sagas', 'saga-manager'); ?></h1>
    <a href="<?php echo esc_url(admin_url('admin.php?page=saga-manager-sagas&action=add')); ?>" class="page-title-action">
        <?php esc_html_e('Add New', 'saga-manager'); ?>
    </a>
    <hr class="wp-header-end">

    <?php foreach ($notices as $notice): ?>
        <div class="notice notice-<?php echo esc_attr($notice['type']); ?> is-dismissible">
            <p><?php echo esc_html($notice['message']); ?></p>
        </div>
    <?php endforeach; ?>

    <form id="sagas-filter" method="get">
        <input type="hidden" name="page" value="saga-manager-sagas" />

        <?php
        $listTable->search_box(__('Search Sagas', 'saga-manager'), 'saga-saga');
        $listTable->display();
        ?>
    </form>
</div>
