<?php
/**
 * Relationship Suggestion Value Object
 *
 * Immutable value object representing an AI-generated relationship suggestion
 * awaiting user review. Tracks confidence, reasoning, and user actions for
 * machine learning feedback loop.
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
 * Suggestion Status Enum
 */
enum SuggestionStatus: string {

	case PENDING       = 'pending';
	case ACCEPTED      = 'accepted';
	case REJECTED      = 'rejected';
	case MODIFIED      = 'modified';
	case AUTO_ACCEPTED = 'auto_accepted';

	/**
	 * Check if status is actioned (not pending)
	 *
	 * @return bool
	 */
	public function isActioned(): bool {
		return $this !== self::PENDING;
	}

	/**
	 * Check if status is positive (accepted/modified/auto_accepted)
	 *
	 * @return bool
	 */
	public function isPositive(): bool {
		return match ( $this ) {
			self::ACCEPTED, self::MODIFIED, self::AUTO_ACCEPTED => true,
			default => false
		};
	}
}

/**
 * User Action Type Enum
 */
enum UserActionType: string {

	case NONE    = 'none';
	case ACCEPT  = 'accept';
	case REJECT  = 'reject';
	case MODIFY  = 'modify';
	case DISMISS = 'dismiss';
}

/**
 * Suggestion Method Enum
 */
enum SuggestionMethod: string {

	case CONTENT   = 'content';               // Based on content analysis
	case TIMELINE  = 'timeline';             // Based on timeline proximity
	case ATTRIBUTE = 'attribute';           // Based on attribute similarity
	case SEMANTIC  = 'semantic';             // Based on semantic embeddings
	case HYBRID    = 'hybrid';                 // Multiple methods combined
}

/**
 * Relationship Suggestion Value Object
 *
 * Represents an AI-generated relationship suggestion between two entities.
 */
final readonly class RelationshipSuggestion {

	/**
	 * Constructor
	 *
	 * @param int|null         $id Suggestion ID (null for new)
	 * @param int              $saga_id Saga ID
	 * @param int              $source_entity_id Source entity ID
	 * @param int              $target_entity_id Target entity ID
	 * @param string           $suggested_type Relationship type (ally, enemy, family, mentor, etc)
	 * @param float            $confidence_score AI confidence 0-100
	 * @param int              $strength Relationship strength 0-100
	 * @param string|null      $reasoning AI explanation
	 * @param array|null       $evidence Supporting evidence
	 * @param SuggestionMethod $suggestion_method How suggestion was generated
	 * @param string           $ai_model AI model used (gpt-4, claude-3, etc)
	 * @param SuggestionStatus $status Current status
	 * @param UserActionType   $user_action_type User's action
	 * @param string|null      $user_feedback_text User explanation
	 * @param int|null         $accepted_at Unix timestamp when accepted
	 * @param int|null         $rejected_at Unix timestamp when rejected
	 * @param int|null         $actioned_by User ID who took action
	 * @param int|null         $created_relationship_id Created relationship ID if accepted
	 * @param float            $priority_score Display priority 0-100
	 * @param int              $created_at Unix timestamp
	 * @param int              $updated_at Unix timestamp
	 */
	public function __construct(
		public ?int $id,
		public int $saga_id,
		public int $source_entity_id,
		public int $target_entity_id,
		public string $suggested_type,
		public float $confidence_score,
		public int $strength,
		public ?string $reasoning,
		public ?array $evidence,
		public SuggestionMethod $suggestion_method,
		public string $ai_model,
		public SuggestionStatus $status,
		public UserActionType $user_action_type,
		public ?string $user_feedback_text,
		public ?int $accepted_at,
		public ?int $rejected_at,
		public ?int $actioned_by,
		public ?int $created_relationship_id,
		public float $priority_score,
		public int $created_at,
		public int $updated_at
	) {
		// Validation
		if ( $this->confidence_score < 0 || $this->confidence_score > 100 ) {
			throw new \InvalidArgumentException( 'Confidence score must be between 0 and 100' );
		}

		if ( $this->strength < 0 || $this->strength > 100 ) {
			throw new \InvalidArgumentException( 'Strength must be between 0 and 100' );
		}

		if ( $this->priority_score < 0 || $this->priority_score > 100 ) {
			throw new \InvalidArgumentException( 'Priority score must be between 0 and 100' );
		}

		if ( $this->source_entity_id === $this->target_entity_id ) {
			throw new \InvalidArgumentException( 'Cannot suggest relationship to self' );
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
			saga_id: (int) $row['saga_id'],
			source_entity_id: (int) $row['source_entity_id'],
			target_entity_id: (int) $row['target_entity_id'],
			suggested_type: $row['suggested_type'],
			confidence_score: (float) $row['confidence_score'],
			strength: (int) $row['strength'],
			reasoning: $row['reasoning'] ?? null,
			evidence: isset( $row['evidence'] ) ? json_decode( $row['evidence'], true ) : null,
			suggestion_method: SuggestionMethod::from( $row['suggestion_method'] ),
			ai_model: $row['ai_model'] ?? 'gpt-4',
			status: SuggestionStatus::from( $row['status'] ?? 'pending' ),
			user_action_type: UserActionType::from( $row['user_action_type'] ?? 'none' ),
			user_feedback_text: $row['user_feedback_text'] ?? null,
			accepted_at: isset( $row['accepted_at'] ) && $row['accepted_at'] ? strtotime( $row['accepted_at'] ) : null,
			rejected_at: isset( $row['rejected_at'] ) && $row['rejected_at'] ? strtotime( $row['rejected_at'] ) : null,
			actioned_by: isset( $row['actioned_by'] ) ? (int) $row['actioned_by'] : null,
			created_relationship_id: isset( $row['created_relationship_id'] ) ? (int) $row['created_relationship_id'] : null,
			priority_score: (float) ( $row['priority_score'] ?? 50 ),
			created_at: strtotime( $row['created_at'] ),
			updated_at: strtotime( $row['updated_at'] )
		);
	}

	/**
	 * Convert to database array
	 *
	 * @return array
	 */
	public function toArray(): array {
		return array(
			'id'                      => $this->id,
			'saga_id'                 => $this->saga_id,
			'source_entity_id'        => $this->source_entity_id,
			'target_entity_id'        => $this->target_entity_id,
			'suggested_type'          => $this->suggested_type,
			'confidence_score'        => $this->confidence_score,
			'strength'                => $this->strength,
			'reasoning'               => $this->reasoning,
			'evidence'                => $this->evidence ? json_encode( $this->evidence ) : null,
			'suggestion_method'       => $this->suggestion_method->value,
			'ai_model'                => $this->ai_model,
			'status'                  => $this->status->value,
			'user_action_type'        => $this->user_action_type->value,
			'user_feedback_text'      => $this->user_feedback_text,
			'accepted_at'             => $this->accepted_at ? date( 'Y-m-d H:i:s', $this->accepted_at ) : null,
			'rejected_at'             => $this->rejected_at ? date( 'Y-m-d H:i:s', $this->rejected_at ) : null,
			'actioned_by'             => $this->actioned_by,
			'created_relationship_id' => $this->created_relationship_id,
			'priority_score'          => $this->priority_score,
			'created_at'              => date( 'Y-m-d H:i:s', $this->created_at ),
			'updated_at'              => date( 'Y-m-d H:i:s', $this->updated_at ),
		);
	}

	/**
	 * Check if suggestion is pending review
	 *
	 * @return bool
	 */
	public function isPending(): bool {
		return $this->status === SuggestionStatus::PENDING;
	}

	/**
	 * Check if suggestion has been actioned
	 *
	 * @return bool
	 */
	public function isActioned(): bool {
		return $this->status->isActioned();
	}

	/**
	 * Get confidence level label
	 *
	 * @return string very_high, high, medium, low
	 */
	public function getConfidenceLevel(): string {
		if ( $this->confidence_score >= 90 ) {
			return 'very_high';
		} elseif ( $this->confidence_score >= 75 ) {
			return 'high';
		} elseif ( $this->confidence_score >= 60 ) {
			return 'medium';
		} else {
			return 'low';
		}
	}

	/**
	 * Accept suggestion
	 *
	 * @param int      $user_id User ID who accepted
	 * @param int|null $created_relationship_id Created relationship ID
	 * @return self
	 */
	public function accept( int $user_id, ?int $created_relationship_id = null ): self {
		$data                            = $this->toArray();
		$data['status']                  = SuggestionStatus::ACCEPTED->value;
		$data['user_action_type']        = UserActionType::ACCEPT->value;
		$data['accepted_at']             = date( 'Y-m-d H:i:s' );
		$data['actioned_by']             = $user_id;
		$data['created_relationship_id'] = $created_relationship_id;
		$data['updated_at']              = date( 'Y-m-d H:i:s' );
		return self::fromArray( $data );
	}

	/**
	 * Reject suggestion
	 *
	 * @param int         $user_id User ID who rejected
	 * @param string|null $feedback User feedback
	 * @return self
	 */
	public function reject( int $user_id, ?string $feedback = null ): self {
		$data                       = $this->toArray();
		$data['status']             = SuggestionStatus::REJECTED->value;
		$data['user_action_type']   = UserActionType::REJECT->value;
		$data['rejected_at']        = date( 'Y-m-d H:i:s' );
		$data['actioned_by']        = $user_id;
		$data['user_feedback_text'] = $feedback;
		$data['updated_at']         = date( 'Y-m-d H:i:s' );
		return self::fromArray( $data );
	}

	/**
	 * Modify suggestion
	 *
	 * @param int      $user_id User ID who modified
	 * @param string   $new_type New relationship type
	 * @param int      $new_strength New strength
	 * @param int|null $created_relationship_id Created relationship ID
	 * @return self
	 */
	public function modify( int $user_id, string $new_type, int $new_strength, ?int $created_relationship_id = null ): self {
		$data                            = $this->toArray();
		$data['status']                  = SuggestionStatus::MODIFIED->value;
		$data['user_action_type']        = UserActionType::MODIFY->value;
		$data['suggested_type']          = $new_type;
		$data['strength']                = $new_strength;
		$data['accepted_at']             = date( 'Y-m-d H:i:s' );
		$data['actioned_by']             = $user_id;
		$data['created_relationship_id'] = $created_relationship_id;
		$data['updated_at']              = date( 'Y-m-d H:i:s' );
		return self::fromArray( $data );
	}

	/**
	 * Calculate priority score for display ordering
	 *
	 * Combines confidence, relationship importance, and method quality
	 *
	 * @return float Priority score 0-100
	 */
	public function calculatePriorityScore(): float {
		$base_score = $this->confidence_score;

		// Boost high-strength relationships
		if ( $this->strength >= 80 ) {
			$base_score += 10;
		} elseif ( $this->strength >= 60 ) {
			$base_score += 5;
		}

		// Method quality boost
		$method_boost = match ( $this->suggestion_method ) {
			SuggestionMethod::HYBRID => 10,
			SuggestionMethod::SEMANTIC => 5,
			SuggestionMethod::CONTENT => 3,
			SuggestionMethod::TIMELINE => 2,
			SuggestionMethod::ATTRIBUTE => 1
		};
		$base_score += $method_boost;

		// Important relationship types boost
		$important_types = array( 'family', 'mentor', 'enemy', 'ally' );
		if ( in_array( strtolower( $this->suggested_type ), $important_types, true ) ) {
			$base_score += 5;
		}

		return min( $base_score, 100 );
	}

	/**
	 * Get time to decision in seconds
	 *
	 * @return int|null Seconds from creation to decision, null if not actioned
	 */
	public function getTimeToDecision(): ?int {
		if ( $this->accepted_at ) {
			return $this->accepted_at - $this->created_at;
		}

		if ( $this->rejected_at ) {
			return $this->rejected_at - $this->created_at;
		}

		return null;
	}

	/**
	 * Should this suggestion be auto-accepted?
	 *
	 * Very high confidence suggestions can be auto-accepted
	 *
	 * @return bool
	 */
	public function shouldAutoAccept(): bool {
		return $this->confidence_score >= 95 &&
				$this->suggestion_method === SuggestionMethod::HYBRID;
	}

	/**
	 * Get human-readable explanation
	 *
	 * @return string
	 */
	public function getExplanation(): string {
		if ( $this->reasoning ) {
			return $this->reasoning;
		}

		$method = match ( $this->suggestion_method ) {
			SuggestionMethod::CONTENT => 'content analysis',
			SuggestionMethod::TIMELINE => 'timeline proximity',
			SuggestionMethod::ATTRIBUTE => 'attribute similarity',
			SuggestionMethod::SEMANTIC => 'semantic analysis',
			SuggestionMethod::HYBRID => 'multiple factors'
		};

		$confidence_desc = match ( $this->getConfidenceLevel() ) {
			'very_high' => 'Very high confidence',
			'high' => 'High confidence',
			'medium' => 'Medium confidence',
			'low' => 'Low confidence'
		};

		return sprintf(
			'%s based on %s (%.1f%% confidence)',
			$confidence_desc,
			$method,
			$this->confidence_score
		);
	}
}
