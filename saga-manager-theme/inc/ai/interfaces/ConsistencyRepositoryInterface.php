<?php
/**
 * Consistency Repository Interface
 *
 * Public contract for consistency issue data access layer.
 * Defines operations for detecting, storing, and resolving consistency issues.
 *
 * @package SagaManager
 * @subpackage AI\Interfaces
 * @since 1.5.0
 */

declare(strict_types=1);

namespace SagaManager\AI\Interfaces;

use SagaManager\AI\Entities\ConsistencyIssue;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Consistency Repository Interface
 *
 * Dependency Inversion Principle: Application services depend on this interface,
 * not on concrete MariaDB implementation.
 */
interface ConsistencyRepositoryInterface
{
    /**
     * Save new issue
     *
     * @param ConsistencyIssue $issue Issue to save
     * @return int|false Insert ID or false on failure
     */
    public function save(ConsistencyIssue $issue): int|false;

    /**
     * Update existing issue
     *
     * @param ConsistencyIssue $issue Issue to update
     * @return bool Success
     */
    public function update(ConsistencyIssue $issue): bool;

    /**
     * Find issue by ID
     *
     * @param int $issueId Issue ID
     * @return ConsistencyIssue|null
     */
    public function findById(int $issueId): ?ConsistencyIssue;

    /**
     * Find issues by saga ID
     *
     * @param int $sagaId Saga ID
     * @param string $status Status filter (empty = all)
     * @param int $limit Limit
     * @param int $offset Offset
     * @return ConsistencyIssue[]
     */
    public function findBySaga(
        int $sagaId,
        string $status = '',
        int $limit = 100,
        int $offset = 0
    ): array;

    /**
     * Find issues by entity ID
     *
     * @param int $entityId Entity ID
     * @param string $status Status filter
     * @return ConsistencyIssue[]
     */
    public function findByEntity(int $entityId, string $status = ''): array;

    /**
     * Delete issue
     *
     * @param int $issueId Issue ID
     * @return bool Success
     */
    public function delete(int $issueId): bool;

    /**
     * Delete all issues for a saga
     *
     * @param int $sagaId Saga ID
     * @return bool Success
     */
    public function deleteBySaga(int $sagaId): bool;

    /**
     * Get consistency statistics for a saga
     *
     * @param int $sagaId Saga ID
     * @return array Statistics ['total_issues', 'open_issues', 'resolved_issues', ...]
     */
    public function getStatistics(int $sagaId): array;

    /**
     * Get issues grouped by type
     *
     * @param int $sagaId Saga ID
     * @param string $status Status filter
     * @return array Associative array [issue_type => count]
     */
    public function getIssuesByType(int $sagaId, string $status = 'open'): array;

    /**
     * Get recent issues
     *
     * @param int $sagaId Saga ID
     * @param int $limit Limit
     * @return ConsistencyIssue[]
     */
    public function getRecentIssues(int $sagaId, int $limit = 10): array;

    /**
     * Count issues by status
     *
     * @param int $sagaId Saga ID
     * @param string $status Status
     * @return int Count
     */
    public function countByStatus(int $sagaId, string $status): int;
}
