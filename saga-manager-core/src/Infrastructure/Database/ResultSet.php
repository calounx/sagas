<?php
declare(strict_types=1);

namespace SagaManagerCore\Infrastructure\Database;

use SagaManagerCore\Infrastructure\Database\Port\ResultSetInterface;
use Traversable;
use ArrayIterator;

/**
 * Generic Result Set Implementation
 *
 * Wraps query results in a consistent interface regardless of the underlying
 * database driver. Supports both read query results and write operation metadata.
 *
 * @package SagaManagerCore\Infrastructure\Database
 */
class ResultSet implements ResultSetInterface
{
    /** @var array<array<string, mixed>> */
    private array $rows;
    private int $position = 0;
    private int $affectedRows;
    private int $lastInsertId;
    private bool $success;
    private ?string $error;
    /** @var array<string> */
    private array $columnNames;

    /**
     * @param array<array<string, mixed>> $rows Query result rows
     * @param int $affectedRows Number of affected rows (for write operations)
     * @param int $lastInsertId Last insert ID (for INSERT operations)
     * @param bool $success Whether the operation was successful
     * @param string|null $error Error message if operation failed
     */
    public function __construct(
        array $rows = [],
        int $affectedRows = 0,
        int $lastInsertId = 0,
        bool $success = true,
        ?string $error = null
    ) {
        $this->rows = array_values($rows);
        $this->affectedRows = $affectedRows;
        $this->lastInsertId = $lastInsertId;
        $this->success = $success;
        $this->error = $error;
        $this->columnNames = !empty($rows) ? array_keys($rows[0]) : [];
    }

    /**
     * Create a successful result set from rows
     *
     * @param array<array<string, mixed>> $rows
     */
    public static function fromRows(array $rows): self
    {
        return new self($rows);
    }

    /**
     * Create a result set for a write operation
     */
    public static function fromWrite(int $affectedRows, int $lastInsertId = 0): self
    {
        return new self([], $affectedRows, $lastInsertId, true);
    }

    /**
     * Create a failed result set
     */
    public static function failed(string $error): self
    {
        return new self([], 0, 0, false, $error);
    }

    /**
     * Create an empty successful result set
     */
    public static function empty(): self
    {
        return new self();
    }

    // =========================================================================
    // ResultSetInterface Implementation
    // =========================================================================

    public function fetch(): ?array
    {
        if (!isset($this->rows[$this->position])) {
            return null;
        }

        return $this->rows[$this->position++];
    }

    public function fetchAll(): array
    {
        $remaining = array_slice($this->rows, $this->position);
        $this->position = count($this->rows);
        return $remaining;
    }

    public function all(): array
    {
        return $this->rows;
    }

    public function fetchAllAsObjects(): array
    {
        return array_map(
            fn(array $row) => (object) $row,
            $this->fetchAll()
        );
    }

    public function fetchColumn(int $columnIndex = 0): mixed
    {
        $row = $this->fetch();
        if ($row === null) {
            return null;
        }

        $values = array_values($row);
        return $values[$columnIndex] ?? null;
    }

    public function fetchColumnAll(int $columnIndex = 0): array
    {
        return array_map(
            fn(array $row) => array_values($row)[$columnIndex] ?? null,
            $this->fetchAll()
        );
    }

    public function fetchObject(): ?object
    {
        $row = $this->fetch();
        return $row !== null ? (object) $row : null;
    }

    public function first(): ?array
    {
        return $this->rows[0] ?? null;
    }

    public function firstOrFail(): array
    {
        $first = $this->first();
        if ($first === null) {
            throw new \RuntimeException('No rows in result set');
        }
        return $first;
    }

    public function last(): ?array
    {
        if (empty($this->rows)) {
            return null;
        }
        return $this->rows[count($this->rows) - 1];
    }

    public function row(int $index): ?array
    {
        return $this->rows[$index] ?? null;
    }

    public function value(string $column, mixed $default = null): mixed
    {
        $first = $this->first();
        if ($first === null) {
            return $default;
        }
        return $first[$column] ?? $default;
    }

    public function pluck(string $column): array
    {
        return array_map(
            fn(array $row) => $row[$column] ?? null,
            $this->rows
        );
    }

    public function pluckKeyed(string $valueColumn, string $keyColumn): array
    {
        $result = [];
        foreach ($this->rows as $row) {
            $key = $row[$keyColumn] ?? null;
            if ($key !== null) {
                $result[$key] = $row[$valueColumn] ?? null;
            }
        }
        return $result;
    }

    public function map(callable $callback): array
    {
        return array_map($callback, $this->rows, array_keys($this->rows));
    }

    public function filter(callable $callback): array
    {
        return array_values(array_filter(
            $this->rows,
            fn($row, $index) => $callback($row, $index),
            ARRAY_FILTER_USE_BOTH
        ));
    }

    public function reduce(callable $callback, mixed $initial): mixed
    {
        $accumulator = $initial;
        foreach ($this->rows as $index => $row) {
            $accumulator = $callback($accumulator, $row, $index);
        }
        return $accumulator;
    }

    public function groupBy(string $column): array
    {
        $grouped = [];
        foreach ($this->rows as $row) {
            $key = $row[$column] ?? '';
            $grouped[$key][] = $row;
        }
        return $grouped;
    }

    public function each(callable $callback): void
    {
        foreach ($this->rows as $index => $row) {
            $callback($row, $index);
        }
    }

    public function chunk(int $size): \Generator
    {
        $chunks = array_chunk($this->rows, $size);
        foreach ($chunks as $chunk) {
            yield $chunk;
        }
    }

    public function rowCount(): int
    {
        return count($this->rows);
    }

    public function columnCount(): int
    {
        return count($this->columnNames);
    }

    public function getColumnNames(): array
    {
        return $this->columnNames;
    }

    public function isEmpty(): bool
    {
        return empty($this->rows);
    }

    public function isNotEmpty(): bool
    {
        return !$this->isEmpty();
    }

    public function reset(): void
    {
        $this->position = 0;
    }

    public function affectedRows(): int
    {
        return $this->affectedRows;
    }

    public function lastInsertId(): int
    {
        return $this->lastInsertId;
    }

    public function isSuccess(): bool
    {
        return $this->success;
    }

    public function getError(): ?string
    {
        return $this->error;
    }

    public function toArray(): array
    {
        return $this->rows;
    }

    public function toJson(int $flags = 0): string
    {
        return json_encode($this->rows, $flags | JSON_THROW_ON_ERROR);
    }

    // =========================================================================
    // IteratorAggregate Implementation
    // =========================================================================

    public function getIterator(): Traversable
    {
        return new ArrayIterator($this->rows);
    }

    // =========================================================================
    // Countable Implementation
    // =========================================================================

    public function count(): int
    {
        return $this->rowCount();
    }
}
