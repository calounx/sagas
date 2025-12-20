<?php
declare(strict_types=1);

namespace SagaManagerCore\Domain\Port\Database;

use IteratorAggregate;
use Countable;

/**
 * Port interface for database query result sets
 *
 * Provides iteration and access to query results in a database-agnostic way.
 * Implements IteratorAggregate for foreach support and Countable for count().
 *
 * @extends IteratorAggregate<int, array<string, mixed>>
 *
 * @example
 * ```php
 * // Fetch single row
 * $result = $connection->query("SELECT * FROM {$prefix}saga_entities WHERE id = ?", [123]);
 * $entity = $result->fetch();
 *
 * if ($entity === null) {
 *     throw new EntityNotFoundException("Entity 123 not found");
 * }
 *
 * // Fetch all rows
 * $result = $connection->query("SELECT * FROM {$prefix}saga_entities WHERE saga_id = ?", [1]);
 * $entities = $result->fetchAll();
 *
 * // Iterate over results
 * $result = $connection->query("SELECT id, name FROM {$prefix}saga_entities");
 * foreach ($result as $row) {
 *     echo $row['name'];
 * }
 *
 * // Get single column value
 * $result = $connection->query("SELECT COUNT(*) FROM {$prefix}saga_entities");
 * $count = $result->fetchColumn();
 *
 * // Use count
 * $result = $connection->query("SELECT * FROM {$prefix}saga_entities");
 * echo "Found " . count($result) . " entities";
 * ```
 */
interface ResultSetInterface extends IteratorAggregate, Countable
{
    /**
     * Fetch the next row as an associative array
     *
     * Returns null when no more rows are available.
     * Advances the internal cursor.
     *
     * @return array<string, mixed>|null Row data or null
     */
    public function fetch(): ?array;

    /**
     * Fetch all remaining rows as an array of associative arrays
     *
     * Returns empty array if no rows available.
     * After calling, cursor is at end.
     *
     * @return array<int, array<string, mixed>> All rows
     */
    public function fetchAll(): array;

    /**
     * Fetch a single column value from the next row
     *
     * @param int $column Zero-based column index (default: 0)
     * @return mixed Column value, or null if no row available
     */
    public function fetchColumn(int $column = 0): mixed;

    /**
     * Fetch all values from a single column
     *
     * @param int $column Zero-based column index (default: 0)
     * @return array<int, mixed> All values from the column
     */
    public function fetchAllColumn(int $column = 0): array;

    /**
     * Fetch all rows keyed by a column value
     *
     * Useful for building lookup tables from query results.
     *
     * @param string $keyColumn Column to use as array key
     * @return array<string|int, array<string, mixed>> Rows keyed by column value
     *
     * @example
     * ```php
     * $result = $query->execute();
     * $entitiesById = $result->fetchAllKeyedBy('id');
     * // ['1' => ['id' => 1, 'name' => 'Luke'], '2' => ['id' => 2, 'name' => 'Leia']]
     * ```
     */
    public function fetchAllKeyedBy(string $keyColumn): array;

    /**
     * Fetch all rows grouped by a column value
     *
     * @param string $groupColumn Column to group by
     * @return array<string|int, array<int, array<string, mixed>>> Grouped rows
     *
     * @example
     * ```php
     * $result = $query->execute();
     * $entitiesByType = $result->fetchAllGroupedBy('entity_type');
     * // ['character' => [row1, row2], 'location' => [row3]]
     * ```
     */
    public function fetchAllGroupedBy(string $groupColumn): array;

    /**
     * Get the number of rows in the result set
     *
     * @return int Number of rows
     */
    public function rowCount(): int;

    /**
     * Get the number of columns in the result set
     *
     * @return int Number of columns
     */
    public function columnCount(): int;

    /**
     * Get column metadata
     *
     * @return array<int, array{name: string, type: string, table: string|null}>
     */
    public function getColumnMeta(): array;

    /**
     * Check if result set is empty
     *
     * @return bool True if no rows
     */
    public function isEmpty(): bool;

    /**
     * Check if result set has rows
     *
     * @return bool True if at least one row
     */
    public function isNotEmpty(): bool;

    /**
     * Reset cursor to beginning
     *
     * Allows re-iterating over results.
     */
    public function rewind(): void;

    /**
     * Free result set resources
     *
     * Release memory used by the result set.
     * Called automatically on destruction.
     */
    public function free(): void;

    /**
     * Get iterator for foreach support
     *
     * @return \Iterator<int, array<string, mixed>>
     */
    public function getIterator(): \Iterator;

    /**
     * Countable implementation
     *
     * @return int Row count
     */
    public function count(): int;
}
