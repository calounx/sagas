<?php
declare(strict_types=1);

namespace SagaManagerCore\Infrastructure\Database\Adapter\InMemory;

use SagaManagerCore\Infrastructure\Database\Port\SchemaManagerInterface;

/**
 * In-Memory Schema Manager for Testing
 *
 * Simulates schema operations on the in-memory data store.
 *
 * @package SagaManagerCore\Infrastructure\Database\Adapter\InMemory
 */
class InMemorySchemaManager implements SchemaManagerInterface
{
    private InMemoryConnection $connection;

    /** @var array<string, array<string, array{name: string, type: string, null: bool, key: string, default: mixed, extra: string}>> */
    private array $tableSchemas = [];

    public function __construct(InMemoryConnection $connection)
    {
        $this->connection = $connection;
    }

    public function createTable(string $table, string $definition, array $options = []): bool
    {
        $this->connection->createTable($table);
        $this->tableSchemas[$table] = [];
        return true;
    }

    public function createTableRaw(string $sql): bool
    {
        // Parse table name from SQL
        if (preg_match('/CREATE\s+TABLE\s+(?:IF\s+NOT\s+EXISTS\s+)?`?(\w+)`?/i', $sql, $matches)) {
            $table = $matches[1];
            // Remove prefix for internal storage
            $table = str_replace($this->connection->getSagaTablePrefix(), '', $table);
            $this->connection->createTable($table);
            return true;
        }
        return false;
    }

    public function dropTable(string $table): void
    {
        $this->connection->dropTable($table);
        unset($this->tableSchemas[$table]);
    }

    public function dropTables(array $tables): void
    {
        foreach ($tables as $table) {
            $this->dropTable($table);
        }
    }

    public function tableExists(string $table): bool
    {
        return $this->connection->tableExists($table);
    }

    public function addColumn(string $table, string $column, string $definition, ?string $after = null): void
    {
        $this->tableSchemas[$table][$column] = [
            'name' => $column,
            'type' => $definition,
            'null' => true,
            'key' => '',
            'default' => null,
            'extra' => '',
        ];
    }

    public function modifyColumn(string $table, string $column, string $definition): void
    {
        if (isset($this->tableSchemas[$table][$column])) {
            $this->tableSchemas[$table][$column]['type'] = $definition;
        }
    }

    public function dropColumn(string $table, string $column): void
    {
        unset($this->tableSchemas[$table][$column]);
    }

    public function columnExists(string $table, string $column): bool
    {
        return isset($this->tableSchemas[$table][$column]);
    }

    public function addIndex(string $table, string $name, string|array $columns, string $type = 'INDEX'): void
    {
        // No-op for in-memory
    }

    public function dropIndex(string $table, string $name): void
    {
        // No-op for in-memory
    }

    public function indexExists(string $table, string $name): bool
    {
        return false;
    }

    public function addForeignKey(
        string $table,
        string $name,
        string $column,
        string $referenceTable,
        string $referenceColumn,
        string $onDelete = 'CASCADE',
        string $onUpdate = 'CASCADE'
    ): void {
        // No-op for in-memory
    }

    public function dropForeignKey(string $table, string $name): void
    {
        // No-op for in-memory
    }

    public function foreignKeyExists(string $table, string $name): bool
    {
        return false;
    }

    public function getTables(): array
    {
        return array_keys($this->tableSchemas);
    }

    public function getColumns(string $table): array
    {
        return $this->tableSchemas[$table] ?? [];
    }

    public function getIndexes(string $table): array
    {
        return [];
    }

    public function getSchemaVersion(): string
    {
        return '1.0.0';
    }

    public function setSchemaVersion(string $version): void
    {
        // No-op for in-memory
    }

    public function migrate(): array
    {
        return [];
    }

    public function rollbackMigration(): array
    {
        return [];
    }

    public function getCharsetCollate(): string
    {
        return 'DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci';
    }
}
