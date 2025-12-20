<?php
declare(strict_types=1);

namespace SagaManagerCore\Infrastructure\Database\Port;

use SagaManagerCore\Infrastructure\Database\Exception\QueryException;

/**
 * Query Builder Port Interface
 *
 * Provides a fluent interface for building database queries.
 * Designed to be database-agnostic with support for complex operations.
 *
 * Usage:
 * ```php
 * $results = $connection->query()
 *     ->select(['id', 'name', 'type'])
 *     ->from('entities')
 *     ->where('saga_id', '=', 1)
 *     ->where('type', 'IN', ['character', 'location'])
 *     ->orderBy('importance_score', 'DESC')
 *     ->limit(20)
 *     ->execute();
 * ```
 *
 * @package SagaManagerCore\Infrastructure\Database\Port
 */
interface QueryBuilderInterface
{
    // =========================================================================
    // SELECT Operations
    // =========================================================================

    /**
     * Set columns to select
     *
     * @param array<string>|string $columns Column names or '*' for all
     * @return static
     */
    public function select(array|string $columns = '*'): static;

    /**
     * Add columns to select (append to existing)
     *
     * @param array<string>|string $columns
     * @return static
     */
    public function addSelect(array|string $columns): static;

    /**
     * Select distinct rows
     *
     * @return static
     */
    public function distinct(): static;

    /**
     * Add a raw expression to select
     *
     * @param string $expression Raw SQL expression
     * @param string|null $alias Optional alias
     * @return static
     */
    public function selectRaw(string $expression, ?string $alias = null): static;

    // =========================================================================
    // FROM Clause
    // =========================================================================

    /**
     * Set the table to query from
     *
     * @param string $table Table name (without prefixes)
     * @param string|null $alias Optional table alias
     * @return static
     */
    public function from(string $table, ?string $alias = null): static;

    /**
     * Set table for INSERT/UPDATE/DELETE operations
     *
     * @param string $table Table name (without prefixes)
     * @return static
     */
    public function table(string $table): static;

    // =========================================================================
    // JOIN Operations
    // =========================================================================

    /**
     * Add an INNER JOIN
     *
     * @param string $table Table to join (without prefixes)
     * @param string $first First column for join condition
     * @param string $operator Comparison operator
     * @param string $second Second column for join condition
     * @param string|null $alias Optional table alias
     * @return static
     */
    public function join(
        string $table,
        string $first,
        string $operator,
        string $second,
        ?string $alias = null
    ): static;

    /**
     * Add a LEFT JOIN
     *
     * @param string $table Table to join
     * @param string $first First column
     * @param string $operator Comparison operator
     * @param string $second Second column
     * @param string|null $alias Optional alias
     * @return static
     */
    public function leftJoin(
        string $table,
        string $first,
        string $operator,
        string $second,
        ?string $alias = null
    ): static;

    /**
     * Add a RIGHT JOIN
     *
     * @param string $table Table to join
     * @param string $first First column
     * @param string $operator Comparison operator
     * @param string $second Second column
     * @param string|null $alias Optional alias
     * @return static
     */
    public function rightJoin(
        string $table,
        string $first,
        string $operator,
        string $second,
        ?string $alias = null
    ): static;

    // =========================================================================
    // WHERE Conditions
    // =========================================================================

    /**
     * Add a WHERE condition
     *
     * @param string $column Column name
     * @param string $operator Comparison operator ('=', '!=', '<', '>', '<=', '>=', 'LIKE', 'IN', 'NOT IN', 'IS NULL', 'IS NOT NULL')
     * @param mixed $value Value to compare (ignored for IS NULL/IS NOT NULL)
     * @return static
     */
    public function where(string $column, string $operator, mixed $value = null): static;

    /**
     * Add a WHERE condition with OR
     *
     * @param string $column Column name
     * @param string $operator Comparison operator
     * @param mixed $value Value to compare
     * @return static
     */
    public function orWhere(string $column, string $operator, mixed $value = null): static;

    /**
     * Add a WHERE IN condition
     *
     * @param string $column Column name
     * @param array<mixed> $values Values to match
     * @return static
     */
    public function whereIn(string $column, array $values): static;

    /**
     * Add a WHERE NOT IN condition
     *
     * @param string $column Column name
     * @param array<mixed> $values Values to exclude
     * @return static
     */
    public function whereNotIn(string $column, array $values): static;

    /**
     * Add a WHERE NULL condition
     *
     * @param string $column Column name
     * @return static
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
     */
    public function whereBetween(string $column, mixed $min, mixed $max): static;

    /**
     * Add a WHERE LIKE condition
     *
     * @param string $column Column name
     * @param string $pattern LIKE pattern (use % for wildcards)
     * @return static
     */
    public function whereLike(string $column, string $pattern): static;

    /**
     * Add a raw WHERE condition (use with caution)
     *
     * @param string $sql Raw SQL condition
     * @param array<mixed> $bindings Parameter bindings
     * @return static
     */
    public function whereRaw(string $sql, array $bindings = []): static;

    /**
     * Add grouped WHERE conditions
     *
     * @param callable(QueryBuilderInterface): void $callback
     * @return static
     */
    public function whereGroup(callable $callback): static;

    /**
     * Add grouped WHERE conditions with OR
     *
     * @param callable(QueryBuilderInterface): void $callback
     * @return static
     */
    public function orWhereGroup(callable $callback): static;

    // =========================================================================
    // ORDER BY
    // =========================================================================

    /**
     * Add ORDER BY clause
     *
     * @param string $column Column name
     * @param string $direction 'ASC' or 'DESC'
     * @return static
     */
    public function orderBy(string $column, string $direction = 'ASC'): static;

    /**
     * Add ORDER BY with raw expression
     *
     * @param string $expression Raw SQL expression
     * @return static
     */
    public function orderByRaw(string $expression): static;

    // =========================================================================
    // GROUP BY / HAVING
    // =========================================================================

    /**
     * Add GROUP BY clause
     *
     * @param array<string>|string $columns
     * @return static
     */
    public function groupBy(array|string $columns): static;

    /**
     * Add HAVING condition
     *
     * @param string $column Column name
     * @param string $operator Comparison operator
     * @param mixed $value Value to compare
     * @return static
     */
    public function having(string $column, string $operator, mixed $value): static;

    /**
     * Add raw HAVING condition
     *
     * @param string $sql Raw SQL
     * @param array<mixed> $bindings
     * @return static
     */
    public function havingRaw(string $sql, array $bindings = []): static;

    // =========================================================================
    // LIMIT / OFFSET
    // =========================================================================

    /**
     * Set LIMIT clause
     *
     * @param int $limit Maximum rows to return
     * @return static
     */
    public function limit(int $limit): static;

    /**
     * Set OFFSET clause
     *
     * @param int $offset Number of rows to skip
     * @return static
     */
    public function offset(int $offset): static;

    /**
     * Convenience method for pagination
     *
     * @param int $page Page number (1-based)
     * @param int $perPage Items per page
     * @return static
     */
    public function paginate(int $page, int $perPage = 20): static;

    // =========================================================================
    // Subqueries
    // =========================================================================

    /**
     * Add a subquery as a column
     *
     * @param QueryBuilderInterface $subquery The subquery
     * @param string $alias Column alias
     * @return static
     */
    public function selectSubquery(QueryBuilderInterface $subquery, string $alias): static;

    /**
     * Add a subquery in WHERE clause
     *
     * @param string $column Column to compare
     * @param string $operator Comparison operator
     * @param QueryBuilderInterface $subquery The subquery
     * @return static
     */
    public function whereSubquery(string $column, string $operator, QueryBuilderInterface $subquery): static;

    /**
     * Add EXISTS condition
     *
     * @param QueryBuilderInterface $subquery The subquery
     * @return static
     */
    public function whereExists(QueryBuilderInterface $subquery): static;

    // =========================================================================
    // INSERT Operations
    // =========================================================================

    /**
     * Insert a single row
     *
     * @param array<string, mixed> $data Column => value pairs
     * @return ResultSetInterface
     * @throws QueryException
     */
    public function insert(array $data): ResultSetInterface;

    /**
     * Insert multiple rows
     *
     * @param array<array<string, mixed>> $rows Array of column => value pairs
     * @return ResultSetInterface
     * @throws QueryException
     */
    public function insertBatch(array $rows): ResultSetInterface;

    /**
     * Insert or update on duplicate key
     *
     * @param array<string, mixed> $data Column => value pairs
     * @param array<string, mixed> $updateOnDuplicate Columns to update on duplicate
     * @return ResultSetInterface
     * @throws QueryException
     */
    public function upsert(array $data, array $updateOnDuplicate): ResultSetInterface;

    // =========================================================================
    // UPDATE Operations
    // =========================================================================

    /**
     * Update rows matching current conditions
     *
     * @param array<string, mixed> $data Column => value pairs
     * @return ResultSetInterface
     * @throws QueryException
     */
    public function update(array $data): ResultSetInterface;

    /**
     * Increment a column value
     *
     * @param string $column Column to increment
     * @param int|float $amount Amount to increment by
     * @return ResultSetInterface
     * @throws QueryException
     */
    public function increment(string $column, int|float $amount = 1): ResultSetInterface;

    /**
     * Decrement a column value
     *
     * @param string $column Column to decrement
     * @param int|float $amount Amount to decrement by
     * @return ResultSetInterface
     * @throws QueryException
     */
    public function decrement(string $column, int|float $amount = 1): ResultSetInterface;

    // =========================================================================
    // DELETE Operations
    // =========================================================================

    /**
     * Delete rows matching current conditions
     *
     * @return ResultSetInterface
     * @throws QueryException
     */
    public function delete(): ResultSetInterface;

    /**
     * Truncate the table (delete all rows)
     *
     * @return ResultSetInterface
     * @throws QueryException
     */
    public function truncate(): ResultSetInterface;

    // =========================================================================
    // Execution
    // =========================================================================

    /**
     * Execute the query and return results
     *
     * @return ResultSetInterface
     * @throws QueryException
     */
    public function execute(): ResultSetInterface;

    /**
     * Get single row result
     *
     * @return array<string, mixed>|null
     * @throws QueryException
     */
    public function first(): ?array;

    /**
     * Get all results as array
     *
     * @return array<array<string, mixed>>
     * @throws QueryException
     */
    public function get(): array;

    /**
     * Get a single column value
     *
     * @param string $column Column name
     * @return mixed
     * @throws QueryException
     */
    public function value(string $column): mixed;

    /**
     * Get array of values for a single column
     *
     * @param string $column Column name
     * @return array<mixed>
     * @throws QueryException
     */
    public function pluck(string $column): array;

    /**
     * Get count of matching rows
     *
     * @param string $column Column to count (default '*')
     * @return int
     * @throws QueryException
     */
    public function count(string $column = '*'): int;

    /**
     * Check if any rows exist
     *
     * @return bool
     * @throws QueryException
     */
    public function exists(): bool;

    /**
     * Get sum of column values
     *
     * @param string $column Column name
     * @return float
     * @throws QueryException
     */
    public function sum(string $column): float;

    /**
     * Get average of column values
     *
     * @param string $column Column name
     * @return float
     * @throws QueryException
     */
    public function avg(string $column): float;

    /**
     * Get minimum value
     *
     * @param string $column Column name
     * @return mixed
     * @throws QueryException
     */
    public function min(string $column): mixed;

    /**
     * Get maximum value
     *
     * @param string $column Column name
     * @return mixed
     * @throws QueryException
     */
    public function max(string $column): mixed;

    // =========================================================================
    // Query Introspection
    // =========================================================================

    /**
     * Get the generated SQL query (for debugging)
     *
     * @return string
     */
    public function toSql(): string;

    /**
     * Get parameter bindings
     *
     * @return array<mixed>
     */
    public function getBindings(): array;

    /**
     * Clone the query builder (for reuse)
     *
     * @return static
     */
    public function clone(): static;

    /**
     * Reset the query builder to initial state
     *
     * @return static
     */
    public function reset(): static;
}
