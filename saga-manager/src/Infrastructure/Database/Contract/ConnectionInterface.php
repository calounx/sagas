<?php

declare(strict_types=1);

namespace SagaManager\Infrastructure\Database\Contract;

/**
 * Connection Interface
 *
 * Represents a database connection with lifecycle management capabilities.
 */
interface ConnectionInterface
{
    /**
     * Get the underlying connection resource
     *
     * @return mixed The native connection (PDO, mysqli, wpdb, etc.)
     */
    public function getNativeConnection(): mixed;

    /**
     * Check if the connection is active
     *
     * @return bool
     */
    public function isConnected(): bool;

    /**
     * Connect to the database
     *
     * @return void
     * @throws \SagaManager\Infrastructure\Exception\DatabaseException On connection failure
     */
    public function connect(): void;

    /**
     * Disconnect from the database
     *
     * @return void
     */
    public function disconnect(): void;

    /**
     * Reconnect to the database
     *
     * @return void
     */
    public function reconnect(): void;

    /**
     * Get the database driver name
     *
     * @return string (mysql, pgsql, sqlite, wordpress)
     */
    public function getDriverName(): string;

    /**
     * Get the database server version
     *
     * @return string
     */
    public function getServerVersion(): string;

    /**
     * Quote a string for use in a query
     *
     * @param string $value The string to quote
     * @return string The quoted string
     */
    public function quote(string $value): string;

    /**
     * Quote an identifier (table name, column name)
     *
     * @param string $identifier The identifier to quote
     * @return string The quoted identifier
     */
    public function quoteIdentifier(string $identifier): string;

    /**
     * Get connection statistics
     *
     * @return array{
     *     queries_executed: int,
     *     total_time: float,
     *     connected_at: \DateTimeImmutable|null
     * }
     */
    public function getStats(): array;

    /**
     * Ping the server to check connection health
     *
     * @return bool True if connection is healthy
     */
    public function ping(): bool;
}
