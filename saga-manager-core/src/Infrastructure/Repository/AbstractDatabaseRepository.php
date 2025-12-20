<?php
declare(strict_types=1);

namespace SagaManagerCore\Infrastructure\Repository;

use SagaManagerCore\Infrastructure\Database\Port\DatabaseConnectionInterface;
use SagaManagerCore\Infrastructure\Database\Port\QueryBuilderInterface;
use SagaManagerCore\Infrastructure\Database\Port\TransactionManagerInterface;

/**
 * Abstract Database Repository
 *
 * Base class for repositories using the database abstraction layer.
 * Provides common functionality and enforces consistent patterns.
 *
 * @package SagaManagerCore\Infrastructure\Repository
 */
abstract class AbstractDatabaseRepository
{
    protected DatabaseConnectionInterface $connection;
    protected string $cacheGroup = 'saga_core';
    protected int $cacheTtl = 300; // 5 minutes

    public function __construct(DatabaseConnectionInterface $connection)
    {
        $this->connection = $connection;
    }

    /**
     * Get the table name (without prefixes)
     */
    abstract protected function getTableName(): string;

    /**
     * Get a fresh query builder for this repository's table
     */
    protected function query(): QueryBuilderInterface
    {
        return $this->connection->query()->from($this->getTableName());
    }

    /**
     * Get the transaction manager
     */
    protected function transaction(): TransactionManagerInterface
    {
        return $this->connection->transaction();
    }

    /**
     * Run a callback within a transaction
     *
     * @template T
     * @param callable(TransactionManagerInterface): T $callback
     * @return T
     */
    protected function runInTransaction(callable $callback): mixed
    {
        return $this->transaction()->run($callback);
    }

    /**
     * Get a value from cache
     *
     * @param string $key Cache key
     * @return mixed Cached value or false if not found
     */
    protected function getFromCache(string $key): mixed
    {
        if (function_exists('wp_cache_get')) {
            return wp_cache_get($key, $this->cacheGroup);
        }
        return false;
    }

    /**
     * Store a value in cache
     *
     * @param string $key Cache key
     * @param mixed $value Value to cache
     * @param int|null $ttl Time to live in seconds (null = use default)
     */
    protected function setInCache(string $key, mixed $value, ?int $ttl = null): void
    {
        if (function_exists('wp_cache_set')) {
            wp_cache_set($key, $value, $this->cacheGroup, $ttl ?? $this->cacheTtl);
        }
    }

    /**
     * Delete a value from cache
     *
     * @param string $key Cache key
     */
    protected function deleteFromCache(string $key): void
    {
        if (function_exists('wp_cache_delete')) {
            wp_cache_delete($key, $this->cacheGroup);
        }
    }

    /**
     * Generate a cache key for an entity
     *
     * @param string $type Entity type identifier
     * @param int|string $id Entity ID
     * @return string Cache key
     */
    protected function getCacheKey(string $type, int|string $id): string
    {
        return "{$this->cacheGroup}_{$type}_{$id}";
    }
}
