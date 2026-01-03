<?php
/**
 * Entity Extraction AJAX Handlers
 *
 * Handles all AJAX requests for the entity extraction workflow.
 * Includes security checks, input validation, and error handling.
 *
 * @package SagaManager
 * @subpackage AJAX\Extraction
 * @since 1.4.0
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use SagaManager\AI\EntityExtractor\ExtractionOrchestrator;
use SagaManager\AI\EntityExtractor\ExtractionRepository;
use SagaManager\AI\EntityExtractor\Entities\ExtractedEntityStatus;
use SagaManager\Security\RateLimiter;

// Load security dependencies
require_once get_template_directory() . '/inc/security/rate-limiter.php';

/**
 * Start new extraction job
 */
add_action(
	'wp_ajax_saga_start_extraction',
	function () {
		// Security checks
		check_ajax_referer( 'saga_extraction_nonce', 'nonce' );

		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_send_json_error( array( 'message' => 'Unauthorized' ), 403 );
		}

		// SECURITY: Database-backed rate limiting (prevents transient bypass)
		$user_id      = get_current_user_id();
		$rate_limiter = new RateLimiter();

		if ( ! $rate_limiter->check( 'entity_extraction', $user_id, 10, HOUR_IN_SECONDS ) ) {
			$reset_time = $rate_limiter->get_reset_time( 'entity_extraction', $user_id, HOUR_IN_SECONDS );
			wp_send_json_error(
				array(
					'message' => sprintf(
						'Rate limit exceeded. Maximum 10 extractions per hour. Try again in %d minutes.',
						ceil( $reset_time / 60 )
					),
				),
				429
			);
		}

		// Validate and sanitize inputs
		$saga_id     = isset( $_POST['saga_id'] ) ? absint( $_POST['saga_id'] ) : 0;
		$source_text = isset( $_POST['source_text'] ) ? wp_kses_post( $_POST['source_text'] ) : '';
		$chunk_size  = isset( $_POST['chunk_size'] ) ? absint( $_POST['chunk_size'] ) : 5000;
		$source_type = isset( $_POST['source_type'] ) ? sanitize_key( $_POST['source_type'] ) : 'manual';
		$ai_provider = isset( $_POST['ai_provider'] ) ? sanitize_key( $_POST['ai_provider'] ) : 'openai';
		$ai_model    = isset( $_POST['ai_model'] ) ? sanitize_text_field( $_POST['ai_model'] ) : 'gpt-4';

		// Validation
		if ( $saga_id === 0 ) {
			wp_send_json_error( array( 'message' => 'Invalid saga ID' ), 400 );
		}

		if ( empty( trim( $source_text ) ) ) {
			wp_send_json_error( array( 'message' => 'Source text cannot be empty' ), 400 );
		}

		if ( mb_strlen( $source_text ) > 100000 ) {
			wp_send_json_error( array( 'message' => 'Text too long. Maximum 100,000 characters.' ), 400 );
		}

		if ( ! in_array( $chunk_size, array( 1000, 2500, 5000, 10000 ), true ) ) {
			wp_send_json_error( array( 'message' => 'Invalid chunk size' ), 400 );
		}

		try {
			$orchestrator = new ExtractionOrchestrator();

			$job = $orchestrator->startExtraction(
				$saga_id,
				$source_text,
				$user_id,
				array(
					'chunk_size'  => $chunk_size,
					'source_type' => $source_type,
					'ai_provider' => $ai_provider,
					'ai_model'    => $ai_model,
				)
			);

			// Update rate limiting - record successful attempt
			$rate_limiter->record( 'entity_extraction', $user_id, true );

			error_log(
				sprintf(
					'[SAGA][EXTRACTOR][AJAX] User %d started extraction job #%d',
					$user_id,
					$job->id
				)
			);

			wp_send_json_success(
				array(
					'job_id'             => $job->id,
					'status'             => $job->status->value,
					'total_chunks'       => $job->total_chunks,
					'estimated_cost'     => $job->metadata['estimated_cost_usd'] ?? 0,
					'estimated_time'     => $job->metadata['estimated_time_seconds'] ?? 0,
					'estimated_entities' => $job->metadata['estimated_entities'] ?? 0,
					'message'            => 'Extraction job started successfully',
				)
			);

		} catch ( \Exception $e ) {
			error_log(
				sprintf(
					'[SAGA][EXTRACTOR][AJAX][ERROR] Start extraction failed: %s',
					$e->getMessage()
				)
			);

			wp_send_json_error(
				array(
					'message' => 'Failed to start extraction: ' . $e->getMessage(),
				),
				500
			);
		}
	}
);

/**
 * Get extraction job progress
 */
add_action(
	'wp_ajax_saga_get_extraction_progress',
	function () {
		check_ajax_referer( 'saga_extraction_nonce', 'nonce' );

		if ( ! current_user_can( 'read' ) ) {
			wp_send_json_error( array( 'message' => 'Unauthorized' ), 403 );
		}

		$job_id = isset( $_POST['job_id'] ) ? absint( $_POST['job_id'] ) : 0;

		if ( $job_id === 0 ) {
			wp_send_json_error( array( 'message' => 'Invalid job ID' ), 400 );
		}

		try {
			$orchestrator = new ExtractionOrchestrator();
			$progress     = $orchestrator->getJobProgress( $job_id );

			wp_send_json_success( $progress );

		} catch ( \Exception $e ) {
			error_log(
				sprintf(
					'[SAGA][EXTRACTOR][AJAX][ERROR] Get progress failed for job #%d: %s',
					$job_id,
					$e->getMessage()
				)
			);

			wp_send_json_error(
				array(
					'message' => 'Failed to get progress: ' . $e->getMessage(),
				),
				500
			);
		}
	}
);

/**
 * Load extracted entities for preview
 */
add_action(
	'wp_ajax_saga_load_extracted_entities',
	function () {
		check_ajax_referer( 'saga_extraction_nonce', 'nonce' );

		if ( ! current_user_can( 'read' ) ) {
			wp_send_json_error( array( 'message' => 'Unauthorized' ), 403 );
		}

		$job_id            = isset( $_POST['job_id'] ) ? absint( $_POST['job_id'] ) : 0;
		$page              = isset( $_POST['page'] ) ? absint( $_POST['page'] ) : 1;
		$per_page          = isset( $_POST['per_page'] ) ? absint( $_POST['per_page'] ) : 25;
		$filter_type       = isset( $_POST['filter_type'] ) ? sanitize_key( $_POST['filter_type'] ) : '';
		$filter_status     = isset( $_POST['filter_status'] ) ? sanitize_key( $_POST['filter_status'] ) : '';
		$filter_confidence = isset( $_POST['filter_confidence'] ) ? sanitize_key( $_POST['filter_confidence'] ) : '';

		if ( $job_id === 0 ) {
			wp_send_json_error( array( 'message' => 'Invalid job ID' ), 400 );
		}

		try {
			$repository   = new ExtractionRepository();
			$all_entities = $repository->findEntitiesByJob( $job_id );

			// Apply filters
			$filtered_entities = array_filter(
				$all_entities,
				function ( $entity ) use ( $filter_type, $filter_status, $filter_confidence ) {
					if ( ! empty( $filter_type ) && $entity->entity_type !== $filter_type ) {
						return false;
					}

					if ( ! empty( $filter_status ) && $entity->status->value !== $filter_status ) {
						return false;
					}

					if ( ! empty( $filter_confidence ) ) {
						$confidence = $entity->confidence_score;
						switch ( $filter_confidence ) {
							case 'high':
								if ( $confidence < 0.8 ) {
									return false;
								}
								break;
							case 'medium':
								if ( $confidence < 0.6 || $confidence >= 0.8 ) {
									return false;
								}
								break;
							case 'low':
								if ( $confidence >= 0.6 ) {
									return false;
								}
								break;
						}
					}

					return true;
				}
			);

			// Pagination
			$total_entities = count( $filtered_entities );
			$total_pages    = ceil( $total_entities / $per_page );
			$offset         = ( $page - 1 ) * $per_page;
			$paged_entities = array_slice( $filtered_entities, $offset, $per_page );

			// Format entities for frontend
			$formatted_entities = array_map(
				function ( $entity ) use ( $repository ) {
					$duplicates = $repository->findDuplicatesByEntity( $entity->id );

					return array(
						'id'                   => $entity->id,
						'canonical_name'       => $entity->canonical_name,
						'entity_type'          => $entity->entity_type,
						'description'          => $entity->description,
						'attributes'           => $entity->attributes,
						'context_snippet'      => $entity->context_snippet,
						'confidence_score'     => $entity->confidence_score,
						'status'               => $entity->status->value,
						'is_duplicate'         => $entity->is_duplicate,
						'duplicate_of'         => $entity->duplicate_of_entity_id,
						'duplicate_similarity' => $entity->duplicate_similarity_score,
						'duplicates'           => array_map(
							fn( $dup ) => array(
								'id'                   => $dup->id,
								'existing_entity_id'   => $dup->existing_entity_id,
								'existing_entity_name' => $dup->existing_entity_name,
								'similarity_score'     => $dup->similarity_score,
								'match_reason'         => $dup->match_reason,
								'user_action'          => $dup->user_action,
							),
							$duplicates
						),
					);
				},
				$paged_entities
			);

			wp_send_json_success(
				array(
					'entities'   => $formatted_entities,
					'pagination' => array(
						'current_page'   => $page,
						'per_page'       => $per_page,
						'total_entities' => $total_entities,
						'total_pages'    => $total_pages,
					),
				)
			);

		} catch ( \Exception $e ) {
			error_log(
				sprintf(
					'[SAGA][EXTRACTOR][AJAX][ERROR] Load entities failed: %s',
					$e->getMessage()
				)
			);

			wp_send_json_error(
				array(
					'message' => 'Failed to load entities: ' . $e->getMessage(),
				),
				500
			);
		}
	}
);

/**
 * Approve single entity
 */
add_action(
	'wp_ajax_saga_approve_entity',
	function () {
		check_ajax_referer( 'saga_extraction_nonce', 'nonce' );

		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_send_json_error( array( 'message' => 'Unauthorized' ), 403 );
		}

		$entity_id = isset( $_POST['entity_id'] ) ? absint( $_POST['entity_id'] ) : 0;

		if ( $entity_id === 0 ) {
			wp_send_json_error( array( 'message' => 'Invalid entity ID' ), 400 );
		}

		try {
			$repository = new ExtractionRepository();
			$entity     = $repository->findEntityById( $entity_id );

			if ( ! $entity ) {
				wp_send_json_error( array( 'message' => 'Entity not found' ), 404 );
			}

			$updated = $entity->approve( get_current_user_id() );
			$repository->updateEntity( $updated );

			error_log(
				sprintf(
					'[SAGA][EXTRACTOR][AJAX] Approved entity #%d: %s',
					$entity_id,
					$entity->canonical_name
				)
			);

			wp_send_json_success(
				array(
					'entity_id' => $entity_id,
					'status'    => $updated->status->value,
					'message'   => 'Entity approved',
				)
			);

		} catch ( \Exception $e ) {
			error_log(
				sprintf(
					'[SAGA][EXTRACTOR][AJAX][ERROR] Approve entity failed: %s',
					$e->getMessage()
				)
			);

			wp_send_json_error(
				array(
					'message' => 'Failed to approve entity: ' . $e->getMessage(),
				),
				500
			);
		}
	}
);

/**
 * Reject single entity
 */
add_action(
	'wp_ajax_saga_reject_entity',
	function () {
		check_ajax_referer( 'saga_extraction_nonce', 'nonce' );

		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_send_json_error( array( 'message' => 'Unauthorized' ), 403 );
		}

		$entity_id = isset( $_POST['entity_id'] ) ? absint( $_POST['entity_id'] ) : 0;

		if ( $entity_id === 0 ) {
			wp_send_json_error( array( 'message' => 'Invalid entity ID' ), 400 );
		}

		try {
			$repository = new ExtractionRepository();
			$entity     = $repository->findEntityById( $entity_id );

			if ( ! $entity ) {
				wp_send_json_error( array( 'message' => 'Entity not found' ), 404 );
			}

			$updated = $entity->reject( get_current_user_id() );
			$repository->updateEntity( $updated );

			error_log(
				sprintf(
					'[SAGA][EXTRACTOR][AJAX] Rejected entity #%d: %s',
					$entity_id,
					$entity->canonical_name
				)
			);

			wp_send_json_success(
				array(
					'entity_id' => $entity_id,
					'status'    => $updated->status->value,
					'message'   => 'Entity rejected',
				)
			);

		} catch ( \Exception $e ) {
			error_log(
				sprintf(
					'[SAGA][EXTRACTOR][AJAX][ERROR] Reject entity failed: %s',
					$e->getMessage()
				)
			);

			wp_send_json_error(
				array(
					'message' => 'Failed to reject entity: ' . $e->getMessage(),
				),
				500
			);
		}
	}
);

/**
 * Bulk approve entities
 */
add_action(
	'wp_ajax_saga_bulk_approve_entities',
	function () {
		check_ajax_referer( 'saga_extraction_nonce', 'nonce' );

		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_send_json_error( array( 'message' => 'Unauthorized' ), 403 );
		}

		$entity_ids = isset( $_POST['entity_ids'] ) ? array_map( 'absint', (array) $_POST['entity_ids'] ) : array();

		if ( empty( $entity_ids ) ) {
			wp_send_json_error( array( 'message' => 'No entities provided' ), 400 );
		}

		try {
			$repository     = new ExtractionRepository();
			$user_id        = get_current_user_id();
			$approved_count = 0;

			foreach ( $entity_ids as $entity_id ) {
				$entity = $repository->findEntityById( $entity_id );

				if ( $entity ) {
					$updated = $entity->approve( $user_id );
					$repository->updateEntity( $updated );
					$approved_count++;
				}
			}

			error_log(
				sprintf(
					'[SAGA][EXTRACTOR][AJAX] Bulk approved %d entities',
					$approved_count
				)
			);

			wp_send_json_success(
				array(
					'approved_count' => $approved_count,
					'message'        => sprintf( '%d entities approved', $approved_count ),
				)
			);

		} catch ( \Exception $e ) {
			error_log(
				sprintf(
					'[SAGA][EXTRACTOR][AJAX][ERROR] Bulk approve failed: %s',
					$e->getMessage()
				)
			);

			wp_send_json_error(
				array(
					'message' => 'Failed to approve entities: ' . $e->getMessage(),
				),
				500
			);
		}
	}
);

/**
 * Batch create approved entities as permanent saga entities
 */
add_action(
	'wp_ajax_saga_batch_create_approved',
	function () {
		check_ajax_referer( 'saga_extraction_nonce', 'nonce' );

		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_send_json_error( array( 'message' => 'Unauthorized' ), 403 );
		}

		$job_id     = isset( $_POST['job_id'] ) ? absint( $_POST['job_id'] ) : 0;
		$entity_ids = isset( $_POST['entity_ids'] ) ? array_map( 'absint', (array) $_POST['entity_ids'] ) : array();

		if ( $job_id === 0 ) {
			wp_send_json_error( array( 'message' => 'Invalid job ID' ), 400 );
		}

		try {
			$orchestrator = new ExtractionOrchestrator();

			$results = $orchestrator->approveAndCreateEntities(
				$job_id,
				$entity_ids,
				get_current_user_id()
			);

			error_log(
				sprintf(
					'[SAGA][EXTRACTOR][AJAX] Batch created %d/%d entities from job #%d',
					$results['created'],
					$results['total'],
					$job_id
				)
			);

			wp_send_json_success(
				array(
					'total'            => $results['total'],
					'created'          => $results['created'],
					'errors'           => $results['errors'],
					'success_entities' => $results['success'],
					'failed_entities'  => $results['failed'],
					'message'          => sprintf(
						'Created %d entities (%d failed)',
						$results['created'],
						$results['errors']
					),
				)
			);

		} catch ( \Exception $e ) {
			error_log(
				sprintf(
					'[SAGA][EXTRACTOR][AJAX][ERROR] Batch create failed: %s',
					$e->getMessage()
				)
			);

			wp_send_json_error(
				array(
					'message' => 'Failed to create entities: ' . $e->getMessage(),
				),
				500
			);
		}
	}
);

/**
 * Resolve duplicate match
 */
add_action(
	'wp_ajax_saga_resolve_duplicate',
	function () {
		check_ajax_referer( 'saga_extraction_nonce', 'nonce' );

		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_send_json_error( array( 'message' => 'Unauthorized' ), 403 );
		}

		$duplicate_id = isset( $_POST['duplicate_id'] ) ? absint( $_POST['duplicate_id'] ) : 0;
		$action       = isset( $_POST['action_type'] ) ? sanitize_key( $_POST['action_type'] ) : '';

		if ( $duplicate_id === 0 ) {
			wp_send_json_error( array( 'message' => 'Invalid duplicate ID' ), 400 );
		}

		if ( ! in_array( $action, array( 'confirmed_duplicate', 'marked_unique' ), true ) ) {
			wp_send_json_error( array( 'message' => 'Invalid action' ), 400 );
		}

		try {
			$repository = new ExtractionRepository();

			// Find duplicate match and update
			global $wpdb;
			$duplicates_table = $wpdb->prefix . 'saga_extraction_duplicates';

			$result = $wpdb->update(
				$duplicates_table,
				array(
					'user_action' => $action,
					'reviewed_at' => current_time( 'mysql' ),
					'reviewed_by' => get_current_user_id(),
				),
				array( 'id' => $duplicate_id ),
				array( '%s', '%s', '%d' ),
				array( '%d' )
			);

			if ( $result === false ) {
				throw new \Exception( 'Failed to update duplicate match' );
			}

			error_log(
				sprintf(
					'[SAGA][EXTRACTOR][AJAX] Resolved duplicate #%d as %s',
					$duplicate_id,
					$action
				)
			);

			wp_send_json_success(
				array(
					'duplicate_id' => $duplicate_id,
					'action'       => $action,
					'message'      => 'Duplicate resolved',
				)
			);

		} catch ( \Exception $e ) {
			error_log(
				sprintf(
					'[SAGA][EXTRACTOR][AJAX][ERROR] Resolve duplicate failed: %s',
					$e->getMessage()
				)
			);

			wp_send_json_error(
				array(
					'message' => 'Failed to resolve duplicate: ' . $e->getMessage(),
				),
				500
			);
		}
	}
);

/**
 * Load extraction job history
 */
add_action(
	'wp_ajax_saga_load_job_history',
	function () {
		check_ajax_referer( 'saga_extraction_nonce', 'nonce' );

		if ( ! current_user_can( 'read' ) ) {
			wp_send_json_error( array( 'message' => 'Unauthorized' ), 403 );
		}

		$saga_id  = isset( $_POST['saga_id'] ) ? absint( $_POST['saga_id'] ) : 0;
		$page     = isset( $_POST['page'] ) ? absint( $_POST['page'] ) : 1;
		$per_page = isset( $_POST['per_page'] ) ? absint( $_POST['per_page'] ) : 20;

		try {
			$repository = new ExtractionRepository();

			$offset = ( $page - 1 ) * $per_page;

			if ( $saga_id > 0 ) {
				$jobs = $repository->findJobsBySaga( $saga_id, $per_page, $offset );
			} else {
				// Get all jobs if no saga specified
				global $wpdb;
				$jobs_table = $wpdb->prefix . 'saga_extraction_jobs';

				$query = $wpdb->prepare(
					"SELECT * FROM {$jobs_table} ORDER BY created_at DESC LIMIT %d OFFSET %d",
					$per_page,
					$offset
				);

				$rows = $wpdb->get_results( $query, ARRAY_A );
				$jobs = array_map(
					fn( $row ) => \SagaManager\AI\EntityExtractor\Entities\ExtractionJob::fromArray( $row ),
					$rows
				);
			}

			// Format jobs for frontend
			$formatted_jobs = array_map(
				function ( $job ) {
					return array(
						'id'                   => $job->id,
						'saga_id'              => $job->saga_id,
						'status'               => $job->status->value,
						'total_entities_found' => $job->total_entities_found,
						'entities_created'     => $job->entities_created,
						'entities_rejected'    => $job->entities_rejected,
						'duplicates_found'     => $job->duplicates_found,
						'accuracy_score'       => $job->accuracy_score,
						'processing_time_ms'   => $job->processing_time_ms,
						'api_cost_usd'         => $job->api_cost_usd,
						'created_at'           => $job->created_at,
						'completed_at'         => $job->completed_at,
						'error_message'        => $job->error_message,
					);
				},
				$jobs
			);

			wp_send_json_success(
				array(
					'jobs'     => $formatted_jobs,
					'page'     => $page,
					'per_page' => $per_page,
				)
			);

		} catch ( \Exception $e ) {
			error_log(
				sprintf(
					'[SAGA][EXTRACTOR][AJAX][ERROR] Load job history failed: %s',
					$e->getMessage()
				)
			);

			wp_send_json_error(
				array(
					'message' => 'Failed to load job history: ' . $e->getMessage(),
				),
				500
			);
		}
	}
);

/**
 * Cancel extraction job
 */
add_action(
	'wp_ajax_saga_cancel_extraction_job',
	function () {
		check_ajax_referer( 'saga_extraction_nonce', 'nonce' );

		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_send_json_error( array( 'message' => 'Unauthorized' ), 403 );
		}

		$job_id = isset( $_POST['job_id'] ) ? absint( $_POST['job_id'] ) : 0;

		if ( $job_id === 0 ) {
			wp_send_json_error( array( 'message' => 'Invalid job ID' ), 400 );
		}

		try {
			$orchestrator = new ExtractionOrchestrator();
			$orchestrator->cancelJob( $job_id );

			error_log(
				sprintf(
					'[SAGA][EXTRACTOR][AJAX] Cancelled job #%d',
					$job_id
				)
			);

			wp_send_json_success(
				array(
					'job_id'  => $job_id,
					'message' => 'Job cancelled',
				)
			);

		} catch ( \Exception $e ) {
			error_log(
				sprintf(
					'[SAGA][EXTRACTOR][AJAX][ERROR] Cancel job failed: %s',
					$e->getMessage()
				)
			);

			wp_send_json_error(
				array(
					'message' => 'Failed to cancel job: ' . $e->getMessage(),
				),
				500
			);
		}
	}
);

/**
 * Get extraction statistics dashboard
 */
add_action(
	'wp_ajax_saga_get_extraction_stats',
	function () {
		check_ajax_referer( 'saga_extraction_nonce', 'nonce' );

		if ( ! current_user_can( 'read' ) ) {
			wp_send_json_error( array( 'message' => 'Unauthorized' ), 403 );
		}

		$saga_id = isset( $_POST['saga_id'] ) ? absint( $_POST['saga_id'] ) : 0;

		try {
			$orchestrator = new ExtractionOrchestrator();

			if ( $saga_id > 0 ) {
				$stats = $orchestrator->getSagaExtractionSummary( $saga_id );
			} else {
				// Get global stats
				global $wpdb;
				$jobs_table = $wpdb->prefix . 'saga_extraction_jobs';

				$stats = array(
					'total_jobs'               => $wpdb->get_var( "SELECT COUNT(*) FROM {$jobs_table}" ),
					'total_entities_extracted' => $wpdb->get_var( "SELECT SUM(total_entities_found) FROM {$jobs_table}" ),
					'total_entities_created'   => $wpdb->get_var( "SELECT SUM(entities_created) FROM {$jobs_table}" ),
					'total_duplicates_found'   => $wpdb->get_var( "SELECT SUM(duplicates_found) FROM {$jobs_table}" ),
					'total_api_cost_usd'       => $wpdb->get_var( "SELECT SUM(api_cost_usd) FROM {$jobs_table}" ),
				);
			}

			wp_send_json_success( $stats );

		} catch ( \Exception $e ) {
			error_log(
				sprintf(
					'[SAGA][EXTRACTOR][AJAX][ERROR] Get stats failed: %s',
					$e->getMessage()
				)
			);

			wp_send_json_error(
				array(
					'message' => 'Failed to get statistics: ' . $e->getMessage(),
				),
				500
			);
		}
	}
);

/**
 * Estimate extraction cost before starting
 */
add_action(
	'wp_ajax_saga_estimate_extraction_cost',
	function () {
		check_ajax_referer( 'saga_extraction_nonce', 'nonce' );

		if ( ! current_user_can( 'read' ) ) {
			wp_send_json_error( array( 'message' => 'Unauthorized' ), 403 );
		}

		$source_text = isset( $_POST['source_text'] ) ? wp_kses_post( $_POST['source_text'] ) : '';

		if ( empty( trim( $source_text ) ) ) {
			wp_send_json_error( array( 'message' => 'Source text cannot be empty' ), 400 );
		}

		try {
			$orchestrator = new ExtractionOrchestrator();
			$estimates    = $orchestrator->estimateExtraction( $source_text );

			wp_send_json_success( $estimates );

		} catch ( \Exception $e ) {
			error_log(
				sprintf(
					'[SAGA][EXTRACTOR][AJAX][ERROR] Estimate cost failed: %s',
					$e->getMessage()
				)
			);

			wp_send_json_error(
				array(
					'message' => 'Failed to estimate cost: ' . $e->getMessage(),
				),
				500
			);
		}
	}
);
