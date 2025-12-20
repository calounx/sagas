<?php
declare(strict_types=1);

namespace SagaManagerCore\Infrastructure\Database\Adapter\InMemory;

use SagaManagerCore\Infrastructure\Database\Port\QueryBuilderInterface;
use SagaManagerCore\Infrastructure\Database\Port\ResultSetInterface;
use SagaManagerCore\Infrastructure\Database\ResultSet;

/**
 * In-Memory Query Builder for Testing
 *
 * Provides a fluent query builder that operates on in-memory arrays.
 * Supports basic CRUD operations with WHERE, ORDER BY, and LIMIT.
 *
 * @package SagaManagerCore\Infrastructure\Database\Adapter\InMemory
 */
class InMemoryQueryBuilder implements QueryBuilderInterface
{
    private InMemoryConnection $connection;

    /** @var array<string> */
    private array $columns = ['*'];
    private bool $distinct = false;
    private ?string $table = null;
    /** @var array<array{column: string, operator: string, value: mixed, boolean: string}> */
    private array $wheres = [];
    /** @var array<string, string> */
    private array $orders = [];
    private ?int $limitValue = null;
    private ?int $offsetValue = null;

    public function __construct(InMemoryConnection $connection)
    {
        $this->connection = $connection;
    }

    public function select(array|string $columns = '*'): static
    {
        $this->columns = is_array($columns) ? $columns : [$columns];
        return $this;
    }

    public function addSelect(array|string $columns): static
    {
        $columns = is_array($columns) ? $columns : [$columns];
        $this->columns = array_merge($this->columns, $columns);
        return $this;
    }

    public function distinct(): static
    {
        $this->distinct = true;
        return $this;
    }

    public function selectRaw(string $expression, ?string $alias = null): static
    {
        return $this;
    }

    public function from(string $table, ?string $alias = null): static
    {
        $this->table = $table;
        return $this;
    }

    public function table(string $table): static
    {
        $this->table = $table;
        return $this;
    }

    public function join(string $table, string $first, string $operator, string $second, ?string $alias = null): static
    {
        return $this;
    }

    public function leftJoin(string $table, string $first, string $operator, string $second, ?string $alias = null): static
    {
        return $this;
    }

    public function rightJoin(string $table, string $first, string $operator, string $second, ?string $alias = null): static
    {
        return $this;
    }

    public function where(string $column, string $operator, mixed $value = null): static
    {
        $this->wheres[] = [
            'column' => $column,
            'operator' => strtoupper($operator),
            'value' => $value,
            'boolean' => 'AND',
        ];
        return $this;
    }

    public function orWhere(string $column, string $operator, mixed $value = null): static
    {
        $this->wheres[] = [
            'column' => $column,
            'operator' => strtoupper($operator),
            'value' => $value,
            'boolean' => 'OR',
        ];
        return $this;
    }

    public function whereIn(string $column, array $values): static
    {
        $this->wheres[] = [
            'column' => $column,
            'operator' => 'IN',
            'value' => $values,
            'boolean' => 'AND',
        ];
        return $this;
    }

    public function whereNotIn(string $column, array $values): static
    {
        $this->wheres[] = [
            'column' => $column,
            'operator' => 'NOT IN',
            'value' => $values,
            'boolean' => 'AND',
        ];
        return $this;
    }

    public function whereNull(string $column): static
    {
        $this->wheres[] = [
            'column' => $column,
            'operator' => 'IS NULL',
            'value' => null,
            'boolean' => 'AND',
        ];
        return $this;
    }

    public function whereNotNull(string $column): static
    {
        $this->wheres[] = [
            'column' => $column,
            'operator' => 'IS NOT NULL',
            'value' => null,
            'boolean' => 'AND',
        ];
        return $this;
    }

    public function whereBetween(string $column, mixed $min, mixed $max): static
    {
        $this->wheres[] = [
            'column' => $column,
            'operator' => 'BETWEEN',
            'value' => [$min, $max],
            'boolean' => 'AND',
        ];
        return $this;
    }

    public function whereLike(string $column, string $pattern): static
    {
        $this->wheres[] = [
            'column' => $column,
            'operator' => 'LIKE',
            'value' => $pattern,
            'boolean' => 'AND',
        ];
        return $this;
    }

    public function whereRaw(string $sql, array $bindings = []): static
    {
        return $this;
    }

    public function whereGroup(callable $callback): static
    {
        return $this;
    }

    public function orWhereGroup(callable $callback): static
    {
        return $this;
    }

    public function orderBy(string $column, string $direction = 'ASC'): static
    {
        $this->orders[$column] = strtoupper($direction) === 'DESC' ? 'DESC' : 'ASC';
        return $this;
    }

    public function orderByRaw(string $expression): static
    {
        return $this;
    }

    public function groupBy(array|string $columns): static
    {
        return $this;
    }

    public function having(string $column, string $operator, mixed $value): static
    {
        return $this;
    }

    public function havingRaw(string $sql, array $bindings = []): static
    {
        return $this;
    }

    public function limit(int $limit): static
    {
        $this->limitValue = $limit;
        return $this;
    }

    public function offset(int $offset): static
    {
        $this->offsetValue = $offset;
        return $this;
    }

    public function paginate(int $page, int $perPage = 20): static
    {
        $this->limitValue = $perPage;
        $this->offsetValue = ($page - 1) * $perPage;
        return $this;
    }

    public function selectSubquery(QueryBuilderInterface $subquery, string $alias): static
    {
        return $this;
    }

    public function whereSubquery(string $column, string $operator, QueryBuilderInterface $subquery): static
    {
        return $this;
    }

    public function whereExists(QueryBuilderInterface $subquery): static
    {
        return $this;
    }

    public function insert(array $data): ResultSetInterface
    {
        $id = $this->connection->insertRow($this->table, $data);
        return ResultSet::fromWrite(1, $id);
    }

    public function insertBatch(array $rows): ResultSetInterface
    {
        $lastId = 0;
        foreach ($rows as $row) {
            $lastId = $this->connection->insertRow($this->table, $row);
        }
        return ResultSet::fromWrite(count($rows), $lastId);
    }

    public function upsert(array $data, array $updateOnDuplicate): ResultSetInterface
    {
        // Simple implementation: try insert, update if exists
        if (isset($data['id'])) {
            $existing = $this->connection->getRow($this->table, $data['id']);
            if ($existing) {
                $this->connection->updateRows($this->table, $updateOnDuplicate, ['id' => $data['id']]);
                return ResultSet::fromWrite(1, $data['id']);
            }
        }
        return $this->insert($data);
    }

    public function update(array $data): ResultSetInterface
    {
        $where = $this->buildSimpleWhere();
        $affected = $this->connection->updateRows($this->table, $data, $where);
        return ResultSet::fromWrite($affected, 0);
    }

    public function increment(string $column, int|float $amount = 1): ResultSetInterface
    {
        $rows = $this->getMatchingRows();
        foreach ($rows as $row) {
            $this->connection->updateRows(
                $this->table,
                [$column => ($row[$column] ?? 0) + $amount],
                ['id' => $row['id']]
            );
        }
        return ResultSet::fromWrite(count($rows), 0);
    }

    public function decrement(string $column, int|float $amount = 1): ResultSetInterface
    {
        return $this->increment($column, -$amount);
    }

    public function delete(): ResultSetInterface
    {
        $where = $this->buildSimpleWhere();
        $affected = $this->connection->deleteRows($this->table, $where);
        return ResultSet::fromWrite($affected, 0);
    }

    public function truncate(): ResultSetInterface
    {
        $this->connection->truncateTable($this->table);
        return ResultSet::fromWrite(0, 0);
    }

    public function execute(): ResultSetInterface
    {
        $rows = $this->getMatchingRows();
        return ResultSet::fromRows($rows);
    }

    public function first(): ?array
    {
        $this->limitValue = 1;
        $rows = $this->getMatchingRows();
        return $rows[0] ?? null;
    }

    public function get(): array
    {
        return $this->getMatchingRows();
    }

    public function value(string $column): mixed
    {
        $first = $this->first();
        return $first[$column] ?? null;
    }

    public function pluck(string $column): array
    {
        $rows = $this->getMatchingRows();
        return array_map(fn($row) => $row[$column] ?? null, $rows);
    }

    public function count(string $column = '*'): int
    {
        return count($this->getMatchingRows());
    }

    public function exists(): bool
    {
        return $this->count() > 0;
    }

    public function sum(string $column): float
    {
        $rows = $this->getMatchingRows();
        return (float) array_sum(array_map(fn($row) => $row[$column] ?? 0, $rows));
    }

    public function avg(string $column): float
    {
        $rows = $this->getMatchingRows();
        if (empty($rows)) {
            return 0.0;
        }
        return $this->sum($column) / count($rows);
    }

    public function min(string $column): mixed
    {
        $values = $this->pluck($column);
        return empty($values) ? null : min($values);
    }

    public function max(string $column): mixed
    {
        $values = $this->pluck($column);
        return empty($values) ? null : max($values);
    }

    public function toSql(): string
    {
        return 'IN-MEMORY QUERY';
    }

    public function getBindings(): array
    {
        return [];
    }

    public function clone(): static
    {
        return clone $this;
    }

    public function reset(): static
    {
        $this->columns = ['*'];
        $this->distinct = false;
        $this->table = null;
        $this->wheres = [];
        $this->orders = [];
        $this->limitValue = null;
        $this->offsetValue = null;
        return $this;
    }

    // =========================================================================
    // Private Helpers
    // =========================================================================

    /**
     * @return array<array<string, mixed>>
     */
    private function getMatchingRows(): array
    {
        $rows = $this->connection->getAllRows($this->table);

        // Filter by WHERE conditions
        $rows = array_filter($rows, fn($row) => $this->matchesWheres($row));

        // Sort
        if (!empty($this->orders)) {
            usort($rows, function ($a, $b) {
                foreach ($this->orders as $column => $direction) {
                    $aVal = $a[$column] ?? null;
                    $bVal = $b[$column] ?? null;
                    if ($aVal === $bVal) {
                        continue;
                    }
                    $cmp = $aVal <=> $bVal;
                    return $direction === 'DESC' ? -$cmp : $cmp;
                }
                return 0;
            });
        }

        // Apply distinct
        if ($this->distinct) {
            $rows = array_values(array_unique($rows, SORT_REGULAR));
        }

        // Apply offset and limit
        $rows = array_values($rows);
        if ($this->offsetValue !== null || $this->limitValue !== null) {
            $rows = array_slice($rows, $this->offsetValue ?? 0, $this->limitValue);
        }

        // Select specific columns
        if ($this->columns !== ['*']) {
            $rows = array_map(function ($row) {
                return array_intersect_key($row, array_flip($this->columns));
            }, $rows);
        }

        return $rows;
    }

    /**
     * @param array<string, mixed> $row
     */
    private function matchesWheres(array $row): bool
    {
        if (empty($this->wheres)) {
            return true;
        }

        $result = true;
        $firstCondition = true;

        foreach ($this->wheres as $where) {
            $matches = $this->matchesCondition($row, $where);

            if ($firstCondition) {
                $result = $matches;
                $firstCondition = false;
            } else {
                if ($where['boolean'] === 'OR') {
                    $result = $result || $matches;
                } else {
                    $result = $result && $matches;
                }
            }
        }

        return $result;
    }

    /**
     * @param array<string, mixed> $row
     * @param array{column: string, operator: string, value: mixed, boolean: string} $where
     */
    private function matchesCondition(array $row, array $where): bool
    {
        $column = $where['column'];
        $operator = $where['operator'];
        $value = $where['value'];
        $rowValue = $row[$column] ?? null;

        return match ($operator) {
            '=' => $rowValue === $value,
            '!=' , '<>' => $rowValue !== $value,
            '<' => $rowValue < $value,
            '>' => $rowValue > $value,
            '<=' => $rowValue <= $value,
            '>=' => $rowValue >= $value,
            'IN' => in_array($rowValue, $value, true),
            'NOT IN' => !in_array($rowValue, $value, true),
            'IS NULL' => $rowValue === null,
            'IS NOT NULL' => $rowValue !== null,
            'BETWEEN' => $rowValue >= $value[0] && $rowValue <= $value[1],
            'LIKE' => $this->matchesLike($rowValue, $value),
            default => true,
        };
    }

    private function matchesLike(?string $value, string $pattern): bool
    {
        if ($value === null) {
            return false;
        }
        // Convert SQL LIKE pattern to regex
        $regex = '/^' . str_replace(['%', '_'], ['.*', '.'], preg_quote($pattern, '/')) . '$/i';
        return (bool) preg_match($regex, $value);
    }

    /**
     * Build a simple WHERE array for direct connection operations
     *
     * @return array<string, mixed>
     */
    private function buildSimpleWhere(): array
    {
        $where = [];
        foreach ($this->wheres as $condition) {
            if ($condition['operator'] === '=') {
                $where[$condition['column']] = $condition['value'];
            }
        }
        return $where;
    }
}
