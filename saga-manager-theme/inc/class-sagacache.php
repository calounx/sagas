<?php
declare(strict_types=1);

namespace SagaTheme;

/**
 * Caching Layer for Saga Manager Theme
 *
 * Implements WordPress object cache for entity and relationship data
 * Provides cache invalidation strategies for data consistency
 *
 * @package SagaTheme
 */
class SagaCache {

	private const CACHE_GROUP = 'saga_theme';
	private const DEFAULT_TTL = 300; // 5 minutes

	/**
	 * Get entity data from cache or database
	 *
	 * @param int $postId WordPress post ID
	 * @return object|null Entity object or null if not found
	 */
	public function getEntity( int $postId ): ?object {
		$key    = "entity_{$postId}";
		$cached = wp_cache_get( $key, self::CACHE_GROUP );

		if ( $cached !== false ) {
			return $cached;
		}

		return null;
	}

	/**
	 * Store entity in cache
	 *
	 * @param int    $postId WordPress post ID
	 * @param object $entity Entity object to cache
	 * @param int    $ttl Time to live in seconds
	 * @return bool True on success, false on failure
	 */
	public function setEntity( int $postId, object $entity, int $ttl = self::DEFAULT_TTL ): bool {
		$key = "entity_{$postId}";
		return wp_cache_set( $key, $entity, self::CACHE_GROUP, $ttl );
	}

	/**
	 * Get entity relationships from cache
	 *
	 * @param int    $entityId Entity ID
	 * @param string $direction Relationship direction: 'outgoing', 'incoming', 'both'
	 * @return array|null Array of relationships or null if not cached
	 */
	public function getRelationships( int $entityId, string $direction = 'both' ): ?array {
		$key    = "relationships_{$entityId}_{$direction}";
		$cached = wp_cache_get( $key, self::CACHE_GROUP );

		return $cached !== false ? $cached : null;
	}

	/**
	 * Store relationships in cache
	 *
	 * @param int    $entityId Entity ID
	 * @param array  $relationships Array of relationship objects
	 * @param string $direction Relationship direction
	 * @param int    $ttl Time to live in seconds
	 * @return bool True on success, false on failure
	 */
	public function setRelationships( int $entityId, array $relationships, string $direction = 'both', int $ttl = self::DEFAULT_TTL ): bool {
		$key = "relationships_{$entityId}_{$direction}";
		return wp_cache_set( $key, $relationships, self::CACHE_GROUP, $ttl );
	}

	/**
	 * Get attribute values from cache
	 *
	 * @param int $entityId Entity ID
	 * @return array|null Array of attribute values or null if not cached
	 */
	public function getAttributes( int $entityId ): ?array {
		$key    = "attributes_{$entityId}";
		$cached = wp_cache_get( $key, self::CACHE_GROUP );

		return $cached !== false ? $cached : null;
	}

	/**
	 * Store attribute values in cache
	 *
	 * @param int   $entityId Entity ID
	 * @param array $attributes Array of attribute values
	 * @param int   $ttl Time to live in seconds
	 * @return bool True on success, false on failure
	 */
	public function setAttributes( int $entityId, array $attributes, int $ttl = self::DEFAULT_TTL ): bool {
		$key = "attributes_{$entityId}";
		return wp_cache_set( $key, $attributes, self::CACHE_GROUP, $ttl );
	}

	/**
	 * Invalidate entity cache
	 *
	 * @param int $postId WordPress post ID
	 * @return bool True on success, false on failure
	 */
	public function invalidateEntity( int $postId ): bool {
		$key = "entity_{$postId}";
		return wp_cache_delete( $key, self::CACHE_GROUP );
	}

	/**
	 * Invalidate relationship cache for an entity
	 *
	 * @param int $entityId Entity ID
	 * @return bool True if all deletions succeeded
	 */
	public function invalidateRelationships( int $entityId ): bool {
		$success    = true;
		$directions = array( 'outgoing', 'incoming', 'both' );

		foreach ( $directions as $direction ) {
			$key = "relationships_{$entityId}_{$direction}";
			if ( ! wp_cache_delete( $key, self::CACHE_GROUP ) ) {
				$success = false;
			}
		}

		return $success;
	}

	/**
	 * Invalidate attribute cache for an entity
	 *
	 * @param int $entityId Entity ID
	 * @return bool True on success, false on failure
	 */
	public function invalidateAttributes( int $entityId ): bool {
		$key = "attributes_{$entityId}";
		return wp_cache_delete( $key, self::CACHE_GROUP );
	}

	/**
	 * Invalidate all entity-related caches
	 *
	 * @param int      $entityId Entity ID
	 * @param int|null $postId Optional WordPress post ID
	 * @return bool True if all deletions succeeded
	 */
	public function invalidateAll( int $entityId, ?int $postId = null ): bool {
		$success = true;

		if ( $postId !== null ) {
			$success = $this->invalidateEntity( $postId ) && $success;
		}

		$success = $this->invalidateRelationships( $entityId ) && $success;
		$success = $this->invalidateAttributes( $entityId ) && $success;

		return $success;
	}

	/**
	 * Flush all saga theme caches
	 *
	 * Use sparingly - only on major data changes
	 *
	 * @return bool True on success
	 */
	public function flushAll(): bool {
		// WordPress doesn't provide group-level flush, so we rely on cache expiration
		// For persistent cache backends (Redis, Memcached), consider implementing group flush
		return wp_cache_flush();
	}
}
