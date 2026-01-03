<?php
declare(strict_types=1);

/**
 * AJAX endpoints for Relationship Suggestions
 *
 * All endpoints require nonce verification and edit_posts capability.
 */

use SagaManager\AI\SuggestionBackgroundProcessor;
use SagaManager\AI\Services\RelationshipPredictionService;
use SagaManager\AI\Services\SuggestionRepository;
use SagaManager\AI\Services\LearningService;
use SagaManager\AI\Services\FeatureExtractionService;
use SagaManager\AI\ValueObjects\SuggestionFeedback;

/**
 * Initialize AJAX endpoints
 */
function saga_init_suggestions_ajax(): void {
	// Generation endpoints
	add_action( 'wp_ajax_saga_generate_suggestions', 'saga_ajax_generate_suggestions' );
	add_action( 'wp_ajax_saga_get_suggestion_progress', 'saga_ajax_get_suggestion_progress' );

	// Suggestion management
	add_action( 'wp_ajax_saga_load_suggestions', 'saga_ajax_load_suggestions' );
	add_action( 'wp_ajax_saga_accept_suggestion', 'saga_ajax_accept_suggestion' );
	add_action( 'wp_ajax_saga_reject_suggestion', 'saga_ajax_reject_suggestion' );
	add_action( 'wp_ajax_saga_modify_suggestion', 'saga_ajax_modify_suggestion' );
	add_action( 'wp_ajax_saga_dismiss_suggestion', 'saga_ajax_dismiss_suggestion' );

	// Bulk operations
	add_action( 'wp_ajax_saga_bulk_accept_suggestions', 'saga_ajax_bulk_accept_suggestions' );
	add_action( 'wp_ajax_saga_bulk_reject_suggestions', 'saga_ajax_bulk_reject_suggestions' );

	// Relationship creation
	add_action( 'wp_ajax_saga_create_relationship_from_suggestion', 'saga_ajax_create_relationship_from_suggestion' );

	// Details and analytics
	add_action( 'wp_ajax_saga_get_suggestion_details', 'saga_ajax_get_suggestion_details' );
	add_action( 'wp_ajax_saga_get_learning_stats', 'saga_ajax_get_learning_stats' );
	add_action( 'wp_ajax_saga_get_suggestion_analytics', 'saga_ajax_get_suggestion_analytics' );

	// Learning management
	add_action( 'wp_ajax_saga_trigger_learning_update', 'saga_ajax_trigger_learning_update' );
	add_action( 'wp_ajax_saga_reset_learning', 'saga_ajax_reset_learning' );
}
add_action( 'init', 'saga_init_suggestions_ajax' );

/**
 * Verify AJAX request security
 */
function saga_verify_suggestions_ajax(): bool {
	// Check nonce
	if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( $_POST['nonce'], 'saga_suggestions_nonce' ) ) {
		wp_send_json_error( array( 'message' => 'Invalid security token' ), 403 );
		return false;
	}

	// Check capability
	if ( ! current_user_can( 'edit_posts' ) ) {
		wp_send_json_error( array( 'message' => 'Insufficient permissions' ), 403 );
		return false;
	}

	return true;
}

/**
 * Get service instances
 */
function saga_get_suggestion_services(): array {
	global $wpdb;

	$repository          = new SuggestionRepository( $wpdb );
	$featureService      = new FeatureExtractionService( $wpdb );
	$predictionService   = new RelationshipPredictionService( $featureService, $repository );
	$learningService     = new LearningService( $repository );
	$backgroundProcessor = new SuggestionBackgroundProcessor( $predictionService, $repository );

	return array(
		'repository'          => $repository,
		'featureService'      => $featureService,
		'predictionService'   => $predictionService,
		'learningService'     => $learningService,
		'backgroundProcessor' => $backgroundProcessor,
	);
}

/**
 * Generate suggestions for a saga (start background job)
 */
function saga_ajax_generate_suggestions(): void {
	if ( ! saga_verify_suggestions_ajax() ) {
		return;
	}

	$saga_id = absint( $_POST['saga_id'] ?? 0 );
	if ( ! $saga_id ) {
		wp_send_json_error( array( 'message' => 'Invalid saga ID' ) );
		return;
	}

	try {
		$services = saga_get_suggestion_services();
		$success  = $services['backgroundProcessor']->scheduleGenerationJob( $saga_id );

		if ( $success ) {
			wp_send_json_success(
				array(
					'message' => 'Background job scheduled successfully',
					'saga_id' => $saga_id,
				)
			);
		} else {
			wp_send_json_error( array( 'message' => 'Failed to schedule job (may already be running)' ) );
		}
	} catch ( \Exception $e ) {
		error_log( '[SAGA][PREDICTIVE][AJAX] Generate error: ' . $e->getMessage() );
		wp_send_json_error( array( 'message' => 'Generation failed: ' . $e->getMessage() ) );
	}
}

/**
 * Get suggestion generation progress
 */
function saga_ajax_get_suggestion_progress(): void {
	if ( ! saga_verify_suggestions_ajax() ) {
		return;
	}

	$saga_id = absint( $_POST['saga_id'] ?? 0 );
	if ( ! $saga_id ) {
		wp_send_json_error( array( 'message' => 'Invalid saga ID' ) );
		return;
	}

	try {
		$services = saga_get_suggestion_services();
		$progress = $services['backgroundProcessor']->getProgress( $saga_id );

		if ( $progress === null ) {
			wp_send_json_success(
				array(
					'status'   => 'idle',
					'progress' => 0,
				)
			);
		} else {
			wp_send_json_success( $progress );
		}
	} catch ( \Exception $e ) {
		error_log( '[SAGA][PREDICTIVE][AJAX] Progress error: ' . $e->getMessage() );
		wp_send_json_error( array( 'message' => 'Failed to get progress' ) );
	}
}

/**
 * Load suggestions with pagination and filtering
 */
function saga_ajax_load_suggestions(): void {
	if ( ! saga_verify_suggestions_ajax() ) {
		return;
	}

	$saga_id           = absint( $_POST['saga_id'] ?? 0 );
	$page              = absint( $_POST['page'] ?? 1 );
	$per_page          = absint( $_POST['per_page'] ?? 25 );
	$status            = sanitize_key( $_POST['status'] ?? 'pending' );
	$min_confidence    = floatval( $_POST['min_confidence'] ?? 0.0 );
	$relationship_type = sanitize_text_field( $_POST['relationship_type'] ?? '' );
	$sort_by           = sanitize_key( $_POST['sort_by'] ?? 'confidence' );
	$sort_order        = sanitize_key( $_POST['sort_order'] ?? 'DESC' );

	if ( ! $saga_id ) {
		wp_send_json_error( array( 'message' => 'Invalid saga ID' ) );
		return;
	}

	try {
		global $wpdb;
		$repository = new SuggestionRepository( $wpdb );

		// Build WHERE clause
		$where  = array( 's.saga_id = %d' );
		$params = array( $saga_id );

		if ( $status !== 'all' ) {
			$where[]  = 's.status = %s';
			$params[] = $status;
		}

		if ( $min_confidence > 0 ) {
			$where[]  = 's.confidence_score >= %f';
			$params[] = $min_confidence;
		}

		if ( ! empty( $relationship_type ) ) {
			$where[]  = 's.suggested_type = %s';
			$params[] = $relationship_type;
		}

		$where_sql = implode( ' AND ', $where );

		// Valid sort columns
		$valid_sorts = array( 'confidence_score', 'priority_score', 'created_at' );
		$sort_column = in_array( $sort_by, $valid_sorts ) ? $sort_by : 'confidence_score';
		$sort_order  = $sort_order === 'ASC' ? 'ASC' : 'DESC';

		// Get total count
		$count_sql = $wpdb->prepare(
			"SELECT COUNT(*) FROM {$wpdb->prefix}saga_relationship_suggestions s WHERE {$where_sql}",
			...$params
		);
		$total     = (int) $wpdb->get_var( $count_sql );

		// Get suggestions
		$offset   = ( $page - 1 ) * $per_page;
		$params[] = $per_page;
		$params[] = $offset;

		$suggestions_sql = $wpdb->prepare(
			"SELECT s.*,
                    e1.canonical_name as source_name,
                    e1.entity_type as source_type,
                    e2.canonical_name as target_name,
                    e2.entity_type as target_type
            FROM {$wpdb->prefix}saga_relationship_suggestions s
            JOIN {$wpdb->prefix}saga_entities e1 ON s.source_entity_id = e1.id
            JOIN {$wpdb->prefix}saga_entities e2 ON s.target_entity_id = e2.id
            WHERE {$where_sql}
            ORDER BY s.{$sort_column} {$sort_order}
            LIMIT %d OFFSET %d",
			...$params
		);

		$suggestions = $wpdb->get_results( $suggestions_sql, ARRAY_A );

		wp_send_json_success(
			array(
				'suggestions' => $suggestions,
				'pagination'  => array(
					'total'       => $total,
					'page'        => $page,
					'per_page'    => $per_page,
					'total_pages' => ceil( $total / $per_page ),
				),
			)
		);

	} catch ( \Exception $e ) {
		error_log( '[SAGA][PREDICTIVE][AJAX] Load suggestions error: ' . $e->getMessage() );
		wp_send_json_error( array( 'message' => 'Failed to load suggestions' ) );
	}
}

/**
 * Accept a suggestion
 */
function saga_ajax_accept_suggestion(): void {
	if ( ! saga_verify_suggestions_ajax() ) {
		return;
	}

	$suggestion_id = absint( $_POST['suggestion_id'] ?? 0 );
	if ( ! $suggestion_id ) {
		wp_send_json_error( array( 'message' => 'Invalid suggestion ID' ) );
		return;
	}

	try {
		$services = saga_get_suggestion_services();

		// Record feedback
		$feedback = new SuggestionFeedback(
			$suggestion_id,
			'accepted',
			null,
			null,
			'User accepted suggestion'
		);

		$services['learningService']->recordFeedback( $feedback );

		wp_send_json_success(
			array(
				'message'       => 'Suggestion accepted',
				'suggestion_id' => $suggestion_id,
			)
		);

	} catch ( \Exception $e ) {
		error_log( '[SAGA][PREDICTIVE][AJAX] Accept error: ' . $e->getMessage() );
		wp_send_json_error( array( 'message' => 'Failed to accept suggestion' ) );
	}
}

/**
 * Reject a suggestion
 */
function saga_ajax_reject_suggestion(): void {
	if ( ! saga_verify_suggestions_ajax() ) {
		return;
	}

	$suggestion_id = absint( $_POST['suggestion_id'] ?? 0 );
	$reason        = sanitize_text_field( $_POST['reason'] ?? '' );

	if ( ! $suggestion_id ) {
		wp_send_json_error( array( 'message' => 'Invalid suggestion ID' ) );
		return;
	}

	try {
		$services = saga_get_suggestion_services();

		$feedback = new SuggestionFeedback(
			$suggestion_id,
			'rejected',
			null,
			null,
			$reason ?: 'User rejected suggestion'
		);

		$services['learningService']->recordFeedback( $feedback );

		wp_send_json_success(
			array(
				'message'       => 'Suggestion rejected',
				'suggestion_id' => $suggestion_id,
			)
		);

	} catch ( \Exception $e ) {
		error_log( '[SAGA][PREDICTIVE][AJAX] Reject error: ' . $e->getMessage() );
		wp_send_json_error( array( 'message' => 'Failed to reject suggestion' ) );
	}
}

/**
 * Modify a suggestion (change type or strength)
 */
function saga_ajax_modify_suggestion(): void {
	if ( ! saga_verify_suggestions_ajax() ) {
		return;
	}

	$suggestion_id      = absint( $_POST['suggestion_id'] ?? 0 );
	$corrected_type     = sanitize_text_field( $_POST['corrected_type'] ?? '' );
	$corrected_strength = absint( $_POST['corrected_strength'] ?? 0 );

	if ( ! $suggestion_id || ( ! $corrected_type && ! $corrected_strength ) ) {
		wp_send_json_error( array( 'message' => 'Invalid parameters' ) );
		return;
	}

	try {
		$services = saga_get_suggestion_services();

		$feedback = new SuggestionFeedback(
			$suggestion_id,
			'modified',
			$corrected_type ?: null,
			$corrected_strength ?: null,
			'User modified suggestion'
		);

		$services['learningService']->recordFeedback( $feedback );

		wp_send_json_success(
			array(
				'message'       => 'Suggestion modified',
				'suggestion_id' => $suggestion_id,
			)
		);

	} catch ( \Exception $e ) {
		error_log( '[SAGA][PREDICTIVE][AJAX] Modify error: ' . $e->getMessage() );
		wp_send_json_error( array( 'message' => 'Failed to modify suggestion' ) );
	}
}

/**
 * Dismiss a suggestion without learning
 */
function saga_ajax_dismiss_suggestion(): void {
	if ( ! saga_verify_suggestions_ajax() ) {
		return;
	}

	$suggestion_id = absint( $_POST['suggestion_id'] ?? 0 );
	if ( ! $suggestion_id ) {
		wp_send_json_error( array( 'message' => 'Invalid suggestion ID' ) );
		return;
	}

	try {
		global $wpdb;

		// SECURITY: Add format specifiers to prevent information disclosure
		$result = $wpdb->update(
			$wpdb->prefix . 'saga_relationship_suggestions',
			array( 'status' => 'dismissed' ),
			array( 'id' => $suggestion_id ),
			array( '%s' ),
			array( '%d' )
		);

		// Check if update was successful
		if ( $result === false ) {
			throw new \Exception( 'Database update failed' );
		}

		wp_send_json_success(
			array(
				'message'       => 'Suggestion dismissed',
				'suggestion_id' => $suggestion_id,
			)
		);

	} catch ( \Exception $e ) {
		error_log( '[SAGA][PREDICTIVE][AJAX] Dismiss error: ' . $e->getMessage() );
		wp_send_json_error( array( 'message' => 'Failed to dismiss suggestion' ) );
	}
}

/**
 * Bulk accept suggestions
 */
function saga_ajax_bulk_accept_suggestions(): void {
	if ( ! saga_verify_suggestions_ajax() ) {
		return;
	}

	$suggestion_ids = array_map( 'absint', $_POST['suggestion_ids'] ?? array() );
	if ( empty( $suggestion_ids ) ) {
		wp_send_json_error( array( 'message' => 'No suggestions provided' ) );
		return;
	}

	try {
		$services      = saga_get_suggestion_services();
		$success_count = 0;

		foreach ( $suggestion_ids as $suggestion_id ) {
			try {
				$feedback = new SuggestionFeedback(
					$suggestion_id,
					'accepted',
					null,
					null,
					'Bulk accepted by user'
				);

				$services['learningService']->recordFeedback( $feedback );
				++$success_count;

			} catch ( \Exception $e ) {
				error_log( "[SAGA][PREDICTIVE][AJAX] Bulk accept failed for {$suggestion_id}: " . $e->getMessage() );
			}
		}

		wp_send_json_success(
			array(
				'message' => "{$success_count} suggestions accepted",
				'count'   => $success_count,
			)
		);

	} catch ( \Exception $e ) {
		error_log( '[SAGA][PREDICTIVE][AJAX] Bulk accept error: ' . $e->getMessage() );
		wp_send_json_error( array( 'message' => 'Bulk operation failed' ) );
	}
}

/**
 * Bulk reject suggestions
 */
function saga_ajax_bulk_reject_suggestions(): void {
	if ( ! saga_verify_suggestions_ajax() ) {
		return;
	}

	$suggestion_ids = array_map( 'absint', $_POST['suggestion_ids'] ?? array() );
	if ( empty( $suggestion_ids ) ) {
		wp_send_json_error( array( 'message' => 'No suggestions provided' ) );
		return;
	}

	try {
		$services      = saga_get_suggestion_services();
		$success_count = 0;

		foreach ( $suggestion_ids as $suggestion_id ) {
			try {
				$feedback = new SuggestionFeedback(
					$suggestion_id,
					'rejected',
					null,
					null,
					'Bulk rejected by user'
				);

				$services['learningService']->recordFeedback( $feedback );
				++$success_count;

			} catch ( \Exception $e ) {
				error_log( "[SAGA][PREDICTIVE][AJAX] Bulk reject failed for {$suggestion_id}: " . $e->getMessage() );
			}
		}

		wp_send_json_success(
			array(
				'message' => "{$success_count} suggestions rejected",
				'count'   => $success_count,
			)
		);

	} catch ( \Exception $e ) {
		error_log( '[SAGA][PREDICTIVE][AJAX] Bulk reject error: ' . $e->getMessage() );
		wp_send_json_error( array( 'message' => 'Bulk operation failed' ) );
	}
}

/**
 * Create actual relationship from suggestion
 */
function saga_ajax_create_relationship_from_suggestion(): void {
	if ( ! saga_verify_suggestions_ajax() ) {
		return;
	}

	$suggestion_id = absint( $_POST['suggestion_id'] ?? 0 );
	if ( ! $suggestion_id ) {
		wp_send_json_error( array( 'message' => 'Invalid suggestion ID' ) );
		return;
	}

	try {
		global $wpdb;
		$repository = new SuggestionRepository( $wpdb );

		// Get suggestion
		$suggestion = $repository->findById( $suggestion_id );
		if ( ! $suggestion ) {
			wp_send_json_error( array( 'message' => 'Suggestion not found' ) );
			return;
		}

		// Create relationship in saga_entity_relationships
		$relationship_table = $wpdb->prefix . 'saga_entity_relationships';

		// SECURITY: Properly formatted insert to prevent information disclosure
		$insert_result = $wpdb->insert(
			$relationship_table,
			array(
				'source_entity_id'  => $suggestion->getSourceEntityId(),
				'target_entity_id'  => $suggestion->getTargetEntityId(),
				'relationship_type' => $suggestion->getSuggestedType(),
				'strength'          => $suggestion->getSuggestedStrength(),
				'metadata'          => json_encode(
					array(
						'created_from_suggestion' => true,
						'suggestion_id'           => $suggestion_id,
						'confidence'              => $suggestion->getConfidence(),
					)
				),
			),
			array( '%d', '%d', '%s', '%d', '%s' )
		);

		// Check if insert was successful
		if ( $insert_result === false ) {
			throw new \Exception( 'Failed to create relationship: ' . $wpdb->last_error );
		}

		$relationship_id = $wpdb->insert_id;

		// Mark suggestion as accepted
		$feedback = new SuggestionFeedback(
			$suggestion_id,
			'accepted',
			null,
			null,
			'Relationship created from suggestion'
		);

		$services = saga_get_suggestion_services();
		$services['learningService']->recordFeedback( $feedback );

		wp_send_json_success(
			array(
				'message'         => 'Relationship created successfully',
				'relationship_id' => $relationship_id,
				'suggestion_id'   => $suggestion_id,
			)
		);

	} catch ( \Exception $e ) {
		error_log( '[SAGA][PREDICTIVE][AJAX] Create relationship error: ' . $e->getMessage() );
		wp_send_json_error( array( 'message' => 'Failed to create relationship' ) );
	}
}

/**
 * Get detailed suggestion information with features
 */
function saga_ajax_get_suggestion_details(): void {
	if ( ! saga_verify_suggestions_ajax() ) {
		return;
	}

	$suggestion_id = absint( $_POST['suggestion_id'] ?? 0 );
	if ( ! $suggestion_id ) {
		wp_send_json_error( array( 'message' => 'Invalid suggestion ID' ) );
		return;
	}

	try {
		global $wpdb;
		$repository = new SuggestionRepository( $wpdb );

		$suggestion = $repository->findById( $suggestion_id );
		if ( ! $suggestion ) {
			wp_send_json_error( array( 'message' => 'Suggestion not found' ) );
			return;
		}

		// Get features
		$features = $repository->getFeatures( $suggestion_id );

		// Get entity details
		$entities_table = $wpdb->prefix . 'saga_entities';

		$source_entity = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$entities_table} WHERE id = %d",
				$suggestion->getSourceEntityId()
			),
			ARRAY_A
		);

		$target_entity = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$entities_table} WHERE id = %d",
				$suggestion->getTargetEntityId()
			),
			ARRAY_A
		);

		wp_send_json_success(
			array(
				'suggestion'    => array(
					'id'                 => $suggestion->getId(),
					'saga_id'            => $suggestion->getSagaId(),
					'source_entity_id'   => $suggestion->getSourceEntityId(),
					'target_entity_id'   => $suggestion->getTargetEntityId(),
					'suggested_type'     => $suggestion->getSuggestedType(),
					'suggested_strength' => $suggestion->getSuggestedStrength(),
					'confidence'         => $suggestion->getConfidence(),
					'priority'           => $suggestion->getPriority(),
					'reasoning'          => $suggestion->getReasoning(),
					'status'             => $suggestion->getStatus(),
				),
				'source_entity' => $source_entity,
				'target_entity' => $target_entity,
				'features'      => array_map(
					function ( $feature ) {
						return array(
							'name'   => $feature->getName(),
							'value'  => $feature->getValue(),
							'weight' => $feature->getWeight(),
						);
					},
					$features
				),
			)
		);

	} catch ( \Exception $e ) {
		error_log( '[SAGA][PREDICTIVE][AJAX] Get details error: ' . $e->getMessage() );
		wp_send_json_error( array( 'message' => 'Failed to get suggestion details' ) );
	}
}

/**
 * Get learning statistics
 */
function saga_ajax_get_learning_stats(): void {
	if ( ! saga_verify_suggestions_ajax() ) {
		return;
	}

	$saga_id = absint( $_POST['saga_id'] ?? 0 );

	try {
		$services = saga_get_suggestion_services();
		$stats    = $services['learningService']->getAccuracyMetrics( $saga_id ?: null );

		wp_send_json_success( $stats );

	} catch ( \Exception $e ) {
		error_log( '[SAGA][PREDICTIVE][AJAX] Get stats error: ' . $e->getMessage() );
		wp_send_json_error( array( 'message' => 'Failed to get learning statistics' ) );
	}
}

/**
 * Get suggestion analytics for dashboard
 */
function saga_ajax_get_suggestion_analytics(): void {
	if ( ! saga_verify_suggestions_ajax() ) {
		return;
	}

	$saga_id = absint( $_POST['saga_id'] ?? 0 );

	try {
		global $wpdb;
		$suggestions_table = $wpdb->prefix . 'saga_relationship_suggestions';
		$feedback_table    = $wpdb->prefix . 'saga_suggestion_feedback';

		$where_saga = $saga_id ? $wpdb->prepare( 'WHERE saga_id = %d', $saga_id ) : '';

		// Get counts by status
		$status_counts = $wpdb->get_results(
			"SELECT status, COUNT(*) as count
            FROM {$suggestions_table}
            {$where_saga}
            GROUP BY status",
			ARRAY_A
		);

		// Get average confidence
		$avg_confidence = $wpdb->get_var(
			"SELECT AVG(confidence_score)
            FROM {$suggestions_table}
            {$where_saga}"
		);

		// Get feedback stats
		$feedback_stats = $wpdb->get_results(
			"SELECT action_type, COUNT(*) as count
            FROM {$feedback_table} f
            JOIN {$suggestions_table} s ON f.suggestion_id = s.id
            {$where_saga}
            GROUP BY action_type",
			ARRAY_A
		);

		// Get acceptance rate
		$total_feedback = array_sum( array_column( $feedback_stats, 'count' ) );
		$accepted_count = 0;
		foreach ( $feedback_stats as $stat ) {
			if ( $stat['action_type'] === 'accepted' ) {
				$accepted_count = (int) $stat['count'];
				break;
			}
		}
		$acceptance_rate = $total_feedback > 0 ? ( $accepted_count / $total_feedback ) * 100 : 0;

		wp_send_json_success(
			array(
				'status_counts'   => $status_counts,
				'avg_confidence'  => round( (float) $avg_confidence, 2 ),
				'feedback_stats'  => $feedback_stats,
				'acceptance_rate' => round( $acceptance_rate, 2 ),
				'total_feedback'  => $total_feedback,
			)
		);

	} catch ( \Exception $e ) {
		error_log( '[SAGA][PREDICTIVE][AJAX] Get analytics error: ' . $e->getMessage() );
		wp_send_json_error( array( 'message' => 'Failed to get analytics' ) );
	}
}

/**
 * Manually trigger learning weight update
 */
function saga_ajax_trigger_learning_update(): void {
	if ( ! saga_verify_suggestions_ajax() ) {
		return;
	}

	$saga_id = absint( $_POST['saga_id'] ?? 0 );

	try {
		$services = saga_get_suggestion_services();
		$services['learningService']->updateFeatureWeights( $saga_id ?: null );

		wp_send_json_success(
			array(
				'message' => 'Learning weights updated successfully',
			)
		);

	} catch ( \Exception $e ) {
		error_log( '[SAGA][PREDICTIVE][AJAX] Learning update error: ' . $e->getMessage() );
		wp_send_json_error( array( 'message' => 'Failed to update learning weights' ) );
	}
}

/**
 * Reset learning weights to defaults
 */
function saga_ajax_reset_learning(): void {
	if ( ! saga_verify_suggestions_ajax() ) {
		return;
	}

	$saga_id = absint( $_POST['saga_id'] ?? 0 );

	try {
		global $wpdb;
		$weights_table = $wpdb->prefix . 'saga_learning_weights';

		$where = $saga_id ? $wpdb->prepare( 'WHERE saga_id = %d', $saga_id ) : '';

		$wpdb->query( "DELETE FROM {$weights_table} {$where}" );

		wp_send_json_success(
			array(
				'message' => 'Learning weights reset successfully',
			)
		);

	} catch ( \Exception $e ) {
		error_log( '[SAGA][PREDICTIVE][AJAX] Reset learning error: ' . $e->getMessage() );
		wp_send_json_error( array( 'message' => 'Failed to reset learning weights' ) );
	}
}
