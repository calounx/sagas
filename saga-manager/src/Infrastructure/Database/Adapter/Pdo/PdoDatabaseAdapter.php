<?php

declare(strict_types=1);

namespace SagaManager\Infrastructure\Database\Adapter\Pdo;

use SagaManager\Infrastructure\Database\Contract\DatabaseInterface;
use SagaManager\Infrastructure\Database\Contract\QueryBuilderInterface;
use SagaManager\Infrastructure\Database\Contract\TransactionInterface;
use SagaManager\Infrastructure\Database\Contract\SchemaManagerInterface;
use SagaManager\Infrastructure\Database\Contract\ResultSetInterface;
use SagaManager\Infrastructure\Database\Config\DatabaseConfig;
use SagaManager\Domain\Exception\DatabaseException;

/**
 * PDO Database Adapter
 *
 * Full-featured database adapter using PDO for MySQL/MariaDB, PostgreSQL, and SQLite.
 *
 * @example MySQL/MariaDB:
 *   $config = DatabaseConfig::pdoMysql(
 *       host: 'localhost',
 *       database: 'saga_manager',
 *       username: 'root',
 *       password: 'secret',
 *       tablePrefix: 'wp_saga_'
 *   );
 *   $db = new PdoDatabaseAdapter($config);
 *
 * @example PostgreSQL:
 *   $config = DatabaseConfig::pdoPostgres(
 *       host: 'localhost',
 *       database: 'saga_manager',
 *       username: 'postgres',
 *       password: 'secret'
 *   );
 *   $db = new PdoDatabaseAdapter($config);
 *
 * @example SQLite:
 *   $config = DatabaseConfig::pdoSqlite('/path/to/database.sqlite');
 *   $db = new PdoDatabaseAdapter($config);
 */
final class PdoDatabaseAdapter implements DatabaseInterface
{
    private readonly PdoConnection $connection;
    private ?PdoQueryBuilder $queryBuilder = null;
    private ?PdoTransactionManager $transactionManager = null;
    private ?PdoSchemaManager $schemaManager = null;
    private ?string $lastError = null;
    private int $lastInsertId = 0;

    public function __construct(
        private readonly DatabaseConfig $config,
    ) {
        $this->connection = new PdoConnection($config);
    }

    public function query(): QueryBuilderInterface
    {
        if ($this->queryBuilder === null) {
            $this->queryBuilder = new PdoQueryBuilder(
                $this->connection,
                $this->config->tablePrefix
            );
        }
        return $this->queryBuilder->reset();
    }

    public function transaction(): TransactionInterface
    {
        if ($this->transactionManager === null) {
            $this->transactionManager = new PdoTransactionManager(
                $this->connection,
                $this
            );
        }
        return $this->transactionManager;
    }

    public function schema(): SchemaManagerInterface
    {
        if ($this->schemaManager === null) {
            $this->schemaManager = new PdoSchemaManager(
                $this->connection,
                $this->config->tablePrefix
            );
        }
        return $this->schemaManager;
    }

    public function raw(string $sql, array $params = []): ResultSetInterface
    {
        try {
            $this->lastError = null;
            $stmt = $this->connection->execute($sql, $params);
            return PdoResultSet::fromStatement($stmt);
        } catch (DatabaseException $e) {
            $this->lastError = $e->getMessage();
            throw $e;
        }
    }

    public function insert(string $table, array $data): int
    {
        if (empty($data)) {
            throw new DatabaseException('Cannot insert empty data');
        }

        $tableName = $this->getTableName($table);
        $columns = array_keys($data);
        $placeholders = array_fill(0, count($columns), '?');

        $columnList = implode(', ', array_map(
            fn($col) => $this->connection->quoteIdentifier($col),
            $columns
        ));

        $sql = "INSERT INTO {$tableName} ({$columnList}) VALUES (" .
               implode(', ', $placeholders) . ")";

        try {
            $this->lastError = null;
            $this->connection->execute($sql, array_values($data));
            $this->lastInsertId = (int) $this->connection->getNativeConnection()->lastInsertId();
            return $this->lastInsertId;
        } catch (DatabaseException $e) {
            $this->lastError = $e->getMessage();
            throw $e;
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

        $setParts = [];
        $params = [];
        foreach ($data as $column => $value) {
            $setParts[] = $this->connection->quoteIdentifier($column) . ' = ?';
            $params[] = $value;
        }

        $whereParts = [];
        foreach ($where as $column => $value) {
            $whereParts[] = $this->connection->quoteIdentifier($column) . ' = ?';
            $params[] = $value;
        }

        $sql = "UPDATE {$tableName} SET " . implode(', ', $setParts) .
               " WHERE " . implode(' AND ', $whereParts);

        try {
            $this->lastError = null;
            $stmt = $this->connection->execute($sql, $params);
            return $stmt->rowCount();
        } catch (DatabaseException $e) {
            $this->lastError = $e->getMessage();
            throw $e;
        }
    }

    public function delete(string $table, array $where): int
    {
        if (empty($where)) {
            throw new DatabaseException('Cannot delete without WHERE clause');
        }

        $tableName = $this->getTableName($table);

        $whereParts = [];
        $params = [];
        foreach ($where as $column => $value) {
            $whereParts[] = $this->connection->quoteIdentifier($column) . ' = ?';
            $params[] = $value;
        }

        $sql = "DELETE FROM {$tableName} WHERE " . implode(' AND ', $whereParts);

        try {
            $this->lastError = null;
            $stmt = $this->connection->execute($sql, $params);
            return $stmt->rowCount();
        } catch (DatabaseException $e) {
            $this->lastError = $e->getMessage();
            throw $e;
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
        $result = $this->select($table, [$primaryKey => $id], [], [], 1);
        return $result->first();
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
        return $this->config->tablePrefix . $table;
    }

    public function getPrefix(): string
    {
        return $this->config->tablePrefix;
    }

    public function getLastError(): ?string
    {
        return $this->lastError;
    }

    public function getLastInsertId(): int
    {
        return $this->lastInsertId;
    }

    /**
     * Get the underlying PDO connection
     *
     * @return PdoConnection
     */
    public function getConnection(): PdoConnection
    {
        return $this->connection;
    }

    /**
     * Get connection statistics
     *
     * @return array{queries_executed: int, total_time: float, connected_at: \DateTimeImmutable|null}
     */
    public function getStats(): array
    {
        return $this->connection->getStats();
    }

    /**
     * Get the database driver name
     *
     * @return string (mysql, pgsql, sqlite)
     */
    public function getDriverName(): string
    {
        return $this->connection->getDriverName();
    }
}
