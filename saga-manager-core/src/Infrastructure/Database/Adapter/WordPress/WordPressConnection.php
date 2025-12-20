<?php

declare(strict_types=1);

namespace SagaManagerCore\Infrastructure\Database\Adapter\WordPress;

use SagaManagerCore\Infrastructure\Database\Exception\ConnectionException;
use SagaManagerCore\Infrastructure\Database\Exception\QueryException;
use SagaManagerCore\Infrastructure\Database\Port\DatabaseConnectionInterface;
use SagaManagerCore\Infrastructure\Database\Port\QueryBuilderInterface;
use SagaManagerCore\Infrastructure\Database\Port\ResultSetInterface;
use SagaManagerCore\Infrastructure\Database\Port\SchemaManagerInterface;
use SagaManagerCore\Infrastructure\Database\Port\TransactionManagerInterface;

/**
 * WordPress Database Connection Implementation
 *
 * Wraps the global $wpdb object to provide a clean abstraction layer
 * for database operations following hexagonal architecture principles.
 *
 * IMPORTANT: This is the ONLY place where global $wpdb should be accessed.
 */
class WordPressConnection implements DatabaseConnectionInterface
{
    private const DRIVER_NAME = 'wordpress';
    private const SAGA_PREFIX = 'saga_';

    private ?\wpdb $wpdb = null;
    private bool $queryLogEnabled = false;

    /**
     * @var array<array{query: string, bindings: array<mixed>, time: float}>
     */
    private array $queryLog = [];

    private ?WordPressTransactionManager $transactionManager = null;
    private ?WordPressSchemaManager $schemaManager = null;

    /**
     * Lazy initialization - wpdb is accessed only when needed
     */
    public function __construct()
    {
        // Connection is lazy - initialized on first use
    }

    /**
     * {@inheritdoc}
     */
    public function connect(): void
    {
        $this->getWpdb();
    }

    /**
     * {@inheritdoc}
     */
    public function disconnect(): void
    {
        // WordPress manages connection lifecycle
        // We just clear our reference
        $this->wpdb = null;
        $this->transactionManager = null;
        $this->schemaManager = null;
    }

    /**
     * {@inheritdoc}
     */
    public function isConnected(): bool
    {
        try {
            $wpdb = $this->getWpdb();
            return $wpdb->check_connection(false);
        } catch (ConnectionException) {
            return false;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function ping(): bool
    {
        try {
            $wpdb = $this->getWpdb();

            // Use db_connect with reconnect flag to verify connection
            if (!$wpdb->check_connection(false)) {
                // Try to reconnect
                return $wpdb->db_connect(true);
            }

            return true;
        } catch (\Exception $e) {
            throw ConnectionException::fromWpdb($e->getMessage());
        }
    }

    /**
     * {@inheritdoc}
     */
    public function query(): QueryBuilderInterface
    {
        return new WordPressQueryBuilder($this);
    }

    /**
     * {@inheritdoc}
     */
    public function transaction(): TransactionManagerInterface
    {
        if ($this->transactionManager === null) {
            $this->transactionManager = new WordPressTransactionManager($this);
        }

        return $this->transactionManager;
    }

    /**
     * {@inheritdoc}
     */
    public function schema(): SchemaManagerInterface
    {
        if ($this->schemaManager === null) {
            $this->schemaManager = new WordPressSchemaManager($this);
        }

        return $this->schemaManager;
    }

    /**
     * {@inheritdoc}
     */
    public function getDriverName(): string
    {
        return self::DRIVER_NAME;
    }

    /**
     * {@inheritdoc}
     */
    public function getDatabaseName(): string
    {
        return $this->getWpdb()->dbname;
    }

    /**
     * {@inheritdoc}
     */
    public function getTablePrefix(): string
    {
        return $this->getWpdb()->prefix;
    }

    /**
     * {@inheritdoc}
     */
    public function getSagaTablePrefix(): string
    {
        return $this->getTablePrefix() . self::SAGA_PREFIX;
    }

    /**
     * {@inheritdoc}
     */
    public function getFullTableName(string $tableName): string
    {
        return $this->getSagaTablePrefix() . $tableName;
    }

    /**
     * {@inheritdoc}
     */
    public function raw(string $sql, array $bindings = []): ResultSetInterface
    {
        $wpdb = $this->getWpdb();
        $startTime = microtime(true);

        // Prepare the query if bindings are provided
        if (!empty($bindings)) {
            $sql = $this->prepareQuery($sql, $bindings);
        }

        // Determine query type
        $queryType = $this->getQueryType($sql);

        // Execute based on query type
        if ($queryType === 'SELECT') {
            $results = $wpdb->get_results($sql, ARRAY_A);
            $affectedRows = $wpdb->num_rows;
            $lastInsertId = 0;
        } else {
            $results = null;
            $affectedRows = $wpdb->query($sql);
            $lastInsertId = (int) $wpdb->insert_id;

            if ($affectedRows === false) {
                $this->logQuery($sql, $bindings, microtime(true) - $startTime);
                throw QueryException::fromWpdb($wpdb->last_error, $sql);
            }
        }

        $duration = microtime(true) - $startTime;
        $this->logQuery($sql, $bindings, $duration);

        // Check for errors on SELECT
        if ($queryType === 'SELECT' && $wpdb->last_error !== '') {
            throw QueryException::fromWpdb($wpdb->last_error, $sql);
        }

        return new WordPressResultSet(
            $results,
            is_int($affectedRows) ? $affectedRows : 0,
            $lastInsertId
        );
    }

    /**
     * Execute a statement and return affected rows
     *
     * @param string $sql SQL query with placeholders
     * @param array<mixed> $bindings Parameter bindings
     * @return int Number of affected rows
     * @throws QueryException
     */
    public function statement(string $sql, array $bindings = []): int
    {
        $result = $this->raw($sql, $bindings);

        return $result->getAffectedRows();
    }

    /**
     * {@inheritdoc}
     */
    public function lastInsertId(): int
    {
        return (int) $this->getWpdb()->insert_id;
    }

    /**
     * {@inheritdoc}
     */
    public function affectedRows(): int
    {
        return (int) $this->getWpdb()->rows_affected;
    }

    /**
     * {@inheritdoc}
     */
    public function getLastError(): ?string
    {
        $error = $this->getWpdb()->last_error;

        return $error !== '' ? $error : null;
    }

    /**
     * {@inheritdoc}
     */
    public function enableQueryLog(): void
    {
        $this->queryLogEnabled = true;
    }

    /**
     * {@inheritdoc}
     */
    public function disableQueryLog(): void
    {
        $this->queryLogEnabled = false;
    }

    /**
     * {@inheritdoc}
     */
    public function getQueryLog(): array
    {
        return $this->queryLog;
    }

    /**
     * {@inheritdoc}
     */
    public function clearQueryLog(): void
    {
        $this->queryLog = [];
    }

    // =========================================================================
    // Additional Helper Methods
    // =========================================================================

    /**
     * Get the underlying wpdb instance
     *
     * @internal Should only be used by other adapter classes
     * @throws ConnectionException
     */
    public function getWpdb(): \wpdb
    {
        if ($this->wpdb === null) {
            global $wpdb;

            if (!$wpdb instanceof \wpdb) {
                throw ConnectionException::fromWpdb('WordPress database not available');
            }

            $this->wpdb = $wpdb;
        }

        return $this->wpdb;
    }

    /**
     * Get the character set for the database
     */
    public function getCharset(): string
    {
        return $this->getWpdb()->charset;
    }

    /**
     * Get the collation for the database
     */
    public function getCollate(): string
    {
        return $this->getWpdb()->collate;
    }

    /**
     * Get the character set and collation string for CREATE TABLE
     */
    public function getCharsetCollate(): string
    {
        return $this->getWpdb()->get_charset_collate();
    }

    /**
     * Check if running in multisite mode
     */
    public function isMultisite(): bool
    {
        return is_multisite();
    }

    /**
     * Get the base prefix for multisite
     */
    public function getBasePrefix(): string
    {
        return $this->getWpdb()->base_prefix;
    }

    /**
     * Get database server info
     *
     * @return array{
     *     database: string,
     *     host: string,
     *     version: string,
     *     charset: string,
     *     collate: string,
     *     prefix: string,
     *     saga_prefix: string,
     *     is_multisite: bool
     * }
     */
    public function getInfo(): array
    {
        $wpdb = $this->getWpdb();

        return [
            'database' => $wpdb->dbname,
            'host' => $wpdb->dbhost ?? 'unknown',
            'version' => $wpdb->db_version(),
            'charset' => $wpdb->charset,
            'collate' => $wpdb->collate,
            'prefix' => $wpdb->prefix,
            'saga_prefix' => $this->getSagaTablePrefix(),
            'is_multisite' => $this->isMultisite(),
        ];
    }

    /**
     * Escape a string for use in SQL
     *
     * @param string $value Value to escape
     * @return string Escaped value
     */
    public function escape(string $value): string
    {
        return $this->getWpdb()->_real_escape($value);
    }

    /**
     * Escape a LIKE pattern
     *
     * @param string $pattern Pattern to escape
     * @return string Escaped pattern
     */
    public function escapeLike(string $pattern): string
    {
        return $this->getWpdb()->esc_like($pattern);
    }

    /**
     * Prepare a query with bound parameters
     *
     * Uses wpdb->prepare() for secure parameter binding.
     *
     * @param string $sql SQL with placeholders (%s, %d, %f)
     * @param array<mixed> $bindings Values to bind
     * @return string Prepared SQL
     */
    public function prepareQuery(string $sql, array $bindings): string
    {
        if (empty($bindings)) {
            return $sql;
        }

        $wpdb = $this->getWpdb();

        // Convert positional bindings to proper format
        $prepared = $wpdb->prepare($sql, ...$bindings);

        if ($prepared === null) {
            // Fallback for edge cases
            return $sql;
        }

        return $prepared;
    }

    /**
     * Insert a row
     *
     * @param string $table Table name (without prefix)
     * @param array<string, mixed> $data Column => value pairs
     * @param array<string>|null $format Format array (%s, %d, %f)
     * @return int Insert ID
     * @throws QueryException
     */
    public function insert(string $table, array $data, ?array $format = null): int
    {
        $wpdb = $this->getWpdb();
        $fullTable = $this->getFullTableName($table);
        $startTime = microtime(true);

        $result = $wpdb->insert($fullTable, $data, $format);

        $this->logQuery(
            "INSERT INTO {$fullTable}",
            $data,
            microtime(true) - $startTime
        );

        if ($result === false) {
            throw QueryException::fromWpdb($wpdb->last_error);
        }

        return (int) $wpdb->insert_id;
    }

    /**
     * Update rows
     *
     * @param string $table Table name (without prefix)
     * @param array<string, mixed> $data Column => value pairs
     * @param array<string, mixed> $where WHERE conditions
     * @param array<string>|null $format Format array for data
     * @param array<string>|null $whereFormat Format array for where
     * @return int Number of rows updated
     * @throws QueryException
     */
    public function update(
        string $table,
        array $data,
        array $where,
        ?array $format = null,
        ?array $whereFormat = null
    ): int {
        $wpdb = $this->getWpdb();
        $fullTable = $this->getFullTableName($table);
        $startTime = microtime(true);

        $result = $wpdb->update($fullTable, $data, $where, $format, $whereFormat);

        $this->logQuery(
            "UPDATE {$fullTable}",
            array_merge($data, $where),
            microtime(true) - $startTime
        );

        if ($result === false) {
            throw QueryException::fromWpdb($wpdb->last_error);
        }

        return $result;
    }

    /**
     * Delete rows
     *
     * @param string $table Table name (without prefix)
     * @param array<string, mixed> $where WHERE conditions
     * @param array<string>|null $whereFormat Format array
     * @return int Number of rows deleted
     * @throws QueryException
     */
    public function delete(string $table, array $where, ?array $whereFormat = null): int
    {
        $wpdb = $this->getWpdb();
        $fullTable = $this->getFullTableName($table);
        $startTime = microtime(true);

        $result = $wpdb->delete($fullTable, $where, $whereFormat);

        $this->logQuery(
            "DELETE FROM {$fullTable}",
            $where,
            microtime(true) - $startTime
        );

        if ($result === false) {
            throw QueryException::fromWpdb($wpdb->last_error);
        }

        return $result;
    }

    // =========================================================================
    // Private Helper Methods
    // =========================================================================

    /**
     * Get the query type from SQL
     */
    private function getQueryType(string $sql): string
    {
        $sql = ltrim($sql);
        $firstWord = strtoupper(strtok($sql, " \t\n\r"));

        return match ($firstWord) {
            'SELECT', 'SHOW', 'DESCRIBE', 'EXPLAIN' => 'SELECT',
            'INSERT' => 'INSERT',
            'UPDATE' => 'UPDATE',
            'DELETE' => 'DELETE',
            default => 'OTHER',
        };
    }

    /**
     * Log a query if logging is enabled
     *
     * @param string $sql SQL query
     * @param array<mixed> $bindings Parameter bindings
     * @param float $time Query time in seconds
     */
    private function logQuery(string $sql, array $bindings, float $time): void
    {
        $timeMs = $time * 1000;

        if ($this->queryLogEnabled) {
            $this->queryLog[] = [
                'query' => $sql,
                'bindings' => $bindings,
                'time' => $timeMs,
            ];
        }

        // Log slow queries (>50ms target from CLAUDE.md)
        if ($timeMs > 50 && defined('WP_DEBUG') && WP_DEBUG) {
            error_log(sprintf(
                '[SAGA][PERF] Slow query (%.2fms): %s',
                $timeMs,
                substr($sql, 0, 200)
            ));
        }
    }
}
