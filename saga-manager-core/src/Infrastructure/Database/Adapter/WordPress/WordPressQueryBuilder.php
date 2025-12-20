<?php

declare(strict_types=1);

namespace SagaManagerCore\Infrastructure\Database\Adapter\WordPress;

use SagaManagerCore\Infrastructure\Database\Exception\QueryException;
use SagaManagerCore\Infrastructure\Database\Port\QueryBuilderInterface;
use SagaManagerCore\Infrastructure\Database\Port\ResultSetInterface;

/**
 * WordPress Query Builder Implementation
 *
 * Fluent interface for building SQL queries using WordPress $wpdb.
 * Automatically handles table prefixes and SQL injection prevention
 * via wpdb->prepare().
 *
 * Security: ALL user input is parameterized. No raw SQL concatenation.
 */
class WordPressQueryBuilder implements QueryBuilderInterface
{
    private WordPressConnection $connection;

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

    /** @var array<string, mixed> */
    private array $insertData = [];

    /** @var array<array<string, mixed>> */
    private array $insertBatchData = [];

    /** @var array<string, mixed> */
    private array $updateData = [];

    /** @var array<string, mixed> */
    private array $upsertUpdate = [];

    public function __construct(WordPressConnection $connection)
    {
        $this->connection = $connection;
    }

    // =========================================================================
    // SELECT Operations
    // =========================================================================

    public function select(array|string $columns = '*'): static
    {
        $this->type = 'select';
        $this->columns = is_array($columns) ? $columns : [$columns];

        return $this;
    }

    public function addSelect(array|string $columns): static
    {
        $columns = is_array($columns) ? $columns : [$columns];

        if ($this->columns === ['*']) {
            $this->columns = $columns;
        } else {
            $this->columns = array_merge($this->columns, $columns);
        }

        return $this;
    }

    public function distinct(): static
    {
        $this->distinct = true;

        return $this;
    }

    public function selectRaw(string $expression, ?string $alias = null): static
    {
        $this->rawSelects[] = $alias ? "{$expression} AS `{$alias}`" : $expression;

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
    // WHERE Conditions
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
            'type' => 'like',
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
        $direction = strtoupper($direction);

        $this->orders[] = [
            'column' => $column,
            'direction' => $direction === 'DESC' ? 'DESC' : 'ASC',
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
            'operator' => strtoupper($operator),
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
        $this->limitValue = max(0, $limit);

        return $this;
    }

    public function offset(int $offset): static
    {
        $this->offsetValue = max(0, $offset);

        return $this;
    }

    public function paginate(int $page, int $perPage = 20): static
    {
        $page = max(1, $page);
        $perPage = max(1, $perPage);

        $this->limitValue = $perPage;
        $this->offsetValue = ($page - 1) * $perPage;

        return $this;
    }

    // =========================================================================
    // Subqueries
    // =========================================================================

    public function selectSubquery(QueryBuilderInterface $subquery, string $alias): static
    {
        $this->rawSelects[] = sprintf('(%s) AS `%s`', $subquery->toSql(), $alias);
        $this->bindings = array_merge($this->bindings, $subquery->getBindings());

        return $this;
    }

    public function whereSubquery(string $column, string $operator, QueryBuilderInterface $subquery): static
    {
        $this->wheres[] = [
            'type' => 'subquery',
            'column' => $column,
            'operator' => strtoupper($operator),
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
        $this->type = 'insert';
        $this->insertData = $data;

        return $this->executeWrite();
    }

    public function insertBatch(array $rows): ResultSetInterface
    {
        if (empty($rows)) {
            return WordPressResultSet::empty();
        }

        $this->type = 'insert_batch';
        $this->insertBatchData = $rows;

        return $this->executeWrite();
    }

    public function upsert(array $data, array $updateOnDuplicate): ResultSetInterface
    {
        $this->type = 'upsert';
        $this->insertData = $data;
        $this->upsertUpdate = $updateOnDuplicate;

        return $this->executeWrite();
    }

    // =========================================================================
    // UPDATE Operations
    // =========================================================================

    public function update(array $data): ResultSetInterface
    {
        $this->type = 'update';
        $this->updateData = $data;

        return $this->executeWrite();
    }

    public function increment(string $column, int|float $amount = 1): ResultSetInterface
    {
        $this->type = 'update';
        $this->updateData = [
            $column => new RawExpression("`{$column}` + {$amount}"),
        ];

        return $this->executeWrite();
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
        $this->type = 'delete';

        return $this->executeWrite();
    }

    public function truncate(): ResultSetInterface
    {
        $this->type = 'truncate';

        return $this->executeWrite();
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
        $result = $this->execute();

        return $result->first();
    }

    public function get(): array
    {
        return $this->execute()->toArray();
    }

    public function value(string $column): mixed
    {
        $this->columns = [$column];
        $this->limitValue = 1;

        $result = $this->execute()->first();

        return $result[$column] ?? null;
    }

    public function pluck(string $column): array
    {
        $this->columns = [$column];

        return array_column($this->execute()->toArray(), $column);
    }

    public function count(string $column = '*'): int
    {
        $originalColumns = $this->columns;
        $originalRawSelects = $this->rawSelects;

        $this->columns = [];
        $this->rawSelects = ["COUNT({$column}) AS aggregate"];

        $result = $this->execute()->first();

        $this->columns = $originalColumns;
        $this->rawSelects = $originalRawSelects;

        return (int) ($result['aggregate'] ?? 0);
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
        $this->bindings = [];

        return match ($this->type) {
            'select' => $this->buildSelectSql(),
            'insert' => $this->buildInsertSql(),
            'insert_batch' => $this->buildInsertBatchSql(),
            'upsert' => $this->buildUpsertSql(),
            'update' => $this->buildUpdateSql(),
            'delete' => $this->buildDeleteSql(),
            'truncate' => $this->buildTruncateSql(),
            default => throw new QueryException("Unknown query type: {$this->type}"),
        };
    }

    public function getBindings(): array
    {
        return $this->bindings;
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
        $this->insertData = [];
        $this->insertBatchData = [];
        $this->updateData = [];
        $this->upsertUpdate = [];

        return $this;
    }

    // =========================================================================
    // Internal Helpers
    // =========================================================================

    /**
     * Get the internal where conditions for grouped queries
     *
     * @return array<array{type: string, column: string, operator: string, value: mixed, boolean: string}>
     */
    public function getWhereConditions(): array
    {
        return $this->wheres;
    }

    /**
     * Create a raw expression (bypasses escaping)
     */
    public function raw(string $expression): RawExpression
    {
        return new RawExpression($expression);
    }

    // =========================================================================
    // SQL Building Methods
    // =========================================================================

    private function buildSelectSql(): string
    {
        $sql = $this->distinct ? 'SELECT DISTINCT ' : 'SELECT ';

        // Build columns
        $columnsParts = [];

        foreach ($this->columns as $column) {
            if ($column === '*') {
                $columnsParts[] = '*';
            } else {
                $columnsParts[] = $this->quoteIdentifier($column);
            }
        }

        foreach ($this->rawSelects as $raw) {
            $columnsParts[] = $raw;
        }

        $sql .= implode(', ', $columnsParts);
        $sql .= ' FROM ' . $this->buildTableReference();
        $sql .= $this->buildJoinsSql();
        $sql .= $this->buildWhereSql();
        $sql .= $this->buildGroupBySql();
        $sql .= $this->buildHavingSql();
        $sql .= $this->buildOrderBySql();
        $sql .= $this->buildLimitSql();

        return $sql;
    }

    private function buildInsertSql(): string
    {
        if (empty($this->insertData)) {
            throw new QueryException('No data provided for INSERT');
        }

        $table = $this->getFullTableName();
        $columns = array_keys($this->insertData);
        $placeholders = [];

        foreach ($this->insertData as $value) {
            $placeholders[] = $this->addBinding($value);
        }

        return sprintf(
            'INSERT INTO %s (%s) VALUES (%s)',
            $table,
            implode(', ', array_map(fn($c) => $this->quoteIdentifier($c), $columns)),
            implode(', ', $placeholders)
        );
    }

    private function buildInsertBatchSql(): string
    {
        if (empty($this->insertBatchData)) {
            throw new QueryException('No data provided for INSERT batch');
        }

        $table = $this->getFullTableName();
        $columns = array_keys($this->insertBatchData[0]);
        $valuesSets = [];

        foreach ($this->insertBatchData as $row) {
            $placeholders = [];
            foreach ($columns as $column) {
                $value = $row[$column] ?? null;
                $placeholders[] = $this->addBinding($value);
            }
            $valuesSets[] = '(' . implode(', ', $placeholders) . ')';
        }

        return sprintf(
            'INSERT INTO %s (%s) VALUES %s',
            $table,
            implode(', ', array_map(fn($c) => $this->quoteIdentifier($c), $columns)),
            implode(', ', $valuesSets)
        );
    }

    private function buildUpsertSql(): string
    {
        if (empty($this->insertData)) {
            throw new QueryException('No data provided for UPSERT');
        }

        $table = $this->getFullTableName();
        $columns = array_keys($this->insertData);
        $placeholders = [];

        foreach ($this->insertData as $value) {
            $placeholders[] = $this->addBinding($value);
        }

        $sql = sprintf(
            'INSERT INTO %s (%s) VALUES (%s)',
            $table,
            implode(', ', array_map(fn($c) => $this->quoteIdentifier($c), $columns)),
            implode(', ', $placeholders)
        );

        if (!empty($this->upsertUpdate)) {
            $updates = [];
            foreach ($this->upsertUpdate as $column => $value) {
                if ($value instanceof RawExpression) {
                    $updates[] = $this->quoteIdentifier($column) . ' = ' . $value->getValue();
                } else {
                    $updates[] = $this->quoteIdentifier($column) . ' = ' . $this->addBinding($value);
                }
            }
            $sql .= ' ON DUPLICATE KEY UPDATE ' . implode(', ', $updates);
        }

        return $sql;
    }

    private function buildUpdateSql(): string
    {
        if (empty($this->updateData)) {
            throw new QueryException('No data provided for UPDATE');
        }

        $table = $this->getFullTableName();
        $sets = [];

        foreach ($this->updateData as $column => $value) {
            if ($value instanceof RawExpression) {
                $sets[] = $this->quoteIdentifier($column) . ' = ' . $value->getValue();
            } else {
                $sets[] = $this->quoteIdentifier($column) . ' = ' . $this->addBinding($value);
            }
        }

        $sql = sprintf('UPDATE %s SET %s', $table, implode(', ', $sets));
        $sql .= $this->buildWhereSql();

        return $sql;
    }

    private function buildDeleteSql(): string
    {
        $sql = sprintf('DELETE FROM %s', $this->getFullTableName());
        $sql .= $this->buildWhereSql();

        return $sql;
    }

    private function buildTruncateSql(): string
    {
        return sprintf('TRUNCATE TABLE %s', $this->getFullTableName());
    }

    private function buildTableReference(): string
    {
        $table = $this->getFullTableName();

        if ($this->tableAlias !== null) {
            return $table . ' AS ' . $this->quoteIdentifier($this->tableAlias);
        }

        return $table;
    }

    private function buildJoinsSql(): string
    {
        if (empty($this->joins)) {
            return '';
        }

        $sql = '';

        foreach ($this->joins as $join) {
            $table = $this->connection->getFullTableName($join['table']);
            $tableRef = $join['alias']
                ? $table . ' AS ' . $this->quoteIdentifier($join['alias'])
                : $table;

            $sql .= sprintf(
                ' %s JOIN %s ON %s %s %s',
                $join['type'],
                $tableRef,
                $this->quoteIdentifier($join['first']),
                $join['operator'],
                $this->quoteIdentifier($join['second'])
            );
        }

        return $sql;
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

        return ' WHERE ' . implode(' ', $conditions);
    }

    private function buildWhereCondition(array $where): string
    {
        return match ($where['type']) {
            'basic' => sprintf(
                '%s %s %s',
                $this->quoteIdentifier($where['column']),
                $where['operator'],
                $this->addBinding($where['value'])
            ),
            'in' => sprintf(
                '%s %s (%s)',
                $this->quoteIdentifier($where['column']),
                $where['operator'],
                $this->buildInPlaceholders($where['value'])
            ),
            'null' => sprintf(
                '%s %s',
                $this->quoteIdentifier($where['column']),
                $where['operator']
            ),
            'between' => sprintf(
                '%s BETWEEN %s AND %s',
                $this->quoteIdentifier($where['column']),
                $this->addBinding($where['value'][0]),
                $this->addBinding($where['value'][1])
            ),
            'like' => sprintf(
                '%s LIKE %s',
                $this->quoteIdentifier($where['column']),
                $this->addBinding($where['value'])
            ),
            'raw' => $this->processRawWhere($where),
            'group' => '(' . $this->buildGroupedConditions($where['value']) . ')',
            'subquery' => sprintf(
                '%s %s (%s)',
                $this->quoteIdentifier($where['column']),
                $where['operator'],
                $this->processSubquery($where['value'])
            ),
            'exists' => sprintf('EXISTS (%s)', $this->processSubquery($where['value'])),
            default => throw new QueryException("Unknown where type: {$where['type']}"),
        };
    }

    private function processRawWhere(array $where): string
    {
        foreach ($where['value'] as $binding) {
            $this->bindings[] = $binding;
        }

        return $where['column'];
    }

    private function processSubquery(QueryBuilderInterface $subquery): string
    {
        $sql = $subquery->toSql();
        $this->bindings = array_merge($this->bindings, $subquery->getBindings());

        return $sql;
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

        $columns = array_map(fn($col) => $this->quoteIdentifier($col), $this->groups);

        return ' GROUP BY ' . implode(', ', $columns);
    }

    private function buildHavingSql(): string
    {
        if (empty($this->havings)) {
            return '';
        }

        $conditions = [];

        foreach ($this->havings as $having) {
            if ($having['operator'] === 'RAW') {
                foreach ($having['value'] as $binding) {
                    $this->bindings[] = $binding;
                }
                $conditions[] = $having['column'];
            } else {
                $conditions[] = sprintf(
                    '%s %s %s',
                    $this->quoteIdentifier($having['column']),
                    $having['operator'],
                    $this->addBinding($having['value'])
                );
            }
        }

        return ' HAVING ' . implode(' AND ', $conditions);
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
                $parts[] = $this->quoteIdentifier($order['column']) . ' ' . $order['direction'];
            }
        }

        return ' ORDER BY ' . implode(', ', $parts);
    }

    private function buildLimitSql(): string
    {
        $sql = '';

        if ($this->limitValue !== null) {
            $sql .= ' LIMIT ' . $this->limitValue;
        }

        if ($this->offsetValue !== null && $this->offsetValue > 0) {
            $sql .= ' OFFSET ' . $this->offsetValue;
        }

        return $sql;
    }

    // =========================================================================
    // Helper Methods
    // =========================================================================

    private function executeWrite(): ResultSetInterface
    {
        $sql = $this->toSql();
        $bindings = $this->getBindings();

        return $this->connection->raw($sql, $bindings);
    }

    private function aggregate(string $function, string $column): mixed
    {
        $originalColumns = $this->columns;
        $originalRawSelects = $this->rawSelects;

        $this->columns = [];
        $quotedColumn = $column === '*' ? '*' : $this->quoteIdentifier($column);
        $this->rawSelects = ["{$function}({$quotedColumn}) AS aggregate"];

        $result = $this->execute()->first();

        $this->columns = $originalColumns;
        $this->rawSelects = $originalRawSelects;

        return $result['aggregate'] ?? null;
    }

    private function getFullTableName(): string
    {
        if ($this->table === null) {
            throw new QueryException('No table specified for query');
        }

        return $this->connection->getFullTableName($this->table);
    }

    private function newBuilder(): self
    {
        return new self($this->connection);
    }

    private function quoteIdentifier(string $identifier): string
    {
        // Handle qualified names (table.column)
        if (str_contains($identifier, '.')) {
            $parts = explode('.', $identifier);

            return implode('.', array_map(fn($p) => '`' . str_replace('`', '``', $p) . '`', $parts));
        }

        // Handle aliases (column AS alias)
        if (preg_match('/^(.+)\s+AS\s+(.+)$/i', $identifier, $matches)) {
            return $this->quoteIdentifier(trim($matches[1])) . ' AS `' . str_replace('`', '``', trim($matches[2])) . '`';
        }

        return '`' . str_replace('`', '``', $identifier) . '`';
    }

    private function addBinding(mixed $value): string
    {
        if ($value === null) {
            return 'NULL';
        }

        if ($value instanceof RawExpression) {
            return $value->getValue();
        }

        $this->bindings[] = $value;

        // Return placeholder based on type
        if (is_int($value)) {
            return '%d';
        } elseif (is_float($value)) {
            return '%f';
        } else {
            return '%s';
        }
    }

    /**
     * @param array<mixed> $values
     */
    private function buildInPlaceholders(array $values): string
    {
        $placeholders = [];

        foreach ($values as $value) {
            $placeholders[] = $this->addBinding($value);
        }

        return implode(', ', $placeholders);
    }
}
