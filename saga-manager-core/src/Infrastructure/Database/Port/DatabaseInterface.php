<?php

declare(strict_types=1);

namespace SagaManagerCore\Infrastructure\Database\Port;

/**
 * Database Port Interface (Main Facade)
 *
 * Primary entry point for all database operations in Saga Manager.
 * Provides access to connection, query building, transactions, and schema management.
 *
 * This interface follows the hexagonal architecture pattern, decoupling
 * domain logic from infrastructure concerns.
 */
interface DatabaseInterface
{
    /**
     * Get the database connection component
     */
    public function connection(): DatabaseConnectionInterface;

    /**
     * Create a new query builder instance
     */
    public function query(): QueryBuilderInterface;

    /**
     * Get the transaction manager
     */
    public function transaction(): TransactionManagerInterface;

    /**
     * Get the schema manager
     */
    public function schema(): SchemaManagerInterface;

    /**
     * Execute a raw SQL query
     *
     * @param string $sql SQL query with placeholders
     * @param array<mixed> $bindings Parameter bindings
     * @return ResultSetInterface
     * @throws \SagaManagerCore\Domain\Exception\DatabaseException On failure
     */
    public function raw(string $sql, array $bindings = []): ResultSetInterface;

    /**
     * Execute a raw SQL query and return affected rows
     *
     * @param string $sql SQL query with placeholders
     * @param array<mixed> $bindings Parameter bindings
     * @return int Number of affected rows
     * @throws \SagaManagerCore\Domain\Exception\DatabaseException On failure
     */
    public function statement(string $sql, array $bindings = []): int;

    /**
     * Get the full table name with prefix
     *
     * @param string $table Base table name (e.g., 'entities')
     * @return string Full table name (e.g., 'wp_saga_entities')
     */
    public function table(string $table): string;

    /**
     * Check if the database is available and responsive
     */
    public function ping(): bool;

    /**
     * Get the last insert ID
     */
    public function lastInsertId(): int;

    /**
     * Get the last error message
     */
    public function lastError(): string;

    /**
     * Cache a query result
     *
     * @param string $key Cache key
     * @param mixed $data Data to cache
     * @param int $ttl Time to live in seconds
     */
    public function cacheSet(string $key, mixed $data, int $ttl = 300): void;

    /**
     * Get a cached query result
     *
     * @param string $key Cache key
     * @return mixed|null Cached data or null if not found
     */
    public function cacheGet(string $key): mixed;

    /**
     * Delete a cached query result
     *
     * @param string $key Cache key
     */
    public function cacheDelete(string $key): void;

    /**
     * Clear all saga-related cache entries
     */
    public function cacheClear(): void;

    /**
     * Log a slow query for performance monitoring
     *
     * @param string $sql SQL query
     * @param float $duration Query duration in milliseconds
     */
    public function logSlowQuery(string $sql, float $duration): void;

    /**
     * Get query performance statistics
     *
     * @return array{
     *     total_queries: int,
     *     slow_queries: int,
     *     cache_hits: int,
     *     cache_misses: int,
     *     average_query_time: float
     * }
     */
    public function getStats(): array;
}
