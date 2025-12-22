<?php

declare(strict_types=1);

namespace SagaManager\Infrastructure\Database\Contract;

/**
 * Transaction Interface
 *
 * Provides transaction management capabilities for database operations.
 *
 * @example Basic transaction:
 *   $db->transaction()->begin();
 *   try {
 *       $db->insert('entities', $entityData);
 *       $db->insert('attributes', $attributeData);
 *       $db->transaction()->commit();
 *   } catch (Exception $e) {
 *       $db->transaction()->rollback();
 *       throw $e;
 *   }
 *
 * @example Using callback:
 *   $result = $db->transaction()->run(function($db) {
 *       $id = $db->insert('entities', $entityData);
 *       $db->insert('attributes', [..., 'entity_id' => $id]);
 *       return $id;
 *   });
 */
interface TransactionInterface
{
    /**
     * Begin a new transaction
     *
     * @return void
     * @throws \SagaManager\Infrastructure\Exception\DatabaseException If transaction cannot be started
     */
    public function begin(): void;

    /**
     * Commit the current transaction
     *
     * @return void
     * @throws \SagaManager\Infrastructure\Exception\DatabaseException If no transaction is active
     */
    public function commit(): void;

    /**
     * Rollback the current transaction
     *
     * @return void
     * @throws \SagaManager\Infrastructure\Exception\DatabaseException If no transaction is active
     */
    public function rollback(): void;

    /**
     * Check if a transaction is currently active
     *
     * @return bool
     */
    public function isActive(): bool;

    /**
     * Get the current transaction nesting level
     *
     * @return int Zero if no transaction is active
     */
    public function getLevel(): int;

    /**
     * Execute a callback within a transaction
     *
     * Automatically commits on success or rolls back on exception.
     *
     * @template T
     * @param callable(DatabaseInterface): T $callback
     * @return T The callback return value
     * @throws \Throwable Re-throws any exception after rollback
     *
     * @example
     *   $entityId = $db->transaction()->run(function(DatabaseInterface $db) use ($data) {
     *       $id = $db->insert('entities', $data);
     *       $db->insert('quality_metrics', ['entity_id' => $id, 'completeness_score' => 0]);
     *       return $id;
     *   });
     */
    public function run(callable $callback): mixed;

    /**
     * Create a savepoint within the current transaction
     *
     * @param string $name Savepoint name
     * @return void
     * @throws \SagaManager\Infrastructure\Exception\DatabaseException If no transaction is active
     *
     * @example
     *   $db->transaction()->begin();
     *   $db->insert('entities', $entity1);
     *   $db->transaction()->savepoint('after_first');
     *   try {
     *       $db->insert('entities', $entity2);
     *   } catch (Exception $e) {
     *       $db->transaction()->rollbackTo('after_first');
     *   }
     *   $db->transaction()->commit();
     */
    public function savepoint(string $name): void;

    /**
     * Rollback to a savepoint
     *
     * @param string $name Savepoint name
     * @return void
     * @throws \SagaManager\Infrastructure\Exception\DatabaseException If savepoint does not exist
     */
    public function rollbackTo(string $name): void;

    /**
     * Release a savepoint
     *
     * @param string $name Savepoint name
     * @return void
     */
    public function releaseSavepoint(string $name): void;
}
