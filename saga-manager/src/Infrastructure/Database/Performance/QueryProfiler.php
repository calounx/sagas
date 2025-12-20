<?php
declare(strict_types=1);

namespace SagaManager\Infrastructure\Database\Performance;

/**
 * Query Profiler - Performance Analysis and Optimization Suggestions
 *
 * Features:
 * - Query execution timing
 * - EXPLAIN plan analysis
 * - Slow query detection (>50ms threshold)
 * - N+1 query pattern detection
 * - Index usage analysis
 * - Query optimization suggestions
 */
final class QueryProfiler
{
    private const SLOW_QUERY_THRESHOLD_MS = 50.0;
    private const N_PLUS_ONE_THRESHOLD = 10;

    /** @var array<int, QueryProfile> Profiled queries */
    private array $profiles = [];

    /** @var array<string, int> Query pattern counts for N+1 detection */
    private array $queryPatterns = [];

    /** @var bool Whether profiling is enabled */
    private bool $enabled = false;

    /** @var int Maximum profiles to keep in memory */
    private int $maxProfiles = 1000;

    private \wpdb $wpdb;

    public function __construct()
    {
        global $wpdb;
        $this->wpdb = $wpdb;

        // Enable profiling in debug mode
        $this->enabled = defined('WP_DEBUG') && WP_DEBUG;
    }

    /**
     * Profile a query execution
     *
     * @param string $sql The SQL query
     * @param callable $executor Function that executes the query
     * @return mixed Query result
     */
    public function profile(string $sql, callable $executor): mixed
    {
        if (!$this->enabled) {
            return $executor();
        }

        $start = hrtime(true);
        $result = $executor();
        $duration = (hrtime(true) - $start) / 1e6; // Convert to milliseconds

        $this->recordProfile($sql, $duration);

        return $result;
    }

    /**
     * Record a query profile
     */
    private function recordProfile(string $sql, float $durationMs): void
    {
        $pattern = $this->normalizeQueryPattern($sql);
        $this->queryPatterns[$pattern] = ($this->queryPatterns[$pattern] ?? 0) + 1;

        $profile = new QueryProfile(
            sql: $sql,
            durationMs: $durationMs,
            timestamp: microtime(true),
            isSlow: $durationMs > self::SLOW_QUERY_THRESHOLD_MS,
            pattern: $pattern
        );

        $this->profiles[] = $profile;

        // Log slow queries
        if ($profile->isSlow) {
            $this->logSlowQuery($profile);
        }

        // Trim old profiles if needed
        if (count($this->profiles) > $this->maxProfiles) {
            $this->profiles = array_slice($this->profiles, -($this->maxProfiles / 2));
        }
    }

    /**
     * Get EXPLAIN analysis for a SELECT query
     *
     * @param string $sql SELECT query to analyze
     * @return array{
     *     plan: array,
     *     analysis: array{type: string, issues: array, suggestions: array},
     *     estimated_rows: int
     * }
     */
    public function explain(string $sql): array
    {
        // Only EXPLAIN SELECT queries
        if (!preg_match('/^\s*SELECT/i', $sql)) {
            return [
                'plan' => [],
                'analysis' => [
                    'type' => 'non_select',
                    'issues' => [],
                    'suggestions' => ['EXPLAIN only works with SELECT queries'],
                ],
                'estimated_rows' => 0,
            ];
        }

        $explainSql = 'EXPLAIN ' . $sql;
        $plan = $this->wpdb->get_results($explainSql, ARRAY_A);

        if (empty($plan)) {
            return [
                'plan' => [],
                'analysis' => [
                    'type' => 'error',
                    'issues' => ['Could not generate EXPLAIN plan'],
                    'suggestions' => [],
                ],
                'estimated_rows' => 0,
            ];
        }

        return [
            'plan' => $plan,
            'analysis' => $this->analyzeExplainPlan($plan),
            'estimated_rows' => $this->estimateRows($plan),
        ];
    }

    /**
     * Analyze EXPLAIN output for issues
     *
     * @param array $plan EXPLAIN result
     * @return array{type: string, issues: array<string>, suggestions: array<string>}
     */
    private function analyzeExplainPlan(array $plan): array
    {
        $issues = [];
        $suggestions = [];
        $type = 'optimal';

        foreach ($plan as $row) {
            // Check for table scan (type = ALL)
            if (isset($row['type']) && $row['type'] === 'ALL') {
                $issues[] = "Full table scan on {$row['table']}";
                $suggestions[] = "Consider adding an index on {$row['table']} for columns in WHERE clause";
                $type = 'full_scan';
            }

            // Check for no index usage
            if (empty($row['key']) && !empty($row['possible_keys'])) {
                $issues[] = "Index exists but not used on {$row['table']}";
                $suggestions[] = "Index {$row['possible_keys']} available but not selected - check query conditions";
            }

            // Check for filesort
            if (isset($row['Extra']) && str_contains($row['Extra'], 'Using filesort')) {
                $issues[] = "Filesort required for ordering";
                $suggestions[] = "Add covering index for ORDER BY columns";
            }

            // Check for temporary table
            if (isset($row['Extra']) && str_contains($row['Extra'], 'Using temporary')) {
                $issues[] = "Temporary table created";
                $suggestions[] = "Simplify GROUP BY or add appropriate indexes";
            }

            // Check for high row estimate
            if (isset($row['rows']) && $row['rows'] > 10000) {
                $issues[] = "High row estimate ({$row['rows']} rows) for {$row['table']}";
                $suggestions[] = "Consider adding more selective indexes or query conditions";
            }

            // Check for index scan without condition
            if (isset($row['type']) && $row['type'] === 'index' && empty($row['ref'])) {
                $issues[] = "Full index scan on {$row['table']}";
                $suggestions[] = "Query is scanning entire index - add WHERE conditions";
            }
        }

        if (!empty($issues)) {
            $type = count($issues) > 2 ? 'needs_optimization' : 'suboptimal';
        }

        return [
            'type' => $type,
            'issues' => $issues,
            'suggestions' => $suggestions,
        ];
    }

    /**
     * Estimate total rows from EXPLAIN plan
     */
    private function estimateRows(array $plan): int
    {
        $total = 1;
        foreach ($plan as $row) {
            $total *= (int) ($row['rows'] ?? 1);
        }
        return $total;
    }

    /**
     * Detect N+1 query patterns
     *
     * @return array<array{pattern: string, count: int, is_n_plus_one: bool}>
     */
    public function detectNPlusOne(): array
    {
        $results = [];

        foreach ($this->queryPatterns as $pattern => $count) {
            $results[] = [
                'pattern' => $pattern,
                'count' => $count,
                'is_n_plus_one' => $count >= self::N_PLUS_ONE_THRESHOLD,
            ];
        }

        // Sort by count descending
        usort($results, fn($a, $b) => $b['count'] <=> $a['count']);

        return array_filter($results, fn($r) => $r['is_n_plus_one']);
    }

    /**
     * Get slow queries
     *
     * @param int $limit Maximum queries to return
     * @return array<QueryProfile>
     */
    public function getSlowQueries(int $limit = 20): array
    {
        $slow = array_filter($this->profiles, fn($p) => $p->isSlow);
        usort($slow, fn($a, $b) => $b->durationMs <=> $a->durationMs);

        return array_slice($slow, 0, $limit);
    }

    /**
     * Get profiling statistics
     *
     * @return array{
     *     total_queries: int,
     *     slow_queries: int,
     *     avg_duration_ms: float,
     *     max_duration_ms: float,
     *     n_plus_one_patterns: int
     * }
     */
    public function getStats(): array
    {
        $totalQueries = count($this->profiles);
        $slowQueries = count(array_filter($this->profiles, fn($p) => $p->isSlow));

        $durations = array_map(fn($p) => $p->durationMs, $this->profiles);
        $avgDuration = $totalQueries > 0 ? array_sum($durations) / $totalQueries : 0;
        $maxDuration = $totalQueries > 0 ? max($durations) : 0;

        $nPlusOnePatterns = count($this->detectNPlusOne());

        return [
            'total_queries' => $totalQueries,
            'slow_queries' => $slowQueries,
            'avg_duration_ms' => round($avgDuration, 2),
            'max_duration_ms' => round($maxDuration, 2),
            'n_plus_one_patterns' => $nPlusOnePatterns,
            'slow_query_threshold_ms' => self::SLOW_QUERY_THRESHOLD_MS,
        ];
    }

    /**
     * Suggest indexes based on query patterns
     *
     * @param string $table Table name
     * @return array<string> Index creation suggestions
     */
    public function suggestIndexes(string $table): array
    {
        $suggestions = [];
        $analyzedColumns = [];

        foreach ($this->profiles as $profile) {
            if (!str_contains($profile->sql, $table)) {
                continue;
            }

            // Extract WHERE clause columns
            if (preg_match_all('/WHERE\s+.*?(\w+)\s*[=<>]/i', $profile->sql, $matches)) {
                foreach ($matches[1] as $column) {
                    $analyzedColumns[$column] = ($analyzedColumns[$column] ?? 0) + 1;
                }
            }

            // Extract JOIN columns
            if (preg_match_all('/ON\s+.*?\.(\w+)\s*=\s*.*?\.(\w+)/i', $profile->sql, $matches)) {
                foreach ($matches[1] as $column) {
                    $analyzedColumns[$column] = ($analyzedColumns[$column] ?? 0) + 1;
                }
                foreach ($matches[2] as $column) {
                    $analyzedColumns[$column] = ($analyzedColumns[$column] ?? 0) + 1;
                }
            }

            // Extract ORDER BY columns
            if (preg_match_all('/ORDER\s+BY\s+(\w+)/i', $profile->sql, $matches)) {
                foreach ($matches[1] as $column) {
                    $analyzedColumns[$column] = ($analyzedColumns[$column] ?? 0) + 1;
                }
            }
        }

        // Suggest indexes for frequently used columns
        arsort($analyzedColumns);
        foreach ($analyzedColumns as $column => $count) {
            if ($count >= 3) {
                $suggestions[] = "CREATE INDEX idx_{$table}_{$column} ON {$table}({$column});";
            }
        }

        return $suggestions;
    }

    /**
     * Enable or disable profiling
     */
    public function setEnabled(bool $enabled): void
    {
        $this->enabled = $enabled;
    }

    /**
     * Check if profiling is enabled
     */
    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    /**
     * Clear profiling data
     */
    public function clear(): void
    {
        $this->profiles = [];
        $this->queryPatterns = [];
    }

    /**
     * Normalize SQL to a pattern for N+1 detection
     *
     * Replaces literal values with placeholders
     */
    private function normalizeQueryPattern(string $sql): string
    {
        // Remove numeric literals
        $pattern = preg_replace('/\b\d+\b/', '?', $sql);

        // Remove string literals
        $pattern = preg_replace("/'[^']*'/", '?', $pattern);
        $pattern = preg_replace('/"[^"]*"/', '?', $pattern);

        // Normalize whitespace
        $pattern = preg_replace('/\s+/', ' ', trim($pattern));

        return $pattern;
    }

    /**
     * Log slow query for monitoring
     */
    private function logSlowQuery(QueryProfile $profile): void
    {
        if (!$this->enabled) {
            return;
        }

        error_log(sprintf(
            '[SAGA][SLOW_QUERY] %.2fms: %s',
            $profile->durationMs,
            substr($profile->sql, 0, 200)
        ));
    }
}

/**
 * Query Profile Value Object
 */
final readonly class QueryProfile
{
    public function __construct(
        public string $sql,
        public float $durationMs,
        public float $timestamp,
        public bool $isSlow,
        public string $pattern
    ) {}
}
