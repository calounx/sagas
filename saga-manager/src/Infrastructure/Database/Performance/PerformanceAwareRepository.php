<?php
declare(strict_types=1);

namespace SagaManager\Infrastructure\Database\Performance;

use SagaManager\Infrastructure\WordPress\WordPressTablePrefixAware;

/**
 * Performance-Aware Repository Base Class
 *
 * Integrates all performance components:
 * - Query caching
 * - Connection pooling
 * - Query profiling
 * - Batch operations
 * - Performance monitoring
 *
 * Maintains <50ms query response target while adding <5% overhead.
 */
abstract class PerformanceAwareRepository extends WordPressTablePrefixAware
{
    protected QueryCache $queryCache;
    protected ConnectionPool $connectionPool;
    protected QueryProfiler $queryProfiler;
    protected BatchExecutor $batchExecutor;
    protected PerformanceMonitor $performanceMonitor;

    /** @var bool Whether performance features are enabled */
    protected bool $performanceEnabled = true;

    public function __construct()
    {
        parent::__construct();

        $this->queryCache = new QueryCache();
        $this->connectionPool = new ConnectionPool();
        $this->queryProfiler = new QueryProfiler();
        $this->batchExecutor = new BatchExecutor();
        $this->performanceMonitor = new PerformanceMonitor(
            $this->queryCache,
            $this->connectionPool,
            $this->queryProfiler
        );
    }

    /**
     * Execute a cached query
     *
     * @param string $cacheKey Unique cache key
     * @param string $sql The SQL query
     * @param array<string> $tags Cache invalidation tags
     * @param int $ttl Cache TTL in seconds
     * @return mixed Query result
     */
    protected function cachedQuery(
        string $cacheKey,
        string $sql,
        array $tags = [],
        int $ttl = 300
    ): mixed {
        if (!$this->performanceEnabled) {
            return $this->executeQuery($sql);
        }

        return $this->queryCache->remember(
            $cacheKey,
            fn() => $this->executeQuery($sql),
            $ttl,
            $tags
        );
    }

    /**
     * Execute a query with monitoring
     *
     * @param string $sql The SQL query
     * @return mixed Query result
     */
    protected function executeQuery(string $sql): mixed
    {
        if (!$this->performanceEnabled) {
            return $this->wpdb->get_results($sql, ARRAY_A);
        }

        return $this->performanceMonitor->monitor(
            $sql,
            fn() => $this->wpdb->get_results($sql, ARRAY_A)
        );
    }

    /**
     * Execute a single row query with caching
     *
     * @param string $cacheKey Unique cache key
     * @param string $sql The SQL query
     * @param array<string> $tags Cache invalidation tags
     * @return array|null Query result
     */
    protected function cachedQueryRow(
        string $cacheKey,
        string $sql,
        array $tags = []
    ): ?array {
        if (!$this->performanceEnabled) {
            return $this->wpdb->get_row($sql, ARRAY_A);
        }

        return $this->queryCache->remember(
            $cacheKey,
            fn() => $this->wpdb->get_row($sql, ARRAY_A),
            300,
            $tags
        );
    }

    /**
     * Execute a scalar query with caching
     *
     * @param string $cacheKey Unique cache key
     * @param string $sql The SQL query
     * @param array<string> $tags Cache invalidation tags
     * @return string|null Query result
     */
    protected function cachedQueryVar(
        string $cacheKey,
        string $sql,
        array $tags = []
    ): ?string {
        if (!$this->performanceEnabled) {
            return $this->wpdb->get_var($sql);
        }

        return $this->queryCache->remember(
            $cacheKey,
            fn() => $this->wpdb->get_var($sql),
            300,
            $tags
        );
    }

    /**
     * Prepare a SQL query with compiled caching
     *
     * @param string $queryId Unique query template ID
     * @param string $sqlTemplate SQL template
     * @param array<mixed> $params Query parameters
     * @return string Prepared SQL
     */
    protected function prepareCompiled(
        string $queryId,
        string $sqlTemplate,
        array $params
    ): string {
        if (!$this->performanceEnabled) {
            return $this->wpdb->prepare($sqlTemplate, ...$params);
        }

        return $this->queryCache->compiledPrepare($queryId, $sqlTemplate, $params);
    }

    /**
     * Bulk insert rows
     *
     * @param string $table Table name (without prefix)
     * @param array<string> $columns Column names
     * @param array<array<mixed>> $rows Row data
     * @return int Rows inserted
     */
    protected function bulkInsert(string $table, array $columns, array $rows): int
    {
        $count = $this->batchExecutor->bulkInsert($table, $columns, $rows);

        // Invalidate related caches
        $this->queryCache->invalidateByTag($table);

        return $count;
    }

    /**
     * Bulk update rows
     *
     * @param string $table Table name (without prefix)
     * @param string $keyColumn Primary key column
     * @param string $updateColumn Column to update
     * @param array<int|string, mixed> $updates Map of key => new value
     * @return int Rows updated
     */
    protected function bulkUpdate(
        string $table,
        string $keyColumn,
        string $updateColumn,
        array $updates
    ): int {
        $count = $this->batchExecutor->bulkUpdate(
            $table,
            $keyColumn,
            $updateColumn,
            $updates
        );

        // Invalidate related caches
        $this->queryCache->invalidateByTag($table);
        foreach (array_keys($updates) as $id) {
            $this->queryCache->invalidateEntity($table, (int) $id);
        }

        return $count;
    }

    /**
     * Bulk delete rows
     *
     * @param string $table Table name (without prefix)
     * @param string $column Column to match
     * @param array<int|string> $values Values to delete
     * @return int Rows deleted
     */
    protected function bulkDelete(string $table, string $column, array $values): int
    {
        $count = $this->batchExecutor->bulkDelete($table, $column, $values);

        // Invalidate related caches
        $this->queryCache->invalidateByTag($table);
        foreach ($values as $id) {
            $this->queryCache->invalidateEntity($table, (int) $id);
        }

        return $count;
    }

    /**
     * Stream large result sets
     *
     * @param string $table Table name (without prefix)
     * @param array<string, mixed> $conditions WHERE conditions
     * @param int $chunkSize Rows per chunk
     * @return \Generator<int, array>
     */
    protected function streamResults(
        string $table,
        array $conditions = [],
        int $chunkSize = 500
    ): \Generator {
        return $this->batchExecutor->streamResults($table, $conditions, $chunkSize);
    }

    /**
     * Get EXPLAIN analysis for a query
     *
     * @param string $sql SELECT query
     * @return array Query plan analysis
     */
    protected function analyzeQuery(string $sql): array
    {
        return $this->queryProfiler->explain($sql);
    }

    /**
     * Invalidate cache for an entity
     *
     * @param string $entityType Entity type
     * @param int $entityId Entity ID
     */
    protected function invalidateEntityCache(string $entityType, int $entityId): void
    {
        $this->queryCache->invalidateEntity($entityType, $entityId);
    }

    /**
     * Invalidate all caches for a table
     *
     * @param string $table Table name
     */
    protected function invalidateTableCache(string $table): void
    {
        $this->queryCache->invalidateByTag($table);
    }

    /**
     * Get performance statistics
     *
     * @return array Performance report
     */
    public function getPerformanceStats(): array
    {
        return $this->performanceMonitor->getReport();
    }

    /**
     * Check if performance meets targets
     *
     * @return array Health check result
     */
    public function checkPerformanceHealth(): array
    {
        return $this->performanceMonitor->healthCheck();
    }

    /**
     * Enable or disable performance features
     *
     * Useful for debugging or when overhead is not acceptable.
     *
     * @param bool $enabled Whether to enable performance features
     */
    public function setPerformanceEnabled(bool $enabled): void
    {
        $this->performanceEnabled = $enabled;
    }

    /**
     * Run with transaction
     *
     * @param callable $operation Operation to run
     * @return mixed Operation result
     * @throws \Throwable If operation fails
     */
    protected function transaction(callable $operation): mixed
    {
        $this->wpdb->query('START TRANSACTION');

        try {
            $result = $operation();
            $this->wpdb->query('COMMIT');
            return $result;
        } catch (\Throwable $e) {
            $this->wpdb->query('ROLLBACK');
            error_log('[SAGA][ERROR] Transaction failed: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Warm up caches for frequently accessed data
     *
     * Override in child classes to define warmup queries.
     */
    public function warmUpCache(): void
    {
        // Default: no-op, override in child classes
    }
}
