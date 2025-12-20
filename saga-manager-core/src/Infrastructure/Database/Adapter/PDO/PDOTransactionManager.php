<?php
declare(strict_types=1);

namespace SagaManagerCore\Infrastructure\Database\Adapter\PDO;

use SagaManagerCore\Infrastructure\Database\Port\TransactionManagerInterface;
use SagaManagerCore\Infrastructure\Database\Port\TransactionIsolation;
use SagaManagerCore\Infrastructure\Database\Exception\TransactionException;

/**
 * PDO Transaction Manager Implementation
 *
 * Provides ACID transaction support for PDO connections.
 * Supports nested transactions via savepoints.
 *
 * @package SagaManagerCore\Infrastructure\Database\Adapter\PDO
 */
class PDOTransactionManager implements TransactionManagerInterface
{
    private PDOConnection $connection;
    private int $level = 0;
    /** @var array<int, string> */
    private array $savepointNames = [];
    /** @var array<callable(): void> */
    private array $afterCommitCallbacks = [];
    /** @var array<callable(): void> */
    private array $afterRollbackCallbacks = [];
    private TransactionIsolation $isolationLevel;

    public function __construct(PDOConnection $connection)
    {
        $this->connection = $connection;
        $this->isolationLevel = TransactionIsolation::REPEATABLE_READ;
    }

    public function begin(): void
    {
        $pdo = $this->connection->getPdo();

        if ($this->level === 0) {
            try {
                $pdo->beginTransaction();
                $this->level = 1;
            } catch (\PDOException $e) {
                throw TransactionException::beginFailed($e->getMessage(), $e);
            }
        } else {
            $savepointName = $this->generateSavepointName();
            $this->savepointNames[$this->level] = $savepointName;
            $this->savepoint($savepointName);
            $this->level++;
        }
    }

    public function commit(): void
    {
        if ($this->level === 0) {
            throw TransactionException::notActive();
        }

        $pdo = $this->connection->getPdo();

        if ($this->level === 1) {
            try {
                $pdo->commit();
                $this->level = 0;
                $this->savepointNames = [];
                $this->executeCallbacks($this->afterCommitCallbacks);
                $this->clearCallbacks();
            } catch (\PDOException $e) {
                throw TransactionException::commitFailed($e->getMessage(), $e);
            }
        } else {
            $savepointName = $this->savepointNames[$this->level - 1] ?? null;
            if ($savepointName !== null) {
                $this->releaseSavepoint($savepointName);
                unset($this->savepointNames[$this->level - 1]);
            }
            $this->level--;
        }
    }

    public function rollback(): void
    {
        if ($this->level === 0) {
            return;
        }

        $pdo = $this->connection->getPdo();

        if ($this->level === 1) {
            try {
                $pdo->rollBack();
            } catch (\PDOException $e) {
                error_log('[SAGA][TX][ERROR] Rollback failed: ' . $e->getMessage());
            }

            $this->level = 0;
            $this->savepointNames = [];
            $this->executeCallbacks($this->afterRollbackCallbacks);
            $this->clearCallbacks();
        } else {
            $savepointName = $this->savepointNames[$this->level - 1] ?? null;
            if ($savepointName !== null) {
                $this->rollbackTo($savepointName);
                unset($this->savepointNames[$this->level - 1]);
            }
            $this->level--;
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
        $attempts = 0;
        $lastException = null;

        while ($attempts < $maxAttempts) {
            $attempts++;

            try {
                return $this->run($callback);
            } catch (\Throwable $e) {
                $lastException = $e;

                if (!$this->isRetryableException($e) || $attempts >= $maxAttempts) {
                    throw $e;
                }

                usleep($retryDelayMs * 1000);
                $retryDelayMs *= 2; // Exponential backoff
            }
        }

        throw $lastException ?? TransactionException::deadlock($maxAttempts);
    }

    public function savepoint(string $name): void
    {
        if ($this->level === 0) {
            throw TransactionException::notActive();
        }

        try {
            $this->connection->getPdo()->exec("SAVEPOINT {$name}");
        } catch (\PDOException $e) {
            throw TransactionException::savepointFailed($name, $e->getMessage());
        }
    }

    public function rollbackTo(string $name): void
    {
        if ($this->level === 0) {
            throw TransactionException::notActive();
        }

        try {
            $this->connection->getPdo()->exec("ROLLBACK TO SAVEPOINT {$name}");
        } catch (\PDOException $e) {
            throw TransactionException::savepointRollbackFailed($name, $e->getMessage());
        }
    }

    public function releaseSavepoint(string $name): void
    {
        if ($this->level === 0) {
            throw TransactionException::notActive();
        }

        try {
            $this->connection->getPdo()->exec("RELEASE SAVEPOINT {$name}");
        } catch (\PDOException $e) {
            throw TransactionException::savepointReleaseFailed($name, $e->getMessage());
        }
    }

    public function setIsolationLevel(TransactionIsolation $level): void
    {
        if ($this->level > 0) {
            throw new TransactionException(
                'Cannot change isolation level during an active transaction'
            );
        }

        try {
            $this->connection->getPdo()->exec($level->toSql());
            $this->isolationLevel = $level;
        } catch (\PDOException $e) {
            throw new TransactionException(
                'Failed to set transaction isolation level: ' . $e->getMessage()
            );
        }
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

    private function generateSavepointName(): string
    {
        return 'saga_sp_' . $this->level . '_' . uniqid();
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
                error_log('[SAGA][TX][ERROR] Callback failed: ' . $e->getMessage());
            }
        }
    }

    private function clearCallbacks(): void
    {
        $this->afterCommitCallbacks = [];
        $this->afterRollbackCallbacks = [];
    }

    private function isRetryableException(\Throwable $e): bool
    {
        $message = strtolower($e->getMessage());
        return str_contains($message, 'deadlock')
            || str_contains($message, 'lock wait timeout')
            || str_contains($message, 'try restarting transaction');
    }
}
