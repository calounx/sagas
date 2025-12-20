<?php

declare(strict_types=1);

namespace SagaManager\Infrastructure\Database\Contract;

/**
 * Database Interface
 *
 * Core contract for all database adapters. Provides a unified API for
 * database operations regardless of the underlying implementation.
 *
 * @example WordPress adapter:
 *   $db = new WordPressDatabaseAdapter();
 *   $result = $db->select('entities', ['id' => 1]);
 *
 * @example PDO adapter:
 *   $db = new PdoDatabaseAdapter($config);
 *   $result = $db->select('entities', ['id' => 1]);
 *
 * @example InMemory adapter (for testing):
 *   $db = new InMemoryDatabaseAdapter();
 *   $db->insert('entities', ['name' => 'Luke']);
 */
interface DatabaseInterface
{
    /**
     * Get the query builder instance
     */
    public function query(): QueryBuilderInterface;

    /**
     * Get the transaction manager instance
     */
    public function transaction(): TransactionInterface;

    /**
     * Get the schema manager instance
     */
    public function schema(): SchemaManagerInterface;

    /**
     * Execute a raw SQL query
     *
     * @param string $sql The SQL query with placeholders
     * @param array<int|string, mixed> $params Parameters to bind
     * @return ResultSetInterface The result set
     *
     * @example
     *   $result = $db->raw('SELECT * FROM users WHERE id = ?', [1]);
     */
    public function raw(string $sql, array $params = []): ResultSetInterface;

    /**
     * Insert a row into a table
     *
     * @param string $table Table name (without prefix)
     * @param array<string, mixed> $data Column => value pairs
     * @return int The last insert ID
     *
     * @example
     *   $id = $db->insert('entities', [
     *       'canonical_name' => 'Luke Skywalker',
     *       'entity_type' => 'character',
     *   ]);
     */
    public function insert(string $table, array $data): int;

    /**
     * Update rows in a table
     *
     * @param string $table Table name (without prefix)
     * @param array<string, mixed> $data Column => value pairs to update
     * @param array<string, mixed> $where Column => value pairs for WHERE clause
     * @return int Number of affected rows
     *
     * @example
     *   $affected = $db->update(
     *       'entities',
     *       ['importance_score' => 100],
     *       ['id' => 1]
     *   );
     */
    public function update(string $table, array $data, array $where): int;

    /**
     * Delete rows from a table
     *
     * @param string $table Table name (without prefix)
     * @param array<string, mixed> $where Column => value pairs for WHERE clause
     * @return int Number of affected rows
     *
     * @example
     *   $deleted = $db->delete('entities', ['id' => 1]);
     */
    public function delete(string $table, array $where): int;

    /**
     * Select rows from a table
     *
     * @param string $table Table name (without prefix)
     * @param array<string, mixed> $where Column => value pairs for WHERE clause
     * @param array<string> $columns Columns to select (empty = all)
     * @param array<string, string> $orderBy Column => direction pairs
     * @param int|null $limit Maximum rows to return
     * @param int $offset Number of rows to skip
     * @return ResultSetInterface The result set
     *
     * @example
     *   $result = $db->select(
     *       'entities',
     *       ['saga_id' => 1, 'entity_type' => 'character'],
     *       ['id', 'canonical_name'],
     *       ['importance_score' => 'DESC'],
     *       10,
     *       0
     *   );
     */
    public function select(
        string $table,
        array $where = [],
        array $columns = [],
        array $orderBy = [],
        ?int $limit = null,
        int $offset = 0
    ): ResultSetInterface;

    /**
     * Find a single row by primary key
     *
     * @param string $table Table name (without prefix)
     * @param int|string $id Primary key value
     * @param string $primaryKey Primary key column name
     * @return array<string, mixed>|null The row data or null
     *
     * @example
     *   $entity = $db->find('entities', 42);
     */
    public function find(string $table, int|string $id, string $primaryKey = 'id'): ?array;

    /**
     * Count rows in a table
     *
     * @param string $table Table name (without prefix)
     * @param array<string, mixed> $where Column => value pairs for WHERE clause
     * @return int Row count
     *
     * @example
     *   $count = $db->count('entities', ['saga_id' => 1]);
     */
    public function count(string $table, array $where = []): int;

    /**
     * Check if a row exists
     *
     * @param string $table Table name (without prefix)
     * @param array<string, mixed> $where Column => value pairs for WHERE clause
     * @return bool True if at least one row matches
     *
     * @example
     *   if ($db->exists('entities', ['slug' => 'luke-skywalker'])) {
     *       // Entity exists
     *   }
     */
    public function exists(string $table, array $where): bool;

    /**
     * Get the full table name with prefix
     *
     * @param string $table Base table name
     * @return string Full table name with prefix
     */
    public function getTableName(string $table): string;

    /**
     * Get the table prefix
     *
     * @return string The table prefix
     */
    public function getPrefix(): string;

    /**
     * Get the last error message
     *
     * @return string|null The last error or null
     */
    public function getLastError(): ?string;

    /**
     * Get the last insert ID
     *
     * @return int The last insert ID
     */
    public function getLastInsertId(): int;
}
