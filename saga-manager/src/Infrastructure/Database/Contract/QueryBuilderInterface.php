<?php

declare(strict_types=1);

namespace SagaManager\Infrastructure\Database\Contract;

/**
 * Query Builder Interface
 *
 * Fluent interface for building SQL queries in a database-agnostic way.
 *
 * @example
 *   $result = $db->query()
 *       ->select('e.id', 'e.canonical_name', 's.name AS saga_name')
 *       ->from('entities', 'e')
 *       ->join('sagas', 's', 'e.saga_id = s.id')
 *       ->where('e.entity_type', '=', 'character')
 *       ->where('e.importance_score', '>=', 80)
 *       ->orderBy('e.importance_score', 'DESC')
 *       ->limit(10)
 *       ->execute();
 */
interface QueryBuilderInterface
{
    /**
     * Specify columns to select
     *
     * @param string ...$columns Column names or expressions
     * @return static
     *
     * @example
     *   $qb->select('id', 'name', 'COUNT(*) AS total');
     */
    public function select(string ...$columns): static;

    /**
     * Specify the table to query from
     *
     * @param string $table Table name (without prefix)
     * @param string|null $alias Optional table alias
     * @return static
     *
     * @example
     *   $qb->from('entities', 'e');
     */
    public function from(string $table, ?string $alias = null): static;

    /**
     * Add an INNER JOIN clause
     *
     * @param string $table Table name (without prefix)
     * @param string $alias Table alias
     * @param string $condition Join condition
     * @return static
     *
     * @example
     *   $qb->join('sagas', 's', 'e.saga_id = s.id');
     */
    public function join(string $table, string $alias, string $condition): static;

    /**
     * Add a LEFT JOIN clause
     *
     * @param string $table Table name (without prefix)
     * @param string $alias Table alias
     * @param string $condition Join condition
     * @return static
     *
     * @example
     *   $qb->leftJoin('quality_metrics', 'qm', 'e.id = qm.entity_id');
     */
    public function leftJoin(string $table, string $alias, string $condition): static;

    /**
     * Add a RIGHT JOIN clause
     *
     * @param string $table Table name (without prefix)
     * @param string $alias Table alias
     * @param string $condition Join condition
     * @return static
     */
    public function rightJoin(string $table, string $alias, string $condition): static;

    /**
     * Add a WHERE condition
     *
     * @param string $column Column name
     * @param string $operator Comparison operator (=, !=, <, >, <=, >=, LIKE, IN, etc.)
     * @param mixed $value Value to compare against
     * @return static
     *
     * @example
     *   $qb->where('entity_type', '=', 'character')
     *      ->where('importance_score', '>=', 80);
     */
    public function where(string $column, string $operator, mixed $value): static;

    /**
     * Add a WHERE condition with OR
     *
     * @param string $column Column name
     * @param string $operator Comparison operator
     * @param mixed $value Value to compare against
     * @return static
     *
     * @example
     *   $qb->where('entity_type', '=', 'character')
     *      ->orWhere('entity_type', '=', 'faction');
     */
    public function orWhere(string $column, string $operator, mixed $value): static;

    /**
     * Add a WHERE IN condition
     *
     * @param string $column Column name
     * @param array<mixed> $values Array of values
     * @return static
     *
     * @example
     *   $qb->whereIn('id', [1, 2, 3, 4]);
     */
    public function whereIn(string $column, array $values): static;

    /**
     * Add a WHERE NOT IN condition
     *
     * @param string $column Column name
     * @param array<mixed> $values Array of values
     * @return static
     */
    public function whereNotIn(string $column, array $values): static;

    /**
     * Add a WHERE NULL condition
     *
     * @param string $column Column name
     * @return static
     *
     * @example
     *   $qb->whereNull('deleted_at');
     */
    public function whereNull(string $column): static;

    /**
     * Add a WHERE NOT NULL condition
     *
     * @param string $column Column name
     * @return static
     */
    public function whereNotNull(string $column): static;

    /**
     * Add a WHERE BETWEEN condition
     *
     * @param string $column Column name
     * @param mixed $min Minimum value
     * @param mixed $max Maximum value
     * @return static
     *
     * @example
     *   $qb->whereBetween('importance_score', 50, 100);
     */
    public function whereBetween(string $column, mixed $min, mixed $max): static;

    /**
     * Add an ORDER BY clause
     *
     * @param string $column Column name
     * @param string $direction 'ASC' or 'DESC'
     * @return static
     *
     * @example
     *   $qb->orderBy('importance_score', 'DESC')
     *      ->orderBy('canonical_name', 'ASC');
     */
    public function orderBy(string $column, string $direction = 'ASC'): static;

    /**
     * Add a GROUP BY clause
     *
     * @param string ...$columns Columns to group by
     * @return static
     *
     * @example
     *   $qb->groupBy('entity_type', 'saga_id');
     */
    public function groupBy(string ...$columns): static;

    /**
     * Add a HAVING condition
     *
     * @param string $column Column name
     * @param string $operator Comparison operator
     * @param mixed $value Value to compare against
     * @return static
     *
     * @example
     *   $qb->groupBy('entity_type')
     *      ->having('COUNT(*)', '>', 10);
     */
    public function having(string $column, string $operator, mixed $value): static;

    /**
     * Set the maximum number of rows to return
     *
     * @param int $limit Maximum rows
     * @return static
     *
     * @example
     *   $qb->limit(10);
     */
    public function limit(int $limit): static;

    /**
     * Set the number of rows to skip
     *
     * @param int $offset Rows to skip
     * @return static
     *
     * @example
     *   $qb->limit(10)->offset(20);
     */
    public function offset(int $offset): static;

    /**
     * Execute the query and return results
     *
     * @return ResultSetInterface
     */
    public function execute(): ResultSetInterface;

    /**
     * Get the first result or null
     *
     * @return array<string, mixed>|null
     */
    public function first(): ?array;

    /**
     * Get the count of matching rows
     *
     * @return int
     */
    public function count(): int;

    /**
     * Get the generated SQL query
     *
     * @return string
     */
    public function toSql(): string;

    /**
     * Get the bound parameters
     *
     * @return array<int|string, mixed>
     */
    public function getParameters(): array;

    /**
     * Reset the query builder to initial state
     *
     * @return static
     */
    public function reset(): static;
}
