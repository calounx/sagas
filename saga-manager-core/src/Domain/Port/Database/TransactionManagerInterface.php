<?php
declare(strict_types=1);

namespace SagaManagerCore\Domain\Port\Database;

use SagaManagerCore\Domain\Exception\DatabaseException;

/**
 * Port interface for database transaction management
 *
 * Provides ACID transaction control with support for nested transactions via savepoints.
 * All saga modifications requiring atomicity must use transactions.
 *
 * @example
 * ```php
 * // Manual transaction control
 * $tx = $db->transaction();
 * $tx->beginTransaction();
 *
 * try {
 *     $entityId = $db->query()->insert('saga_entities', $entityData);
 *
 *     foreach ($attributes as $attr) {
 *         $db->query()->insert('saga_attribute_values', [
 *             'entity_id' => $entityId,
 *             'attribute_id' => $attr['id'],
 *             'value_string' => $attr['value'],
 *         ]);
 *     }
 *
 *     $tx->commit();
 * } catch (\Exception $e) {
 *     $tx->rollback();
 *     throw new DatabaseException('Entity creation failed: ' . $e->getMessage(), 0, $e);
 * }
 *
 * // Recommended: Use transactional() callback
 * $entityId = $db->transaction()->transactional(function() use ($db, $entityData, $attributes) {
 *     $entityId = $db->query()->insert('saga_entities', $entityData);
 *
 *     foreach ($attributes as $attr) {
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
 * // Nested transactions (savepoints)
 * $tx->beginTransaction();
 * // ... outer operations ...
 * $tx->beginTransaction(); // Creates savepoint
 * // ... inner operations ...
 * $tx->rollback(); // Rolls back to savepoint only
 * // ... continue outer operations ...
 * $tx->commit();
 * ```
 */
interface TransactionManagerInterface
{
    /**
     * Begin a new transaction
     *
     * If already in a transaction, creates a savepoint for nested transaction.
     * Track nesting level with inTransaction() and getTransactionLevel().
     *
     * @throws DatabaseException If transaction cannot be started
     */
    public function beginTransaction(): void;

    /**
     * Commit the current transaction
     *
     * If in a nested transaction, releases the savepoint.
     * Only the outermost commit actually commits to database.
     *
     * @throws DatabaseException If not in a transaction or commit fails
     */
    public function commit(): void;

    /**
     * Rollback the current transaction
     *
     * If in a nested transaction, rolls back to the savepoint only.
     * Outermost rollback discards all changes.
     *
     * @throws DatabaseException If not in a transaction or rollback fails
     */
    public function rollback(): void;

    /**
     * Check if currently in a transaction
     *
     * @return bool True if in transaction (at any nesting level)
     */
    public function inTransaction(): bool;

    /**
     * Get current transaction nesting level
     *
     * @return int 0 = not in transaction, 1 = in transaction, 2+ = nested
     */
    public function getTransactionLevel(): int;

    /**
     * Execute callback within a transaction
     *
     * Automatically commits on success, rolls back on exception.
     * Supports nested transactions via savepoints.
     * Returns the callback's return value.
     *
     * @template T
     * @param callable(): T $callback Function to execute
     * @return T Callback return value
     * @throws DatabaseException If transaction fails
     * @throws \Throwable Re-throws any exception from callback after rollback
     *
     * @example
     * ```php
     * $result = $tx->transactional(function() use ($db) {
     *     $id = $db->query()->insert('saga_entities', $data);
     *     $db->query()->insert('saga_quality_metrics', ['entity_id' => $id]);
     *     return $id;
     * });
     * ```
     */
    public function transactional(callable $callback): mixed;

    /**
     * Create a named savepoint
     *
     * Allows manual savepoint control within a transaction.
     *
     * @param string $name Savepoint name
     * @throws DatabaseException If not in transaction or savepoint fails
     */
    public function savepoint(string $name): void;

    /**
     * Rollback to a named savepoint
     *
     * Discards changes since the savepoint was created.
     *
     * @param string $name Savepoint name
     * @throws DatabaseException If savepoint doesn't exist or rollback fails
     */
    public function rollbackToSavepoint(string $name): void;

    /**
     * Release a named savepoint
     *
     * Removes savepoint without affecting data. Changes become permanent
     * when the outer transaction commits.
     *
     * @param string $name Savepoint name
     * @throws DatabaseException If savepoint doesn't exist
     */
    public function releaseSavepoint(string $name): void;

    /**
     * Set transaction isolation level
     *
     * Must be called before beginTransaction().
     * Level persists for the connection until changed.
     *
     * @param TransactionIsolationLevel $level Isolation level
     * @throws DatabaseException If already in transaction
     */
    public function setIsolationLevel(TransactionIsolationLevel $level): void;

    /**
     * Get current transaction isolation level
     *
     * @return TransactionIsolationLevel Current level
     */
    public function getIsolationLevel(): TransactionIsolationLevel;

    /**
     * Set transaction as read-only
     *
     * Optimization hint that transaction won't modify data.
     * Must be called before beginTransaction().
     *
     * @param bool $readOnly True for read-only transaction
     * @throws DatabaseException If already in transaction
     */
    public function setReadOnly(bool $readOnly = true): void;

    /**
     * Check if current transaction is read-only
     *
     * @return bool True if read-only
     */
    public function isReadOnly(): bool;
}
