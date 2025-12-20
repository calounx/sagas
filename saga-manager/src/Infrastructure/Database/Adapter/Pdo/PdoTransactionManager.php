<?php

declare(strict_types=1);

namespace SagaManager\Infrastructure\Database\Adapter\Pdo;

use SagaManager\Infrastructure\Database\Contract\TransactionInterface;
use SagaManager\Infrastructure\Database\Contract\DatabaseInterface;
use SagaManager\Domain\Exception\DatabaseException;

/**
 * PDO Transaction Manager
 *
 * Manages transactions for PDO connections with nested transaction support via savepoints.
 *
 * @example
 *   $tm = $db->transaction();
 *   $tm->begin();
 *   try {
 *       $db->insert('entities', $data);
 *       $tm->commit();
 *   } catch (Exception $e) {
 *       $tm->rollback();
 *       throw $e;
 *   }
 */
final class PdoTransactionManager implements TransactionInterface
{
    private int $level = 0;

    /** @var array<string> */
    private array $savepoints = [];

    public function __construct(
        private readonly PdoConnection $connection,
        private readonly DatabaseInterface $database,
    ) {}

    public function begin(): void
    {
        $pdo = $this->connection->getNativeConnection();

        if ($this->level === 0) {
            if (!$pdo->beginTransaction()) {
                throw new DatabaseException('Failed to begin transaction');
            }
        } else {
            // Nested transaction - use savepoint
            $savepoint = $this->generateSavepointName();
            $pdo->exec("SAVEPOINT {$savepoint}");
            $this->savepoints[] = $savepoint;
        }

        $this->level++;
    }

    public function commit(): void
    {
        if ($this->level === 0) {
            throw new DatabaseException('No active transaction to commit');
        }

        $pdo = $this->connection->getNativeConnection();

        if ($this->level === 1) {
            if (!$pdo->commit()) {
                throw new DatabaseException('Failed to commit transaction');
            }
        } else {
            // Release nested savepoint
            $savepoint = array_pop($this->savepoints);
            if ($savepoint !== null) {
                $pdo->exec("RELEASE SAVEPOINT {$savepoint}");
            }
        }

        $this->level--;
    }

    public function rollback(): void
    {
        if ($this->level === 0) {
            throw new DatabaseException('No active transaction to rollback');
        }

        $pdo = $this->connection->getNativeConnection();

        if ($this->level === 1) {
            if (!$pdo->rollBack()) {
                throw new DatabaseException('Failed to rollback transaction');
            }
            $this->savepoints = [];
        } else {
            // Rollback to nested savepoint
            $savepoint = array_pop($this->savepoints);
            if ($savepoint !== null) {
                $pdo->exec("ROLLBACK TO SAVEPOINT {$savepoint}");
            }
        }

        $this->level--;
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

        $safeName = $this->sanitizeSavepointName($name);
        $pdo = $this->connection->getNativeConnection();
        $pdo->exec("SAVEPOINT {$safeName}");
        $this->savepoints[] = $safeName;
    }

    public function rollbackTo(string $name): void
    {
        $safeName = $this->sanitizeSavepointName($name);

        if (!in_array($safeName, $this->savepoints, true)) {
            throw new DatabaseException("Savepoint '{$name}' does not exist");
        }

        $pdo = $this->connection->getNativeConnection();
        $pdo->exec("ROLLBACK TO SAVEPOINT {$safeName}");

        // Remove savepoints created after this one
        $index = array_search($safeName, $this->savepoints, true);
        if ($index !== false) {
            $this->savepoints = array_slice($this->savepoints, 0, $index + 1);
        }
    }

    public function releaseSavepoint(string $name): void
    {
        $safeName = $this->sanitizeSavepointName($name);

        if (!in_array($safeName, $this->savepoints, true)) {
            return; // Silently ignore if savepoint doesn't exist
        }

        $pdo = $this->connection->getNativeConnection();
        $pdo->exec("RELEASE SAVEPOINT {$safeName}");

        // Remove this savepoint and all created after it
        $index = array_search($safeName, $this->savepoints, true);
        if ($index !== false) {
            $this->savepoints = array_slice($this->savepoints, 0, $index);
        }
    }

    private function generateSavepointName(): string
    {
        return 'sp_' . uniqid();
    }

    private function sanitizeSavepointName(string $name): string
    {
        return preg_replace('/[^a-zA-Z0-9_]/', '', $name);
    }
}
