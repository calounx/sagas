<?php

declare(strict_types=1);

namespace SagaManagerCore\Infrastructure\Database\Adapter\WordPress;

use SagaManagerCore\Infrastructure\Database\Exception\ConnectionException;
use SagaManagerCore\Infrastructure\Database\Port\DatabaseConnectionInterface;
use SagaManagerCore\Infrastructure\Database\Port\DatabaseInterface;
use SagaManagerCore\Infrastructure\Database\Port\QueryBuilderInterface;
use SagaManagerCore\Infrastructure\Database\Port\ResultSetInterface;
use SagaManagerCore\Infrastructure\Database\Port\SchemaManagerInterface;
use SagaManagerCore\Infrastructure\Database\Port\TransactionManagerInterface;

/**
 * WordPress Database Adapter (Main Facade)
 *
 * Primary entry point for all database operations in Saga Manager.
 * Wraps WordPress wpdb and provides clean abstractions for:
 * - Query building with SQL injection prevention
 * - Transaction management with savepoint support
 * - Schema management via dbDelta
 * - Query result caching via WordPress object cache
 * - Performance monitoring and slow query logging
 *
 * Usage:
 * ```php
 * $db = new WordPressDatabaseAdapter();
 *
 * // Query building
 * $entities = $db->query()
 *     ->select(['id', 'name'])
 *     ->from('entities')
 *     ->where('saga_id', '=', 1)
 *     ->orderBy('importance_score', 'DESC')
 *     ->limit(10)
 *     ->get();
 *
 * // Transactions
 * $db->transaction()->run(function() use ($db) {
 *     $db->query()->table('entities')->insert([...]);
 *     $db->query()->table('attributes')->insert([...]);
 * });
 *
 * // Raw queries (use sparingly)
 * $result = $db->raw('SELECT * FROM %s WHERE id = %d', [$tableName, $id]);
 * ```
 *
 * IMPORTANT: This adapter is the ONLY place where global $wpdb should be accessed.
 */
class WordPressDatabaseAdapter implements DatabaseInterface
{
    private const CACHE_GROUP = 'saga_db';
    private const SLOW_QUERY_THRESHOLD_MS = 50;

    private ?WordPressConnection $connection = null;
    private ?WordPressTransactionManager $transactionManager = null;
    private ?WordPressSchemaManager $schemaManager = null;

    // =========================================================================
    // Performance Statistics
    // =========================================================================

    private int $totalQueries = 0;
    private int $slowQueries = 0;
    private int $cacheHits = 0;
    private int $cacheMisses = 0;
    private float $totalQueryTime = 0.0;

    public function __construct()
    {
        // Lazy initialization - connection created on first use
    }

    // =========================================================================
    // Component Accessors
    // =========================================================================

    /**
     * {@inheritdoc}
     */
    public function connection(): DatabaseConnectionInterface
    {
        return $this->getConnection();
    }

    /**
     * {@inheritdoc}
     */
    public function query(): QueryBuilderInterface
    {
        return new WordPressQueryBuilder($this->getConnection());
    }

    /**
     * {@inheritdoc}
     */
    public function transaction(): TransactionManagerInterface
    {
        if ($this->transactionManager === null) {
            $this->transactionManager = new WordPressTransactionManager($this->getConnection());
        }

        return $this->transactionManager;
    }

    /**
     * {@inheritdoc}
     */
    public function schema(): SchemaManagerInterface
    {
        if ($this->schemaManager === null) {
            $this->schemaManager = new WordPressSchemaManager($this->getConnection());
        }

        return $this->schemaManager;
    }

    // =========================================================================
    // Query Execution
    // =========================================================================

    /**
     * {@inheritdoc}
     */
    public function raw(string $sql, array $bindings = []): ResultSetInterface
    {
        $startTime = microtime(true);

        $result = $this->getConnection()->raw($sql, $bindings);

        $duration = (microtime(true) - $startTime) * 1000;
        $this->recordQueryStats($sql, $duration);

        return $result;
    }

    /**
     * {@inheritdoc}
     */
    public function statement(string $sql, array $bindings = []): int
    {
        return $this->getConnection()->statement($sql, $bindings);
    }

    /**
     * {@inheritdoc}
     */
    public function table(string $table): string
    {
        return $this->getConnection()->getFullTableName($table);
    }

    /**
     * {@inheritdoc}
     */
    public function ping(): bool
    {
        try {
            return $this->getConnection()->ping();
        } catch (ConnectionException) {
            return false;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function lastInsertId(): int
    {
        return $this->getConnection()->lastInsertId();
    }

    /**
     * {@inheritdoc}
     */
    public function lastError(): string
    {
        return $this->getConnection()->getLastError() ?? '';
    }

    // =========================================================================
    // Caching
    // =========================================================================

    /**
     * {@inheritdoc}
     */
    public function cacheSet(string $key, mixed $data, int $ttl = 300): void
    {
        $cacheKey = $this->getCacheKey($key);
        wp_cache_set($cacheKey, $data, self::CACHE_GROUP, $ttl);
    }

    /**
     * {@inheritdoc}
     */
    public function cacheGet(string $key): mixed
    {
        $cacheKey = $this->getCacheKey($key);
        $data = wp_cache_get($cacheKey, self::CACHE_GROUP);

        if ($data === false) {
            $this->cacheMisses++;

            return null;
        }

        $this->cacheHits++;

        return $data;
    }

    /**
     * {@inheritdoc}
     */
    public function cacheDelete(string $key): void
    {
        $cacheKey = $this->getCacheKey($key);
        wp_cache_delete($cacheKey, self::CACHE_GROUP);
    }

    /**
     * {@inheritdoc}
     */
    public function cacheClear(): void
    {
        // WordPress doesn't have a built-in way to clear a specific group
        // This is a best-effort implementation
        if (function_exists('wp_cache_flush_group')) {
            wp_cache_flush_group(self::CACHE_GROUP);
        } else {
            // Fallback: just clear everything (not ideal but works)
            wp_cache_flush();
        }
    }

    // =========================================================================
    // Performance Monitoring
    // =========================================================================

    /**
     * {@inheritdoc}
     */
    public function logSlowQuery(string $sql, float $duration): void
    {
        if ($duration > self::SLOW_QUERY_THRESHOLD_MS) {
            $this->slowQueries++;

            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log(sprintf(
                    '[SAGA][PERF] Slow query (%.2fms): %s',
                    $duration,
                    substr($sql, 0, 200)
                ));
            }

            // Track metrics in transient for dashboard
            $this->trackSlowQueryMetric($sql, $duration);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getStats(): array
    {
        return [
            'total_queries' => $this->totalQueries,
            'slow_queries' => $this->slowQueries,
            'cache_hits' => $this->cacheHits,
            'cache_misses' => $this->cacheMisses,
            'average_query_time' => $this->totalQueries > 0
                ? $this->totalQueryTime / $this->totalQueries
                : 0.0,
        ];
    }

    // =========================================================================
    // Convenience Methods
    // =========================================================================

    /**
     * Execute a cached query
     *
     * @param string $cacheKey Unique cache key for this query
     * @param callable(): ResultSetInterface $query Function that executes the query
     * @param int $ttl Cache TTL in seconds
     * @return ResultSetInterface
     */
    public function cached(string $cacheKey, callable $query, int $ttl = 300): ResultSetInterface
    {
        $cached = $this->cacheGet($cacheKey);

        if ($cached !== null) {
            return WordPressResultSet::fromWpdb($cached);
        }

        $result = $query();
        $this->cacheSet($cacheKey, $result->toArray(), $ttl);

        return $result;
    }

    /**
     * Run a callback within a transaction with automatic retry on deadlock
     *
     * @template T
     * @param callable(): T $callback
     * @param int $maxAttempts
     * @return T
     */
    public function transactional(callable $callback, int $maxAttempts = 3): mixed
    {
        return $this->transaction()->runWithRetry($callback, $maxAttempts);
    }

    /**
     * Insert a row and return the insert ID
     *
     * @param string $table Table name (without prefix)
     * @param array<string, mixed> $data Column => value pairs
     * @return int Insert ID
     */
    public function insert(string $table, array $data): int
    {
        $result = $this->query()
            ->table($table)
            ->insert($data);

        return $result->getLastInsertId();
    }

    /**
     * Update rows matching conditions
     *
     * @param string $table Table name (without prefix)
     * @param array<string, mixed> $data Column => value pairs
     * @param array<string, mixed> $conditions WHERE conditions
     * @return int Affected rows
     */
    public function update(string $table, array $data, array $conditions): int
    {
        $query = $this->query()->table($table);

        foreach ($conditions as $column => $value) {
            $query->where($column, '=', $value);
        }

        $result = $query->update($data);

        return $result->getAffectedRows();
    }

    /**
     * Delete rows matching conditions
     *
     * @param string $table Table name (without prefix)
     * @param array<string, mixed> $conditions WHERE conditions
     * @return int Affected rows
     */
    public function delete(string $table, array $conditions): int
    {
        $query = $this->query()->table($table);

        foreach ($conditions as $column => $value) {
            $query->where($column, '=', $value);
        }

        $result = $query->delete();

        return $result->getAffectedRows();
    }

    /**
     * Find a single row by ID
     *
     * @param string $table Table name (without prefix)
     * @param int $id Primary key value
     * @param string $primaryKey Primary key column name
     * @return array<string, mixed>|null
     */
    public function find(string $table, int $id, string $primaryKey = 'id'): ?array
    {
        return $this->query()
            ->from($table)
            ->where($primaryKey, '=', $id)
            ->first();
    }

    /**
     * Check if a row exists
     *
     * @param string $table Table name (without prefix)
     * @param array<string, mixed> $conditions WHERE conditions
     * @return bool
     */
    public function exists(string $table, array $conditions): bool
    {
        $query = $this->query()->from($table);

        foreach ($conditions as $column => $value) {
            $query->where($column, '=', $value);
        }

        return $query->exists();
    }

    /**
     * Count rows matching conditions
     *
     * @param string $table Table name (without prefix)
     * @param array<string, mixed> $conditions WHERE conditions
     * @return int
     */
    public function count(string $table, array $conditions = []): int
    {
        $query = $this->query()->from($table);

        foreach ($conditions as $column => $value) {
            $query->where($column, '=', $value);
        }

        return $query->count();
    }

    /**
     * Get database connection info for diagnostics
     *
     * @return array{
     *     database: string,
     *     host: string,
     *     version: string,
     *     charset: string,
     *     collate: string,
     *     prefix: string,
     *     saga_prefix: string,
     *     is_multisite: bool,
     *     stats: array{
     *         total_queries: int,
     *         slow_queries: int,
     *         cache_hits: int,
     *         cache_misses: int,
     *         average_query_time: float
     *     }
     * }
     */
    public function getDiagnostics(): array
    {
        $info = $this->getConnection()->getInfo();
        $info['stats'] = $this->getStats();

        return $info;
    }

    // =========================================================================
    // Private Methods
    // =========================================================================

    /**
     * Get or create the connection instance (lazy initialization)
     */
    private function getConnection(): WordPressConnection
    {
        if ($this->connection === null) {
            $this->connection = new WordPressConnection();
        }

        return $this->connection;
    }

    /**
     * Generate a cache key with prefix
     */
    private function getCacheKey(string $key): string
    {
        return 'saga_' . md5($key);
    }

    /**
     * Record query statistics
     */
    private function recordQueryStats(string $sql, float $duration): void
    {
        $this->totalQueries++;
        $this->totalQueryTime += $duration;

        if ($duration > self::SLOW_QUERY_THRESHOLD_MS) {
            $this->logSlowQuery($sql, $duration);
        }
    }

    /**
     * Track slow query metric for dashboard display
     */
    private function trackSlowQueryMetric(string $sql, float $duration): void
    {
        $metrics = get_transient('saga_slow_queries_hourly') ?: [];

        $metrics[] = [
            'time' => time(),
            'duration' => $duration,
            'query' => substr($sql, 0, 100),
        ];

        // Keep only last 100 slow queries
        if (count($metrics) > 100) {
            $metrics = array_slice($metrics, -100);
        }

        set_transient('saga_slow_queries_hourly', $metrics, HOUR_IN_SECONDS);
    }
}
