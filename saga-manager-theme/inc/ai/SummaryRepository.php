<?php
/**
 * Summary Repository
 *
 * Data access layer for AI-generated summaries.
 * Handles CRUD operations with WordPress $wpdb integration.
 * Supports versioning, caching, and source reference tracking.
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

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Summary Repository
 *
 * WordPress database integration for generated summaries.
 */
class SummaryRepository
{
    private string $requests_table;
    private string $summaries_table;
    private int $cache_ttl = 300; // 5 minutes

    /**
     * Constructor
     */
    public function __construct()
    {
        global $wpdb;
        $this->requests_table = $wpdb->prefix . 'saga_summary_requests';
        $this->summaries_table = $wpdb->prefix . 'saga_generated_summaries';
    }

    /**
     * Create new summary
     *
     * @param GeneratedSummary $summary Summary object (id should be null)
     * @return int Summary ID
     * @throws \Exception If creation fails
     *
     * @example
     * $summary_id = $repo->create($summary);
     */
    public function create(GeneratedSummary $summary): int
    {
        global $wpdb;

        $data = [
            'request_id' => $summary->request_id,
            'saga_id' => $summary->saga_id,
            'entity_id' => $summary->entity_id,
            'summary_type' => $summary->summary_type->value,
            'version' => $summary->version,
            'title' => $summary->title,
            'summary_text' => $summary->summary_text,
            'word_count' => $summary->word_count,
            'key_points' => wp_json_encode($summary->key_points),
            'metadata' => wp_json_encode($summary->metadata),
            'quality_score' => $summary->quality_score,
            'readability_score' => $summary->readability_score,
            'is_current' => $summary->is_current ? 1 : 0,
            'regeneration_reason' => $summary->regeneration_reason,
            'cache_key' => $summary->cache_key,
            'cache_expires_at' => $summary->cache_expires_at
                ? date('Y-m-d H:i:s', $summary->cache_expires_at)
                : null,
            'ai_model' => $summary->ai_model,
            'token_count' => $summary->token_count,
            'generation_cost' => $summary->generation_cost,
        ];

        $result = $wpdb->insert($this->summaries_table, $data);

        if ($result === false) {
            error_log(sprintf(
                '[SAGA][SUMMARY][ERROR] Failed to create summary: %s',
                $wpdb->last_error
            ));
            throw new \Exception('Failed to create summary: ' . $wpdb->last_error);
        }

        $summary_id = $wpdb->insert_id;

        error_log(sprintf('[SAGA][SUMMARY] Created summary #%d', $summary_id));

        // Clear cache
        $this->clearCache($summary_id, $summary->cache_key, $summary->saga_id);

        return $summary_id;
    }

    /**
     * Find summary by ID
     *
     * @param int $id Summary ID
     * @return GeneratedSummary|null
     *
     * @example
     * $summary = $repo->findById(123);
     */
    public function findById(int $id): ?GeneratedSummary
    {
        global $wpdb;

        // Check cache
        $cache_key = "summary_{$id}";
        $cached = wp_cache_get($cache_key, 'saga');

        if ($cached !== false && $cached instanceof GeneratedSummary) {
            return $cached;
        }

        // Query database
        $row = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->summaries_table} WHERE id = %d",
            $id
        ));

        if (!$row) {
            return null;
        }

        $summary = GeneratedSummary::fromDatabase($row);

        // Cache result
        wp_cache_set($cache_key, $summary, 'saga', $this->cache_ttl);

        return $summary;
    }

    /**
     * Find summary by request ID
     *
     * Returns the current (latest) version.
     *
     * @param int $request_id Request ID
     * @return GeneratedSummary|null
     *
     * @example
     * $summary = $repo->findByRequest(456);
     */
    public function findByRequest(int $request_id): ?GeneratedSummary
    {
        global $wpdb;

        // Check cache
        $cache_key = "summary_request_{$request_id}";
        $cached = wp_cache_get($cache_key, 'saga');

        if ($cached !== false && $cached instanceof GeneratedSummary) {
            return $cached;
        }

        // Query database - get current version
        $row = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->summaries_table}
             WHERE request_id = %d AND is_current = 1
             ORDER BY version DESC
             LIMIT 1",
            $request_id
        ));

        if (!$row) {
            return null;
        }

        $summary = GeneratedSummary::fromDatabase($row);

        // Cache result
        wp_cache_set($cache_key, $summary, 'saga', $this->cache_ttl);

        return $summary;
    }

    /**
     * Find summary by cache key
     *
     * Checks if a matching summary already exists and is not expired.
     *
     * @param string $cache_key Cache key
     * @return GeneratedSummary|null
     *
     * @example
     * $existing = $repo->findByCacheKey($cache_key);
     * if ($existing && !$existing->isCacheExpired()) {
     *     return $existing; // Reuse cached summary
     * }
     */
    public function findByCacheKey(string $cache_key): ?GeneratedSummary
    {
        global $wpdb;

        // Query database
        $row = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->summaries_table}
             WHERE cache_key = %s AND is_current = 1
             LIMIT 1",
            $cache_key
        ));

        if (!$row) {
            return null;
        }

        return GeneratedSummary::fromDatabase($row);
    }

    /**
     * Find summaries by saga
     *
     * @param int $saga_id Saga ID
     * @param array $filters Optional filters
     * @return array Array of GeneratedSummary objects
     *
     * @example
     * $summaries = $repo->findBySaga(1, [
     *     'type' => 'character_arc',
     *     'entity_id' => 123,
     *     'limit' => 10
     * ]);
     */
    public function findBySaga(int $saga_id, array $filters = []): array
    {
        global $wpdb;

        $query = "SELECT * FROM {$this->summaries_table} WHERE saga_id = %d";
        $args = [$saga_id];

        // Apply filters
        if (!empty($filters['type'])) {
            $query .= " AND summary_type = %s";
            $args[] = $filters['type'];
        }

        if (!empty($filters['entity_id'])) {
            $query .= " AND entity_id = %d";
            $args[] = $filters['entity_id'];
        }

        if (isset($filters['is_current'])) {
            $query .= " AND is_current = %d";
            $args[] = $filters['is_current'] ? 1 : 0;
        } else {
            // Default: only current versions
            $query .= " AND is_current = 1";
        }

        // Order and limit
        $query .= " ORDER BY created_at DESC";

        if (!empty($filters['limit'])) {
            $query .= " LIMIT %d";
            $args[] = (int)$filters['limit'];
        } else {
            $query .= " LIMIT 50";
        }

        $rows = $wpdb->get_results($wpdb->prepare($query, ...$args));

        $summaries = [];
        foreach ($rows as $row) {
            $summaries[] = GeneratedSummary::fromDatabase($row);
        }

        return $summaries;
    }

    /**
     * Update summary version
     *
     * Marks old version as not current and creates new version.
     *
     * @param int $old_id Old summary ID to supersede
     * @param GeneratedSummary $new New summary version
     * @return int New summary ID
     * @throws \Exception If update fails
     *
     * @example
     * $old_summary = $repo->findById(123);
     * $new_summary = $old_summary->withNewVersion('Data updated');
     * $new_id = $repo->updateVersion(123, $new_summary);
     */
    public function updateVersion(int $old_id, GeneratedSummary $new): int
    {
        global $wpdb;

        // Start transaction
        $wpdb->query('START TRANSACTION');

        try {
            // Mark old version as not current
            $result = $wpdb->update(
                $this->summaries_table,
                ['is_current' => 0],
                ['id' => $old_id],
                ['%d'],
                ['%d']
            );

            if ($result === false) {
                throw new \Exception('Failed to update old version: ' . $wpdb->last_error);
            }

            // Create new version
            $new_id = $this->create($new);

            $wpdb->query('COMMIT');

            error_log(sprintf(
                '[SAGA][SUMMARY] Updated summary #%d -> #%d (v%d)',
                $old_id,
                $new_id,
                $new->version
            ));

            return $new_id;

        } catch (\Exception $e) {
            $wpdb->query('ROLLBACK');
            error_log("[SAGA][SUMMARY][ERROR] Version update failed: {$e->getMessage()}");
            throw $e;
        }
    }

    /**
     * Get summary statistics for saga
     *
     * @param int $saga_id Saga ID
     * @return array Statistics
     *
     * @example
     * $stats = $repo->getStatistics(1);
     * // Returns: [
     * //   'total_summaries' => 45,
     * //   'by_type' => ['character_arc' => 20, 'timeline' => 5, ...],
     * //   'avg_quality' => 85.5,
     * //   'avg_readability' => 72.3,
     * //   'total_cost' => 1.25
     * // ]
     */
    public function getStatistics(int $saga_id): array
    {
        global $wpdb;

        // Overall stats
        $overall = $wpdb->get_row($wpdb->prepare(
            "SELECT
                COUNT(*) as total,
                AVG(quality_score) as avg_quality,
                AVG(readability_score) as avg_readability,
                SUM(generation_cost) as total_cost,
                SUM(token_count) as total_tokens
             FROM {$this->summaries_table}
             WHERE saga_id = %d AND is_current = 1",
            $saga_id
        ), ARRAY_A);

        // By type
        $by_type = $wpdb->get_results($wpdb->prepare(
            "SELECT summary_type, COUNT(*) as count
             FROM {$this->summaries_table}
             WHERE saga_id = %d AND is_current = 1
             GROUP BY summary_type",
            $saga_id
        ), ARRAY_A);

        $type_counts = [];
        foreach ($by_type as $row) {
            $type_counts[$row['summary_type']] = (int)$row['count'];
        }

        return [
            'total_summaries' => (int)$overall['total'],
            'by_type' => $type_counts,
            'avg_quality' => $overall['avg_quality'] ? round((float)$overall['avg_quality'], 2) : null,
            'avg_readability' => $overall['avg_readability'] ? round((float)$overall['avg_readability'], 2) : null,
            'total_cost' => $overall['total_cost'] ? round((float)$overall['total_cost'], 4) : 0.0,
            'total_tokens' => (int)$overall['total_tokens'],
        ];
    }

    /**
     * Delete summary
     *
     * @param int $id Summary ID
     * @return bool Success
     */
    public function delete(int $id): bool
    {
        global $wpdb;

        $result = $wpdb->delete(
            $this->summaries_table,
            ['id' => $id],
            ['%d']
        );

        if ($result) {
            wp_cache_delete("summary_{$id}", 'saga');
            error_log("[SAGA][SUMMARY] Deleted summary #{$id}");
        }

        return $result !== false;
    }

    /**
     * Delete expired summaries
     *
     * Cleans up summaries past their cache expiration.
     *
     * @return int Number of deleted summaries
     */
    public function deleteExpired(): int
    {
        global $wpdb;

        $result = $wpdb->query(
            "DELETE FROM {$this->summaries_table}
             WHERE cache_expires_at IS NOT NULL
             AND cache_expires_at < NOW()"
        );

        if ($result) {
            error_log("[SAGA][SUMMARY] Deleted {$result} expired summaries");
        }

        return $result ?: 0;
    }

    /**
     * Get version history for summary
     *
     * @param string $cache_key Cache key
     * @return array Array of GeneratedSummary objects (all versions)
     *
     * @example
     * $history = $repo->getVersionHistory($cache_key);
     * foreach ($history as $version) {
     *     echo "v{$version->version}: {$version->regeneration_reason}\n";
     * }
     */
    public function getVersionHistory(string $cache_key): array
    {
        global $wpdb;

        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$this->summaries_table}
             WHERE cache_key = %s
             ORDER BY version DESC",
            $cache_key
        ));

        $summaries = [];
        foreach ($rows as $row) {
            $summaries[] = GeneratedSummary::fromDatabase($row);
        }

        return $summaries;
    }

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
    ): bool {
        global $wpdb;

        $data = [];
        $format = [];

        if ($quality_score !== null) {
            $data['quality_score'] = $quality_score;
            $format[] = '%f';
        }

        if ($readability_score !== null) {
            $data['readability_score'] = $readability_score;
            $format[] = '%f';
        }

        if (empty($data)) {
            return true;
        }

        $result = $wpdb->update(
            $this->summaries_table,
            $data,
            ['id' => $id],
            $format,
            ['%d']
        );

        if ($result !== false) {
            wp_cache_delete("summary_{$id}", 'saga');
        }

        return $result !== false;
    }

    /**
     * Search summaries by content
     *
     * Full-text search across summary text.
     *
     * @param int $saga_id Saga ID
     * @param string $search_term Search term
     * @param int $limit Result limit
     * @return array Array of GeneratedSummary objects
     *
     * @example
     * $results = $repo->search(1, 'Paul Atreides', 10);
     */
    public function search(int $saga_id, string $search_term, int $limit = 20): array
    {
        global $wpdb;

        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$this->summaries_table}
             WHERE saga_id = %d
             AND is_current = 1
             AND (
                 title LIKE %s
                 OR summary_text LIKE %s
             )
             ORDER BY created_at DESC
             LIMIT %d",
            $saga_id,
            '%' . $wpdb->esc_like($search_term) . '%',
            '%' . $wpdb->esc_like($search_term) . '%',
            $limit
        ));

        $summaries = [];
        foreach ($rows as $row) {
            $summaries[] = GeneratedSummary::fromDatabase($row);
        }

        return $summaries;
    }

    /**
     * Clear cache for summary
     *
     * @param int $summary_id Summary ID
     * @param string $cache_key Cache key
     * @param int $saga_id Saga ID
     * @return void
     */
    private function clearCache(int $summary_id, string $cache_key, int $saga_id): void
    {
        wp_cache_delete("summary_{$summary_id}", 'saga');
        wp_cache_delete("summary_cache_{$cache_key}", 'saga');
        wp_cache_delete("summary_stats_{$saga_id}", 'saga');
    }

    // =========================================================================
    // REQUEST REPOSITORY METHODS
    // =========================================================================

    /**
     * Create summary request
     *
     * @param SummaryRequest $request Request object
     * @return int Request ID
     * @throws \Exception If creation fails
     */
    public function createRequest(SummaryRequest $request): int
    {
        global $wpdb;

        $data = [
            'saga_id' => $request->saga_id,
            'user_id' => $request->user_id,
            'summary_type' => $request->summary_type->value,
            'entity_id' => $request->entity_id,
            'scope' => $request->scope->value,
            'scope_params' => wp_json_encode($request->scope_params),
            'status' => $request->status->value,
            'priority' => $request->priority,
            'ai_provider' => $request->ai_provider->value,
            'ai_model' => $request->ai_model,
            'estimated_tokens' => $request->estimated_tokens,
            'estimated_cost' => $request->estimated_cost,
            'retry_count' => $request->retry_count,
        ];

        $result = $wpdb->insert($this->requests_table, $data);

        if ($result === false) {
            throw new \Exception('Failed to create request: ' . $wpdb->last_error);
        }

        return $wpdb->insert_id;
    }

    /**
     * Update summary request
     *
     * @param SummaryRequest $request Request object with ID
     * @return bool Success
     * @throws \Exception If update fails
     */
    public function updateRequest(SummaryRequest $request): bool
    {
        global $wpdb;

        if ($request->id === null) {
            throw new \Exception('Cannot update request without ID');
        }

        $data = [
            'status' => $request->status->value,
            'actual_tokens' => $request->actual_tokens,
            'actual_cost' => $request->actual_cost,
            'processing_time' => $request->processing_time,
            'error_message' => $request->error_message,
            'retry_count' => $request->retry_count,
            'started_at' => $request->started_at ? date('Y-m-d H:i:s', $request->started_at) : null,
            'completed_at' => $request->completed_at ? date('Y-m-d H:i:s', $request->completed_at) : null,
        ];

        $result = $wpdb->update(
            $this->requests_table,
            $data,
            ['id' => $request->id],
            null,
            ['%d']
        );

        if ($result === false) {
            throw new \Exception('Failed to update request: ' . $wpdb->last_error);
        }

        wp_cache_delete("summary_request_{$request->id}", 'saga');

        return true;
    }

    /**
     * Find request by ID
     *
     * @param int $id Request ID
     * @return SummaryRequest|null
     */
    public function findRequestById(int $id): ?SummaryRequest
    {
        global $wpdb;

        $row = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->requests_table} WHERE id = %d",
            $id
        ));

        if (!$row) {
            return null;
        }

        return SummaryRequest::fromDatabase($row);
    }
}
