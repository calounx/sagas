<?php
/**
 * AI Consistency Guardian Admin Page
 *
 * Full-featured admin interface for managing consistency issues
 *
 * @package SagaManager\PageTemplates
 * @version 1.4.0
 */

declare(strict_types=1);

use SagaManager\AI\ConsistencyRepository;

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Check permissions
if (!current_user_can('manage_options')) {
    wp_die(__('You do not have sufficient permissions to access this page.', 'saga-manager-theme'));
}

// Get current saga
$currentSagaId = isset($_GET['saga_id']) ? absint($_GET['saga_id']) : 0;

if ($currentSagaId === 0) {
    // Get default saga
    global $wpdb;
    $currentSagaId = (int) $wpdb->get_var("SELECT id FROM {$wpdb->prefix}saga_sagas ORDER BY id ASC LIMIT 1");
}

// Get all sagas for dropdown
$sagas = $wpdb->get_results("SELECT id, name FROM {$wpdb->prefix}saga_sagas ORDER BY name ASC");

// Get statistics
$repository = new ConsistencyRepository();
$stats = $currentSagaId > 0 ? $repository->getStatistics($currentSagaId) : null;
$issuesByType = $currentSagaId > 0 ? $repository->getIssuesByType($currentSagaId, 'open') : [];
?>

<div class="wrap saga-consistency-guardian">
    <h1>
        <span class="dashicons dashicons-shield-alt"></span>
        <?php esc_html_e('AI Consistency Guardian', 'saga-manager-theme'); ?>
    </h1>

    <?php if (empty($sagas)) : ?>
        <div class="notice notice-warning">
            <p>
                <?php esc_html_e('No sagas found. Please create a saga first.', 'saga-manager-theme'); ?>
            </p>
        </div>
    <?php else : ?>

        <!-- Saga Selector -->
        <div class="saga-selector-bar">
            <label for="saga-selector">
                <strong><?php esc_html_e('Saga:', 'saga-manager-theme'); ?></strong>
            </label>
            <select id="saga-selector" name="saga_id">
                <?php foreach ($sagas as $saga) : ?>
                    <option value="<?php echo esc_attr($saga->id); ?>" <?php selected($currentSagaId, $saga->id); ?>>
                        <?php echo esc_html($saga->name); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <?php if ($stats !== null) : ?>

            <!-- Statistics Row -->
            <div class="consistency-stats-row">
                <div class="stat-box stat-critical">
                    <span class="stat-count"><?php echo esc_html($stats['critical_count']); ?></span>
                    <span class="stat-label"><?php esc_html_e('Critical', 'saga-manager-theme'); ?></span>
                </div>
                <div class="stat-box stat-high">
                    <span class="stat-count"><?php echo esc_html($stats['high_count']); ?></span>
                    <span class="stat-label"><?php esc_html_e('High', 'saga-manager-theme'); ?></span>
                </div>
                <div class="stat-box stat-medium">
                    <span class="stat-count"><?php echo esc_html($stats['medium_count']); ?></span>
                    <span class="stat-label"><?php esc_html_e('Medium', 'saga-manager-theme'); ?></span>
                </div>
                <div class="stat-box stat-low">
                    <span class="stat-count"><?php echo esc_html($stats['low_count']); ?></span>
                    <span class="stat-label"><?php esc_html_e('Low', 'saga-manager-theme'); ?></span>
                </div>
                <div class="stat-box stat-total">
                    <span class="stat-count"><?php echo esc_html($stats['open_issues']); ?></span>
                    <span class="stat-label"><?php esc_html_e('Open Issues', 'saga-manager-theme'); ?></span>
                </div>
                <div class="stat-box stat-resolved">
                    <span class="stat-count"><?php echo esc_html($stats['resolved_issues']); ?></span>
                    <span class="stat-label"><?php esc_html_e('Resolved', 'saga-manager-theme'); ?></span>
                </div>
            </div>

            <!-- Charts Row -->
            <div class="consistency-charts-row">
                <div class="chart-container">
                    <h3><?php esc_html_e('Issues by Severity', 'saga-manager-theme'); ?></h3>
                    <canvas id="severity-chart"></canvas>
                </div>
                <div class="chart-container">
                    <h3><?php esc_html_e('Issues by Type', 'saga-manager-theme'); ?></h3>
                    <canvas id="type-chart"></canvas>
                </div>
            </div>

            <!-- Run Scan Section -->
            <div class="scan-section">
                <h2><?php esc_html_e('Run Consistency Scan', 'saga-manager-theme'); ?></h2>
                <div class="scan-controls">
                    <label>
                        <input type="checkbox" id="use-ai-scan" checked>
                        <?php esc_html_e('Use AI-powered analysis (slower but more thorough)', 'saga-manager-theme'); ?>
                    </label>
                    <button type="button" class="button button-primary button-large" id="run-scan-btn">
                        <span class="dashicons dashicons-update"></span>
                        <?php esc_html_e('Run Full Scan', 'saga-manager-theme'); ?>
                    </button>
                </div>
                <div class="scan-progress" style="display: none;">
                    <div class="progress-bar">
                        <div class="progress-fill"></div>
                    </div>
                    <div class="progress-text"><?php esc_html_e('Initializing...', 'saga-manager-theme'); ?></div>
                </div>
            </div>

            <!-- Filters and Actions -->
            <div class="issues-toolbar">
                <div class="filters-group">
                    <select id="filter-status">
                        <option value=""><?php esc_html_e('All Statuses', 'saga-manager-theme'); ?></option>
                        <option value="open" selected><?php esc_html_e('Open', 'saga-manager-theme'); ?></option>
                        <option value="resolved"><?php esc_html_e('Resolved', 'saga-manager-theme'); ?></option>
                        <option value="dismissed"><?php esc_html_e('Dismissed', 'saga-manager-theme'); ?></option>
                        <option value="false_positive"><?php esc_html_e('False Positive', 'saga-manager-theme'); ?></option>
                    </select>

                    <select id="filter-severity">
                        <option value=""><?php esc_html_e('All Severities', 'saga-manager-theme'); ?></option>
                        <option value="critical"><?php esc_html_e('Critical', 'saga-manager-theme'); ?></option>
                        <option value="high"><?php esc_html_e('High', 'saga-manager-theme'); ?></option>
                        <option value="medium"><?php esc_html_e('Medium', 'saga-manager-theme'); ?></option>
                        <option value="low"><?php esc_html_e('Low', 'saga-manager-theme'); ?></option>
                        <option value="info"><?php esc_html_e('Info', 'saga-manager-theme'); ?></option>
                    </select>

                    <select id="filter-type">
                        <option value=""><?php esc_html_e('All Types', 'saga-manager-theme'); ?></option>
                        <option value="timeline"><?php esc_html_e('Timeline', 'saga-manager-theme'); ?></option>
                        <option value="character"><?php esc_html_e('Character', 'saga-manager-theme'); ?></option>
                        <option value="location"><?php esc_html_e('Location', 'saga-manager-theme'); ?></option>
                        <option value="relationship"><?php esc_html_e('Relationship', 'saga-manager-theme'); ?></option>
                        <option value="logical"><?php esc_html_e('Logical', 'saga-manager-theme'); ?></option>
                    </select>

                    <button type="button" class="button" id="apply-filters">
                        <?php esc_html_e('Apply Filters', 'saga-manager-theme'); ?>
                    </button>
                </div>

                <div class="actions-group">
                    <select id="bulk-action">
                        <option value=""><?php esc_html_e('Bulk Actions', 'saga-manager-theme'); ?></option>
                        <option value="resolve"><?php esc_html_e('Resolve', 'saga-manager-theme'); ?></option>
                        <option value="dismiss"><?php esc_html_e('Dismiss', 'saga-manager-theme'); ?></option>
                        <option value="mark_false_positive"><?php esc_html_e('Mark as False Positive', 'saga-manager-theme'); ?></option>
                    </select>

                    <button type="button" class="button" id="apply-bulk-action">
                        <?php esc_html_e('Apply', 'saga-manager-theme'); ?>
                    </button>

                    <button type="button" class="button" id="export-csv">
                        <span class="dashicons dashicons-download"></span>
                        <?php esc_html_e('Export CSV', 'saga-manager-theme'); ?>
                    </button>
                </div>
            </div>

            <!-- Issues Table -->
            <div class="issues-table-container">
                <table class="wp-list-table widefat fixed striped" id="issues-table">
                    <thead>
                        <tr>
                            <td class="check-column">
                                <input type="checkbox" id="select-all-issues">
                            </td>
                            <th class="column-severity"><?php esc_html_e('Severity', 'saga-manager-theme'); ?></th>
                            <th class="column-type"><?php esc_html_e('Type', 'saga-manager-theme'); ?></th>
                            <th class="column-description"><?php esc_html_e('Description', 'saga-manager-theme'); ?></th>
                            <th class="column-entity"><?php esc_html_e('Entity', 'saga-manager-theme'); ?></th>
                            <th class="column-detected"><?php esc_html_e('Detected', 'saga-manager-theme'); ?></th>
                            <th class="column-status"><?php esc_html_e('Status', 'saga-manager-theme'); ?></th>
                            <th class="column-actions"><?php esc_html_e('Actions', 'saga-manager-theme'); ?></th>
                        </tr>
                    </thead>
                    <tbody id="issues-tbody">
                        <tr class="loading-row">
                            <td colspan="8">
                                <span class="spinner is-active"></span>
                                <?php esc_html_e('Loading issues...', 'saga-manager-theme'); ?>
                            </td>
                        </tr>
                    </tbody>
                </table>

                <!-- Pagination -->
                <div class="tablenav bottom">
                    <div class="tablenav-pages">
                        <span class="displaying-num"></span>
                        <span class="pagination-links">
                            <button class="button" id="first-page" disabled>&laquo;</button>
                            <button class="button" id="prev-page" disabled>&lsaquo;</button>
                            <span class="paging-input">
                                <label for="current-page-selector" class="screen-reader-text">
                                    <?php esc_html_e('Current Page', 'saga-manager-theme'); ?>
                                </label>
                                <input class="current-page" id="current-page-selector" type="text" value="1" size="2">
                                <span class="tablenav-paging-text">
                                    <?php esc_html_e('of', 'saga-manager-theme'); ?>
                                    <span class="total-pages">1</span>
                                </span>
                            </span>
                            <button class="button" id="next-page">&rsaquo;</button>
                            <button class="button" id="last-page">&raquo;</button>
                        </span>
                    </div>
                </div>
            </div>

        <?php endif; ?>

    <?php endif; ?>
</div>

<!-- Issue Details Modal -->
<div id="issue-details-modal" class="saga-modal" style="display: none;">
    <div class="saga-modal-backdrop"></div>
    <div class="saga-modal-content">
        <div class="saga-modal-header">
            <h2><?php esc_html_e('Issue Details', 'saga-manager-theme'); ?></h2>
            <button type="button" class="saga-modal-close">
                <span class="dashicons dashicons-no"></span>
            </button>
        </div>
        <div class="saga-modal-body">
            <div class="spinner is-active" style="float: none; margin: 40px auto;"></div>
        </div>
        <div class="saga-modal-footer">
            <button type="button" class="button button-large" data-action="dismiss">
                <?php esc_html_e('Dismiss', 'saga-manager-theme'); ?>
            </button>
            <button type="button" class="button button-large" data-action="false-positive">
                <?php esc_html_e('Mark False Positive', 'saga-manager-theme'); ?>
            </button>
            <button type="button" class="button button-primary button-large" data-action="resolve">
                <?php esc_html_e('Resolve', 'saga-manager-theme'); ?>
            </button>
        </div>
    </div>
</div>

<!-- Pass data to JavaScript -->
<script type="text/javascript">
var sagaConsistencyData = {
    sagaId: <?php echo esc_js($currentSagaId); ?>,
    nonce: '<?php echo esc_js(wp_create_nonce('saga_consistency_nonce')); ?>',
    ajaxUrl: '<?php echo esc_js(admin_url('admin-ajax.php')); ?>',
    statsData: <?php echo wp_json_encode($stats); ?>,
    issuesByType: <?php echo wp_json_encode($issuesByType); ?>,
    i18n: {
        scanRunning: '<?php esc_html_e('Scan running...', 'saga-manager-theme'); ?>',
        scanComplete: '<?php esc_html_e('Scan complete!', 'saga-manager-theme'); ?>',
        scanFailed: '<?php esc_html_e('Scan failed', 'saga-manager-theme'); ?>',
        noIssuesSelected: '<?php esc_html_e('Please select at least one issue', 'saga-manager-theme'); ?>',
        confirmBulkAction: '<?php esc_html_e('Are you sure you want to perform this action on the selected issues?', 'saga-manager-theme'); ?>',
        networkError: '<?php esc_html_e('Network error. Please try again.', 'saga-manager-theme'); ?>',
    }
};
</script>

<?php
// Enqueue Chart.js for statistics
wp_enqueue_script('chart-js', 'https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js', [], '4.4.0', true);
?>
