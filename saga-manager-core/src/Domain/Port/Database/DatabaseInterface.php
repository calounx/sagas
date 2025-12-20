<?php
declare(strict_types=1);

namespace SagaManagerCore\Domain\Port\Database;

/**
 * Facade interface for database operations
 *
 * Provides unified access to all database port interfaces.
 * Acts as the main entry point for domain/application layer database access.
 *
 * Implementations should be registered in the DI container as a singleton.
 *
 * @example
 * ```php
 * // Get database from container
 * $db = $container->get(DatabaseInterface::class);
 *
 * // Basic query
 * $entities = $db->query()
 *     ->select(['id', 'canonical_name'])
 *     ->from('saga_entities')
 *     ->where('saga_id', '=', 1)
 *     ->orderBy('importance_score', 'DESC')
 *     ->limit(10)
 *     ->execute()
 *     ->fetchAll();
 *
 * // Transaction with automatic commit/rollback
 * $entityId = $db->transaction()->transactional(function() use ($db, $data) {
 *     $entityId = $db->query()->insert('saga_entities', $data['entity']);
 *
 *     foreach ($data['attributes'] as $attr) {
 *         $db->query()->insert('saga_attribute_values', [
 *             'entity_id' => $entityId,
 *             'attribute_id' => $attr['id'],
 *             'value_string' => $attr['value'],
 *         ]);
 *     }
 *
 *     return $entityId;
 * });
 *
 * // Schema operations
 * if (!$db->schema()->tableExists('saga_entities')) {
 *     $db->schema()->createTable($entityTableDefinition);
 * }
 *
 * // Check connection health
 * if (!$db->getConnection()->ping()) {
 *     $db->getConnection()->disconnect();
 *     $db->getConnection()->connect();
 * }
 *
 * // Get table prefix for raw queries
 * $prefix = $db->getTablePrefix();
 * $sql = "SELECT COUNT(*) FROM {$prefix}saga_entities WHERE saga_id = %d";
 * ```
 */
interface DatabaseInterface
{
    /**
     * Get the connection manager
     *
     * Use for connection lifecycle management and low-level operations.
     *
     * @return DatabaseConnectionInterface
     */
    public function getConnection(): DatabaseConnectionInterface;

    /**
     * Get a new query builder instance
     *
     * Returns a fresh builder for constructing SELECT, INSERT, UPDATE, DELETE queries.
     *
     * @return QueryBuilderInterface
     */
    public function query(): QueryBuilderInterface;

    /**
     * Get the transaction manager
     *
     * Use for transaction control (begin, commit, rollback, savepoints).
     *
     * @return TransactionManagerInterface
     */
    public function transaction(): TransactionManagerInterface;

    /**
     * Get the schema manager
     *
     * Use for DDL operations (create/alter/drop tables, indexes, etc.).
     *
     * @return SchemaManagerInterface
     */
    public function schema(): SchemaManagerInterface;

    /**
     * Get the table prefix (shortcut)
     *
     * Convenience method equivalent to getConnection()->getTablePrefix().
     *
     * @return string Table prefix (e.g., 'wp_saga_')
     */
    public function getTablePrefix(): string;

    /**
     * Check if currently in a transaction (shortcut)
     *
     * Convenience method equivalent to transaction()->inTransaction().
     *
     * @return bool True if in transaction
     */
    public function inTransaction(): bool;

    /**
     * Execute a raw SELECT query
     *
     * Shortcut for simple queries. Prefer query() builder for complex queries.
     *
     * @param string $sql SQL query with placeholders
     * @param array<mixed> $bindings Parameter values
     * @return ResultSetInterface Query results
     */
    public function select(string $sql, array $bindings = []): ResultSetInterface;

    /**
     * Execute a raw statement (INSERT, UPDATE, DELETE)
     *
     * Shortcut for simple statements. Prefer query() builder for complex operations.
     *
     * @param string $sql SQL statement with placeholders
     * @param array<mixed> $bindings Parameter values
     * @return int Affected rows
     */
    public function execute(string $sql, array $bindings = []): int;

    /**
     * Execute callback within a transaction (shortcut)
     *
     * Convenience method equivalent to transaction()->transactional($callback).
     *
     * @template T
     * @param callable(): T $callback Function to execute
     * @return T Callback return value
     */
    public function transactional(callable $callback): mixed;

    /**
     * Check if a table exists (shortcut)
     *
     * Convenience method equivalent to schema()->tableExists($table).
     *
     * @param string $table Table name (without prefix)
     * @return bool True if table exists
     */
    public function tableExists(string $table): bool;

    /**
     * Get the full prefixed table name (shortcut)
     *
     * Convenience method equivalent to schema()->getTableName($table).
     *
     * @param string $table Table name (without prefix)
     * @return string Full table name with prefix
     */
    public function getTableName(string $table): string;

    /**
     * Get debug information
     *
     * Returns information useful for debugging and monitoring.
     * SECURITY: Do not log in production.
     *
     * @return array{
     *     connected: bool,
     *     in_transaction: bool,
     *     transaction_level: int,
     *     prefix: string,
     *     last_error: string|null,
     *     connection_info: array<string, mixed>
     * }
     */
    public function getDebugInfo(): array;
}
