<?php
/**
 * Template Name: Relationship Suggestions Admin
 * Description: AI-powered relationship suggestions dashboard
 */

// Security check
if (!defined('ABSPATH') || !current_user_can('edit_posts')) {
    wp_die('Unauthorized access');
}

// Get all sagas for dropdown
global $wpdb;
$sagas = $wpdb->get_results("SELECT id, name FROM {$wpdb->prefix}saga_sagas ORDER BY name");
$current_saga_id = absint($_GET['saga_id'] ?? ($sagas[0]->id ?? 0));

?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo('charset'); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Relationship Suggestions - Saga Manager</title>
    <?php wp_head(); ?>
</head>
<body class="saga-suggestions-page">

<div class="saga-suggestions-wrapper">

    <!-- Header -->
    <header class="suggestions-header">
        <div class="header-content">
            <h1>
                <span class="dashicons dashicons-networking"></span>
                AI Relationship Suggestions
            </h1>

            <div class="header-controls">
                <!-- Saga Selector -->
                <div class="saga-selector">
                    <label for="saga-select">Saga:</label>
                    <select id="saga-select" name="saga_id">
                        <?php foreach ($sagas as $saga): ?>
                            <option value="<?php echo esc_attr($saga->id); ?>"
                                    <?php selected($current_saga_id, $saga->id); ?>>
                                <?php echo esc_html($saga->name); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Generate Button -->
                <button id="generate-suggestions-btn" class="button button-primary">
                    <span class="dashicons dashicons-update"></span>
                    Generate Suggestions
                </button>

                <!-- Progress Indicator -->
                <div id="generation-progress" class="generation-progress" style="display: none;">
                    <div class="progress-bar">
                        <div class="progress-fill" style="width: 0%"></div>
                    </div>
                    <span class="progress-text">0%</span>
                </div>
            </div>
        </div>

        <!-- Accuracy Metrics -->
        <div class="accuracy-metrics" id="accuracy-metrics">
            <div class="metric-item">
                <span class="metric-label">Acceptance Rate:</span>
                <span class="metric-value" id="acceptance-rate">--</span>
            </div>
            <div class="metric-item">
                <span class="metric-label">Avg Confidence:</span>
                <span class="metric-value" id="avg-confidence">--</span>
            </div>
        </div>
    </header>

    <!-- Statistics Cards -->
    <div class="statistics-row">
        <div class="stat-card">
            <div class="stat-icon pending">
                <span class="dashicons dashicons-clock"></span>
            </div>
            <div class="stat-content">
                <div class="stat-value" id="stat-pending">0</div>
                <div class="stat-label">Pending</div>
            </div>
        </div>

        <div class="stat-card">
            <div class="stat-icon accepted">
                <span class="dashicons dashicons-yes-alt"></span>
            </div>
            <div class="stat-content">
                <div class="stat-value" id="stat-accepted">0</div>
                <div class="stat-label">Accepted</div>
            </div>
        </div>

        <div class="stat-card">
            <div class="stat-icon accuracy">
                <span class="dashicons dashicons-chart-line"></span>
            </div>
            <div class="stat-content">
                <div class="stat-value" id="stat-accuracy">0%</div>
                <div class="stat-label">Accuracy</div>
            </div>
        </div>

        <div class="stat-card">
            <div class="stat-icon confidence">
                <span class="dashicons dashicons-star-filled"></span>
            </div>
            <div class="stat-content">
                <div class="stat-value" id="stat-avg-confidence">0.0</div>
                <div class="stat-label">Avg Confidence</div>
            </div>
        </div>
    </div>

    <!-- Tabs Navigation -->
    <div class="suggestions-tabs">
        <button class="tab-button active" data-tab="suggestions">
            <span class="dashicons dashicons-list-view"></span>
            Suggestions
        </button>
        <button class="tab-button" data-tab="learning">
            <span class="dashicons dashicons-admin-generic"></span>
            Learning Dashboard
        </button>
    </div>

    <!-- Suggestions Tab Content -->
    <div id="suggestions-tab" class="tab-content active">

        <!-- Filters & Sorting -->
        <div class="filters-bar">
            <div class="filter-group">
                <label>Status:</label>
                <select id="filter-status">
                    <option value="pending">Pending</option>
                    <option value="accepted">Accepted</option>
                    <option value="rejected">Rejected</option>
                    <option value="all">All</option>
                </select>
            </div>

            <div class="filter-group">
                <label>Min Confidence:</label>
                <select id="filter-confidence">
                    <option value="0">All</option>
                    <option value="0.8" selected>High (≥80%)</option>
                    <option value="0.6">Medium (≥60%)</option>
                    <option value="0.4">Low (≥40%)</option>
                </select>
            </div>

            <div class="filter-group">
                <label>Relationship Type:</label>
                <select id="filter-type">
                    <option value="">All Types</option>
                    <option value="ally">Ally</option>
                    <option value="enemy">Enemy</option>
                    <option value="family">Family</option>
                    <option value="mentor">Mentor</option>
                    <option value="rival">Rival</option>
                    <option value="lover">Lover</option>
                    <option value="friend">Friend</option>
                    <option value="colleague">Colleague</option>
                </select>
            </div>

            <div class="filter-group">
                <label>Sort By:</label>
                <select id="sort-by">
                    <option value="confidence_score">Confidence</option>
                    <option value="priority_score">Priority</option>
                    <option value="created_at">Date Created</option>
                </select>
            </div>

            <div class="filter-group">
                <label>Order:</label>
                <select id="sort-order">
                    <option value="DESC">Descending</option>
                    <option value="ASC">Ascending</option>
                </select>
            </div>

            <button id="apply-filters-btn" class="button">Apply Filters</button>
            <button id="reset-filters-btn" class="button">Reset</button>
        </div>

        <!-- Bulk Actions Bar -->
        <div id="bulk-actions-bar" class="bulk-actions-bar" style="display: none;">
            <span id="selected-count">0 selected</span>
            <button id="bulk-accept-btn" class="button button-primary">
                <span class="dashicons dashicons-yes"></span>
                Accept Selected
            </button>
            <button id="bulk-reject-btn" class="button">
                <span class="dashicons dashicons-no"></span>
                Reject Selected
            </button>
        </div>

        <!-- Suggestions Table -->
        <div class="suggestions-table-wrapper">
            <table class="suggestions-table">
                <thead>
                    <tr>
                        <th class="col-checkbox">
                            <input type="checkbox" id="select-all">
                        </th>
                        <th class="col-entities">Entities</th>
                        <th class="col-type">Relationship Type</th>
                        <th class="col-confidence">Confidence</th>
                        <th class="col-strength">Strength</th>
                        <th class="col-actions">Actions</th>
                    </tr>
                </thead>
                <tbody id="suggestions-tbody">
                    <!-- Populated by JavaScript -->
                </tbody>
            </table>

            <!-- Loading State -->
            <div id="suggestions-loading" class="suggestions-loading" style="display: none;">
                <span class="spinner is-active"></span>
                <p>Loading suggestions...</p>
            </div>

            <!-- Empty State -->
            <div id="suggestions-empty" class="suggestions-empty" style="display: none;">
                <span class="dashicons dashicons-info"></span>
                <p>No suggestions found. Click "Generate Suggestions" to create new ones.</p>
            </div>
        </div>

        <!-- Pagination -->
        <div class="suggestions-pagination" id="suggestions-pagination">
            <button id="prev-page-btn" class="button" disabled>Previous</button>
            <span id="page-info">Page 1 of 1</span>
            <button id="next-page-btn" class="button" disabled>Next</button>
        </div>
    </div>

    <!-- Learning Dashboard Tab Content -->
    <div id="learning-tab" class="tab-content">

        <div class="learning-dashboard">

            <!-- Feature Weights Chart -->
            <div class="dashboard-card">
                <h3>Feature Weights</h3>
                <div id="feature-weights-chart" class="chart-container">
                    <canvas id="weights-canvas"></canvas>
                </div>
            </div>

            <!-- Accuracy Over Time Chart -->
            <div class="dashboard-card">
                <h3>Accuracy Over Time</h3>
                <div id="accuracy-chart" class="chart-container">
                    <canvas id="accuracy-canvas"></canvas>
                </div>
            </div>

            <!-- Accept/Reject Ratio Chart -->
            <div class="dashboard-card">
                <h3>Feedback Distribution</h3>
                <div id="feedback-chart" class="chart-container">
                    <canvas id="feedback-canvas"></canvas>
                </div>
            </div>

            <!-- Recent Feedback Log -->
            <div class="dashboard-card full-width">
                <h3>Recent Feedback</h3>
                <div id="recent-feedback" class="feedback-log">
                    <!-- Populated by JavaScript -->
                </div>
            </div>

            <!-- Learning Controls -->
            <div class="dashboard-card full-width">
                <h3>Learning Controls</h3>
                <div class="learning-controls">
                    <button id="trigger-learning-btn" class="button button-primary">
                        <span class="dashicons dashicons-update"></span>
                        Update Learning Weights
                    </button>
                    <button id="reset-learning-btn" class="button button-secondary">
                        <span class="dashicons dashicons-undo"></span>
                        Reset to Default Weights
                    </button>
                    <p class="description">
                        Manually trigger learning weight updates based on accumulated feedback,
                        or reset to default weights to start fresh.
                    </p>
                </div>
            </div>
        </div>
    </div>

</div>

<!-- Suggestion Details Modal -->
<div id="suggestion-details-modal" class="modal" style="display: none;">
    <div class="modal-overlay"></div>
    <div class="modal-content">
        <div class="modal-header">
            <h2>Suggestion Details</h2>
            <button class="modal-close">
                <span class="dashicons dashicons-no"></span>
            </button>
        </div>
        <div class="modal-body" id="modal-body">
            <!-- Populated by JavaScript -->
        </div>
        <div class="modal-footer">
            <button id="modal-accept-btn" class="button button-primary">Accept</button>
            <button id="modal-reject-btn" class="button">Reject</button>
            <button id="modal-modify-btn" class="button">Modify</button>
            <button id="modal-create-relationship-btn" class="button button-primary">Create Relationship</button>
        </div>
    </div>
</div>

<!-- Toast Notifications Container -->
<div id="toast-container" class="toast-container"></div>

<?php wp_footer(); ?>

<script>
// Expose saga_id to JavaScript
window.SAGA_CURRENT_ID = <?php echo absint($current_saga_id); ?>;
</script>

</body>
</html>
