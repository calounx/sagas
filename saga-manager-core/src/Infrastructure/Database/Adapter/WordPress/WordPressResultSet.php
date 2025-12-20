<?php

declare(strict_types=1);

namespace SagaManagerCore\Infrastructure\Database\Adapter\WordPress;

use Generator;
use SagaManagerCore\Infrastructure\Database\Port\ResultSetInterface;
use Traversable;

/**
 * WordPress Result Set Implementation
 *
 * Wraps wpdb query results providing a consistent interface
 * for iterating and fetching database results.
 */
class WordPressResultSet implements ResultSetInterface
{
    /**
     * @var array<int, array<string, mixed>>
     */
    private array $rows;

    /**
     * @var int Current position for iteration
     */
    private int $position = 0;

    /**
     * @var array<int, string> Column names
     */
    private array $columnNames = [];

    /**
     * @var int Number of affected rows (for INSERT/UPDATE/DELETE)
     */
    private int $affectedRowsCount;

    /**
     * @var int Last insert ID
     */
    private int $insertId;

    /**
     * @var string|null Error message if operation failed
     */
    private ?string $error;

    /**
     * @param array<int, array<string, mixed>|object>|null $results Raw wpdb results
     * @param int $affectedRows Number of affected rows
     * @param int $lastInsertId Last insert ID
     * @param string|null $error Error message if any
     */
    public function __construct(
        array|null $results = null,
        int $affectedRows = 0,
        int $lastInsertId = 0,
        ?string $error = null
    ) {
        $this->rows = $this->normalizeResults($results);
        $this->affectedRowsCount = $affectedRows;
        $this->insertId = $lastInsertId;
        $this->error = $error;

        // Extract column names from first row
        if (!empty($this->rows)) {
            $this->columnNames = array_keys($this->rows[0]);
        }
    }

    /**
     * Create from wpdb query results
     */
    public static function fromWpdb(
        array|null $results,
        int $affectedRows = 0,
        int $lastInsertId = 0,
        ?string $error = null
    ): self {
        return new self($results, $affectedRows, $lastInsertId, $error);
    }

    /**
     * Create an empty result set
     */
    public static function empty(): self
    {
        return new self(null, 0, 0);
    }

    // =========================================================================
    // Basic Fetch Operations
    // =========================================================================

    /**
     * {@inheritdoc}
     */
    public function fetch(): ?array
    {
        if (!isset($this->rows[$this->position])) {
            return null;
        }

        return $this->rows[$this->position++];
    }

    /**
     * {@inheritdoc}
     */
    public function fetchAll(): array
    {
        $remaining = array_slice($this->rows, $this->position);
        $this->position = count($this->rows);

        return $remaining;
    }

    /**
     * {@inheritdoc}
     */
    public function all(): array
    {
        return $this->rows;
    }

    /**
     * {@inheritdoc}
     */
    public function fetchAllAsObjects(): array
    {
        return array_map(
            fn(array $row) => (object) $row,
            $this->fetchAll()
        );
    }

    /**
     * {@inheritdoc}
     */
    public function fetchColumn(int $columnIndex = 0): mixed
    {
        $row = $this->fetch();

        if ($row === null) {
            return null;
        }

        $values = array_values($row);

        return $values[$columnIndex] ?? null;
    }

    /**
     * {@inheritdoc}
     */
    public function fetchColumnAll(int $columnIndex = 0): array
    {
        if (!isset($this->columnNames[$columnIndex])) {
            return [];
        }

        $columnName = $this->columnNames[$columnIndex];

        return array_column($this->rows, $columnName);
    }

    /**
     * {@inheritdoc}
     */
    public function fetchObject(): ?object
    {
        $row = $this->fetch();

        return $row !== null ? (object) $row : null;
    }

    // =========================================================================
    // Row Access
    // =========================================================================

    /**
     * {@inheritdoc}
     */
    public function first(): ?array
    {
        return $this->rows[0] ?? null;
    }

    /**
     * {@inheritdoc}
     */
    public function firstOrFail(): array
    {
        $first = $this->first();

        if ($first === null) {
            throw new \RuntimeException('No rows found in result set');
        }

        return $first;
    }

    /**
     * {@inheritdoc}
     */
    public function last(): ?array
    {
        if (empty($this->rows)) {
            return null;
        }

        return $this->rows[count($this->rows) - 1];
    }

    /**
     * {@inheritdoc}
     */
    public function row(int $index): ?array
    {
        return $this->rows[$index] ?? null;
    }

    // =========================================================================
    // Value Extraction
    // =========================================================================

    /**
     * {@inheritdoc}
     */
    public function value(string $column, mixed $default = null): mixed
    {
        $first = $this->first();

        if ($first === null) {
            return $default;
        }

        return $first[$column] ?? $default;
    }

    /**
     * {@inheritdoc}
     */
    public function pluck(string $column): array
    {
        return array_column($this->rows, $column);
    }

    /**
     * {@inheritdoc}
     */
    public function pluckKeyed(string $valueColumn, string $keyColumn): array
    {
        return array_column($this->rows, $valueColumn, $keyColumn);
    }

    // =========================================================================
    // Functional Operations
    // =========================================================================

    /**
     * {@inheritdoc}
     */
    public function map(callable $callback): array
    {
        $result = [];

        foreach ($this->rows as $index => $row) {
            $result[] = $callback($row, $index);
        }

        return $result;
    }

    /**
     * {@inheritdoc}
     */
    public function filter(callable $callback): array
    {
        $result = [];

        foreach ($this->rows as $index => $row) {
            if ($callback($row, $index)) {
                $result[] = $row;
            }
        }

        return $result;
    }

    /**
     * {@inheritdoc}
     */
    public function reduce(callable $callback, mixed $initial): mixed
    {
        $accumulator = $initial;

        foreach ($this->rows as $index => $row) {
            $accumulator = $callback($accumulator, $row, $index);
        }

        return $accumulator;
    }

    /**
     * {@inheritdoc}
     */
    public function groupBy(string $column): array
    {
        $grouped = [];

        foreach ($this->rows as $row) {
            $key = $row[$column] ?? '';
            $grouped[$key][] = $row;
        }

        return $grouped;
    }

    /**
     * {@inheritdoc}
     */
    public function each(callable $callback): void
    {
        foreach ($this->rows as $index => $row) {
            $callback($row, $index);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function chunk(int $size): Generator
    {
        $chunks = array_chunk($this->rows, $size);

        foreach ($chunks as $chunk) {
            yield $chunk;
        }
    }

    // =========================================================================
    // Metadata
    // =========================================================================

    /**
     * {@inheritdoc}
     */
    public function rowCount(): int
    {
        return count($this->rows);
    }

    /**
     * {@inheritdoc}
     */
    public function columnCount(): int
    {
        return count($this->columnNames);
    }

    /**
     * {@inheritdoc}
     */
    public function getColumnNames(): array
    {
        return $this->columnNames;
    }

    /**
     * {@inheritdoc}
     */
    public function isEmpty(): bool
    {
        return empty($this->rows);
    }

    /**
     * {@inheritdoc}
     */
    public function isNotEmpty(): bool
    {
        return !empty($this->rows);
    }

    /**
     * {@inheritdoc}
     */
    public function reset(): void
    {
        $this->position = 0;
    }

    // =========================================================================
    // Write Operation Results
    // =========================================================================

    /**
     * {@inheritdoc}
     */
    public function affectedRows(): int
    {
        return $this->affectedRowsCount;
    }

    /**
     * {@inheritdoc}
     */
    public function lastInsertId(): int
    {
        return $this->insertId;
    }

    /**
     * {@inheritdoc}
     */
    public function isSuccess(): bool
    {
        return $this->error === null;
    }

    /**
     * {@inheritdoc}
     */
    public function getError(): ?string
    {
        return $this->error;
    }

    /**
     * Get the number of affected rows (alias for interface compatibility)
     */
    public function getAffectedRows(): int
    {
        return $this->affectedRowsCount;
    }

    /**
     * Get the last insert ID (alias for interface compatibility)
     */
    public function getLastInsertId(): int
    {
        return $this->insertId;
    }

    // =========================================================================
    // Conversion
    // =========================================================================

    /**
     * {@inheritdoc}
     */
    public function toArray(): array
    {
        return $this->rows;
    }

    /**
     * {@inheritdoc}
     */
    public function toJson(int $flags = 0): string
    {
        return json_encode($this->rows, $flags) ?: '[]';
    }

    // =========================================================================
    // IteratorAggregate Implementation
    // =========================================================================

    /**
     * {@inheritdoc}
     */
    public function getIterator(): Traversable
    {
        return new \ArrayIterator($this->rows);
    }

    // =========================================================================
    // Countable Implementation
    // =========================================================================

    /**
     * {@inheritdoc}
     */
    public function count(): int
    {
        return count($this->rows);
    }

    // =========================================================================
    // Helper Methods
    // =========================================================================

    /**
     * Normalize wpdb results to associative arrays
     *
     * @param array<int, array<string, mixed>|object>|null $results
     * @return array<int, array<string, mixed>>
     */
    private function normalizeResults(array|null $results): array
    {
        if ($results === null || empty($results)) {
            return [];
        }

        return array_map(function ($row): array {
            if (is_object($row)) {
                return (array) $row;
            }

            return $row;
        }, $results);
    }
}
