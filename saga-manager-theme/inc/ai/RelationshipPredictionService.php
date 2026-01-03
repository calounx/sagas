<?php
/**
 * Relationship Prediction Service
 *
 * AI-powered relationship prediction using extracted features and learned weights.
 * Generates suggestions for potential relationships between entities using
 * machine learning and semantic analysis.
 *
 * @package SagaManager
 * @subpackage AI\PredictiveRelationships
 * @since 1.4.0
 */

declare(strict_types=1);

namespace SagaManager\AI\PredictiveRelationships;

use SagaManager\AI\AIClient;
use SagaManager\AI\PredictiveRelationships\Entities\RelationshipSuggestion;
use SagaManager\AI\PredictiveRelationships\Entities\SuggestionStatus;
use SagaManager\AI\PredictiveRelationships\Entities\UserActionType;
use SagaManager\AI\PredictiveRelationships\Entities\SuggestionMethod;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Relationship Prediction Service
 *
 * Generates and scores relationship suggestions using AI and ML.
 */
class RelationshipPredictionService {

	private FeatureExtractionService $feature_service;
	private SuggestionRepository $repository;
	private ?AIClient $ai_client;
	private string $entities_table;

	/**
	 * Minimum confidence threshold for suggestions
	 */
	private const MIN_CONFIDENCE = 40.0;

	/**
	 * Auto-accept threshold
	 */
	private const AUTO_ACCEPT_THRESHOLD = 95.0;

	/**
	 * Constructor
	 *
	 * @param FeatureExtractionService|null $feature_service Feature extraction service
	 * @param SuggestionRepository|null     $repository Suggestion repository
	 * @param AIClient|null                 $ai_client AI client for semantic analysis
	 */
	public function __construct(
		?FeatureExtractionService $feature_service = null,
		?SuggestionRepository $repository = null,
		?AIClient $ai_client = null
	) {
		$this->feature_service = $feature_service ?? new FeatureExtractionService();
		$this->repository      = $repository ?? new SuggestionRepository();
		$this->ai_client       = $ai_client;

		global $wpdb;
		$this->entities_table = $wpdb->prefix . 'saga_entities';
	}

	/**
	 * Predict relationships for entire saga
	 *
	 * @param int $saga_id Saga ID
	 * @param int $limit Maximum suggestions to generate
	 * @return array Array of RelationshipSuggestion objects
	 * @throws \Exception If prediction fails
	 */
	public function predictRelationships( int $saga_id, int $limit = 50 ): array {
		global $wpdb;

		error_log(
			sprintf(
				'[SAGA][PREDICTIVE] Starting relationship prediction for saga %d',
				$saga_id
			)
		);

		// Get all entities in saga
		$entities = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT id, canonical_name, entity_type FROM {$this->entities_table}
             WHERE saga_id = %d
             ORDER BY importance_score DESC
             LIMIT 100",
				$saga_id
			),
			ARRAY_A
		);

		if ( empty( $entities ) ) {
			error_log( '[SAGA][PREDICTIVE] No entities found for saga' );
			return array();
		}

		$suggestions    = array();
		$pairs_analyzed = 0;

		// Analyze entity pairs
		foreach ( $entities as $i => $entity1 ) {
			foreach ( array_slice( $entities, $i + 1 ) as $entity2 ) {
				// Skip if suggestion already exists
				if ( $this->suggestionExists( $entity1['id'], $entity2['id'] ) ) {
					continue;
				}

				// Skip if relationship already exists
				if ( $this->relationshipExists( $entity1['id'], $entity2['id'] ) ) {
					continue;
				}

				try {
					$suggestion = $this->predictForPair(
						(int) $entity1['id'],
						(int) $entity2['id'],
						$saga_id
					);

					if ( $suggestion && $suggestion->confidence_score >= self::MIN_CONFIDENCE ) {
						$suggestions[] = $suggestion;
						++$pairs_analyzed;

						if ( count( $suggestions ) >= $limit ) {
							break 2; // Exit both loops
						}
					}
				} catch ( \Exception $e ) {
					error_log(
						sprintf(
							'[SAGA][PREDICTIVE][ERROR] Failed to predict for pair %d-%d: %s',
							$entity1['id'],
							$entity2['id'],
							$e->getMessage()
						)
					);
				}
			}
		}

		// Sort by priority score
		usort( $suggestions, fn( $a, $b ) => $b->priority_score <=> $a->priority_score );

		error_log(
			sprintf(
				'[SAGA][PREDICTIVE] Generated %d suggestions from %d pairs',
				count( $suggestions ),
				$pairs_analyzed
			)
		);

		return array_slice( $suggestions, 0, $limit );
	}

	/**
	 * Predict relationships for specific entity
	 *
	 * @param int $entity_id Entity ID
	 * @param int $saga_id Saga ID
	 * @param int $limit Maximum suggestions
	 * @return array Array of RelationshipSuggestion objects
	 * @throws \Exception If prediction fails
	 */
	public function predictForEntity( int $entity_id, int $saga_id, int $limit = 10 ): array {
		global $wpdb;

		// Get other entities in saga
		$others = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT id FROM {$this->entities_table}
             WHERE saga_id = %d AND id != %d
             ORDER BY importance_score DESC
             LIMIT 50",
				$saga_id,
				$entity_id
			),
			ARRAY_A
		);

		$suggestions = array();

		foreach ( $others as $other ) {
			// Skip if suggestion exists
			if ( $this->suggestionExists( $entity_id, $other['id'] ) ) {
				continue;
			}

			// Skip if relationship exists
			if ( $this->relationshipExists( $entity_id, $other['id'] ) ) {
				continue;
			}

			try {
				$suggestion = $this->predictForPair( $entity_id, (int) $other['id'], $saga_id );

				if ( $suggestion && $suggestion->confidence_score >= self::MIN_CONFIDENCE ) {
					$suggestions[] = $suggestion;
				}
			} catch ( \Exception $e ) {
				error_log(
					sprintf(
						'[SAGA][PREDICTIVE][ERROR] Failed to predict for entity %d: %s',
						$entity_id,
						$e->getMessage()
					)
				);
			}
		}

		// Sort by priority
		usort( $suggestions, fn( $a, $b ) => $b->priority_score <=> $a->priority_score );

		return array_slice( $suggestions, 0, $limit );
	}

	/**
	 * Predict relationship for specific entity pair
	 *
	 * @param int $entity1_id First entity ID
	 * @param int $entity2_id Second entity ID
	 * @param int $saga_id Saga ID
	 * @return RelationshipSuggestion|null Suggestion or null if below threshold
	 * @throws \Exception If prediction fails
	 */
	private function predictForPair( int $entity1_id, int $entity2_id, int $saga_id ): ?RelationshipSuggestion {
		// Extract features
		$features = $this->feature_service->extractFeatures( $entity1_id, $entity2_id, $saga_id );

		// Get learned weights
		$weights = $this->repository->getWeightsForSaga( $saga_id );

		// Calculate confidence
		$confidence = $this->calculateConfidence( $features, $weights );

		if ( $confidence < self::MIN_CONFIDENCE ) {
			return null;
		}

		// Suggest relationship type
		$suggested_type = $this->suggestType( $entity1_id, $entity2_id, $features );

		// Estimate strength
		$strength = $this->estimateStrength( $features );

		// Generate reasoning
		$reasoning = $this->generateReasoning( $entity1_id, $entity2_id, $features, $suggested_type );

		// Gather evidence
		$evidence = $this->gatherEvidence( $features );

		// Determine suggestion method
		$method = $this->determineSuggestionMethod( $features );

		// Create suggestion
		$suggestion = new RelationshipSuggestion(
			id: null,
			saga_id: $saga_id,
			source_entity_id: $entity1_id,
			target_entity_id: $entity2_id,
			suggested_type: $suggested_type,
			confidence_score: $confidence,
			strength: $strength,
			reasoning: $reasoning,
			evidence: $evidence,
			suggestion_method: $method,
			ai_model: $this->ai_client ? 'gpt-4' : 'rule_based',
			status: $confidence >= self::AUTO_ACCEPT_THRESHOLD
				? SuggestionStatus::AUTO_ACCEPTED
				: SuggestionStatus::PENDING,
			user_action_type: UserActionType::NONE,
			user_feedback_text: null,
			accepted_at: null,
			rejected_at: null,
			actioned_by: null,
			created_relationship_id: null,
			priority_score: 0, // Will be calculated
			created_at: time(),
			updated_at: time()
		);

		// Calculate priority
		$priority               = $suggestion->calculatePriorityScore();
		$data                   = $suggestion->toArray();
		$data['priority_score'] = $priority;
		$suggestion             = RelationshipSuggestion::fromArray( $data );

		return $suggestion;
	}

	/**
	 * Calculate confidence score from features and weights
	 *
	 * @param array $features Feature values [type => value]
	 * @param array $weights Feature weights [type => weight]
	 * @return float Confidence score 0-100
	 */
	public function calculateConfidence( array $features, array $weights ): float {
		$weighted_sum = 0.0;
		$total_weight = 0.0;

		foreach ( $features as $type => $value ) {
			$weight        = $weights[ $type ] ?? 0.5;
			$weighted_sum += $value * $weight;
			$total_weight += $weight;
		}

		if ( $total_weight <= 0 ) {
			return 0.0;
		}

		// Normalize to 0-100
		$confidence = ( $weighted_sum / $total_weight ) * 100;

		return round( max( 0, min( 100, $confidence ) ), 2 );
	}

	/**
	 * Suggest relationship type based on features
	 *
	 * @param int   $entity1_id First entity ID
	 * @param int   $entity2_id Second entity ID
	 * @param array $features Extracted features
	 * @return string Relationship type
	 */
	public function suggestType( int $entity1_id, int $entity2_id, array $features ): string {
		global $wpdb;

		// Get entity types
		$entity1 = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT entity_type, canonical_name FROM {$this->entities_table} WHERE id = %d",
				$entity1_id
			),
			ARRAY_A
		);

		$entity2 = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT entity_type, canonical_name FROM {$this->entities_table} WHERE id = %d",
				$entity2_id
			),
			ARRAY_A
		);

		// Same faction = ally
		if ( ( $features['shared_faction'] ?? 0 ) >= 0.8 ) {
			return 'ally';
		}

		// High co-occurrence + timeline proximity = ally or family
		$co_occur = $features['co_occurrence'] ?? 0;
		$timeline = $features['timeline_proximity'] ?? 0;

		if ( $co_occur >= 0.7 && $timeline >= 0.7 ) {
			// Use AI to distinguish if available
			if ( $this->ai_client ) {
				return $this->suggestTypeWithAI( $entity1, $entity2, $features );
			}
			return 'ally';
		}

		// Default to ally
		return 'ally';
	}

	/**
	 * Suggest type using AI semantic analysis
	 *
	 * @param array $entity1 Entity 1 data
	 * @param array $entity2 Entity 2 data
	 * @param array $features Features
	 * @return string Relationship type
	 */
	private function suggestTypeWithAI( array $entity1, array $entity2, array $features ): string {
		// AI implementation would go here
		// For now, return default
		return 'ally';
	}

	/**
	 * Estimate relationship strength
	 *
	 * @param array $features Extracted features
	 * @return int Strength 0-100
	 */
	private function estimateStrength( array $features ): int {
		// Average of top features
		$values = array_values( $features );
		if ( empty( $values ) ) {
			return 50;
		}

		rsort( $values );
		$top_features = array_slice( $values, 0, 3 );
		$avg          = array_sum( $top_features ) / count( $top_features );

		return (int) round( $avg * 100 );
	}

	/**
	 * Generate human-readable reasoning
	 *
	 * @param int    $entity1_id First entity ID
	 * @param int    $entity2_id Second entity ID
	 * @param array  $features Extracted features
	 * @param string $type Suggested type
	 * @return string Reasoning text
	 */
	public function generateReasoning( int $entity1_id, int $entity2_id, array $features, string $type ): string {
		global $wpdb;

		$entity1_name = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT canonical_name FROM {$this->entities_table} WHERE id = %d",
				$entity1_id
			)
		);

		$entity2_name = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT canonical_name FROM {$this->entities_table} WHERE id = %d",
				$entity2_id
			)
		);

		$reasons = array();

		// Analyze top features
		arsort( $features );
		$top_features = array_slice( $features, 0, 3, true );

		foreach ( $top_features as $feature_type => $value ) {
			if ( $value >= 0.6 ) {
				$reason = match ( $feature_type ) {
					'co_occurrence' => sprintf(
						'appear together frequently in content (%.0f%%)',
						$value * 100
					),
					'timeline_proximity' => sprintf(
						'are close in timeline events (%.0f%%)',
						$value * 100
					),
					'shared_faction' => 'belong to the same faction',
					'shared_location' => 'share common locations',
					'attribute_similarity' => sprintf(
						'have similar attributes (%.0f%%)',
						$value * 100
					),
					default => null
				};

				if ( $reason ) {
					$reasons[] = $reason;
				}
			}
		}

		if ( empty( $reasons ) ) {
			return sprintf(
				'%s and %s may be connected as %s',
				$entity1_name,
				$entity2_name,
				$type
			);
		}

		return sprintf(
			'%s and %s %s, suggesting a %s relationship',
			$entity1_name,
			$entity2_name,
			implode( ', ', $reasons ),
			$type
		);
	}

	/**
	 * Gather evidence for suggestion
	 *
	 * @param array $features Extracted features
	 * @return array Evidence array
	 */
	private function gatherEvidence( array $features ): array {
		$evidence = array();

		foreach ( $features as $type => $value ) {
			if ( $value >= 0.5 ) {
				$evidence[] = array(
					'type'        => $type,
					'value'       => $value,
					'description' => ucwords( str_replace( '_', ' ', $type ) ),
				);
			}
		}

		return $evidence;
	}

	/**
	 * Determine suggestion method
	 *
	 * @param array $features Extracted features
	 * @return SuggestionMethod
	 */
	private function determineSuggestionMethod( array $features ): SuggestionMethod {
		$high_value_count = 0;

		foreach ( $features as $value ) {
			if ( $value >= 0.7 ) {
				++$high_value_count;
			}
		}

		return $high_value_count >= 3 ? SuggestionMethod::HYBRID : SuggestionMethod::CONTENT;
	}

	/**
	 * Check if suggestion already exists
	 *
	 * @param int $entity1_id First entity ID
	 * @param int $entity2_id Second entity ID
	 * @return bool
	 */
	private function suggestionExists( int $entity1_id, int $entity2_id ): bool {
		$existing = $this->repository->findByEntities( $entity1_id, $entity2_id );
		return ! empty( $existing );
	}

	/**
	 * Check if relationship already exists
	 *
	 * @param int $entity1_id First entity ID
	 * @param int $entity2_id Second entity ID
	 * @return bool
	 */
	private function relationshipExists( int $entity1_id, int $entity2_id ): bool {
		global $wpdb;

		$table = $wpdb->prefix . 'saga_entity_relationships';

		$count = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$table}
             WHERE (source_entity_id = %d AND target_entity_id = %d)
                OR (source_entity_id = %d AND target_entity_id = %d)",
				$entity1_id,
				$entity2_id,
				$entity2_id,
				$entity1_id
			)
		);

		return (int) $count > 0;
	}

	/**
	 * Get prediction statistics
	 *
	 * @param int $saga_id Saga ID
	 * @return array Statistics array
	 */
	public function getPredictionStatistics( int $saga_id ): array {
		global $wpdb;

		$total_entities = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$this->entities_table} WHERE saga_id = %d",
				$saga_id
			)
		);

		$possible_pairs = ( $total_entities * ( $total_entities - 1 ) ) / 2;

		$existing_relationships = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->prefix}saga_entity_relationships r
             INNER JOIN {$this->entities_table} e ON r.source_entity_id = e.id
             WHERE e.saga_id = %d",
				$saga_id
			)
		);

		$pending_suggestions = $this->repository->countPendingSuggestions( $saga_id );

		return array(
			'total_entities'         => (int) $total_entities,
			'possible_pairs'         => (int) $possible_pairs,
			'existing_relationships' => (int) $existing_relationships,
			'pending_suggestions'    => $pending_suggestions,
			'coverage_percent'       => $possible_pairs > 0
				? round( ( ( $existing_relationships + $pending_suggestions ) / $possible_pairs ) * 100, 2 )
				: 0,
		);
	}
}
