<?php
declare(strict_types=1);

namespace SagaManagerCore\Domain\Port\Database;

use SagaManagerCore\Domain\Exception\DatabaseException;

/**
 * Port interface for fluent SQL query building
 *
 * Provides a type-safe, fluent interface for constructing SQL queries.
 * All methods return self for chaining. Immutable: each method returns a new instance.
 *
 * IMPORTANT: Table names should NOT include the prefix - implementations handle prefixing.
 *
 * @example
 * ```php
 * // SELECT query with conditions
 * $entities = $db->query()
 *     ->select(['id', 'canonical_name', 'entity_type'])
 *     ->from('saga_entities')
 *     ->where('saga_id', '=', 1)
 *     ->where('entity_type', '=', 'character')
 *     ->orderBy('importance_score', 'DESC')
 *     ->limit(10)
 *     ->execute()
 *     ->fetchAll();
 *
 * // JOIN query
 * $entitiesWithValues = $db->query()
 *     ->select(['e.id', 'e.canonical_name', 'av.value_string'])
 *     ->from('saga_entities', 'e')
 *     ->join('saga_attribute_values', 'av.entity_id = e.id', 'LEFT', 'av')
 *     ->where('e.saga_id', '=', 1)
 *     ->execute();
 *
 * // INSERT
 * $newId = $db->query()
 *     ->insert('saga_entities', [
 *         'saga_id' => 1,
 *         'entity_type' => 'character',
 *         'canonical_name' => 'Paul Atreides',
 *         'slug' => 'paul-atreides',
 *     ]);
 *
 * // UPDATE
 * $affected = $db->query()
 *     ->update('saga_entities', ['importance_score' => 95], ['id' => 123]);
 *
 * // DELETE
 * $deleted = $db->query()
 *     ->delete('saga_entities', ['id' => 123]);
 *
 * // Debug query
 * $builder = $db->query()
 *     ->select('*')
 *     ->from('saga_entities')
 *     ->where('saga_id', '=', 1);
 * error_log($builder->toSql());
 * error_log(print_r($builder->getBindings(), true));
 * ```
 */
interface QueryBuilderInterface
{
    /**
     * Set columns to select
     *
     * @param string|array<string> $columns Column names or expressions
     * @return static New builder instance
     *
     * @example
     * ->select('*')
     * ->select(['id', 'name'])
     * ->select(['e.id', 'COUNT(*) as total'])
     */
    public function select(string|array $columns): static;

    /**
     * Add columns to existing select
     *
     * @param string|array<string> $columns Additional columns
     * @return static New builder instance
     */
    public function addSelect(string|array $columns): static;

    /**
     * Set the table to query from
     *
     * @param string $table Table name (without prefix)
     * @param string|null $alias Optional table alias
     * @return static New builder instance
     */
    public function from(string $table, ?string $alias = null): static;

    /**
     * Add a JOIN clause
     *
     * @param string $table Table to join (without prefix)
     * @param string $on Join condition
     * @param string $type Join type (INNER, LEFT, RIGHT, CROSS)
     * @param string|null $alias Optional table alias
     * @return static New builder instance
     *
     * @example
     * ->join('saga_attribute_values', 'av.entity_id = e.id', 'LEFT', 'av')
     */
    public function join(
        string $table,
        string $on,
        string $type = 'INNER',
        ?string $alias = null
    ): static;

    /**
     * Add a LEFT JOIN clause
     *
     * @param string $table Table to join (without prefix)
     * @param string $on Join condition
     * @param string|null $alias Optional table alias
     * @return static New builder instance
     */
    public function leftJoin(string $table, string $on, ?string $alias = null): static;

    /**
     * Add a RIGHT JOIN clause
     *
     * @param string $table Table to join (without prefix)
     * @param string $on Join condition
     * @param string|null $alias Optional table alias
     * @return static New builder instance
     */
    public function rightJoin(string $table, string $on, ?string $alias = null): static;

    /**
     * Add a WHERE condition
     *
     * Multiple where() calls are ANDed together.
     *
     * @param string $column Column name or expression
     * @param string $operator Comparison operator (=, !=, <, >, <=, >=, LIKE, etc.)
     * @param mixed $value Value to compare
     * @return static New builder instance
     *
     * @example
     * ->where('saga_id', '=', 1)
     * ->where('name', 'LIKE', '%Skywalker%')
     * ->where('importance_score', '>=', 50)
     */
    public function where(string $column, string $operator, mixed $value): static;

    /**
     * Add a WHERE column IN (values) condition
     *
     * @param string $column Column name
     * @param array<mixed> $values Values to match
     * @return static New builder instance
     *
     * @example
     * ->whereIn('entity_type', ['character', 'location'])
     * ->whereIn('id', [1, 2, 3])
     */
    public function whereIn(string $column, array $values): static;

    /**
     * Add a WHERE column NOT IN (values) condition
     *
     * @param string $column Column name
     * @param array<mixed> $values Values to exclude
     * @return static New builder instance
     */
    public function whereNotIn(string $column, array $values): static;

    /**
     * Add a WHERE column IS NULL condition
     *
     * @param string $column Column name
     * @return static New builder instance
     */
    public function whereNull(string $column): static;

    /**
     * Add a WHERE column IS NOT NULL condition
     *
     * @param string $column Column name
     * @return static New builder instance
     */
    public function whereNotNull(string $column): static;

    /**
     * Add a WHERE column BETWEEN min AND max condition
     *
     * @param string $column Column name
     * @param mixed $min Minimum value
     * @param mixed $max Maximum value
     * @return static New builder instance
     */
    public function whereBetween(string $column, mixed $min, mixed $max): static;

    /**
     * Add a raw WHERE clause
     *
     * Use for complex conditions not covered by other methods.
     * SECURITY: Ensure raw SQL is properly parameterized.
     *
     * @param string $sql Raw SQL condition
     * @param array<mixed> $bindings Parameter bindings
     * @return static New builder instance
     *
     * @example
     * ->whereRaw('(importance_score > ? OR entity_type = ?)', [50, 'character'])
     */
    public function whereRaw(string $sql, array $bindings = []): static;

    /**
     * Add an OR WHERE condition
     *
     * @param string $column Column name
     * @param string $operator Comparison operator
     * @param mixed $value Value to compare
     * @return static New builder instance
     */
    public function orWhere(string $column, string $operator, mixed $value): static;

    /**
     * Add a nested WHERE group
     *
     * @param callable(QueryBuilderInterface): QueryBuilderInterface $callback
     * @return static New builder instance
     *
     * @example
     * ->where('saga_id', '=', 1)
     * ->whereGroup(fn($q) => $q
     *     ->where('entity_type', '=', 'character')
     *     ->orWhere('importance_score', '>', 90)
     * )
     * // WHERE saga_id = 1 AND (entity_type = 'character' OR importance_score > 90)
     */
    public function whereGroup(callable $callback): static;

    /**
     * Add a GROUP BY clause
     *
     * @param string|array<string> $columns Columns to group by
     * @return static New builder instance
     */
    public function groupBy(string|array $columns): static;

    /**
     * Add a HAVING clause
     *
     * @param string $column Column or aggregate
     * @param string $operator Comparison operator
     * @param mixed $value Value to compare
     * @return static New builder instance
     */
    public function having(string $column, string $operator, mixed $value): static;

    /**
     * Add an ORDER BY clause
     *
     * @param string $column Column to order by
     * @param string $direction ASC or DESC
     * @return static New builder instance
     */
    public function orderBy(string $column, string $direction = 'ASC'): static;

    /**
     * Order by multiple columns
     *
     * @param array<string, string> $orders Column => direction pairs
     * @return static New builder instance
     *
     * @example
     * ->orderByMultiple(['importance_score' => 'DESC', 'name' => 'ASC'])
     */
    public function orderByMultiple(array $orders): static;

    /**
     * Set query result limit
     *
     * @param int $limit Maximum rows to return
     * @return static New builder instance
     */
    public function limit(int $limit): static;

    /**
     * Set query result offset
     *
     * @param int $offset Number of rows to skip
     * @return static New builder instance
     */
    public function offset(int $offset): static;

    /**
     * Set DISTINCT flag
     *
     * @return static New builder instance
     */
    public function distinct(): static;

    /**
     * Add FOR UPDATE lock hint
     *
     * @return static New builder instance
     */
    public function forUpdate(): static;

    /**
     * Add LOCK IN SHARE MODE hint
     *
     * @return static New builder instance
     */
    public function sharedLock(): static;

    /**
     * Insert a row and return the insert ID
     *
     * @param string $table Table name (without prefix)
     * @param array<string, mixed> $data Column => value pairs
     * @return int Last insert ID
     * @throws DatabaseException On insert failure
     */
    public function insert(string $table, array $data): int;

    /**
     * Insert multiple rows
     *
     * @param string $table Table name (without prefix)
     * @param array<int, array<string, mixed>> $rows Array of row data
     * @return int Number of rows inserted
     * @throws DatabaseException On insert failure
     */
    public function insertMany(string $table, array $rows): int;

    /**
     * Insert or update on duplicate key
     *
     * @param string $table Table name (without prefix)
     * @param array<string, mixed> $data Column => value pairs
     * @param array<string, mixed> $updateOnDuplicate Columns to update on duplicate
     * @return int Affected rows (1 for insert, 2 for update)
     * @throws DatabaseException On failure
     */
    public function upsert(string $table, array $data, array $updateOnDuplicate): int;

    /**
     * Update rows and return affected count
     *
     * @param string $table Table name (without prefix)
     * @param array<string, mixed> $data Column => value pairs to update
     * @param array<string, mixed> $where Column => value conditions
     * @return int Number of affected rows
     * @throws DatabaseException On update failure
     */
    public function update(string $table, array $data, array $where): int;

    /**
     * Delete rows and return affected count
     *
     * @param string $table Table name (without prefix)
     * @param array<string, mixed> $where Column => value conditions
     * @return int Number of deleted rows
     * @throws DatabaseException On delete failure
     */
    public function delete(string $table, array $where): int;

    /**
     * Execute the built SELECT query
     *
     * @return ResultSetInterface Query results
     * @throws DatabaseException On query failure
     */
    public function execute(): ResultSetInterface;

    /**
     * Get the SQL string (for debugging)
     *
     * Returns the SQL with placeholders.
     * SECURITY: Do not log in production with user data.
     *
     * @return string SQL query string
     */
    public function toSql(): string;

    /**
     * Get parameter bindings (for debugging)
     *
     * @return array<mixed> Parameter values in order
     */
    public function getBindings(): array;

    /**
     * Get the complete SQL with bindings substituted (for debugging only)
     *
     * WARNING: Never use this for execution - only for debugging.
     *
     * @return string SQL with values substituted
     */
    public function toRawSql(): string;

    /**
     * Clone the builder for branching queries
     *
     * @return static New independent builder instance
     */
    public function clone(): static;

    /**
     * Reset the builder to initial state
     *
     * @return static New empty builder instance
     */
    public function reset(): static;

    /**
     * Count matching rows
     *
     * @param string $column Column to count (default: *)
     * @return int Row count
     */
    public function count(string $column = '*'): int;

    /**
     * Check if any matching rows exist
     *
     * @return bool True if at least one row matches
     */
    public function exists(): bool;

    /**
     * Get the first matching row
     *
     * @return array<string, mixed>|null First row or null
     */
    public function first(): ?array;

    /**
     * Get a single column value from first row
     *
     * @param string $column Column name
     * @return mixed Column value or null
     */
    public function value(string $column): mixed;

    /**
     * Get all values from a single column
     *
     * @param string $column Column name
     * @return array<mixed> Column values
     */
    public function pluck(string $column): array;

    /**
     * Get column values keyed by another column
     *
     * @param string $valueColumn Column for values
     * @param string $keyColumn Column for keys
     * @return array<string|int, mixed> Keyed values
     */
    public function pluckKeyed(string $valueColumn, string $keyColumn): array;

    /**
     * Paginate results
     *
     * @param int $page Page number (1-based)
     * @param int $perPage Items per page
     * @return array{
     *     data: array<int, array<string, mixed>>,
     *     total: int,
     *     per_page: int,
     *     current_page: int,
     *     last_page: int,
     *     from: int,
     *     to: int
     * }
     */
    public function paginate(int $page = 1, int $perPage = 15): array;
}
