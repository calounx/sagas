<?php
declare(strict_types=1);

namespace SagaManager\Infrastructure\Database\Performance;

/**
 * Performance Monitor - Centralized Database Metrics and Monitoring
 *
 * Tracks:
 * - Query execution times
 * - Cache hit/miss ratios
 * - Connection pool statistics
 * - Slow query counts
 * - Query counts per request
 * - Resource utilization
 */
final class PerformanceMonitor
{
    private const METRICS_TRANSIENT_KEY = 'saga_db_metrics';
    private const SLOW_QUERY_LOG_KEY = 'saga_slow_queries';
    private const METRICS_RETENTION_HOURS = 24;
    private const SLOW_QUERY_THRESHOLD_MS = 50.0;

    /** @var array<string, float> Query start times */
    private array $queryTimers = [];

    /** @var array<int, array{sql: string, duration_ms: float, timestamp: float}> Current request queries */
    private array $requestQueries = [];

    /** @var float Request start time */
    private float $requestStart;

    /** @var int Queries in current request */
    private int $queryCount = 0;

    private QueryCache $queryCache;
    private ConnectionPool $connectionPool;
    private QueryProfiler $queryProfiler;

    public function __construct(
        ?QueryCache $queryCache = null,
        ?ConnectionPool $connectionPool = null,
        ?QueryProfiler $queryProfiler = null
    ) {
        $this->queryCache = $queryCache ?? new QueryCache();
        $this->connectionPool = $connectionPool ?? new ConnectionPool();
        $this->queryProfiler = $queryProfiler ?? new QueryProfiler();
        $this->requestStart = microtime(true);
    }

    /**
     * Start timing a query
     *
     * @param string $queryId Unique query identifier
     */
    public function startQuery(string $queryId): void
    {
        $this->queryTimers[$queryId] = hrtime(true);
    }

    /**
     * End timing a query and record metrics
     *
     * @param string $queryId Query identifier
     * @param string $sql The executed SQL
     * @return float Duration in milliseconds
     */
    public function endQuery(string $queryId, string $sql): float
    {
        if (!isset($this->queryTimers[$queryId])) {
            return 0.0;
        }

        $durationNs = hrtime(true) - $this->queryTimers[$queryId];
        $durationMs = $durationNs / 1e6;
        unset($this->queryTimers[$queryId]);

        $this->queryCount++;
        $this->requestQueries[] = [
            'sql' => $sql,
            'duration_ms' => $durationMs,
            'timestamp' => microtime(true),
        ];

        // Log slow queries
        if ($durationMs > self::SLOW_QUERY_THRESHOLD_MS) {
            $this->recordSlowQuery($sql, $durationMs);
        }

        // Update aggregate metrics
        $this->updateMetrics($durationMs);

        return $durationMs;
    }

    /**
     * Record a query with timing in one call
     *
     * @param string $sql The SQL query
     * @param callable $executor Function that executes the query
     * @return mixed Query result
     */
    public function monitor(string $sql, callable $executor): mixed
    {
        $queryId = uniqid('q_', true);
        $this->startQuery($queryId);

        try {
            $result = $executor();
            return $result;
        } finally {
            $this->endQuery($queryId, $sql);
        }
    }

    /**
     * Get comprehensive performance report
     *
     * @return array{
     *     request: array,
     *     cache: array,
     *     connection: array,
     *     profiler: array,
     *     aggregate: array
     * }
     */
    public function getReport(): array
    {
        return [
            'request' => $this->getRequestMetrics(),
            'cache' => $this->queryCache->getStats(),
            'connection' => $this->connectionPool->getStats(),
            'profiler' => $this->queryProfiler->getStats(),
            'aggregate' => $this->getAggregateMetrics(),
            'slow_queries' => $this->getRecentSlowQueries(10),
        ];
    }

    /**
     * Get metrics for current request
     *
     * @return array{
     *     query_count: int,
     *     total_query_time_ms: float,
     *     avg_query_time_ms: float,
     *     max_query_time_ms: float,
     *     request_duration_ms: float,
     *     slow_query_count: int
     * }
     */
    public function getRequestMetrics(): array
    {
        $durations = array_column($this->requestQueries, 'duration_ms');
        $totalQueryTime = array_sum($durations);
        $slowCount = count(array_filter($durations, fn($d) => $d > self::SLOW_QUERY_THRESHOLD_MS));

        return [
            'query_count' => $this->queryCount,
            'total_query_time_ms' => round($totalQueryTime, 2),
            'avg_query_time_ms' => $this->queryCount > 0
                ? round($totalQueryTime / $this->queryCount, 2)
                : 0.0,
            'max_query_time_ms' => !empty($durations) ? round(max($durations), 2) : 0.0,
            'request_duration_ms' => round((microtime(true) - $this->requestStart) * 1000, 2),
            'slow_query_count' => $slowCount,
            'queries' => $this->requestQueries,
        ];
    }

    /**
     * Get aggregate metrics (persisted across requests)
     *
     * @return array{
     *     total_queries: int,
     *     total_slow_queries: int,
     *     avg_query_time_ms: float,
     *     queries_per_minute: float,
     *     collection_period_hours: int
     * }
     */
    public function getAggregateMetrics(): array
    {
        $metrics = get_transient(self::METRICS_TRANSIENT_KEY);

        if (!is_array($metrics)) {
            return [
                'total_queries' => 0,
                'total_slow_queries' => 0,
                'avg_query_time_ms' => 0.0,
                'queries_per_minute' => 0.0,
                'collection_period_hours' => self::METRICS_RETENTION_HOURS,
            ];
        }

        $periodSeconds = time() - ($metrics['start_time'] ?? time());
        $queriesPerMinute = $periodSeconds > 0
            ? ($metrics['total_queries'] / $periodSeconds) * 60
            : 0;

        return [
            'total_queries' => $metrics['total_queries'] ?? 0,
            'total_slow_queries' => $metrics['slow_queries'] ?? 0,
            'avg_query_time_ms' => $metrics['total_queries'] > 0
                ? round($metrics['total_time_ms'] / $metrics['total_queries'], 2)
                : 0.0,
            'queries_per_minute' => round($queriesPerMinute, 2),
            'collection_period_hours' => self::METRICS_RETENTION_HOURS,
            'start_time' => $metrics['start_time'] ?? time(),
        ];
    }

    /**
     * Get recent slow queries
     *
     * @param int $limit Maximum queries to return
     * @return array<array{sql: string, duration_ms: float, timestamp: float}>
     */
    public function getRecentSlowQueries(int $limit = 20): array
    {
        $slowQueries = get_transient(self::SLOW_QUERY_LOG_KEY);

        if (!is_array($slowQueries)) {
            return [];
        }

        return array_slice($slowQueries, -$limit);
    }

    /**
     * Check if performance meets targets
     *
     * @return array{
     *     meets_targets: bool,
     *     issues: array<string>,
     *     recommendations: array<string>
     * }
     */
    public function healthCheck(): array
    {
        $issues = [];
        $recommendations = [];

        // Check average query time
        $requestMetrics = $this->getRequestMetrics();
        if ($requestMetrics['avg_query_time_ms'] > self::SLOW_QUERY_THRESHOLD_MS) {
            $issues[] = sprintf(
                'Average query time (%.2fms) exceeds threshold (%.2fms)',
                $requestMetrics['avg_query_time_ms'],
                self::SLOW_QUERY_THRESHOLD_MS
            );
            $recommendations[] = 'Review slow query log and add missing indexes';
        }

        // Check query count per request
        if ($requestMetrics['query_count'] > 50) {
            $issues[] = sprintf(
                'High query count (%d) in single request',
                $requestMetrics['query_count']
            );
            $recommendations[] = 'Consider implementing eager loading or caching';
        }

        // Check cache hit ratio
        $cacheStats = $this->queryCache->getStats();
        if ($cacheStats['hit_ratio'] < 50.0 && $cacheStats['result_hits'] + $cacheStats['result_misses'] > 100) {
            $issues[] = sprintf(
                'Low cache hit ratio (%.2f%%)',
                $cacheStats['hit_ratio']
            );
            $recommendations[] = 'Increase cache TTL or implement cache warming';
        }

        // Check connection health
        if (!$this->connectionPool->isHealthy()) {
            $issues[] = 'Database connection is unhealthy';
            $recommendations[] = 'Check database server status and connection settings';
        }

        // Check for N+1 patterns
        $nPlusOne = $this->queryProfiler->detectNPlusOne();
        if (!empty($nPlusOne)) {
            $issues[] = sprintf(
                'N+1 query patterns detected (%d patterns)',
                count($nPlusOne)
            );
            $recommendations[] = 'Implement batch loading for repeated queries';
        }

        return [
            'meets_targets' => empty($issues),
            'issues' => $issues,
            'recommendations' => $recommendations,
        ];
    }

    /**
     * Log performance summary to error log
     */
    public function logSummary(): void
    {
        $metrics = $this->getRequestMetrics();

        error_log(sprintf(
            '[SAGA][PERF] Request: %d queries, %.2fms total, %.2fms avg, %d slow',
            $metrics['query_count'],
            $metrics['total_query_time_ms'],
            $metrics['avg_query_time_ms'],
            $metrics['slow_query_count']
        ));
    }

    /**
     * Reset all monitoring data
     */
    public function reset(): void
    {
        $this->queryTimers = [];
        $this->requestQueries = [];
        $this->queryCount = 0;
        $this->requestStart = microtime(true);

        delete_transient(self::METRICS_TRANSIENT_KEY);
        delete_transient(self::SLOW_QUERY_LOG_KEY);
    }

    /**
     * Register shutdown hook to log metrics
     */
    public function registerShutdownHook(): void
    {
        register_shutdown_function(function () {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                $this->logSummary();
            }
        });
    }

    /**
     * Export metrics in Prometheus format
     *
     * @return string Prometheus-compatible metrics
     */
    public function exportPrometheusMetrics(): string
    {
        $metrics = $this->getAggregateMetrics();
        $cacheStats = $this->queryCache->getStats();
        $connStats = $this->connectionPool->getStats();

        $lines = [];

        $lines[] = '# HELP saga_db_queries_total Total database queries';
        $lines[] = '# TYPE saga_db_queries_total counter';
        $lines[] = "saga_db_queries_total {$metrics['total_queries']}";

        $lines[] = '# HELP saga_db_slow_queries_total Total slow queries';
        $lines[] = '# TYPE saga_db_slow_queries_total counter';
        $lines[] = "saga_db_slow_queries_total {$metrics['total_slow_queries']}";

        $lines[] = '# HELP saga_db_query_duration_avg_ms Average query duration';
        $lines[] = '# TYPE saga_db_query_duration_avg_ms gauge';
        $lines[] = "saga_db_query_duration_avg_ms {$metrics['avg_query_time_ms']}";

        $lines[] = '# HELP saga_db_cache_hit_ratio Cache hit ratio percentage';
        $lines[] = '# TYPE saga_db_cache_hit_ratio gauge';
        $lines[] = "saga_db_cache_hit_ratio {$cacheStats['hit_ratio']}";

        $lines[] = '# HELP saga_db_connections_active Active database connections';
        $lines[] = '# TYPE saga_db_connections_active gauge';
        $lines[] = "saga_db_connections_active {$connStats['active_connections']}";

        return implode("\n", $lines);
    }

    /**
     * Update aggregate metrics
     */
    private function updateMetrics(float $durationMs): void
    {
        $metrics = get_transient(self::METRICS_TRANSIENT_KEY);

        if (!is_array($metrics)) {
            $metrics = [
                'total_queries' => 0,
                'total_time_ms' => 0.0,
                'slow_queries' => 0,
                'start_time' => time(),
            ];
        }

        $metrics['total_queries']++;
        $metrics['total_time_ms'] += $durationMs;

        if ($durationMs > self::SLOW_QUERY_THRESHOLD_MS) {
            $metrics['slow_queries']++;
        }

        set_transient(
            self::METRICS_TRANSIENT_KEY,
            $metrics,
            self::METRICS_RETENTION_HOURS * HOUR_IN_SECONDS
        );
    }

    /**
     * Record a slow query
     */
    private function recordSlowQuery(string $sql, float $durationMs): void
    {
        $slowQueries = get_transient(self::SLOW_QUERY_LOG_KEY);

        if (!is_array($slowQueries)) {
            $slowQueries = [];
        }

        $slowQueries[] = [
            'sql' => substr($sql, 0, 500), // Truncate long queries
            'duration_ms' => round($durationMs, 2),
            'timestamp' => time(),
        ];

        // Keep only last 100 slow queries
        if (count($slowQueries) > 100) {
            $slowQueries = array_slice($slowQueries, -100);
        }

        set_transient(
            self::SLOW_QUERY_LOG_KEY,
            $slowQueries,
            self::METRICS_RETENTION_HOURS * HOUR_IN_SECONDS
        );

        // Also log to error log for immediate visibility
        error_log(sprintf(
            '[SAGA][SLOW_QUERY] %.2fms: %s',
            $durationMs,
            substr($sql, 0, 200)
        ));
    }
}
