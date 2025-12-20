<?php
declare(strict_types=1);

namespace SagaManagerCore\Infrastructure\Database\Adapter\PDO;

use PDO;
use SagaManagerCore\Infrastructure\Database\Port\QueryBuilderInterface;
use SagaManagerCore\Infrastructure\Database\Port\ResultSetInterface;
use SagaManagerCore\Infrastructure\Database\ResultSet;
use SagaManagerCore\Infrastructure\Database\Exception\QueryException;

/**
 * PDO Query Builder Implementation
 *
 * Fluent interface for building SQL queries using PDO prepared statements.
 * Uses positional placeholders for security.
 *
 * @package SagaManagerCore\Infrastructure\Database\Adapter\PDO
 */
class PDOQueryBuilder implements QueryBuilderInterface
{
    private PDOConnection $connection;

    private string $type = 'select';
    /** @var array<string> */
    private array $columns = ['*'];
    private bool $distinct = false;
    private ?string $table = null;
    private ?string $tableAlias = null;
    /** @var array<array{type: string, table: string, first: string, operator: string, second: string, alias: ?string}> */
    private array $joins = [];
    /** @var array<array{type: string, column: string, operator: string, value: mixed, boolean: string}> */
    private array $wheres = [];
    /** @var array<string> */
    private array $groups = [];
    /** @var array<array{column: string, operator: string, value: mixed}> */
    private array $havings = [];
    /** @var array<array{column: string, direction: string}> */
    private array $orders = [];
    private ?int $limitValue = null;
    private ?int $offsetValue = null;
    /** @var array<mixed> */
    private array $bindings = [];
    /** @var array<string> */
    private array $rawSelects = [];

    public function __construct(PDOConnection $connection)
    {
        $this->connection = $connection;
    }

    // =========================================================================
    // SELECT Operations
    // =========================================================================

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
        $this->rawSelects[] = $alias ? "$expression AS $alias" : $expression;
        return $this;
    }

    // =========================================================================
    // FROM Clause
    // =========================================================================

    public function from(string $table, ?string $alias = null): static
    {
        $this->table = $table;
        $this->tableAlias = $alias;
        return $this;
    }

    public function table(string $table): static
    {
        $this->table = $table;
        return $this;
    }

    // =========================================================================
    // JOIN Operations
    // =========================================================================

    public function join(
        string $table,
        string $first,
        string $operator,
        string $second,
        ?string $alias = null
    ): static {
        $this->joins[] = [
            'type' => 'INNER',
            'table' => $table,
            'first' => $first,
            'operator' => $operator,
            'second' => $second,
            'alias' => $alias,
        ];
        return $this;
    }

    public function leftJoin(
        string $table,
        string $first,
        string $operator,
        string $second,
        ?string $alias = null
    ): static {
        $this->joins[] = [
            'type' => 'LEFT',
            'table' => $table,
            'first' => $first,
            'operator' => $operator,
            'second' => $second,
            'alias' => $alias,
        ];
        return $this;
    }

    public function rightJoin(
        string $table,
        string $first,
        string $operator,
        string $second,
        ?string $alias = null
    ): static {
        $this->joins[] = [
            'type' => 'RIGHT',
            'table' => $table,
            'first' => $first,
            'operator' => $operator,
            'second' => $second,
            'alias' => $alias,
        ];
        return $this;
    }

    // =========================================================================
    // WHERE Conditions (using positional placeholders)
    // =========================================================================

    public function where(string $column, string $operator, mixed $value = null): static
    {
        $this->wheres[] = [
            'type' => 'basic',
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
            'type' => 'basic',
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
            'type' => 'in',
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
            'type' => 'in',
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
            'type' => 'null',
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
            'type' => 'null',
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
            'type' => 'between',
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
            'type' => 'basic',
            'column' => $column,
            'operator' => 'LIKE',
            'value' => $pattern,
            'boolean' => 'AND',
        ];
        return $this;
    }

    public function whereRaw(string $sql, array $bindings = []): static
    {
        $this->wheres[] = [
            'type' => 'raw',
            'column' => $sql,
            'operator' => '',
            'value' => $bindings,
            'boolean' => 'AND',
        ];
        return $this;
    }

    public function whereGroup(callable $callback): static
    {
        $builder = $this->newBuilder();
        $callback($builder);

        $this->wheres[] = [
            'type' => 'group',
            'column' => '',
            'operator' => '',
            'value' => $builder->getWhereConditions(),
            'boolean' => 'AND',
        ];
        return $this;
    }

    public function orWhereGroup(callable $callback): static
    {
        $builder = $this->newBuilder();
        $callback($builder);

        $this->wheres[] = [
            'type' => 'group',
            'column' => '',
            'operator' => '',
            'value' => $builder->getWhereConditions(),
            'boolean' => 'OR',
        ];
        return $this;
    }

    // =========================================================================
    // ORDER BY
    // =========================================================================

    public function orderBy(string $column, string $direction = 'ASC'): static
    {
        $this->orders[] = [
            'column' => $column,
            'direction' => strtoupper($direction) === 'DESC' ? 'DESC' : 'ASC',
        ];
        return $this;
    }

    public function orderByRaw(string $expression): static
    {
        $this->orders[] = [
            'column' => $expression,
            'direction' => 'RAW',
        ];
        return $this;
    }

    // =========================================================================
    // GROUP BY / HAVING
    // =========================================================================

    public function groupBy(array|string $columns): static
    {
        $columns = is_array($columns) ? $columns : [$columns];
        $this->groups = array_merge($this->groups, $columns);
        return $this;
    }

    public function having(string $column, string $operator, mixed $value): static
    {
        $this->havings[] = [
            'column' => $column,
            'operator' => $operator,
            'value' => $value,
        ];
        return $this;
    }

    public function havingRaw(string $sql, array $bindings = []): static
    {
        $this->havings[] = [
            'column' => $sql,
            'operator' => 'RAW',
            'value' => $bindings,
        ];
        return $this;
    }

    // =========================================================================
    // LIMIT / OFFSET
    // =========================================================================

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

    // =========================================================================
    // Subqueries
    // =========================================================================

    public function selectSubquery(QueryBuilderInterface $subquery, string $alias): static
    {
        $this->rawSelects[] = sprintf('(%s) AS %s', $subquery->toSql(), $alias);
        $this->bindings = array_merge($this->bindings, $subquery->getBindings());
        return $this;
    }

    public function whereSubquery(string $column, string $operator, QueryBuilderInterface $subquery): static
    {
        $this->wheres[] = [
            'type' => 'subquery',
            'column' => $column,
            'operator' => $operator,
            'value' => $subquery,
            'boolean' => 'AND',
        ];
        return $this;
    }

    public function whereExists(QueryBuilderInterface $subquery): static
    {
        $this->wheres[] = [
            'type' => 'exists',
            'column' => '',
            'operator' => 'EXISTS',
            'value' => $subquery,
            'boolean' => 'AND',
        ];
        return $this;
    }

    // =========================================================================
    // INSERT Operations
    // =========================================================================

    public function insert(array $data): ResultSetInterface
    {
        $tableName = $this->getFullTableName();
        $columns = array_keys($data);
        $values = array_values($data);
        $placeholders = array_fill(0, count($values), '?');

        $sql = sprintf(
            'INSERT INTO %s (%s) VALUES (%s)',
            $tableName,
            implode(', ', array_map(fn($c) => "`$c`", $columns)),
            implode(', ', $placeholders)
        );

        return $this->connection->raw($sql, $values);
    }

    public function insertBatch(array $rows): ResultSetInterface
    {
        if (empty($rows)) {
            return ResultSet::empty();
        }

        $tableName = $this->getFullTableName();
        $columns = array_keys($rows[0]);
        $placeholders = [];
        $values = [];

        foreach ($rows as $row) {
            $rowPlaceholders = array_fill(0, count($columns), '?');
            $placeholders[] = '(' . implode(', ', $rowPlaceholders) . ')';
            foreach ($columns as $column) {
                $values[] = $row[$column] ?? null;
            }
        }

        $sql = sprintf(
            'INSERT INTO %s (%s) VALUES %s',
            $tableName,
            implode(', ', array_map(fn($c) => "`$c`", $columns)),
            implode(', ', $placeholders)
        );

        return $this->connection->raw($sql, $values);
    }

    public function upsert(array $data, array $updateOnDuplicate): ResultSetInterface
    {
        $tableName = $this->getFullTableName();
        $columns = array_keys($data);
        $values = array_values($data);
        $placeholders = array_fill(0, count($values), '?');

        $updateParts = [];
        foreach (array_keys($updateOnDuplicate) as $column) {
            $updateParts[] = "`$column` = ?";
            $values[] = $updateOnDuplicate[$column];
        }

        $sql = sprintf(
            'INSERT INTO %s (%s) VALUES (%s) ON DUPLICATE KEY UPDATE %s',
            $tableName,
            implode(', ', array_map(fn($c) => "`$c`", $columns)),
            implode(', ', $placeholders),
            implode(', ', $updateParts)
        );

        return $this->connection->raw($sql, $values);
    }

    // =========================================================================
    // UPDATE Operations
    // =========================================================================

    public function update(array $data): ResultSetInterface
    {
        $tableName = $this->getFullTableName();
        $setParts = [];
        $values = [];

        foreach ($data as $column => $value) {
            $setParts[] = "`$column` = ?";
            $values[] = $value;
        }

        $whereSql = $this->buildWhereSql();
        $whereBindings = $this->getWhereBindings();
        $values = array_merge($values, $whereBindings);

        $sql = sprintf('UPDATE %s SET %s %s', $tableName, implode(', ', $setParts), $whereSql);

        return $this->connection->raw($sql, $values);
    }

    public function increment(string $column, int|float $amount = 1): ResultSetInterface
    {
        $tableName = $this->getFullTableName();
        $whereSql = $this->buildWhereSql();
        $whereBindings = $this->getWhereBindings();

        $sql = sprintf('UPDATE %s SET `%s` = `%s` + ? %s', $tableName, $column, $column, $whereSql);
        $values = array_merge([$amount], $whereBindings);

        return $this->connection->raw($sql, $values);
    }

    public function decrement(string $column, int|float $amount = 1): ResultSetInterface
    {
        return $this->increment($column, -$amount);
    }

    // =========================================================================
    // DELETE Operations
    // =========================================================================

    public function delete(): ResultSetInterface
    {
        $tableName = $this->getFullTableName();
        $whereSql = $this->buildWhereSql();
        $whereBindings = $this->getWhereBindings();

        $sql = sprintf('DELETE FROM %s %s', $tableName, $whereSql);

        return $this->connection->raw($sql, $whereBindings);
    }

    public function truncate(): ResultSetInterface
    {
        $tableName = $this->getFullTableName();
        return $this->connection->raw("TRUNCATE TABLE {$tableName}");
    }

    // =========================================================================
    // Execution
    // =========================================================================

    public function execute(): ResultSetInterface
    {
        $sql = $this->toSql();
        $bindings = $this->getBindings();
        return $this->connection->raw($sql, $bindings);
    }

    public function first(): ?array
    {
        $this->limitValue = 1;
        return $this->execute()->first();
    }

    public function get(): array
    {
        return $this->execute()->all();
    }

    public function value(string $column): mixed
    {
        $this->columns = [$column];
        $this->limitValue = 1;
        return $this->execute()->value($column);
    }

    public function pluck(string $column): array
    {
        $this->columns = [$column];
        return $this->execute()->pluck($column);
    }

    public function count(string $column = '*'): int
    {
        $sql = sprintf(
            'SELECT COUNT(%s) as aggregate FROM %s %s %s',
            $column,
            $this->getFullTableName(),
            $this->buildJoinsSql(),
            $this->buildWhereSql()
        );

        $result = $this->connection->raw($sql, $this->getWhereBindings());
        return (int) $result->value('aggregate', 0);
    }

    public function exists(): bool
    {
        return $this->count() > 0;
    }

    public function sum(string $column): float
    {
        return (float) $this->aggregate('SUM', $column);
    }

    public function avg(string $column): float
    {
        return (float) $this->aggregate('AVG', $column);
    }

    public function min(string $column): mixed
    {
        return $this->aggregate('MIN', $column);
    }

    public function max(string $column): mixed
    {
        return $this->aggregate('MAX', $column);
    }

    // =========================================================================
    // Query Introspection
    // =========================================================================

    public function toSql(): string
    {
        return $this->buildSelectSql();
    }

    public function getBindings(): array
    {
        return array_merge($this->bindings, $this->getWhereBindings());
    }

    public function clone(): static
    {
        return clone $this;
    }

    public function reset(): static
    {
        $this->type = 'select';
        $this->columns = ['*'];
        $this->distinct = false;
        $this->table = null;
        $this->tableAlias = null;
        $this->joins = [];
        $this->wheres = [];
        $this->groups = [];
        $this->havings = [];
        $this->orders = [];
        $this->limitValue = null;
        $this->offsetValue = null;
        $this->bindings = [];
        $this->rawSelects = [];
        return $this;
    }

    /**
     * @return array<array{type: string, column: string, operator: string, value: mixed, boolean: string}>
     */
    public function getWhereConditions(): array
    {
        return $this->wheres;
    }

    // =========================================================================
    // Internal Helpers
    // =========================================================================

    private function getFullTableName(): string
    {
        if ($this->table === null) {
            throw new \RuntimeException('No table specified for query');
        }
        return $this->connection->getSagaTablePrefix() . $this->table;
    }

    private function newBuilder(): self
    {
        return new self($this->connection);
    }

    private function buildSelectSql(): string
    {
        $columns = $this->columns;
        if (!empty($this->rawSelects)) {
            $columns = array_merge($columns, $this->rawSelects);
        }

        $select = $this->distinct ? 'SELECT DISTINCT' : 'SELECT';
        $columnsStr = implode(', ', $columns);

        $tableName = $this->getFullTableName();
        $tableRef = $this->tableAlias ? "{$tableName} AS {$this->tableAlias}" : $tableName;

        $parts = [
            $select,
            $columnsStr,
            'FROM',
            $tableRef,
            $this->buildJoinsSql(),
            $this->buildWhereSql(),
            $this->buildGroupBySql(),
            $this->buildHavingSql(),
            $this->buildOrderBySql(),
            $this->buildLimitSql(),
        ];

        return implode(' ', array_filter($parts));
    }

    private function buildJoinsSql(): string
    {
        if (empty($this->joins)) {
            return '';
        }

        $sql = [];
        foreach ($this->joins as $join) {
            $tableName = $this->connection->getSagaTablePrefix() . $join['table'];
            $tableRef = $join['alias'] ? "{$tableName} AS {$join['alias']}" : $tableName;
            $sql[] = sprintf(
                '%s JOIN %s ON %s %s %s',
                $join['type'],
                $tableRef,
                $join['first'],
                $join['operator'],
                $join['second']
            );
        }

        return implode(' ', $sql);
    }

    private function buildWhereSql(): string
    {
        if (empty($this->wheres)) {
            return '';
        }

        $conditions = [];
        foreach ($this->wheres as $index => $where) {
            $condition = $this->buildWhereCondition($where);
            if ($index === 0) {
                $conditions[] = $condition;
            } else {
                $conditions[] = $where['boolean'] . ' ' . $condition;
            }
        }

        return 'WHERE ' . implode(' ', $conditions);
    }

    private function buildWhereCondition(array $where): string
    {
        return match ($where['type']) {
            'basic' => sprintf('%s %s ?', $where['column'], $where['operator']),
            'in' => sprintf('%s %s (%s)', $where['column'], $where['operator'], implode(', ', array_fill(0, count($where['value']), '?'))),
            'null' => sprintf('%s %s', $where['column'], $where['operator']),
            'between' => sprintf('%s BETWEEN ? AND ?', $where['column']),
            'raw' => $where['column'],
            'group' => '(' . $this->buildGroupedConditions($where['value']) . ')',
            'subquery' => sprintf('%s %s (%s)', $where['column'], $where['operator'], $where['value']->toSql()),
            'exists' => sprintf('EXISTS (%s)', $where['value']->toSql()),
            default => throw new \RuntimeException("Unknown where type: {$where['type']}"),
        };
    }

    /**
     * @param array<array{type: string, column: string, operator: string, value: mixed, boolean: string}> $conditions
     */
    private function buildGroupedConditions(array $conditions): string
    {
        $parts = [];
        foreach ($conditions as $index => $condition) {
            $built = $this->buildWhereCondition($condition);
            if ($index === 0) {
                $parts[] = $built;
            } else {
                $parts[] = $condition['boolean'] . ' ' . $built;
            }
        }
        return implode(' ', $parts);
    }

    private function buildGroupBySql(): string
    {
        if (empty($this->groups)) {
            return '';
        }
        return 'GROUP BY ' . implode(', ', $this->groups);
    }

    private function buildHavingSql(): string
    {
        if (empty($this->havings)) {
            return '';
        }

        $conditions = [];
        foreach ($this->havings as $having) {
            if ($having['operator'] === 'RAW') {
                $conditions[] = $having['column'];
            } else {
                $conditions[] = sprintf('%s %s ?', $having['column'], $having['operator']);
            }
        }

        return 'HAVING ' . implode(' AND ', $conditions);
    }

    private function buildOrderBySql(): string
    {
        if (empty($this->orders)) {
            return '';
        }

        $parts = [];
        foreach ($this->orders as $order) {
            if ($order['direction'] === 'RAW') {
                $parts[] = $order['column'];
            } else {
                $parts[] = sprintf('%s %s', $order['column'], $order['direction']);
            }
        }

        return 'ORDER BY ' . implode(', ', $parts);
    }

    private function buildLimitSql(): string
    {
        if ($this->limitValue === null) {
            return '';
        }

        $sql = 'LIMIT ' . $this->limitValue;
        if ($this->offsetValue !== null) {
            $sql .= ' OFFSET ' . $this->offsetValue;
        }

        return $sql;
    }

    /**
     * @return array<mixed>
     */
    private function getWhereBindings(): array
    {
        $bindings = [];
        foreach ($this->wheres as $where) {
            $bindings = array_merge($bindings, $this->extractBindings($where));
        }
        return $bindings;
    }

    /**
     * @return array<mixed>
     */
    private function extractBindings(array $where): array
    {
        return match ($where['type']) {
            'basic' => [$where['value']],
            'in' => $where['value'],
            'null' => [],
            'between' => $where['value'],
            'raw' => $where['value'],
            'group' => array_reduce($where['value'], fn($carry, $w) => array_merge($carry, $this->extractBindings($w)), []),
            'subquery', 'exists' => $where['value']->getBindings(),
            default => [],
        };
    }

    private function aggregate(string $function, string $column): mixed
    {
        $sql = sprintf(
            'SELECT %s(%s) as aggregate FROM %s %s %s',
            $function,
            $column,
            $this->getFullTableName(),
            $this->buildJoinsSql(),
            $this->buildWhereSql()
        );

        $result = $this->connection->raw($sql, $this->getWhereBindings());
        return $result->value('aggregate');
    }
}
