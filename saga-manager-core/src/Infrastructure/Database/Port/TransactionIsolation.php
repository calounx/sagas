<?php
declare(strict_types=1);

namespace SagaManagerCore\Infrastructure\Database\Port;

/**
 * Transaction Isolation Levels
 *
 * Defines standard SQL transaction isolation levels.
 * Not all databases support all levels.
 *
 * @package SagaManagerCore\Infrastructure\Database\Port
 */
enum TransactionIsolation: string
{
    /**
     * Read Uncommitted - Allows dirty reads
     *
     * Lowest isolation, highest concurrency.
     * Transactions can see uncommitted changes from other transactions.
     */
    case READ_UNCOMMITTED = 'READ UNCOMMITTED';

    /**
     * Read Committed - Prevents dirty reads
     *
     * Default for many databases (PostgreSQL, Oracle).
     * Only sees committed data, but non-repeatable reads possible.
     */
    case READ_COMMITTED = 'READ COMMITTED';

    /**
     * Repeatable Read - Prevents dirty and non-repeatable reads
     *
     * Default for MySQL/MariaDB InnoDB.
     * Same query returns same results within a transaction.
     * Phantom reads still possible in some implementations.
     */
    case REPEATABLE_READ = 'REPEATABLE READ';

    /**
     * Serializable - Full isolation
     *
     * Highest isolation, lowest concurrency.
     * Transactions are completely isolated as if executed serially.
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
     * Get recommended level for read-heavy operations
     */
    public static function forReadOperations(): self
    {
        return self::READ_COMMITTED;
    }

    /**
     * Get recommended level for write operations requiring consistency
     */
    public static function forWriteOperations(): self
    {
        return self::REPEATABLE_READ;
    }

    /**
     * Get recommended level for critical financial/inventory operations
     */
    public static function forCriticalOperations(): self
    {
        return self::SERIALIZABLE;
    }
}
