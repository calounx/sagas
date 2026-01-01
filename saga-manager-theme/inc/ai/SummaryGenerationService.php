<?php
/**
 * Summary Generation Service
 *
 * Core service for AI-powered summary generation from VERIFIED DATA ONLY.
 * Orchestrates data collection, template rendering, AI API calls, and summary creation.
 *
 * CRITICAL: All summaries are based on real database data - no fictional content.
 * All content is generated to be HUMAN-FRIENDLY and readable.
 *
 * @package SagaManager
 * @subpackage AI\SummaryGenerator
 * @since 1.5.0
 */

declare(strict_types=1);

namespace SagaManager\AI\SummaryGenerator;

use SagaManager\AI\Entities\SummaryRequest;
use SagaManager\AI\Entities\GeneratedSummary;
use SagaManager\AI\Entities\SummaryType;
use SagaManager\AI\Entities\AIProvider;
use SagaManager\AI\AIClient;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Summary Generation Service
 *
 * Generates AI-powered summaries from verified saga data.
 */
class SummaryGenerationService
{
    private DataCollectionService $data_collector;
    private SummaryTemplateEngine $template_engine;
    private AIClient $ai_client;

    /**
     * Constructor
     *
     * @param DataCollectionService|null $data_collector Optional data collector
     * @param SummaryTemplateEngine|null $template_engine Optional template engine
     * @param AIClient|null $ai_client Optional AI client
     */
    public function __construct(
        ?DataCollectionService $data_collector = null,
        ?SummaryTemplateEngine $template_engine = null,
        ?AIClient $ai_client = null
    ) {
        $this->data_collector = $data_collector ?? new DataCollectionService();
        $this->template_engine = $template_engine ?? new SummaryTemplateEngine();
        $this->ai_client = $ai_client ?? new AIClient();
    }

    /**
     * Generate summary from request
     *
     * Main orchestration method that:
     * 1. Collects verified data from database
     * 2. Builds AI context from real data only
     * 3. Calls AI API with human-friendly instructions
     * 4. Parses response and extracts key points
     * 5. Calculates quality and readability scores
     * 6. Returns GeneratedSummary value object with source references
     *
     * @param SummaryRequest $request Summary request
     * @return GeneratedSummary Generated summary
     * @throws \Exception If generation fails
     *
     * @example
     * $request = new SummaryRequest(
     *     id: null,
     *     saga_id: 1,
     *     user_id: 1,
     *     summary_type: SummaryType::CHARACTER_ARC,
     *     entity_id: 123,
     *     scope: SummaryScope::FULL,
     *     scope_params: [],
     *     status: RequestStatus::PENDING,
     *     priority: 5,
     *     ai_provider: AIProvider::OPENAI,
     *     ai_model: 'gpt-4'
     * );
     * $summary = $service->generateSummary($request);
     */
    public function generateSummary(SummaryRequest $request): GeneratedSummary
    {
        error_log(sprintf(
            '[SAGA][SUMMARY] Generating %s summary for saga #%d',
            $request->summary_type->value,
            $request->saga_id
        ));

        // Step 1: Collect verified data
        $data = $this->collectVerifiedData($request);

        // Step 2: Build AI context
        $context = $this->buildAIContext($request, $data);

        // Step 3: Call AI API
        $ai_response = $this->callAI($request, $context);

        // Step 4: Parse response
        $parsed = $this->parseAIResponse($ai_response, $request);

        // Step 5: Calculate scores
        $quality_score = $this->calculateQualityScore($parsed['text'], $data);
        $readability_score = GeneratedSummary::calculateReadabilityScore($parsed['text']);

        // Step 6: Extract key points
        $key_points = GeneratedSummary::extractKeyPoints($parsed['text']);

        // Step 7: Build metadata with source references
        $metadata = $this->buildMetadata($data, $parsed);

        // Step 8: Generate cache key
        $cache_key = GeneratedSummary::generateCacheKey(
            $request->saga_id,
            $request->summary_type,
            $request->entity_id,
            $request->scope_params
        );

        // Create GeneratedSummary object
        $summary = new GeneratedSummary(
            id: null,
            request_id: $request->id ?? 0,
            saga_id: $request->saga_id,
            entity_id: $request->entity_id,
            summary_type: $request->summary_type,
            version: 1,
            title: $parsed['title'],
            summary_text: $parsed['text'],
            word_count: GeneratedSummary::calculateWordCount($parsed['text']),
            key_points: $key_points,
            metadata: $metadata,
            quality_score: $quality_score,
            readability_score: $readability_score,
            is_current: true,
            regeneration_reason: null,
            cache_key: $cache_key,
            cache_expires_at: time() + (7 * DAY_IN_SECONDS), // 7 days
            ai_model: $request->ai_model,
            token_count: $parsed['token_count'] ?? 0,
            generation_cost: $parsed['cost'] ?? 0.0
        );

        error_log(sprintf(
            '[SAGA][SUMMARY] Generated summary: %d words, quality %.2f, readability %.2f',
            $summary->word_count,
            $summary->quality_score ?? 0,
            $summary->readability_score ?? 0
        ));

        return $summary;
    }

    /**
     * Collect verified data from database
     *
     * @param SummaryRequest $request Request
     * @return array Collected data with source IDs
     * @throws \Exception If data collection fails
     */
    private function collectVerifiedData(SummaryRequest $request): array
    {
        try {
            return match($request->summary_type) {
                SummaryType::CHARACTER_ARC => $this->data_collector->collectCharacterData($request->entity_id),
                SummaryType::TIMELINE => $this->data_collector->collectTimelineData($request->saga_id, $request->scope_params),
                SummaryType::RELATIONSHIP => $this->data_collector->collectRelationshipData($request->saga_id, $request->entity_id),
                SummaryType::FACTION => $this->data_collector->collectFactionData($request->entity_id),
                SummaryType::LOCATION => $this->data_collector->collectLocationData($request->entity_id),
            };
        } catch (\Exception $e) {
            error_log("[SAGA][SUMMARY][ERROR] Data collection failed: {$e->getMessage()}");
            throw new \Exception("Failed to collect data: {$e->getMessage()}", 0, $e);
        }
    }

    /**
     * Build AI context with templates and variables
     *
     * @param SummaryRequest $request Request
     * @param array $data Collected data
     * @return array Context with system_prompt and user_prompt
     */
    private function buildAIContext(SummaryRequest $request, array $data): array
    {
        // Load template
        $template = $this->template_engine->loadTemplate($request->summary_type);

        // Build variables for substitution
        $variables = $this->buildTemplateVariables($request, $data);

        // Render prompts
        $system_prompt = $this->template_engine->getSystemPrompt($template);
        $user_prompt = $this->template_engine->renderPrompt($template, $variables);

        return [
            'system_prompt' => $system_prompt,
            'user_prompt' => $user_prompt,
            'template' => $template,
        ];
    }

    /**
     * Build template variables from data
     *
     * @param SummaryRequest $request Request
     * @param array $data Collected data
     * @return array Variables for template substitution
     */
    private function buildTemplateVariables(SummaryRequest $request, array $data): array
    {
        $variables = [];

        // Common variables
        if (isset($data['entity'])) {
            $variables['character_name'] = $data['entity']['canonical_name'] ?? '';
            $variables['faction_name'] = $data['entity']['canonical_name'] ?? '';
            $variables['location_name'] = $data['entity']['canonical_name'] ?? '';
            $variables['saga_name'] = $data['entity']['saga_name'] ?? '';
        }

        if (isset($data['saga'])) {
            $variables['saga_name'] = $data['saga']['name'] ?? '';
        }

        // Format data for human-friendly AI consumption
        $type_map = [
            SummaryType::CHARACTER_ARC => 'character',
            SummaryType::TIMELINE => 'timeline',
            SummaryType::RELATIONSHIP => 'relationship',
            SummaryType::FACTION => 'faction',
            SummaryType::LOCATION => 'location',
        ];

        $formatted = $this->data_collector->formatForAI($data, $type_map[$request->summary_type]);

        // Type-specific variables
        switch ($request->summary_type) {
            case SummaryType::CHARACTER_ARC:
                $variables['character_data'] = $formatted;
                $variables['timeline_events'] = $this->formatEvents($data['events'] ?? []);
                $variables['relationships'] = $this->formatRelationships($data['relationships'] ?? []);
                break;

            case SummaryType::TIMELINE:
                $variables['timeline_events'] = $formatted;
                $variables['entities'] = $this->formatEntities($data['entities'] ?? []);
                $variables['scope_description'] = $this->buildScopeDescription($request);
                break;

            case SummaryType::RELATIONSHIP:
                $variables['relationships'] = $formatted;
                $variables['entities'] = $this->formatEntities($data['entities'] ?? []);
                $variables['relevant_events'] = $this->formatEvents($data['events'] ?? []);
                break;

            case SummaryType::FACTION:
                $variables['faction_data'] = $formatted;
                $variables['members'] = $this->formatEntities($data['members'] ?? []);
                $variables['events'] = $this->formatEvents($data['activities'] ?? []);
                break;

            case SummaryType::LOCATION:
                $variables['location_data'] = $formatted;
                $variables['events'] = $this->formatEvents($data['events'] ?? []);
                $variables['entities'] = $this->formatEntities($data['residents'] ?? []);
                break;
        }

        return $variables;
    }

    /**
     * Call AI API to generate summary
     *
     * @param SummaryRequest $request Request
     * @param array $context AI context
     * @return array AI response data
     * @throws \Exception If AI call fails
     */
    private function callAI(SummaryRequest $request, array $context): array
    {
        $options = [
            'temperature' => $context['template']['temperature'] ?? 0.7,
            'max_tokens' => 2000,
        ];

        // Add response format for OpenAI
        if ($request->ai_provider === AIProvider::OPENAI) {
            $options['response_format'] = ['type' => 'json_object'];
        }

        try {
            if ($request->ai_provider === AIProvider::OPENAI) {
                return $this->callOpenAI($context['system_prompt'], $context['user_prompt'], $options);
            } else {
                return $this->callAnthropic($context['system_prompt'], $context['user_prompt'], $options);
            }
        } catch (\Exception $e) {
            error_log("[SAGA][SUMMARY][ERROR] AI API call failed: {$e->getMessage()}");
            throw new \Exception("AI generation failed: {$e->getMessage()}", 0, $e);
        }
    }

    /**
     * Call OpenAI GPT-4 API
     *
     * @param string $system_prompt System prompt
     * @param string $user_prompt User prompt
     * @param array $options API options
     * @return array Response data
     */
    private function callOpenAI(string $system_prompt, string $user_prompt, array $options): array
    {
        $api_key = get_option('saga_ai_openai_key', '');
        if (empty($api_key)) {
            throw new \Exception('OpenAI API key not configured');
        }

        $response = wp_remote_post('https://api.openai.com/v1/chat/completions', [
            'timeout' => 60,
            'headers' => [
                'Authorization' => 'Bearer ' . $api_key,
                'Content-Type' => 'application/json',
            ],
            'body' => wp_json_encode([
                'model' => 'gpt-4',
                'messages' => [
                    ['role' => 'system', 'content' => $system_prompt],
                    ['role' => 'user', 'content' => $user_prompt],
                ],
                'temperature' => $options['temperature'] ?? 0.7,
                'max_tokens' => $options['max_tokens'] ?? 2000,
            ]),
        ]);

        if (is_wp_error($response)) {
            throw new \Exception('OpenAI API error: ' . $response->get_error_message());
        }

        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        if (!isset($data['choices'][0]['message']['content'])) {
            throw new \Exception('Invalid OpenAI response format');
        }

        return [
            'content' => $data['choices'][0]['message']['content'],
            'token_count' => $data['usage']['total_tokens'] ?? 0,
            'input_tokens' => $data['usage']['prompt_tokens'] ?? 0,
            'output_tokens' => $data['usage']['completion_tokens'] ?? 0,
        ];
    }

    /**
     * Call Anthropic Claude API
     *
     * @param string $system_prompt System prompt
     * @param string $user_prompt User prompt
     * @param array $options API options
     * @return array Response data
     */
    private function callAnthropic(string $system_prompt, string $user_prompt, array $options): array
    {
        $api_key = get_option('saga_ai_anthropic_key', '');
        if (empty($api_key)) {
            throw new \Exception('Anthropic API key not configured');
        }

        $combined_prompt = $system_prompt . "\n\n" . $user_prompt;

        $response = wp_remote_post('https://api.anthropic.com/v1/messages', [
            'timeout' => 60,
            'headers' => [
                'x-api-key' => $api_key,
                'anthropic-version' => '2023-06-01',
                'Content-Type' => 'application/json',
            ],
            'body' => wp_json_encode([
                'model' => 'claude-3-opus-20240229',
                'max_tokens' => $options['max_tokens'] ?? 2000,
                'messages' => [
                    ['role' => 'user', 'content' => $combined_prompt],
                ],
            ]),
        ]);

        if (is_wp_error($response)) {
            throw new \Exception('Anthropic API error: ' . $response->get_error_message());
        }

        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        if (!isset($data['content'][0]['text'])) {
            throw new \Exception('Invalid Anthropic response format');
        }

        return [
            'content' => $data['content'][0]['text'],
            'token_count' => ($data['usage']['input_tokens'] ?? 0) + ($data['usage']['output_tokens'] ?? 0),
            'input_tokens' => $data['usage']['input_tokens'] ?? 0,
            'output_tokens' => $data['usage']['output_tokens'] ?? 0,
        ];
    }

    /**
     * Parse AI response
     *
     * @param array $ai_response AI response data
     * @param SummaryRequest $request Request
     * @return array Parsed data with title and text
     */
    private function parseAIResponse(array $ai_response, SummaryRequest $request): array
    {
        $content = $ai_response['content'];

        // Try to parse as JSON first (OpenAI format)
        $json = json_decode($content, true);
        if ($json && isset($json['summary'])) {
            return [
                'title' => $json['title'] ?? $this->generateDefaultTitle($request),
                'text' => $json['summary'],
                'token_count' => $ai_response['token_count'],
                'cost' => $this->calculateCost(
                    $ai_response['input_tokens'],
                    $ai_response['output_tokens'],
                    $request->ai_provider
                ),
            ];
        }

        // Otherwise treat as plain text
        // Extract title from first line or heading
        $lines = explode("\n", trim($content));
        $title = $this->extractTitle($lines[0]) ?? $this->generateDefaultTitle($request);

        return [
            'title' => $title,
            'text' => $content,
            'token_count' => $ai_response['token_count'],
            'cost' => $this->calculateCost(
                $ai_response['input_tokens'],
                $ai_response['output_tokens'],
                $request->ai_provider
            ),
        ];
    }

    /**
     * Calculate generation cost
     *
     * @param int $input_tokens Input tokens
     * @param int $output_tokens Output tokens
     * @param AIProvider $provider AI provider
     * @return float Cost in USD
     */
    private function calculateCost(int $input_tokens, int $output_tokens, AIProvider $provider): float
    {
        $input_cost = ($input_tokens / 1000) * $provider->getInputCostPer1K();
        $output_cost = ($output_tokens / 1000) * $provider->getOutputCostPer1K();

        return round($input_cost + $output_cost, 4);
    }

    /**
     * Calculate quality score based on data coverage
     *
     * @param string $text Generated text
     * @param array $data Source data
     * @return float Quality score (0-100)
     */
    private function calculateQualityScore(string $text, array $data): float
    {
        $score = 50.0; // Base score

        // Check data coverage
        $source_count = count($data['source_ids'] ?? []);
        if ($source_count > 0) {
            $score += min(20, $source_count * 2); // +2 per source, max +20
        }

        // Check text length (should be substantial)
        $word_count = str_word_count($text);
        if ($word_count >= 200) {
            $score += 15;
        } elseif ($word_count >= 100) {
            $score += 10;
        } elseif ($word_count >= 50) {
            $score += 5;
        }

        // Check structure (has headings/sections)
        if (preg_match('/^#{1,3}\s+/m', $text)) {
            $score += 10;
        }

        // Check for bullet points/lists
        if (preg_match('/^[\*\-\+]\s+/m', $text)) {
            $score += 5;
        }

        return min(100, $score);
    }

    /**
     * Build metadata with source references
     *
     * @param array $data Source data
     * @param array $parsed Parsed AI response
     * @return array Metadata
     */
    private function buildMetadata(array $data, array $parsed): array
    {
        return array_merge(
            $data['source_ids'] ?? [],
            [
                'generated_at' => current_time('mysql'),
                'data_sources' => count($data),
            ]
        );
    }

    /**
     * Generate default title from request
     *
     * @param SummaryRequest $request Request
     * @return string Title
     */
    private function generateDefaultTitle(SummaryRequest $request): string
    {
        return $request->summary_type->getLabel();
    }

    /**
     * Extract title from text line
     *
     * @param string $line Text line
     * @return string|null Title or null
     */
    private function extractTitle(string $line): ?string
    {
        // Remove markdown heading markers
        $title = preg_replace('/^#{1,6}\s*/', '', $line);
        return !empty(trim($title)) ? trim($title) : null;
    }

    /**
     * Format events for template
     *
     * @param array $events Events array
     * @return string Formatted text
     */
    private function formatEvents(array $events): string
    {
        $output = '';
        foreach ($events as $event) {
            $output .= "- {$event['canon_date']}: {$event['title']}\n";
        }
        return $output;
    }

    /**
     * Format entities for template
     *
     * @param array $entities Entities array
     * @return string Formatted text
     */
    private function formatEntities(array $entities): string
    {
        $output = '';
        foreach ($entities as $entity) {
            $output .= "- {$entity['canonical_name']} ({$entity['entity_type']})\n";
        }
        return $output;
    }

    /**
     * Format relationships for template
     *
     * @param array $relationships Relationships array
     * @return string Formatted text
     */
    private function formatRelationships(array $relationships): string
    {
        $output = '';
        foreach ($relationships as $rel) {
            $output .= "- {$rel['source_name']} → {$rel['relationship_type']} → {$rel['target_name']}\n";
        }
        return $output;
    }

    /**
     * Build scope description from request
     *
     * @param SummaryRequest $request Request
     * @return string Scope description
     */
    private function buildScopeDescription(SummaryRequest $request): string
    {
        if (!empty($request->scope_params['date_range'])) {
            return "from {$request->scope_params['date_range']['start']} to {$request->scope_params['date_range']['end']}";
        }

        if (!empty($request->scope_params['chapter_ids'])) {
            return "for chapters " . implode(', ', $request->scope_params['chapter_ids']);
        }

        return 'complete';
    }
}
