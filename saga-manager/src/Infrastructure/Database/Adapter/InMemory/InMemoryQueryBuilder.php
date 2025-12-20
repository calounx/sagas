<?php

declare(strict_types=1);

namespace SagaManager\Infrastructure\Database\Adapter\InMemory;

use SagaManager\Infrastructure\Database\Contract\QueryBuilderInterface;
use SagaManager\Infrastructure\Database\Contract\ResultSetInterface;

/**
 * In-Memory Query Builder
 *
 * Fluent interface for building queries against in-memory data store.
 *
 * @example
 *   $result = $qb
 *       ->select('id', 'name')
 *       ->from('entities')
 *       ->where('saga_id', '=', 1)
 *       ->orderBy('importance_score', 'DESC')
 *       ->limit(10)
 *       ->execute();
 */
final class InMemoryQueryBuilder implements QueryBuilderInterface
{
    /** @var array<string> */
    private array $columns = [];

    private ?string $table = null;
    private ?string $tableAlias = null;

    /** @var array<array{type: string, table: string, alias: string, condition: string}> */
    private array $joins = [];

    /** @var array<array{column: string, operator: string, value: mixed, boolean: string}> */
    private array $wheres = [];

    /** @var array<array{column: string, direction: string}> */
    private array $orders = [];

    /** @var array<string> */
    private array $groups = [];

    /** @var array<array{column: string, operator: string, value: mixed}> */
    private array $havings = [];

    private ?int $limitValue = null;
    private int $offsetValue = 0;

    /** @var array<int|string, mixed> */
    private array $parameters = [];

    public function __construct(
        private readonly InMemoryDataStore $dataStore,
        private readonly string $tablePrefix = '',
    ) {}

    public function select(string ...$columns): static
    {
        $this->columns = $columns ?: ['*'];
        return $this;
    }

    public function from(string $table, ?string $alias = null): static
    {
        $this->table = $table;
        $this->tableAlias = $alias;
        return $this;
    }

    public function join(string $table, string $alias, string $condition): static
    {
        $this->joins[] = [
            'type' => 'INNER',
            'table' => $table,
            'alias' => $alias,
            'condition' => $condition,
        ];
        return $this;
    }

    public function leftJoin(string $table, string $alias, string $condition): static
    {
        $this->joins[] = [
            'type' => 'LEFT',
            'table' => $table,
            'alias' => $alias,
            'condition' => $condition,
        ];
        return $this;
    }

    public function rightJoin(string $table, string $alias, string $condition): static
    {
        $this->joins[] = [
            'type' => 'RIGHT',
            'table' => $table,
            'alias' => $alias,
            'condition' => $condition,
        ];
        return $this;
    }

    public function where(string $column, string $operator, mixed $value): static
    {
        $this->wheres[] = [
            'column' => $column,
            'operator' => strtoupper($operator),
            'value' => $value,
            'boolean' => 'AND',
        ];
        return $this;
    }

    public function orWhere(string $column, string $operator, mixed $value): static
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
        if (empty($values)) {
            // Always false - add impossible condition
            $this->wheres[] = [
                'column' => '__always_false__',
                'operator' => '=',
                'value' => true,
                'boolean' => 'AND',
            ];
            return $this;
        }

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
        if (empty($values)) {
            return $this; // No constraint needed
        }

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

    public function orderBy(string $column, string $direction = 'ASC'): static
    {
        $this->orders[] = [
            'column' => $column,
            'direction' => strtoupper($direction) === 'DESC' ? 'DESC' : 'ASC',
        ];
        return $this;
    }

    public function groupBy(string ...$columns): static
    {
        $this->groups = array_merge($this->groups, $columns);
        return $this;
    }

    public function having(string $column, string $operator, mixed $value): static
    {
        $this->havings[] = [
            'column' => $column,
            'operator' => strtoupper($operator),
            'value' => $value,
        ];
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

    public function execute(): ResultSetInterface
    {
        if ($this->table === null) {
            return InMemoryResultSet::empty();
        }

        $tableName = $this->tablePrefix . $this->table;
        $rows = $this->dataStore->getTable($tableName);

        // Handle JOINs (simplified - only supports basic joins)
        foreach ($this->joins as $join) {
            $rows = $this->performJoin($rows, $join);
        }

        // Apply WHERE filters
        $rows = $this->applyWheres($rows);

        // Apply GROUP BY
        if (!empty($this->groups)) {
            $rows = $this->applyGroupBy($rows);
        }

        // Apply HAVING
        if (!empty($this->havings)) {
            $rows = $this->applyHavings($rows);
        }

        // Apply ORDER BY
        if (!empty($this->orders)) {
            $rows = $this->applyOrderBy($rows);
        }

        // Apply OFFSET
        if ($this->offsetValue > 0) {
            $rows = array_slice($rows, $this->offsetValue);
        }

        // Apply LIMIT
        if ($this->limitValue !== null) {
            $rows = array_slice($rows, 0, $this->limitValue);
        }

        // Select columns
        if (!empty($this->columns) && $this->columns !== ['*']) {
            $rows = $this->selectColumns($rows);
        }

        return InMemoryResultSet::fromArray(array_values($rows));
    }

    public function first(): ?array
    {
        $this->limit(1);
        $result = $this->execute();
        return $result->first();
    }

    public function count(): int
    {
        if ($this->table === null) {
            return 0;
        }

        $tableName = $this->tablePrefix . $this->table;
        $rows = $this->dataStore->getTable($tableName);

        // Apply WHERE filters
        $rows = $this->applyWheres($rows);

        return count($rows);
    }

    public function toSql(): string
    {
        $parts = [];

        // SELECT
        $parts[] = 'SELECT ' . implode(', ', $this->columns ?: ['*']);

        // FROM
        if ($this->table !== null) {
            $tableName = $this->tablePrefix . $this->table;
            $parts[] = 'FROM ' . $tableName . ($this->tableAlias ? ' ' . $this->tableAlias : '');
        }

        // JOINs
        foreach ($this->joins as $join) {
            $joinTable = $this->tablePrefix . $join['table'];
            $parts[] = "{$join['type']} JOIN {$joinTable} {$join['alias']} ON {$join['condition']}";
        }

        // WHERE
        if (!empty($this->wheres)) {
            $whereParts = [];
            $first = true;
            foreach ($this->wheres as $where) {
                $condition = "{$where['column']} {$where['operator']}";
                if (!in_array($where['operator'], ['IS NULL', 'IS NOT NULL'])) {
                    $condition .= ' ?';
                }
                if ($first) {
                    $whereParts[] = $condition;
                    $first = false;
                } else {
                    $whereParts[] = "{$where['boolean']} {$condition}";
                }
            }
            $parts[] = 'WHERE ' . implode(' ', $whereParts);
        }

        // GROUP BY
        if (!empty($this->groups)) {
            $parts[] = 'GROUP BY ' . implode(', ', $this->groups);
        }

        // HAVING
        if (!empty($this->havings)) {
            $havingParts = [];
            foreach ($this->havings as $having) {
                $havingParts[] = "{$having['column']} {$having['operator']} ?";
            }
            $parts[] = 'HAVING ' . implode(' AND ', $havingParts);
        }

        // ORDER BY
        if (!empty($this->orders)) {
            $orderParts = [];
            foreach ($this->orders as $order) {
                $orderParts[] = "{$order['column']} {$order['direction']}";
            }
            $parts[] = 'ORDER BY ' . implode(', ', $orderParts);
        }

        // LIMIT
        if ($this->limitValue !== null) {
            $parts[] = 'LIMIT ' . $this->limitValue;
        }

        // OFFSET
        if ($this->offsetValue > 0) {
            $parts[] = 'OFFSET ' . $this->offsetValue;
        }

        return implode(' ', $parts);
    }

    public function getParameters(): array
    {
        $params = [];
        foreach ($this->wheres as $where) {
            if (!in_array($where['operator'], ['IS NULL', 'IS NOT NULL'])) {
                if (is_array($where['value'])) {
                    foreach ($where['value'] as $v) {
                        $params[] = $v;
                    }
                } else {
                    $params[] = $where['value'];
                }
            }
        }
        foreach ($this->havings as $having) {
            $params[] = $having['value'];
        }
        return $params;
    }

    public function reset(): static
    {
        $this->columns = [];
        $this->table = null;
        $this->tableAlias = null;
        $this->joins = [];
        $this->wheres = [];
        $this->orders = [];
        $this->groups = [];
        $this->havings = [];
        $this->limitValue = null;
        $this->offsetValue = 0;
        $this->parameters = [];

        return $this;
    }

    /**
     * Apply WHERE conditions to rows
     *
     * @param array<int, array<string, mixed>> $rows
     * @return array<int, array<string, mixed>>
     */
    private function applyWheres(array $rows): array
    {
        if (empty($this->wheres)) {
            return $rows;
        }

        return array_filter($rows, function (array $row): bool {
            $result = true;
            $currentResult = true;

            foreach ($this->wheres as $index => $where) {
                $matches = $this->evaluateCondition($row, $where);

                if ($index === 0) {
                    $result = $matches;
                    $currentResult = $matches;
                } else {
                    if ($where['boolean'] === 'OR') {
                        $result = $currentResult || $matches;
                        $currentResult = $result;
                    } else {
                        $result = $currentResult && $matches;
                        $currentResult = $result;
                    }
                }
            }

            return $result;
        });
    }

    /**
     * Evaluate a single WHERE condition
     *
     * @param array<string, mixed> $row
     * @param array{column: string, operator: string, value: mixed, boolean: string} $where
     * @return bool
     */
    private function evaluateCondition(array $row, array $where): bool
    {
        // Strip table alias from column if present
        $column = $where['column'];
        if (str_contains($column, '.')) {
            $parts = explode('.', $column);
            $column = end($parts);
        }

        $value = $row[$column] ?? null;
        $compareValue = $where['value'];

        return match ($where['operator']) {
            '=' => $value == $compareValue, // Loose comparison like databases
            '!=' => $value != $compareValue,
            '<' => $value < $compareValue,
            '<=' => $value <= $compareValue,
            '>' => $value > $compareValue,
            '>=' => $value >= $compareValue,
            'LIKE' => $this->matchLike((string) $value, (string) $compareValue),
            'NOT LIKE' => !$this->matchLike((string) $value, (string) $compareValue),
            'IN' => in_array($value, (array) $compareValue, false),
            'NOT IN' => !in_array($value, (array) $compareValue, false),
            'IS NULL' => $value === null,
            'IS NOT NULL' => $value !== null,
            'BETWEEN' => $value >= $compareValue[0] && $value <= $compareValue[1],
            default => false,
        };
    }

    /**
     * Match LIKE pattern
     *
     * @param string $value
     * @param string $pattern
     * @return bool
     */
    private function matchLike(string $value, string $pattern): bool
    {
        // Convert SQL LIKE to regex
        $regex = '/^' . str_replace(
            ['%', '_'],
            ['.*', '.'],
            preg_quote($pattern, '/')
        ) . '$/i';

        return preg_match($regex, $value) === 1;
    }

    /**
     * Apply ORDER BY to rows
     *
     * @param array<int, array<string, mixed>> $rows
     * @return array<int, array<string, mixed>>
     */
    private function applyOrderBy(array $rows): array
    {
        usort($rows, function ($a, $b) {
            foreach ($this->orders as $order) {
                $column = $order['column'];
                // Strip table alias
                if (str_contains($column, '.')) {
                    $parts = explode('.', $column);
                    $column = end($parts);
                }

                $aVal = $a[$column] ?? null;
                $bVal = $b[$column] ?? null;

                $cmp = $this->compareValues($aVal, $bVal);
                if ($cmp !== 0) {
                    return $order['direction'] === 'DESC' ? -$cmp : $cmp;
                }
            }
            return 0;
        });

        return $rows;
    }

    /**
     * Compare two values for sorting
     *
     * @param mixed $a
     * @param mixed $b
     * @return int
     */
    private function compareValues(mixed $a, mixed $b): int
    {
        if ($a === null && $b === null) {
            return 0;
        }
        if ($a === null) {
            return -1;
        }
        if ($b === null) {
            return 1;
        }

        return $a <=> $b;
    }

    /**
     * Select specific columns from rows
     *
     * @param array<int, array<string, mixed>> $rows
     * @return array<int, array<string, mixed>>
     */
    private function selectColumns(array $rows): array
    {
        return array_map(function ($row) {
            $result = [];
            foreach ($this->columns as $column) {
                // Handle expressions like "COUNT(*) AS count"
                if (str_contains($column, ' AS ')) {
                    [$expr, $alias] = array_map('trim', explode(' AS ', $column, 2));
                    if ($expr === 'COUNT(*)') {
                        $result[$alias] = 1; // Will be handled by groupBy
                    } else {
                        $result[$alias] = $row[$expr] ?? null;
                    }
                } else {
                    // Strip table alias
                    $col = $column;
                    if (str_contains($col, '.')) {
                        $parts = explode('.', $col);
                        $col = end($parts);
                    }
                    $result[$col] = $row[$col] ?? null;
                }
            }
            return $result;
        }, $rows);
    }

    /**
     * Apply GROUP BY to rows (simplified implementation)
     *
     * @param array<int, array<string, mixed>> $rows
     * @return array<int, array<string, mixed>>
     */
    private function applyGroupBy(array $rows): array
    {
        $grouped = [];

        foreach ($rows as $row) {
            $key = '';
            foreach ($this->groups as $column) {
                $key .= ($row[$column] ?? '') . '|';
            }

            if (!isset($grouped[$key])) {
                $grouped[$key] = [
                    'row' => $row,
                    'count' => 0,
                ];
            }
            $grouped[$key]['count']++;
        }

        return array_map(function ($group) {
            $row = $group['row'];
            $row['__count__'] = $group['count'];
            return $row;
        }, array_values($grouped));
    }

    /**
     * Apply HAVING to grouped rows
     *
     * @param array<int, array<string, mixed>> $rows
     * @return array<int, array<string, mixed>>
     */
    private function applyHavings(array $rows): array
    {
        return array_filter($rows, function ($row) {
            foreach ($this->havings as $having) {
                $column = $having['column'];
                $value = $column === 'COUNT(*)' ? ($row['__count__'] ?? 0) : ($row[$column] ?? null);

                if (!$this->evaluateCondition(
                    [$column => $value],
                    array_merge($having, ['boolean' => 'AND'])
                )) {
                    return false;
                }
            }
            return true;
        });
    }

    /**
     * Perform a JOIN operation (simplified)
     *
     * @param array<int, array<string, mixed>> $rows
     * @param array{type: string, table: string, alias: string, condition: string} $join
     * @return array<int, array<string, mixed>>
     */
    private function performJoin(array $rows, array $join): array
    {
        $joinTableName = $this->tablePrefix . $join['table'];
        $joinRows = $this->dataStore->getTable($joinTableName);

        // Parse join condition (simplified: assumes "a.col = b.col" format)
        if (preg_match('/(\w+)\.(\w+)\s*=\s*(\w+)\.(\w+)/', $join['condition'], $matches)) {
            $leftAlias = $matches[1];
            $leftCol = $matches[2];
            $rightAlias = $matches[3];
            $rightCol = $matches[4];

            $result = [];
            foreach ($rows as $leftRow) {
                $matched = false;
                foreach ($joinRows as $rightRow) {
                    $leftValue = $leftRow[$leftCol] ?? null;
                    $rightValue = $rightRow[$rightCol] ?? null;

                    if ($leftValue == $rightValue) {
                        // Merge rows with aliases
                        $merged = [];
                        foreach ($leftRow as $k => $v) {
                            $merged[$k] = $v;
                            $merged["{$leftAlias}.{$k}"] = $v;
                        }
                        foreach ($rightRow as $k => $v) {
                            $merged[$k] = $v;
                            $merged["{$join['alias']}.{$k}"] = $v;
                        }
                        $result[] = $merged;
                        $matched = true;
                    }
                }

                // LEFT JOIN: include unmatched rows
                if (!$matched && $join['type'] === 'LEFT') {
                    $result[] = $leftRow;
                }
            }

            return $result;
        }

        return $rows;
    }
}
