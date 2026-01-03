<?php
/**
 * Cache Manager
 *
 * Centralized WordPress object cache handling service.
 * Eliminates code duplication across repositories by providing
 * consistent cache operations with automatic key namespacing.
 *
 * @package SagaManager
 * @subpackage AI\Services
 * @since 1.5.0
 */

declare(strict_types=1);

namespace SagaManager\AI\Services;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Cache Manager Service
 *
 * Single Responsibility Principle: Handles all caching logic.
 * Used by repositories to DRY up repeated wp_cache_* patterns.
 *
 * @example
 * $cache = new CacheManager('saga', 300);
 * $entity = $cache->remember('entity_123', function() {
 *     return fetch_entity_from_db(123);
 * });
 */
final class CacheManager
{
    /**
     * @var string Cache group
     */
    private string $group;

    /**
     * @var int Default TTL in seconds
     */
    private int $default_ttl;

    /**
     * Constructor
     *
     * @param string $group Cache group (e.g., 'saga')
     * @param int $default_ttl Default TTL in seconds (default: 300 = 5 minutes)
     */
    public function __construct(string $group = 'saga', int $default_ttl = 300)
    {
        $this->group = $group;
        $this->default_ttl = $default_ttl;
    }

    /**
     * Get value from cache
     *
     * @param string $key Cache key
     * @param mixed $default Default value if not found
     * @return mixed Cached value or default
     *
     * @example
     * $entity = $cache->get('entity_123');
     */
    public function get(string $key, mixed $default = null): mixed
    {
        $value = wp_cache_get($key, $this->group);

        if ($value === false) {
            return $default;
        }

        return $value;
    }

    /**
     * Set value in cache
     *
     * @param string $key Cache key
     * @param mixed $value Value to cache
     * @param int|null $ttl TTL in seconds (null = use default)
     * @return bool Success
     *
     * @example
     * $cache->set('entity_123', $entity, 600);
     */
    public function set(string $key, mixed $value, ?int $ttl = null): bool
    {
        $ttl = $ttl ?? $this->default_ttl;

        return wp_cache_set($key, $value, $this->group, $ttl);
    }

    /**
     * Delete value from cache
     *
     * @param string $key Cache key
     * @return bool Success
     *
     * @example
     * $cache->delete('entity_123');
     */
    public function delete(string $key): bool
    {
        return wp_cache_delete($key, $this->group);
    }

    /**
     * Delete multiple keys from cache
     *
     * @param array $keys Array of cache keys
     * @return int Number of successfully deleted keys
     *
     * @example
     * $cache->deleteMultiple(['entity_123', 'entity_456']);
     */
    public function deleteMultiple(array $keys): int
    {
        $deleted = 0;

        foreach ($keys as $key) {
            if ($this->delete($key)) {
                $deleted++;
            }
        }

        return $deleted;
    }

    /**
     * Get value from cache or execute callback to generate it
     *
     * Remember pattern: Cache hit returns cached value,
     * cache miss executes callback and caches result.
     *
     * @param string $key Cache key
     * @param callable $callback Callback to generate value
     * @param int|null $ttl TTL in seconds (null = use default)
     * @return mixed Cached or generated value
     *
     * @example
     * $entity = $cache->remember('entity_123', function() use ($wpdb) {
     *     return $wpdb->get_row("SELECT * FROM entities WHERE id = 123");
     * });
     */
    public function remember(string $key, callable $callback, ?int $ttl = null): mixed
    {
        $value = $this->get($key);

        if ($value !== null) {
            return $value;
        }

        // Cache miss - execute callback
        $value = $callback();

        // Cache the result
        if ($value !== null) {
            $this->set($key, $value, $ttl);
        }

        return $value;
    }

    /**
     * Invalidate cache for multiple related keys
     *
     * Uses pattern matching to clear related cache entries.
     *
     * @param string $pattern Pattern to match (e.g., 'saga_1_*')
     * @return int Number of deleted keys
     *
     * @example
     * $cache->invalidatePattern('summary_saga_1_*');
     */
    public function invalidatePattern(string $pattern): int
    {
        // WordPress object cache doesn't support pattern deletion natively
        // This would require a custom implementation or cache backend that supports it
        // For now, return 0 and log a notice
        error_log(sprintf(
            '[SAGA][CACHE] Pattern invalidation not supported: %s',
            $pattern
        ));

        return 0;
    }

    /**
     * Clear all cache for this group
     *
     * @return bool Success
     *
     * @example
     * $cache->flush();
     */
    public function flush(): bool
    {
        // WordPress doesn't have a built-in group flush
        // This would require wp_cache_flush() which clears ALL cache
        error_log('[SAGA][CACHE] Group flush not implemented - use wp_cache_flush() to clear all');

        return false;
    }

    /**
     * Check if key exists in cache
     *
     * @param string $key Cache key
     * @return bool True if exists
     *
     * @example
     * if ($cache->has('entity_123')) { ... }
     */
    public function has(string $key): bool
    {
        return $this->get($key) !== null;
    }

    /**
     * Get multiple values from cache
     *
     * @param array $keys Array of cache keys
     * @return array Associative array [key => value]
     *
     * @example
     * $entities = $cache->getMultiple(['entity_123', 'entity_456']);
     */
    public function getMultiple(array $keys): array
    {
        $results = [];

        foreach ($keys as $key) {
            $value = $this->get($key);
            if ($value !== null) {
                $results[$key] = $value;
            }
        }

        return $results;
    }

    /**
     * Set multiple values in cache
     *
     * @param array $values Associative array [key => value]
     * @param int|null $ttl TTL in seconds (null = use default)
     * @return int Number of successfully cached values
     *
     * @example
     * $cache->setMultiple([
     *     'entity_123' => $entity1,
     *     'entity_456' => $entity2
     * ]);
     */
    public function setMultiple(array $values, ?int $ttl = null): int
    {
        $cached = 0;

        foreach ($values as $key => $value) {
            if ($this->set($key, $value, $ttl)) {
                $cached++;
            }
        }

        return $cached;
    }

    /**
     * Increment numeric value in cache
     *
     * @param string $key Cache key
     * @param int $offset Increment amount (default: 1)
     * @return int|false New value or false on failure
     *
     * @example
     * $cache->increment('saga_entity_count');
     */
    public function increment(string $key, int $offset = 1): int|false
    {
        return wp_cache_incr($key, $offset, $this->group);
    }

    /**
     * Decrement numeric value in cache
     *
     * @param string $key Cache key
     * @param int $offset Decrement amount (default: 1)
     * @return int|false New value or false on failure
     *
     * @example
     * $cache->decrement('saga_pending_count');
     */
    public function decrement(string $key, int $offset = 1): int|false
    {
        return wp_cache_decr($key, $offset, $this->group);
    }

    /**
     * Add value to cache only if key doesn't exist
     *
     * @param string $key Cache key
     * @param mixed $value Value to cache
     * @param int|null $ttl TTL in seconds (null = use default)
     * @return bool True if added, false if key already exists
     *
     * @example
     * $cache->add('entity_123', $entity); // Only if not cached
     */
    public function add(string $key, mixed $value, ?int $ttl = null): bool
    {
        $ttl = $ttl ?? $this->default_ttl;

        return wp_cache_add($key, $value, $this->group, $ttl);
    }

    /**
     * Replace value in cache only if key exists
     *
     * @param string $key Cache key
     * @param mixed $value New value
     * @param int|null $ttl TTL in seconds (null = use default)
     * @return bool True if replaced, false if key doesn't exist
     *
     * @example
     * $cache->replace('entity_123', $updated_entity);
     */
    public function replace(string $key, mixed $value, ?int $ttl = null): bool
    {
        $ttl = $ttl ?? $this->default_ttl;

        return wp_cache_replace($key, $value, $this->group, $ttl);
    }

    /**
     * Get cache group
     *
     * @return string Cache group
     */
    public function getGroup(): string
    {
        return $this->group;
    }

    /**
     * Get default TTL
     *
     * @return int TTL in seconds
     */
    public function getDefaultTTL(): int
    {
        return $this->default_ttl;
    }

    /**
     * Create namespaced key
     *
     * Useful for creating consistent cache keys across application.
     *
     * @param string $prefix Prefix (e.g., 'entity', 'summary')
     * @param string|int $identifier ID or unique identifier
     * @param string|null $suffix Optional suffix
     * @return string Namespaced key
     *
     * @example
     * $key = $cache->makeKey('entity', 123); // "entity_123"
     * $key = $cache->makeKey('summary', 456, 'stats'); // "summary_456_stats"
     */
    public function makeKey(string $prefix, string|int $identifier, ?string $suffix = null): string
    {
        $key = "{$prefix}_{$identifier}";

        if ($suffix !== null) {
            $key .= "_{$suffix}";
        }

        return $key;
    }
}
