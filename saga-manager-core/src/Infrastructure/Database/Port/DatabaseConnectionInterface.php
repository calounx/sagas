<?php
declare(strict_types=1);

namespace SagaManagerCore\Infrastructure\Database\Port;

use SagaManagerCore\Infrastructure\Database\Exception\ConnectionException;

/**
 * Database Connection Port Interface
 *
 * Defines the contract for database connection management.
 * Implementations must handle connection lifecycle, health checks,
 * and provide access to specialized database components.
 *
 * @package SagaManagerCore\Infrastructure\Database\Port
 */
interface DatabaseConnectionInterface
{
    /**
     * Establish database connection
     *
     * @throws ConnectionException When connection cannot be established
     */
    public function connect(): void;

    /**
     * Close database connection
     */
    public function disconnect(): void;

    /**
     * Check if connection is active and healthy
     */
    public function isConnected(): bool;

    /**
     * Ping database to verify connection is alive
     *
     * @throws ConnectionException When ping fails
     */
    public function ping(): bool;

    /**
     * Get the query builder instance
     */
    public function query(): QueryBuilderInterface;

    /**
     * Get the transaction manager instance
     */
    public function transaction(): TransactionManagerInterface;

    /**
     * Get the schema manager instance
     */
    public function schema(): SchemaManagerInterface;

    /**
     * Get the database driver name (e.g., 'wordpress', 'pdo_mysql', 'memory')
     */
    public function getDriverName(): string;

    /**
     * Get current database name
     */
    public function getDatabaseName(): string;

    /**
     * Get table prefix configured for this connection
     */
    public function getTablePrefix(): string;

    /**
     * Get the saga-specific table prefix (e.g., 'wp_saga_')
     */
    public function getSagaTablePrefix(): string;

    /**
     * Get a full table name with all prefixes applied
     *
     * @param string $tableName Base table name without prefixes (e.g., 'entities')
     * @return string Full table name (e.g., 'wp_saga_entities')
     */
    public function getFullTableName(string $tableName): string;

    /**
     * Execute a raw SQL query (use with caution)
     *
     * @param string $sql The SQL query with placeholders
     * @param array<mixed> $bindings Parameter bindings
     * @return ResultSetInterface
     * @throws ConnectionException
     */
    public function raw(string $sql, array $bindings = []): ResultSetInterface;

    /**
     * Get the last insert ID
     */
    public function lastInsertId(): int;

    /**
     * Get the number of rows affected by the last query
     */
    public function affectedRows(): int;

    /**
     * Get the last error message (if any)
     */
    public function getLastError(): ?string;

    /**
     * Enable query logging for debugging
     */
    public function enableQueryLog(): void;

    /**
     * Disable query logging
     */
    public function disableQueryLog(): void;

    /**
     * Get logged queries (only available when logging is enabled)
     *
     * @return array<array{query: string, bindings: array<mixed>, time: float}>
     */
    public function getQueryLog(): array;

    /**
     * Clear the query log
     */
    public function clearQueryLog(): void;
}
