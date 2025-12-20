<?php
declare(strict_types=1);

namespace SagaManagerCore\Infrastructure\Database\Adapter;

use SagaManagerCore\Infrastructure\Database\Port\DatabaseConnectionInterface;
use SagaManagerCore\Infrastructure\Database\Port\QueryBuilderInterface;
use SagaManagerCore\Infrastructure\Database\Port\TransactionManagerInterface;
use SagaManagerCore\Infrastructure\Database\Port\SchemaManagerInterface;
use SagaManagerCore\Infrastructure\Database\Port\ResultSetInterface;

/**
 * Abstract Database Adapter
 *
 * Base class for all database adapters providing common functionality
 * like query logging, table prefix handling, and shared configuration.
 *
 * @package SagaManagerCore\Infrastructure\Database\Adapter
 */
abstract class AbstractDatabaseAdapter implements DatabaseConnectionInterface
{
    protected const SAGA_TABLE_PREFIX = 'saga_';

    protected string $tablePrefix = '';
    protected bool $queryLoggingEnabled = false;

    /** @var array<array{query: string, bindings: array<mixed>, time: float}> */
    protected array $queryLog = [];

    protected bool $connected = false;

    /**
     * Get the saga-specific table prefix (e.g., 'wp_saga_')
     */
    public function getSagaTablePrefix(): string
    {
        return $this->tablePrefix . self::SAGA_TABLE_PREFIX;
    }

    /**
     * Get a full table name with all prefixes applied
     *
     * @param string $tableName Base table name without prefixes (e.g., 'entities')
     * @return string Full table name (e.g., 'wp_saga_entities')
     */
    public function getFullTableName(string $tableName): string
    {
        return $this->getSagaTablePrefix() . $tableName;
    }

    /**
     * Get table prefix configured for this connection
     */
    public function getTablePrefix(): string
    {
        return $this->tablePrefix;
    }

    /**
     * Check if connection is active and healthy
     */
    public function isConnected(): bool
    {
        return $this->connected;
    }

    /**
     * Enable query logging for debugging
     */
    public function enableQueryLog(): void
    {
        $this->queryLoggingEnabled = true;
    }

    /**
     * Disable query logging
     */
    public function disableQueryLog(): void
    {
        $this->queryLoggingEnabled = false;
    }

    /**
     * Get logged queries (only available when logging is enabled)
     *
     * @return array<array{query: string, bindings: array<mixed>, time: float}>
     */
    public function getQueryLog(): array
    {
        return $this->queryLog;
    }

    /**
     * Clear the query log
     */
    public function clearQueryLog(): void
    {
        $this->queryLog = [];
    }

    /**
     * Log a query execution
     *
     * @param string $query The SQL query
     * @param array<mixed> $bindings Parameter bindings
     * @param float $time Execution time in milliseconds
     */
    protected function logQuery(string $query, array $bindings, float $time): void
    {
        if (!$this->queryLoggingEnabled) {
            return;
        }

        $this->queryLog[] = [
            'query' => $query,
            'bindings' => $bindings,
            'time' => $time,
        ];

        // Performance warning for slow queries (>50ms target from CLAUDE.md)
        if ($time > 50.0) {
            $this->logSlowQuery($query, $time);
        }
    }

    /**
     * Log a slow query for performance monitoring
     */
    protected function logSlowQuery(string $query, float $time): void
    {
        error_log(sprintf(
            '[SAGA][PERF] Slow query (%.2fms): %s',
            $time,
            substr($query, 0, 200)
        ));
    }

    /**
     * Measure query execution time
     *
     * @template T
     * @param callable(): T $callback
     * @return array{result: T, time: float}
     */
    protected function measureTime(callable $callback): array
    {
        $start = microtime(true);
        $result = $callback();
        $time = (microtime(true) - $start) * 1000;

        return [
            'result' => $result,
            'time' => $time,
        ];
    }

    /**
     * Escape a string for use in LIKE patterns
     */
    protected function escapeLike(string $value): string
    {
        return str_replace(
            ['%', '_', '\\'],
            ['\\%', '\\_', '\\\\'],
            $value
        );
    }

    /**
     * Format values for SQL placeholders based on type
     *
     * @param mixed $value The value to format
     * @return string The placeholder format (%s, %d, %f)
     */
    protected function getPlaceholderFormat(mixed $value): string
    {
        return match (true) {
            is_int($value) => '%d',
            is_float($value) => '%f',
            default => '%s',
        };
    }

    /**
     * Build placeholders string for IN clauses
     *
     * @param array<mixed> $values
     * @return string Comma-separated placeholders
     */
    protected function buildInPlaceholders(array $values): string
    {
        $placeholders = array_map(
            fn($value) => $this->getPlaceholderFormat($value),
            $values
        );

        return implode(', ', $placeholders);
    }
}
