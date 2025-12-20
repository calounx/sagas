<?php
declare(strict_types=1);

namespace SagaManager\Tests\Performance;

use SagaManager\Infrastructure\Database\Performance\QueryCache;
use SagaManager\Infrastructure\Database\Performance\ConnectionPool;
use SagaManager\Infrastructure\Database\Performance\QueryProfiler;
use SagaManager\Infrastructure\Database\Performance\BatchExecutor;
use SagaManager\Infrastructure\Database\Performance\PerformanceMonitor;

/**
 * Database Performance Benchmark Suite
 *
 * Compares:
 * - Direct $wpdb vs abstraction layer overhead
 * - Cached vs uncached queries
 * - Single vs batch operations
 * - Different adapter implementations
 *
 * Run: php tests/Performance/DatabasePerformanceBenchmark.php
 */
final class DatabasePerformanceBenchmark
{
    private const ITERATIONS = 100;
    private const BATCH_SIZES = [10, 50, 100, 500];
    private const WARMUP_ITERATIONS = 5;

    private \wpdb $wpdb;
    private string $tablePrefix;
    private QueryCache $queryCache;
    private ConnectionPool $connectionPool;
    private QueryProfiler $queryProfiler;
    private BatchExecutor $batchExecutor;
    private PerformanceMonitor $performanceMonitor;

    /** @var array<string, array{avg_ms: float, min_ms: float, max_ms: float, iterations: int}> */
    private array $results = [];

    public function __construct()
    {
        global $wpdb;
        $this->wpdb = $wpdb;
        $this->tablePrefix = $wpdb->prefix . 'saga_';

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
     * Run all benchmarks
     */
    public function runAll(): void
    {
        echo "=== Saga Manager Database Performance Benchmarks ===\n\n";
        echo "Configuration:\n";
        echo "- Iterations: " . self::ITERATIONS . "\n";
        echo "- Warmup: " . self::WARMUP_ITERATIONS . "\n";
        echo "- Table prefix: {$this->tablePrefix}\n";
        echo "- Target: <50ms per query\n\n";

        $this->ensureTestTable();

        // Run benchmark suites
        $this->benchmarkDirectVsAbstraction();
        $this->benchmarkCachedVsUncached();
        $this->benchmarkSingleVsBatch();
        $this->benchmarkQueryProfilerOverhead();

        // Print summary
        $this->printResults();

        // Cleanup
        $this->cleanup();
    }

    /**
     * Benchmark: Direct $wpdb vs Abstraction Layer
     *
     * Measures overhead of performance layer wrapper.
     */
    private function benchmarkDirectVsAbstraction(): void
    {
        echo ">>> Benchmark: Direct wpdb vs Abstraction Layer\n";

        // Insert test data
        $this->insertTestData(100);

        // 1. Direct $wpdb query
        $this->benchmark('direct_wpdb_select', function () {
            $sql = $this->wpdb->prepare(
                "SELECT * FROM {$this->tablePrefix}benchmark WHERE id = %d",
                rand(1, 100)
            );
            return $this->wpdb->get_row($sql);
        });

        // 2. With PerformanceMonitor wrapper
        $this->benchmark('monitor_wrapped_select', function () {
            $sql = $this->wpdb->prepare(
                "SELECT * FROM {$this->tablePrefix}benchmark WHERE id = %d",
                rand(1, 100)
            );
            return $this->performanceMonitor->monitor($sql, function () use ($sql) {
                return $this->wpdb->get_row($sql);
            });
        });

        // 3. With ConnectionPool
        $this->benchmark('connection_pool_select', function () {
            return $this->connectionPool->withConnection(function ($wpdb) {
                $sql = $wpdb->prepare(
                    "SELECT * FROM {$this->tablePrefix}benchmark WHERE id = %d",
                    rand(1, 100)
                );
                return $wpdb->get_row($sql);
            });
        });

        // 4. With QueryProfiler
        $this->benchmark('profiler_wrapped_select', function () {
            $sql = $this->wpdb->prepare(
                "SELECT * FROM {$this->tablePrefix}benchmark WHERE id = %d",
                rand(1, 100)
            );
            return $this->queryProfiler->profile($sql, function () use ($sql) {
                return $this->wpdb->get_row($sql);
            });
        });

        echo "\n";
    }

    /**
     * Benchmark: Cached vs Uncached Queries
     */
    private function benchmarkCachedVsUncached(): void
    {
        echo ">>> Benchmark: Cached vs Uncached Queries\n";

        // 1. Uncached repeated query
        $this->benchmark('uncached_repeated_select', function () {
            $sql = $this->wpdb->prepare(
                "SELECT * FROM {$this->tablePrefix}benchmark WHERE id = %d",
                42 // Same ID every time
            );
            return $this->wpdb->get_row($sql);
        });

        // 2. Cached with QueryCache
        $this->queryCache->flush(); // Start fresh
        $this->benchmark('cached_select', function () {
            return $this->queryCache->remember(
                'benchmark_entity_42',
                function () {
                    $sql = $this->wpdb->prepare(
                        "SELECT * FROM {$this->tablePrefix}benchmark WHERE id = %d",
                        42
                    );
                    return $this->wpdb->get_row($sql);
                },
                300
            );
        });

        // 3. Cache miss scenario (different IDs)
        $this->queryCache->flush();
        $counter = 0;
        $this->benchmark('cache_miss_varied_select', function () use (&$counter) {
            $counter++;
            return $this->queryCache->remember(
                "benchmark_entity_{$counter}",
                function () use ($counter) {
                    $sql = $this->wpdb->prepare(
                        "SELECT * FROM {$this->tablePrefix}benchmark WHERE id = %d",
                        $counter % 100 + 1
                    );
                    return $this->wpdb->get_row($sql);
                },
                300
            );
        });

        // Print cache stats
        $cacheStats = $this->queryCache->getStats();
        echo "Cache Hit Ratio: {$cacheStats['hit_ratio']}%\n\n";
    }

    /**
     * Benchmark: Single vs Batch Operations
     */
    private function benchmarkSingleVsBatch(): void
    {
        echo ">>> Benchmark: Single vs Batch Operations\n";

        foreach (self::BATCH_SIZES as $batchSize) {
            // Clear test table
            $this->wpdb->query("TRUNCATE TABLE {$this->tablePrefix}benchmark");

            // 1. Single inserts
            $singleStart = hrtime(true);
            for ($i = 0; $i < $batchSize; $i++) {
                $this->wpdb->insert(
                    "{$this->tablePrefix}benchmark",
                    [
                        'name' => "Entity {$i}",
                        'value' => rand(1, 1000),
                        'created_at' => current_time('mysql'),
                    ]
                );
            }
            $singleDuration = (hrtime(true) - $singleStart) / 1e6;

            // Clear for batch test
            $this->wpdb->query("TRUNCATE TABLE {$this->tablePrefix}benchmark");

            // 2. Batch insert
            $rows = [];
            for ($i = 0; $i < $batchSize; $i++) {
                $rows[] = [
                    "Entity {$i}",
                    rand(1, 1000),
                    current_time('mysql'),
                ];
            }

            $batchStart = hrtime(true);
            $this->batchExecutor->bulkInsert(
                'benchmark',
                ['name', 'value', 'created_at'],
                $rows
            );
            $batchDuration = (hrtime(true) - $batchStart) / 1e6;

            $speedup = $singleDuration / $batchDuration;

            echo sprintf(
                "  Batch size %d: Single=%.2fms, Batch=%.2fms, Speedup=%.1fx\n",
                $batchSize,
                $singleDuration,
                $batchDuration,
                $speedup
            );

            $this->results["insert_single_{$batchSize}"] = [
                'avg_ms' => $singleDuration,
                'min_ms' => $singleDuration,
                'max_ms' => $singleDuration,
                'iterations' => 1,
            ];
            $this->results["insert_batch_{$batchSize}"] = [
                'avg_ms' => $batchDuration,
                'min_ms' => $batchDuration,
                'max_ms' => $batchDuration,
                'iterations' => 1,
            ];
        }

        echo "\n";
    }

    /**
     * Benchmark: Query Profiler Overhead
     */
    private function benchmarkQueryProfilerOverhead(): void
    {
        echo ">>> Benchmark: Query Profiler Overhead\n";

        $this->insertTestData(100);

        // 1. Without profiling
        $this->queryProfiler->setEnabled(false);
        $this->benchmark('query_no_profiling', function () {
            $sql = $this->wpdb->prepare(
                "SELECT * FROM {$this->tablePrefix}benchmark WHERE value > %d ORDER BY value LIMIT 10",
                rand(1, 500)
            );
            return $this->wpdb->get_results($sql);
        });

        // 2. With profiling enabled
        $this->queryProfiler->setEnabled(true);
        $this->benchmark('query_with_profiling', function () {
            $sql = $this->wpdb->prepare(
                "SELECT * FROM {$this->tablePrefix}benchmark WHERE value > %d ORDER BY value LIMIT 10",
                rand(1, 500)
            );
            return $this->queryProfiler->profile($sql, function () use ($sql) {
                return $this->wpdb->get_results($sql);
            });
        });

        // 3. EXPLAIN analysis overhead
        $this->benchmark('explain_analysis', function () {
            $sql = $this->wpdb->prepare(
                "SELECT * FROM {$this->tablePrefix}benchmark WHERE value > %d ORDER BY value LIMIT 10",
                500
            );
            return $this->queryProfiler->explain($sql);
        });

        // Print profiler stats
        $profilerStats = $this->queryProfiler->getStats();
        echo "Profiler - Total Queries: {$profilerStats['total_queries']}, ";
        echo "Slow Queries: {$profilerStats['slow_queries']}, ";
        echo "Avg Duration: {$profilerStats['avg_duration_ms']}ms\n\n";
    }

    /**
     * Run a benchmark with timing
     *
     * @param string $name Benchmark name
     * @param callable $operation Operation to benchmark
     */
    private function benchmark(string $name, callable $operation): void
    {
        // Warmup
        for ($i = 0; $i < self::WARMUP_ITERATIONS; $i++) {
            $operation();
        }

        // Benchmark
        $times = [];
        for ($i = 0; $i < self::ITERATIONS; $i++) {
            $start = hrtime(true);
            $operation();
            $times[] = (hrtime(true) - $start) / 1e6; // ms
        }

        $avg = array_sum($times) / count($times);
        $min = min($times);
        $max = max($times);

        $this->results[$name] = [
            'avg_ms' => round($avg, 4),
            'min_ms' => round($min, 4),
            'max_ms' => round($max, 4),
            'iterations' => self::ITERATIONS,
        ];

        $status = $avg < 50 ? 'PASS' : 'SLOW';
        echo sprintf(
            "  %-35s avg=%.4fms min=%.4fms max=%.4fms [%s]\n",
            $name,
            $avg,
            $min,
            $max,
            $status
        );
    }

    /**
     * Print final results summary
     */
    private function printResults(): void
    {
        echo "=== Performance Summary ===\n\n";

        // Calculate overhead percentages
        $directAvg = $this->results['direct_wpdb_select']['avg_ms'] ?? 0;
        $monitorAvg = $this->results['monitor_wrapped_select']['avg_ms'] ?? 0;
        $profilerAvg = $this->results['profiler_wrapped_select']['avg_ms'] ?? 0;

        if ($directAvg > 0) {
            $monitorOverhead = (($monitorAvg - $directAvg) / $directAvg) * 100;
            $profilerOverhead = (($profilerAvg - $directAvg) / $directAvg) * 100;

            echo "Abstraction Layer Overhead:\n";
            echo sprintf("  - PerformanceMonitor: %.2f%% overhead\n", $monitorOverhead);
            echo sprintf("  - QueryProfiler: %.2f%% overhead\n", $profilerOverhead);
            echo sprintf("  - Target: <5%% overhead, Status: %s\n\n",
                max($monitorOverhead, $profilerOverhead) < 5 ? 'PASS' : 'NEEDS OPTIMIZATION'
            );
        }

        // Cache effectiveness
        $uncachedAvg = $this->results['uncached_repeated_select']['avg_ms'] ?? 0;
        $cachedAvg = $this->results['cached_select']['avg_ms'] ?? 0;

        if ($uncachedAvg > 0) {
            $cacheSpeedup = $uncachedAvg / max($cachedAvg, 0.001);
            echo "Cache Effectiveness:\n";
            echo sprintf("  - Uncached: %.4fms\n", $uncachedAvg);
            echo sprintf("  - Cached: %.4fms\n", $cachedAvg);
            echo sprintf("  - Speedup: %.1fx\n\n", $cacheSpeedup);
        }

        // Batch operations summary
        echo "Batch Operation Speedups:\n";
        foreach (self::BATCH_SIZES as $size) {
            $single = $this->results["insert_single_{$size}"]['avg_ms'] ?? 0;
            $batch = $this->results["insert_batch_{$size}"]['avg_ms'] ?? 0;
            if ($batch > 0) {
                echo sprintf("  - %d rows: %.1fx faster\n", $size, $single / $batch);
            }
        }

        // Overall health
        echo "\n=== Health Check ===\n";
        $health = $this->performanceMonitor->healthCheck();
        echo "Meets Targets: " . ($health['meets_targets'] ? 'YES' : 'NO') . "\n";

        if (!empty($health['issues'])) {
            echo "Issues:\n";
            foreach ($health['issues'] as $issue) {
                echo "  - {$issue}\n";
            }
        }

        if (!empty($health['recommendations'])) {
            echo "Recommendations:\n";
            foreach ($health['recommendations'] as $rec) {
                echo "  - {$rec}\n";
            }
        }
    }

    /**
     * Create test table
     */
    private function ensureTestTable(): void
    {
        $charset = $this->wpdb->get_charset_collate();

        $sql = "CREATE TABLE IF NOT EXISTS {$this->tablePrefix}benchmark (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(255) NOT NULL,
            value INT NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_value (value)
        ) {$charset}";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql);
    }

    /**
     * Insert test data
     */
    private function insertTestData(int $count): void
    {
        $this->wpdb->query("TRUNCATE TABLE {$this->tablePrefix}benchmark");

        $rows = [];
        for ($i = 1; $i <= $count; $i++) {
            $rows[] = ["Entity {$i}", rand(1, 1000), current_time('mysql')];
        }

        $this->batchExecutor->bulkInsert(
            'benchmark',
            ['name', 'value', 'created_at'],
            $rows
        );
    }

    /**
     * Cleanup test table
     */
    private function cleanup(): void
    {
        $this->wpdb->query("DROP TABLE IF EXISTS {$this->tablePrefix}benchmark");
    }
}

// Run benchmarks if executed directly
if (php_sapi_name() === 'cli' && realpath($_SERVER['argv'][0]) === __FILE__) {
    // Bootstrap WordPress
    require_once dirname(__DIR__, 4) . '/wp-load.php';

    $benchmark = new DatabasePerformanceBenchmark();
    $benchmark->runAll();
}
