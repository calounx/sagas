<?php
/**
 * Admin Page Template: Auto-Generated Summaries
 *
 * Bilingual admin interface for AI-powered summary generation.
 * Supports French and English with WordPress i18n functions.
 *
 * @package SagaManager
 * @subpackage Admin\Templates
 * @since 1.5.0
 */

if (!defined('ABSPATH')) {
    exit;
}

// Load i18n helper
require_once get_template_directory() . '/inc/i18n/i18n-summaries.php';

// Get available sagas for dropdown
global $wpdb;
$sagas = $wpdb->get_results(
    "SELECT id, name FROM {$wpdb->prefix}saga_sagas ORDER BY name ASC",
    ARRAY_A
);

?>

<div class="wrap saga-summaries-dashboard">
    <h1 class="wp-heading-inline"><?php echo esc_html(summary_i18n_text('page_title')); ?></h1>
    <hr class="wp-header-end">

    <div class="saga-summaries-container">

        <!-- Summary Generation Form Section -->
        <div class="saga-summaries-form-section">
            <div class="card">
                <h2><?php echo esc_html(summary_i18n_text('new_summary')); ?></h2>
                <p class="description"><?php echo esc_html(summary_i18n_text('desc_verified_data')); ?></p>

                <form id="saga-summary-form">
                    <!-- Saga Selection -->
                    <div class="form-group">
                        <label for="saga-select">
                            <strong><?php echo esc_html(summary_i18n_text('select_saga')); ?> *</strong>
                        </label>
                        <select id="saga-select" name="saga_id" required class="regular-text">
                            <option value="">-- <?php echo esc_html(summary_i18n_text('select_saga')); ?> --</option>
                            <?php foreach ($sagas as $saga): ?>
                                <option value="<?php echo esc_attr($saga['id']); ?>">
                                    <?php echo esc_html($saga['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Summary Type -->
                    <div class="form-group">
                        <label for="summary-type-select">
                            <strong><?php echo esc_html(summary_i18n_text('select_type')); ?> *</strong>
                        </label>
                        <select id="summary-type-select" name="summary_type" required class="regular-text">
                            <option value="">-- <?php echo esc_html(summary_i18n_text('select_type')); ?> --</option>
                            <option value="character_arc"><?php echo esc_html(summary_i18n_text('character_arc')); ?></option>
                            <option value="timeline"><?php echo esc_html(summary_i18n_text('timeline')); ?></option>
                            <option value="relationship"><?php echo esc_html(summary_i18n_text('relationship')); ?></option>
                            <option value="faction"><?php echo esc_html(summary_i18n_text('faction')); ?></option>
                            <option value="location"><?php echo esc_html(summary_i18n_text('location')); ?></option>
                        </select>
                        <p class="description" id="type-description"></p>
                    </div>

                    <!-- Entity Selection (conditional) -->
                    <div class="form-group" id="entity-select-group" style="display: none;">
                        <label for="entity-select">
                            <strong><?php echo esc_html(summary_i18n_text('select_entity')); ?> *</strong>
                        </label>
                        <select id="entity-select" name="entity_id" class="regular-text">
                            <option value=""><?php echo esc_html(summary_i18n_text('placeholder_select_entity')); ?></option>
                        </select>
                    </div>

                    <!-- Scope Selection -->
                    <div class="form-group">
                        <label for="scope-select">
                            <strong><?php echo esc_html(summary_i18n_text('select_scope')); ?></strong>
                        </label>
                        <select id="scope-select" name="scope" class="regular-text">
                            <option value="full" selected><?php echo esc_html(summary_i18n_text('scope_full')); ?></option>
                            <option value="chapter"><?php echo esc_html(summary_i18n_text('scope_chapter')); ?></option>
                            <option value="date_range"><?php echo esc_html(summary_i18n_text('scope_date_range')); ?></option>
                        </select>
                    </div>

                    <!-- Advanced Settings -->
                    <div class="form-group">
                        <button type="button" id="toggle-advanced" class="button button-secondary">
                            <?php _e('Show Advanced Settings', 'saga-manager'); ?>
                        </button>
                    </div>

                    <div id="advanced-settings" style="display: none;">
                        <!-- AI Provider -->
                        <div class="form-group">
                            <label for="ai-provider">
                                <strong><?php echo esc_html(summary_i18n_text('select_provider')); ?></strong>
                            </label>
                            <select id="ai-provider" name="ai_provider" class="regular-text">
                                <option value="openai" selected>OpenAI (GPT-4)</option>
                                <option value="anthropic">Anthropic (Claude 3)</option>
                            </select>
                        </div>

                        <!-- AI Model -->
                        <div class="form-group">
                            <label for="ai-model">
                                <strong><?php echo esc_html(summary_i18n_text('select_model')); ?></strong>
                            </label>
                            <select id="ai-model" name="ai_model" class="regular-text">
                                <option value="gpt-4" selected>GPT-4 (<?php _e('Best Quality', 'saga-manager'); ?>)</option>
                                <option value="gpt-3.5-turbo">GPT-3.5 Turbo (<?php _e('Faster', 'saga-manager'); ?>)</option>
                            </select>
                        </div>
                    </div>

                    <!-- Cost Estimation -->
                    <div id="cost-estimation" class="saga-notice saga-notice-info" style="display: none;">
                        <h3><?php _e('Estimated Cost', 'saga-manager'); ?></h3>
                        <div class="cost-details">
                            <p><strong><?php _e('Tokens:', 'saga-manager'); ?></strong> <span id="est-tokens">-</span></p>
                            <p><strong><?php _e('API Cost:', 'saga-manager'); ?></strong> $<span id="est-cost">-</span> USD</p>
                            <p class="description"><?php echo esc_html(summary_i18n_text('desc_cached')); ?></p>
                        </div>
                    </div>

                    <!-- Submit Button -->
                    <div class="form-group">
                        <button type="submit" id="generate-summary-btn" class="button button-primary button-large">
                            <?php echo esc_html(summary_i18n_text('btn_generate')); ?>
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Progress Section -->
        <div id="progress-section" class="saga-summary-progress" style="display: none;">
            <div class="card">
                <h2><?php _e('Generation Progress', 'saga-manager'); ?></h2>

                <div class="progress-bar-container">
                    <div class="progress-bar">
                        <div id="progress-bar-fill" class="progress-bar-fill" style="width: 0%"></div>
                    </div>
                    <p class="progress-text">
                        <span id="progress-percent">0</span>% -
                        <span id="progress-message"><?php echo esc_html(summary_i18n_text('loading')); ?></span>
                    </p>
                </div>

                <button type="button" id="cancel-request-btn" class="button button-secondary">
                    <?php echo esc_html(summary_i18n_text('btn_cancel')); ?>
                </button>
            </div>
        </div>

        <!-- Summary List Section -->
        <div class="saga-summary-list">
            <div class="card">
                <div class="card-header">
                    <h2><?php echo esc_html(summary_i18n_text('summary_list')); ?></h2>

                    <!-- Search and Filters -->
                    <div class="summary-controls">
                        <input
                            type="text"
                            id="summary-search"
                            class="regular-text"
                            placeholder="<?php echo esc_attr(summary_i18n_text('placeholder_search')); ?>"
                        >

                        <select id="filter-type" class="filter-select">
                            <option value=""><?php echo esc_html(summary_i18n_text('filter_all_types')); ?></option>
                            <option value="character_arc"><?php echo esc_html(summary_i18n_text('character_arc')); ?></option>
                            <option value="timeline"><?php echo esc_html(summary_i18n_text('timeline')); ?></option>
                            <option value="relationship"><?php echo esc_html(summary_i18n_text('relationship')); ?></option>
                            <option value="faction"><?php echo esc_html(summary_i18n_text('faction')); ?></option>
                            <option value="location"><?php echo esc_html(summary_i18n_text('location')); ?></option>
                        </select>

                        <button type="button" id="apply-filters-btn" class="button button-secondary">
                            <?php echo esc_html(summary_i18n_text('filter_apply')); ?>
                        </button>
                    </div>
                </div>

                <!-- Summary Table -->
                <table id="summaries-table" class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th><?php echo esc_html(summary_i18n_text('th_id')); ?></th>
                            <th><?php echo esc_html(summary_i18n_text('th_type')); ?></th>
                            <th><?php echo esc_html(summary_i18n_text('th_title')); ?></th>
                            <th><?php echo esc_html(summary_i18n_text('th_quality')); ?></th>
                            <th><?php echo esc_html(summary_i18n_text('th_words')); ?></th>
                            <th><?php echo esc_html(summary_i18n_text('th_cost')); ?></th>
                            <th><?php echo esc_html(summary_i18n_text('th_created')); ?></th>
                            <th><?php echo esc_html(summary_i18n_text('th_actions')); ?></th>
                        </tr>
                    </thead>
                    <tbody id="summaries-tbody">
                        <tr>
                            <td colspan="8" style="text-align: center; padding: 20px;">
                                <?php echo esc_html(summary_i18n_text('no_summaries')); ?>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Statistics Section -->
        <div class="saga-summary-statistics">
            <div class="card">
                <h2><?php echo esc_html(summary_i18n_text('statistics')); ?></h2>

                <div class="stats-grid">
                    <div class="stat-box">
                        <div class="stat-label"><?php echo esc_html(summary_i18n_text('stat_total_summaries')); ?></div>
                        <div class="stat-value" id="stat-total">0</div>
                    </div>

                    <div class="stat-box">
                        <div class="stat-label"><?php echo esc_html(summary_i18n_text('stat_avg_quality')); ?></div>
                        <div class="stat-value" id="stat-quality">-</div>
                    </div>

                    <div class="stat-box">
                        <div class="stat-label"><?php echo esc_html(summary_i18n_text('stat_avg_readability')); ?></div>
                        <div class="stat-value" id="stat-readability">-</div>
                    </div>

                    <div class="stat-box">
                        <div class="stat-label"><?php echo esc_html(summary_i18n_text('stat_total_cost')); ?></div>
                        <div class="stat-value" id="stat-cost">$0.00</div>
                    </div>
                </div>

                <div id="stats-chart-container">
                    <canvas id="stats-chart"></canvas>
                </div>
            </div>
        </div>

    </div>
</div>

<!-- Summary Detail Modal -->
<div id="summary-detail-modal" class="saga-modal" style="display: none;">
    <div class="saga-modal-overlay"></div>
    <div class="saga-modal-content saga-modal-large">
        <div class="saga-modal-header">
            <h2 id="modal-title"><?php _e('Summary Details', 'saga-manager'); ?></h2>
            <button type="button" class="saga-modal-close">&times;</button>
        </div>
        <div class="saga-modal-body">
            <div id="summary-detail-content">
                <!-- Loaded dynamically -->
            </div>
        </div>
        <div class="saga-modal-footer">
            <button type="button" id="export-markdown-btn" class="button button-secondary">
                <?php echo esc_html(summary_i18n_text('export_markdown')); ?>
            </button>
            <button type="button" id="export-html-btn" class="button button-secondary">
                <?php echo esc_html(summary_i18n_text('export_html')); ?>
            </button>
            <button type="button" id="regenerate-btn" class="button button-primary">
                <?php echo esc_html(summary_i18n_text('btn_regenerate')); ?>
            </button>
            <button type="button" id="delete-summary-btn" class="button button-link-delete">
                <?php echo esc_html(summary_i18n_text('btn_delete')); ?>
            </button>
            <button type="button" class="saga-modal-close button">
                <?php _e('Close', 'saga-manager'); ?>
            </button>
        </div>
    </div>
</div>

<!-- Feedback Modal -->
<div id="feedback-modal" class="saga-modal" style="display: none;">
    <div class="saga-modal-overlay"></div>
    <div class="saga-modal-content">
        <div class="saga-modal-header">
            <h2><?php _e('Submit Feedback', 'saga-manager'); ?></h2>
            <button type="button" class="saga-modal-close">&times;</button>
        </div>
        <div class="saga-modal-body">
            <form id="feedback-form">
                <input type="hidden" id="feedback-summary-id" name="summary_id">

                <div class="form-group">
                    <label><strong><?php _e('Rating:', 'saga-manager'); ?></strong></label>
                    <div class="rating-stars">
                        <span class="star" data-rating="1">&#9733;</span>
                        <span class="star" data-rating="2">&#9733;</span>
                        <span class="star" data-rating="3">&#9733;</span>
                        <span class="star" data-rating="4">&#9733;</span>
                        <span class="star" data-rating="5">&#9733;</span>
                    </div>
                    <input type="hidden" id="feedback-rating" name="rating" required>
                </div>

                <div class="form-group">
                    <label for="feedback-text"><strong><?php _e('Feedback:', 'saga-manager'); ?></strong></label>
                    <textarea
                        id="feedback-text"
                        name="feedback"
                        rows="5"
                        class="large-text"
                        placeholder="<?php echo esc_attr(summary_i18n_text('placeholder_feedback')); ?>"
                    ></textarea>
                </div>
            </form>
        </div>
        <div class="saga-modal-footer">
            <button type="button" id="submit-feedback-btn" class="button button-primary">
                <?php echo esc_html(summary_i18n_text('btn_submit_feedback')); ?>
            </button>
            <button type="button" class="saga-modal-close button">
                <?php _e('Cancel', 'saga-manager'); ?>
            </button>
        </div>
    </div>
</div>

<!-- Toast Notification Container -->
<div id="toast-container" class="saga-toast-container"></div>

<script type="text/javascript">
// Pass PHP data to JavaScript
window.sagaSummariesData = {
    nonce: '<?php echo wp_create_nonce('saga_summaries_nonce'); ?>',
    ajaxUrl: '<?php echo admin_url('admin-ajax.php'); ?>',
    currentSagaId: <?php echo isset($_GET['saga_id']) ? absint($_GET['saga_id']) : 0; ?>,
    i18n: {
        errorUnauthorized: '<?php echo esc_js(summary_i18n_text('error_unauthorized')); ?>',
        errorNetwork: '<?php echo esc_js(summary_i18n_text('error_network')); ?>',
        confirmDelete: '<?php echo esc_js(summary_i18n_text('confirm_delete')); ?>',
        confirmRegenerate: '<?php echo esc_js(summary_i18n_text('confirm_regenerate')); ?>',
        confirmCancel: '<?php echo esc_js(summary_i18n_text('confirm_cancel')); ?>',
        loading: '<?php echo esc_js(summary_i18n_text('loading')); ?>',
        processing: '<?php echo esc_js(summary_i18n_text('processing')); ?>',
    }
};
</script>
