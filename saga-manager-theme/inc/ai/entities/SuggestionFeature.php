<?php
/**
 * Suggestion Feature Value Object
 *
 * Represents a single extracted feature used for relationship prediction.
 * Features are normalized to 0-1 range and combined with learned weights
 * to calculate prediction confidence.
 *
 * @package SagaManager
 * @subpackage AI\PredictiveRelationships\Entities
 * @since 1.4.0
 */

declare(strict_types=1);

namespace SagaManager\AI\PredictiveRelationships\Entities;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Feature Type Enum
 */
enum FeatureType: string {

	case CO_OCCURRENCE        = 'co_occurrence';           // Entities appear together in content
	case TIMELINE_PROXIMITY   = 'timeline_proximity'; // Entities close in timeline
	case ATTRIBUTE_SIMILARITY = 'attribute_similarity'; // Similar attributes
	case CONTENT_SIMILARITY   = 'content_similarity'; // Similar descriptions
	case NETWORK_CENTRALITY   = 'network_centrality'; // Graph centrality
	case SEMANTIC_SIMILARITY  = 'semantic_similarity'; // Embedding similarity
	case SHARED_LOCATION      = 'shared_location';       // Common locations
	case SHARED_FACTION       = 'shared_faction';         // Same faction
	case MENTION_FREQUENCY    = 'mention_frequency';   // Mention frequency together

	/**
	 * Get feature description
	 *
	 * @return string
	 */
	public function getDescription(): string {
		return match ( $this ) {
			self::CO_OCCURRENCE => 'How often entities appear together',
			self::TIMELINE_PROXIMITY => 'Timeline distance between entities',
			self::ATTRIBUTE_SIMILARITY => 'Similarity of entity attributes',
			self::CONTENT_SIMILARITY => 'Similarity of descriptions',
			self::NETWORK_CENTRALITY => 'Centrality in relationship graph',
			self::SEMANTIC_SIMILARITY => 'Semantic embedding similarity',
			self::SHARED_LOCATION => 'Common locations',
			self::SHARED_FACTION => 'Same faction membership',
			self::MENTION_FREQUENCY => 'Co-mention frequency'
		};
	}

	/**
	 * Get default weight for this feature type
	 *
	 * @return float Default weight 0-1
	 */
	public function getDefaultWeight(): float {
		return match ( $this ) {
			self::CO_OCCURRENCE => 0.7,
			self::SEMANTIC_SIMILARITY => 0.8,
			self::TIMELINE_PROXIMITY => 0.6,
			self::ATTRIBUTE_SIMILARITY => 0.5,
			self::CONTENT_SIMILARITY => 0.6,
			self::NETWORK_CENTRALITY => 0.4,
			self::SHARED_LOCATION => 0.5,
			self::SHARED_FACTION => 0.7,
			self::MENTION_FREQUENCY => 0.6
		};
	}
}

/**
 * Suggestion Feature Value Object
 *
 * Represents an extracted feature for relationship prediction.
 */
final readonly class SuggestionFeature {

	/**
	 * Constructor
	 *
	 * @param int|null    $id Feature ID (null for new)
	 * @param int         $suggestion_id Suggestion ID
	 * @param FeatureType $feature_type Type of feature
	 * @param string      $feature_name Human-readable name
	 * @param float       $feature_value Normalized value 0-1
	 * @param float       $weight Feature importance weight 0-1
	 * @param array|null  $metadata Additional feature data
	 * @param int         $created_at Unix timestamp
	 */
	public function __construct(
		public ?int $id,
		public int $suggestion_id,
		public FeatureType $feature_type,
		public string $feature_name,
		public float $feature_value,
		public float $weight,
		public ?array $metadata,
		public int $created_at
	) {
		// Validation
		if ( $this->feature_value < 0 || $this->feature_value > 1 ) {
			throw new \InvalidArgumentException(
				sprintf( 'Feature value must be between 0 and 1, got %.4f', $this->feature_value )
			);
		}

		if ( $this->weight < 0 || $this->weight > 1 ) {
			throw new \InvalidArgumentException(
				sprintf( 'Weight must be between 0 and 1, got %.4f', $this->weight )
			);
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
			suggestion_id: (int) $row['suggestion_id'],
			feature_type: FeatureType::from( $row['feature_type'] ),
			feature_name: $row['feature_name'],
			feature_value: (float) $row['feature_value'],
			weight: (float) ( $row['weight'] ?? 0.5 ),
			metadata: isset( $row['metadata'] ) ? json_decode( $row['metadata'], true ) : null,
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
			'id'            => $this->id,
			'suggestion_id' => $this->suggestion_id,
			'feature_type'  => $this->feature_type->value,
			'feature_name'  => $this->feature_name,
			'feature_value' => $this->feature_value,
			'weight'        => $this->weight,
			'metadata'      => $this->metadata ? json_encode( $this->metadata ) : null,
			'created_at'    => date( 'Y-m-d H:i:s', $this->created_at ),
		);
	}

	/**
	 * Get weighted value
	 *
	 * Multiplies feature value by weight
	 *
	 * @return float Weighted value 0-1
	 */
	public function getWeightedValue(): float {
		return $this->feature_value * $this->weight;
	}

	/**
	 * Get feature strength label
	 *
	 * @return string very_strong, strong, moderate, weak
	 */
	public function getStrengthLabel(): string {
		if ( $this->feature_value >= 0.8 ) {
			return 'very_strong';
		} elseif ( $this->feature_value >= 0.6 ) {
			return 'strong';
		} elseif ( $this->feature_value >= 0.4 ) {
			return 'moderate';
		} else {
			return 'weak';
		}
	}

	/**
	 * Create feature with custom weight
	 *
	 * @param float $new_weight New weight 0-1
	 * @return self
	 */
	public function withWeight( float $new_weight ): self {
		if ( $new_weight < 0 || $new_weight > 1 ) {
			throw new \InvalidArgumentException( 'Weight must be between 0 and 1' );
		}

		$data           = $this->toArray();
		$data['weight'] = $new_weight;
		return self::fromArray( $data );
	}

	/**
	 * Get feature contribution to overall confidence
	 *
	 * Percentage of weighted contribution
	 *
	 * @param float $total_weighted_sum Total of all weighted features
	 * @return float Percentage contribution 0-100
	 */
	public function getContribution( float $total_weighted_sum ): float {
		if ( $total_weighted_sum <= 0 ) {
			return 0;
		}

		return ( $this->getWeightedValue() / $total_weighted_sum ) * 100;
	}

	/**
	 * Is this a high-value feature?
	 *
	 * @return bool
	 */
	public function isHighValue(): bool {
		return $this->feature_value >= 0.7 && $this->weight >= 0.6;
	}

	/**
	 * Get human-readable explanation
	 *
	 * @return string
	 */
	public function getExplanation(): string {
		$strength = match ( $this->getStrengthLabel() ) {
			'very_strong' => 'Very strong',
			'strong' => 'Strong',
			'moderate' => 'Moderate',
			'weak' => 'Weak'
		};

		$percentage = round( $this->feature_value * 100, 1 );

		return sprintf(
			'%s: %s signal (%.1f%%, weight: %.2f)',
			$this->feature_name,
			$strength,
			$percentage,
			$this->weight
		);
	}

	/**
	 * Create feature from raw data
	 *
	 * @param int         $suggestion_id Suggestion ID
	 * @param FeatureType $type Feature type
	 * @param string      $name Feature name
	 * @param float       $raw_value Raw unnormalized value
	 * @param float       $min Minimum possible value
	 * @param float       $max Maximum possible value
	 * @param float|null  $weight Optional weight (uses default if null)
	 * @param array|null  $metadata Optional metadata
	 * @return self
	 */
	public static function createNormalized(
		int $suggestion_id,
		FeatureType $type,
		string $name,
		float $raw_value,
		float $min,
		float $max,
		?float $weight = null,
		?array $metadata = null
	): self {
		// Normalize value to 0-1 range
		$normalized = $max > $min
			? ( $raw_value - $min ) / ( $max - $min )
			: 0.5;

		// Clamp to 0-1
		$normalized = max( 0, min( 1, $normalized ) );

		return new self(
			id: null,
			suggestion_id: $suggestion_id,
			feature_type: $type,
			feature_name: $name,
			feature_value: $normalized,
			weight: $weight ?? $type->getDefaultWeight(),
			metadata: $metadata,
			created_at: time()
		);
	}
}
