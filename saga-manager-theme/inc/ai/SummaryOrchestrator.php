<?php
/**
 * Summary Orchestrator
 *
 * Workflow orchestration service for AI summary generation.
 * Coordinates data collection, generation, and background processing.
 *
 * @package SagaManager
 * @subpackage AI\SummaryGenerator
 * @since 1.5.0
 */

declare(strict_types=1);

namespace SagaManager\AI\SummaryGenerator;

use SagaManager\AI\Entities\SummaryRequest;
use SagaManager\AI\Entities\GeneratedSummary;
use SagaManager\AI\Entities\SummaryType;
use SagaManager\AI\Entities\SummaryScope;
use SagaManager\AI\Entities\AIProvider;
use SagaManager\AI\Entities\RequestStatus;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Summary Orchestrator
 *
 * Coordinates the summary generation workflow.
 */
class SummaryOrchestrator {

	private SummaryRepository $repository;
	private SummaryGenerationService $generation_service;
	private DataCollectionService $data_collector;

	/**
	 * Constructor
	 *
	 * @param SummaryRepository|null        $repository
	 * @param SummaryGenerationService|null $generation_service
	 * @param DataCollectionService|null    $data_collector
	 */
	public function __construct(
		?SummaryRepository $repository = null,
		?SummaryGenerationService $generation_service = null,
		?DataCollectionService $data_collector = null
	) {
		$this->repository         = $repository ?? new SummaryRepository();
		$this->generation_service = $generation_service ?? new SummaryGenerationService();
		$this->data_collector     = $data_collector ?? new DataCollectionService();
	}

	/**
	 * Start summary generation
	 *
	 * Creates request, validates, and queues for processing.
	 *
	 * @param int         $saga_id Saga ID
	 * @param SummaryType $type Summary type
	 * @param array       $options Options: entity_id, scope, scope_params, ai_provider, ai_model, priority
	 * @return SummaryRequest Created request
	 * @throws \Exception If validation fails
	 */
	public function startSummaryGeneration( int $saga_id, SummaryType $type, array $options = array() ): SummaryRequest {
		// Extract options
		$entity_id    = $options['entity_id'] ?? null;
		$scope        = isset( $options['scope'] ) ? SummaryScope::from( $options['scope'] ) : SummaryScope::FULL;
		$scope_params = $options['scope_params'] ?? array();
		$ai_provider  = isset( $options['ai_provider'] )
			? AIProvider::from( $options['ai_provider'] )
			: AIProvider::OPENAI;
		$ai_model     = $options['ai_model'] ?? $ai_provider->getDefaultModel();
		$priority     = $options['priority'] ?? 5;
		$user_id      = get_current_user_id();

		// Validate
		if ( $type->requiresEntity() && $entity_id === null ) {
			throw new \InvalidArgumentException(
				sprintf( __( 'Summary type "%s" requires an entity_id', 'saga-manager' ), $type->value )
			);
		}

		// Check for existing cached summary
		$cache_key = GeneratedSummary::generateCacheKey( $saga_id, $type, $entity_id, $scope_params );
		$existing  = $this->repository->findByCacheKey( $cache_key );

		if ( $existing && ! $existing->isCacheExpired() ) {
			error_log(
				sprintf(
					'[SAGA][SUMMARY] Using cached summary #%d (cache_key: %s)',
					$existing->id,
					$cache_key
				)
			);

			// Return existing request if available
			$request = $this->repository->findRequestById( $existing->request_id );
			if ( $request ) {
				return $request;
			}
		}

		// Estimate tokens
		$estimated_length = $this->estimateContextLength( $saga_id, $type, $entity_id, $scope_params );

		// Create request
		$request = new SummaryRequest(
			id: null,
			saga_id: $saga_id,
			user_id: $user_id,
			summary_type: $type,
			entity_id: $entity_id,
			scope: $scope,
			scope_params: $scope_params,
			status: RequestStatus::PENDING,
			priority: $priority,
			ai_provider: $ai_provider,
			ai_model: $ai_model
		);

		$request = $request->withEstimatedTokens( $estimated_length );

		// Save request
		$request_id = $this->repository->createRequest( $request );

		error_log(
			sprintf(
				'[SAGA][SUMMARY] Created summary request #%d: %s for saga #%d',
				$request_id,
				$type->value,
				$saga_id
			)
		);

		// Update request with ID
		$request = new SummaryRequest(
			...array_merge(
				get_object_vars( $request ),
				array( 'id' => $request_id )
			)
		);

		// Process immediately in foreground
		$this->processRequest( $request );

		return $request;
	}

	/**
	 * Process pending requests (for background/cron processing)
	 *
	 * @param int $limit Maximum number of requests to process
	 * @return int Number of requests processed
	 */
	public function processPendingRequests( int $limit = 10 ): int {
		global $wpdb;

		$requests_table = $wpdb->prefix . 'saga_summary_requests';

		// Get pending requests ordered by priority and creation time
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$requests_table}
             WHERE status = %s
             ORDER BY priority DESC, created_at ASC
             LIMIT %d",
				'pending',
				$limit
			)
		);

		$processed = 0;

		foreach ( $rows as $row ) {
			try {
				$request = SummaryRequest::fromDatabase( $row );
				$this->processRequest( $request );
				++$processed;
			} catch ( \Exception $e ) {
				error_log(
					sprintf(
						'[SAGA][SUMMARY][ERROR] Failed to process request #%d: %s',
						$row->id,
						$e->getMessage()
					)
				);
			}
		}

		return $processed;
	}

	/**
	 * Process single request
	 *
	 * @param SummaryRequest $request Request to process
	 * @return GeneratedSummary Generated summary
	 * @throws \Exception If processing fails
	 */
	private function processRequest( SummaryRequest $request ): GeneratedSummary {
		// Update status to generating
		$request = $request->withStatus( RequestStatus::GENERATING );
		$this->repository->updateRequest( $request );

		// Store progress in transient
		$this->setProgress( $request->id, 10, __( 'Collecting data...', 'saga-manager' ) );

		try {
			// Generate summary
			$this->setProgress( $request->id, 50, __( 'Generating summary...', 'saga-manager' ) );
			$summary = $this->generation_service->generateSummary( $request );

			// Save summary
			$this->setProgress( $request->id, 90, __( 'Saving summary...', 'saga-manager' ) );
			$summary_id = $this->repository->create( $summary );

			// Update summary with ID
			$summary = new GeneratedSummary(
				...array_merge(
					get_object_vars( $summary ),
					array( 'id' => $summary_id )
				)
			);

			// Update request status
			$request = $request->withStatus( RequestStatus::COMPLETED );
			$request = $request->withTokenUsage(
				(int) ( $summary->token_count * 0.7 ), // Estimate input tokens
				(int) ( $summary->token_count * 0.3 )  // Estimate output tokens
			);
			$this->repository->updateRequest( $request );

			$this->setProgress( $request->id, 100, __( 'Complete', 'saga-manager' ) );

			error_log(
				sprintf(
					'[SAGA][SUMMARY] Successfully generated summary #%d for request #%d',
					$summary_id,
					$request->id
				)
			);

			return $summary;

		} catch ( \Exception $e ) {
			// Mark request as failed
			$request = $request->withError( $e->getMessage() );
			$this->repository->updateRequest( $request );

			$this->setProgress( $request->id, 0, sprintf( __( 'Error: %s', 'saga-manager' ), $e->getMessage() ) );

			error_log(
				sprintf(
					'[SAGA][SUMMARY][ERROR] Summary generation failed for request #%d: %s',
					$request->id,
					$e->getMessage()
				)
			);

			throw $e;
		}
	}

	/**
	 * Regenerate existing summary
	 *
	 * @param int    $summary_id Existing summary ID
	 * @param string $reason Regeneration reason
	 * @return GeneratedSummary New summary version
	 * @throws \Exception If regeneration fails
	 */
	public function regenerateSummary( int $summary_id, string $reason ): GeneratedSummary {
		$old_summary = $this->repository->findById( $summary_id );

		if ( ! $old_summary ) {
			throw new \Exception( sprintf( __( 'Summary #%d not found', 'saga-manager' ), $summary_id ) );
		}

		// Get original request
		$old_request = $this->repository->findRequestById( $old_summary->request_id );

		if ( ! $old_request ) {
			throw new \Exception( __( 'Original request not found', 'saga-manager' ) );
		}

		// Create new request with same parameters
		$new_request = new SummaryRequest(
			id: null,
			saga_id: $old_request->saga_id,
			user_id: get_current_user_id(),
			summary_type: $old_request->summary_type,
			entity_id: $old_request->entity_id,
			scope: $old_request->scope,
			scope_params: $old_request->scope_params,
			status: RequestStatus::PENDING,
			priority: 10, // High priority for regeneration
			ai_provider: $old_request->ai_provider,
			ai_model: $old_request->ai_model
		);

		$request_id  = $this->repository->createRequest( $new_request );
		$new_request = new SummaryRequest(
			...array_merge(
				get_object_vars( $new_request ),
				array( 'id' => $request_id )
			)
		);

		// Process request
		$new_summary = $this->processRequest( $new_request );

		// Update version number
		$new_summary = $new_summary->withNewVersion( $reason );

		// Mark old summary as not current and save new version
		$this->repository->updateVersion( $summary_id, $new_summary );

		error_log(
			sprintf(
				'[SAGA][SUMMARY] Regenerated summary #%d -> #%d (reason: %s)',
				$summary_id,
				$new_summary->id,
				$reason
			)
		);

		return $new_summary;
	}

	/**
	 * Cancel request
	 *
	 * @param int $request_id Request ID
	 * @return bool Success
	 * @throws \Exception If cancellation fails
	 */
	public function cancelRequest( int $request_id ): bool {
		$request = $this->repository->findRequestById( $request_id );

		if ( ! $request ) {
			throw new \Exception( sprintf( __( 'Request #%d not found', 'saga-manager' ), $request_id ) );
		}

		if ( $request->status->isFinal() ) {
			throw new \Exception( __( 'Cannot cancel completed or failed request', 'saga-manager' ) );
		}

		$request = $request->withStatus( RequestStatus::CANCELLED );
		$this->repository->updateRequest( $request );

		$this->clearProgress( $request_id );

		error_log( sprintf( '[SAGA][SUMMARY] Cancelled request #%d', $request_id ) );

		return true;
	}

	/**
	 * Get progress for request
	 *
	 * @param int $request_id Request ID
	 * @return array Progress data
	 */
	public function getProgress( int $request_id ): array {
		$transient_key = "saga_summary_progress_{$request_id}";
		$progress      = get_transient( $transient_key );

		if ( $progress === false ) {
			// Check request status
			$request = $this->repository->findRequestById( $request_id );

			if ( ! $request ) {
				return array(
					'percent' => 0,
					'message' => __( 'Request not found', 'saga-manager' ),
					'status'  => 'failed',
				);
			}

			return array(
				'percent' => $request->getProgressPercentage(),
				'message' => $this->getStatusMessage( $request->status ),
				'status'  => $request->status->value,
				'error'   => $request->error_message,
			);
		}

		return $progress;
	}

	/**
	 * Set progress in transient
	 *
	 * @param int    $request_id Request ID
	 * @param int    $percent Percentage complete
	 * @param string $message Progress message
	 */
	private function setProgress( int $request_id, int $percent, string $message ): void {
		$transient_key = "saga_summary_progress_{$request_id}";
		$progress      = array(
			'percent'   => $percent,
			'message'   => $message,
			'status'    => 'generating',
			'timestamp' => time(),
		);

		set_transient( $transient_key, $progress, 300 ); // 5 minutes
	}

	/**
	 * Clear progress transient
	 *
	 * @param int $request_id Request ID
	 */
	private function clearProgress( int $request_id ): void {
		$transient_key = "saga_summary_progress_{$request_id}";
		delete_transient( $transient_key );
	}

	/**
	 * Get status message for request status
	 *
	 * @param RequestStatus $status Status
	 * @return string Bilingual message
	 */
	private function getStatusMessage( RequestStatus $status ): string {
		return match ( $status ) {
			RequestStatus::PENDING => __( 'Waiting to start...', 'saga-manager' ),
			RequestStatus::GENERATING => __( 'Generating summary...', 'saga-manager' ),
			RequestStatus::COMPLETED => __( 'Complete', 'saga-manager' ),
			RequestStatus::FAILED => __( 'Failed', 'saga-manager' ),
			RequestStatus::CANCELLED => __( 'Cancelled', 'saga-manager' ),
		};
	}

	/**
	 * Estimate context length for token calculation
	 *
	 * @param int         $saga_id Saga ID
	 * @param SummaryType $type Summary type
	 * @param int|null    $entity_id Entity ID
	 * @param array       $scope_params Scope parameters
	 * @return int Estimated character count
	 */
	private function estimateContextLength(
		int $saga_id,
		SummaryType $type,
		?int $entity_id,
		array $scope_params
	): int {
		// Base estimate by type
		$base = match ( $type ) {
			SummaryType::CHARACTER_ARC => 2000,
			SummaryType::TIMELINE => 3000,
			SummaryType::RELATIONSHIP => 2500,
			SummaryType::FACTION => 2000,
			SummaryType::LOCATION => 1500,
		};

		// Adjust for scope
		if ( isset( $scope_params['date_range'] ) ) {
			$base = (int) ( $base * 0.5 );
		}

		return $base;
	}

	/**
	 * Register WordPress cron job for background processing
	 */
	public static function registerCronJob(): void {
		if ( ! wp_next_scheduled( 'saga_process_summaries' ) ) {
			wp_schedule_event( time(), 'hourly', 'saga_process_summaries' );
		}
	}

	/**
	 * Unregister WordPress cron job
	 */
	public static function unregisterCronJob(): void {
		$timestamp = wp_next_scheduled( 'saga_process_summaries' );
		if ( $timestamp ) {
			wp_unschedule_event( $timestamp, 'saga_process_summaries' );
		}
	}
}

// Register cron job handler
add_action(
	'saga_process_summaries',
	function () {
		$orchestrator = new SummaryOrchestrator();
		$processed    = $orchestrator->processPendingRequests( 10 );

		error_log( sprintf( '[SAGA][SUMMARY][CRON] Processed %d pending requests', $processed ) );
	}
);
