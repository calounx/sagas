<?php

declare(strict_types=1);

namespace SagaManager\Infrastructure\Database\Adapter\InMemory;

use SagaManager\Infrastructure\Database\Contract\TransactionInterface;
use SagaManager\Infrastructure\Database\Contract\DatabaseInterface;
use SagaManager\Infrastructure\Exception\DatabaseException;

/**
 * In-Memory Transaction Manager
 *
 * Manages transactions for in-memory database by creating snapshots of data.
 * Supports nested transactions via savepoints.
 *
 * @example
 *   $db->transaction()->begin();
 *   try {
 *       $db->insert('entities', ['name' => 'Luke']);
 *       $db->transaction()->commit();
 *   } catch (Exception $e) {
 *       $db->transaction()->rollback();
 *       throw $e;
 *   }
 */
final class InMemoryTransactionManager implements TransactionInterface
{
    private int $level = 0;

    /** @var array<int, InMemoryDataStore> */
    private array $snapshots = [];

    /** @var array<string, InMemoryDataStore> */
    private array $savepoints = [];

    public function __construct(
        private readonly InMemoryDataStore $dataStore,
        private readonly DatabaseInterface $database,
    ) {}

    public function begin(): void
    {
        // Create a snapshot of current state
        $this->snapshots[$this->level] = $this->dataStore->clone();
        $this->level++;
    }

    public function commit(): void
    {
        if ($this->level === 0) {
            throw new DatabaseException('No active transaction to commit');
        }

        $this->level--;

        // Remove the snapshot for this level
        unset($this->snapshots[$this->level]);

        // Clean up any savepoints from this level
        $this->cleanupSavepoints();
    }

    public function rollback(): void
    {
        if ($this->level === 0) {
            throw new DatabaseException('No active transaction to rollback');
        }

        $this->level--;

        // Restore from snapshot
        if (isset($this->snapshots[$this->level])) {
            $this->dataStore->restore($this->snapshots[$this->level]);
            unset($this->snapshots[$this->level]);
        }

        // Clean up any savepoints from this level
        $this->cleanupSavepoints();
    }

    public function isActive(): bool
    {
        return $this->level > 0;
    }

    public function getLevel(): int
    {
        return $this->level;
    }

    public function run(callable $callback): mixed
    {
        $this->begin();

        try {
            $result = $callback($this->database);
            $this->commit();
            return $result;
        } catch (\Throwable $e) {
            $this->rollback();
            throw $e;
        }
    }

    public function savepoint(string $name): void
    {
        if ($this->level === 0) {
            throw new DatabaseException('Cannot create savepoint outside of transaction');
        }

        $key = $this->getSavepointKey($name);
        $this->savepoints[$key] = $this->dataStore->clone();
    }

    public function rollbackTo(string $name): void
    {
        $key = $this->getSavepointKey($name);

        if (!isset($this->savepoints[$key])) {
            throw new DatabaseException("Savepoint '{$name}' does not exist");
        }

        $this->dataStore->restore($this->savepoints[$key]);
    }

    public function releaseSavepoint(string $name): void
    {
        $key = $this->getSavepointKey($name);
        unset($this->savepoints[$key]);
    }

    private function getSavepointKey(string $name): string
    {
        return "{$this->level}_{$name}";
    }

    private function cleanupSavepoints(): void
    {
        // Remove all savepoints for levels higher than current
        foreach (array_keys($this->savepoints) as $key) {
            $parts = explode('_', $key, 2);
            $level = (int) $parts[0];
            if ($level > $this->level) {
                unset($this->savepoints[$key]);
            }
        }
    }
}
