<?php
/**
 * Summary Template Engine
 *
 * Loads and renders summary templates with variable substitution.
 * Templates define system prompts and user prompt templates for AI summary generation.
 * Supports variable substitution ({{character_name}}, {{saga_name}}, etc.).
 *
 * @package SagaManager
 * @subpackage AI\SummaryGenerator
 * @since 1.5.0
 */

declare(strict_types=1);

namespace SagaManager\AI\SummaryGenerator;

use SagaManager\AI\Entities\SummaryType;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Summary Template Engine
 *
 * Manages template loading and prompt rendering with variable substitution.
 */
class SummaryTemplateEngine
{
    private string $templates_table;
    private int $cache_ttl = 3600; // 1 hour

    /**
     * Constructor
     */
    public function __construct()
    {
        global $wpdb;
        $this->templates_table = $wpdb->prefix . 'saga_summary_templates';
    }

    /**
     * Load template for summary type
     *
     * Loads template from database with fallback to defaults.
     * Returns array with system_prompt and user_prompt_template.
     *
     * @param SummaryType $type Summary type
     * @param string|null $template_name Optional specific template name
     * @return array Template data
     * @throws \Exception If no template found
     *
     * @example
     * $template = $engine->loadTemplate(SummaryType::CHARACTER_ARC);
     * // Returns: [
     * //   'system_prompt' => 'You are an expert literary analyst...',
     * //   'user_prompt_template' => 'Create a summary of {{character_name}}...',
     * //   'temperature' => 0.7,
     * //   'max_length' => 800,
     * //   'style' => 'professional'
     * // ]
     */
    public function loadTemplate(SummaryType $type, ?string $template_name = null): array
    {
        global $wpdb;

        // Build cache key
        $cache_key = $template_name
            ? "summary_template_{$template_name}"
            : "summary_template_default_{$type->value}";

        // Check cache
        $cached = wp_cache_get($cache_key, 'saga');
        if ($cached !== false) {
            return $cached;
        }

        // Query database
        if ($template_name !== null) {
            // Load specific template
            $template = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM {$this->templates_table}
                 WHERE template_name = %s AND is_active = 1",
                $template_name
            ), ARRAY_A);
        } else {
            // Load default template for type
            $template = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM {$this->templates_table}
                 WHERE summary_type = %s AND is_default = 1 AND is_active = 1",
                $type->value
            ), ARRAY_A);
        }

        if (!$template) {
            // Fallback to any active template of this type
            $template = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM {$this->templates_table}
                 WHERE summary_type = %s AND is_active = 1
                 ORDER BY usage_count DESC
                 LIMIT 1",
                $type->value
            ), ARRAY_A);
        }

        if (!$template) {
            // Ultimate fallback: create basic template
            error_log("[SAGA][SUMMARY] No template found for {$type->value}, using fallback");
            $template = $this->getFallbackTemplate($type);
        }

        // Format template data
        $data = [
            'id' => (int)($template['id'] ?? 0),
            'template_name' => $template['template_name'] ?? '',
            'system_prompt' => $template['system_prompt'] ?? '',
            'user_prompt_template' => $template['user_prompt_template'] ?? '',
            'temperature' => isset($template['temperature']) ? (float)$template['temperature'] : 0.7,
            'max_length' => (int)($template['max_length'] ?? 1000),
            'style' => $template['style'] ?? 'professional',
            'output_format' => $template['output_format'] ?? 'markdown',
            'include_quotes' => (bool)($template['include_quotes'] ?? true),
            'include_analysis' => (bool)($template['include_analysis'] ?? true),
        ];

        // Cache result
        wp_cache_set($cache_key, $data, 'saga', $this->cache_ttl);

        return $data;
    }

    /**
     * Render prompt with variable substitution
     *
     * Replaces {{variable}} placeholders with actual values.
     * Ensures all values are human-friendly and readable.
     *
     * @param array $template Template data from loadTemplate()
     * @param array $variables Variables to substitute
     * @return string Rendered prompt
     *
     * @example
     * $prompt = $engine->renderPrompt($template, [
     *     'character_name' => 'Paul Atreides',
     *     'saga_name' => 'Dune',
     *     'character_data' => $formatted_data
     * ]);
     */
    public function renderPrompt(array $template, array $variables): string
    {
        $prompt = $template['user_prompt_template'];

        // Add instructions for human-friendly output
        $style_instruction = "\n\nIMPORTANT: Write in clear, human-friendly language. ";
        $style_instruction .= "Avoid jargon, use active voice, and make the summary engaging and readable. ";
        $style_instruction .= "Style: {$template['style']}. ";
        $style_instruction .= "Maximum length: {$template['max_length']} words.";

        // Substitute variables
        foreach ($variables as $key => $value) {
            $placeholder = '{{' . $key . '}}';

            // Convert value to string if needed
            $string_value = $this->valueToString($value);

            $prompt = str_replace($placeholder, $string_value, $prompt);
        }

        // Add style instruction
        $prompt .= $style_instruction;

        // Ensure output format instruction
        if ($template['output_format'] === 'markdown') {
            $prompt .= "\n\nFormat the response in Markdown with clear headings and bullet points.";
        } elseif ($template['output_format'] === 'html') {
            $prompt .= "\n\nFormat the response in semantic HTML with proper tags.";
        } else {
            $prompt .= "\n\nFormat the response as plain text with clear paragraphs.";
        }

        return $prompt;
    }

    /**
     * Get system prompt from template
     *
     * Returns the system prompt with human-friendly writing instructions.
     *
     * @param array $template Template data
     * @return string System prompt
     */
    public function getSystemPrompt(array $template): string
    {
        $system_prompt = $template['system_prompt'];

        // Add universal human-friendly writing instructions
        $system_prompt .= "\n\nYou MUST write in clear, human-friendly language. ";
        $system_prompt .= "Avoid technical jargon, use active voice, and make content engaging. ";
        $system_prompt .= "All summaries must be based ONLY on the provided verified data - no fictional content or placeholders.";

        return $system_prompt;
    }

    /**
     * Get fallback template for type
     *
     * Returns basic template when none exists in database.
     *
     * @param SummaryType $type Summary type
     * @return array Template data
     */
    private function getFallbackTemplate(SummaryType $type): array
    {
        $templates = [
            'character_arc' => [
                'template_name' => 'character_arc_fallback',
                'system_prompt' => 'You are an expert literary analyst. Create a comprehensive summary of the character\'s arc based on verified data only.',
                'user_prompt_template' => 'Summarize the character arc for {{character_name}} in {{saga_name}}. Data:\n{{character_data}}',
                'temperature' => 0.7,
                'max_length' => 800,
                'style' => 'professional',
                'output_format' => 'markdown',
                'include_quotes' => true,
                'include_analysis' => true,
            ],
            'timeline' => [
                'template_name' => 'timeline_fallback',
                'system_prompt' => 'You are a skilled timeline analyst. Create chronological summaries of events.',
                'user_prompt_template' => 'Summarize the timeline for {{saga_name}}. Events:\n{{timeline_events}}',
                'temperature' => 0.6,
                'max_length' => 1000,
                'style' => 'professional',
                'output_format' => 'markdown',
                'include_quotes' => false,
                'include_analysis' => true,
            ],
            'relationship' => [
                'template_name' => 'relationship_fallback',
                'system_prompt' => 'You are an expert in social dynamics. Analyze relationship networks from verified data.',
                'user_prompt_template' => 'Summarize the relationship network in {{saga_name}}. Data:\n{{relationships}}',
                'temperature' => 0.7,
                'max_length' => 700,
                'style' => 'professional',
                'output_format' => 'markdown',
                'include_quotes' => true,
                'include_analysis' => true,
            ],
            'faction' => [
                'template_name' => 'faction_fallback',
                'system_prompt' => 'You are a political analyst. Analyze factions and their impact from verified data.',
                'user_prompt_template' => 'Summarize the faction {{faction_name}} in {{saga_name}}. Data:\n{{faction_data}}',
                'temperature' => 0.6,
                'max_length' => 600,
                'style' => 'professional',
                'output_format' => 'markdown',
                'include_quotes' => false,
                'include_analysis' => true,
            ],
            'location' => [
                'template_name' => 'location_fallback',
                'system_prompt' => 'You are a world-building expert. Create summaries of locations and their significance.',
                'user_prompt_template' => 'Summarize the location {{location_name}} in {{saga_name}}. Data:\n{{location_data}}',
                'temperature' => 0.7,
                'max_length' => 500,
                'style' => 'professional',
                'output_format' => 'markdown',
                'include_quotes' => false,
                'include_analysis' => true,
            ],
        ];

        return $templates[$type->value] ?? $templates['character_arc'];
    }

    /**
     * Convert value to string for substitution
     *
     * @param mixed $value Variable value
     * @return string String representation
     */
    private function valueToString(mixed $value): string
    {
        if (is_string($value)) {
            return $value;
        }

        if (is_array($value)) {
            // Format arrays as readable lists
            return $this->formatArrayAsText($value);
        }

        if (is_object($value)) {
            // Convert objects to JSON
            return json_encode($value, JSON_PRETTY_PRINT);
        }

        return (string)$value;
    }

    /**
     * Format array as human-readable text
     *
     * @param array $array Array to format
     * @param int $depth Recursion depth
     * @return string Formatted text
     */
    private function formatArrayAsText(array $array, int $depth = 0): string
    {
        $output = '';
        $indent = str_repeat('  ', $depth);

        foreach ($array as $key => $value) {
            if (is_array($value)) {
                $output .= "{$indent}{$key}:\n";
                $output .= $this->formatArrayAsText($value, $depth + 1);
            } else {
                $output .= "{$indent}- {$key}: {$value}\n";
            }
        }

        return $output;
    }

    /**
     * Update template usage statistics
     *
     * Increments usage count and updates average quality score.
     *
     * @param int $template_id Template ID
     * @param float|null $quality_score Latest quality score (0-100)
     * @return bool Success
     */
    public function updateTemplateStats(int $template_id, ?float $quality_score = null): bool
    {
        global $wpdb;

        // Increment usage count
        $wpdb->query($wpdb->prepare(
            "UPDATE {$this->templates_table}
             SET usage_count = usage_count + 1
             WHERE id = %d",
            $template_id
        ));

        // Update average quality score if provided
        if ($quality_score !== null) {
            // Get current average and count
            $template = $wpdb->get_row($wpdb->prepare(
                "SELECT usage_count, avg_quality_score FROM {$this->templates_table} WHERE id = %d",
                $template_id
            ));

            if ($template) {
                $count = (int)$template->usage_count;
                $current_avg = (float)($template->avg_quality_score ?? 0);

                // Calculate new average
                $new_avg = (($current_avg * ($count - 1)) + $quality_score) / $count;

                $wpdb->update(
                    $this->templates_table,
                    ['avg_quality_score' => round($new_avg, 2)],
                    ['id' => $template_id],
                    ['%f'],
                    ['%d']
                );
            }
        }

        // Clear cache
        wp_cache_delete("summary_template_{$template_id}", 'saga');

        return true;
    }

    /**
     * List all active templates for a type
     *
     * @param SummaryType $type Summary type
     * @return array Templates array
     */
    public function listTemplates(SummaryType $type): array
    {
        global $wpdb;

        $templates = $wpdb->get_results($wpdb->prepare(
            "SELECT id, template_name, description, usage_count, avg_quality_score, is_default
             FROM {$this->templates_table}
             WHERE summary_type = %s AND is_active = 1
             ORDER BY is_default DESC, usage_count DESC",
            $type->value
        ), ARRAY_A);

        return $templates;
    }

    /**
     * Create custom template
     *
     * @param SummaryType $type Summary type
     * @param string $name Template name
     * @param string $system_prompt System prompt
     * @param string $user_prompt_template User prompt template
     * @param array $options Additional options
     * @return int Template ID
     * @throws \Exception If creation fails
     */
    public function createTemplate(
        SummaryType $type,
        string $name,
        string $system_prompt,
        string $user_prompt_template,
        array $options = []
    ): int {
        global $wpdb;

        $data = [
            'template_name' => sanitize_key($name),
            'summary_type' => $type->value,
            'description' => $options['description'] ?? '',
            'system_prompt' => $system_prompt,
            'user_prompt_template' => $user_prompt_template,
            'output_format' => $options['output_format'] ?? 'markdown',
            'max_length' => $options['max_length'] ?? 1000,
            'style' => $options['style'] ?? 'professional',
            'include_quotes' => $options['include_quotes'] ?? true,
            'include_analysis' => $options['include_analysis'] ?? true,
            'temperature' => $options['temperature'] ?? 0.7,
            'is_default' => $options['is_default'] ?? false,
            'is_active' => true,
            'usage_count' => 0,
            'created_by' => get_current_user_id(),
        ];

        $result = $wpdb->insert($this->templates_table, $data);

        if ($result === false) {
            throw new \Exception("Failed to create template: {$wpdb->last_error}");
        }

        return $wpdb->insert_id;
    }
}
