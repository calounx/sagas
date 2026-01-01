<?php
/**
 * Extraction Repository
 *
 * Data access layer for entity extraction feature.
 * Handles CRUD operations for extraction jobs, extracted entities, and duplicate matches.
 * Uses WordPress $wpdb with proper table prefix support and prepared statements.
 *
 * @package SagaManager
 * @subpackage AI\EntityExtractor\Repository
 * @since 1.4.0
 */

declare(strict_types=1);

namespace SagaManager\AI\EntityExtractor;

use SagaManager\AI\EntityExtractor\Entities\ExtractionJob;
use SagaManager\AI\EntityExtractor\Entities\ExtractedEntity;
use SagaManager\AI\EntityExtractor\Entities\DuplicateMatch;
use SagaManager\AI\EntityExtractor\Entities\JobStatus;
use SagaManager\AI\EntityExtractor\Entities\ExtractedEntityStatus;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Extraction Repository
 *
 * WordPress database integration for entity extraction.
 */
class ExtractionRepository
{
    private string $jobs_table;
    private string $entities_table;
    private string $duplicates_table;
    private int $cache_ttl = 300; // 5 minutes

    /**
     * Constructor
     */
    public function __construct()
    {
        global $wpdb;
        $this->jobs_table = $wpdb->prefix . 'saga_extraction_jobs';
        $this->entities_table = $wpdb->prefix . 'saga_extracted_entities';
        $this->duplicates_table = $wpdb->prefix . 'saga_extraction_duplicates';
    }

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
    public function createJob(ExtractionJob $job): ExtractionJob
    {
        global $wpdb;

        $data = $job->toArray();
        unset($data['id']); // Let DB auto-increment

        $result = $wpdb->insert($this->jobs_table, $data);

        if ($result === false) {
            error_log(sprintf(
                '[SAGA][EXTRACTOR][ERROR] Failed to create job: %s',
                $wpdb->last_error
            ));
            throw new \Exception('Failed to create extraction job: ' . $wpdb->last_error);
        }

        $job_id = $wpdb->insert_id;

        error_log(sprintf('[SAGA][EXTRACTOR] Created extraction job #%d', $job_id));

        // Clear cache
        wp_cache_delete("extraction_job_{$job_id}", 'saga');

        // Return job with ID
        $data['id'] = $job_id;
        return ExtractionJob::fromArray($data);
    }

    /**
     * Update existing extraction job
     *
     * @param ExtractionJob $job Job object with ID
     * @return bool Success
     * @throws \Exception If job ID is null or update fails
     */
    public function updateJob(ExtractionJob $job): bool
    {
        global $wpdb;

        if ($job->id === null) {
            throw new \Exception('Cannot update job without ID');
        }

        $data = $job->toArray();
        $job_id = $data['id'];
        unset($data['id']); // Don't update ID column

        $result = $wpdb->update(
            $this->jobs_table,
            $data,
            ['id' => $job_id],
            null,
            ['%d']
        );

        if ($result === false) {
            error_log(sprintf(
                '[SAGA][EXTRACTOR][ERROR] Failed to update job #%d: %s',
                $job_id,
                $wpdb->last_error
            ));
            throw new \Exception('Failed to update extraction job: ' . $wpdb->last_error);
        }

        // Clear cache
        wp_cache_delete("extraction_job_{$job_id}", 'saga');

        return true;
    }

    /**
     * Find job by ID
     *
     * @param int $job_id Job ID
     * @return ExtractionJob|null
     */
    public function findJobById(int $job_id): ?ExtractionJob
    {
        global $wpdb;

        // Check cache
        $cache_key = "extraction_job_{$job_id}";
        $cached = wp_cache_get($cache_key, 'saga');

        if ($cached !== false) {
            return $cached;
        }

        $query = $wpdb->prepare(
            "SELECT * FROM {$this->jobs_table} WHERE id = %d",
            $job_id
        );

        $row = $wpdb->get_row($query, ARRAY_A);

        if (!$row) {
            return null;
        }

        $job = ExtractionJob::fromArray($row);

        // Cache result
        wp_cache_set($cache_key, $job, 'saga', $this->cache_ttl);

        return $job;
    }

    /**
     * Find jobs by saga ID
     *
     * @param int $saga_id Saga ID
     * @param int $limit Maximum results
     * @param int $offset Offset for pagination
     * @return array Array of ExtractionJob objects
     */
    public function findJobsBySaga(int $saga_id, int $limit = 50, int $offset = 0): array
    {
        global $wpdb;

        $query = $wpdb->prepare(
            "SELECT * FROM {$this->jobs_table}
             WHERE saga_id = %d
             ORDER BY created_at DESC
             LIMIT %d OFFSET %d",
            $saga_id,
            $limit,
            $offset
        );

        $rows = $wpdb->get_results($query, ARRAY_A);

        return array_map(fn($row) => ExtractionJob::fromArray($row), $rows);
    }

    /**
     * Find jobs by status
     *
     * @param JobStatus $status Job status
     * @param int $limit Maximum results
     * @return array Array of ExtractionJob objects
     */
    public function findJobsByStatus(JobStatus $status, int $limit = 50): array
    {
        global $wpdb;

        $query = $wpdb->prepare(
            "SELECT * FROM {$this->jobs_table}
             WHERE status = %s
             ORDER BY created_at DESC
             LIMIT %d",
            $status->value,
            $limit
        );

        $rows = $wpdb->get_results($query, ARRAY_A);

        return array_map(fn($row) => ExtractionJob::fromArray($row), $rows);
    }

    /**
     * Delete job and all related entities
     *
     * @param int $job_id Job ID
     * @return bool Success
     */
    public function deleteJob(int $job_id): bool
    {
        global $wpdb;

        $wpdb->query('START TRANSACTION');

        try {
            // Delete duplicates first (foreign key constraint)
            $wpdb->delete(
                $this->duplicates_table,
                ['extracted_entity_id' => $wpdb->prepare('IN (SELECT id FROM %i WHERE job_id = %d)', $this->entities_table, $job_id)],
                ['%d']
            );

            // Delete extracted entities
            $wpdb->delete($this->entities_table, ['job_id' => $job_id], ['%d']);

            // Delete job
            $result = $wpdb->delete($this->jobs_table, ['id' => $job_id], ['%d']);

            if ($result === false) {
                throw new \Exception('Failed to delete job');
            }

            $wpdb->query('COMMIT');

            // Clear cache
            wp_cache_delete("extraction_job_{$job_id}", 'saga');

            error_log(sprintf('[SAGA][EXTRACTOR] Deleted job #%d and related data', $job_id));

            return true;

        } catch (\Exception $e) {
            $wpdb->query('ROLLBACK');
            error_log(sprintf(
                '[SAGA][EXTRACTOR][ERROR] Failed to delete job #%d: %s',
                $job_id,
                $e->getMessage()
            ));
            return false;
        }
    }

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
    public function createEntity(ExtractedEntity $entity): ExtractedEntity
    {
        global $wpdb;

        $data = $entity->toArray();
        unset($data['id']);

        $result = $wpdb->insert($this->entities_table, $data);

        if ($result === false) {
            error_log(sprintf(
                '[SAGA][EXTRACTOR][ERROR] Failed to create entity "%s": %s',
                $entity->canonical_name,
                $wpdb->last_error
            ));
            throw new \Exception('Failed to create extracted entity: ' . $wpdb->last_error);
        }

        $entity_id = $wpdb->insert_id;

        $data['id'] = $entity_id;
        return ExtractedEntity::fromArray($data);
    }

    /**
     * Batch create extracted entities
     *
     * @param array $entities Array of ExtractedEntity objects
     * @return array Array of created ExtractedEntity objects with IDs
     * @throws \Exception If batch creation fails
     */
    public function batchCreateEntities(array $entities): array
    {
        global $wpdb;

        if (empty($entities)) {
            return [];
        }

        $wpdb->query('START TRANSACTION');

        try {
            $created = [];

            foreach ($entities as $entity) {
                if (!($entity instanceof ExtractedEntity)) {
                    continue;
                }

                $data = $entity->toArray();
                unset($data['id']);

                $result = $wpdb->insert($this->entities_table, $data);

                if ($result === false) {
                    throw new \Exception('Failed to insert entity: ' . $wpdb->last_error);
                }

                $data['id'] = $wpdb->insert_id;
                $created[] = ExtractedEntity::fromArray($data);
            }

            $wpdb->query('COMMIT');

            error_log(sprintf(
                '[SAGA][EXTRACTOR] Batch created %d extracted entities',
                count($created)
            ));

            return $created;

        } catch (\Exception $e) {
            $wpdb->query('ROLLBACK');
            error_log(sprintf(
                '[SAGA][EXTRACTOR][ERROR] Batch entity creation failed: %s',
                $e->getMessage()
            ));
            throw $e;
        }
    }

    /**
     * Update extracted entity
     *
     * @param ExtractedEntity $entity Entity with ID
     * @return bool Success
     * @throws \Exception If entity ID is null or update fails
     */
    public function updateEntity(ExtractedEntity $entity): bool
    {
        global $wpdb;

        if ($entity->id === null) {
            throw new \Exception('Cannot update entity without ID');
        }

        $data = $entity->toArray();
        $entity_id = $data['id'];
        unset($data['id']);

        $result = $wpdb->update(
            $this->entities_table,
            $data,
            ['id' => $entity_id],
            null,
            ['%d']
        );

        if ($result === false) {
            error_log(sprintf(
                '[SAGA][EXTRACTOR][ERROR] Failed to update entity #%d: %s',
                $entity_id,
                $wpdb->last_error
            ));
            throw new \Exception('Failed to update extracted entity: ' . $wpdb->last_error);
        }

        return true;
    }

    /**
     * Find entity by ID
     *
     * @param int $entity_id Entity ID
     * @return ExtractedEntity|null
     */
    public function findEntityById(int $entity_id): ?ExtractedEntity
    {
        global $wpdb;

        $query = $wpdb->prepare(
            "SELECT * FROM {$this->entities_table} WHERE id = %d",
            $entity_id
        );

        $row = $wpdb->get_row($query, ARRAY_A);

        if (!$row) {
            return null;
        }

        return ExtractedEntity::fromArray($row);
    }

    /**
     * Find entities by job ID
     *
     * @param int $job_id Job ID
     * @return array Array of ExtractedEntity objects
     */
    public function findEntitiesByJob(int $job_id): array
    {
        global $wpdb;

        $query = $wpdb->prepare(
            "SELECT * FROM {$this->entities_table}
             WHERE job_id = %d
             ORDER BY chunk_index ASC, position_in_text ASC",
            $job_id
        );

        $rows = $wpdb->get_results($query, ARRAY_A);

        return array_map(fn($row) => ExtractedEntity::fromArray($row), $rows);
    }

    /**
     * Find entities by status
     *
     * @param int $job_id Job ID
     * @param ExtractedEntityStatus $status Entity status
     * @return array Array of ExtractedEntity objects
     */
    public function findEntitiesByStatus(int $job_id, ExtractedEntityStatus $status): array
    {
        global $wpdb;

        $query = $wpdb->prepare(
            "SELECT * FROM {$this->entities_table}
             WHERE job_id = %d AND status = %s
             ORDER BY confidence_score DESC",
            $job_id,
            $status->value
        );

        $rows = $wpdb->get_results($query, ARRAY_A);

        return array_map(fn($row) => ExtractedEntity::fromArray($row), $rows);
    }

    /**
     * Find pending entities (awaiting review)
     *
     * @param int $job_id Job ID
     * @return array Array of ExtractedEntity objects
     */
    public function findPendingEntities(int $job_id): array
    {
        return $this->findEntitiesByStatus($job_id, ExtractedEntityStatus::PENDING);
    }

    /**
     * Count entities by status
     *
     * @param int $job_id Job ID
     * @return array Associative array [status => count]
     */
    public function countEntitiesByStatus(int $job_id): array
    {
        global $wpdb;

        $query = $wpdb->prepare(
            "SELECT status, COUNT(*) as count
             FROM {$this->entities_table}
             WHERE job_id = %d
             GROUP BY status",
            $job_id
        );

        $rows = $wpdb->get_results($query, ARRAY_A);

        $counts = [
            'pending' => 0,
            'approved' => 0,
            'rejected' => 0,
            'duplicate' => 0,
            'created' => 0,
        ];

        foreach ($rows as $row) {
            $counts[$row['status']] = (int)$row['count'];
        }

        return $counts;
    }

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
    public function createDuplicateMatch(DuplicateMatch $match): DuplicateMatch
    {
        global $wpdb;

        $data = $match->toArray();
        unset($data['id']);

        $result = $wpdb->insert($this->duplicates_table, $data);

        if ($result === false) {
            error_log(sprintf(
                '[SAGA][EXTRACTOR][ERROR] Failed to create duplicate match: %s',
                $wpdb->last_error
            ));
            throw new \Exception('Failed to create duplicate match: ' . $wpdb->last_error);
        }

        $match_id = $wpdb->insert_id;

        $data['id'] = $match_id;
        return DuplicateMatch::fromArray($data);
    }

    /**
     * Batch create duplicate matches
     *
     * @param array $matches Array of DuplicateMatch objects
     * @return array Array of created DuplicateMatch objects with IDs
     * @throws \Exception If batch creation fails
     */
    public function batchCreateDuplicates(array $matches): array
    {
        global $wpdb;

        if (empty($matches)) {
            return [];
        }

        $wpdb->query('START TRANSACTION');

        try {
            $created = [];

            foreach ($matches as $match) {
                if (!($match instanceof DuplicateMatch)) {
                    continue;
                }

                $data = $match->toArray();
                unset($data['id']);

                $result = $wpdb->insert($this->duplicates_table, $data);

                if ($result === false) {
                    throw new \Exception('Failed to insert duplicate: ' . $wpdb->last_error);
                }

                $data['id'] = $wpdb->insert_id;
                $created[] = DuplicateMatch::fromArray($data);
            }

            $wpdb->query('COMMIT');

            error_log(sprintf(
                '[SAGA][EXTRACTOR] Batch created %d duplicate matches',
                count($created)
            ));

            return $created;

        } catch (\Exception $e) {
            $wpdb->query('ROLLBACK');
            error_log(sprintf(
                '[SAGA][EXTRACTOR][ERROR] Batch duplicate creation failed: %s',
                $e->getMessage()
            ));
            throw $e;
        }
    }

    /**
     * Update duplicate match
     *
     * @param DuplicateMatch $match Match with ID
     * @return bool Success
     * @throws \Exception If match ID is null or update fails
     */
    public function updateDuplicateMatch(DuplicateMatch $match): bool
    {
        global $wpdb;

        if ($match->id === null) {
            throw new \Exception('Cannot update duplicate match without ID');
        }

        $data = $match->toArray();
        $match_id = $data['id'];
        unset($data['id']);

        $result = $wpdb->update(
            $this->duplicates_table,
            $data,
            ['id' => $match_id],
            null,
            ['%d']
        );

        if ($result === false) {
            error_log(sprintf(
                '[SAGA][EXTRACTOR][ERROR] Failed to update duplicate match #%d: %s',
                $match_id,
                $wpdb->last_error
            ));
            throw new \Exception('Failed to update duplicate match: ' . $wpdb->last_error);
        }

        return true;
    }

    /**
     * Find duplicate matches for extracted entity
     *
     * @param int $extracted_entity_id Extracted entity ID
     * @return array Array of DuplicateMatch objects
     */
    public function findDuplicatesByEntity(int $extracted_entity_id): array
    {
        global $wpdb;

        $query = $wpdb->prepare(
            "SELECT * FROM {$this->duplicates_table}
             WHERE extracted_entity_id = %d
             ORDER BY similarity_score DESC",
            $extracted_entity_id
        );

        $rows = $wpdb->get_results($query, ARRAY_A);

        return array_map(fn($row) => DuplicateMatch::fromArray($row), $rows);
    }

    /**
     * Find all duplicate matches for a job
     *
     * @param int $job_id Job ID
     * @return array Array of DuplicateMatch objects
     */
    public function findDuplicatesByJob(int $job_id): array
    {
        global $wpdb;

        $query = $wpdb->prepare(
            "SELECT d.*
             FROM {$this->duplicates_table} d
             INNER JOIN {$this->entities_table} e ON d.extracted_entity_id = e.id
             WHERE e.job_id = %d
             ORDER BY d.similarity_score DESC",
            $job_id
        );

        $rows = $wpdb->get_results($query, ARRAY_A);

        return array_map(fn($row) => DuplicateMatch::fromArray($row), $rows);
    }

    /**
     * Count pending duplicate reviews for job
     *
     * @param int $job_id Job ID
     * @return int Count
     */
    public function countPendingDuplicates(int $job_id): int
    {
        global $wpdb;

        $query = $wpdb->prepare(
            "SELECT COUNT(*)
             FROM {$this->duplicates_table} d
             INNER JOIN {$this->entities_table} e ON d.extracted_entity_id = e.id
             WHERE e.job_id = %d AND d.user_action = 'pending'",
            $job_id
        );

        return (int)$wpdb->get_var($query);
    }

    // =========================================================================
    // STATISTICS & ANALYTICS
    // =========================================================================

    /**
     * Get job statistics
     *
     * @param int $job_id Job ID
     * @return array Statistics array
     */
    public function getJobStatistics(int $job_id): array
    {
        global $wpdb;

        $entity_counts = $this->countEntitiesByStatus($job_id);
        $duplicate_count = $this->countPendingDuplicates($job_id);

        return [
            'total_entities' => array_sum($entity_counts),
            'pending_review' => $entity_counts['pending'],
            'approved' => $entity_counts['approved'],
            'rejected' => $entity_counts['rejected'],
            'created' => $entity_counts['created'],
            'duplicates_found' => $entity_counts['duplicate'],
            'pending_duplicate_reviews' => $duplicate_count,
        ];
    }

    /**
     * Update job statistics from current entity counts
     *
     * Synchronizes job record with actual entity counts
     *
     * @param int $job_id Job ID
     * @return bool Success
     */
    public function syncJobStatistics(int $job_id): bool
    {
        $stats = $this->getJobStatistics($job_id);
        $job = $this->findJobById($job_id);

        if (!$job) {
            return false;
        }

        global $wpdb;

        $result = $wpdb->update(
            $this->jobs_table,
            [
                'total_entities_found' => $stats['total_entities'],
                'entities_created' => $stats['created'],
                'entities_rejected' => $stats['rejected'],
                'duplicates_found' => $stats['duplicates_found'],
            ],
            ['id' => $job_id],
            ['%d', '%d', '%d', '%d'],
            ['%d']
        );

        // Clear cache
        wp_cache_delete("extraction_job_{$job_id}", 'saga');

        return $result !== false;
    }
}
