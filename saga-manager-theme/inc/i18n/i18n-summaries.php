<?php
/**
 * Bilingual Translation Helper for Summaries
 *
 * Provides bilingual (French/English) text strings for summary interface.
 * All text wrapped in WordPress i18n functions for proper translation.
 *
 * @package SagaManager
 * @subpackage I18n
 * @since 1.5.0
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Get translated text for summaries
 *
 * @param string $key Text key
 * @param string|null $lang Optional language code (fr, en)
 * @return string Translated text
 */
function summary_i18n_text(string $key, ?string $lang = null): string
{
    // Use WordPress locale if not specified
    if ($lang === null) {
        $lang = substr(get_locale(), 0, 2);
    }

    $texts = [
        // Page titles
        'page_title' => __('Auto-Generated Summaries', 'saga-manager'),
        'new_summary' => __('Generate New Summary', 'saga-manager'),
        'summary_list' => __('Summary List', 'saga-manager'),
        'statistics' => __('Statistics', 'saga-manager'),

        // Summary types
        'character_arc' => __('Character Arc', 'saga-manager'),
        'timeline' => __('Timeline Summary', 'saga-manager'),
        'relationship' => __('Relationship Overview', 'saga-manager'),
        'faction' => __('Faction Analysis', 'saga-manager'),
        'location' => __('Location Summary', 'saga-manager'),

        // Form labels
        'select_saga' => __('Select Saga', 'saga-manager'),
        'select_type' => __('Summary Type', 'saga-manager'),
        'select_entity' => __('Select Entity', 'saga-manager'),
        'select_scope' => __('Scope', 'saga-manager'),
        'select_provider' => __('AI Provider', 'saga-manager'),
        'select_model' => __('AI Model', 'saga-manager'),

        // Scope options
        'scope_full' => __('Full Saga', 'saga-manager'),
        'scope_chapter' => __('Chapter/Section', 'saga-manager'),
        'scope_date_range' => __('Date Range', 'saga-manager'),

        // Buttons
        'btn_generate' => __('Generate Summary', 'saga-manager'),
        'btn_regenerate' => __('Regenerate', 'saga-manager'),
        'btn_cancel' => __('Cancel', 'saga-manager'),
        'btn_delete' => __('Delete', 'saga-manager'),
        'btn_export' => __('Export', 'saga-manager'),
        'btn_preview' => __('Preview', 'saga-manager'),
        'btn_view_detail' => __('View Details', 'saga-manager'),
        'btn_submit_feedback' => __('Submit Feedback', 'saga-manager'),

        // Status messages
        'status_pending' => __('Pending', 'saga-manager'),
        'status_generating' => __('Generating...', 'saga-manager'),
        'status_completed' => __('Completed', 'saga-manager'),
        'status_failed' => __('Failed', 'saga-manager'),
        'status_cancelled' => __('Cancelled', 'saga-manager'),

        // Progress messages
        'progress_collecting' => __('Collecting verified data...', 'saga-manager'),
        'progress_generating' => __('Generating AI summary...', 'saga-manager'),
        'progress_saving' => __('Saving summary...', 'saga-manager'),
        'progress_complete' => __('Summary complete!', 'saga-manager'),

        // Quality labels
        'quality_excellent' => __('Excellent', 'saga-manager'),
        'quality_good' => __('Good', 'saga-manager'),
        'quality_fair' => __('Fair', 'saga-manager'),
        'quality_poor' => __('Poor', 'saga-manager'),
        'quality_very_poor' => __('Very Poor', 'saga-manager'),

        // Readability labels
        'readability_very_easy' => __('Very Easy', 'saga-manager'),
        'readability_easy' => __('Easy', 'saga-manager'),
        'readability_fairly_easy' => __('Fairly Easy', 'saga-manager'),
        'readability_standard' => __('Standard', 'saga-manager'),
        'readability_fairly_difficult' => __('Fairly Difficult', 'saga-manager'),
        'readability_difficult' => __('Difficult', 'saga-manager'),
        'readability_very_difficult' => __('Very Difficult', 'saga-manager'),

        // Table headers
        'th_id' => __('ID', 'saga-manager'),
        'th_type' => __('Type', 'saga-manager'),
        'th_title' => __('Title', 'saga-manager'),
        'th_status' => __('Status', 'saga-manager'),
        'th_quality' => __('Quality', 'saga-manager'),
        'th_words' => __('Words', 'saga-manager'),
        'th_cost' => __('Cost', 'saga-manager'),
        'th_created' => __('Created', 'saga-manager'),
        'th_actions' => __('Actions', 'saga-manager'),

        // Statistics
        'stat_total_summaries' => __('Total Summaries', 'saga-manager'),
        'stat_avg_quality' => __('Average Quality', 'saga-manager'),
        'stat_avg_readability' => __('Average Readability', 'saga-manager'),
        'stat_total_cost' => __('Total Cost', 'saga-manager'),
        'stat_total_words' => __('Total Words', 'saga-manager'),

        // Error messages
        'error_no_saga' => __('Please select a saga', 'saga-manager'),
        'error_no_type' => __('Please select a summary type', 'saga-manager'),
        'error_no_entity' => __('This summary type requires an entity', 'saga-manager'),
        'error_generation_failed' => __('Summary generation failed', 'saga-manager'),
        'error_not_found' => __('Summary not found', 'saga-manager'),
        'error_network' => __('Network error. Please try again.', 'saga-manager'),

        // Success messages
        'success_generated' => __('Summary generated successfully!', 'saga-manager'),
        'success_regenerated' => __('Summary regenerated successfully!', 'saga-manager'),
        'success_deleted' => __('Summary deleted successfully!', 'saga-manager'),
        'success_feedback' => __('Thank you for your feedback!', 'saga-manager'),

        // Confirmations
        'confirm_delete' => __('Are you sure you want to delete this summary?', 'saga-manager'),
        'confirm_regenerate' => __('Are you sure you want to regenerate this summary? The current version will be archived.', 'saga-manager'),
        'confirm_cancel' => __('Are you sure you want to cancel this generation?', 'saga-manager'),

        // Export formats
        'export_markdown' => __('Export as Markdown', 'saga-manager'),
        'export_html' => __('Export as HTML', 'saga-manager'),
        'export_plain' => __('Export as Plain Text', 'saga-manager'),

        // Filter labels
        'filter_all_types' => __('All Types', 'saga-manager'),
        'filter_all_status' => __('All Statuses', 'saga-manager'),
        'filter_date_range' => __('Date Range', 'saga-manager'),
        'filter_apply' => __('Apply Filters', 'saga-manager'),
        'filter_reset' => __('Reset Filters', 'saga-manager'),

        // Descriptions
        'desc_verified_data' => __('All summaries are based on verified data from your saga database.', 'saga-manager'),
        'desc_human_friendly' => __('AI-generated content optimized for human readability.', 'saga-manager'),
        'desc_cached' => __('Summaries are cached to reduce API costs. Regenerate if data has changed.', 'saga-manager'),

        // Placeholders
        'placeholder_search' => __('Search summaries...', 'saga-manager'),
        'placeholder_select_entity' => __('-- Select Entity --', 'saga-manager'),
        'placeholder_feedback' => __('Your feedback helps improve summary quality...', 'saga-manager'),

        // Loading states
        'loading' => __('Loading...', 'saga-manager'),
        'processing' => __('Processing...', 'saga-manager'),
        'please_wait' => __('Please wait...', 'saga-manager'),

        // Empty states
        'no_summaries' => __('No summaries yet. Generate your first summary above.', 'saga-manager'),
        'no_results' => __('No summaries match your filters.', 'saga-manager'),
    ];

    return $texts[$key] ?? $key;
}
