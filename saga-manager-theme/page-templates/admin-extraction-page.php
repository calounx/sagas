<?php
/**
 * Admin Page Template: Entity Extractor
 *
 * Main admin interface for AI-powered entity extraction workflow.
 *
 * @package SagaManager
 * @subpackage Admin\Templates
 * @since 1.4.0
 */

if (!defined('ABSPATH')) {
    exit;
}

// Get available sagas for dropdown
global $wpdb;
$sagas = $wpdb->get_results(
    "SELECT id, name FROM {$wpdb->prefix}saga_sagas ORDER BY name ASC",
    ARRAY_A
);

?>

<div class="wrap saga-extraction-dashboard">
    <h1 class="wp-heading-inline">Entity Extractor</h1>
    <hr class="wp-header-end">

    <div class="saga-extraction-container">

        <!-- Extraction Form Section -->
        <div class="saga-extraction-form-section">
            <div class="card">
                <h2>Extract Entities from Text</h2>

                <form id="saga-extraction-form">
                    <!-- Saga Selection -->
                    <div class="form-group">
                        <label for="saga-select">
                            <strong>Target Saga *</strong>
                        </label>
                        <select id="saga-select" name="saga_id" required class="regular-text">
                            <option value="">-- Select Saga --</option>
                            <?php foreach ($sagas as $saga): ?>
                                <option value="<?php echo esc_attr($saga['id']); ?>">
                                    <?php echo esc_html($saga['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <p class="description">Choose the saga where extracted entities will be added</p>
                    </div>

                    <!-- Text Input -->
                    <div class="form-group">
                        <label for="source-text">
                            <strong>Source Text *</strong>
                        </label>
                        <textarea
                            id="source-text"
                            name="source_text"
                            rows="15"
                            class="large-text code"
                            placeholder="Paste your text here... Maximum 100,000 characters."
                            required
                        ></textarea>
                        <p class="description">
                            <span id="char-count">0</span> / 100,000 characters
                        </p>
                    </div>

                    <!-- Advanced Settings -->
                    <div class="form-group">
                        <button type="button" id="toggle-advanced" class="button button-secondary">
                            Show Advanced Settings
                        </button>
                    </div>

                    <div id="advanced-settings" style="display: none;">
                        <!-- Chunk Size -->
                        <div class="form-group">
                            <label for="chunk-size">
                                <strong>Chunk Size</strong>
                            </label>
                            <select id="chunk-size" name="chunk_size" class="regular-text">
                                <option value="1000">1,000 characters</option>
                                <option value="2500">2,500 characters</option>
                                <option value="5000" selected>5,000 characters (Recommended)</option>
                                <option value="10000">10,000 characters</option>
                            </select>
                            <p class="description">Larger chunks = fewer API calls but may reduce accuracy</p>
                        </div>

                        <!-- AI Provider -->
                        <div class="form-group">
                            <label for="ai-provider">
                                <strong>AI Provider</strong>
                            </label>
                            <select id="ai-provider" name="ai_provider" class="regular-text">
                                <option value="openai" selected>OpenAI</option>
                                <option value="anthropic">Anthropic (Claude)</option>
                                <option value="google">Google (Gemini)</option>
                            </select>
                        </div>

                        <!-- AI Model -->
                        <div class="form-group">
                            <label for="ai-model">
                                <strong>AI Model</strong>
                            </label>
                            <select id="ai-model" name="ai_model" class="regular-text">
                                <option value="gpt-4" selected>GPT-4 (Best Quality)</option>
                                <option value="gpt-3.5-turbo">GPT-3.5 Turbo (Faster)</option>
                            </select>
                        </div>
                    </div>

                    <!-- Cost Estimation -->
                    <div id="cost-estimation" class="saga-notice saga-notice-info" style="display: none;">
                        <h3>Estimated Cost</h3>
                        <div class="cost-details">
                            <p><strong>Tokens:</strong> <span id="est-tokens">-</span></p>
                            <p><strong>API Cost:</strong> $<span id="est-cost">-</span> USD</p>
                            <p><strong>Processing Time:</strong> ~<span id="est-time">-</span> seconds</p>
                            <p><strong>Expected Entities:</strong> ~<span id="est-entities">-</span></p>
                        </div>
                    </div>

                    <!-- Submit Button -->
                    <div class="form-group">
                        <button type="submit" id="start-extraction-btn" class="button button-primary button-large">
                            Start Extraction
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Progress Section -->
        <div id="progress-section" class="saga-extraction-progress" style="display: none;">
            <div class="card">
                <h2>Extraction Progress</h2>

                <div class="progress-bar-container">
                    <div class="progress-bar">
                        <div id="progress-bar-fill" class="progress-bar-fill" style="width: 0%"></div>
                    </div>
                    <p class="progress-text">
                        <span id="progress-percent">0</span>% -
                        <span id="progress-message">Initializing...</span>
                    </p>
                </div>

                <div class="progress-stats">
                    <div class="stat">
                        <span class="stat-label">Status:</span>
                        <span id="job-status" class="stat-value">-</span>
                    </div>
                    <div class="stat">
                        <span class="stat-label">Entities Found:</span>
                        <span id="entities-found" class="stat-value">0</span>
                    </div>
                    <div class="stat">
                        <span class="stat-label">Pending Review:</span>
                        <span id="pending-review" class="stat-value">0</span>
                    </div>
                    <div class="stat">
                        <span class="stat-label">Approved:</span>
                        <span id="approved-count" class="stat-value">0</span>
                    </div>
                </div>

                <button type="button" id="cancel-job-btn" class="button button-secondary">
                    Cancel Job
                </button>
            </div>
        </div>

        <!-- Entity Preview Section -->
        <div id="preview-section" class="saga-extraction-preview" style="display: none;">
            <div class="card">
                <h2>Review Extracted Entities</h2>

                <!-- Filters -->
                <div class="preview-filters">
                    <label>
                        Type:
                        <select id="filter-type" class="filter-select">
                            <option value="">All Types</option>
                            <option value="character">Character</option>
                            <option value="location">Location</option>
                            <option value="event">Event</option>
                            <option value="faction">Faction</option>
                            <option value="artifact">Artifact</option>
                            <option value="concept">Concept</option>
                        </select>
                    </label>

                    <label>
                        Status:
                        <select id="filter-status" class="filter-select">
                            <option value="">All Statuses</option>
                            <option value="pending" selected>Pending</option>
                            <option value="approved">Approved</option>
                            <option value="rejected">Rejected</option>
                        </select>
                    </label>

                    <label>
                        Confidence:
                        <select id="filter-confidence" class="filter-select">
                            <option value="">All Levels</option>
                            <option value="high">High (80%+)</option>
                            <option value="medium">Medium (60-80%)</option>
                            <option value="low">Low (<60%)</option>
                        </select>
                    </label>

                    <button type="button" id="apply-filters-btn" class="button button-secondary">
                        Apply Filters
                    </button>
                </div>

                <!-- Bulk Actions -->
                <div class="preview-bulk-actions">
                    <label>
                        <input type="checkbox" id="select-all-entities">
                        Select All
                    </label>

                    <button type="button" id="bulk-approve-btn" class="button button-primary">
                        Approve Selected
                    </button>

                    <button type="button" id="bulk-reject-btn" class="button button-secondary">
                        Reject Selected
                    </button>

                    <button type="button" id="create-approved-btn" class="button button-primary button-large">
                        Create Approved Entities
                    </button>
                </div>

                <!-- Entity Grid -->
                <div id="entities-grid" class="entities-grid">
                    <!-- Entities loaded via AJAX -->
                </div>

                <!-- Pagination -->
                <div id="pagination-controls" class="pagination-controls">
                    <!-- Pagination loaded via AJAX -->
                </div>
            </div>
        </div>

        <!-- Job History Section -->
        <div class="saga-extraction-history">
            <div class="card">
                <h2>Recent Extraction Jobs</h2>

                <table id="job-history-table" class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th>Job ID</th>
                            <th>Status</th>
                            <th>Entities</th>
                            <th>Created</th>
                            <th>Rejected</th>
                            <th>Duplicates</th>
                            <th>Date</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody id="job-history-tbody">
                        <tr>
                            <td colspan="8" style="text-align: center; padding: 20px;">
                                No extraction jobs yet
                            </td>
                        </tr>
                    </tbody>
                </table>

                <button type="button" id="load-more-jobs-btn" class="button button-secondary" style="display: none;">
                    Load More
                </button>
            </div>
        </div>

    </div>
</div>

<!-- Duplicate Resolution Modal -->
<div id="duplicate-modal" class="saga-modal" style="display: none;">
    <div class="saga-modal-overlay"></div>
    <div class="saga-modal-content">
        <div class="saga-modal-header">
            <h2>Resolve Duplicate</h2>
            <button type="button" class="saga-modal-close">&times;</button>
        </div>
        <div class="saga-modal-body">
            <div id="duplicate-details">
                <!-- Loaded dynamically -->
            </div>
        </div>
        <div class="saga-modal-footer">
            <button type="button" id="confirm-duplicate-btn" class="button button-primary">
                Confirm as Duplicate
            </button>
            <button type="button" id="mark-unique-btn" class="button button-secondary">
                Mark as Unique
            </button>
            <button type="button" class="saga-modal-close button">
                Cancel
            </button>
        </div>
    </div>
</div>

<!-- Toast Notification Container -->
<div id="toast-container" class="saga-toast-container"></div>

<?php
// Load preview partial template
$preview_partial = get_template_directory() . '/page-templates/partials/extraction-preview.php';
if (file_exists($preview_partial)) {
    // Template loaded, entity cards will be rendered by JS
}
?>
