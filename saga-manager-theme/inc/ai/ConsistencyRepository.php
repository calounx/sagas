<?php
/**
 * ConsistencyRepository
 *
 * Database operations for consistency issues
 * Handles CRUD with proper WordPress table prefix support
 *
 * @package SagaManager\AI
 * @version 1.4.0
 */

declare(strict_types=1);

namespace SagaManager\AI;

use SagaManager\AI\Entities\ConsistencyIssue;

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * ConsistencyRepository Class
 *
 * Repository pattern for consistency issues
 */
final class ConsistencyRepository
{
    /**
     * @var \wpdb WordPress database object
     */
    private \wpdb $wpdb;

    /**
     * @var string Table name
     */
    private string $tableName;

    /**
     * @var int Cache TTL (5 minutes)
     */
    private const CACHE_TTL = 300;

    /**
     * Constructor
     */
    public function __construct()
    {
        global $wpdb;
        $this->wpdb = $wpdb;
        $this->tableName = $wpdb->prefix . 'saga_consistency_issues';
    }

    /**
     * Save new issue
     *
     * @param ConsistencyIssue $issue Issue to save
     * @return int|false Insert ID or false on failure
     */
    public function save(ConsistencyIssue $issue): int|false
    {
        $data = $issue->toArray();
        unset($data['id']); // Remove ID for insert

        $result = $this->wpdb->insert(
            $this->tableName,
            $data,
            [
                '%d', // saga_id
                '%s', // issue_type
                '%s', // severity
                '%d', // entity_id
                '%d', // related_entity_id
                '%s', // description
                '%s', // context (JSON)
                '%s', // suggested_fix
                '%s', // status
                '%s', // detected_at
                '%s', // resolved_at
                '%d', // resolved_by
                '%f', // ai_confidence
            ]
        );

        if ($result === false) {
            error_log('[SAGA][AI][ERROR] Failed to save consistency issue: ' . $this->wpdb->last_error);
            return false;
        }

        $insertId = $this->wpdb->insert_id;

        // Invalidate cache
        $this->invalidateCache($issue->sagaId);

        return $insertId;
    }

    /**
     * Update existing issue
     *
     * @param ConsistencyIssue $issue Issue to update
     * @return bool
     */
    public function update(ConsistencyIssue $issue): bool
    {
        if ($issue->id === null) {
            return false;
        }

        $data = $issue->toArray();
        unset($data['id']); // Remove ID from data

        $result = $this->wpdb->update(
            $this->tableName,
            $data,
            ['id' => $issue->id],
            [
                '%d', // saga_id
                '%s', // issue_type
                '%s', // severity
                '%d', // entity_id
                '%d', // related_entity_id
                '%s', // description
                '%s', // context
                '%s', // suggested_fix
                '%s', // status
                '%s', // detected_at
                '%s', // resolved_at
                '%d', // resolved_by
                '%f', // ai_confidence
            ],
            ['%d'] // WHERE id
        );

        if ($result === false) {
            error_log('[SAGA][AI][ERROR] Failed to update consistency issue: ' . $this->wpdb->last_error);
            return false;
        }

        // Invalidate cache
        $this->invalidateCache($issue->sagaId);

        return true;
    }

    /**
     * Find issue by ID
     *
     * @param int $issueId Issue ID
     * @return ConsistencyIssue|null
     */
    public function findById(int $issueId): ?ConsistencyIssue
    {
        $cacheKey = "saga_issue_{$issueId}";
        $cached = wp_cache_get($cacheKey, 'saga');

        if ($cached !== false) {
            return ConsistencyIssue::fromDatabase($cached);
        }

        $row = $this->wpdb->get_row($this->wpdb->prepare(
            "SELECT * FROM {$this->tableName} WHERE id = %d",
            $issueId
        ));

        if ($row === null) {
            return null;
        }

        wp_cache_set($cacheKey, $row, 'saga', self::CACHE_TTL);

        return ConsistencyIssue::fromDatabase($row);
    }

    /**
     * Find issues by saga ID
     *
     * @param int    $sagaId Saga ID
     * @param string $status Status filter (empty = all)
     * @param int    $limit  Limit
     * @param int    $offset Offset
     * @return ConsistencyIssue[]
     */
    public function findBySaga(
        int $sagaId,
        string $status = '',
        int $limit = 100,
        int $offset = 0
    ): array {
        $cacheKey = "saga_issues_{$sagaId}_{$status}_{$limit}_{$offset}";
        $cached = wp_cache_get($cacheKey, 'saga');

        if ($cached !== false && is_array($cached)) {
            return array_map(fn($row) => ConsistencyIssue::fromDatabase($row), $cached);
        }

        $sql = "SELECT * FROM {$this->tableName} WHERE saga_id = %d";
        $params = [$sagaId];

        if (!empty($status)) {
            $sql .= " AND status = %s";
            $params[] = $status;
        }

        $sql .= " ORDER BY
                  FIELD(severity, 'critical', 'high', 'medium', 'low', 'info'),
                  detected_at DESC
                  LIMIT %d OFFSET %d";
        $params[] = $limit;
        $params[] = $offset;

        $rows = $this->wpdb->get_results($this->wpdb->prepare($sql, ...$params));

        wp_cache_set($cacheKey, $rows, 'saga', self::CACHE_TTL);

        return array_map(fn($row) => ConsistencyIssue::fromDatabase($row), $rows);
    }

    /**
     * Find issues by entity ID
     *
     * @param int    $entityId Entity ID
     * @param string $status   Status filter
     * @return ConsistencyIssue[]
     */
    public function findByEntity(int $entityId, string $status = ''): array
    {
        $sql = "SELECT * FROM {$this->tableName}
                WHERE (entity_id = %d OR related_entity_id = %d)";
        $params = [$entityId, $entityId];

        if (!empty($status)) {
            $sql .= " AND status = %s";
            $params[] = $status;
        }

        $sql .= " ORDER BY detected_at DESC";

        $rows = $this->wpdb->get_results($this->wpdb->prepare($sql, ...$params));

        return array_map(fn($row) => ConsistencyIssue::fromDatabase($row), $rows);
    }

    /**
     * Delete issue
     *
     * @param int $issueId Issue ID
     * @return bool
     */
    public function delete(int $issueId): bool
    {
        $issue = $this->findById($issueId);

        if ($issue === null) {
            return false;
        }

        $result = $this->wpdb->delete(
            $this->tableName,
            ['id' => $issueId],
            ['%d']
        );

        if ($result === false) {
            error_log('[SAGA][AI][ERROR] Failed to delete consistency issue: ' . $this->wpdb->last_error);
            return false;
        }

        // Invalidate cache
        $this->invalidateCache($issue->sagaId);

        return true;
    }

    /**
     * Delete all issues for a saga
     *
     * @param int $sagaId Saga ID
     * @return bool
     */
    public function deleteBySaga(int $sagaId): bool
    {
        $result = $this->wpdb->delete(
            $this->tableName,
            ['saga_id' => $sagaId],
            ['%d']
        );

        // Invalidate cache
        $this->invalidateCache($sagaId);

        return $result !== false;
    }

    /**
     * Get consistency statistics for a saga
     *
     * @param int $sagaId Saga ID
     * @return array
     */
    public function getStatistics(int $sagaId): array
    {
        $cacheKey = "saga_stats_{$sagaId}";
        $cached = wp_cache_get($cacheKey, 'saga');

        if ($cached !== false && is_array($cached)) {
            return $cached;
        }

        $stats = $this->wpdb->get_row($this->wpdb->prepare(
            "SELECT
                COUNT(*) as total_issues,
                SUM(CASE WHEN status = 'open' THEN 1 ELSE 0 END) as open_issues,
                SUM(CASE WHEN status = 'resolved' THEN 1 ELSE 0 END) as resolved_issues,
                SUM(CASE WHEN status = 'dismissed' THEN 1 ELSE 0 END) as dismissed_issues,
                SUM(CASE WHEN severity = 'critical' THEN 1 ELSE 0 END) as critical_count,
                SUM(CASE WHEN severity = 'high' THEN 1 ELSE 0 END) as high_count,
                SUM(CASE WHEN severity = 'medium' THEN 1 ELSE 0 END) as medium_count,
                SUM(CASE WHEN severity = 'low' THEN 1 ELSE 0 END) as low_count,
                AVG(ai_confidence) as avg_ai_confidence
            FROM {$this->tableName}
            WHERE saga_id = %d",
            $sagaId
        ), ARRAY_A);

        if ($stats === null) {
            $stats = [
                'total_issues' => 0,
                'open_issues' => 0,
                'resolved_issues' => 0,
                'dismissed_issues' => 0,
                'critical_count' => 0,
                'high_count' => 0,
                'medium_count' => 0,
                'low_count' => 0,
                'avg_ai_confidence' => 0.0,
            ];
        }

        // Convert to integers
        foreach (['total_issues', 'open_issues', 'resolved_issues', 'dismissed_issues', 'critical_count', 'high_count', 'medium_count', 'low_count'] as $key) {
            $stats[$key] = (int) $stats[$key];
        }

        $stats['avg_ai_confidence'] = $stats['avg_ai_confidence'] !== null
            ? round((float) $stats['avg_ai_confidence'], 2)
            : 0.0;

        wp_cache_set($cacheKey, $stats, 'saga', self::CACHE_TTL);

        return $stats;
    }

    /**
     * Get issues grouped by type
     *
     * @param int    $sagaId Saga ID
     * @param string $status Status filter
     * @return array
     */
    public function getIssuesByType(int $sagaId, string $status = 'open'): array
    {
        $sql = "SELECT issue_type, COUNT(*) as count
                FROM {$this->tableName}
                WHERE saga_id = %d";
        $params = [$sagaId];

        if (!empty($status)) {
            $sql .= " AND status = %s";
            $params[] = $status;
        }

        $sql .= " GROUP BY issue_type";

        $results = $this->wpdb->get_results($this->wpdb->prepare($sql, ...$params), ARRAY_A);

        $grouped = [];
        foreach ($results as $row) {
            $grouped[$row['issue_type']] = (int) $row['count'];
        }

        return $grouped;
    }

    /**
     * Get recent issues
     *
     * @param int $sagaId Saga ID
     * @param int $limit  Limit
     * @return ConsistencyIssue[]
     */
    public function getRecentIssues(int $sagaId, int $limit = 10): array
    {
        $rows = $this->wpdb->get_results($this->wpdb->prepare(
            "SELECT * FROM {$this->tableName}
            WHERE saga_id = %d
            ORDER BY detected_at DESC
            LIMIT %d",
            $sagaId,
            $limit
        ));

        return array_map(fn($row) => ConsistencyIssue::fromDatabase($row), $rows);
    }

    /**
     * Invalidate cache for a saga
     *
     * @param int $sagaId Saga ID
     * @return void
     */
    private function invalidateCache(int $sagaId): void
    {
        // Clear object cache for this saga
        wp_cache_delete("saga_stats_{$sagaId}", 'saga');

        // Clear transients
        delete_transient("saga_issues_{$sagaId}");
    }

    /**
     * Count issues by status
     *
     * @param int    $sagaId Saga ID
     * @param string $status Status
     * @return int
     */
    public function countByStatus(int $sagaId, string $status): int
    {
        $count = $this->wpdb->get_var($this->wpdb->prepare(
            "SELECT COUNT(*) FROM {$this->tableName}
            WHERE saga_id = %d AND status = %s",
            $sagaId,
            $status
        ));

        return (int) $count;
    }
}
