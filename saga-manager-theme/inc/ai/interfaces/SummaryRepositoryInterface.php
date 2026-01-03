<?php
/**
 * Summary Repository Interface
 *
 * Public contract for summary data access layer.
 * Defines operations for AI-generated summaries and summary requests.
 *
 * @package SagaManager
 * @subpackage AI\Interfaces
 * @since 1.5.0
 */

declare(strict_types=1);

namespace SagaManager\AI\Interfaces;

use SagaManager\AI\Entities\SummaryRequest;
use SagaManager\AI\Entities\GeneratedSummary;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Summary Repository Interface
 *
 * Dependency Inversion Principle: Domain layer depends on this interface,
 * not on concrete MariaDB implementation.
 */
interface SummaryRepositoryInterface
{
    /**
     * Create new summary
     *
     * @param GeneratedSummary $summary Summary object (id should be null)
     * @return int Summary ID
     * @throws \Exception If creation fails
     */
    public function create(GeneratedSummary $summary): int;

    /**
     * Find summary by ID
     *
     * @param int $id Summary ID
     * @return GeneratedSummary|null
     */
    public function findById(int $id): ?GeneratedSummary;

    /**
     * Find summary by request ID
     *
     * Returns the current (latest) version.
     *
     * @param int $request_id Request ID
     * @return GeneratedSummary|null
     */
    public function findByRequest(int $request_id): ?GeneratedSummary;

    /**
     * Find summary by cache key
     *
     * Checks if a matching summary already exists and is not expired.
     *
     * @param string $cache_key Cache key
     * @return GeneratedSummary|null
     */
    public function findByCacheKey(string $cache_key): ?GeneratedSummary;

    /**
     * Find summaries by saga
     *
     * @param int $saga_id Saga ID
     * @param array $filters Optional filters ['type', 'entity_id', 'is_current', 'limit']
     * @return array Array of GeneratedSummary objects
     */
    public function findBySaga(int $saga_id, array $filters = []): array;

    /**
     * Update summary version
     *
     * Marks old version as not current and creates new version.
     *
     * @param int $old_id Old summary ID to supersede
     * @param GeneratedSummary $new New summary version
     * @return int New summary ID
     * @throws \Exception If update fails
     */
    public function updateVersion(int $old_id, GeneratedSummary $new): int;

    /**
     * Get summary statistics for saga
     *
     * @param int $saga_id Saga ID
     * @return array Statistics ['total_summaries', 'by_type', 'avg_quality', ...]
     */
    public function getStatistics(int $saga_id): array;

    /**
     * Delete summary
     *
     * @param int $id Summary ID
     * @return bool Success
     */
    public function delete(int $id): bool;

    /**
     * Delete expired summaries
     *
     * Cleans up summaries past their cache expiration.
     *
     * @return int Number of deleted summaries
     */
    public function deleteExpired(): int;

    /**
     * Get version history for summary
     *
     * @param string $cache_key Cache key
     * @return array Array of GeneratedSummary objects (all versions)
     */
    public function getVersionHistory(string $cache_key): array;

    /**
     * Update quality metrics
     *
     * Updates quality and readability scores after regeneration or feedback.
     *
     * @param int $id Summary ID
     * @param float|null $quality_score Quality score (0-100)
     * @param float|null $readability_score Readability score
     * @return bool Success
     */
    public function updateQualityMetrics(
        int $id,
        ?float $quality_score = null,
        ?float $readability_score = null
    ): bool;

    /**
     * Search summaries by content
     *
     * Full-text search across summary text.
     *
     * @param int $saga_id Saga ID
     * @param string $search_term Search term
     * @param int $limit Result limit
     * @return array Array of GeneratedSummary objects
     */
    public function search(int $saga_id, string $search_term, int $limit = 20): array;

    /**
     * Create summary request
     *
     * @param SummaryRequest $request Request object
     * @return int Request ID
     * @throws \Exception If creation fails
     */
    public function createRequest(SummaryRequest $request): int;

    /**
     * Update summary request
     *
     * @param SummaryRequest $request Request object with ID
     * @return bool Success
     * @throws \Exception If update fails
     */
    public function updateRequest(SummaryRequest $request): bool;

    /**
     * Find request by ID
     *
     * @param int $id Request ID
     * @return SummaryRequest|null
     */
    public function findRequestById(int $id): ?SummaryRequest;
}
