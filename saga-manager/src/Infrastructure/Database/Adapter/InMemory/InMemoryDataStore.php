<?php

declare(strict_types=1);

namespace SagaManager\Infrastructure\Database\Adapter\InMemory;

/**
 * In-Memory Data Store
 *
 * Stores table data in PHP arrays with auto-increment ID generation.
 * Supports cloning for transaction snapshots.
 */
final class InMemoryDataStore
{
    /** @var array<string, array<int, array<string, mixed>>> Table name => rows */
    private array $tables = [];

    /** @var array<string, int> Table name => next auto-increment ID */
    private array $autoIncrementIds = [];

    /** @var array<string, array<string, array<string, mixed>>> Table name => column definitions */
    private array $schemas = [];

    /**
     * Get all rows from a table
     *
     * @param string $table Table name
     * @return array<int, array<string, mixed>>
     */
    public function getTable(string $table): array
    {
        return $this->tables[$table] ?? [];
    }

    /**
     * Set all rows for a table
     *
     * @param string $table Table name
     * @param array<int, array<string, mixed>> $rows
     */
    public function setTable(string $table, array $rows): void
    {
        $this->tables[$table] = $rows;
    }

    /**
     * Check if a table exists
     *
     * @param string $table Table name
     * @return bool
     */
    public function hasTable(string $table): bool
    {
        return isset($this->tables[$table]) || isset($this->schemas[$table]);
    }

    /**
     * Create a table
     *
     * @param string $table Table name
     * @param array<string, array<string, mixed>> $schema Column definitions
     */
    public function createTable(string $table, array $schema): void
    {
        $this->tables[$table] = [];
        $this->autoIncrementIds[$table] = 1;
        $this->schemas[$table] = $schema;
    }

    /**
     * Drop a table
     *
     * @param string $table Table name
     */
    public function dropTable(string $table): void
    {
        unset($this->tables[$table]);
        unset($this->autoIncrementIds[$table]);
        unset($this->schemas[$table]);
    }

    /**
     * Get table schema
     *
     * @param string $table Table name
     * @return array<string, array<string, mixed>>
     */
    public function getSchema(string $table): array
    {
        return $this->schemas[$table] ?? [];
    }

    /**
     * Set table schema
     *
     * @param string $table Table name
     * @param array<string, array<string, mixed>> $schema Column definitions
     */
    public function setSchema(string $table, array $schema): void
    {
        $this->schemas[$table] = $schema;
    }

    /**
     * Insert a row and return the auto-increment ID
     *
     * @param string $table Table name
     * @param array<string, mixed> $row Row data
     * @param string $primaryKey Primary key column name
     * @return int The inserted row ID
     */
    public function insert(string $table, array $row, string $primaryKey = 'id'): int
    {
        if (!isset($this->tables[$table])) {
            $this->tables[$table] = [];
            $this->autoIncrementIds[$table] = 1;
        }

        // Auto-generate ID if not provided
        if (!isset($row[$primaryKey]) || $row[$primaryKey] === null) {
            $row[$primaryKey] = $this->autoIncrementIds[$table]++;
        } else {
            // Update auto-increment if provided ID is higher
            $id = (int) $row[$primaryKey];
            if ($id >= $this->autoIncrementIds[$table]) {
                $this->autoIncrementIds[$table] = $id + 1;
            }
        }

        $this->tables[$table][] = $row;
        return (int) $row[$primaryKey];
    }

    /**
     * Update rows matching criteria
     *
     * @param string $table Table name
     * @param array<string, mixed> $data Data to update
     * @param array<string, mixed> $where WHERE criteria
     * @return int Number of affected rows
     */
    public function update(string $table, array $data, array $where): int
    {
        if (!isset($this->tables[$table])) {
            return 0;
        }

        $affected = 0;
        foreach ($this->tables[$table] as $key => $row) {
            if ($this->matchesWhere($row, $where)) {
                $this->tables[$table][$key] = array_merge($row, $data);
                $affected++;
            }
        }

        return $affected;
    }

    /**
     * Delete rows matching criteria
     *
     * @param string $table Table name
     * @param array<string, mixed> $where WHERE criteria
     * @return int Number of deleted rows
     */
    public function delete(string $table, array $where): int
    {
        if (!isset($this->tables[$table])) {
            return 0;
        }

        $originalCount = count($this->tables[$table]);
        $this->tables[$table] = array_values(array_filter(
            $this->tables[$table],
            fn($row) => !$this->matchesWhere($row, $where)
        ));

        return $originalCount - count($this->tables[$table]);
    }

    /**
     * Select rows matching criteria
     *
     * @param string $table Table name
     * @param array<string, mixed> $where WHERE criteria
     * @param array<string> $columns Columns to select (empty = all)
     * @param array<string, string> $orderBy Order by columns
     * @param int|null $limit Maximum rows
     * @param int $offset Rows to skip
     * @return array<int, array<string, mixed>>
     */
    public function select(
        string $table,
        array $where = [],
        array $columns = [],
        array $orderBy = [],
        ?int $limit = null,
        int $offset = 0
    ): array {
        if (!isset($this->tables[$table])) {
            return [];
        }

        $rows = $this->tables[$table];

        // Filter by WHERE
        if (!empty($where)) {
            $rows = array_filter($rows, fn($row) => $this->matchesWhere($row, $where));
        }

        // Order by
        if (!empty($orderBy)) {
            usort($rows, function ($a, $b) use ($orderBy) {
                foreach ($orderBy as $column => $direction) {
                    $aVal = $a[$column] ?? null;
                    $bVal = $b[$column] ?? null;

                    $cmp = $this->compareValues($aVal, $bVal);
                    if ($cmp !== 0) {
                        return strtoupper($direction) === 'DESC' ? -$cmp : $cmp;
                    }
                }
                return 0;
            });
        }

        // Offset and limit
        $rows = array_values($rows);
        if ($offset > 0) {
            $rows = array_slice($rows, $offset);
        }
        if ($limit !== null) {
            $rows = array_slice($rows, 0, $limit);
        }

        // Select specific columns
        if (!empty($columns)) {
            $rows = array_map(function ($row) use ($columns) {
                $result = [];
                foreach ($columns as $column) {
                    if (isset($row[$column])) {
                        $result[$column] = $row[$column];
                    }
                }
                return $result;
            }, $rows);
        }

        return $rows;
    }

    /**
     * Count rows matching criteria
     *
     * @param string $table Table name
     * @param array<string, mixed> $where WHERE criteria
     * @return int
     */
    public function count(string $table, array $where = []): int
    {
        if (!isset($this->tables[$table])) {
            return 0;
        }

        if (empty($where)) {
            return count($this->tables[$table]);
        }

        return count(array_filter(
            $this->tables[$table],
            fn($row) => $this->matchesWhere($row, $where)
        ));
    }

    /**
     * Truncate a table (delete all rows)
     *
     * @param string $table Table name
     */
    public function truncate(string $table): void
    {
        if (isset($this->tables[$table])) {
            $this->tables[$table] = [];
            $this->autoIncrementIds[$table] = 1;
        }
    }

    /**
     * Get the next auto-increment ID for a table
     *
     * @param string $table Table name
     * @return int
     */
    public function getNextId(string $table): int
    {
        return $this->autoIncrementIds[$table] ?? 1;
    }

    /**
     * Clone the data store (for transactions)
     *
     * @return self
     */
    public function clone(): self
    {
        $clone = new self();
        $clone->tables = $this->deepClone($this->tables);
        $clone->autoIncrementIds = $this->autoIncrementIds;
        $clone->schemas = $this->deepClone($this->schemas);
        return $clone;
    }

    /**
     * Restore from another data store (for rollback)
     *
     * @param self $source
     */
    public function restore(self $source): void
    {
        $this->tables = $this->deepClone($source->tables);
        $this->autoIncrementIds = $source->autoIncrementIds;
        $this->schemas = $this->deepClone($source->schemas);
    }

    /**
     * Check if a row matches WHERE criteria
     *
     * @param array<string, mixed> $row
     * @param array<string, mixed> $where
     * @return bool
     */
    private function matchesWhere(array $row, array $where): bool
    {
        foreach ($where as $column => $value) {
            $rowValue = $row[$column] ?? null;

            if (is_array($value)) {
                // IN clause
                if (!in_array($rowValue, $value, false)) {
                    return false;
                }
            } elseif ($value === null) {
                if ($rowValue !== null) {
                    return false;
                }
            } else {
                // Use loose comparison for type coercion (like real databases)
                if ($rowValue != $value) {
                    return false;
                }
            }
        }
        return true;
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
     * Deep clone an array
     *
     * @template T
     * @param T $data
     * @return T
     */
    private function deepClone(mixed $data): mixed
    {
        if (is_array($data)) {
            return array_map(fn($item) => $this->deepClone($item), $data);
        }
        if (is_object($data)) {
            return clone $data;
        }
        return $data;
    }
}
