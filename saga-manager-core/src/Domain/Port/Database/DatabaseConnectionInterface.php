<?php
declare(strict_types=1);

namespace SagaManagerCore\Domain\Port\Database;

use SagaManagerCore\Domain\Exception\DatabaseException;

/**
 * Port interface for database connection management
 *
 * Abstracts connection lifecycle operations from infrastructure.
 * Implementations handle platform-specific connection details (WordPress, PDO, etc.).
 *
 * @example
 * ```php
 * // Using the connection
 * $connection = $container->get(DatabaseConnectionInterface::class);
 *
 * if (!$connection->isConnected()) {
 *     $connection->connect();
 * }
 *
 * // Check connection health
 * if (!$connection->ping()) {
 *     $connection->disconnect();
 *     $connection->connect();
 * }
 *
 * // Get connection info for logging
 * $info = $connection->getConnectionInfo();
 * error_log("Connected to {$info['host']}:{$info['database']}");
 * ```
 */
interface DatabaseConnectionInterface
{
    /**
     * Establish database connection
     *
     * Connects to the database using configured credentials.
     * Idempotent: calling when already connected has no effect.
     *
     * @throws DatabaseException When connection cannot be established
     */
    public function connect(): void;

    /**
     * Close database connection
     *
     * Releases connection resources.
     * Idempotent: calling when not connected has no effect.
     */
    public function disconnect(): void;

    /**
     * Check if currently connected
     *
     * @return bool True if connection is established
     */
    public function isConnected(): bool;

    /**
     * Get connection information
     *
     * Returns metadata about the current connection for debugging/logging.
     * Sensitive data (passwords) should never be included.
     *
     * @return array{
     *     host: string,
     *     database: string,
     *     port: int,
     *     charset: string,
     *     collation: string,
     *     server_version: string|null,
     *     connection_id: int|null,
     *     prefix: string
     * }
     */
    public function getConnectionInfo(): array;

    /**
     * Test if connection is still alive
     *
     * Sends a lightweight query to verify connection health.
     * Useful for connection pooling and long-running processes.
     *
     * @return bool True if connection responds correctly
     */
    public function ping(): bool;

    /**
     * Get the table prefix for this connection
     *
     * Returns the WordPress table prefix (e.g., 'wp_saga_').
     * Used by repositories to construct table names.
     *
     * @return string Table prefix including 'saga_' namespace
     */
    public function getTablePrefix(): string;

    /**
     * Get the raw underlying connection handle
     *
     * Returns the platform-specific connection object (wpdb, PDO, etc.).
     * Use sparingly - prefer higher-level abstractions.
     *
     * @return object Platform-specific connection object
     */
    public function getHandle(): object;

    /**
     * Execute a raw SQL query
     *
     * Low-level query execution for cases not covered by QueryBuilder.
     * Use prepared statements for user input.
     *
     * @param string $sql SQL query to execute
     * @param array<mixed> $bindings Parameter bindings
     * @return ResultSetInterface Query results
     * @throws DatabaseException On query failure
     */
    public function query(string $sql, array $bindings = []): ResultSetInterface;

    /**
     * Execute a raw SQL statement that doesn't return results
     *
     * For INSERT, UPDATE, DELETE, DDL statements.
     *
     * @param string $sql SQL statement to execute
     * @param array<mixed> $bindings Parameter bindings
     * @return int Number of affected rows
     * @throws DatabaseException On execution failure
     */
    public function execute(string $sql, array $bindings = []): int;

    /**
     * Get the last auto-increment ID
     *
     * @return int Last insert ID, or 0 if none
     */
    public function getLastInsertId(): int;

    /**
     * Get the last error message
     *
     * @return string|null Last error message, or null if no error
     */
    public function getLastError(): ?string;

    /**
     * Quote a value for safe SQL inclusion
     *
     * Prefer prepared statements over manual quoting.
     *
     * @param mixed $value Value to quote
     * @return string Quoted value
     */
    public function quote(mixed $value): string;

    /**
     * Quote an identifier (table/column name)
     *
     * @param string $identifier Identifier to quote
     * @return string Quoted identifier (e.g., `table_name`)
     */
    public function quoteIdentifier(string $identifier): string;
}
