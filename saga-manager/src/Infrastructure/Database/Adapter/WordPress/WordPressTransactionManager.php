<?php

declare(strict_types=1);

namespace SagaManager\Infrastructure\Database\Adapter\WordPress;

use SagaManager\Infrastructure\Database\Contract\TransactionInterface;
use SagaManager\Infrastructure\Database\Contract\DatabaseInterface;
use SagaManager\Domain\Exception\DatabaseException;

/**
 * WordPress Transaction Manager
 *
 * Manages database transactions using WordPress wpdb.
 * Supports nested transactions via savepoints.
 *
 * @example
 *   $db->transaction()->begin();
 *   try {
 *       $db->insert('entities', $data);
 *       $db->transaction()->commit();
 *   } catch (Exception $e) {
 *       $db->transaction()->rollback();
 *       throw $e;
 *   }
 */
final class WordPressTransactionManager implements TransactionInterface
{
    private int $level = 0;

    /** @var array<string> */
    private array $savepoints = [];

    public function __construct(
        private readonly \wpdb $wpdb,
        private readonly DatabaseInterface $database,
    ) {}

    public function begin(): void
    {
        if ($this->level === 0) {
            $result = $this->wpdb->query('START TRANSACTION');
            if ($result === false) {
                throw new DatabaseException(
                    'Failed to begin transaction: ' . $this->wpdb->last_error
                );
            }
        } else {
            // Nested transaction - use savepoint
            $savepoint = $this->generateSavepointName();
            $result = $this->wpdb->query("SAVEPOINT {$savepoint}");
            if ($result === false) {
                throw new DatabaseException(
                    'Failed to create savepoint: ' . $this->wpdb->last_error
                );
            }
            $this->savepoints[] = $savepoint;
        }

        $this->level++;
    }

    public function commit(): void
    {
        if ($this->level === 0) {
            throw new DatabaseException('No active transaction to commit');
        }

        if ($this->level === 1) {
            $result = $this->wpdb->query('COMMIT');
            if ($result === false) {
                throw new DatabaseException(
                    'Failed to commit transaction: ' . $this->wpdb->last_error
                );
            }
        } else {
            // Release nested savepoint
            $savepoint = array_pop($this->savepoints);
            if ($savepoint !== null) {
                $this->wpdb->query("RELEASE SAVEPOINT {$savepoint}");
            }
        }

        $this->level--;
    }

    public function rollback(): void
    {
        if ($this->level === 0) {
            throw new DatabaseException('No active transaction to rollback');
        }

        if ($this->level === 1) {
            $result = $this->wpdb->query('ROLLBACK');
            if ($result === false) {
                throw new DatabaseException(
                    'Failed to rollback transaction: ' . $this->wpdb->last_error
                );
            }
            $this->savepoints = [];
        } else {
            // Rollback to nested savepoint
            $savepoint = array_pop($this->savepoints);
            if ($savepoint !== null) {
                $this->wpdb->query("ROLLBACK TO SAVEPOINT {$savepoint}");
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
        $result = $this->wpdb->query("SAVEPOINT {$safeName}");
        if ($result === false) {
            throw new DatabaseException(
                'Failed to create savepoint: ' . $this->wpdb->last_error
            );
        }
        $this->savepoints[] = $safeName;
    }

    public function rollbackTo(string $name): void
    {
        $safeName = $this->sanitizeSavepointName($name);

        if (!in_array($safeName, $this->savepoints, true)) {
            throw new DatabaseException("Savepoint '{$name}' does not exist");
        }

        $result = $this->wpdb->query("ROLLBACK TO SAVEPOINT {$safeName}");
        if ($result === false) {
            throw new DatabaseException(
                'Failed to rollback to savepoint: ' . $this->wpdb->last_error
            );
        }

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
            return;
        }

        $this->wpdb->query("RELEASE SAVEPOINT {$safeName}");

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
