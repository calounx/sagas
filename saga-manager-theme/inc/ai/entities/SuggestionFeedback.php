<?php
/**
 * Suggestion Feedback Value Object
 *
 * Tracks user feedback on relationship suggestions for machine learning.
 * Records user actions, decisions, and timing data to improve future predictions.
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
 * Feedback Action Enum
 */
enum FeedbackAction: string {

	case ACCEPT  = 'accept';
	case REJECT  = 'reject';
	case MODIFY  = 'modify';
	case DISMISS = 'dismiss';

	/**
	 * Check if action is positive (accept/modify)
	 *
	 * @return bool
	 */
	public function isPositive(): bool {
		return match ( $this ) {
			self::ACCEPT, self::MODIFY => true,
			default => false
		};
	}

	/**
	 * Check if action is negative (reject)
	 *
	 * @return bool
	 */
	public function isNegative(): bool {
		return $this === self::REJECT;
	}

	/**
	 * Check if action is neutral (dismiss)
	 *
	 * @return bool
	 */
	public function isNeutral(): bool {
		return $this === self::DISMISS;
	}
}

/**
 * Suggestion Feedback Value Object
 *
 * Represents user feedback on a relationship suggestion.
 */
final readonly class SuggestionFeedback {

	/**
	 * Constructor
	 *
	 * @param int|null       $id Feedback ID (null for new)
	 * @param int            $suggestion_id Suggestion ID
	 * @param int            $user_id User who provided feedback
	 * @param FeedbackAction $action User's action
	 * @param string|null    $modified_type User-corrected relationship type
	 * @param int|null       $modified_strength User-corrected strength 0-100
	 * @param string|null    $feedback_text User explanation
	 * @param float          $confidence_at_decision Confidence score when decision made
	 * @param array|null     $features_at_decision Feature values when decided
	 * @param int            $time_to_decision_seconds Time from creation to decision
	 * @param bool           $was_auto_accepted Whether suggestion was auto-accepted
	 * @param int            $created_at Unix timestamp
	 */
	public function __construct(
		public ?int $id,
		public int $suggestion_id,
		public int $user_id,
		public FeedbackAction $action,
		public ?string $modified_type,
		public ?int $modified_strength,
		public ?string $feedback_text,
		public float $confidence_at_decision,
		public ?array $features_at_decision,
		public int $time_to_decision_seconds,
		public bool $was_auto_accepted,
		public int $created_at
	) {
		// Validation
		if ( $this->confidence_at_decision < 0 || $this->confidence_at_decision > 100 ) {
			throw new \InvalidArgumentException( 'Confidence must be between 0 and 100' );
		}

		if ( $this->modified_strength !== null && ( $this->modified_strength < 0 || $this->modified_strength > 100 ) ) {
			throw new \InvalidArgumentException( 'Modified strength must be between 0 and 100' );
		}

		if ( $this->time_to_decision_seconds < 0 ) {
			throw new \InvalidArgumentException( 'Time to decision cannot be negative' );
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
			user_id: (int) $row['user_id'],
			action: FeedbackAction::from( $row['action'] ),
			modified_type: $row['modified_type'] ?? null,
			modified_strength: isset( $row['modified_strength'] ) ? (int) $row['modified_strength'] : null,
			feedback_text: $row['feedback_text'] ?? null,
			confidence_at_decision: (float) $row['confidence_at_decision'],
			features_at_decision: isset( $row['features_at_decision'] )
				? json_decode( $row['features_at_decision'], true )
				: null,
			time_to_decision_seconds: (int) $row['time_to_decision_seconds'],
			was_auto_accepted: (bool) ( $row['was_auto_accepted'] ?? false ),
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
			'id'                       => $this->id,
			'suggestion_id'            => $this->suggestion_id,
			'user_id'                  => $this->user_id,
			'action'                   => $this->action->value,
			'modified_type'            => $this->modified_type,
			'modified_strength'        => $this->modified_strength,
			'feedback_text'            => $this->feedback_text,
			'confidence_at_decision'   => $this->confidence_at_decision,
			'features_at_decision'     => $this->features_at_decision
				? json_encode( $this->features_at_decision )
				: null,
			'time_to_decision_seconds' => $this->time_to_decision_seconds,
			'was_auto_accepted'        => $this->was_auto_accepted,
			'created_at'               => date( 'Y-m-d H:i:s', $this->created_at ),
		);
	}

	/**
	 * Check if feedback is positive
	 *
	 * @return bool
	 */
	public function wasPositive(): bool {
		return $this->action->isPositive();
	}

	/**
	 * Check if feedback is negative
	 *
	 * @return bool
	 */
	public function wasNegative(): bool {
		return $this->action->isNegative();
	}

	/**
	 * Check if user modified the suggestion
	 *
	 * @return bool
	 */
	public function wasModified(): bool {
		return $this->action === FeedbackAction::MODIFY;
	}

	/**
	 * Get decision speed category
	 *
	 * @return string instant, quick, considered, slow
	 */
	public function getDecisionSpeed(): string {
		if ( $this->time_to_decision_seconds < 10 ) {
			return 'instant'; // < 10 seconds
		} elseif ( $this->time_to_decision_seconds < 60 ) {
			return 'quick'; // < 1 minute
		} elseif ( $this->time_to_decision_seconds < 300 ) {
			return 'considered'; // < 5 minutes
		} else {
			return 'slow'; // > 5 minutes
		}
	}

	/**
	 * Was confidence appropriate for decision?
	 *
	 * High confidence should lead to acceptance, low to rejection
	 *
	 * @return bool
	 */
	public function wasConfidenceAppropriate(): bool {
		$high_confidence = $this->confidence_at_decision >= 75;
		$low_confidence  = $this->confidence_at_decision < 50;

		if ( $high_confidence && $this->wasPositive() ) {
			return true; // High confidence + accepted = good
		}

		if ( $low_confidence && $this->wasNegative() ) {
			return true; // Low confidence + rejected = good
		}

		if ( ! $high_confidence && ! $low_confidence ) {
			return true; // Medium confidence = always okay
		}

		return false; // Mismatch
	}

	/**
	 * Get feedback quality score
	 *
	 * Combines confidence appropriateness with decision speed
	 *
	 * @return float Quality score 0-100
	 */
	public function getQualityScore(): float {
		$score = 50; // Base score

		// Confidence appropriateness
		if ( $this->wasConfidenceAppropriate() ) {
			$score += 25;
		} else {
			$score -= 15;
		}

		// Decision speed (faster is better for high confidence)
		if ( $this->confidence_at_decision >= 80 ) {
			$speed_bonus = match ( $this->getDecisionSpeed() ) {
				'instant' => 15,
				'quick' => 10,
				'considered' => 5,
				'slow' => 0
			};
			$score += $speed_bonus;
		}

		// Modified suggestions are valuable learning data
		if ( $this->wasModified() ) {
			$score += 10;
		}

		// Has explanatory text
		if ( $this->feedback_text && strlen( $this->feedback_text ) > 20 ) {
			$score += 10;
		}

		return max( 0, min( 100, $score ) );
	}

	/**
	 * Get learning value
	 *
	 * How valuable is this feedback for machine learning?
	 *
	 * @return float Learning value 0-1
	 */
	public function getLearningValue(): float {
		$value = 0.5; // Base value

		// Clear decisions on edge confidences are valuable
		if ( $this->confidence_at_decision >= 90 && $this->wasNegative() ) {
			$value += 0.3; // High confidence but rejected - important to learn
		} elseif ( $this->confidence_at_decision <= 30 && $this->wasPositive() ) {
			$value += 0.3; // Low confidence but accepted - important to learn
		}

		// Modifications are very valuable
		if ( $this->wasModified() ) {
			$value += 0.2;
		}

		// Has feature data
		if ( $this->features_at_decision ) {
			$value += 0.1;
		}

		// Not auto-accepted (human decision)
		if ( ! $this->was_auto_accepted ) {
			$value += 0.1;
		}

		return min( 1.0, $value );
	}

	/**
	 * Create feedback from suggestion
	 *
	 * @param RelationshipSuggestion $suggestion Suggestion object
	 * @param int                    $user_id User ID
	 * @param FeedbackAction         $action User action
	 * @param array                  $features Current features
	 * @param string|null            $modified_type Modified type (for MODIFY action)
	 * @param int|null               $modified_strength Modified strength (for MODIFY action)
	 * @param string|null            $feedback_text Optional user explanation
	 * @return self
	 */
	public static function fromSuggestion(
		RelationshipSuggestion $suggestion,
		int $user_id,
		FeedbackAction $action,
		array $features,
		?string $modified_type = null,
		?int $modified_strength = null,
		?string $feedback_text = null
	): self {
		$time_to_decision = $suggestion->getTimeToDecision() ?? 0;

		return new self(
			id: null,
			suggestion_id: $suggestion->id,
			user_id: $user_id,
			action: $action,
			modified_type: $modified_type,
			modified_strength: $modified_strength,
			feedback_text: $feedback_text,
			confidence_at_decision: $suggestion->confidence_score,
			features_at_decision: $features,
			time_to_decision_seconds: $time_to_decision,
			was_auto_accepted: $suggestion->status === SuggestionStatus::AUTO_ACCEPTED,
			created_at: time()
		);
	}
}
