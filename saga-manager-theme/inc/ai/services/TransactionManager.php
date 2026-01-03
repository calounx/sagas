<?php
/**
 * Transaction Manager
 *
 * Centralized database transaction handling service.
 * Eliminates code duplication across repositories by providing
 * consistent transaction management with automatic rollback.
 *
 * @package SagaManager
 * @subpackage AI\Services
 * @since 1.5.0
 */

declare(strict_types=1);

namespace SagaManager\AI\Services;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Transaction Manager Service
 *
 * Single Responsibility Principle: Handles all transaction logic.
 * Used by repositories to DRY up repeated START/COMMIT/ROLLBACK patterns.
 *
 * @example
 * $transaction = new TransactionManager();
 * $result = $transaction->execute(function() use ($wpdb) {
 *     $wpdb->insert(...);
 *     $wpdb->insert(...);
 *     return $wpdb->insert_id;
 * });
 */
final class TransactionManager
{
    /**
     * @var \wpdb WordPress database object
     */
    private \wpdb $wpdb;

    /**
     * Constructor
     *
     * @param \wpdb|null $wpdb Optional wpdb instance (for testing)
     */
    public function __construct(?\wpdb $wpdb = null)
    {
        if ($wpdb === null) {
            global $wpdb;
        }
        $this->wpdb = $wpdb;
    }

    /**
     * Execute operation within transaction
     *
     * Automatically handles START TRANSACTION, COMMIT, and ROLLBACK.
     * On exception, rolls back and re-throws.
     *
     * @param callable $operation Operation to execute (receives no parameters)
     * @return mixed Result from operation
     * @throws \Exception If operation fails (after rollback)
     *
     * @example
     * $manager->execute(function() use ($wpdb, $data) {
     *     $wpdb->insert('table1', $data1);
     *     $wpdb->insert('table2', $data2);
     *     return $wpdb->insert_id;
     * });
     */
    public function execute(callable $operation): mixed
    {
        $this->wpdb->query('START TRANSACTION');

        try {
            $result = $operation();

            $this->wpdb->query('COMMIT');

            return $result;

        } catch (\Exception $e) {
            $this->wpdb->query('ROLLBACK');

            error_log(sprintf(
                '[SAGA][TRANSACTION][ERROR] Transaction rolled back: %s',
                $e->getMessage()
            ));

            throw $e;
        }
    }

    /**
     * Execute operation within transaction with context logging
     *
     * Same as execute() but logs context for debugging.
     *
     * @param callable $operation Operation to execute
     * @param string $context Context description (e.g., "Create summary with attributes")
     * @return mixed Result from operation
     * @throws \Exception If operation fails (after rollback)
     *
     * @example
     * $manager->executeWithContext(
     *     function() { ... },
     *     'Batch create suggestions with features'
     * );
     */
    public function executeWithContext(callable $operation, string $context): mixed
    {
        $this->wpdb->query('START TRANSACTION');

        try {
            $result = $operation();

            $this->wpdb->query('COMMIT');

            error_log(sprintf(
                '[SAGA][TRANSACTION] Committed: %s',
                $context
            ));

            return $result;

        } catch (\Exception $e) {
            $this->wpdb->query('ROLLBACK');

            error_log(sprintf(
                '[SAGA][TRANSACTION][ERROR] Rolled back [%s]: %s',
                $context,
                $e->getMessage()
            ));

            throw $e;
        }
    }

    /**
     * Check if database supports transactions
     *
     * Verifies table engine is InnoDB (or compatible).
     *
     * @param string $table_name Table name with prefix
     * @return bool True if transactional
     */
    public function supportsTransactions(string $table_name): bool
    {
        $engine = $this->wpdb->get_var($this->wpdb->prepare(
            "SELECT ENGINE FROM information_schema.TABLES
             WHERE TABLE_SCHEMA = %s AND TABLE_NAME = %s",
            DB_NAME,
            $table_name
        ));

        // InnoDB supports transactions, MyISAM does not
        return strtolower($engine) === 'innodb';
    }

    /**
     * Execute operation with retry logic
     *
     * Retries operation on deadlock or lock wait timeout.
     *
     * @param callable $operation Operation to execute
     * @param int $max_retries Maximum retry attempts
     * @param int $retry_delay_ms Delay between retries (milliseconds)
     * @return mixed Result from operation
     * @throws \Exception If all retries fail
     *
     * @example
     * $manager->executeWithRetry(function() { ... }, 3, 100);
     */
    public function executeWithRetry(
        callable $operation,
        int $max_retries = 3,
        int $retry_delay_ms = 100
    ): mixed {
        $attempts = 0;
        $last_exception = null;

        while ($attempts < $max_retries) {
            try {
                return $this->execute($operation);

            } catch (\Exception $e) {
                $last_exception = $e;
                $attempts++;

                // Check if it's a retryable error (deadlock or lock timeout)
                $error_code = $this->wpdb->last_error;
                $is_retryable = (
                    str_contains($error_code, 'Deadlock') ||
                    str_contains($error_code, 'Lock wait timeout')
                );

                if (!$is_retryable || $attempts >= $max_retries) {
                    break;
                }

                // Exponential backoff
                $delay = $retry_delay_ms * (2 ** ($attempts - 1));
                usleep($delay * 1000);

                error_log(sprintf(
                    '[SAGA][TRANSACTION] Retrying transaction (attempt %d/%d)',
                    $attempts,
                    $max_retries
                ));
            }
        }

        // All retries failed
        throw new \Exception(
            sprintf(
                'Transaction failed after %d attempts: %s',
                $attempts,
                $last_exception->getMessage()
            ),
            0,
            $last_exception
        );
    }

    /**
     * Begin manual transaction
     *
     * Use only when you need fine-grained control.
     * Prefer execute() for most cases.
     *
     * @return void
     */
    public function begin(): void
    {
        $this->wpdb->query('START TRANSACTION');
    }

    /**
     * Commit manual transaction
     *
     * @return void
     */
    public function commit(): void
    {
        $this->wpdb->query('COMMIT');
    }

    /**
     * Rollback manual transaction
     *
     * @return void
     */
    public function rollback(): void
    {
        $this->wpdb->query('ROLLBACK');
    }
}
