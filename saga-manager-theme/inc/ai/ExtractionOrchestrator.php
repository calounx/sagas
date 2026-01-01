<?php
/**
 * Extraction Orchestrator
 *
 * High-level service that orchestrates the complete entity extraction workflow:
 * 1. Create extraction job
 * 2. Extract entities using AI
 * 3. Save extracted entities to database
 * 4. Run duplicate detection
 * 5. Save duplicate matches
 * 6. Update job status and statistics
 *
 * Handles workflow state transitions, error recovery, and progress tracking.
 *
 * @package SagaManager
 * @subpackage AI\EntityExtractor
 * @since 1.4.0
 */

declare(strict_types=1);

namespace SagaManager\AI\EntityExtractor;

use SagaManager\AI\EntityExtractor\Entities\ExtractionJob;
use SagaManager\AI\EntityExtractor\Entities\ExtractedEntity;
use SagaManager\AI\EntityExtractor\Entities\JobStatus;
use SagaManager\AI\EntityExtractor\Entities\SourceType;
use SagaManager\AI\AIClient;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Extraction Orchestrator
 *
 * Coordinates the entire extraction workflow from start to finish.
 */
class ExtractionOrchestrator
{
    private ExtractionRepository $repository;
    private EntityExtractionService $extraction_service;
    private DuplicateDetectionService $duplicate_service;
    private BatchEntityCreationService $creation_service;

    /**
     * Constructor
     *
     * @param ExtractionRepository|null $repository Optional repository
     * @param EntityExtractionService|null $extraction_service Optional extraction service
     * @param DuplicateDetectionService|null $duplicate_service Optional duplicate service
     * @param BatchEntityCreationService|null $creation_service Optional creation service
     */
    public function __construct(
        ?ExtractionRepository $repository = null,
        ?EntityExtractionService $extraction_service = null,
        ?DuplicateDetectionService $duplicate_service = null,
        ?BatchEntityCreationService $creation_service = null
    ) {
        $this->repository = $repository ?? new ExtractionRepository();
        $this->extraction_service = $extraction_service ?? new EntityExtractionService();
        $this->duplicate_service = $duplicate_service ?? new DuplicateDetectionService();
        $this->creation_service = $creation_service ?? new BatchEntityCreationService($this->repository);
    }

    /**
     * Start extraction workflow
     *
     * Creates job and begins extraction process
     *
     * @param int $saga_id Target saga ID
     * @param string $source_text Text to extract from
     * @param int $user_id User initiating extraction
     * @param array $options Extraction options
     * @return ExtractionJob Created job
     * @throws \Exception If job creation fails
     */
    public function startExtraction(
        int $saga_id,
        string $source_text,
        int $user_id,
        array $options = []
    ): ExtractionJob {
        // Validate input
        if (empty(trim($source_text))) {
            throw new \InvalidArgumentException('Source text cannot be empty');
        }

        // Calculate chunks
        $chunk_size = $options['chunk_size'] ?? 5000;
        $chunks = $this->calculateChunks($source_text, $chunk_size);

        // Estimate cost and time
        $estimates = $this->extraction_service->estimateCost($source_text);

        // Create job
        $job = new ExtractionJob(
            id: null,
            saga_id: $saga_id,
            user_id: $user_id,
            source_text: $source_text,
            source_type: SourceType::from($options['source_type'] ?? 'manual'),
            chunk_size: $chunk_size,
            total_chunks: count($chunks),
            processed_chunks: 0,
            status: JobStatus::PENDING,
            total_entities_found: 0,
            entities_created: 0,
            entities_rejected: 0,
            duplicates_found: 0,
            ai_provider: $options['ai_provider'] ?? 'openai',
            ai_model: $options['ai_model'] ?? 'gpt-4',
            accuracy_score: null,
            processing_time_ms: null,
            api_cost_usd: null,
            error_message: null,
            metadata: [
                'estimated_cost_usd' => $estimates['estimated_cost_usd'],
                'estimated_time_seconds' => $estimates['processing_time_seconds'],
                'estimated_entities' => $estimates['estimated_entities'],
            ],
            created_at: time(),
            started_at: null,
            completed_at: null
        );

        $job = $this->repository->createJob($job);

        error_log(sprintf(
            '[SAGA][EXTRACTOR] Started extraction job #%d for saga #%d (%d chunks)',
            $job->id,
            $saga_id,
            count($chunks)
        ));

        return $job;
    }

    /**
     * Process extraction job
     *
     * Executes the full extraction workflow:
     * - Extract entities using AI
     * - Save to database
     * - Detect duplicates
     * - Update job status
     *
     * @param int $job_id Job ID to process
     * @param callable|null $progress_callback Optional callback(job_id, progress_percent, message)
     * @return ExtractionJob Updated job
     * @throws \Exception If processing fails
     */
    public function processJob(int $job_id, ?callable $progress_callback = null): ExtractionJob
    {
        $start_time = microtime(true);

        $job = $this->repository->findJobById($job_id);

        if (!$job) {
            throw new \Exception("Job #{$job_id} not found");
        }

        if (!$job->status->canProcess()) {
            throw new \Exception("Job #{$job_id} cannot be processed (status: {$job->status->value})");
        }

        try {
            // Update job to PROCESSING
            $job = $job->withStatus(JobStatus::PROCESSING);
            $this->repository->updateJob($job);

            $this->notifyProgress($progress_callback, $job_id, 5, 'Starting extraction...');

            // 1. Extract entities from text
            $extracted_entities = $this->extraction_service->extractEntities(
                $job->source_text,
                $job_id,
                [
                    'chunk_size' => $job->chunk_size,
                ]
            );

            $this->notifyProgress($progress_callback, $job_id, 40, sprintf(
                'Extracted %d entities',
                count($extracted_entities)
            ));

            // 2. Save extracted entities to database
            $saved_entities = $this->repository->batchCreateEntities($extracted_entities);

            $this->notifyProgress($progress_callback, $job_id, 60, 'Detecting duplicates...');

            // 3. Detect duplicates
            $duplicate_matches = $this->duplicate_service->batchFindDuplicates(
                $saved_entities,
                $job->saga_id
            );

            // Flatten matches array
            $all_matches = [];
            foreach ($duplicate_matches as $entity_matches) {
                // Update extracted entities with duplicate info
                foreach ($entity_matches as $match) {
                    $all_matches[] = $match;

                    // Mark entity as duplicate if high confidence
                    if ($match->isHighConfidence()) {
                        $entity_key = array_search($match->extracted_entity_id, array_column($saved_entities, 'id'));
                        if ($entity_key !== false) {
                            $entity = $saved_entities[$entity_key];
                            $updated = $entity->markDuplicate(
                                $match->existing_entity_id,
                                $match->similarity_score
                            );
                            $this->repository->updateEntity($updated);
                        }
                    }
                }
            }

            // 4. Save duplicate matches
            if (!empty($all_matches)) {
                $this->repository->batchCreateDuplicates($all_matches);
            }

            $this->notifyProgress($progress_callback, $job_id, 80, 'Calculating quality metrics...');

            // 5. Calculate quality metrics
            $quality_metrics = $this->extraction_service->validateExtractionQuality($saved_entities);

            // 6. Update job with final statistics
            $processing_time_ms = (int)((microtime(true) - $start_time) * 1000);

            $job_data = $job->toArray();
            $job_data['status'] = JobStatus::COMPLETED->value;
            $job_data['total_entities_found'] = count($saved_entities);
            $job_data['duplicates_found'] = count($all_matches);
            $job_data['processed_chunks'] = $job->total_chunks;
            $job_data['accuracy_score'] = $quality_metrics['quality_score'];
            $job_data['processing_time_ms'] = $processing_time_ms;
            $job_data['completed_at'] = date('Y-m-d H:i:s');

            $job = ExtractionJob::fromArray($job_data);
            $this->repository->updateJob($job);

            $this->notifyProgress($progress_callback, $job_id, 100, 'Extraction complete');

            error_log(sprintf(
                '[SAGA][EXTRACTOR] Completed job #%d: %d entities, %d duplicates, %.2fs',
                $job_id,
                count($saved_entities),
                count($all_matches),
                $processing_time_ms / 1000
            ));

            return $job;

        } catch (\Exception $e) {
            // Mark job as failed
            $job_data = $job->toArray();
            $job_data['status'] = JobStatus::FAILED->value;
            $job_data['error_message'] = $e->getMessage();
            $job_data['completed_at'] = date('Y-m-d H:i:s');

            $job = ExtractionJob::fromArray($job_data);
            $this->repository->updateJob($job);

            error_log(sprintf(
                '[SAGA][EXTRACTOR][ERROR] Job #%d failed: %s',
                $job_id,
                $e->getMessage()
            ));

            $this->notifyProgress($progress_callback, $job_id, 0, 'Extraction failed: ' . $e->getMessage());

            throw $e;
        }
    }

    /**
     * Approve and create entities from extraction job
     *
     * @param int $job_id Job ID
     * @param array $entity_ids Entity IDs to approve and create (empty = all pending)
     * @param int $user_id User approving entities
     * @return array Creation results
     * @throws \Exception If operation fails
     */
    public function approveAndCreateEntities(int $job_id, array $entity_ids, int $user_id): array
    {
        $job = $this->repository->findJobById($job_id);

        if (!$job) {
            throw new \Exception("Job #{$job_id} not found");
        }

        // Get entities to approve
        if (empty($entity_ids)) {
            // Approve all pending entities
            $entities = $this->repository->findPendingEntities($job_id);
        } else {
            // Approve specific entities
            $entities = array_filter(
                array_map(
                    fn($id) => $this->repository->findEntityById($id),
                    $entity_ids
                ),
                fn($entity) => $entity !== null && $entity->job_id === $job_id
            );
        }

        if (empty($entities)) {
            return [
                'success' => [],
                'failed' => [],
                'total' => 0,
                'created' => 0,
                'errors' => 0,
            ];
        }

        // Approve entities
        $approved = [];
        foreach ($entities as $entity) {
            $updated = $entity->approve($user_id);
            $this->repository->updateEntity($updated);
            $approved[] = $updated;
        }

        error_log(sprintf(
            '[SAGA][EXTRACTOR] Approved %d entities from job #%d',
            count($approved),
            $job_id
        ));

        // Create saga entities
        $results = $this->creation_service->createEntities($approved, $job->saga_id);

        // Update job statistics
        $this->repository->syncJobStatistics($job_id);

        return $results;
    }

    /**
     * Reject entities from extraction job
     *
     * @param int $job_id Job ID
     * @param array $entity_ids Entity IDs to reject
     * @param int $user_id User rejecting entities
     * @return int Number of entities rejected
     * @throws \Exception If operation fails
     */
    public function rejectEntities(int $job_id, array $entity_ids, int $user_id): int
    {
        $job = $this->repository->findJobById($job_id);

        if (!$job) {
            throw new \Exception("Job #{$job_id} not found");
        }

        $rejected_count = 0;

        foreach ($entity_ids as $entity_id) {
            $entity = $this->repository->findEntityById($entity_id);

            if (!$entity || $entity->job_id !== $job_id) {
                continue;
            }

            $updated = $entity->reject($user_id);
            $this->repository->updateEntity($updated);
            $rejected_count++;
        }

        // Update job statistics
        $this->repository->syncJobStatistics($job_id);

        error_log(sprintf(
            '[SAGA][EXTRACTOR] Rejected %d entities from job #%d',
            $rejected_count,
            $job_id
        ));

        return $rejected_count;
    }

    /**
     * Cancel extraction job
     *
     * @param int $job_id Job ID
     * @return bool Success
     * @throws \Exception If cancellation fails
     */
    public function cancelJob(int $job_id): bool
    {
        $job = $this->repository->findJobById($job_id);

        if (!$job) {
            throw new \Exception("Job #{$job_id} not found");
        }

        if ($job->status->isFinal()) {
            throw new \Exception("Cannot cancel job in final state: {$job->status->value}");
        }

        $job = $job->withStatus(JobStatus::CANCELLED);
        $this->repository->updateJob($job);

        error_log(sprintf('[SAGA][EXTRACTOR] Cancelled job #%d', $job_id));

        return true;
    }

    /**
     * Get job progress information
     *
     * @param int $job_id Job ID
     * @return array Progress data
     */
    public function getJobProgress(int $job_id): array
    {
        $job = $this->repository->findJobById($job_id);

        if (!$job) {
            throw new \Exception("Job #{$job_id} not found");
        }

        $stats = $this->repository->getJobStatistics($job_id);

        return [
            'job_id' => $job_id,
            'status' => $job->status->value,
            'progress_percent' => $job->getProgressPercentage(),
            'entities_found' => $stats['total_entities'],
            'pending_review' => $stats['pending_review'],
            'approved' => $stats['approved'],
            'rejected' => $stats['rejected'],
            'created' => $stats['created'],
            'duplicates_found' => $stats['duplicates_found'],
            'pending_duplicate_reviews' => $stats['pending_duplicate_reviews'],
            'processing_time_seconds' => $job->getProcessingDuration(),
            'accuracy_score' => $job->accuracy_score,
            'is_complete' => $job->isComplete(),
            'is_successful' => $job->isSuccessful(),
            'error_message' => $job->error_message,
        ];
    }

    /**
     * Estimate extraction for text
     *
     * @param string $text Text to analyze
     * @param array $options Extraction options
     * @return array Estimates
     */
    public function estimateExtraction(string $text, array $options = []): array
    {
        return $this->extraction_service->estimateCost($text);
    }

    /**
     * Get extraction summary for saga
     *
     * @param int $saga_id Saga ID
     * @return array Summary statistics
     */
    public function getSagaExtractionSummary(int $saga_id): array
    {
        $jobs = $this->repository->findJobsBySaga($saga_id);

        $total_jobs = count($jobs);
        $total_entities_extracted = 0;
        $total_entities_created = 0;
        $total_duplicates = 0;
        $total_cost = 0;

        $status_counts = [
            'pending' => 0,
            'processing' => 0,
            'completed' => 0,
            'failed' => 0,
            'cancelled' => 0,
        ];

        foreach ($jobs as $job) {
            $total_entities_extracted += $job->total_entities_found;
            $total_entities_created += $job->entities_created;
            $total_duplicates += $job->duplicates_found;
            $total_cost += $job->api_cost_usd ?? 0;
            $status_counts[$job->status->value]++;
        }

        return [
            'saga_id' => $saga_id,
            'total_jobs' => $total_jobs,
            'total_entities_extracted' => $total_entities_extracted,
            'total_entities_created' => $total_entities_created,
            'total_duplicates_found' => $total_duplicates,
            'total_api_cost_usd' => round($total_cost, 2),
            'status_breakdown' => $status_counts,
            'acceptance_rate' => $total_entities_extracted > 0
                ? round(($total_entities_created / $total_entities_extracted) * 100, 2)
                : 0,
        ];
    }

    /**
     * Calculate text chunks
     *
     * @param string $text Text to chunk
     * @param int $chunk_size Chunk size
     * @return array Array of text chunks
     */
    private function calculateChunks(string $text, int $chunk_size): array
    {
        if (mb_strlen($text) <= $chunk_size) {
            return [$text];
        }

        $chunks = [];
        $sentences = preg_split('/(?<=[.!?])\s+/', $text, -1, PREG_SPLIT_NO_EMPTY);

        $current_chunk = '';
        foreach ($sentences as $sentence) {
            if (mb_strlen($current_chunk . ' ' . $sentence) > $chunk_size && !empty($current_chunk)) {
                $chunks[] = trim($current_chunk);
                $current_chunk = $sentence;
            } else {
                $current_chunk .= ($current_chunk ? ' ' : '') . $sentence;
            }
        }

        if (!empty($current_chunk)) {
            $chunks[] = trim($current_chunk);
        }

        return $chunks;
    }

    /**
     * Notify progress callback
     *
     * @param callable|null $callback Progress callback
     * @param int $job_id Job ID
     * @param int $percent Progress percentage
     * @param string $message Status message
     * @return void
     */
    private function notifyProgress(?callable $callback, int $job_id, int $percent, string $message): void
    {
        if ($callback !== null) {
            try {
                $callback($job_id, $percent, $message);
            } catch (\Exception $e) {
                error_log(sprintf(
                    '[SAGA][EXTRACTOR][ERROR] Progress callback failed: %s',
                    $e->getMessage()
                ));
            }
        }

        // Fire WordPress action
        do_action('saga_extraction_progress', $job_id, $percent, $message);
    }
}
