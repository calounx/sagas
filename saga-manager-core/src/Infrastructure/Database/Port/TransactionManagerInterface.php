<?php
declare(strict_types=1);

namespace SagaManagerCore\Infrastructure\Database\Port;

use SagaManagerCore\Infrastructure\Database\Exception\TransactionException;

/**
 * Transaction Manager Port Interface
 *
 * Provides ACID transaction management with support for nested transactions
 * (using savepoints where available) and automatic rollback on failure.
 *
 * Usage:
 * ```php
 * // Simple transaction
 * $connection->transaction()->run(function($tx) {
 *     // All operations here are atomic
 *     $this->repository->save($entity);
 * });
 *
 * // Manual control
 * $tx = $connection->transaction();
 * $tx->begin();
 * try {
 *     // operations
 *     $tx->commit();
 * } catch (\Exception $e) {
 *     $tx->rollback();
 *     throw $e;
 * }
 * ```
 *
 * @package SagaManagerCore\Infrastructure\Database\Port
 */
interface TransactionManagerInterface
{
    /**
     * Begin a new transaction
     *
     * If a transaction is already active, creates a savepoint (nested transaction).
     *
     * @throws TransactionException When transaction cannot be started
     */
    public function begin(): void;

    /**
     * Commit the current transaction
     *
     * If in a nested transaction (savepoint), releases the savepoint.
     * Only the outermost commit actually commits to the database.
     *
     * @throws TransactionException When commit fails
     */
    public function commit(): void;

    /**
     * Rollback the current transaction
     *
     * If in a nested transaction, rolls back to the savepoint.
     * Otherwise, rolls back the entire transaction.
     *
     * @throws TransactionException When rollback fails
     */
    public function rollback(): void;

    /**
     * Check if a transaction is currently active
     *
     * @return bool True if in a transaction
     */
    public function isActive(): bool;

    /**
     * Get the current transaction nesting level
     *
     * @return int 0 = no transaction, 1 = base transaction, 2+ = nested
     */
    public function getLevel(): int;

    /**
     * Execute a callback within a transaction
     *
     * Automatically begins, commits, and rolls back on exception.
     *
     * @template T
     * @param callable(TransactionManagerInterface): T $callback
     * @return T The callback's return value
     * @throws TransactionException When transaction fails
     * @throws \Throwable Re-throws any exception from the callback after rollback
     */
    public function run(callable $callback): mixed;

    /**
     * Execute callback with automatic retry on deadlock/lock timeout
     *
     * @template T
     * @param callable(TransactionManagerInterface): T $callback
     * @param int $maxAttempts Maximum retry attempts (default: 3)
     * @param int $retryDelayMs Delay between retries in milliseconds (default: 100)
     * @return T The callback's return value
     * @throws TransactionException When all retries fail
     */
    public function runWithRetry(
        callable $callback,
        int $maxAttempts = 3,
        int $retryDelayMs = 100
    ): mixed;

    /**
     * Create a savepoint manually
     *
     * @param string $name Savepoint name
     * @throws TransactionException When savepoint creation fails
     */
    public function savepoint(string $name): void;

    /**
     * Rollback to a specific savepoint
     *
     * @param string $name Savepoint name
     * @throws TransactionException When rollback fails
     */
    public function rollbackTo(string $name): void;

    /**
     * Release a savepoint
     *
     * @param string $name Savepoint name
     * @throws TransactionException When release fails
     */
    public function releaseSavepoint(string $name): void;

    /**
     * Set transaction isolation level
     *
     * @param TransactionIsolation $level Isolation level
     * @throws TransactionException When setting fails
     */
    public function setIsolationLevel(TransactionIsolation $level): void;

    /**
     * Get current transaction isolation level
     *
     * @return TransactionIsolation
     */
    public function getIsolationLevel(): TransactionIsolation;

    /**
     * Execute operations after transaction commits (deferred)
     *
     * The callback is only executed if the outermost transaction commits.
     * Useful for cache invalidation, event dispatch, etc.
     *
     * @param callable(): void $callback
     */
    public function afterCommit(callable $callback): void;

    /**
     * Execute operations after transaction rolls back
     *
     * @param callable(): void $callback
     */
    public function afterRollback(callable $callback): void;
}
