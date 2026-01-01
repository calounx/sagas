<?php
/**
 * Duplicate Match Value Object
 *
 * Immutable value object representing a potential duplicate entity
 * match found during extraction. Tracks similarity and user decisions.
 *
 * @package SagaManager
 * @subpackage AI\EntityExtractor\Entities
 * @since 1.4.0
 */

declare(strict_types=1);

namespace SagaManager\AI\EntityExtractor\Entities;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Match Type Enum
 */
enum MatchType: string
{
    case EXACT = 'exact';           // Exact name match
    case FUZZY = 'fuzzy';           // Fuzzy string match
    case SEMANTIC = 'semantic';     // Semantic similarity (embeddings)
    case ALIAS = 'alias';           // Matches an alternative name

    /**
     * Get match type priority (lower = higher priority)
     *
     * @return int
     */
    public function getPriority(): int
    {
        return match($this) {
            self::EXACT => 1,
            self::ALIAS => 2,
            self::FUZZY => 3,
            self::SEMANTIC => 4
        };
    }
}

/**
 * User Action Enum
 */
enum DuplicateAction: string
{
    case PENDING = 'pending';
    case CONFIRMED_DUPLICATE = 'confirmed_duplicate';
    case CONFIRMED_UNIQUE = 'confirmed_unique';
    case MERGED = 'merged';

    /**
     * Check if action is final
     *
     * @return bool
     */
    public function isFinal(): bool
    {
        return $this !== self::PENDING;
    }
}

/**
 * Duplicate Match Value Object
 *
 * Represents a potential duplicate between an extracted entity and existing entity.
 */
final readonly class DuplicateMatch
{
    /**
     * Constructor
     *
     * @param int|null $id Match ID (null for new)
     * @param int $extracted_entity_id Extracted entity ID
     * @param int $existing_entity_id Existing entity ID
     * @param float $similarity_score Similarity 0-100
     * @param MatchType $match_type Type of match
     * @param string|null $matching_field Which field matched
     * @param float $confidence AI confidence in duplicate
     * @param DuplicateAction $user_action User's decision
     * @param array|null $merged_attributes Merged attributes if confirmed duplicate
     * @param int $created_at Unix timestamp
     * @param int|null $reviewed_at Unix timestamp
     */
    public function __construct(
        public ?int $id,
        public int $extracted_entity_id,
        public int $existing_entity_id,
        public float $similarity_score,
        public MatchType $match_type,
        public ?string $matching_field,
        public float $confidence,
        public DuplicateAction $user_action,
        public ?array $merged_attributes,
        public int $created_at,
        public ?int $reviewed_at
    ) {
        // Validation
        if ($this->similarity_score < 0 || $this->similarity_score > 100) {
            throw new \InvalidArgumentException('Similarity score must be between 0 and 100');
        }

        if ($this->confidence < 0 || $this->confidence > 100) {
            throw new \InvalidArgumentException('Confidence must be between 0 and 100');
        }

        if ($this->extracted_entity_id === $this->existing_entity_id) {
            throw new \InvalidArgumentException('Cannot match entity to itself');
        }
    }

    /**
     * Create from database row
     *
     * @param array $row Database row
     * @return self
     */
    public static function fromArray(array $row): self
    {
        return new self(
            id: isset($row['id']) ? (int)$row['id'] : null,
            extracted_entity_id: (int)$row['extracted_entity_id'],
            existing_entity_id: (int)$row['existing_entity_id'],
            similarity_score: (float)$row['similarity_score'],
            match_type: MatchType::from($row['match_type']),
            matching_field: $row['matching_field'] ?? null,
            confidence: (float)$row['confidence'],
            user_action: DuplicateAction::from($row['user_action'] ?? 'pending'),
            merged_attributes: isset($row['merged_attributes'])
                ? json_decode($row['merged_attributes'], true)
                : null,
            created_at: strtotime($row['created_at']),
            reviewed_at: isset($row['reviewed_at']) ? strtotime($row['reviewed_at']) : null
        );
    }

    /**
     * Convert to database array
     *
     * @return array
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'extracted_entity_id' => $this->extracted_entity_id,
            'existing_entity_id' => $this->existing_entity_id,
            'similarity_score' => $this->similarity_score,
            'match_type' => $this->match_type->value,
            'matching_field' => $this->matching_field,
            'confidence' => $this->confidence,
            'user_action' => $this->user_action->value,
            'merged_attributes' => $this->merged_attributes ? json_encode($this->merged_attributes) : null,
            'created_at' => date('Y-m-d H:i:s', $this->created_at),
            'reviewed_at' => $this->reviewed_at ? date('Y-m-d H:i:s', $this->reviewed_at) : null
        ];
    }

    /**
     * Get similarity level label
     *
     * @return string very_high, high, medium, low
     */
    public function getSimilarityLevel(): string
    {
        if ($this->similarity_score >= 95) {
            return 'very_high';
        } elseif ($this->similarity_score >= 80) {
            return 'high';
        } elseif ($this->similarity_score >= 60) {
            return 'medium';
        } else {
            return 'low';
        }
    }

    /**
     * Check if match is high confidence
     *
     * @return bool
     */
    public function isHighConfidence(): bool
    {
        return $this->similarity_score >= 90 && $this->confidence >= 80;
    }

    /**
     * Check if match needs user review
     *
     * @return bool
     */
    public function needsReview(): bool
    {
        return $this->user_action === DuplicateAction::PENDING;
    }

    /**
     * Get recommendation (auto-merge, review, ignore)
     *
     * @return string
     */
    public function getRecommendation(): string
    {
        // Exact matches with high confidence should auto-merge
        if ($this->match_type === MatchType::EXACT && $this->confidence >= 95) {
            return 'auto_merge';
        }

        // Very high similarity needs review
        if ($this->similarity_score >= 90) {
            return 'review_merge';
        }

        // Medium similarity needs review
        if ($this->similarity_score >= 70) {
            return 'review_keep_separate';
        }

        // Low similarity can be ignored
        return 'ignore';
    }

    /**
     * Create match with confirmed duplicate action
     *
     * @param array|null $merged_attributes Optional merged attributes
     * @return self
     */
    public function confirmDuplicate(?array $merged_attributes = null): self
    {
        $data = $this->toArray();
        $data['user_action'] = DuplicateAction::CONFIRMED_DUPLICATE->value;
        $data['merged_attributes'] = $merged_attributes ? json_encode($merged_attributes) : null;
        $data['reviewed_at'] = date('Y-m-d H:i:s');
        return self::fromArray($data);
    }

    /**
     * Create match with confirmed unique action
     *
     * @return self
     */
    public function confirmUnique(): self
    {
        $data = $this->toArray();
        $data['user_action'] = DuplicateAction::CONFIRMED_UNIQUE->value;
        $data['reviewed_at'] = date('Y-m-d H:i:s');
        return self::fromArray($data);
    }

    /**
     * Create match with merged action
     *
     * @param array $merged_attributes Merged attributes
     * @return self
     */
    public function markMerged(array $merged_attributes): self
    {
        $data = $this->toArray();
        $data['user_action'] = DuplicateAction::MERGED->value;
        $data['merged_attributes'] = json_encode($merged_attributes);
        $data['reviewed_at'] = date('Y-m-d H:i:s');
        return self::fromArray($data);
    }

    /**
     * Calculate overall match quality
     *
     * Combines similarity and confidence scores
     *
     * @return float 0-100
     */
    public function getMatchQuality(): float
    {
        $similarity_weight = 0.6;
        $confidence_weight = 0.4;

        $quality = ($this->similarity_score * $similarity_weight) +
                   ($this->confidence * $confidence_weight);

        return round($quality, 2);
    }

    /**
     * Get match priority score (for sorting)
     *
     * Higher priority = more important to review
     *
     * @return float
     */
    public function getPriorityScore(): float
    {
        $base_score = $this->getMatchQuality();

        // Boost exact matches
        if ($this->match_type === MatchType::EXACT) {
            $base_score += 20;
        }

        // Boost alias matches
        if ($this->match_type === MatchType::ALIAS) {
            $base_score += 10;
        }

        return min($base_score, 100);
    }

    /**
     * Get human-readable match explanation
     *
     * @return string
     */
    public function getExplanation(): string
    {
        $field = $this->matching_field ?? 'entity';

        return match($this->match_type) {
            MatchType::EXACT => "Exact match on {$field}",
            MatchType::FUZZY => "Similar {$field} (" . round($this->similarity_score) . "% match)",
            MatchType::SEMANTIC => "Semantically similar (" . round($this->similarity_score) . "% match)",
            MatchType::ALIAS => "Matches alternative name in {$field}"
        };
    }
}
