<?php

declare(strict_types=1);

namespace SagaManager\Infrastructure\Database\Contract;

use Countable;
use IteratorAggregate;

/**
 * Result Set Interface
 *
 * Represents the result of a database query with iteration and extraction capabilities.
 *
 * @extends IteratorAggregate<int, array<string, mixed>>
 *
 * @example
 *   $result = $db->select('entities', ['saga_id' => 1]);
 *
 *   // Iterate over results
 *   foreach ($result as $row) {
 *       echo $row['canonical_name'];
 *   }
 *
 *   // Get all rows
 *   $entities = $result->toArray();
 *
 *   // Get first row
 *   $first = $result->first();
 *
 *   // Get column values
 *   $ids = $result->column('id');
 */
interface ResultSetInterface extends IteratorAggregate, Countable
{
    /**
     * Get all rows as an array
     *
     * @return array<int, array<string, mixed>>
     *
     * @example
     *   $rows = $result->toArray();
     */
    public function toArray(): array;

    /**
     * Get the first row or null if empty
     *
     * @return array<string, mixed>|null
     *
     * @example
     *   $entity = $result->first();
     *   if ($entity !== null) {
     *       echo $entity['canonical_name'];
     *   }
     */
    public function first(): ?array;

    /**
     * Get the last row or null if empty
     *
     * @return array<string, mixed>|null
     */
    public function last(): ?array;

    /**
     * Get a specific row by index
     *
     * @param int $index Zero-based row index
     * @return array<string, mixed>|null
     *
     * @example
     *   $third = $result->get(2);
     */
    public function get(int $index): ?array;

    /**
     * Extract values from a single column
     *
     * @param string $column Column name
     * @return array<int, mixed>
     *
     * @example
     *   $ids = $result->column('id'); // [1, 2, 3, ...]
     */
    public function column(string $column): array;

    /**
     * Create a key-value map from two columns
     *
     * @param string $keyColumn Column to use as keys
     * @param string $valueColumn Column to use as values
     * @return array<string|int, mixed>
     *
     * @example
     *   $map = $result->pluck('id', 'canonical_name');
     *   // [1 => 'Luke Skywalker', 2 => 'Darth Vader', ...]
     */
    public function pluck(string $keyColumn, string $valueColumn): array;

    /**
     * Group results by a column value
     *
     * @param string $column Column to group by
     * @return array<string|int, array<int, array<string, mixed>>>
     *
     * @example
     *   $grouped = $result->groupBy('entity_type');
     *   // ['character' => [...], 'location' => [...]]
     */
    public function groupBy(string $column): array;

    /**
     * Check if the result set is empty
     *
     * @return bool
     */
    public function isEmpty(): bool;

    /**
     * Check if the result set is not empty
     *
     * @return bool
     */
    public function isNotEmpty(): bool;

    /**
     * Get the number of rows
     *
     * @return int
     */
    public function count(): int;

    /**
     * Map each row through a callback
     *
     * @template T
     * @param callable(array<string, mixed>): T $callback
     * @return array<int, T>
     *
     * @example
     *   $names = $result->map(fn($row) => strtoupper($row['canonical_name']));
     */
    public function map(callable $callback): array;

    /**
     * Filter rows through a callback
     *
     * @param callable(array<string, mixed>): bool $callback
     * @return ResultSetInterface
     *
     * @example
     *   $highImportance = $result->filter(fn($row) => $row['importance_score'] >= 80);
     */
    public function filter(callable $callback): ResultSetInterface;

    /**
     * Get the first row matching a callback
     *
     * @param callable(array<string, mixed>): bool $callback
     * @return array<string, mixed>|null
     *
     * @example
     *   $luke = $result->firstWhere(fn($row) => $row['canonical_name'] === 'Luke Skywalker');
     */
    public function firstWhere(callable $callback): ?array;

    /**
     * Check if any row matches a callback
     *
     * @param callable(array<string, mixed>): bool $callback
     * @return bool
     */
    public function any(callable $callback): bool;

    /**
     * Check if all rows match a callback
     *
     * @param callable(array<string, mixed>): bool $callback
     * @return bool
     */
    public function all(callable $callback): bool;
}
