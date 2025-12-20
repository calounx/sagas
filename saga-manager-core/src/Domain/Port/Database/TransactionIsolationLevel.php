<?php
declare(strict_types=1);

namespace SagaManagerCore\Domain\Port\Database;

/**
 * Enum representing SQL transaction isolation levels
 *
 * Defines the degree of isolation between concurrent transactions.
 * Higher isolation = more consistency but less concurrency.
 *
 * @see https://dev.mysql.com/doc/refman/8.0/en/innodb-transaction-isolation-levels.html
 *
 * @example
 * ```php
 * // For read-heavy operations with acceptable phantom reads
 * $tx->setIsolationLevel(TransactionIsolationLevel::READ_COMMITTED);
 * $tx->beginTransaction();
 *
 * // For operations requiring snapshot consistency
 * $tx->setIsolationLevel(TransactionIsolationLevel::REPEATABLE_READ);
 * $tx->beginTransaction();
 *
 * // For critical operations requiring full serialization
 * $tx->setIsolationLevel(TransactionIsolationLevel::SERIALIZABLE);
 * $tx->beginTransaction();
 * ```
 */
enum TransactionIsolationLevel: string
{
    /**
     * Lowest isolation. Transactions see uncommitted changes from others.
     *
     * Allows: Dirty reads, Non-repeatable reads, Phantom reads
     * Use case: Rarely appropriate; use for non-critical read operations
     */
    case READ_UNCOMMITTED = 'READ UNCOMMITTED';

    /**
     * Transactions only see committed changes from others.
     *
     * Prevents: Dirty reads
     * Allows: Non-repeatable reads, Phantom reads
     * Use case: Most read operations where point-in-time consistency isn't required
     */
    case READ_COMMITTED = 'READ COMMITTED';

    /**
     * Transactions see a consistent snapshot from start.
     * Default for InnoDB.
     *
     * Prevents: Dirty reads, Non-repeatable reads
     * Allows: Phantom reads (but InnoDB's gap locking often prevents these too)
     * Use case: Default for most write operations
     */
    case REPEATABLE_READ = 'REPEATABLE READ';

    /**
     * Highest isolation. Transactions execute as if serialized.
     *
     * Prevents: Dirty reads, Non-repeatable reads, Phantom reads
     * Use case: Critical operations requiring absolute consistency (rare)
     * Warning: Can cause significant lock contention
     */
    case SERIALIZABLE = 'SERIALIZABLE';

    /**
     * Get the SQL statement to set this isolation level
     */
    public function toSql(): string
    {
        return "SET TRANSACTION ISOLATION LEVEL {$this->value}";
    }

    /**
     * Check if this level prevents dirty reads
     */
    public function preventsDirtyReads(): bool
    {
        return $this !== self::READ_UNCOMMITTED;
    }

    /**
     * Check if this level prevents non-repeatable reads
     */
    public function preventsNonRepeatableReads(): bool
    {
        return match ($this) {
            self::REPEATABLE_READ, self::SERIALIZABLE => true,
            default => false,
        };
    }

    /**
     * Check if this level prevents phantom reads
     */
    public function preventsPhantomReads(): bool
    {
        return $this === self::SERIALIZABLE;
    }

    /**
     * Get relative concurrency impact (1-4, higher = more impact)
     */
    public function getConcurrencyImpact(): int
    {
        return match ($this) {
            self::READ_UNCOMMITTED => 1,
            self::READ_COMMITTED => 2,
            self::REPEATABLE_READ => 3,
            self::SERIALIZABLE => 4,
        };
    }
}
