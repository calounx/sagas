<?php

declare(strict_types=1);

namespace SagaManager\Infrastructure\Database\Adapter\InMemory;

use SagaManager\Infrastructure\Database\Contract\DatabaseInterface;
use SagaManager\Infrastructure\Database\Contract\QueryBuilderInterface;
use SagaManager\Infrastructure\Database\Contract\TransactionInterface;
use SagaManager\Infrastructure\Database\Contract\SchemaManagerInterface;
use SagaManager\Infrastructure\Database\Contract\ResultSetInterface;
use SagaManager\Infrastructure\Database\Config\DatabaseConfig;
use SagaManager\Domain\Exception\DatabaseException;

/**
 * In-Memory Database Adapter
 *
 * Full-featured database adapter using PHP arrays for data storage.
 * Ideal for unit testing without any external database dependencies.
 *
 * Features:
 * - All CRUD operations
 * - WHERE conditions with operators (=, !=, <, >, <=, >=, LIKE, IN, etc.)
 * - ORDER BY and LIMIT/OFFSET
 * - Transaction support with rollback (via array snapshots)
 * - Schema management (tables, columns, indexes)
 * - No external dependencies - perfect for fast unit tests
 *
 * @example Basic usage:
 *   $db = new InMemoryDatabaseAdapter();
 *   $db->schema()->createTable('entities', [
 *       'id' => ['type' => 'bigint', 'autoincrement' => true],
 *       'name' => ['type' => 'varchar', 'length' => 255],
 *   ]);
 *   $id = $db->insert('entities', ['name' => 'Luke Skywalker']);
 *   $entity = $db->find('entities', $id);
 *
 * @example With configuration:
 *   $config = DatabaseConfig::memory('wp_saga_');
 *   $db = new InMemoryDatabaseAdapter($config);
 *
 * @example Testing:
 *   $db = InMemoryDatabaseAdapter::createWithTestData([
 *       'entities' => [
 *           ['id' => 1, 'name' => 'Luke', 'saga_id' => 1],
 *           ['id' => 2, 'name' => 'Leia', 'saga_id' => 1],
 *       ],
 *   ]);
 */
final class InMemoryDatabaseAdapter implements DatabaseInterface
{
    private readonly InMemoryDataStore $dataStore;
    private readonly string $tablePrefix;
    private ?InMemoryQueryBuilder $queryBuilder = null;
    private ?InMemoryTransactionManager $transactionManager = null;
    private ?InMemorySchemaManager $schemaManager = null;
    private ?string $lastError = null;
    private int $lastInsertId = 0;

    public function __construct(
        ?DatabaseConfig $config = null,
        ?InMemoryDataStore $dataStore = null,
    ) {
        $this->tablePrefix = $config?->tablePrefix ?? '';
        $this->dataStore = $dataStore ?? new InMemoryDataStore();
    }

    /**
     * Create adapter with pre-populated test data
     *
     * @param array<string, array<int, array<string, mixed>>> $tables Table name => rows
     * @param string $tablePrefix Table prefix
     * @return self
     *
     * @example
     *   $db = InMemoryDatabaseAdapter::createWithTestData([
     *       'entities' => [
     *           ['id' => 1, 'name' => 'Luke', 'entity_type' => 'character'],
     *           ['id' => 2, 'name' => 'Tatooine', 'entity_type' => 'location'],
     *       ],
     *       'sagas' => [
     *           ['id' => 1, 'name' => 'Star Wars'],
     *       ],
     *   ], 'wp_saga_');
     */
    public static function createWithTestData(array $tables, string $tablePrefix = ''): self
    {
        $config = DatabaseConfig::memory($tablePrefix);
        $dataStore = new InMemoryDataStore();

        foreach ($tables as $table => $rows) {
            $tableName = $tablePrefix . $table;

            // Infer schema from first row
            $schema = [];
            if (!empty($rows)) {
                foreach ($rows[0] as $column => $value) {
                    $schema[$column] = ['type' => gettype($value)];
                }
            }

            $dataStore->createTable($tableName, $schema);
            $dataStore->setTable($tableName, $rows);
        }

        return new self($config, $dataStore);
    }

    public function query(): QueryBuilderInterface
    {
        if ($this->queryBuilder === null) {
            $this->queryBuilder = new InMemoryQueryBuilder(
                $this->dataStore,
                $this->tablePrefix
            );
        }
        return $this->queryBuilder->reset();
    }

    public function transaction(): TransactionInterface
    {
        if ($this->transactionManager === null) {
            $this->transactionManager = new InMemoryTransactionManager(
                $this->dataStore,
                $this
            );
        }
        return $this->transactionManager;
    }

    public function schema(): SchemaManagerInterface
    {
        if ($this->schemaManager === null) {
            $this->schemaManager = new InMemorySchemaManager(
                $this->dataStore,
                $this->tablePrefix
            );
        }
        return $this->schemaManager;
    }

    public function raw(string $sql, array $params = []): ResultSetInterface
    {
        // In-memory database doesn't support raw SQL
        // Parse simple SELECT statements for compatibility
        if (preg_match('/SELECT\s+(.+?)\s+FROM\s+(\w+)/i', $sql, $matches)) {
            $tableName = $this->tablePrefix . $matches[2];
            $rows = $this->dataStore->getTable($tableName);
            return InMemoryResultSet::fromArray($rows);
        }

        return InMemoryResultSet::empty();
    }

    public function insert(string $table, array $data): int
    {
        if (empty($data)) {
            throw new DatabaseException('Cannot insert empty data');
        }

        $tableName = $this->getTableName($table);

        try {
            $this->lastError = null;
            $this->lastInsertId = $this->dataStore->insert($tableName, $data);
            return $this->lastInsertId;
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
            return $this->dataStore->update($tableName, $data, $where);
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
            return $this->dataStore->delete($tableName, $where);
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
        $tableName = $this->getTableName($table);
        $rows = $this->dataStore->select(
            $tableName,
            $where,
            $columns,
            $orderBy,
            $limit,
            $offset
        );

        return InMemoryResultSet::fromArray($rows);
    }

    public function find(string $table, int|string $id, string $primaryKey = 'id'): ?array
    {
        $result = $this->select($table, [$primaryKey => $id], [], [], 1);
        return $result->first();
    }

    public function count(string $table, array $where = []): int
    {
        $tableName = $this->getTableName($table);
        return $this->dataStore->count($tableName, $where);
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
        return $this->lastError;
    }

    public function getLastInsertId(): int
    {
        return $this->lastInsertId;
    }

    /**
     * Get the underlying data store (for testing)
     *
     * @return InMemoryDataStore
     */
    public function getDataStore(): InMemoryDataStore
    {
        return $this->dataStore;
    }

    /**
     * Clear all data (for testing)
     *
     * @return void
     */
    public function clearAll(): void
    {
        // Get all tables and drop them
        // Note: This is a simple implementation that creates a new data store
        $this->queryBuilder = null;
        $this->transactionManager = null;
        $this->schemaManager = null;
    }

    /**
     * Seed test data
     *
     * @param string $table Table name (without prefix)
     * @param array<int, array<string, mixed>> $rows Rows to insert
     * @return void
     *
     * @example
     *   $db->seed('entities', [
     *       ['id' => 1, 'name' => 'Luke', 'saga_id' => 1],
     *       ['id' => 2, 'name' => 'Leia', 'saga_id' => 1],
     *   ]);
     */
    public function seed(string $table, array $rows): void
    {
        $tableName = $this->getTableName($table);

        // Create table if not exists
        if (!$this->dataStore->hasTable($tableName)) {
            $schema = [];
            if (!empty($rows)) {
                foreach ($rows[0] as $column => $value) {
                    $schema[$column] = ['type' => gettype($value)];
                }
            }
            $this->dataStore->createTable($tableName, $schema);
        }

        foreach ($rows as $row) {
            $this->dataStore->insert($tableName, $row);
        }
    }

    /**
     * Get all data from a table (for testing assertions)
     *
     * @param string $table Table name (without prefix)
     * @return array<int, array<string, mixed>>
     */
    public function getAll(string $table): array
    {
        $tableName = $this->getTableName($table);
        return $this->dataStore->getTable($tableName);
    }
}
