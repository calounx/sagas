<?php

declare(strict_types=1);

namespace SagaManagerCore\Presentation\Admin\Pages;

use SagaManagerCore\Application\Service\EntityService;
use SagaManagerCore\Presentation\Admin\ListTable\EntityListTable;
use SagaManagerCore\Presentation\Admin\AdminMenuManager;

/**
 * Entity list page using WP_List_Table
 */
final class EntityListPage
{
    private EntityService $entityService;
    private EntityListTable $listTable;

    public function __construct()
    {
        $this->entityService = $GLOBALS['saga_container']->get(EntityService::class);
        $this->listTable = new EntityListTable($this->entityService);
    }

    public function render(): void
    {
        // Process messages
        $this->displayMessages();

        // Prepare items
        $this->listTable->prepare_items();

        // Get current saga filter
        $sagaId = absint($_GET['saga_id'] ?? 0);
        ?>
        <div class="wrap saga-admin-page">
            <h1 class="wp-heading-inline">
                <?php esc_html_e('Entities', 'saga-manager-core'); ?>
            </h1>

            <a href="<?php echo esc_url(AdminMenuManager::getUrl('entities', ['action' => 'new', 'saga_id' => $sagaId])); ?>" class="page-title-action">
                <?php esc_html_e('Add New', 'saga-manager-core'); ?>
            </a>

            <?php if ($sagaId): ?>
                <a href="<?php echo esc_url(AdminMenuManager::getUrl('entities')); ?>" class="page-title-action">
                    <?php esc_html_e('View All', 'saga-manager-core'); ?>
                </a>
            <?php endif; ?>

            <hr class="wp-header-end">

            <?php $this->renderStats(); ?>

            <form id="entities-filter" method="get">
                <input type="hidden" name="page" value="saga-manager-entities">

                <?php
                $this->listTable->search_box(__('Search Entities', 'saga-manager-core'), 'entity');
                $this->listTable->display();
                ?>
            </form>
        </div>

        <style>
            .saga-type-badge {
                display: inline-block;
                padding: 3px 8px;
                border-radius: 3px;
                color: #fff;
                font-size: 11px;
                font-weight: 500;
                text-transform: uppercase;
            }

            .saga-importance-bar {
                position: relative;
                width: 80px;
                height: 20px;
                background: #e5e5e5;
                border-radius: 3px;
                overflow: hidden;
            }

            .saga-importance-fill {
                position: absolute;
                left: 0;
                top: 0;
                height: 100%;
                transition: width 0.3s;
            }

            .saga-importance-bar.high .saga-importance-fill { background: #1e8e3e; }
            .saga-importance-bar.medium .saga-importance-fill { background: #f0b849; }
            .saga-importance-bar.low .saga-importance-fill { background: #d63638; }

            .saga-importance-value {
                position: absolute;
                width: 100%;
                text-align: center;
                line-height: 20px;
                font-size: 11px;
                font-weight: 600;
                color: #1d2327;
            }

            .saga-count-badge {
                display: inline-block;
                min-width: 24px;
                padding: 2px 6px;
                background: #2271b1;
                color: #fff;
                text-align: center;
                border-radius: 10px;
                font-size: 11px;
                text-decoration: none;
            }

            .saga-count-zero {
                color: #999;
            }

            .saga-stats {
                display: flex;
                gap: 20px;
                margin-bottom: 20px;
                padding: 15px;
                background: #fff;
                border: 1px solid #c3c4c7;
                border-radius: 4px;
            }

            .saga-stat {
                text-align: center;
            }

            .saga-stat-value {
                font-size: 24px;
                font-weight: 600;
                color: #1d2327;
            }

            .saga-stat-label {
                font-size: 12px;
                color: #666;
            }
        </style>
        <?php
    }

    private function renderStats(): void
    {
        $stats = $this->entityService->getStats(absint($_GET['saga_id'] ?? 0) ?: null);
        ?>
        <div class="saga-stats">
            <div class="saga-stat">
                <div class="saga-stat-value"><?php echo esc_html(number_format($stats['total'])); ?></div>
                <div class="saga-stat-label"><?php esc_html_e('Total Entities', 'saga-manager-core'); ?></div>
            </div>
            <?php foreach ($stats['by_type'] as $type => $count): ?>
                <div class="saga-stat">
                    <div class="saga-stat-value"><?php echo esc_html(number_format($count)); ?></div>
                    <div class="saga-stat-label"><?php echo esc_html(ucfirst($type) . 's'); ?></div>
                </div>
            <?php endforeach; ?>
            <div class="saga-stat">
                <div class="saga-stat-value"><?php echo esc_html(number_format($stats['relationships'])); ?></div>
                <div class="saga-stat-label"><?php esc_html_e('Relationships', 'saga-manager-core'); ?></div>
            </div>
        </div>
        <?php
    }

    private function displayMessages(): void
    {
        $message = sanitize_key($_GET['message'] ?? '');

        if (!$message) {
            return;
        }

        $messages = [
            'created' => __('Entity created successfully.', 'saga-manager-core'),
            'updated' => __('Entity updated successfully.', 'saga-manager-core'),
            'deleted' => __('Entity deleted successfully.', 'saga-manager-core'),
            'bulk_deleted' => __('Entities deleted successfully.', 'saga-manager-core'),
        ];

        if (isset($messages[$message])) {
            echo '<div class="notice notice-success is-dismissible"><p>' . esc_html($messages[$message]) . '</p></div>';
        }
    }
}
