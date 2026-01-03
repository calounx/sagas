<?php
/**
 * Suggestion Repository
 *
 * Data access layer for relationship suggestions feature.
 * Handles CRUD operations for suggestions, features, feedback, and learning weights.
 * Uses WordPress $wpdb with proper table prefix support and prepared statements.
 *
 * @package SagaManager
 * @subpackage AI\PredictiveRelationships
 * @since 1.4.0
 */

declare(strict_types=1);

namespace SagaManager\AI\PredictiveRelationships;

use SagaManager\AI\PredictiveRelationships\Entities\RelationshipSuggestion;
use SagaManager\AI\PredictiveRelationships\Entities\SuggestionFeature;
use SagaManager\AI\PredictiveRelationships\Entities\SuggestionFeedback;
use SagaManager\AI\PredictiveRelationships\Entities\SuggestionStatus;
use SagaManager\AI\PredictiveRelationships\Entities\FeatureType;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Suggestion Repository
 *
 * WordPress database integration for predictive relationships.
 */
class SuggestionRepository {

	private string $suggestions_table;
	private string $features_table;
	private string $feedback_table;
	private string $weights_table;
	private int $cache_ttl = 300; // 5 minutes

	/**
	 * Constructor
	 */
	public function __construct() {
		global $wpdb;
		$this->suggestions_table = $wpdb->prefix . 'saga_relationship_suggestions';
		$this->features_table    = $wpdb->prefix . 'saga_suggestion_features';
		$this->feedback_table    = $wpdb->prefix . 'saga_suggestion_feedback';
		$this->weights_table     = $wpdb->prefix . 'saga_learning_weights';
	}

	// =========================================================================
	// RELATIONSHIP SUGGESTIONS
	// =========================================================================

	/**
	 * Create new suggestion
	 *
	 * @param RelationshipSuggestion $suggestion Suggestion object (id should be null)
	 * @return int Suggestion ID
	 * @throws \Exception If creation fails
	 */
	public function createSuggestion( RelationshipSuggestion $suggestion ): int {
		global $wpdb;

		$data = $suggestion->toArray();
		unset( $data['id'] );

		$result = $wpdb->insert( $this->suggestions_table, $data );

		if ( $result === false ) {
			error_log(
				sprintf(
					'[SAGA][PREDICTIVE][ERROR] Failed to create suggestion: %s',
					$wpdb->last_error
				)
			);
			throw new \Exception( 'Failed to create suggestion: ' . $wpdb->last_error );
		}

		$suggestion_id = $wpdb->insert_id;

		error_log(
			sprintf(
				'[SAGA][PREDICTIVE] Created suggestion #%d (%s, %.2f%% confidence)',
				$suggestion_id,
				$suggestion->suggested_type,
				$suggestion->confidence_score
			)
		);

		// Clear cache
		wp_cache_delete( "suggestion_{$suggestion_id}", 'saga' );

		return $suggestion_id;
	}

	/**
	 * Update existing suggestion
	 *
	 * @param RelationshipSuggestion $suggestion Suggestion with ID
	 * @return bool Success
	 * @throws \Exception If suggestion ID is null or update fails
	 */
	public function updateSuggestion( RelationshipSuggestion $suggestion ): bool {
		global $wpdb;

		if ( $suggestion->id === null ) {
			throw new \Exception( 'Cannot update suggestion without ID' );
		}

		$data          = $suggestion->toArray();
		$suggestion_id = $data['id'];
		unset( $data['id'] );

		$result = $wpdb->update(
			$this->suggestions_table,
			$data,
			array( 'id' => $suggestion_id ),
			null,
			array( '%d' )
		);

		if ( $result === false ) {
			error_log(
				sprintf(
					'[SAGA][PREDICTIVE][ERROR] Failed to update suggestion #%d: %s',
					$suggestion_id,
					$wpdb->last_error
				)
			);
			throw new \Exception( 'Failed to update suggestion: ' . $wpdb->last_error );
		}

		// Clear cache
		wp_cache_delete( "suggestion_{$suggestion_id}", 'saga' );

		return true;
	}

	/**
	 * Find suggestion by ID
	 *
	 * @param int $id Suggestion ID
	 * @return RelationshipSuggestion|null
	 */
	public function findById( int $id ): ?RelationshipSuggestion {
		global $wpdb;

		// Check cache
		$cache_key = "suggestion_{$id}";
		$cached    = wp_cache_get( $cache_key, 'saga' );

		if ( $cached !== false ) {
			return $cached;
		}

		$query = $wpdb->prepare(
			"SELECT * FROM {$this->suggestions_table} WHERE id = %d",
			$id
		);

		$row = $wpdb->get_row( $query, ARRAY_A );

		if ( ! $row ) {
			return null;
		}

		$suggestion = RelationshipSuggestion::fromArray( $row );

		// Cache result
		wp_cache_set( $cache_key, $suggestion, 'saga', $this->cache_ttl );

		return $suggestion;
	}

	/**
	 * Find pending suggestions for saga
	 *
	 * @param int $saga_id Saga ID
	 * @param int $limit Maximum results
	 * @return array Array of RelationshipSuggestion objects
	 */
	public function findPendingSuggestions( int $saga_id, int $limit = 50 ): array {
		global $wpdb;

		$query = $wpdb->prepare(
			"SELECT * FROM {$this->suggestions_table}
             WHERE saga_id = %d AND status = 'pending'
             ORDER BY priority_score DESC, confidence_score DESC
             LIMIT %d",
			$saga_id,
			$limit
		);

		$rows = $wpdb->get_results( $query, ARRAY_A );

		return array_map( fn( $row ) => RelationshipSuggestion::fromArray( $row ), $rows );
	}

	/**
	 * Find suggestions by entities
	 *
	 * @param int $entity1_id First entity ID
	 * @param int $entity2_id Second entity ID
	 * @return array Array of RelationshipSuggestion objects
	 */
	public function findByEntities( int $entity1_id, int $entity2_id ): array {
		global $wpdb;

		$query = $wpdb->prepare(
			"SELECT * FROM {$this->suggestions_table}
             WHERE (source_entity_id = %d AND target_entity_id = %d)
                OR (source_entity_id = %d AND target_entity_id = %d)",
			$entity1_id,
			$entity2_id,
			$entity2_id,
			$entity1_id
		);

		$rows = $wpdb->get_results( $query, ARRAY_A );

		return array_map( fn( $row ) => RelationshipSuggestion::fromArray( $row ), $rows );
	}

	/**
	 * Get actioned suggestions for saga
	 *
	 * @param int $saga_id Saga ID
	 * @return array Array of RelationshipSuggestion objects
	 */
	public function getActionedSuggestions( int $saga_id ): array {
		global $wpdb;

		$query = $wpdb->prepare(
			"SELECT * FROM {$this->suggestions_table}
             WHERE saga_id = %d AND status != 'pending'
             ORDER BY updated_at DESC",
			$saga_id
		);

		$rows = $wpdb->get_results( $query, ARRAY_A );

		return array_map( fn( $row ) => RelationshipSuggestion::fromArray( $row ), $rows );
	}

	/**
	 * Count pending suggestions
	 *
	 * @param int $saga_id Saga ID
	 * @return int Count
	 */
	public function countPendingSuggestions( int $saga_id ): int {
		global $wpdb;

		$query = $wpdb->prepare(
			"SELECT COUNT(*) FROM {$this->suggestions_table}
             WHERE saga_id = %d AND status = 'pending'",
			$saga_id
		);

		return (int) $wpdb->get_var( $query );
	}

	/**
	 * Get acceptance rate for saga
	 *
	 * @param int $saga_id Saga ID
	 * @return float Acceptance rate 0-100
	 */
	public function getAcceptanceRate( int $saga_id ): float {
		global $wpdb;

		$total = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$this->suggestions_table}
             WHERE saga_id = %d AND status != 'pending'",
				$saga_id
			)
		);

		if ( ! $total ) {
			return 0.0;
		}

		$accepted = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$this->suggestions_table}
             WHERE saga_id = %d AND status IN ('accepted', 'modified', 'auto_accepted')",
				$saga_id
			)
		);

		return round( ( (int) $accepted / (int) $total ) * 100, 2 );
	}

	// =========================================================================
	// SUGGESTION FEATURES
	// =========================================================================

	/**
	 * Save features for suggestion
	 *
	 * @param int   $suggestion_id Suggestion ID
	 * @param array $features Array of SuggestionFeature objects
	 * @return bool Success
	 * @throws \Exception If save fails
	 */
	public function saveFeatures( int $suggestion_id, array $features ): bool {
		global $wpdb;

		$wpdb->query( 'START TRANSACTION' );

		try {
			foreach ( $features as $feature ) {
				if ( ! ( $feature instanceof SuggestionFeature ) ) {
					continue;
				}

				$data = $feature->toArray();
				unset( $data['id'] );
				$data['suggestion_id'] = $suggestion_id;

				$result = $wpdb->insert( $this->features_table, $data );

				if ( $result === false ) {
					throw new \Exception( 'Failed to insert feature: ' . $wpdb->last_error );
				}
			}

			$wpdb->query( 'COMMIT' );

			error_log(
				sprintf(
					'[SAGA][PREDICTIVE] Saved %d features for suggestion #%d',
					count( $features ),
					$suggestion_id
				)
			);

			return true;

		} catch ( \Exception $e ) {
			$wpdb->query( 'ROLLBACK' );
			error_log(
				sprintf(
					'[SAGA][PREDICTIVE][ERROR] Failed to save features: %s',
					$e->getMessage()
				)
			);
			throw $e;
		}
	}

	/**
	 * Get features for suggestion
	 *
	 * @param int $suggestion_id Suggestion ID
	 * @return array Array of SuggestionFeature objects
	 */
	public function getFeatures( int $suggestion_id ): array {
		global $wpdb;

		$query = $wpdb->prepare(
			"SELECT * FROM {$this->features_table}
             WHERE suggestion_id = %d
             ORDER BY feature_value DESC",
			$suggestion_id
		);

		$rows = $wpdb->get_results( $query, ARRAY_A );

		return array_map( fn( $row ) => SuggestionFeature::fromArray( $row ), $rows );
	}

	// =========================================================================
	// SUGGESTION FEEDBACK
	// =========================================================================

	/**
	 * Save feedback
	 *
	 * @param SuggestionFeedback $feedback Feedback object
	 * @return int Feedback ID
	 * @throws \Exception If save fails
	 */
	public function saveFeedback( SuggestionFeedback $feedback ): int {
		global $wpdb;

		$data = $feedback->toArray();
		unset( $data['id'] );

		$result = $wpdb->insert( $this->feedback_table, $data );

		if ( $result === false ) {
			error_log(
				sprintf(
					'[SAGA][PREDICTIVE][ERROR] Failed to save feedback: %s',
					$wpdb->last_error
				)
			);
			throw new \Exception( 'Failed to save feedback: ' . $wpdb->last_error );
		}

		$feedback_id = $wpdb->insert_id;

		error_log(
			sprintf(
				'[SAGA][PREDICTIVE] Saved feedback #%d for suggestion #%d',
				$feedback_id,
				$feedback->suggestion_id
			)
		);

		return $feedback_id;
	}

	/**
	 * Get feedback for saga
	 *
	 * @param int $saga_id Saga ID
	 * @return array Array of SuggestionFeedback objects
	 */
	public function getFeedbackForSaga( int $saga_id ): array {
		global $wpdb;

		$query = $wpdb->prepare(
			"SELECT f.*
             FROM {$this->feedback_table} f
             INNER JOIN {$this->suggestions_table} s ON f.suggestion_id = s.id
             WHERE s.saga_id = %d
             ORDER BY f.created_at DESC",
			$saga_id
		);

		$rows = $wpdb->get_results( $query, ARRAY_A );

		return array_map( fn( $row ) => SuggestionFeedback::fromArray( $row ), $rows );
	}

	// =========================================================================
	// LEARNING WEIGHTS
	// =========================================================================

	/**
	 * Get weights for saga
	 *
	 * @param int $saga_id Saga ID
	 * @return array Weights [feature_type => weight]
	 */
	public function getWeightsForSaga( int $saga_id ): array {
		global $wpdb;

		$cache_key = "weights_saga_{$saga_id}";
		$cached    = wp_cache_get( $cache_key, 'saga' );

		if ( $cached !== false ) {
			return $cached;
		}

		$query = $wpdb->prepare(
			"SELECT feature_type, weight FROM {$this->weights_table}
             WHERE saga_id = %d AND relationship_type IS NULL",
			$saga_id
		);

		$rows = $wpdb->get_results( $query, ARRAY_A );

		$weights = array();

		// Start with defaults
		foreach ( FeatureType::cases() as $feature_type ) {
			$weights[ $feature_type->value ] = $feature_type->getDefaultWeight();
		}

		// Override with learned weights
		foreach ( $rows as $row ) {
			$weights[ $row['feature_type'] ] = (float) $row['weight'];
		}

		wp_cache_set( $cache_key, $weights, 'saga', $this->cache_ttl );

		return $weights;
	}

	/**
	 * Update weight for feature type
	 *
	 * @param int    $saga_id Saga ID
	 * @param string $feature_type Feature type
	 * @param float  $weight New weight
	 * @param int    $samples_count Number of samples
	 * @return bool Success
	 * @throws \Exception If update fails
	 */
	public function updateWeight(
		int $saga_id,
		string $feature_type,
		float $weight,
		int $samples_count = 0
	): bool {
		global $wpdb;

		// Try to update existing
		$exists = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT id FROM {$this->weights_table}
             WHERE saga_id = %d AND feature_type = %s AND relationship_type IS NULL",
				$saga_id,
				$feature_type
			)
		);

		$data = array(
			'weight'        => $weight,
			'samples_count' => $samples_count,
			'last_updated'  => current_time( 'mysql' ),
		);

		if ( $exists ) {
			// Update
			$result = $wpdb->update(
				$this->weights_table,
				$data,
				array(
					'saga_id'           => $saga_id,
					'feature_type'      => $feature_type,
					'relationship_type' => null,
				),
				array( '%f', '%d', '%s' ),
				array( '%d', '%s', 'IS NULL' )
			);
		} else {
			// Insert
			$data['saga_id']           = $saga_id;
			$data['feature_type']      = $feature_type;
			$data['relationship_type'] = null;

			$result = $wpdb->insert( $this->weights_table, $data );
		}

		if ( $result === false ) {
			error_log(
				sprintf(
					'[SAGA][PREDICTIVE][ERROR] Failed to update weight: %s',
					$wpdb->last_error
				)
			);
			throw new \Exception( 'Failed to update weight: ' . $wpdb->last_error );
		}

		// Clear cache
		wp_cache_delete( "weights_saga_{$saga_id}", 'saga' );

		return true;
	}

	/**
	 * Update accuracy for saga
	 *
	 * @param int   $saga_id Saga ID
	 * @param float $accuracy Accuracy percentage
	 * @return bool Success
	 */
	public function updateAccuracy( int $saga_id, float $accuracy ): bool {
		global $wpdb;

		// Update accuracy score for all weights in this saga
		$result = $wpdb->update(
			$this->weights_table,
			array( 'accuracy_score' => $accuracy ),
			array( 'saga_id' => $saga_id ),
			array( '%f' ),
			array( '%d' )
		);

		return $result !== false;
	}

	/**
	 * Reset weights for saga
	 *
	 * @param int $saga_id Saga ID
	 * @return bool Success
	 */
	public function resetWeights( int $saga_id ): bool {
		global $wpdb;

		$result = $wpdb->delete(
			$this->weights_table,
			array( 'saga_id' => $saga_id ),
			array( '%d' )
		);

		// Clear cache
		wp_cache_delete( "weights_saga_{$saga_id}", 'saga' );

		return $result !== false;
	}

	// =========================================================================
	// BATCH OPERATIONS
	// =========================================================================

	/**
	 * Batch create suggestions with features
	 *
	 * @param array $suggestions Array of [suggestion, features] pairs
	 * @return array Created suggestion IDs
	 * @throws \Exception If batch creation fails
	 */
	public function batchCreateSuggestions( array $suggestions ): array {
		global $wpdb;

		$wpdb->query( 'START TRANSACTION' );

		try {
			$created_ids = array();

			foreach ( $suggestions as $data ) {
				$suggestion = $data['suggestion'] ?? null;
				$features   = $data['features'] ?? array();

				if ( ! ( $suggestion instanceof RelationshipSuggestion ) ) {
					continue;
				}

				// Create suggestion
				$suggestion_id = $this->createSuggestion( $suggestion );
				$created_ids[] = $suggestion_id;

				// Save features
				if ( ! empty( $features ) ) {
					$this->saveFeatures( $suggestion_id, $features );
				}
			}

			$wpdb->query( 'COMMIT' );

			error_log(
				sprintf(
					'[SAGA][PREDICTIVE] Batch created %d suggestions',
					count( $created_ids )
				)
			);

			return $created_ids;

		} catch ( \Exception $e ) {
			$wpdb->query( 'ROLLBACK' );
			error_log(
				sprintf(
					'[SAGA][PREDICTIVE][ERROR] Batch creation failed: %s',
					$e->getMessage()
				)
			);
			throw $e;
		}
	}

	// =========================================================================
	// STATISTICS
	// =========================================================================

	/**
	 * Get suggestion statistics for saga
	 *
	 * @param int $saga_id Saga ID
	 * @return array Statistics array
	 */
	public function getSuggestionStatistics( int $saga_id ): array {
		global $wpdb;

		$stats = array(
			'total'           => 0,
			'pending'         => 0,
			'accepted'        => 0,
			'rejected'        => 0,
			'modified'        => 0,
			'auto_accepted'   => 0,
			'avg_confidence'  => 0,
			'acceptance_rate' => 0,
		);

		$query = $wpdb->prepare(
			"SELECT status, COUNT(*) as count, AVG(confidence_score) as avg_conf
             FROM {$this->suggestions_table}
             WHERE saga_id = %d
             GROUP BY status",
			$saga_id
		);

		$rows = $wpdb->get_results( $query, ARRAY_A );

		$total = 0;
		foreach ( $rows as $row ) {
			$count                   = (int) $row['count'];
			$stats[ $row['status'] ] = $count;
			$total                  += $count;
		}

		$stats['total'] = $total;

		// Get average confidence
		$stats['avg_confidence'] = round(
			(float) $wpdb->get_var(
				$wpdb->prepare(
					"SELECT AVG(confidence_score) FROM {$this->suggestions_table} WHERE saga_id = %d",
					$saga_id
				)
			),
			2
		);

		// Calculate acceptance rate
		$stats['acceptance_rate'] = $this->getAcceptanceRate( $saga_id );

		return $stats;
	}
}
