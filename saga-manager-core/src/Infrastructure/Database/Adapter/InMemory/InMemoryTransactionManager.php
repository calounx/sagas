<?php
declare(strict_types=1);

namespace SagaManagerCore\Infrastructure\Database\Adapter\InMemory;

use SagaManagerCore\Infrastructure\Database\Port\TransactionManagerInterface;
use SagaManagerCore\Infrastructure\Database\Port\TransactionIsolation;
use SagaManagerCore\Infrastructure\Database\Exception\TransactionException;

/**
 * In-Memory Transaction Manager for Testing
 *
 * Simulates transaction behavior using data snapshots.
 * Supports nested transactions via internal snapshot stack.
 *
 * @package SagaManagerCore\Infrastructure\Database\Adapter\InMemory
 */
class InMemoryTransactionManager implements TransactionManagerInterface
{
    private InMemoryConnection $connection;
    private int $level = 0;
    /** @var array<callable(): void> */
    private array $afterCommitCallbacks = [];
    /** @var array<callable(): void> */
    private array $afterRollbackCallbacks = [];
    private TransactionIsolation $isolationLevel;

    public function __construct(InMemoryConnection $connection)
    {
        $this->connection = $connection;
        $this->isolationLevel = TransactionIsolation::REPEATABLE_READ;
    }

    public function begin(): void
    {
        $this->connection->createSnapshot();
        $this->level++;
    }

    public function commit(): void
    {
        if ($this->level === 0) {
            throw TransactionException::notActive();
        }

        $this->connection->discardSnapshot();
        $this->level--;

        if ($this->level === 0) {
            $this->executeCallbacks($this->afterCommitCallbacks);
            $this->clearCallbacks();
        }
    }

    public function rollback(): void
    {
        if ($this->level === 0) {
            return;
        }

        $this->connection->restoreSnapshot();
        $this->level--;

        if ($this->level === 0) {
            $this->executeCallbacks($this->afterRollbackCallbacks);
            $this->clearCallbacks();
        }
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
            $result = $callback($this);
            $this->commit();
            return $result;
        } catch (\Throwable $e) {
            $this->rollback();
            throw $e;
        }
    }

    public function runWithRetry(
        callable $callback,
        int $maxAttempts = 3,
        int $retryDelayMs = 100
    ): mixed {
        // In-memory doesn't have deadlocks, but implement for interface compliance
        return $this->run($callback);
    }

    public function savepoint(string $name): void
    {
        if ($this->level === 0) {
            throw TransactionException::notActive();
        }
        $this->connection->createSnapshot();
    }

    public function rollbackTo(string $name): void
    {
        if ($this->level === 0) {
            throw TransactionException::notActive();
        }
        $this->connection->restoreSnapshot();
    }

    public function releaseSavepoint(string $name): void
    {
        if ($this->level === 0) {
            throw TransactionException::notActive();
        }
        $this->connection->discardSnapshot();
    }

    public function setIsolationLevel(TransactionIsolation $level): void
    {
        if ($this->level > 0) {
            throw new TransactionException(
                'Cannot change isolation level during an active transaction'
            );
        }
        $this->isolationLevel = $level;
    }

    public function getIsolationLevel(): TransactionIsolation
    {
        return $this->isolationLevel;
    }

    public function afterCommit(callable $callback): void
    {
        $this->afterCommitCallbacks[] = $callback;
    }

    public function afterRollback(callable $callback): void
    {
        $this->afterRollbackCallbacks[] = $callback;
    }

    /**
     * @param array<callable(): void> $callbacks
     */
    private function executeCallbacks(array $callbacks): void
    {
        foreach ($callbacks as $callback) {
            try {
                $callback();
            } catch (\Throwable $e) {
                // Log but don't throw
            }
        }
    }

    private function clearCallbacks(): void
    {
        $this->afterCommitCallbacks = [];
        $this->afterRollbackCallbacks = [];
    }
}
