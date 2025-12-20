<?php

declare(strict_types=1);

namespace SagaManagerCore\Infrastructure\Database\Port;

use Countable;
use IteratorAggregate;

/**
 * Result Set Port Interface
 *
 * Provides a database-agnostic interface for query results.
 * Supports iteration, array access, and various result retrieval methods.
 *
 * Usage:
 * ```php
 * $result = $connection->query()
 *     ->select('*')
 *     ->from('entities')
 *     ->execute();
 *
 * // Iteration
 * foreach ($result as $row) {
 *     echo $row['canonical_name'];
 * }
 *
 * // Direct access
 * $all = $result->all();
 * $first = $result->first();
 * $count = $result->count();
 * ```
 *
 * @package SagaManagerCore\Infrastructure\Database\Port
 * @extends IteratorAggregate<int, array<string, mixed>>
 */
interface ResultSetInterface extends IteratorAggregate, Countable
{
    /**
     * Output format: Associative array (column name => value)
     */
    public const FORMAT_ASSOC = 'ARRAY_A';

    /**
     * Output format: Numeric array (0 => value)
     */
    public const FORMAT_NUM = 'ARRAY_N';

    /**
     * Output format: Object with properties
     */
    public const FORMAT_OBJECT = 'OBJECT';

    // =========================================================================
    // Basic Fetch Operations
    // =========================================================================

    /**
     * Fetch the next row from the result set
     *
     * @return array<string, mixed>|null Row data or null if no more rows
     */
    public function fetch(): ?array;

    /**
     * Fetch all remaining rows from the result set
     *
     * @return array<int, array<string, mixed>> All rows as arrays
     */
    public function fetchAll(): array;

    /**
     * Get all rows as an array (alias for fetchAll)
     *
     * @return array<array<string, mixed>>
     */
    public function all(): array;

    /**
     * Fetch all rows as objects
     *
     * @return array<int, object> All rows as objects
     */
    public function fetchAllAsObjects(): array;

    /**
     * Fetch a single column value from the next row
     *
     * @param int $columnIndex Zero-based column index
     * @return mixed Column value or null if no more rows
     */
    public function fetchColumn(int $columnIndex = 0): mixed;

    /**
     * Fetch all values from a single column
     *
     * @param int $columnIndex Zero-based column index
     * @return array<int, mixed> All values from the column
     */
    public function fetchColumnAll(int $columnIndex = 0): array;

    /**
     * Fetch a single row as an object
     *
     * @return object|null Row as object or null if no more rows
     */
    public function fetchObject(): ?object;

    // =========================================================================
    // Row Access
    // =========================================================================

    /**
     * Get the first row or null if empty
     *
     * @return array<string, mixed>|null First row or null if empty
     */
    public function first(): ?array;

    /**
     * Get the first row or throw exception if empty
     *
     * @return array<string, mixed>
     * @throws \RuntimeException When no rows exist
     */
    public function firstOrFail(): array;

    /**
     * Get the last row
     *
     * @return array<string, mixed>|null Last row or null if empty
     */
    public function last(): ?array;

    /**
     * Get a specific row by index (0-based)
     *
     * @param int $index Row index
     * @return array<string, mixed>|null
     */
    public function row(int $index): ?array;

    // =========================================================================
    // Value Extraction
    // =========================================================================

    /**
     * Get a single column value from the first row
     *
     * @param string $column Column name
     * @param mixed $default Default value if not found
     * @return mixed
     */
    public function value(string $column, mixed $default = null): mixed;

    /**
     * Get all values for a specific column
     *
     * @param string $column Column name
     * @return array<mixed>
     */
    public function pluck(string $column): array;

    /**
     * Get column values keyed by another column
     *
     * @param string $valueColumn Column for values
     * @param string $keyColumn Column for keys
     * @return array<string|int, mixed>
     */
    public function pluckKeyed(string $valueColumn, string $keyColumn): array;

    // =========================================================================
    // Functional Operations
    // =========================================================================

    /**
     * Map results using a callback
     *
     * @template T
     * @param callable(array<string, mixed>, int): T $callback
     * @return array<T>
     */
    public function map(callable $callback): array;

    /**
     * Filter results using a callback
     *
     * @param callable(array<string, mixed>, int): bool $callback
     * @return array<array<string, mixed>>
     */
    public function filter(callable $callback): array;

    /**
     * Reduce results to a single value
     *
     * @template T
     * @param callable(T, array<string, mixed>, int): T $callback
     * @param T $initial
     * @return T
     */
    public function reduce(callable $callback, mixed $initial): mixed;

    /**
     * Group results by a column value
     *
     * @param string $column Column to group by
     * @return array<string|int, array<array<string, mixed>>>
     */
    public function groupBy(string $column): array;

    /**
     * Process each row with a callback (memory-efficient for large results)
     *
     * @param callable(array<string, mixed>, int): void $callback
     */
    public function each(callable $callback): void;

    /**
     * Chunk results for memory-efficient processing
     *
     * @param int $size Chunk size
     * @return \Generator<array<array<string, mixed>>>
     */
    public function chunk(int $size): \Generator;

    // =========================================================================
    // Metadata
    // =========================================================================

    /**
     * Get the number of rows in the result set
     */
    public function rowCount(): int;

    /**
     * Get the number of columns in the result set
     */
    public function columnCount(): int;

    /**
     * Get column names from the result set
     *
     * @return array<int, string> Column names
     */
    public function getColumnNames(): array;

    /**
     * Check if the result set is empty
     */
    public function isEmpty(): bool;

    /**
     * Check if results are not empty
     *
     * @return bool
     */
    public function isNotEmpty(): bool;

    /**
     * Reset the internal pointer to the beginning
     */
    public function reset(): void;

    // =========================================================================
    // Write Operation Results
    // =========================================================================

    /**
     * Get the number of rows affected (for INSERT/UPDATE/DELETE)
     *
     * @return int
     */
    public function affectedRows(): int;

    /**
     * Get the last insert ID (for INSERT operations)
     *
     * @return int
     */
    public function lastInsertId(): int;

    /**
     * Check if the operation was successful
     *
     * @return bool
     */
    public function isSuccess(): bool;

    /**
     * Get any error message from the operation
     *
     * @return string|null
     */
    public function getError(): ?string;

    // =========================================================================
    // Conversion
    // =========================================================================

    /**
     * Convert the entire result set to an array
     *
     * @return array<int, array<string, mixed>>
     */
    public function toArray(): array;

    /**
     * Convert results to JSON string
     *
     * @param int $flags JSON encoding flags
     * @return string
     */
    public function toJson(int $flags = 0): string;
}
