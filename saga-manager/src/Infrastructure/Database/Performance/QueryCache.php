<?php
declare(strict_types=1);

namespace SagaManager\Infrastructure\Database\Performance;

/**
 * Query Cache - Compiled SQL and Result Caching
 *
 * Two-level caching strategy:
 * 1. Compiled query cache - Stores prepared SQL strings
 * 2. Result cache - Stores query results with TTL
 *
 * Target: <5% overhead vs direct $wpdb calls
 */
final class QueryCache
{
    private const CACHE_GROUP = 'saga_query';
    private const DEFAULT_TTL = 300; // 5 minutes
    private const COMPILED_CACHE_KEY = 'saga_compiled_queries';

    /** @var array<string, string> In-memory compiled query cache */
    private array $compiledQueries = [];

    /** @var array<string, int> Cache hit statistics */
    private array $stats = [
        'compiled_hits' => 0,
        'compiled_misses' => 0,
        'result_hits' => 0,
        'result_misses' => 0,
    ];

    /** @var array<string, callable> Invalidation callbacks by tag */
    private array $invalidationCallbacks = [];

    private \wpdb $wpdb;

    public function __construct()
    {
        global $wpdb;
        $this->wpdb = $wpdb;
        $this->loadCompiledCache();
    }

    /**
     * Get cached query result or execute and cache
     *
     * @param string $cacheKey Unique cache key for the query
     * @param callable $queryExecutor Function that returns query result
     * @param int $ttl Cache TTL in seconds
     * @param array<string> $tags Tags for grouped invalidation
     * @return mixed Query result
     */
    public function remember(
        string $cacheKey,
        callable $queryExecutor,
        int $ttl = self::DEFAULT_TTL,
        array $tags = []
    ): mixed {
        $fullKey = $this->buildCacheKey($cacheKey);

        // Check result cache first
        $cached = wp_cache_get($fullKey, self::CACHE_GROUP);

        if ($cached !== false) {
            $this->stats['result_hits']++;
            return $cached;
        }

        $this->stats['result_misses']++;

        // Execute query and cache result
        $result = $queryExecutor();

        wp_cache_set($fullKey, $result, self::CACHE_GROUP, $ttl);

        // Store tag associations for invalidation
        foreach ($tags as $tag) {
            $this->addTagAssociation($tag, $fullKey);
        }

        return $result;
    }

    /**
     * Get or compile a prepared SQL statement
     *
     * Caches the compiled SQL string to avoid repeated prepare() overhead
     *
     * @param string $queryId Unique identifier for the query template
     * @param string $sqlTemplate SQL template with placeholders
     * @param array<mixed> $params Parameters for prepare()
     * @return string Compiled SQL ready for execution
     */
    public function compiledPrepare(
        string $queryId,
        string $sqlTemplate,
        array $params
    ): string {
        // For parameterized queries, we cache the template and apply params each time
        $templateKey = $this->buildTemplateKey($queryId);

        if (isset($this->compiledQueries[$templateKey])) {
            $this->stats['compiled_hits']++;
            // Re-apply parameters to cached template
            return $this->wpdb->prepare(
                $this->compiledQueries[$templateKey],
                ...$params
            );
        }

        $this->stats['compiled_misses']++;

        // Store the template
        $this->compiledQueries[$templateKey] = $sqlTemplate;

        // Persist to WordPress cache for cross-request sharing
        $this->saveCompiledCache();

        return $this->wpdb->prepare($sqlTemplate, ...$params);
    }

    /**
     * Cache query result with automatic key generation
     *
     * @param string $sql The SQL query
     * @param mixed $result Query result
     * @param int $ttl Cache TTL
     */
    public function cacheResult(string $sql, mixed $result, int $ttl = self::DEFAULT_TTL): void
    {
        $cacheKey = $this->generateSqlCacheKey($sql);
        wp_cache_set($cacheKey, $result, self::CACHE_GROUP, $ttl);
    }

    /**
     * Get cached result by SQL
     *
     * @param string $sql The SQL query
     * @return mixed|null Cached result or null if not found
     */
    public function getCachedResult(string $sql): mixed
    {
        $cacheKey = $this->generateSqlCacheKey($sql);
        $cached = wp_cache_get($cacheKey, self::CACHE_GROUP);

        if ($cached !== false) {
            $this->stats['result_hits']++;
            return $cached;
        }

        $this->stats['result_misses']++;
        return null;
    }

    /**
     * Invalidate cache by tag
     *
     * @param string $tag Cache tag to invalidate
     */
    public function invalidateByTag(string $tag): void
    {
        $tagKey = $this->buildTagKey($tag);
        $keys = wp_cache_get($tagKey, self::CACHE_GROUP);

        if (is_array($keys)) {
            foreach ($keys as $key) {
                wp_cache_delete($key, self::CACHE_GROUP);
            }
            wp_cache_delete($tagKey, self::CACHE_GROUP);
        }

        // Execute registered callbacks
        if (isset($this->invalidationCallbacks[$tag])) {
            ($this->invalidationCallbacks[$tag])();
        }
    }

    /**
     * Invalidate cache for specific entity
     *
     * @param string $entityType Entity type (e.g., 'entity', 'relationship')
     * @param int $entityId Entity ID
     */
    public function invalidateEntity(string $entityType, int $entityId): void
    {
        $this->invalidateByTag("{$entityType}_{$entityId}");
        $this->invalidateByTag($entityType);
    }

    /**
     * Clear all query caches
     */
    public function flush(): void
    {
        wp_cache_flush_group(self::CACHE_GROUP);
        $this->compiledQueries = [];
        $this->stats = [
            'compiled_hits' => 0,
            'compiled_misses' => 0,
            'result_hits' => 0,
            'result_misses' => 0,
        ];
    }

    /**
     * Get cache statistics
     *
     * @return array{compiled_hits: int, compiled_misses: int, result_hits: int, result_misses: int, hit_ratio: float}
     */
    public function getStats(): array
    {
        $totalResult = $this->stats['result_hits'] + $this->stats['result_misses'];
        $hitRatio = $totalResult > 0
            ? round($this->stats['result_hits'] / $totalResult * 100, 2)
            : 0.0;

        return [
            ...$this->stats,
            'hit_ratio' => $hitRatio,
            'compiled_queries_cached' => count($this->compiledQueries),
        ];
    }

    /**
     * Register invalidation callback for a tag
     *
     * @param string $tag Cache tag
     * @param callable $callback Callback to execute on invalidation
     */
    public function onInvalidate(string $tag, callable $callback): void
    {
        $this->invalidationCallbacks[$tag] = $callback;
    }

    /**
     * Warm up cache with pre-defined queries
     *
     * @param array<string, callable> $queries Map of cache key => query executor
     * @param int $ttl Cache TTL
     */
    public function warmUp(array $queries, int $ttl = self::DEFAULT_TTL): void
    {
        foreach ($queries as $key => $executor) {
            $this->remember($key, $executor, $ttl);
        }
    }

    /**
     * Build full cache key with prefix
     */
    private function buildCacheKey(string $key): string
    {
        return 'saga_result_' . md5($key);
    }

    /**
     * Build template key for compiled queries
     */
    private function buildTemplateKey(string $queryId): string
    {
        return 'tpl_' . $queryId;
    }

    /**
     * Build tag association key
     */
    private function buildTagKey(string $tag): string
    {
        return 'saga_tag_' . md5($tag);
    }

    /**
     * Generate cache key from SQL query
     */
    private function generateSqlCacheKey(string $sql): string
    {
        return 'saga_sql_' . md5($sql);
    }

    /**
     * Add key to tag association for grouped invalidation
     */
    private function addTagAssociation(string $tag, string $cacheKey): void
    {
        $tagKey = $this->buildTagKey($tag);
        $keys = wp_cache_get($tagKey, self::CACHE_GROUP);

        if (!is_array($keys)) {
            $keys = [];
        }

        if (!in_array($cacheKey, $keys, true)) {
            $keys[] = $cacheKey;
            wp_cache_set($tagKey, $keys, self::CACHE_GROUP, 3600); // 1 hour for tags
        }
    }

    /**
     * Load compiled query cache from WordPress object cache
     */
    private function loadCompiledCache(): void
    {
        $cached = wp_cache_get(self::COMPILED_CACHE_KEY, self::CACHE_GROUP);

        if (is_array($cached)) {
            $this->compiledQueries = $cached;
        }
    }

    /**
     * Save compiled query cache to WordPress object cache
     */
    private function saveCompiledCache(): void
    {
        // Limit cache size to prevent memory issues
        if (count($this->compiledQueries) > 1000) {
            $this->compiledQueries = array_slice($this->compiledQueries, -500, null, true);
        }

        wp_cache_set(
            self::COMPILED_CACHE_KEY,
            $this->compiledQueries,
            self::CACHE_GROUP,
            3600 // 1 hour
        );
    }
}
