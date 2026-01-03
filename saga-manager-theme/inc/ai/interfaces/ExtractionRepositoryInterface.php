<?php
/**
 * Extraction Repository Interface
 *
 * Public contract for entity extraction data access layer.
 * Defines operations for extraction jobs, extracted entities, and duplicate detection.
 *
 * @package SagaManager
 * @subpackage AI\Interfaces
 * @since 1.5.0
 */

declare(strict_types=1);

namespace SagaManager\AI\Interfaces;

use SagaManager\AI\EntityExtractor\Entities\ExtractionJob;
use SagaManager\AI\EntityExtractor\Entities\ExtractedEntity;
use SagaManager\AI\EntityExtractor\Entities\DuplicateMatch;
use SagaManager\AI\EntityExtractor\Entities\JobStatus;
use SagaManager\AI\EntityExtractor\Entities\ExtractedEntityStatus;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Extraction Repository Interface
 *
 * Dependency Inversion Principle: Extraction services depend on this interface,
 * not on concrete MariaDB implementation.
 */
interface ExtractionRepositoryInterface
{
    // =========================================================================
    // EXTRACTION JOBS
    // =========================================================================

    /**
     * Create new extraction job
     *
     * @param ExtractionJob $job Job object (id should be null)
     * @return ExtractionJob Job with assigned ID
     * @throws \Exception If creation fails
     */
    public function createJob(ExtractionJob $job): ExtractionJob;

    /**
     * Update existing extraction job
     *
     * @param ExtractionJob $job Job object with ID
     * @return bool Success
     * @throws \Exception If job ID is null or update fails
     */
    public function updateJob(ExtractionJob $job): bool;

    /**
     * Find job by ID
     *
     * @param int $job_id Job ID
     * @return ExtractionJob|null
     */
    public function findJobById(int $job_id): ?ExtractionJob;

    /**
     * Find jobs by saga ID
     *
     * @param int $saga_id Saga ID
     * @param int $limit Maximum results
     * @param int $offset Offset for pagination
     * @return array Array of ExtractionJob objects
     */
    public function findJobsBySaga(int $saga_id, int $limit = 50, int $offset = 0): array;

    /**
     * Find jobs by status
     *
     * @param JobStatus $status Job status
     * @param int $limit Maximum results
     * @return array Array of ExtractionJob objects
     */
    public function findJobsByStatus(JobStatus $status, int $limit = 50): array;

    /**
     * Delete job and all related entities
     *
     * @param int $job_id Job ID
     * @return bool Success
     */
    public function deleteJob(int $job_id): bool;

    // =========================================================================
    // EXTRACTED ENTITIES
    // =========================================================================

    /**
     * Create extracted entity
     *
     * @param ExtractedEntity $entity Entity object (id should be null)
     * @return ExtractedEntity Entity with assigned ID
     * @throws \Exception If creation fails
     */
    public function createEntity(ExtractedEntity $entity): ExtractedEntity;

    /**
     * Batch create extracted entities
     *
     * @param array $entities Array of ExtractedEntity objects
     * @return array Array of created ExtractedEntity objects with IDs
     * @throws \Exception If batch creation fails
     */
    public function batchCreateEntities(array $entities): array;

    /**
     * Update extracted entity
     *
     * @param ExtractedEntity $entity Entity with ID
     * @return bool Success
     * @throws \Exception If entity ID is null or update fails
     */
    public function updateEntity(ExtractedEntity $entity): bool;

    /**
     * Find entity by ID
     *
     * @param int $entity_id Entity ID
     * @return ExtractedEntity|null
     */
    public function findEntityById(int $entity_id): ?ExtractedEntity;

    /**
     * Find entities by job ID
     *
     * @param int $job_id Job ID
     * @return array Array of ExtractedEntity objects
     */
    public function findEntitiesByJob(int $job_id): array;

    /**
     * Find entities by status
     *
     * @param int $job_id Job ID
     * @param ExtractedEntityStatus $status Entity status
     * @return array Array of ExtractedEntity objects
     */
    public function findEntitiesByStatus(int $job_id, ExtractedEntityStatus $status): array;

    /**
     * Find pending entities (awaiting review)
     *
     * @param int $job_id Job ID
     * @return array Array of ExtractedEntity objects
     */
    public function findPendingEntities(int $job_id): array;

    /**
     * Count entities by status
     *
     * @param int $job_id Job ID
     * @return array Associative array [status => count]
     */
    public function countEntitiesByStatus(int $job_id): array;

    // =========================================================================
    // DUPLICATE MATCHES
    // =========================================================================

    /**
     * Create duplicate match
     *
     * @param DuplicateMatch $match Match object (id should be null)
     * @return DuplicateMatch Match with assigned ID
     * @throws \Exception If creation fails
     */
    public function createDuplicateMatch(DuplicateMatch $match): DuplicateMatch;

    /**
     * Batch create duplicate matches
     *
     * @param array $matches Array of DuplicateMatch objects
     * @return array Array of created DuplicateMatch objects with IDs
     * @throws \Exception If batch creation fails
     */
    public function batchCreateDuplicates(array $matches): array;

    /**
     * Update duplicate match
     *
     * @param DuplicateMatch $match Match with ID
     * @return bool Success
     * @throws \Exception If match ID is null or update fails
     */
    public function updateDuplicateMatch(DuplicateMatch $match): bool;

    /**
     * Find duplicate matches for extracted entity
     *
     * @param int $extracted_entity_id Extracted entity ID
     * @return array Array of DuplicateMatch objects
     */
    public function findDuplicatesByEntity(int $extracted_entity_id): array;

    /**
     * Find all duplicate matches for a job
     *
     * @param int $job_id Job ID
     * @return array Array of DuplicateMatch objects
     */
    public function findDuplicatesByJob(int $job_id): array;

    /**
     * Count pending duplicate reviews for job
     *
     * @param int $job_id Job ID
     * @return int Count
     */
    public function countPendingDuplicates(int $job_id): int;

    // =========================================================================
    // STATISTICS & ANALYTICS
    // =========================================================================

    /**
     * Get job statistics
     *
     * @param int $job_id Job ID
     * @return array Statistics ['total_entities', 'pending_review', 'approved', ...]
     */
    public function getJobStatistics(int $job_id): array;

    /**
     * Update job statistics from current entity counts
     *
     * Synchronizes job record with actual entity counts
     *
     * @param int $job_id Job ID
     * @return bool Success
     */
    public function syncJobStatistics(int $job_id): bool;
}
