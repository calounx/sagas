<?php
/**
 * Extracted Entity Value Object
 *
 * Immutable value object representing an entity extracted from text by AI.
 * Awaits user approval before batch creation as a permanent saga entity.
 *
 * @package SagaManager
 * @subpackage AI\EntityExtractor\Entities
 * @since 1.4.0
 */

declare(strict_types=1);

namespace SagaManager\AI\EntityExtractor\Entities;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Extracted Entity Status Enum
 */
enum ExtractedEntityStatus: string {

	case PENDING   = 'pending';
	case APPROVED  = 'approved';
	case REJECTED  = 'rejected';
	case DUPLICATE = 'duplicate';
	case CREATED   = 'created';

	/**
	 * Check if entity is actionable (can be approved/rejected)
	 *
	 * @return bool
	 */
	public function isActionable(): bool {
		return in_array( $this, array( self::PENDING, self::DUPLICATE ), true );
	}

	/**
	 * Check if entity is final (no more actions possible)
	 *
	 * @return bool
	 */
	public function isFinal(): bool {
		return in_array( $this, array( self::REJECTED, self::CREATED ), true );
	}
}

/**
 * Entity Type Enum
 */
enum EntityType: string {

	case CHARACTER = 'character';
	case LOCATION  = 'location';
	case EVENT     = 'event';
	case FACTION   = 'faction';
	case ARTIFACT  = 'artifact';
	case CONCEPT   = 'concept';

	/**
	 * Get human-readable label
	 *
	 * @return string
	 */
	public function getLabel(): string {
		return ucfirst( $this->value );
	}
}

/**
 * Extracted Entity Value Object
 *
 * Represents a single entity extracted from text, pending approval.
 */
final readonly class ExtractedEntity {

	/**
	 * Constructor
	 *
	 * @param int|null              $id Entity ID (null for new)
	 * @param int                   $job_id Parent extraction job ID
	 * @param EntityType            $entity_type Type of entity
	 * @param string                $canonical_name Primary entity name
	 * @param array                 $alternative_names Array of aliases
	 * @param string|null           $description Entity description
	 * @param array                 $attributes Extracted attributes
	 * @param string|null           $context_snippet Text where found
	 * @param float                 $confidence_score AI confidence 0-100
	 * @param int                   $chunk_index Which chunk entity was found in
	 * @param int|null              $position_in_text Character offset
	 * @param ExtractedEntityStatus $status Current status
	 * @param int|null              $duplicate_of Existing entity ID if duplicate
	 * @param float|null            $duplicate_similarity Similarity score 0-100
	 * @param int|null              $created_entity_id ID after batch creation
	 * @param int|null              $reviewed_by User ID who reviewed
	 * @param int|null              $reviewed_at Unix timestamp
	 * @param int                   $created_at Unix timestamp
	 */
	public function __construct(
		public ?int $id,
		public int $job_id,
		public EntityType $entity_type,
		public string $canonical_name,
		public array $alternative_names,
		public ?string $description,
		public array $attributes,
		public ?string $context_snippet,
		public float $confidence_score,
		public int $chunk_index,
		public ?int $position_in_text,
		public ExtractedEntityStatus $status,
		public ?int $duplicate_of,
		public ?float $duplicate_similarity,
		public ?int $created_entity_id,
		public ?int $reviewed_by,
		public ?int $reviewed_at,
		public int $created_at
	) {
		// Validation
		if ( empty( trim( $this->canonical_name ) ) ) {
			throw new \InvalidArgumentException( 'Canonical name cannot be empty' );
		}

		if ( $this->confidence_score < 0 || $this->confidence_score > 100 ) {
			throw new \InvalidArgumentException( 'Confidence score must be between 0 and 100' );
		}

		if ( $this->duplicate_similarity !== null &&
			( $this->duplicate_similarity < 0 || $this->duplicate_similarity > 100 ) ) {
			throw new \InvalidArgumentException( 'Duplicate similarity must be between 0 and 100' );
		}

		if ( $this->chunk_index < 0 ) {
			throw new \InvalidArgumentException( 'Chunk index cannot be negative' );
		}
	}

	/**
	 * Create from database row
	 *
	 * @param array $row Database row
	 * @return self
	 */
	public static function fromArray( array $row ): self {
		return new self(
			id: isset( $row['id'] ) ? (int) $row['id'] : null,
			job_id: (int) $row['job_id'],
			entity_type: EntityType::from( $row['entity_type'] ),
			canonical_name: (string) $row['canonical_name'],
			alternative_names: isset( $row['alternative_names'] )
				? json_decode( $row['alternative_names'], true ) ?? array()
				: array(),
			description: $row['description'] ?? null,
			attributes: isset( $row['attributes'] )
				? json_decode( $row['attributes'], true ) ?? array()
				: array(),
			context_snippet: $row['context_snippet'] ?? null,
			confidence_score: (float) $row['confidence_score'],
			chunk_index: (int) ( $row['chunk_index'] ?? 0 ),
			position_in_text: isset( $row['position_in_text'] ) ? (int) $row['position_in_text'] : null,
			status: ExtractedEntityStatus::from( $row['status'] ?? 'pending' ),
			duplicate_of: isset( $row['duplicate_of'] ) ? (int) $row['duplicate_of'] : null,
			duplicate_similarity: isset( $row['duplicate_similarity'] ) ? (float) $row['duplicate_similarity'] : null,
			created_entity_id: isset( $row['created_entity_id'] ) ? (int) $row['created_entity_id'] : null,
			reviewed_by: isset( $row['reviewed_by'] ) ? (int) $row['reviewed_by'] : null,
			reviewed_at: isset( $row['reviewed_at'] ) ? strtotime( $row['reviewed_at'] ) : null,
			created_at: strtotime( $row['created_at'] )
		);
	}

	/**
	 * Convert to database array
	 *
	 * @return array
	 */
	public function toArray(): array {
		return array(
			'id'                   => $this->id,
			'job_id'               => $this->job_id,
			'entity_type'          => $this->entity_type->value,
			'canonical_name'       => $this->canonical_name,
			'alternative_names'    => json_encode( $this->alternative_names ),
			'description'          => $this->description,
			'attributes'           => json_encode( $this->attributes ),
			'context_snippet'      => $this->context_snippet,
			'confidence_score'     => $this->confidence_score,
			'chunk_index'          => $this->chunk_index,
			'position_in_text'     => $this->position_in_text,
			'status'               => $this->status->value,
			'duplicate_of'         => $this->duplicate_of,
			'duplicate_similarity' => $this->duplicate_similarity,
			'created_entity_id'    => $this->created_entity_id,
			'reviewed_by'          => $this->reviewed_by,
			'reviewed_at'          => $this->reviewed_at ? date( 'Y-m-d H:i:s', $this->reviewed_at ) : null,
			'created_at'           => date( 'Y-m-d H:i:s', $this->created_at ),
		);
	}

	/**
	 * Get confidence level label
	 *
	 * @return string high, medium, low
	 */
	public function getConfidenceLevel(): string {
		if ( $this->confidence_score >= 80 ) {
			return 'high';
		} elseif ( $this->confidence_score >= 60 ) {
			return 'medium';
		} else {
			return 'low';
		}
	}

	/**
	 * Check if entity is likely duplicate
	 *
	 * @return bool
	 */
	public function isPossibleDuplicate(): bool {
		return $this->duplicate_of !== null && $this->duplicate_similarity !== null;
	}

	/**
	 * Get all names (canonical + alternatives)
	 *
	 * @return array
	 */
	public function getAllNames(): array {
		return array_unique( array_merge( array( $this->canonical_name ), $this->alternative_names ) );
	}

	/**
	 * Check if entity has specific attribute
	 *
	 * @param string $key Attribute key
	 * @return bool
	 */
	public function hasAttribute( string $key ): bool {
		return isset( $this->attributes[ $key ] );
	}

	/**
	 * Get attribute value
	 *
	 * @param string $key Attribute key
	 * @param mixed  $default Default value
	 * @return mixed
	 */
	public function getAttribute( string $key, mixed $default = null ): mixed {
		return $this->attributes[ $key ] ?? $default;
	}

	/**
	 * Get truncated context snippet
	 *
	 * @param int $max_length Maximum length
	 * @return string|null
	 */
	public function getTruncatedContext( int $max_length = 200 ): ?string {
		if ( $this->context_snippet === null ) {
			return null;
		}

		if ( mb_strlen( $this->context_snippet ) <= $max_length ) {
			return $this->context_snippet;
		}

		return mb_substr( $this->context_snippet, 0, $max_length ) . '...';
	}

	/**
	 * Create entity with approved status
	 *
	 * @param int $user_id User who approved
	 * @return self
	 */
	public function approve( int $user_id ): self {
		$data                = $this->toArray();
		$data['status']      = ExtractedEntityStatus::APPROVED->value;
		$data['reviewed_by'] = $user_id;
		$data['reviewed_at'] = date( 'Y-m-d H:i:s' );
		return self::fromArray( $data );
	}

	/**
	 * Create entity with rejected status
	 *
	 * @param int $user_id User who rejected
	 * @return self
	 */
	public function reject( int $user_id ): self {
		$data                = $this->toArray();
		$data['status']      = ExtractedEntityStatus::REJECTED->value;
		$data['reviewed_by'] = $user_id;
		$data['reviewed_at'] = date( 'Y-m-d H:i:s' );
		return self::fromArray( $data );
	}

	/**
	 * Create entity marked as created
	 *
	 * @param int $entity_id Created entity ID
	 * @return self
	 */
	public function markCreated( int $entity_id ): self {
		$data                      = $this->toArray();
		$data['status']            = ExtractedEntityStatus::CREATED->value;
		$data['created_entity_id'] = $entity_id;
		return self::fromArray( $data );
	}

	/**
	 * Create entity marked as duplicate
	 *
	 * @param int   $existing_entity_id Existing entity ID
	 * @param float $similarity Similarity score
	 * @return self
	 */
	public function markDuplicate( int $existing_entity_id, float $similarity ): self {
		$data                         = $this->toArray();
		$data['status']               = ExtractedEntityStatus::DUPLICATE->value;
		$data['duplicate_of']         = $existing_entity_id;
		$data['duplicate_similarity'] = $similarity;
		return self::fromArray( $data );
	}

	/**
	 * Check if entity should be shown to user for review
	 *
	 * @return bool
	 */
	public function needsReview(): bool {
		return $this->status->isActionable();
	}

	/**
	 * Get quality score (combination of confidence and completeness)
	 *
	 * @return float 0-100
	 */
	public function getQualityScore(): float {
		$confidence_weight   = 0.7;
		$completeness_weight = 0.3;

		// Calculate completeness
		$completeness = 0.0;
		if ( ! empty( $this->description ) ) {
			$completeness += 40;
		}
		if ( ! empty( $this->alternative_names ) ) {
			$completeness += 20;
		}
		if ( ! empty( $this->attributes ) ) {
			$completeness += 20;
		}
		if ( $this->context_snippet !== null ) {
			$completeness += 20;
		}

		$quality = ( $this->confidence_score * $confidence_weight ) + ( $completeness * $completeness_weight );

		return round( $quality, 2 );
	}
}
