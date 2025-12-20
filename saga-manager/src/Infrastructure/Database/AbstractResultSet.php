<?php

declare(strict_types=1);

namespace SagaManager\Infrastructure\Database;

use SagaManager\Infrastructure\Database\Contract\ResultSetInterface;
use ArrayIterator;
use Traversable;

/**
 * Abstract Result Set
 *
 * Base implementation of ResultSetInterface with common functionality.
 */
abstract class AbstractResultSet implements ResultSetInterface
{
    /**
     * @param array<int, array<string, mixed>> $rows
     */
    public function __construct(
        protected array $rows = [],
    ) {}

    /**
     * @return Traversable<int, array<string, mixed>>
     */
    public function getIterator(): Traversable
    {
        return new ArrayIterator($this->rows);
    }

    public function toArray(): array
    {
        return $this->rows;
    }

    public function first(): ?array
    {
        return $this->rows[0] ?? null;
    }

    public function last(): ?array
    {
        if (empty($this->rows)) {
            return null;
        }
        return $this->rows[count($this->rows) - 1];
    }

    public function get(int $index): ?array
    {
        return $this->rows[$index] ?? null;
    }

    public function column(string $column): array
    {
        return array_column($this->rows, $column);
    }

    public function pluck(string $keyColumn, string $valueColumn): array
    {
        return array_column($this->rows, $valueColumn, $keyColumn);
    }

    public function groupBy(string $column): array
    {
        $grouped = [];
        foreach ($this->rows as $row) {
            $key = $row[$column] ?? '';
            if (!isset($grouped[$key])) {
                $grouped[$key] = [];
            }
            $grouped[$key][] = $row;
        }
        return $grouped;
    }

    public function isEmpty(): bool
    {
        return empty($this->rows);
    }

    public function isNotEmpty(): bool
    {
        return !$this->isEmpty();
    }

    public function count(): int
    {
        return count($this->rows);
    }

    public function map(callable $callback): array
    {
        return array_map($callback, $this->rows);
    }

    public function filter(callable $callback): ResultSetInterface
    {
        $filtered = array_values(array_filter($this->rows, $callback));
        return new static($filtered);
    }

    public function firstWhere(callable $callback): ?array
    {
        foreach ($this->rows as $row) {
            if ($callback($row)) {
                return $row;
            }
        }
        return null;
    }

    public function any(callable $callback): bool
    {
        foreach ($this->rows as $row) {
            if ($callback($row)) {
                return true;
            }
        }
        return false;
    }

    public function all(callable $callback): bool
    {
        foreach ($this->rows as $row) {
            if (!$callback($row)) {
                return false;
            }
        }
        return true;
    }
}
