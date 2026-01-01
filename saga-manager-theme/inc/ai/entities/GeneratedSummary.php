<?php
/**
 * Generated Summary Value Object
 *
 * Immutable value object representing an AI-generated summary based on verified saga data.
 * IMPORTANT: Summaries synthesize only verified data from the database - no fictional content.
 *
 * @package SagaManager
 * @subpackage AI\SummaryGenerator
 */

declare(strict_types=1);

namespace SagaManager\AI\Entities;

use SagaManager\AI\Entities\SummaryType;

/**
 * Summary output format enum
 */
enum OutputFormat: string
{
    case MARKDOWN = 'markdown';
    case HTML = 'html';
    case PLAIN = 'plain';

    /**
     * Get file extension
     */
    public function getExtension(): string
    {
        return match($this) {
            self::MARKDOWN => 'md',
            self::HTML => 'html',
            self::PLAIN => 'txt',
        };
    }
}

/**
 * Generated Summary value object
 */
final readonly class GeneratedSummary
{
    /**
     * Constructor
     *
     * @param int|null $id Summary ID
     * @param int $request_id Parent request ID
     * @param int $saga_id Saga ID
     * @param int|null $entity_id Target entity ID
     * @param SummaryType $summary_type Type of summary
     * @param int $version Version number
     * @param string $title Summary title
     * @param string $summary_text Generated summary content (verified data only)
     * @param int $word_count Word count
     * @param array $key_points Extracted key points
     * @param array $metadata Additional metadata (themes, tags, source references)
     * @param float|null $quality_score AI-assessed quality (0-100)
     * @param float|null $readability_score Readability score
     * @param bool $is_current Is this the current version?
     * @param string|null $regeneration_reason Reason for regeneration
     * @param string $cache_key Cache key for lookup
     * @param int|null $cache_expires_at Cache expiration timestamp
     * @param string $ai_model AI model used
     * @param int $token_count Token count
     * @param float $generation_cost Generation cost in USD
     * @param int $created_at Creation timestamp
     * @param int $updated_at Update timestamp
     */
    public function __construct(
        public ?int $id,
        public int $request_id,
        public int $saga_id,
        public ?int $entity_id,
        public SummaryType $summary_type,
        public int $version,
        public string $title,
        public string $summary_text,
        public int $word_count,
        public array $key_points,
        public array $metadata,
        public ?float $quality_score,
        public ?float $readability_score,
        public bool $is_current,
        public ?string $regeneration_reason,
        public string $cache_key,
        public ?int $cache_expires_at,
        public string $ai_model,
        public int $token_count,
        public float $generation_cost,
        public int $created_at = 0,
        public int $updated_at = 0
    ) {
        if (empty($this->title)) {
            throw new \InvalidArgumentException('Summary title cannot be empty');
        }

        if (empty($this->summary_text)) {
            throw new \InvalidArgumentException('Summary text cannot be empty');
        }

        if ($this->word_count < 0) {
            throw new \InvalidArgumentException('Word count cannot be negative');
        }

        if ($this->quality_score !== null && ($this->quality_score < 0 || $this->quality_score > 100)) {
            throw new \InvalidArgumentException('Quality score must be between 0 and 100');
        }

        if ($this->version < 1) {
            throw new \InvalidArgumentException('Version must be >= 1');
        }

        if ($this->created_at === 0) {
            $this->created_at = time();
        }

        if ($this->updated_at === 0) {
            $this->updated_at = time();
        }
    }

    /**
     * Calculate word count from text
     *
     * @param string $text Text content
     * @return int Word count
     */
    public static function calculateWordCount(string $text): int
    {
        return str_word_count(strip_tags($text));
    }

    /**
     * Calculate readability score (Flesch Reading Ease)
     *
     * @param string $text Text content
     * @return float Score (0-100, higher = easier to read)
     */
    public static function calculateReadabilityScore(string $text): float
    {
        $text = strip_tags($text);

        // Count sentences
        $sentence_count = preg_match_all('/[.!?]+/', $text);
        if ($sentence_count === 0) {
            $sentence_count = 1;
        }

        // Count words
        $word_count = str_word_count($text);
        if ($word_count === 0) {
            return 0.0;
        }

        // Count syllables (approximate)
        $syllable_count = self::countSyllables($text);

        // Flesch Reading Ease formula
        $score = 206.835
                - (1.015 * ($word_count / $sentence_count))
                - (84.6 * ($syllable_count / $word_count));

        return round(max(0, min(100, $score)), 2);
    }

    /**
     * Approximate syllable count
     *
     * @param string $text Text content
     * @return int Syllable count
     */
    private static function countSyllables(string $text): int
    {
        $words = str_word_count(strtolower($text), 1);
        $syllables = 0;

        foreach ($words as $word) {
            $syllables += max(1, preg_match_all('/[aeiouy]+/', $word));
        }

        return $syllables;
    }

    /**
     * Generate cache key
     *
     * @param int $saga_id Saga ID
     * @param SummaryType $type Summary type
     * @param int|null $entity_id Entity ID
     * @param array $scope_params Scope parameters
     * @return string MD5 hash
     */
    public static function generateCacheKey(
        int $saga_id,
        SummaryType $type,
        ?int $entity_id,
        array $scope_params
    ): string {
        $key_data = [
            'saga_id' => $saga_id,
            'type' => $type->value,
            'entity_id' => $entity_id,
            'scope' => $scope_params,
        ];

        return md5(json_encode($key_data));
    }

    /**
     * Create new summary with incremented version
     *
     * @param string $reason Regeneration reason
     * @return self
     */
    public function withNewVersion(string $reason): self
    {
        $data = get_object_vars($this);
        $data['id'] = null; // New record
        $data['version'] = $this->version + 1;
        $data['is_current'] = true;
        $data['regeneration_reason'] = $reason;
        $data['created_at'] = time();
        $data['updated_at'] = time();

        return new self(...$data);
    }

    /**
     * Mark as not current (when new version is created)
     *
     * @return self
     */
    public function markAsOldVersion(): self
    {
        $data = get_object_vars($this);
        $data['is_current'] = false;
        $data['updated_at'] = time();

        return new self(...$data);
    }

    /**
     * Update cache expiration
     *
     * @param int $ttl_seconds Time to live in seconds
     * @return self
     */
    public function withCacheExpiration(int $ttl_seconds): self
    {
        $data = get_object_vars($this);
        $data['cache_expires_at'] = time() + $ttl_seconds;

        return new self(...$data);
    }

    /**
     * Check if cache is expired
     *
     * @return bool
     */
    public function isCacheExpired(): bool
    {
        if ($this->cache_expires_at === null) {
            return false; // No expiration set
        }

        return time() > $this->cache_expires_at;
    }

    /**
     * Get quality level label
     *
     * @return string
     */
    public function getQualityLabel(): string
    {
        if ($this->quality_score === null) {
            return 'Unknown';
        }

        return match(true) {
            $this->quality_score >= 90 => 'Excellent',
            $this->quality_score >= 75 => 'Good',
            $this->quality_score >= 60 => 'Fair',
            $this->quality_score >= 40 => 'Poor',
            default => 'Very Poor',
        };
    }

    /**
     * Get readability level label
     *
     * @return string
     */
    public function getReadabilityLabel(): string
    {
        if ($this->readability_score === null) {
            return 'Unknown';
        }

        return match(true) {
            $this->readability_score >= 90 => 'Very Easy',
            $this->readability_score >= 80 => 'Easy',
            $this->readability_score >= 70 => 'Fairly Easy',
            $this->readability_score >= 60 => 'Standard',
            $this->readability_score >= 50 => 'Fairly Difficult',
            $this->readability_score >= 30 => 'Difficult',
            default => 'Very Difficult',
        };
    }

    /**
     * Check if summary has verified source references
     *
     * @return bool
     */
    public function hasSourceReferences(): bool
    {
        return isset($this->metadata['source_entities'])
            || isset($this->metadata['source_events'])
            || isset($this->metadata['source_relationships']);
    }

    /**
     * Get source reference count
     *
     * @return int
     */
    public function getSourceReferenceCount(): int
    {
        $count = 0;

        if (isset($this->metadata['source_entities'])) {
            $count += count($this->metadata['source_entities']);
        }

        if (isset($this->metadata['source_events'])) {
            $count += count($this->metadata['source_events']);
        }

        if (isset($this->metadata['source_relationships'])) {
            $count += count($this->metadata['source_relationships']);
        }

        return $count;
    }

    /**
     * Extract key points from summary text
     *
     * @param string $text Summary text
     * @param int $max_points Maximum number of points
     * @return array Key points
     */
    public static function extractKeyPoints(string $text, int $max_points = 5): array
    {
        // Extract bullet points or numbered lists
        $points = [];

        // Match markdown/HTML lists
        if (preg_match_all('/^[\*\-\+]\s+(.+)$/m', $text, $matches)) {
            $points = array_merge($points, $matches[1]);
        }

        if (preg_match_all('/^\d+\.\s+(.+)$/m', $text, $matches)) {
            $points = array_merge($points, $matches[1]);
        }

        // If no lists found, extract first sentences
        if (empty($points)) {
            preg_match_all('/[^.!?]+[.!?]/', $text, $matches);
            $points = array_slice($matches[0] ?? [], 0, $max_points);
        }

        // Clean and limit
        $points = array_map('trim', $points);
        $points = array_slice($points, 0, $max_points);

        return array_values($points);
    }

    /**
     * Convert to array
     *
     * @return array
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'request_id' => $this->request_id,
            'saga_id' => $this->saga_id,
            'entity_id' => $this->entity_id,
            'summary_type' => $this->summary_type->value,
            'summary_type_label' => $this->summary_type->getLabel(),
            'version' => $this->version,
            'title' => $this->title,
            'summary_text' => $this->summary_text,
            'word_count' => $this->word_count,
            'key_points' => $this->key_points,
            'metadata' => $this->metadata,
            'quality_score' => $this->quality_score,
            'quality_label' => $this->getQualityLabel(),
            'readability_score' => $this->readability_score,
            'readability_label' => $this->getReadabilityLabel(),
            'is_current' => $this->is_current,
            'regeneration_reason' => $this->regeneration_reason,
            'cache_key' => $this->cache_key,
            'cache_expires_at' => $this->cache_expires_at,
            'is_cache_expired' => $this->isCacheExpired(),
            'ai_model' => $this->ai_model,
            'token_count' => $this->token_count,
            'generation_cost' => $this->generation_cost,
            'has_source_references' => $this->hasSourceReferences(),
            'source_reference_count' => $this->getSourceReferenceCount(),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }

    /**
     * Create from database row
     *
     * @param object $row Database row
     * @return self
     */
    public static function fromDatabase(object $row): self
    {
        return new self(
            id: (int)$row->id,
            request_id: (int)$row->request_id,
            saga_id: (int)$row->saga_id,
            entity_id: $row->entity_id ? (int)$row->entity_id : null,
            summary_type: SummaryType::from($row->summary_type),
            version: (int)$row->version,
            title: $row->title,
            summary_text: $row->summary_text,
            word_count: (int)$row->word_count,
            key_points: json_decode($row->key_points ?? '[]', true),
            metadata: json_decode($row->metadata ?? '{}', true),
            quality_score: $row->quality_score ? (float)$row->quality_score : null,
            readability_score: $row->readability_score ? (float)$row->readability_score : null,
            is_current: (bool)$row->is_current,
            regeneration_reason: $row->regeneration_reason,
            cache_key: $row->cache_key,
            cache_expires_at: $row->cache_expires_at ? strtotime($row->cache_expires_at) : null,
            ai_model: $row->ai_model,
            token_count: (int)$row->token_count,
            generation_cost: (float)$row->generation_cost,
            created_at: strtotime($row->created_at),
            updated_at: strtotime($row->updated_at)
        );
    }
}
