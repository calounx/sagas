<?php
/**
 * Learning Service
 *
 * Machine learning from user feedback on relationship suggestions.
 * Updates feature weights using gradient descent and weighted averaging
 * to improve future prediction accuracy.
 *
 * @package SagaManager
 * @subpackage AI\PredictiveRelationships
 * @since 1.4.0
 */

declare(strict_types=1);

namespace SagaManager\AI\PredictiveRelationships;

use SagaManager\AI\PredictiveRelationships\Entities\SuggestionFeedback;
use SagaManager\AI\PredictiveRelationships\Entities\FeedbackAction;
use SagaManager\AI\PredictiveRelationships\Entities\RelationshipSuggestion;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Learning Service
 *
 * Implements machine learning from user feedback.
 */
class LearningService {

	private SuggestionRepository $repository;

	/**
	 * Learning rate for weight updates
	 */
	private const LEARNING_RATE = 0.1;

	/**
	 * Minimum samples before updating weights
	 */
	private const MIN_SAMPLES = 5;

	/**
	 * Constructor
	 *
	 * @param SuggestionRepository|null $repository Suggestion repository
	 */
	public function __construct( ?SuggestionRepository $repository = null ) {
		$this->repository = $repository ?? new SuggestionRepository();
	}

	/**
	 * Record user feedback on suggestion
	 *
	 * @param int            $suggestion_id Suggestion ID
	 * @param FeedbackAction $action User's action
	 * @param int            $user_id User ID
	 * @param string|null    $modified_type Modified type (for MODIFY action)
	 * @param int|null       $modified_strength Modified strength (for MODIFY action)
	 * @param string|null    $feedback_text Optional user explanation
	 * @return void
	 * @throws \Exception If feedback recording fails
	 */
	public function recordFeedback(
		int $suggestion_id,
		FeedbackAction $action,
		int $user_id,
		?string $modified_type = null,
		?int $modified_strength = null,
		?string $feedback_text = null
	): void {
		// Get suggestion
		$suggestion = $this->repository->findById( $suggestion_id );
		if ( ! $suggestion ) {
			throw new \Exception( 'Suggestion not found' );
		}

		// Get features
		$features      = $this->repository->getFeatures( $suggestion_id );
		$feature_array = array();
		foreach ( $features as $feature ) {
			$feature_array[ $feature->feature_type->value ] = array(
				'value'  => $feature->feature_value,
				'weight' => $feature->weight,
			);
		}

		// Create feedback
		$feedback = SuggestionFeedback::fromSuggestion(
			suggestion: $suggestion,
			user_id: $user_id,
			action: $action,
			features: $feature_array,
			modified_type: $modified_type,
			modified_strength: $modified_strength,
			feedback_text: $feedback_text
		);

		// Save feedback
		$this->repository->saveFeedback( $feedback );

		error_log(
			sprintf(
				'[SAGA][PREDICTIVE] Recorded %s feedback for suggestion #%d',
				$action->value,
				$suggestion_id
			)
		);

		// Trigger weight update if enough samples
		$this->maybeUpdateWeights( $suggestion->saga_id );
	}

	/**
	 * Update feature weights based on feedback
	 *
	 * Uses simple gradient descent and weighted averaging
	 *
	 * @param int $saga_id Saga ID
	 * @return void
	 * @throws \Exception If update fails
	 */
	public function updateWeights( int $saga_id ): void {
		error_log( sprintf( '[SAGA][PREDICTIVE] Updating weights for saga %d', $saga_id ) );

		// Get all feedback for saga
		$feedback_records = $this->repository->getFeedbackForSaga( $saga_id );

		if ( count( $feedback_records ) < self::MIN_SAMPLES ) {
			error_log( '[SAGA][PREDICTIVE] Not enough feedback samples for learning' );
			return;
		}

		// Calculate weight adjustments per feature type
		$adjustments = $this->calculateWeightAdjustments( $feedback_records );

		// Get current weights
		$current_weights = $this->repository->getWeightsForSaga( $saga_id );

		// Apply adjustments
		foreach ( $adjustments as $feature_type => $adjustment ) {
			$current_weight = $current_weights[ $feature_type ] ?? 0.5;

			// Gradient descent update
			$new_weight = $current_weight + ( self::LEARNING_RATE * $adjustment );

			// Clamp to 0-1
			$new_weight = max( 0.0, min( 1.0, $new_weight ) );

			// Update in database
			$this->repository->updateWeight(
				saga_id: $saga_id,
				feature_type: $feature_type,
				weight: $new_weight,
				samples_count: count( $feedback_records )
			);

			error_log(
				sprintf(
					'[SAGA][PREDICTIVE] Updated %s weight: %.4f -> %.4f (adjustment: %.4f)',
					$feature_type,
					$current_weight,
					$new_weight,
					$adjustment
				)
			);
		}

		// Calculate and store accuracy
		$accuracy = $this->calculateAccuracy( $saga_id );
		$this->repository->updateAccuracy( $saga_id, $accuracy );

		error_log(
			sprintf(
				'[SAGA][PREDICTIVE] Learning complete. Accuracy: %.2f%%',
				$accuracy
			)
		);
	}

	/**
	 * Calculate weight adjustments from feedback
	 *
	 * @param array $feedback_records Array of SuggestionFeedback objects
	 * @return array Adjustments [feature_type => adjustment]
	 */
	private function calculateWeightAdjustments( array $feedback_records ): array {
		$adjustments = array();
		$counts      = array();

		foreach ( $feedback_records as $feedback ) {
			if ( ! $feedback->features_at_decision ) {
				continue;
			}

			// Determine if prediction was correct
			$was_correct   = $feedback->wasPositive() && $feedback->confidence_at_decision >= 70;
			$was_incorrect = $feedback->wasNegative() && $feedback->confidence_at_decision >= 70;

			if ( ! $was_correct && ! $was_incorrect ) {
				continue; // Skip ambiguous cases
			}

			// Calculate error signal
			$error = $was_correct ? 1.0 : -1.0;

			// Weight by learning value
			$error *= $feedback->getLearningValue();

			// Update adjustments for each feature
			foreach ( $feedback->features_at_decision as $feature_type => $feature_data ) {
				$feature_value = $feature_data['value'] ?? 0;

				// Gradient: error * feature_value
				$gradient = $error * $feature_value;

				if ( ! isset( $adjustments[ $feature_type ] ) ) {
					$adjustments[ $feature_type ] = 0;
					$counts[ $feature_type ]      = 0;
				}

				$adjustments[ $feature_type ] += $gradient;
				++$counts[ $feature_type ];
			}
		}

		// Average adjustments
		foreach ( $adjustments as $type => $total ) {
			if ( $counts[ $type ] > 0 ) {
				$adjustments[ $type ] = $total / $counts[ $type ];
			}
		}

		return $adjustments;
	}

	/**
	 * Calculate current accuracy metrics
	 *
	 * @param int $saga_id Saga ID
	 * @return float Accuracy percentage 0-100
	 */
	private function calculateAccuracy( int $saga_id ): float {
		$suggestions = $this->repository->getActionedSuggestions( $saga_id );

		if ( empty( $suggestions ) ) {
			return 0.0;
		}

		$correct = 0;
		$total   = count( $suggestions );

		foreach ( $suggestions as $suggestion ) {
			// Consider high-confidence accepted or low-confidence rejected as correct
			$is_correct = false;

			if ( $suggestion->status->isPositive() && $suggestion->confidence_score >= 70 ) {
				$is_correct = true; // High confidence + accepted
			} elseif ( ! $suggestion->status->isPositive() && $suggestion->confidence_score < 50 ) {
				$is_correct = true; // Low confidence + rejected
			}

			if ( $is_correct ) {
				++$correct;
			}
		}

		return round( ( $correct / $total ) * 100, 2 );
	}

	/**
	 * Get accuracy metrics for saga
	 *
	 * @param int $saga_id Saga ID
	 * @return array Metrics array with precision, recall, F1 score
	 */
	public function getAccuracyMetrics( int $saga_id ): array {
		$suggestions = $this->repository->getActionedSuggestions( $saga_id );

		if ( empty( $suggestions ) ) {
			return array(
				'precision'     => 0,
				'recall'        => 0,
				'f1_score'      => 0,
				'accuracy'      => 0,
				'total_samples' => 0,
			);
		}

		$true_positives  = 0;  // High confidence + accepted
		$false_positives = 0; // High confidence + rejected
		$true_negatives  = 0;  // Low confidence + rejected
		$false_negatives = 0; // Low confidence + accepted

		foreach ( $suggestions as $suggestion ) {
			$high_confidence = $suggestion->confidence_score >= 70;
			$accepted        = $suggestion->status->isPositive();

			if ( $high_confidence && $accepted ) {
				++$true_positives;
			} elseif ( $high_confidence && ! $accepted ) {
				++$false_positives;
			} elseif ( ! $high_confidence && ! $accepted ) {
				++$true_negatives;
			} else {
				++$false_negatives;
			}
		}

		// Calculate precision: TP / (TP + FP)
		$precision = ( $true_positives + $false_positives ) > 0
			? $true_positives / ( $true_positives + $false_positives )
			: 0;

		// Calculate recall: TP / (TP + FN)
		$recall = ( $true_positives + $false_negatives ) > 0
			? $true_positives / ( $true_positives + $false_negatives )
			: 0;

		// Calculate F1 score: 2 * (precision * recall) / (precision + recall)
		$f1_score = ( $precision + $recall ) > 0
			? 2 * ( $precision * $recall ) / ( $precision + $recall )
			: 0;

		// Calculate accuracy: (TP + TN) / Total
		$accuracy = count( $suggestions ) > 0
			? ( $true_positives + $true_negatives ) / count( $suggestions )
			: 0;

		return array(
			'precision'       => round( $precision * 100, 2 ),
			'recall'          => round( $recall * 100, 2 ),
			'f1_score'        => round( $f1_score * 100, 2 ),
			'accuracy'        => round( $accuracy * 100, 2 ),
			'total_samples'   => count( $suggestions ),
			'true_positives'  => $true_positives,
			'false_positives' => $false_positives,
			'true_negatives'  => $true_negatives,
			'false_negatives' => $false_negatives,
		);
	}

	/**
	 * Get optimal weights for saga
	 *
	 * @param int $saga_id Saga ID
	 * @return array Current weights [feature_type => weight]
	 */
	public function getOptimalWeights( int $saga_id ): array {
		return $this->repository->getWeightsForSaga( $saga_id );
	}

	/**
	 * Predict accuracy improvement from more feedback
	 *
	 * Estimates how much accuracy could improve with additional samples
	 *
	 * @param int $saga_id Saga ID
	 * @return float Estimated improvement percentage
	 */
	public function predictAccuracyImprovement( int $saga_id ): float {
		$current_metrics  = $this->getAccuracyMetrics( $saga_id );
		$current_accuracy = $current_metrics['accuracy'];
		$samples          = $current_metrics['total_samples'];

		if ( $samples < self::MIN_SAMPLES ) {
			return 20.0; // High potential improvement
		}

		// Diminishing returns based on sample count
		// More samples = less potential improvement
		$potential_improvement = 100 - $current_accuracy;
		$learning_factor       = 1 / ( 1 + ( $samples / 20 ) );

		return round( $potential_improvement * $learning_factor, 2 );
	}

	/**
	 * Maybe update weights if enough new feedback
	 *
	 * @param int $saga_id Saga ID
	 * @return void
	 */
	private function maybeUpdateWeights( int $saga_id ): void {
		// Check if enough new feedback since last update
		$last_update = get_transient( "saga_learning_last_update_{$saga_id}" );

		if ( $last_update !== false ) {
			return; // Recently updated
		}

		$feedback_count = count( $this->repository->getFeedbackForSaga( $saga_id ) );

		if ( $feedback_count >= self::MIN_SAMPLES ) {
			try {
				$this->updateWeights( $saga_id );

				// Set cooldown (1 hour)
				set_transient( "saga_learning_last_update_{$saga_id}", time(), HOUR_IN_SECONDS );
			} catch ( \Exception $e ) {
				error_log(
					sprintf(
						'[SAGA][PREDICTIVE][ERROR] Failed to update weights: %s',
						$e->getMessage()
					)
				);
			}
		}
	}

	/**
	 * Reset learning for saga
	 *
	 * Clears all learned weights and feedback
	 *
	 * @param int $saga_id Saga ID
	 * @return bool Success
	 */
	public function resetLearning( int $saga_id ): bool {
		error_log( sprintf( '[SAGA][PREDICTIVE] Resetting learning data for saga %d', $saga_id ) );

		try {
			$this->repository->resetWeights( $saga_id );
			delete_transient( "saga_learning_last_update_{$saga_id}" );
			return true;
		} catch ( \Exception $e ) {
			error_log(
				sprintf(
					'[SAGA][PREDICTIVE][ERROR] Failed to reset learning: %s',
					$e->getMessage()
				)
			);
			return false;
		}
	}

	/**
	 * Get learning statistics
	 *
	 * @param int $saga_id Saga ID
	 * @return array Statistics array
	 */
	public function getLearningStatistics( int $saga_id ): array {
		$metrics     = $this->getAccuracyMetrics( $saga_id );
		$weights     = $this->getOptimalWeights( $saga_id );
		$improvement = $this->predictAccuracyImprovement( $saga_id );

		return array(
			'accuracy_metrics'      => $metrics,
			'feature_weights'       => $weights,
			'predicted_improvement' => $improvement,
			'is_learning_active'    => $metrics['total_samples'] >= self::MIN_SAMPLES,
			'samples_needed'        => max( 0, self::MIN_SAMPLES - $metrics['total_samples'] ),
		);
	}
}
