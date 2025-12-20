<?php

declare(strict_types=1);

namespace SagaManager\Infrastructure\Database\Adapter\WordPress;

use SagaManager\Infrastructure\Database\Contract\QueryBuilderInterface;
use SagaManager\Infrastructure\Database\Contract\ResultSetInterface;

/**
 * WordPress Query Builder
 *
 * Fluent interface for building SQL queries with WordPress wpdb.
 *
 * @example
 *   $result = $qb
 *       ->select('id', 'name', 'importance_score')
 *       ->from('entities', 'e')
 *       ->where('saga_id', '=', 1)
 *       ->where('entity_type', '=', 'character')
 *       ->orderBy('importance_score', 'DESC')
 *       ->limit(10)
 *       ->execute();
 */
final class WordPressQueryBuilder implements QueryBuilderInterface
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
        private readonly \wpdb $wpdb,
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
            // Always false condition for empty IN
            $this->wheres[] = [
                'column' => '1',
                'operator' => '=',
                'value' => '0',
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
            return $this;
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
        $sql = $this->toSql();
        $params = $this->getParameters();

        if (!empty($params)) {
            $sql = $this->wpdb->prepare($sql, $params);
        }

        $results = $this->wpdb->get_results($sql, ARRAY_A);

        return WordPressResultSet::fromArray($results ?: []);
    }

    public function first(): ?array
    {
        $this->limit(1);
        $result = $this->execute();
        return $result->first();
    }

    public function count(): int
    {
        $originalColumns = $this->columns;
        $originalLimit = $this->limitValue;
        $originalOffset = $this->offsetValue;

        $this->columns = ['COUNT(*) AS count'];
        $this->limitValue = null;
        $this->offsetValue = 0;

        $sql = $this->toSql();
        $params = $this->getParameters();

        if (!empty($params)) {
            $sql = $this->wpdb->prepare($sql, $params);
        }

        $result = $this->wpdb->get_var($sql);

        // Restore original values
        $this->columns = $originalColumns;
        $this->limitValue = $originalLimit;
        $this->offsetValue = $originalOffset;

        return (int) $result;
    }

    public function toSql(): string
    {
        $this->parameters = [];

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
        $whereSql = $this->buildWhere();
        if ($whereSql !== '') {
            $parts[] = 'WHERE ' . $whereSql;
        }

        // GROUP BY
        if (!empty($this->groups)) {
            $parts[] = 'GROUP BY ' . implode(', ', $this->groups);
        }

        // HAVING
        if (!empty($this->havings)) {
            $havingParts = [];
            foreach ($this->havings as $having) {
                $placeholder = $this->getPlaceholder($having['value']);
                $this->parameters[] = $having['value'];
                $havingParts[] = "{$having['column']} {$having['operator']} {$placeholder}";
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
            $parts[] = 'LIMIT %d';
            $this->parameters[] = $this->limitValue;
        }

        // OFFSET
        if ($this->offsetValue > 0) {
            $parts[] = 'OFFSET %d';
            $this->parameters[] = $this->offsetValue;
        }

        return implode(' ', $parts);
    }

    public function getParameters(): array
    {
        return $this->parameters;
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

    private function buildWhere(): string
    {
        if (empty($this->wheres)) {
            return '';
        }

        $parts = [];
        $first = true;

        foreach ($this->wheres as $where) {
            $condition = $this->buildWhereCondition($where);

            if ($first) {
                $parts[] = $condition;
                $first = false;
            } else {
                $parts[] = $where['boolean'] . ' ' . $condition;
            }
        }

        return implode(' ', $parts);
    }

    /**
     * @param array{column: string, operator: string, value: mixed, boolean: string} $where
     */
    private function buildWhereCondition(array $where): string
    {
        $column = $where['column'];
        $operator = $where['operator'];
        $value = $where['value'];

        return match ($operator) {
            'IS NULL', 'IS NOT NULL' => "{$column} {$operator}",
            'IN', 'NOT IN' => $this->buildInCondition($column, $operator, $value),
            'BETWEEN' => $this->buildBetweenCondition($column, $value),
            default => $this->buildSimpleCondition($column, $operator, $value),
        };
    }

    private function buildInCondition(string $column, string $operator, array $values): string
    {
        $placeholders = [];
        foreach ($values as $value) {
            $placeholders[] = $this->getPlaceholder($value);
            $this->parameters[] = $value;
        }
        return "{$column} {$operator} (" . implode(', ', $placeholders) . ")";
    }

    private function buildBetweenCondition(string $column, array $values): string
    {
        $minPlaceholder = $this->getPlaceholder($values[0]);
        $maxPlaceholder = $this->getPlaceholder($values[1]);
        $this->parameters[] = $values[0];
        $this->parameters[] = $values[1];
        return "{$column} BETWEEN {$minPlaceholder} AND {$maxPlaceholder}";
    }

    private function buildSimpleCondition(string $column, string $operator, mixed $value): string
    {
        $placeholder = $this->getPlaceholder($value);
        $this->parameters[] = $value;
        return "{$column} {$operator} {$placeholder}";
    }

    /**
     * Get the appropriate wpdb placeholder for a value
     *
     * @param mixed $value
     * @return string %d for integers, %f for floats, %s for strings
     */
    private function getPlaceholder(mixed $value): string
    {
        return match (true) {
            is_int($value) => '%d',
            is_float($value) => '%f',
            default => '%s',
        };
    }
}
