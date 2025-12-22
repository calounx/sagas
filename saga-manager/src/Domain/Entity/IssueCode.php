<?php
declare(strict_types=1);

namespace SagaManager\Domain\Entity;

/**
 * Issue Code Enum
 *
 * Represents different types of quality issues that can be detected.
 */
enum IssueCode: string
{
    // Completeness issues
    case MISSING_DESCRIPTION = 'missing_description';
    case MISSING_ATTRIBUTES = 'missing_attributes';
    case MISSING_RELATIONSHIPS = 'missing_relationships';
    case MISSING_TIMELINE = 'missing_timeline';
    case INCOMPLETE_METADATA = 'incomplete_metadata';

    // Consistency issues
    case ORPHAN_RELATIONSHIP = 'orphan_relationship';
    case CIRCULAR_RELATIONSHIP = 'circular_relationship';
    case DUPLICATE_RELATIONSHIP = 'duplicate_relationship';
    case TIMELINE_CONFLICT = 'timeline_conflict';
    case INVALID_REFERENCE = 'invalid_reference';

    // Content issues
    case SHORT_DESCRIPTION = 'short_description';
    case MISSING_FRAGMENTS = 'missing_fragments';
    case STALE_CONTENT = 'stale_content';
    case NO_EMBEDDING = 'no_embedding';

    // Data integrity issues
    case INVALID_DATE = 'invalid_date';
    case OUT_OF_RANGE = 'out_of_range';
    case TYPE_MISMATCH = 'type_mismatch';

    /**
     * Get human-readable label for the issue
     */
    public function label(): string
    {
        return match ($this) {
            self::MISSING_DESCRIPTION => 'Missing description',
            self::MISSING_ATTRIBUTES => 'Missing required attributes',
            self::MISSING_RELATIONSHIPS => 'No relationships defined',
            self::MISSING_TIMELINE => 'Not linked to timeline',
            self::INCOMPLETE_METADATA => 'Incomplete metadata',
            self::ORPHAN_RELATIONSHIP => 'Orphan relationship reference',
            self::CIRCULAR_RELATIONSHIP => 'Circular relationship detected',
            self::DUPLICATE_RELATIONSHIP => 'Duplicate relationship',
            self::TIMELINE_CONFLICT => 'Timeline date conflict',
            self::INVALID_REFERENCE => 'Invalid entity reference',
            self::SHORT_DESCRIPTION => 'Description too short',
            self::MISSING_FRAGMENTS => 'No content fragments',
            self::STALE_CONTENT => 'Content not updated recently',
            self::NO_EMBEDDING => 'Missing vector embedding',
            self::INVALID_DATE => 'Invalid date format',
            self::OUT_OF_RANGE => 'Value out of valid range',
            self::TYPE_MISMATCH => 'Data type mismatch',
        };
    }

    /**
     * Get severity level (1-5, where 5 is most severe)
     */
    public function severity(): int
    {
        return match ($this) {
            self::MISSING_DESCRIPTION,
            self::SHORT_DESCRIPTION,
            self::STALE_CONTENT => 1,

            self::MISSING_ATTRIBUTES,
            self::MISSING_FRAGMENTS,
            self::NO_EMBEDDING,
            self::INCOMPLETE_METADATA => 2,

            self::MISSING_RELATIONSHIPS,
            self::MISSING_TIMELINE,
            self::DUPLICATE_RELATIONSHIP => 3,

            self::ORPHAN_RELATIONSHIP,
            self::INVALID_REFERENCE,
            self::TIMELINE_CONFLICT,
            self::OUT_OF_RANGE,
            self::TYPE_MISMATCH => 4,

            self::CIRCULAR_RELATIONSHIP,
            self::INVALID_DATE => 5,
        };
    }

    /**
     * Get category of the issue
     */
    public function category(): string
    {
        return match ($this) {
            self::MISSING_DESCRIPTION,
            self::MISSING_ATTRIBUTES,
            self::MISSING_RELATIONSHIPS,
            self::MISSING_TIMELINE,
            self::INCOMPLETE_METADATA => 'completeness',

            self::ORPHAN_RELATIONSHIP,
            self::CIRCULAR_RELATIONSHIP,
            self::DUPLICATE_RELATIONSHIP,
            self::TIMELINE_CONFLICT,
            self::INVALID_REFERENCE => 'consistency',

            self::SHORT_DESCRIPTION,
            self::MISSING_FRAGMENTS,
            self::STALE_CONTENT,
            self::NO_EMBEDDING => 'content',

            self::INVALID_DATE,
            self::OUT_OF_RANGE,
            self::TYPE_MISMATCH => 'integrity',
        };
    }

    /**
     * Check if this is a critical issue that blocks publishing
     */
    public function isCritical(): bool
    {
        return $this->severity() >= 4;
    }

    /**
     * Get all issue codes
     *
     * @return IssueCode[]
     */
    public static function all(): array
    {
        return self::cases();
    }

    /**
     * Get issue codes by category
     *
     * @return IssueCode[]
     */
    public static function byCategory(string $category): array
    {
        return array_filter(self::cases(), fn(self $code) => $code->category() === $category);
    }
}
