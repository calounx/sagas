<?php

declare(strict_types=1);

namespace SagaManager\Infrastructure\Database\Adapter\WordPress;

use SagaManager\Infrastructure\Database\Contract\DatabaseInterface;
use SagaManager\Infrastructure\Database\Contract\QueryBuilderInterface;
use SagaManager\Infrastructure\Database\Contract\TransactionInterface;
use SagaManager\Infrastructure\Database\Contract\SchemaManagerInterface;
use SagaManager\Infrastructure\Database\Contract\ResultSetInterface;
use SagaManager\Infrastructure\Database\Config\DatabaseConfig;
use SagaManager\Domain\Exception\DatabaseException;

/**
 * WordPress Database Adapter
 *
 * Database adapter using WordPress wpdb for all operations.
 * Fully compatible with WordPress multisite and custom table prefixes.
 *
 * @example Basic usage:
 *   $db = new WordPressDatabaseAdapter();
 *   $entity = $db->find('entities', 42);
 *
 * @example With custom saga prefix:
 *   $config = DatabaseConfig::wordpress('saga_');
 *   $db = new WordPressDatabaseAdapter($config);
 *   // Tables will be: wp_saga_entities, wp_saga_sagas, etc.
 *
 * @example Using query builder:
 *   $result = $db->query()
 *       ->select('id', 'canonical_name')
 *       ->from('entities')
 *       ->where('saga_id', '=', 1)
 *       ->where('importance_score', '>=', 80)
 *       ->orderBy('importance_score', 'DESC')
 *       ->limit(10)
 *       ->execute();
 */
final class WordPressDatabaseAdapter implements DatabaseInterface
{
    private readonly \wpdb $wpdb;
    private readonly string $tablePrefix;
    private ?WordPressQueryBuilder $queryBuilder = null;
    private ?WordPressTransactionManager $transactionManager = null;
    private ?WordPressSchemaManager $schemaManager = null;
    private ?string $lastError = null;
    private int $lastInsertId = 0;

    public function __construct(
        ?DatabaseConfig $config = null,
    ) {
        global $wpdb;
        $this->wpdb = $wpdb;

        // Build full table prefix: WordPress prefix + optional saga prefix
        $sagaPrefix = $config?->tablePrefix ?? '';
        $this->tablePrefix = $this->wpdb->prefix . $sagaPrefix;
    }

    public function query(): QueryBuilderInterface
    {
        if ($this->queryBuilder === null) {
            $this->queryBuilder = new WordPressQueryBuilder(
                $this->wpdb,
                $this->tablePrefix
            );
        }
        return $this->queryBuilder->reset();
    }

    public function transaction(): TransactionInterface
    {
        if ($this->transactionManager === null) {
            $this->transactionManager = new WordPressTransactionManager(
                $this->wpdb,
                $this
            );
        }
        return $this->transactionManager;
    }

    public function schema(): SchemaManagerInterface
    {
        if ($this->schemaManager === null) {
            $this->schemaManager = new WordPressSchemaManager(
                $this->wpdb,
                $this->tablePrefix
            );
        }
        return $this->schemaManager;
    }

    public function raw(string $sql, array $params = []): ResultSetInterface
    {
        try {
            $this->lastError = null;

            if (!empty($params)) {
                $sql = $this->wpdb->prepare($sql, $params);
            }

            $results = $this->wpdb->get_results($sql, ARRAY_A);

            if ($this->wpdb->last_error) {
                $this->lastError = $this->wpdb->last_error;
                throw new DatabaseException('Query failed: ' . $this->wpdb->last_error);
            }

            return WordPressResultSet::fromArray($results ?: []);

        } catch (DatabaseException $e) {
            throw $e;
        } catch (\Throwable $e) {
            $this->lastError = $e->getMessage();
            throw new DatabaseException('Query failed: ' . $e->getMessage(), 0, $e);
        }
    }

    public function insert(string $table, array $data): int
    {
        if (empty($data)) {
            throw new DatabaseException('Cannot insert empty data');
        }

        $tableName = $this->getTableName($table);

        try {
            $this->lastError = null;

            $result = $this->wpdb->insert($tableName, $data);

            if ($result === false) {
                $this->lastError = $this->wpdb->last_error;
                throw new DatabaseException('Insert failed: ' . $this->wpdb->last_error);
            }

            $this->lastInsertId = (int) $this->wpdb->insert_id;
            return $this->lastInsertId;

        } catch (DatabaseException $e) {
            throw $e;
        } catch (\Throwable $e) {
            $this->lastError = $e->getMessage();
            throw new DatabaseException('Insert failed: ' . $e->getMessage(), 0, $e);
        }
    }

    public function update(string $table, array $data, array $where): int
    {
        if (empty($data)) {
            throw new DatabaseException('Cannot update with empty data');
        }

        if (empty($where)) {
            throw new DatabaseException('Cannot update without WHERE clause');
        }

        $tableName = $this->getTableName($table);

        try {
            $this->lastError = null;

            $result = $this->wpdb->update($tableName, $data, $where);

            if ($result === false) {
                $this->lastError = $this->wpdb->last_error;
                throw new DatabaseException('Update failed: ' . $this->wpdb->last_error);
            }

            return (int) $result;

        } catch (DatabaseException $e) {
            throw $e;
        } catch (\Throwable $e) {
            $this->lastError = $e->getMessage();
            throw new DatabaseException('Update failed: ' . $e->getMessage(), 0, $e);
        }
    }

    public function delete(string $table, array $where): int
    {
        if (empty($where)) {
            throw new DatabaseException('Cannot delete without WHERE clause');
        }

        $tableName = $this->getTableName($table);

        try {
            $this->lastError = null;

            $result = $this->wpdb->delete($tableName, $where);

            if ($result === false) {
                $this->lastError = $this->wpdb->last_error;
                throw new DatabaseException('Delete failed: ' . $this->wpdb->last_error);
            }

            return (int) $result;

        } catch (DatabaseException $e) {
            throw $e;
        } catch (\Throwable $e) {
            $this->lastError = $e->getMessage();
            throw new DatabaseException('Delete failed: ' . $e->getMessage(), 0, $e);
        }
    }

    public function select(
        string $table,
        array $where = [],
        array $columns = [],
        array $orderBy = [],
        ?int $limit = null,
        int $offset = 0
    ): ResultSetInterface {
        $qb = $this->query()
            ->select(...($columns ?: ['*']))
            ->from($table);

        foreach ($where as $column => $value) {
            if (is_array($value)) {
                $qb->whereIn($column, $value);
            } elseif ($value === null) {
                $qb->whereNull($column);
            } else {
                $qb->where($column, '=', $value);
            }
        }

        foreach ($orderBy as $column => $direction) {
            $qb->orderBy($column, $direction);
        }

        if ($limit !== null) {
            $qb->limit($limit);
        }

        if ($offset > 0) {
            $qb->offset($offset);
        }

        return $qb->execute();
    }

    public function find(string $table, int|string $id, string $primaryKey = 'id'): ?array
    {
        $tableName = $this->getTableName($table);

        $sql = $this->wpdb->prepare(
            "SELECT * FROM {$tableName} WHERE {$primaryKey} = %s LIMIT 1",
            $id
        );

        $row = $this->wpdb->get_row($sql, ARRAY_A);

        return $row ?: null;
    }

    public function count(string $table, array $where = []): int
    {
        $qb = $this->query()
            ->select('COUNT(*) AS count')
            ->from($table);

        foreach ($where as $column => $value) {
            if (is_array($value)) {
                $qb->whereIn($column, $value);
            } elseif ($value === null) {
                $qb->whereNull($column);
            } else {
                $qb->where($column, '=', $value);
            }
        }

        $row = $qb->first();
        return (int) ($row['count'] ?? 0);
    }

    public function exists(string $table, array $where): bool
    {
        return $this->count($table, $where) > 0;
    }

    public function getTableName(string $table): string
    {
        return $this->tablePrefix . $table;
    }

    public function getPrefix(): string
    {
        return $this->tablePrefix;
    }

    public function getLastError(): ?string
    {
        return $this->lastError ?: ($this->wpdb->last_error ?: null);
    }

    public function getLastInsertId(): int
    {
        return $this->lastInsertId ?: (int) $this->wpdb->insert_id;
    }

    /**
     * Get the underlying wpdb instance
     *
     * @return \wpdb
     */
    public function getWpdb(): \wpdb
    {
        return $this->wpdb;
    }

    /**
     * Get the WordPress table prefix (without saga prefix)
     *
     * @return string
     */
    public function getWordPressPrefix(): string
    {
        return $this->wpdb->prefix;
    }

    /**
     * Get the base prefix for multisite
     *
     * @return string
     */
    public function getBasePrefix(): string
    {
        return $this->wpdb->base_prefix;
    }

    /**
     * Check if this is a multisite installation
     *
     * @return bool
     */
    public function isMultisite(): bool
    {
        return is_multisite();
    }

    /**
     * Execute a prepared statement with WordPress-style formatting
     *
     * This is a convenience method for complex queries that don't fit
     * the query builder pattern.
     *
     * @param string $sql SQL with %s, %d, %f placeholders
     * @param mixed ...$args Arguments to substitute
     * @return ResultSetInterface
     *
     * @example
     *   $result = $db->prepared(
     *       "SELECT * FROM {$db->getTableName('entities')} WHERE saga_id = %d AND entity_type = %s",
     *       1,
     *       'character'
     *   );
     */
    public function prepared(string $sql, mixed ...$args): ResultSetInterface
    {
        $query = empty($args) ? $sql : $this->wpdb->prepare($sql, $args);
        $results = $this->wpdb->get_results($query, ARRAY_A);

        if ($this->wpdb->last_error) {
            $this->lastError = $this->wpdb->last_error;
            throw new DatabaseException('Query failed: ' . $this->wpdb->last_error);
        }

        return WordPressResultSet::fromArray($results ?: []);
    }
}
