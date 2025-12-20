<?php

declare(strict_types=1);

namespace SagaManager\Infrastructure\Database;

use SagaManager\Infrastructure\Database\Contract\DatabaseInterface;
use SagaManager\Domain\Exception\DatabaseException;

/**
 * Abstract Database Repository
 *
 * Base class for all repositories using the database adapter layer.
 * Replaces WordPressTablePrefixAware with a database-agnostic approach.
 *
 * @example
 *   class MariaDBEntityRepository extends AbstractDatabaseRepository
 *   {
 *       protected string $table = 'entities';
 *
 *       public function __construct(DatabaseInterface $database)
 *       {
 *           parent::__construct($database);
 *       }
 *
 *       public function findById(EntityId $id): SagaEntity
 *       {
 *           $row = $this->db->find($this->table, $id->value());
 *           if (!$row) {
 *               throw new EntityNotFoundException();
 *           }
 *           return $this->hydrate($row);
 *       }
 *   }
 */
abstract class AbstractDatabaseRepository
{
    /**
     * The table name (without prefix)
     */
    protected string $table = '';

    /**
     * The primary key column name
     */
    protected string $primaryKey = 'id';

    /**
     * Cache group for object caching
     */
    protected string $cacheGroup = 'saga';

    /**
     * Cache TTL in seconds
     */
    protected int $cacheTtl = 300;

    public function __construct(
        protected readonly DatabaseInterface $db,
    ) {}

    /**
     * Get the full table name with prefix
     *
     * @return string
     */
    protected function getTableName(): string
    {
        return $this->db->getTableName($this->table);
    }

    /**
     * Find a record by ID
     *
     * @param int|string $id
     * @return array<string, mixed>|null
     */
    protected function findRow(int|string $id): ?array
    {
        return $this->db->find($this->table, $id, $this->primaryKey);
    }

    /**
     * Find records matching criteria
     *
     * @param array<string, mixed> $where
     * @param array<string, string> $orderBy
     * @param int|null $limit
     * @param int $offset
     * @return array<int, array<string, mixed>>
     */
    protected function findRows(
        array $where = [],
        array $orderBy = [],
        ?int $limit = null,
        int $offset = 0
    ): array {
        return $this->db->select($this->table, $where, [], $orderBy, $limit, $offset)->toArray();
    }

    /**
     * Insert a new record
     *
     * @param array<string, mixed> $data
     * @return int The inserted ID
     * @throws DatabaseException
     */
    protected function insertRow(array $data): int
    {
        return $this->db->insert($this->table, $data);
    }

    /**
     * Update records
     *
     * @param array<string, mixed> $data
     * @param array<string, mixed> $where
     * @return int Number of affected rows
     * @throws DatabaseException
     */
    protected function updateRow(array $data, array $where): int
    {
        return $this->db->update($this->table, $data, $where);
    }

    /**
     * Delete records
     *
     * @param array<string, mixed> $where
     * @return int Number of deleted rows
     * @throws DatabaseException
     */
    protected function deleteRow(array $where): int
    {
        return $this->db->delete($this->table, $where);
    }

    /**
     * Count records
     *
     * @param array<string, mixed> $where
     * @return int
     */
    protected function countRows(array $where = []): int
    {
        return $this->db->count($this->table, $where);
    }

    /**
     * Check if a record exists
     *
     * @param array<string, mixed> $where
     * @return bool
     */
    protected function existsRow(array $where): bool
    {
        return $this->db->exists($this->table, $where);
    }

    /**
     * Execute within a transaction
     *
     * @template T
     * @param callable(DatabaseInterface): T $callback
     * @return T
     * @throws \Throwable
     */
    protected function transaction(callable $callback): mixed
    {
        return $this->db->transaction()->run($callback);
    }

    /**
     * Get from cache or fetch and store
     *
     * @template T
     * @param string $key Cache key
     * @param callable(): T $callback Fetch callback
     * @return T
     */
    protected function cached(string $key, callable $callback): mixed
    {
        // Check if wp_cache is available
        if (function_exists('wp_cache_get')) {
            $cached = wp_cache_get($key, $this->cacheGroup);
            if ($cached !== false) {
                return $cached;
            }
        }

        $result = $callback();

        if (function_exists('wp_cache_set')) {
            wp_cache_set($key, $result, $this->cacheGroup, $this->cacheTtl);
        }

        return $result;
    }

    /**
     * Invalidate cache
     *
     * @param string $key Cache key
     */
    protected function invalidateCache(string $key): void
    {
        if (function_exists('wp_cache_delete')) {
            wp_cache_delete($key, $this->cacheGroup);
        }
    }

    /**
     * Build cache key for an entity
     *
     * @param int|string $id
     * @return string
     */
    protected function buildCacheKey(int|string $id): string
    {
        return "{$this->cacheGroup}_{$this->table}_{$id}";
    }

    /**
     * Use query builder for complex queries
     *
     * @return \SagaManager\Infrastructure\Database\Contract\QueryBuilderInterface
     */
    protected function query(): \SagaManager\Infrastructure\Database\Contract\QueryBuilderInterface
    {
        return $this->db->query()->from($this->table);
    }
}
