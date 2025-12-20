<?php

declare(strict_types=1);

namespace SagaManagerCore\Infrastructure\Database\Adapter\WordPress;

use SagaManagerCore\Infrastructure\Database\Exception\TransactionException;
use SagaManagerCore\Infrastructure\Database\Port\TransactionIsolation;
use SagaManagerCore\Infrastructure\Database\Port\TransactionManagerInterface;

/**
 * WordPress Transaction Manager Implementation
 *
 * Provides ACID transaction support through WordPress wpdb.
 * Supports nested transactions via savepoints and automatic rollback on failure.
 *
 * IMPORTANT: MySQL/MariaDB does not support true nested transactions.
 * This implementation uses savepoints to simulate nesting.
 */
class WordPressTransactionManager implements TransactionManagerInterface
{
    private WordPressConnection $connection;

    /**
     * Current transaction nesting level (0 = no transaction)
     */
    private int $level = 0;

    /**
     * Auto-generated savepoint names for nested transactions
     *
     * @var array<int, string>
     */
    private array $savepointNames = [];

    /**
     * Callbacks to execute after commit
     *
     * @var array<callable(): void>
     */
    private array $afterCommitCallbacks = [];

    /**
     * Callbacks to execute after rollback
     *
     * @var array<callable(): void>
     */
    private array $afterRollbackCallbacks = [];

    /**
     * Current transaction isolation level
     */
    private TransactionIsolation $isolationLevel;

    public function __construct(WordPressConnection $connection)
    {
        $this->connection = $connection;
        $this->isolationLevel = TransactionIsolation::REPEATABLE_READ; // MariaDB default
    }

    /**
     * {@inheritdoc}
     */
    public function begin(): void
    {
        $wpdb = $this->connection->getWpdb();

        if ($this->level === 0) {
            // Start a real transaction
            $result = $wpdb->query('START TRANSACTION');

            if ($result === false) {
                throw TransactionException::beginFailed($wpdb->last_error, 0);
            }

            $this->level = 1;

            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('[SAGA][TX] Transaction started');
            }
        } else {
            // Create a savepoint for nested transaction
            $savepointName = $this->generateSavepointName();
            $this->savepointNames[$this->level] = $savepointName;
            $this->savepoint($savepointName);
            $this->level++;

            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log("[SAGA][TX] Savepoint created: {$savepointName} (level {$this->level})");
            }
        }
    }

    /**
     * {@inheritdoc}
     */
    public function commit(): void
    {
        if ($this->level === 0) {
            throw TransactionException::noActiveTransaction('commit');
        }

        $wpdb = $this->connection->getWpdb();

        if ($this->level === 1) {
            // Commit the real transaction
            $result = $wpdb->query('COMMIT');

            if ($result === false) {
                throw TransactionException::commitFailed($wpdb->last_error, $this->level);
            }

            $this->level = 0;
            $this->savepointNames = [];

            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('[SAGA][TX] Transaction committed');
            }

            // Execute after-commit callbacks
            $this->executeAfterCommitCallbacks();
        } else {
            // Release the savepoint (implicit commit for nested)
            $savepointName = $this->savepointNames[$this->level - 1] ?? null;

            if ($savepointName !== null) {
                $this->releaseSavepoint($savepointName);
                unset($this->savepointNames[$this->level - 1]);
            }

            $this->level--;

            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log("[SAGA][TX] Savepoint released (level {$this->level})");
            }
        }
    }

    /**
     * {@inheritdoc}
     */
    public function rollback(): void
    {
        if ($this->level === 0) {
            // No active transaction, nothing to rollback
            return;
        }

        $wpdb = $this->connection->getWpdb();

        if ($this->level === 1) {
            // Rollback the real transaction
            $result = $wpdb->query('ROLLBACK');

            if ($result === false) {
                error_log('[SAGA][TX][ERROR] Rollback failed: ' . $wpdb->last_error);
                // Don't throw - rollback should succeed even if there are issues
            }

            $this->level = 0;
            $this->savepointNames = [];

            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('[SAGA][TX] Transaction rolled back');
            }

            // Execute after-rollback callbacks
            $this->executeAfterRollbackCallbacks();
        } else {
            // Rollback to the savepoint
            $savepointName = $this->savepointNames[$this->level - 1] ?? null;

            if ($savepointName !== null) {
                $this->rollbackTo($savepointName);
                unset($this->savepointNames[$this->level - 1]);
            }

            $this->level--;

            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log("[SAGA][TX] Rolled back to savepoint (level {$this->level})");
            }
        }
    }

    /**
     * {@inheritdoc}
     */
    public function isActive(): bool
    {
        return $this->level > 0;
    }

    /**
     * {@inheritdoc}
     */
    public function getLevel(): int
    {
        return $this->level;
    }

    /**
     * {@inheritdoc}
     */
    public function run(callable $callback): mixed
    {
        $this->begin();

        try {
            $result = $callback($this);
            $this->commit();

            return $result;
        } catch (\Throwable $e) {
            $this->rollback();

            error_log('[SAGA][TX][ERROR] Transaction failed: ' . $e->getMessage());

            throw $e;
        }
    }

    /**
     * {@inheritdoc}
     */
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

                // Check if the error is retryable
                $isRetryable = false;

                if ($e instanceof TransactionException) {
                    $message = strtolower($e->getMessage());
                    $isRetryable = str_contains($message, 'deadlock')
                        || str_contains($message, 'lock wait timeout');
                }

                if (!$isRetryable || $attempts >= $maxAttempts) {
                    throw $e;
                }

                // Wait before retrying
                usleep($retryDelayMs * 1000);

                if (defined('WP_DEBUG') && WP_DEBUG) {
                    error_log(sprintf(
                        '[SAGA][TX] Retrying transaction (attempt %d/%d) after: %s',
                        $attempts + 1,
                        $maxAttempts,
                        $e->getMessage()
                    ));
                }
            }
        }

        // Should never reach here, but just in case
        throw $lastException ?? new TransactionException('Transaction failed after max attempts');
    }

    /**
     * {@inheritdoc}
     */
    public function savepoint(string $name): void
    {
        if ($this->level === 0) {
            throw TransactionException::noActiveTransaction('create savepoint');
        }

        $wpdb = $this->connection->getWpdb();
        $safeName = $this->sanitizeSavepointName($name);
        $result = $wpdb->query("SAVEPOINT {$safeName}");

        if ($result === false) {
            throw TransactionException::savepointFailed($name, 'creation');
        }
    }

    /**
     * {@inheritdoc}
     */
    public function rollbackTo(string $name): void
    {
        if ($this->level === 0) {
            throw TransactionException::noActiveTransaction('rollback to savepoint');
        }

        $wpdb = $this->connection->getWpdb();
        $safeName = $this->sanitizeSavepointName($name);
        $result = $wpdb->query("ROLLBACK TO SAVEPOINT {$safeName}");

        if ($result === false) {
            throw TransactionException::savepointFailed($name, 'rollback');
        }
    }

    /**
     * {@inheritdoc}
     */
    public function releaseSavepoint(string $name): void
    {
        if ($this->level === 0) {
            throw TransactionException::noActiveTransaction('release savepoint');
        }

        $wpdb = $this->connection->getWpdb();
        $safeName = $this->sanitizeSavepointName($name);
        $result = $wpdb->query("RELEASE SAVEPOINT {$safeName}");

        if ($result === false) {
            throw TransactionException::savepointFailed($name, 'release');
        }
    }

    /**
     * {@inheritdoc}
     */
    public function setIsolationLevel(TransactionIsolation $level): void
    {
        if ($this->level > 0) {
            throw new TransactionException(
                'Cannot change isolation level during an active transaction'
            );
        }

        $wpdb = $this->connection->getWpdb();
        $result = $wpdb->query($level->toSql());

        if ($result === false) {
            throw new TransactionException(
                'Failed to set transaction isolation level: ' . $wpdb->last_error
            );
        }

        $this->isolationLevel = $level;
    }

    /**
     * {@inheritdoc}
     */
    public function getIsolationLevel(): TransactionIsolation
    {
        return $this->isolationLevel;
    }

    /**
     * {@inheritdoc}
     */
    public function afterCommit(callable $callback): void
    {
        $this->afterCommitCallbacks[] = $callback;
    }

    /**
     * {@inheritdoc}
     */
    public function afterRollback(callable $callback): void
    {
        $this->afterRollbackCallbacks[] = $callback;
    }

    // =========================================================================
    // Private Helper Methods
    // =========================================================================

    /**
     * Generate a unique savepoint name
     */
    private function generateSavepointName(): string
    {
        return 'saga_sp_' . $this->level . '_' . uniqid();
    }

    /**
     * Sanitize savepoint name to prevent SQL injection
     */
    private function sanitizeSavepointName(string $name): string
    {
        // Only allow alphanumeric and underscore
        return preg_replace('/[^a-zA-Z0-9_]/', '', $name);
    }

    /**
     * Execute after-commit callbacks
     */
    private function executeAfterCommitCallbacks(): void
    {
        $callbacks = $this->afterCommitCallbacks;
        $this->afterCommitCallbacks = [];

        foreach ($callbacks as $callback) {
            try {
                $callback();
            } catch (\Throwable $e) {
                error_log('[SAGA][TX][ERROR] After-commit callback failed: ' . $e->getMessage());
            }
        }
    }

    /**
     * Execute after-rollback callbacks
     */
    private function executeAfterRollbackCallbacks(): void
    {
        $callbacks = $this->afterRollbackCallbacks;
        $this->afterRollbackCallbacks = [];

        foreach ($callbacks as $callback) {
            try {
                $callback();
            } catch (\Throwable $e) {
                error_log('[SAGA][TX][ERROR] After-rollback callback failed: ' . $e->getMessage());
            }
        }
    }
}
