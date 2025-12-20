<?php

declare(strict_types=1);

namespace SagaManagerCore\Infrastructure\Database\Exception;

use SagaManagerCore\Domain\Exception\DatabaseException;

/**
 * Exception thrown when transaction operations fail
 *
 * Covers begin, commit, rollback, and savepoint errors.
 */
class TransactionException extends DatabaseException
{
    private int $transactionLevel;
    private ?string $savepointName;

    public function __construct(
        string $message,
        int $transactionLevel = 0,
        ?string $savepointName = null,
        ?\Throwable $previous = null
    ) {
        parent::__construct($message, 0, $previous);
        $this->transactionLevel = $transactionLevel;
        $this->savepointName = $savepointName;
    }

    /**
     * Get the transaction nesting level when error occurred
     */
    public function getTransactionLevel(): int
    {
        return $this->transactionLevel;
    }

    /**
     * Get the savepoint name (if applicable)
     */
    public function getSavepointName(): ?string
    {
        return $this->savepointName;
    }

    /**
     * Create exception for nested transaction attempt without savepoint support
     */
    public static function nestedNotSupported(): self
    {
        return new self(
            'Nested transactions are not supported by this database driver'
        );
    }

    /**
     * Create exception for begin failure
     */
    public static function beginFailed(string $reason, int $level = 0): self
    {
        return new self(
            'Failed to begin transaction: ' . $reason,
            $level
        );
    }

    /**
     * Create exception for commit failure
     */
    public static function commitFailed(string $reason, int $level = 0): self
    {
        return new self(
            'Failed to commit transaction: ' . $reason,
            $level
        );
    }

    /**
     * Create exception for rollback failure
     */
    public static function rollbackFailed(string $reason, int $level = 0): self
    {
        return new self(
            'Failed to rollback transaction: ' . $reason,
            $level
        );
    }

    /**
     * Create exception for missing active transaction
     */
    public static function noActiveTransaction(string $operation): self
    {
        return new self(
            sprintf('Cannot %s: no active transaction', $operation)
        );
    }

    /**
     * Create exception for savepoint failure
     */
    public static function savepointFailed(string $name, string $operation): self
    {
        return new self(
            sprintf('Savepoint "%s" %s failed', $name, $operation),
            0,
            $name
        );
    }

    /**
     * Create exception for savepoint not found
     */
    public static function savepointNotFound(string $name): self
    {
        return new self(
            sprintf('Savepoint "%s" does not exist', $name),
            0,
            $name
        );
    }

    /**
     * Create exception for deadlock
     */
    public static function deadlockDetected(): self
    {
        return new self(
            'Deadlock detected, transaction rolled back'
        );
    }

    /**
     * Create exception for lock timeout
     */
    public static function lockTimeout(): self
    {
        return new self(
            'Lock wait timeout exceeded'
        );
    }
}
