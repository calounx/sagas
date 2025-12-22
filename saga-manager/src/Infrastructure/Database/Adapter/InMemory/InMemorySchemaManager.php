<?php

declare(strict_types=1);

namespace SagaManager\Infrastructure\Database\Adapter\InMemory;

use SagaManager\Infrastructure\Database\Contract\SchemaManagerInterface;
use SagaManager\Infrastructure\Exception\DatabaseException;

/**
 * In-Memory Schema Manager
 *
 * Manages schema operations for in-memory database.
 * Stores column definitions for validation and introspection.
 *
 * @example
 *   $db->schema()->createTable('entities', [
 *       'id' => ['type' => 'bigint', 'autoincrement' => true],
 *       'name' => ['type' => 'varchar', 'length' => 255],
 *   ]);
 */
final class InMemorySchemaManager implements SchemaManagerInterface
{
    /** @var array<string, array<string, array<string, mixed>>> */
    private array $indexes = [];

    /** @var array<string, array<string, array<string, mixed>>> */
    private array $foreignKeys = [];

    public function __construct(
        private readonly InMemoryDataStore $dataStore,
        private readonly string $tablePrefix = '',
    ) {}

    public function tableExists(string $table): bool
    {
        $tableName = $this->tablePrefix . $table;
        return $this->dataStore->hasTable($tableName);
    }

    public function createTable(string $table, array $columns, array $options = []): void
    {
        $tableName = $this->tablePrefix . $table;

        if ($this->dataStore->hasTable($tableName)) {
            throw new DatabaseException("Table '{$table}' already exists");
        }

        // Store column definitions
        $this->dataStore->createTable($tableName, $columns);

        // Initialize indexes storage
        $this->indexes[$tableName] = [];

        // Create primary key index
        if (isset($options['primary'])) {
            $pkColumns = (array) $options['primary'];
            $this->indexes[$tableName]['PRIMARY'] = [
                'columns' => $pkColumns,
                'unique' => true,
            ];
        }

        // Create other indexes
        if (isset($options['indexes'])) {
            foreach ($options['indexes'] as $name => $cols) {
                $this->indexes[$tableName][$name] = [
                    'columns' => (array) $cols,
                    'unique' => false,
                ];
            }
        }

        // Create unique constraints
        if (isset($options['unique'])) {
            foreach ($options['unique'] as $name => $cols) {
                $this->indexes[$tableName][$name] = [
                    'columns' => (array) $cols,
                    'unique' => true,
                ];
            }
        }
    }

    public function dropTable(string $table): void
    {
        $tableName = $this->tablePrefix . $table;

        if (!$this->dataStore->hasTable($tableName)) {
            throw new DatabaseException("Table '{$table}' does not exist");
        }

        $this->dataStore->dropTable($tableName);
        unset($this->indexes[$tableName]);
        unset($this->foreignKeys[$tableName]);
    }

    public function dropTableIfExists(string $table): void
    {
        $tableName = $this->tablePrefix . $table;

        if ($this->dataStore->hasTable($tableName)) {
            $this->dataStore->dropTable($tableName);
            unset($this->indexes[$tableName]);
            unset($this->foreignKeys[$tableName]);
        }
    }

    public function renameTable(string $from, string $to): void
    {
        $fromName = $this->tablePrefix . $from;
        $toName = $this->tablePrefix . $to;

        if (!$this->dataStore->hasTable($fromName)) {
            throw new DatabaseException("Table '{$from}' does not exist");
        }

        if ($this->dataStore->hasTable($toName)) {
            throw new DatabaseException("Table '{$to}' already exists");
        }

        // Copy data
        $rows = $this->dataStore->getTable($fromName);
        $schema = $this->dataStore->getSchema($fromName);

        $this->dataStore->createTable($toName, $schema);
        $this->dataStore->setTable($toName, $rows);

        // Copy indexes
        if (isset($this->indexes[$fromName])) {
            $this->indexes[$toName] = $this->indexes[$fromName];
            unset($this->indexes[$fromName]);
        }

        // Drop old table
        $this->dataStore->dropTable($fromName);
    }

    public function columnExists(string $table, string $column): bool
    {
        $columns = $this->getColumns($table);
        return isset($columns[$column]);
    }

    public function addColumn(string $table, string $column, array $definition): void
    {
        $tableName = $this->tablePrefix . $table;

        if (!$this->dataStore->hasTable($tableName)) {
            throw new DatabaseException("Table '{$table}' does not exist");
        }

        $schema = $this->dataStore->getSchema($tableName);

        if (isset($schema[$column])) {
            throw new DatabaseException("Column '{$column}' already exists in table '{$table}'");
        }

        $schema[$column] = $definition;
        $this->dataStore->setSchema($tableName, $schema);

        // Add default value to existing rows
        $defaultValue = $definition['default'] ?? null;
        $rows = $this->dataStore->getTable($tableName);
        foreach ($rows as &$row) {
            $row[$column] = $defaultValue;
        }
        $this->dataStore->setTable($tableName, $rows);
    }

    public function modifyColumn(string $table, string $column, array $definition): void
    {
        $tableName = $this->tablePrefix . $table;

        if (!$this->dataStore->hasTable($tableName)) {
            throw new DatabaseException("Table '{$table}' does not exist");
        }

        $schema = $this->dataStore->getSchema($tableName);

        if (!isset($schema[$column])) {
            throw new DatabaseException("Column '{$column}' does not exist in table '{$table}'");
        }

        $schema[$column] = array_merge($schema[$column], $definition);
        $this->dataStore->setSchema($tableName, $schema);
    }

    public function dropColumn(string $table, string $column): void
    {
        $tableName = $this->tablePrefix . $table;

        if (!$this->dataStore->hasTable($tableName)) {
            throw new DatabaseException("Table '{$table}' does not exist");
        }

        $schema = $this->dataStore->getSchema($tableName);

        if (!isset($schema[$column])) {
            throw new DatabaseException("Column '{$column}' does not exist in table '{$table}'");
        }

        unset($schema[$column]);
        $this->dataStore->setSchema($tableName, $schema);

        // Remove column from existing rows
        $rows = $this->dataStore->getTable($tableName);
        foreach ($rows as &$row) {
            unset($row[$column]);
        }
        $this->dataStore->setTable($tableName, $rows);
    }

    public function renameColumn(string $table, string $from, string $to): void
    {
        $tableName = $this->tablePrefix . $table;

        if (!$this->dataStore->hasTable($tableName)) {
            throw new DatabaseException("Table '{$table}' does not exist");
        }

        $schema = $this->dataStore->getSchema($tableName);

        if (!isset($schema[$from])) {
            throw new DatabaseException("Column '{$from}' does not exist in table '{$table}'");
        }

        if (isset($schema[$to])) {
            throw new DatabaseException("Column '{$to}' already exists in table '{$table}'");
        }

        // Rename in schema
        $schema[$to] = $schema[$from];
        unset($schema[$from]);
        $this->dataStore->setSchema($tableName, $schema);

        // Rename in rows
        $rows = $this->dataStore->getTable($tableName);
        foreach ($rows as &$row) {
            if (array_key_exists($from, $row)) {
                $row[$to] = $row[$from];
                unset($row[$from]);
            }
        }
        $this->dataStore->setTable($tableName, $rows);
    }

    public function addIndex(string $table, string $name, array $columns, string $type = 'INDEX'): void
    {
        $tableName = $this->tablePrefix . $table;

        if (!$this->dataStore->hasTable($tableName)) {
            throw new DatabaseException("Table '{$table}' does not exist");
        }

        if (!isset($this->indexes[$tableName])) {
            $this->indexes[$tableName] = [];
        }

        $this->indexes[$tableName][$name] = [
            'columns' => $columns,
            'unique' => in_array(strtoupper($type), ['UNIQUE', 'UNIQUE INDEX']),
            'type' => strtoupper($type),
        ];
    }

    public function dropIndex(string $table, string $name): void
    {
        $tableName = $this->tablePrefix . $table;

        if (isset($this->indexes[$tableName][$name])) {
            unset($this->indexes[$tableName][$name]);
        }
    }

    public function indexExists(string $table, string $name): bool
    {
        $tableName = $this->tablePrefix . $table;
        return isset($this->indexes[$tableName][$name]);
    }

    public function addForeignKey(
        string $table,
        string $name,
        array $columns,
        string $referencedTable,
        array $referencedColumns,
        string $onDelete = 'CASCADE',
        string $onUpdate = 'CASCADE'
    ): void {
        $tableName = $this->tablePrefix . $table;
        $refTableName = $this->tablePrefix . $referencedTable;

        if (!$this->dataStore->hasTable($tableName)) {
            throw new DatabaseException("Table '{$table}' does not exist");
        }

        if (!$this->dataStore->hasTable($refTableName)) {
            throw new DatabaseException("Referenced table '{$referencedTable}' does not exist");
        }

        if (!isset($this->foreignKeys[$tableName])) {
            $this->foreignKeys[$tableName] = [];
        }

        $this->foreignKeys[$tableName][$name] = [
            'columns' => $columns,
            'referenced_table' => $refTableName,
            'referenced_columns' => $referencedColumns,
            'on_delete' => $onDelete,
            'on_update' => $onUpdate,
        ];
    }

    public function dropForeignKey(string $table, string $name): void
    {
        $tableName = $this->tablePrefix . $table;

        if (isset($this->foreignKeys[$tableName][$name])) {
            unset($this->foreignKeys[$tableName][$name]);
        }
    }

    public function getColumns(string $table): array
    {
        $tableName = $this->tablePrefix . $table;
        return $this->dataStore->getSchema($tableName);
    }

    public function getIndexes(string $table): array
    {
        $tableName = $this->tablePrefix . $table;
        return $this->indexes[$tableName] ?? [];
    }

    public function truncate(string $table): void
    {
        $tableName = $this->tablePrefix . $table;

        if (!$this->dataStore->hasTable($tableName)) {
            throw new DatabaseException("Table '{$table}' does not exist");
        }

        $this->dataStore->truncate($tableName);
    }

    public function raw(string $sql): void
    {
        // In-memory database doesn't execute raw SQL
        // This is a no-op for testing purposes
    }

    /**
     * Get foreign keys for a table
     *
     * @param string $table Table name
     * @return array<string, array<string, mixed>>
     */
    public function getForeignKeys(string $table): array
    {
        $tableName = $this->tablePrefix . $table;
        return $this->foreignKeys[$tableName] ?? [];
    }
}
