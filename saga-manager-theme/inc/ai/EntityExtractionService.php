<?php
/**
 * Entity Extraction Service
 *
 * AI-powered service for extracting structured entities from unstructured text.
 * Uses OpenAI GPT-4 or Anthropic Claude to identify characters, locations,
 * events, factions, artifacts, and concepts with high accuracy (85%+ target).
 *
 * @package SagaManager
 * @subpackage AI\EntityExtractor
 * @since 1.4.0
 */

declare(strict_types=1);

namespace SagaManager\AI\EntityExtractor;

use SagaManager\AI\EntityExtractor\Entities\ExtractedEntity;
use SagaManager\AI\EntityExtractor\Entities\EntityType;
use SagaManager\AI\EntityExtractor\Entities\ExtractedEntityStatus;
use SagaManager\AI\AIClient;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Entity Extraction Service
 *
 * Orchestrates AI-powered entity extraction from text.
 */
class EntityExtractionService
{
    private AIClient $ai_client;
    private int $chunk_size;
    private int $max_entities_per_chunk;

    /**
     * Constructor
     *
     * @param AIClient|null $ai_client Optional AI client (auto-created if null)
     * @param int $chunk_size Text chunk size (default 5000 chars)
     * @param int $max_entities_per_chunk Max entities per chunk (default 50)
     */
    public function __construct(
        ?AIClient $ai_client = null,
        int $chunk_size = 5000,
        int $max_entities_per_chunk = 50
    ) {
        $this->ai_client = $ai_client ?? new AIClient();
        $this->chunk_size = $chunk_size;
        $this->max_entities_per_chunk = $max_entities_per_chunk;
    }

    /**
     * Extract entities from text
     *
     * @param string $text Text to analyze
     * @param int $job_id Parent extraction job ID
     * @param array $options Extraction options
     * @return array Array of ExtractedEntity objects
     * @throws \Exception If extraction fails
     */
    public function extractEntities(string $text, int $job_id, array $options = []): array
    {
        $chunks = $this->chunkText($text);
        $all_entities = [];

        foreach ($chunks as $index => $chunk) {
            try {
                $entities = $this->extractFromChunk($chunk, $job_id, $index, $options);
                $all_entities = array_merge($all_entities, $entities);

                // Allow hook for progress tracking
                do_action('saga_extraction_chunk_complete', $job_id, $index + 1, count($chunks));

            } catch (\Exception $e) {
                error_log(sprintf(
                    '[SAGA][EXTRACTOR][ERROR] Chunk %d extraction failed: %s',
                    $index,
                    $e->getMessage()
                ));

                // Continue with other chunks instead of failing completely
                do_action('saga_extraction_chunk_failed', $job_id, $index, $e->getMessage());
            }
        }

        return $all_entities;
    }

    /**
     * Extract entities from a single chunk
     *
     * @param string $chunk Text chunk
     * @param int $job_id Job ID
     * @param int $chunk_index Chunk index
     * @param array $options Extraction options
     * @return array Array of ExtractedEntity objects
     */
    private function extractFromChunk(
        string $chunk,
        int $job_id,
        int $chunk_index,
        array $options
    ): array {
        $prompt = $this->buildExtractionPrompt($chunk, $options);

        // Call AI API
        $response = $this->ai_client->complete($prompt, [
            'temperature' => 0.1, // Low temperature for consistent extraction
            'max_tokens' => 4000,
            'response_format' => ['type' => 'json_object']
        ]);

        // Parse response
        $data = json_decode($response, true);

        if (!$data || !isset($data['entities'])) {
            error_log('[SAGA][EXTRACTOR][ERROR] Invalid AI response format');
            return [];
        }

        // Convert to ExtractedEntity objects
        return $this->parseExtractedEntities(
            $data['entities'],
            $job_id,
            $chunk_index,
            $chunk
        );
    }

    /**
     * Build AI prompt for entity extraction
     *
     * @param string $text Text to analyze
     * @param array $options Extraction options
     * @return string Formatted prompt
     */
    private function buildExtractionPrompt(string $text, array $options): string
    {
        $entity_types = implode(', ', ['character', 'location', 'event', 'faction', 'artifact', 'concept']);

        $prompt = <<<PROMPT
You are an expert entity extraction system for fictional universe management.

Analyze the following text and extract ALL entities of these types: {$entity_types}.

For each entity, provide:
1. **type**: One of: character, location, event, faction, artifact, concept
2. **canonical_name**: Primary name (most common/formal)
3. **alternative_names**: Array of aliases, nicknames, or alternative names
4. **description**: Brief description (1-2 sentences)
5. **attributes**: Key-value object of entity-specific attributes
6. **confidence**: Your confidence in this extraction (0-100)
7. **context**: Brief quote showing where entity appears

Guidelines:
- Be thorough but precise - extract every meaningful entity
- Characters: Include age, role, affiliations if mentioned
- Locations: Include type (city, planet, etc), region if mentioned
- Events: Include date/timeframe if mentioned
- Factions: Include type (organization, empire, etc)
- Artifacts: Include type (weapon, relic, etc)
- Concepts: Include category (magic system, technology, etc)
- Confidence should reflect certainty - use 90+ for clear entities, 60-80 for ambiguous
- Context should be exact quote (max 150 chars)

Return JSON with this structure:
{
  "entities": [
    {
      "type": "character",
      "canonical_name": "Paul Atreides",
      "alternative_names": ["Muad'Dib", "Usul"],
      "description": "Son of Duke Leto, destined to become the Kwisatz Haderach",
      "attributes": {
        "age": "15",
        "role": "protagonist",
        "affiliation": "House Atreides"
      },
      "confidence": 95,
      "context": "Paul Atreides was fifteen years old"
    }
  ]
}

Text to analyze:
{$text}

Extract entities now:
PROMPT;

        return $prompt;
    }

    /**
     * Parse AI response into ExtractedEntity objects
     *
     * @param array $entities_data Raw entity data from AI
     * @param int $job_id Job ID
     * @param int $chunk_index Chunk index
     * @param string $source_text Source text for context
     * @return array Array of ExtractedEntity objects
     */
    private function parseExtractedEntities(
        array $entities_data,
        int $job_id,
        int $chunk_index,
        string $source_text
    ): array {
        $entities = [];

        foreach ($entities_data as $data) {
            try {
                // Validate required fields
                if (empty($data['type']) || empty($data['canonical_name'])) {
                    continue;
                }

                // Map AI type to EntityType enum
                $entity_type = $this->mapEntityType($data['type']);
                if ($entity_type === null) {
                    continue;
                }

                // Find position in text
                $position = $this->findEntityPosition(
                    $data['canonical_name'],
                    $source_text
                );

                $entity = new ExtractedEntity(
                    id: null,
                    job_id: $job_id,
                    entity_type: $entity_type,
                    canonical_name: trim($data['canonical_name']),
                    alternative_names: $data['alternative_names'] ?? [],
                    description: $data['description'] ?? null,
                    attributes: $data['attributes'] ?? [],
                    context_snippet: $data['context'] ?? null,
                    confidence_score: (float)($data['confidence'] ?? 70),
                    chunk_index: $chunk_index,
                    position_in_text: $position,
                    status: ExtractedEntityStatus::PENDING,
                    duplicate_of: null,
                    duplicate_similarity: null,
                    created_entity_id: null,
                    reviewed_by: null,
                    reviewed_at: null,
                    created_at: time()
                );

                $entities[] = $entity;

            } catch (\Exception $e) {
                error_log(sprintf(
                    '[SAGA][EXTRACTOR][ERROR] Failed to parse entity "%s": %s',
                    $data['canonical_name'] ?? 'unknown',
                    $e->getMessage()
                ));
            }
        }

        return $entities;
    }

    /**
     * Map AI entity type string to EntityType enum
     *
     * @param string $type AI type string
     * @return EntityType|null
     */
    private function mapEntityType(string $type): ?EntityType
    {
        $type_lower = strtolower(trim($type));

        return match($type_lower) {
            'character', 'person', 'individual' => EntityType::CHARACTER,
            'location', 'place', 'region' => EntityType::LOCATION,
            'event', 'incident', 'occurrence' => EntityType::EVENT,
            'faction', 'organization', 'group' => EntityType::FACTION,
            'artifact', 'item', 'object' => EntityType::ARTIFACT,
            'concept', 'idea', 'system' => EntityType::CONCEPT,
            default => null
        };
    }

    /**
     * Find entity position in text
     *
     * @param string $name Entity name
     * @param string $text Source text
     * @return int|null Character position or null
     */
    private function findEntityPosition(string $name, string $text): ?int
    {
        $position = mb_stripos($text, $name);
        return $position !== false ? $position : null;
    }

    /**
     * Chunk text into manageable pieces
     *
     * Splits on sentence boundaries to avoid breaking entities
     *
     * @param string $text Text to chunk
     * @return array Array of text chunks
     */
    private function chunkText(string $text): array
    {
        // If text is small enough, return as single chunk
        if (mb_strlen($text) <= $this->chunk_size) {
            return [$text];
        }

        $chunks = [];
        $sentences = preg_split('/(?<=[.!?])\s+/', $text, -1, PREG_SPLIT_NO_EMPTY);

        $current_chunk = '';
        foreach ($sentences as $sentence) {
            // If adding this sentence exceeds chunk size, save current chunk
            if (mb_strlen($current_chunk . ' ' . $sentence) > $this->chunk_size && !empty($current_chunk)) {
                $chunks[] = trim($current_chunk);
                $current_chunk = $sentence;
            } else {
                $current_chunk .= ($current_chunk ? ' ' : '') . $sentence;
            }
        }

        // Add final chunk
        if (!empty($current_chunk)) {
            $chunks[] = trim($current_chunk);
        }

        return $chunks;
    }

    /**
     * Estimate extraction cost
     *
     * @param string $text Text to analyze
     * @return array Cost estimates
     */
    public function estimateCost(string $text): array
    {
        $chunks = $this->chunkText($text);
        $total_tokens = 0;

        foreach ($chunks as $chunk) {
            // Rough token estimation (1 token ≈ 4 characters)
            $chunk_tokens = (int)(mb_strlen($chunk) / 4);
            $prompt_tokens = 500; // Prompt overhead
            $response_tokens = 2000; // Estimated response size
            $total_tokens += $chunk_tokens + $prompt_tokens + $response_tokens;
        }

        // OpenAI GPT-4 pricing (as of 2024)
        $cost_per_1k_input = 0.03;  // $0.03 per 1K input tokens
        $cost_per_1k_output = 0.06; // $0.06 per 1K output tokens

        $estimated_cost = (($total_tokens * 0.7) / 1000 * $cost_per_1k_input) +
                         (($total_tokens * 0.3) / 1000 * $cost_per_1k_output);

        return [
            'chunks' => count($chunks),
            'estimated_tokens' => $total_tokens,
            'estimated_cost_usd' => round($estimated_cost, 4),
            'estimated_entities' => count($chunks) * 15, // Rough estimate
            'processing_time_seconds' => count($chunks) * 5 // 5 seconds per chunk
        ];
    }

    /**
     * Validate extracted entities quality
     *
     * @param array $entities ExtractedEntity array
     * @return array Validation results
     */
    public function validateExtractionQuality(array $entities): array
    {
        $total = count($entities);
        $high_confidence = 0;
        $medium_confidence = 0;
        $low_confidence = 0;
        $with_description = 0;
        $with_context = 0;

        foreach ($entities as $entity) {
            if (!($entity instanceof ExtractedEntity)) {
                continue;
            }

            if ($entity->confidence_score >= 80) {
                $high_confidence++;
            } elseif ($entity->confidence_score >= 60) {
                $medium_confidence++;
            } else {
                $low_confidence++;
            }

            if ($entity->description !== null) {
                $with_description++;
            }

            if ($entity->context_snippet !== null) {
                $with_context++;
            }
        }

        $avg_confidence = $total > 0
            ? array_sum(array_map(fn($e) => $e->confidence_score, $entities)) / $total
            : 0;

        return [
            'total_entities' => $total,
            'high_confidence' => $high_confidence,
            'medium_confidence' => $medium_confidence,
            'low_confidence' => $low_confidence,
            'avg_confidence' => round($avg_confidence, 2),
            'with_description_percent' => $total > 0 ? round(($with_description / $total) * 100, 2) : 0,
            'with_context_percent' => $total > 0 ? round(($with_context / $total) * 100, 2) : 0,
            'quality_score' => $this->calculateQualityScore([
                'avg_confidence' => $avg_confidence,
                'high_confidence_percent' => $total > 0 ? ($high_confidence / $total) * 100 : 0,
                'completeness' => $total > 0 ? (($with_description + $with_context) / ($total * 2)) * 100 : 0
            ])
        ];
    }

    /**
     * Calculate overall quality score
     *
     * @param array $metrics Quality metrics
     * @return float 0-100 quality score
     */
    private function calculateQualityScore(array $metrics): float
    {
        $confidence_weight = 0.5;
        $high_confidence_weight = 0.3;
        $completeness_weight = 0.2;

        $score = ($metrics['avg_confidence'] * $confidence_weight) +
                 ($metrics['high_confidence_percent'] * $high_confidence_weight) +
                 ($metrics['completeness'] * $completeness_weight);

        return round($score, 2);
    }
}
