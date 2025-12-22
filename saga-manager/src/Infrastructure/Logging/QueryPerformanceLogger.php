<?php
declare(strict_types=1);

namespace SagaManager\Infrastructure\Logging;

/**
 * Query Performance Logger
 *
 * Tracks and logs database query performance against the target threshold.
 * Target: sub-50ms query response time as specified in CLAUDE.md.
 */
class QueryPerformanceLogger
{
    private const TARGET_MS = 50.0;
    private const SLOW_QUERY_THRESHOLD = 100.0; // 2x target
    private const CRITICAL_THRESHOLD = 500.0; // 10x target

    private bool $enabled;
    private array $metrics = [];

    public function __construct()
    {
        $this->enabled = defined('WP_DEBUG') && WP_DEBUG;
    }

    /**
     * Start timing a query
     *
     * @param string $operation Operation name (e.g., 'findById', 'findBySaga')
     * @return string Timer ID
     */
    public function start(string $operation): string
    {
        $timerId = uniqid('query_', true);

        $this->metrics[$timerId] = [
            'operation' => $operation,
            'start' => hrtime(true),
            'end' => null,
            'duration_ms' => null,
        ];

        return $timerId;
    }

    /**
     * Stop timing and log if threshold exceeded
     *
     * @param string $timerId Timer ID from start()
     * @param string|null $query SQL query (sanitized) for context
     * @param int|null $resultCount Number of results returned
     */
    public function stop(string $timerId, ?string $query = null, ?int $resultCount = null): float
    {
        if (!isset($this->metrics[$timerId])) {
            return 0.0;
        }

        $this->metrics[$timerId]['end'] = hrtime(true);
        $durationNs = $this->metrics[$timerId]['end'] - $this->metrics[$timerId]['start'];
        $durationMs = $durationNs / 1_000_000;
        $this->metrics[$timerId]['duration_ms'] = $durationMs;

        $operation = $this->metrics[$timerId]['operation'];

        // Track metrics for performance dashboard
        $this->trackMetric($operation, $durationMs);

        // Log based on threshold
        if ($durationMs > self::CRITICAL_THRESHOLD) {
            $this->logCritical($operation, $durationMs, $query, $resultCount);
        } elseif ($durationMs > self::SLOW_QUERY_THRESHOLD) {
            $this->logSlow($operation, $durationMs, $query, $resultCount);
        } elseif ($durationMs > self::TARGET_MS && $this->enabled) {
            $this->logAboveTarget($operation, $durationMs, $query, $resultCount);
        }

        // Clean up old metrics
        unset($this->metrics[$timerId]);

        return $durationMs;
    }

    /**
     * Execute a callable and measure its duration
     *
     * @template T
     * @param string $operation Operation name
     * @param callable(): T $callback
     * @param string|null $query Optional query for logging
     * @return T
     */
    public function measure(string $operation, callable $callback, ?string $query = null): mixed
    {
        $timerId = $this->start($operation);

        try {
            $result = $callback();

            $resultCount = is_array($result) ? count($result) : (is_null($result) ? 0 : 1);
            $this->stop($timerId, $query, $resultCount);

            return $result;
        } catch (\Exception $e) {
            $this->stop($timerId, $query, 0);
            throw $e;
        }
    }

    /**
     * Track metric for performance dashboard
     */
    private function trackMetric(string $operation, float $durationMs): void
    {
        // Use WordPress transients for hourly metrics
        $key = 'saga_perf_' . date('YmdH');
        $metrics = get_transient($key) ?: [
            'total_queries' => 0,
            'total_duration_ms' => 0.0,
            'above_target' => 0,
            'slow_queries' => 0,
            'critical_queries' => 0,
            'by_operation' => [],
        ];

        $metrics['total_queries']++;
        $metrics['total_duration_ms'] += $durationMs;

        if ($durationMs > self::TARGET_MS) {
            $metrics['above_target']++;
        }
        if ($durationMs > self::SLOW_QUERY_THRESHOLD) {
            $metrics['slow_queries']++;
        }
        if ($durationMs > self::CRITICAL_THRESHOLD) {
            $metrics['critical_queries']++;
        }

        // Track by operation
        if (!isset($metrics['by_operation'][$operation])) {
            $metrics['by_operation'][$operation] = [
                'count' => 0,
                'total_ms' => 0.0,
                'max_ms' => 0.0,
            ];
        }

        $metrics['by_operation'][$operation]['count']++;
        $metrics['by_operation'][$operation]['total_ms'] += $durationMs;
        $metrics['by_operation'][$operation]['max_ms'] = max(
            $metrics['by_operation'][$operation]['max_ms'],
            $durationMs
        );

        set_transient($key, $metrics, HOUR_IN_SECONDS);
    }

    /**
     * Log critical performance issue (always logged)
     */
    private function logCritical(string $operation, float $durationMs, ?string $query, ?int $resultCount): void
    {
        $message = sprintf(
            '[SAGA][CRITICAL][PERF] %s took %.2fms (target: %.0fms, threshold: %.0fms)',
            $operation,
            $durationMs,
            self::TARGET_MS,
            self::CRITICAL_THRESHOLD
        );

        if ($resultCount !== null) {
            $message .= " | Results: {$resultCount}";
        }

        if ($query !== null) {
            $message .= " | Query: " . substr($query, 0, 200);
        }

        error_log($message);
    }

    /**
     * Log slow query (always logged)
     */
    private function logSlow(string $operation, float $durationMs, ?string $query, ?int $resultCount): void
    {
        $message = sprintf(
            '[SAGA][PERF][SLOW] %s took %.2fms (target: %.0fms)',
            $operation,
            $durationMs,
            self::TARGET_MS
        );

        if ($resultCount !== null) {
            $message .= " | Results: {$resultCount}";
        }

        if ($query !== null) {
            $message .= " | Query: " . substr($query, 0, 150);
        }

        error_log($message);
    }

    /**
     * Log query above target (debug mode only)
     */
    private function logAboveTarget(string $operation, float $durationMs, ?string $query, ?int $resultCount): void
    {
        if (!$this->enabled) {
            return;
        }

        $message = sprintf(
            '[SAGA][PERF] %s took %.2fms (target: %.0fms)',
            $operation,
            $durationMs,
            self::TARGET_MS
        );

        if ($resultCount !== null) {
            $message .= " | Results: {$resultCount}";
        }

        error_log($message);
    }

    /**
     * Get performance summary for the current hour
     *
     * @return array{total_queries: int, avg_ms: float, above_target_pct: float, slow_queries: int}
     */
    public static function getHourlySummary(): array
    {
        $key = 'saga_perf_' . date('YmdH');
        $metrics = get_transient($key);

        if (!$metrics || $metrics['total_queries'] === 0) {
            return [
                'total_queries' => 0,
                'avg_ms' => 0.0,
                'above_target_pct' => 0.0,
                'slow_queries' => 0,
                'critical_queries' => 0,
            ];
        }

        return [
            'total_queries' => $metrics['total_queries'],
            'avg_ms' => $metrics['total_duration_ms'] / $metrics['total_queries'],
            'above_target_pct' => ($metrics['above_target'] / $metrics['total_queries']) * 100,
            'slow_queries' => $metrics['slow_queries'],
            'critical_queries' => $metrics['critical_queries'],
            'by_operation' => $metrics['by_operation'],
        ];
    }

    /**
     * Check if performance is within acceptable bounds
     *
     * @return bool True if >90% of queries are under target
     */
    public static function isPerformanceHealthy(): bool
    {
        $summary = self::getHourlySummary();

        if ($summary['total_queries'] < 10) {
            return true; // Not enough data
        }

        return $summary['above_target_pct'] < 10.0;
    }
}
