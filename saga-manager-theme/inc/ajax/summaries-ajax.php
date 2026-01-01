<?php
/**
 * Summary Generation AJAX Handlers
 *
 * Handles all AJAX requests for the summary generation workflow.
 * Includes security checks, input validation, and bilingual error handling.
 *
 * @package SagaManager
 * @subpackage AJAX\Summaries
 * @since 1.5.0
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

use SagaManager\AI\SummaryGenerator\SummaryOrchestrator;
use SagaManager\AI\SummaryGenerator\SummaryRepository;
use SagaManager\AI\Entities\SummaryType;
use SagaManager\AI\Entities\OutputFormat;

// Load i18n helper
require_once get_template_directory() . '/inc/i18n/i18n-summaries.php';

/**
 * Request new summary
 */
add_action('wp_ajax_saga_request_summary', function() {
    // Security checks
    check_ajax_referer('saga_summaries_nonce', 'nonce');

    if (!current_user_can('edit_posts')) {
        wp_send_json_error(['message' => summary_i18n_text('error_unauthorized')], 403);
    }

    // Rate limiting
    $user_id = get_current_user_id();
    $rate_key = "saga_summary_rate_{$user_id}";
    $recent_requests = get_transient($rate_key) ?: 0;

    if ($recent_requests >= 20) {
        wp_send_json_error([
            'message' => __('Rate limit exceeded. Maximum 20 summaries per hour.', 'saga-manager'),
        ], 429);
    }

    // Validate and sanitize inputs
    $saga_id = isset($_POST['saga_id']) ? absint($_POST['saga_id']) : 0;
    $summary_type = isset($_POST['summary_type']) ? sanitize_key($_POST['summary_type']) : '';
    $entity_id = isset($_POST['entity_id']) ? absint($_POST['entity_id']) : null;
    $scope = isset($_POST['scope']) ? sanitize_key($_POST['scope']) : 'full';
    $scope_params = isset($_POST['scope_params']) ? (array)$_POST['scope_params'] : [];
    $ai_provider = isset($_POST['ai_provider']) ? sanitize_key($_POST['ai_provider']) : 'openai';
    $ai_model = isset($_POST['ai_model']) ? sanitize_text_field($_POST['ai_model']) : 'gpt-4';

    // Validation
    if ($saga_id === 0) {
        wp_send_json_error(['message' => summary_i18n_text('error_no_saga')], 400);
    }

    if (empty($summary_type)) {
        wp_send_json_error(['message' => summary_i18n_text('error_no_type')], 400);
    }

    try {
        $type = SummaryType::from($summary_type);
    } catch (\ValueError $e) {
        wp_send_json_error(['message' => __('Invalid summary type', 'saga-manager')], 400);
    }

    // Check entity requirement
    if ($type->requiresEntity() && $entity_id === null) {
        wp_send_json_error(['message' => summary_i18n_text('error_no_entity')], 400);
    }

    try {
        $orchestrator = new SummaryOrchestrator();

        $request = $orchestrator->startSummaryGeneration($saga_id, $type, [
            'entity_id' => $entity_id,
            'scope' => $scope,
            'scope_params' => $scope_params,
            'ai_provider' => $ai_provider,
            'ai_model' => $ai_model,
        ]);

        // Update rate limiting
        set_transient($rate_key, $recent_requests + 1, HOUR_IN_SECONDS);

        error_log(sprintf(
            '[SAGA][SUMMARY][AJAX] User %d started summary request #%d',
            $user_id,
            $request->id
        ));

        wp_send_json_success([
            'request_id' => $request->id,
            'status' => $request->status->value,
            'estimated_cost' => $request->estimated_cost,
            'estimated_tokens' => $request->estimated_tokens,
            'message' => summary_i18n_text('success_generated'),
        ]);

    } catch (\Exception $e) {
        error_log(sprintf(
            '[SAGA][SUMMARY][AJAX][ERROR] Request summary failed: %s',
            $e->getMessage()
        ));

        wp_send_json_error([
            'message' => sprintf(__('Failed to request summary: %s', 'saga-manager'), $e->getMessage()),
        ], 500);
    }
});

/**
 * Get summary progress
 */
add_action('wp_ajax_saga_get_summary_progress', function() {
    check_ajax_referer('saga_summaries_nonce', 'nonce');

    if (!current_user_can('read')) {
        wp_send_json_error(['message' => summary_i18n_text('error_unauthorized')], 403);
    }

    $request_id = isset($_POST['request_id']) ? absint($_POST['request_id']) : 0;

    if ($request_id === 0) {
        wp_send_json_error(['message' => __('Invalid request ID', 'saga-manager')], 400);
    }

    try {
        $orchestrator = new SummaryOrchestrator();
        $progress = $orchestrator->getProgress($request_id);

        wp_send_json_success($progress);

    } catch (\Exception $e) {
        error_log(sprintf(
            '[SAGA][SUMMARY][AJAX][ERROR] Get progress failed for request #%d: %s',
            $request_id,
            $e->getMessage()
        ));

        wp_send_json_error([
            'message' => __('Failed to get progress', 'saga-manager'),
        ], 500);
    }
});

/**
 * Load summaries with pagination and filters
 */
add_action('wp_ajax_saga_load_summaries', function() {
    check_ajax_referer('saga_summaries_nonce', 'nonce');

    if (!current_user_can('read')) {
        wp_send_json_error(['message' => summary_i18n_text('error_unauthorized')], 403);
    }

    $saga_id = isset($_POST['saga_id']) ? absint($_POST['saga_id']) : 0;
    $filter_type = isset($_POST['filter_type']) ? sanitize_key($_POST['filter_type']) : '';
    $filter_status = isset($_POST['filter_status']) ? sanitize_key($_POST['filter_status']) : '';
    $page = isset($_POST['page']) ? absint($_POST['page']) : 1;
    $per_page = isset($_POST['per_page']) ? absint($_POST['per_page']) : 20;

    if ($saga_id === 0) {
        wp_send_json_error(['message' => summary_i18n_text('error_no_saga')], 400);
    }

    try {
        $repository = new SummaryRepository();

        $filters = ['limit' => $per_page];
        if (!empty($filter_type)) {
            $filters['type'] = $filter_type;
        }

        $summaries = $repository->findBySaga($saga_id, $filters);

        // Format for frontend
        $formatted = array_map(fn($s) => $s->toArray(), $summaries);

        wp_send_json_success([
            'summaries' => $formatted,
            'total' => count($formatted),
            'page' => $page,
            'per_page' => $per_page,
        ]);

    } catch (\Exception $e) {
        error_log(sprintf(
            '[SAGA][SUMMARY][AJAX][ERROR] Load summaries failed: %s',
            $e->getMessage()
        ));

        wp_send_json_error([
            'message' => __('Failed to load summaries', 'saga-manager'),
        ], 500);
    }
});

/**
 * Load single summary detail
 */
add_action('wp_ajax_saga_load_summary_detail', function() {
    check_ajax_referer('saga_summaries_nonce', 'nonce');

    if (!current_user_can('read')) {
        wp_send_json_error(['message' => summary_i18n_text('error_unauthorized')], 403);
    }

    $summary_id = isset($_POST['summary_id']) ? absint($_POST['summary_id']) : 0;

    if ($summary_id === 0) {
        wp_send_json_error(['message' => __('Invalid summary ID', 'saga-manager')], 400);
    }

    try {
        $repository = new SummaryRepository();
        $summary = $repository->findById($summary_id);

        if (!$summary) {
            wp_send_json_error(['message' => summary_i18n_text('error_not_found')], 404);
        }

        wp_send_json_success($summary->toArray());

    } catch (\Exception $e) {
        error_log(sprintf(
            '[SAGA][SUMMARY][AJAX][ERROR] Load summary detail failed: %s',
            $e->getMessage()
        ));

        wp_send_json_error([
            'message' => __('Failed to load summary', 'saga-manager'),
        ], 500);
    }
});

/**
 * Regenerate summary
 */
add_action('wp_ajax_saga_regenerate_summary', function() {
    check_ajax_referer('saga_summaries_nonce', 'nonce');

    if (!current_user_can('edit_posts')) {
        wp_send_json_error(['message' => summary_i18n_text('error_unauthorized')], 403);
    }

    $summary_id = isset($_POST['summary_id']) ? absint($_POST['summary_id']) : 0;
    $reason = isset($_POST['reason']) ? sanitize_text_field($_POST['reason']) : '';

    if ($summary_id === 0) {
        wp_send_json_error(['message' => __('Invalid summary ID', 'saga-manager')], 400);
    }

    if (empty($reason)) {
        $reason = __('Manual regeneration', 'saga-manager');
    }

    try {
        $orchestrator = new SummaryOrchestrator();
        $new_summary = $orchestrator->regenerateSummary($summary_id, $reason);

        error_log(sprintf(
            '[SAGA][SUMMARY][AJAX] Regenerated summary #%d -> #%d',
            $summary_id,
            $new_summary->id
        ));

        wp_send_json_success([
            'summary_id' => $new_summary->id,
            'summary' => $new_summary->toArray(),
            'message' => summary_i18n_text('success_regenerated'),
        ]);

    } catch (\Exception $e) {
        error_log(sprintf(
            '[SAGA][SUMMARY][AJAX][ERROR] Regenerate summary failed: %s',
            $e->getMessage()
        ));

        wp_send_json_error([
            'message' => sprintf(__('Failed to regenerate: %s', 'saga-manager'), $e->getMessage()),
        ], 500);
    }
});

/**
 * Delete summary
 */
add_action('wp_ajax_saga_delete_summary', function() {
    check_ajax_referer('saga_summaries_nonce', 'nonce');

    if (!current_user_can('delete_posts')) {
        wp_send_json_error(['message' => summary_i18n_text('error_unauthorized')], 403);
    }

    $summary_id = isset($_POST['summary_id']) ? absint($_POST['summary_id']) : 0;

    if ($summary_id === 0) {
        wp_send_json_error(['message' => __('Invalid summary ID', 'saga-manager')], 400);
    }

    try {
        $repository = new SummaryRepository();
        $success = $repository->delete($summary_id);

        if (!$success) {
            throw new \Exception('Delete failed');
        }

        error_log(sprintf('[SAGA][SUMMARY][AJAX] Deleted summary #%d', $summary_id));

        wp_send_json_success([
            'summary_id' => $summary_id,
            'message' => summary_i18n_text('success_deleted'),
        ]);

    } catch (\Exception $e) {
        error_log(sprintf(
            '[SAGA][SUMMARY][AJAX][ERROR] Delete summary failed: %s',
            $e->getMessage()
        ));

        wp_send_json_error([
            'message' => __('Failed to delete summary', 'saga-manager'),
        ], 500);
    }
});

/**
 * Export summary
 */
add_action('wp_ajax_saga_export_summary', function() {
    check_ajax_referer('saga_summaries_nonce', 'nonce');

    if (!current_user_can('read')) {
        wp_send_json_error(['message' => summary_i18n_text('error_unauthorized')], 403);
    }

    $summary_id = isset($_POST['summary_id']) ? absint($_POST['summary_id']) : 0;
    $format = isset($_POST['format']) ? sanitize_key($_POST['format']) : 'markdown';

    if ($summary_id === 0) {
        wp_send_json_error(['message' => __('Invalid summary ID', 'saga-manager')], 400);
    }

    try {
        $output_format = OutputFormat::from($format);
    } catch (\ValueError $e) {
        $output_format = OutputFormat::MARKDOWN;
    }

    try {
        $repository = new SummaryRepository();
        $summary = $repository->findById($summary_id);

        if (!$summary) {
            wp_send_json_error(['message' => summary_i18n_text('error_not_found')], 404);
        }

        // Format content based on output format
        $content = match($output_format) {
            OutputFormat::MARKDOWN => $summary->summary_text,
            OutputFormat::HTML => wpautop($summary->summary_text),
            OutputFormat::PLAIN => strip_tags($summary->summary_text),
        };

        // Add header
        $header = "# {$summary->title}\n\n";
        $header .= sprintf(__('Generated: %s', 'saga-manager'), date_i18n('Y-m-d H:i', $summary->created_at)) . "\n";
        $header .= sprintf(__('Type: %s', 'saga-manager'), $summary->summary_type->getLabel()) . "\n";
        $header .= sprintf(__('Words: %d', 'saga-manager'), $summary->word_count) . "\n\n";
        $header .= "---\n\n";

        $export = $header . $content;

        wp_send_json_success([
            'content' => $export,
            'filename' => sanitize_file_name($summary->title . '.' . $output_format->getExtension()),
            'format' => $format,
        ]);

    } catch (\Exception $e) {
        error_log(sprintf(
            '[SAGA][SUMMARY][AJAX][ERROR] Export summary failed: %s',
            $e->getMessage()
        ));

        wp_send_json_error([
            'message' => __('Failed to export summary', 'saga-manager'),
        ], 500);
    }
});

/**
 * Submit summary feedback
 */
add_action('wp_ajax_saga_submit_summary_feedback', function() {
    check_ajax_referer('saga_summaries_nonce', 'nonce');

    if (!current_user_can('read')) {
        wp_send_json_error(['message' => summary_i18n_text('error_unauthorized')], 403);
    }

    $summary_id = isset($_POST['summary_id']) ? absint($_POST['summary_id']) : 0;
    $rating = isset($_POST['rating']) ? absint($_POST['rating']) : 0;
    $feedback = isset($_POST['feedback']) ? sanitize_textarea_field($_POST['feedback']) : '';

    if ($summary_id === 0) {
        wp_send_json_error(['message' => __('Invalid summary ID', 'saga-manager')], 400);
    }

    if ($rating < 1 || $rating > 5) {
        wp_send_json_error(['message' => __('Rating must be between 1 and 5', 'saga-manager')], 400);
    }

    try {
        // Store feedback in user meta or custom table
        global $wpdb;
        $feedback_table = $wpdb->prefix . 'saga_summary_feedback';

        $wpdb->insert($feedback_table, [
            'summary_id' => $summary_id,
            'user_id' => get_current_user_id(),
            'rating' => $rating,
            'feedback_text' => $feedback,
            'created_at' => current_time('mysql'),
        ]);

        error_log(sprintf(
            '[SAGA][SUMMARY][AJAX] Feedback submitted for summary #%d (rating: %d)',
            $summary_id,
            $rating
        ));

        wp_send_json_success([
            'message' => summary_i18n_text('success_feedback'),
        ]);

    } catch (\Exception $e) {
        error_log(sprintf(
            '[SAGA][SUMMARY][AJAX][ERROR] Submit feedback failed: %s',
            $e->getMessage()
        ));

        wp_send_json_error([
            'message' => __('Failed to submit feedback', 'saga-manager'),
        ], 500);
    }
});

/**
 * Get summary statistics
 */
add_action('wp_ajax_saga_get_summary_statistics', function() {
    check_ajax_referer('saga_summaries_nonce', 'nonce');

    if (!current_user_can('read')) {
        wp_send_json_error(['message' => summary_i18n_text('error_unauthorized')], 403);
    }

    $saga_id = isset($_POST['saga_id']) ? absint($_POST['saga_id']) : 0;

    if ($saga_id === 0) {
        wp_send_json_error(['message' => summary_i18n_text('error_no_saga')], 400);
    }

    try {
        $repository = new SummaryRepository();
        $stats = $repository->getStatistics($saga_id);

        wp_send_json_success($stats);

    } catch (\Exception $e) {
        error_log(sprintf(
            '[SAGA][SUMMARY][AJAX][ERROR] Get statistics failed: %s',
            $e->getMessage()
        ));

        wp_send_json_error([
            'message' => __('Failed to get statistics', 'saga-manager'),
        ], 500);
    }
});

/**
 * Search summaries
 */
add_action('wp_ajax_saga_search_summaries', function() {
    check_ajax_referer('saga_summaries_nonce', 'nonce');

    if (!current_user_can('read')) {
        wp_send_json_error(['message' => summary_i18n_text('error_unauthorized')], 403);
    }

    $saga_id = isset($_POST['saga_id']) ? absint($_POST['saga_id']) : 0;
    $search_term = isset($_POST['search_term']) ? sanitize_text_field($_POST['search_term']) : '';
    $limit = isset($_POST['limit']) ? absint($_POST['limit']) : 20;

    if ($saga_id === 0) {
        wp_send_json_error(['message' => summary_i18n_text('error_no_saga')], 400);
    }

    if (empty($search_term)) {
        wp_send_json_error(['message' => __('Search term cannot be empty', 'saga-manager')], 400);
    }

    try {
        $repository = new SummaryRepository();
        $summaries = $repository->search($saga_id, $search_term, $limit);

        // Format for frontend
        $formatted = array_map(fn($s) => $s->toArray(), $summaries);

        wp_send_json_success([
            'summaries' => $formatted,
            'total' => count($formatted),
            'search_term' => $search_term,
        ]);

    } catch (\Exception $e) {
        error_log(sprintf(
            '[SAGA][SUMMARY][AJAX][ERROR] Search summaries failed: %s',
            $e->getMessage()
        ));

        wp_send_json_error([
            'message' => __('Failed to search summaries', 'saga-manager'),
        ], 500);
    }
});
