<?php
declare(strict_types=1);

namespace SagaManagerCore\Infrastructure\Database\Adapter\InMemory;

use SagaManagerCore\Infrastructure\Database\Port\DatabaseConnectionInterface;
use SagaManagerCore\Infrastructure\Database\Port\QueryBuilderInterface;
use SagaManagerCore\Infrastructure\Database\Port\TransactionManagerInterface;
use SagaManagerCore\Infrastructure\Database\Port\SchemaManagerInterface;
use SagaManagerCore\Infrastructure\Database\Port\ResultSetInterface;
use SagaManagerCore\Infrastructure\Database\ResultSet;
use SagaManagerCore\Infrastructure\Database\Exception\QueryException;

/**
 * In-Memory Database Connection for Testing
 *
 * Provides a fully functional in-memory database adapter that requires
 * no external database. Perfect for unit tests and integration tests.
 *
 * Features:
 * - Full CRUD operations on in-memory arrays
 * - Transaction simulation with rollback support
 * - Auto-increment ID generation
 * - Basic query matching (WHERE, ORDER BY, LIMIT)
 *
 * @package SagaManagerCore\Infrastructure\Database\Adapter\InMemory
 */
class InMemoryConnection implements DatabaseConnectionInterface
{
    private const DRIVER_NAME = 'memory';
    private const SAGA_PREFIX = 'saga_';

    private bool $connected = false;
    private string $tablePrefix;

    /**
     * In-memory data storage
     * Structure: [table_name => [id => row_data, ...], ...]
     *
     * @var array<string, array<int, array<string, mixed>>>
     */
    private array $tables = [];

    /**
     * Auto-increment counters per table
     *
     * @var array<string, int>
     */
    private array $autoIncrements = [];

    /**
     * Last insert ID
     */
    private int $lastInsertId = 0;

    /**
     * Last affected rows count
     */
    private int $affectedRows = 0;

    /**
     * Query logging
     */
    private bool $queryLogEnabled = false;
    /** @var array<array{query: string, bindings: array<mixed>, time: float}> */
    private array $queryLog = [];

    /**
     * Transaction state
     *
     * @var array<array<string, array<int, array<string, mixed>>>>
     */
    private array $transactionSnapshots = [];

    private ?InMemoryTransactionManager $transactionManager = null;
    private ?InMemorySchemaManager $schemaManager = null;

    public function __construct(string $tablePrefix = 'test_')
    {
        $this->tablePrefix = $tablePrefix;
    }

    public function connect(): void
    {
        $this->connected = true;
    }

    public function disconnect(): void
    {
        $this->connected = false;
        $this->tables = [];
        $this->autoIncrements = [];
        $this->transactionSnapshots = [];
        $this->transactionManager = null;
        $this->schemaManager = null;
    }

    public function isConnected(): bool
    {
        return $this->connected;
    }

    public function ping(): bool
    {
        return $this->connected;
    }

    public function query(): QueryBuilderInterface
    {
        return new InMemoryQueryBuilder($this);
    }

    public function transaction(): TransactionManagerInterface
    {
        if ($this->transactionManager === null) {
            $this->transactionManager = new InMemoryTransactionManager($this);
        }
        return $this->transactionManager;
    }

    public function schema(): SchemaManagerInterface
    {
        if ($this->schemaManager === null) {
            $this->schemaManager = new InMemorySchemaManager($this);
        }
        return $this->schemaManager;
    }

    public function getDriverName(): string
    {
        return self::DRIVER_NAME;
    }

    public function getDatabaseName(): string
    {
        return 'in_memory';
    }

    public function getTablePrefix(): string
    {
        return $this->tablePrefix;
    }

    public function getSagaTablePrefix(): string
    {
        return $this->tablePrefix . self::SAGA_PREFIX;
    }

    public function getFullTableName(string $tableName): string
    {
        return $this->getSagaTablePrefix() . $tableName;
    }

    public function raw(string $sql, array $bindings = []): ResultSetInterface
    {
        // Simple SQL parser for common operations
        $sql = trim($sql);
        $startTime = microtime(true);

        try {
            $result = $this->executeSql($sql, $bindings);
            $this->logQuery($sql, $bindings, (microtime(true) - $startTime) * 1000);
            return $result;
        } catch (\Exception $e) {
            $this->logQuery($sql, $bindings, (microtime(true) - $startTime) * 1000);
            throw $e;
        }
    }

    public function lastInsertId(): int
    {
        return $this->lastInsertId;
    }

    public function affectedRows(): int
    {
        return $this->affectedRows;
    }

    public function getLastError(): ?string
    {
        return null;
    }

    public function enableQueryLog(): void
    {
        $this->queryLogEnabled = true;
    }

    public function disableQueryLog(): void
    {
        $this->queryLogEnabled = false;
    }

    public function getQueryLog(): array
    {
        return $this->queryLog;
    }

    public function clearQueryLog(): void
    {
        $this->queryLog = [];
    }

    // =========================================================================
    // In-Memory Data Operations
    // =========================================================================

    /**
     * Insert a row into a table
     *
     * @param array<string, mixed> $data
     * @return int The insert ID
     */
    public function insertRow(string $table, array $data): int
    {
        $fullTable = $this->getFullTableName($table);

        if (!isset($this->tables[$fullTable])) {
            $this->tables[$fullTable] = [];
            $this->autoIncrements[$fullTable] = 1;
        }

        // Auto-generate ID if not provided
        if (!isset($data['id'])) {
            $data['id'] = $this->autoIncrements[$fullTable]++;
        } else {
            // Update auto-increment if needed
            if ($data['id'] >= $this->autoIncrements[$fullTable]) {
                $this->autoIncrements[$fullTable] = $data['id'] + 1;
            }
        }

        $this->tables[$fullTable][$data['id']] = $data;
        $this->lastInsertId = $data['id'];
        $this->affectedRows = 1;

        return $data['id'];
    }

    /**
     * Update rows matching conditions
     *
     * @param array<string, mixed> $data
     * @param array<string, mixed> $where
     * @return int Number of updated rows
     */
    public function updateRows(string $table, array $data, array $where): int
    {
        $fullTable = $this->getFullTableName($table);

        if (!isset($this->tables[$fullTable])) {
            return 0;
        }

        $updated = 0;
        foreach ($this->tables[$fullTable] as $id => &$row) {
            if ($this->matchesWhere($row, $where)) {
                foreach ($data as $column => $value) {
                    $row[$column] = $value;
                }
                $updated++;
            }
        }

        $this->affectedRows = $updated;
        return $updated;
    }

    /**
     * Delete rows matching conditions
     *
     * @param array<string, mixed> $where
     * @return int Number of deleted rows
     */
    public function deleteRows(string $table, array $where): int
    {
        $fullTable = $this->getFullTableName($table);

        if (!isset($this->tables[$fullTable])) {
            return 0;
        }

        $deleted = 0;
        foreach ($this->tables[$fullTable] as $id => $row) {
            if ($this->matchesWhere($row, $where)) {
                unset($this->tables[$fullTable][$id]);
                $deleted++;
            }
        }

        $this->affectedRows = $deleted;
        return $deleted;
    }

    /**
     * Select rows from a table
     *
     * @param array<string, mixed>|null $where
     * @param array<string, string>|null $orderBy [column => direction]
     * @return array<array<string, mixed>>
     */
    public function selectRows(
        string $table,
        ?array $where = null,
        ?array $orderBy = null,
        ?int $limit = null,
        int $offset = 0
    ): array {
        $fullTable = $this->getFullTableName($table);

        if (!isset($this->tables[$fullTable])) {
            return [];
        }

        $rows = array_values($this->tables[$fullTable]);

        // Filter by WHERE
        if ($where !== null) {
            $rows = array_filter($rows, fn($row) => $this->matchesWhere($row, $where));
            $rows = array_values($rows);
        }

        // Order by
        if ($orderBy !== null) {
            usort($rows, function ($a, $b) use ($orderBy) {
                foreach ($orderBy as $column => $direction) {
                    $aVal = $a[$column] ?? null;
                    $bVal = $b[$column] ?? null;

                    if ($aVal === $bVal) {
                        continue;
                    }

                    $cmp = $aVal <=> $bVal;
                    return strtoupper($direction) === 'DESC' ? -$cmp : $cmp;
                }
                return 0;
            });
        }

        // Offset and limit
        if ($offset > 0 || $limit !== null) {
            $rows = array_slice($rows, $offset, $limit);
        }

        return $rows;
    }

    /**
     * Get a single row by ID
     *
     * @return array<string, mixed>|null
     */
    public function getRow(string $table, int $id): ?array
    {
        $fullTable = $this->getFullTableName($table);
        return $this->tables[$fullTable][$id] ?? null;
    }

    /**
     * Count rows in a table
     *
     * @param array<string, mixed>|null $where
     */
    public function countRows(string $table, ?array $where = null): int
    {
        $fullTable = $this->getFullTableName($table);

        if (!isset($this->tables[$fullTable])) {
            return 0;
        }

        if ($where === null) {
            return count($this->tables[$fullTable]);
        }

        return count(array_filter(
            $this->tables[$fullTable],
            fn($row) => $this->matchesWhere($row, $where)
        ));
    }

    /**
     * Check if a table exists
     */
    public function tableExists(string $table): bool
    {
        $fullTable = $this->getFullTableName($table);
        return isset($this->tables[$fullTable]);
    }

    /**
     * Create a table (for schema manager)
     */
    public function createTable(string $table): void
    {
        $fullTable = $this->getFullTableName($table);
        if (!isset($this->tables[$fullTable])) {
            $this->tables[$fullTable] = [];
            $this->autoIncrements[$fullTable] = 1;
        }
    }

    /**
     * Drop a table
     */
    public function dropTable(string $table): void
    {
        $fullTable = $this->getFullTableName($table);
        unset($this->tables[$fullTable]);
        unset($this->autoIncrements[$fullTable]);
    }

    /**
     * Truncate a table
     */
    public function truncateTable(string $table): void
    {
        $fullTable = $this->getFullTableName($table);
        $this->tables[$fullTable] = [];
        $this->autoIncrements[$fullTable] = 1;
    }

    // =========================================================================
    // Transaction Support
    // =========================================================================

    /**
     * Create a snapshot for transaction support
     */
    public function createSnapshot(): void
    {
        $this->transactionSnapshots[] = $this->tables;
    }

    /**
     * Restore from the last snapshot (rollback)
     */
    public function restoreSnapshot(): void
    {
        if (!empty($this->transactionSnapshots)) {
            $this->tables = array_pop($this->transactionSnapshots);
        }
    }

    /**
     * Discard the last snapshot (commit)
     */
    public function discardSnapshot(): void
    {
        if (!empty($this->transactionSnapshots)) {
            array_pop($this->transactionSnapshots);
        }
    }

    /**
     * Get current transaction depth
     */
    public function getTransactionDepth(): int
    {
        return count($this->transactionSnapshots);
    }

    // =========================================================================
    // Testing Helpers
    // =========================================================================

    /**
     * Reset all data (useful between tests)
     */
    public function reset(): void
    {
        $this->tables = [];
        $this->autoIncrements = [];
        $this->transactionSnapshots = [];
        $this->queryLog = [];
        $this->lastInsertId = 0;
        $this->affectedRows = 0;
    }

    /**
     * Seed data for testing
     *
     * @param array<array<string, mixed>> $rows
     */
    public function seed(string $table, array $rows): void
    {
        foreach ($rows as $row) {
            $this->insertRow($table, $row);
        }
    }

    /**
     * Get all data in a table (for assertions)
     *
     * @return array<array<string, mixed>>
     */
    public function getAllRows(string $table): array
    {
        $fullTable = $this->getFullTableName($table);
        return array_values($this->tables[$fullTable] ?? []);
    }

    // =========================================================================
    // Private Helpers
    // =========================================================================

    /**
     * Check if a row matches WHERE conditions
     *
     * @param array<string, mixed> $row
     * @param array<string, mixed> $where Simple key => value conditions
     */
    private function matchesWhere(array $row, array $where): bool
    {
        foreach ($where as $column => $value) {
            if (!isset($row[$column]) || $row[$column] !== $value) {
                return false;
            }
        }
        return true;
    }

    /**
     * Execute a SQL string (basic parser for common operations)
     *
     * @param array<mixed> $bindings
     */
    private function executeSql(string $sql, array $bindings): ResultSetInterface
    {
        $sql = trim($sql);
        $upperSql = strtoupper($sql);

        // Very basic SQL parsing - for testing purposes only
        if (str_starts_with($upperSql, 'SELECT')) {
            return $this->executeSelect($sql, $bindings);
        }

        if (str_starts_with($upperSql, 'INSERT')) {
            return $this->executeInsert($sql, $bindings);
        }

        if (str_starts_with($upperSql, 'UPDATE')) {
            return $this->executeUpdate($sql, $bindings);
        }

        if (str_starts_with($upperSql, 'DELETE')) {
            return $this->executeDelete($sql, $bindings);
        }

        // For DDL and other statements, just return success
        return ResultSet::fromWrite(0, 0);
    }

    /**
     * @param array<mixed> $bindings
     */
    private function executeSelect(string $sql, array $bindings): ResultSetInterface
    {
        // Extract table name (very basic)
        if (preg_match('/FROM\s+`?(\w+)`?/i', $sql, $matches)) {
            $table = $matches[1];

            // Remove prefix for internal lookup
            $table = str_replace($this->getSagaTablePrefix(), '', $table);

            $rows = $this->selectRows($table);
            return ResultSet::fromRows($rows);
        }

        return ResultSet::empty();
    }

    /**
     * @param array<mixed> $bindings
     */
    private function executeInsert(string $sql, array $bindings): ResultSetInterface
    {
        // Extract table name
        if (preg_match('/INTO\s+`?(\w+)`?/i', $sql, $matches)) {
            $table = $matches[1];
            $table = str_replace($this->getSagaTablePrefix(), '', $table);

            // Basic column extraction
            if (preg_match('/\(([^)]+)\)\s*VALUES/i', $sql, $colMatches)) {
                $columns = array_map('trim', explode(',', str_replace(['`', "'"], '', $colMatches[1])));
                $data = array_combine($columns, $bindings);

                $id = $this->insertRow($table, $data);
                return ResultSet::fromWrite(1, $id);
            }
        }

        return ResultSet::fromWrite(0, 0);
    }

    /**
     * @param array<mixed> $bindings
     */
    private function executeUpdate(string $sql, array $bindings): ResultSetInterface
    {
        // Very basic - in real usage, use the QueryBuilder
        return ResultSet::fromWrite($this->affectedRows, 0);
    }

    /**
     * @param array<mixed> $bindings
     */
    private function executeDelete(string $sql, array $bindings): ResultSetInterface
    {
        // Very basic - in real usage, use the QueryBuilder
        return ResultSet::fromWrite($this->affectedRows, 0);
    }

    /**
     * @param array<mixed> $bindings
     */
    private function logQuery(string $sql, array $bindings, float $timeMs): void
    {
        if ($this->queryLogEnabled) {
            $this->queryLog[] = [
                'query' => $sql,
                'bindings' => $bindings,
                'time' => $timeMs,
            ];
        }
    }
}
