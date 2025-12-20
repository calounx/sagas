<?php

declare(strict_types=1);

namespace SagaManager\Infrastructure\Database\Contract;

/**
 * Schema Manager Interface
 *
 * Provides database schema management capabilities for creating, modifying,
 * and inspecting database tables.
 *
 * @example
 *   // Create a table
 *   $db->schema()->createTable('entities', [
 *       'id' => ['type' => 'bigint', 'unsigned' => true, 'autoincrement' => true],
 *       'name' => ['type' => 'varchar', 'length' => 255, 'nullable' => false],
 *       'created_at' => ['type' => 'timestamp', 'default' => 'CURRENT_TIMESTAMP'],
 *   ], [
 *       'primary' => ['id'],
 *       'indexes' => [
 *           'idx_name' => ['name'],
 *       ],
 *   ]);
 */
interface SchemaManagerInterface
{
    /**
     * Check if a table exists
     *
     * @param string $table Table name (without prefix)
     * @return bool
     */
    public function tableExists(string $table): bool;

    /**
     * Create a table
     *
     * @param string $table Table name (without prefix)
     * @param array<string, array<string, mixed>> $columns Column definitions
     * @param array<string, mixed> $options Table options (primary key, indexes, engine, charset)
     * @return void
     *
     * @example
     *   $db->schema()->createTable('entities', [
     *       'id' => [
     *           'type' => 'bigint',
     *           'unsigned' => true,
     *           'autoincrement' => true,
     *       ],
     *       'saga_id' => [
     *           'type' => 'int',
     *           'unsigned' => true,
     *           'nullable' => false,
     *       ],
     *       'canonical_name' => [
     *           'type' => 'varchar',
     *           'length' => 255,
     *           'nullable' => false,
     *       ],
     *       'importance_score' => [
     *           'type' => 'tinyint',
     *           'unsigned' => true,
     *           'default' => 50,
     *       ],
     *       'created_at' => [
     *           'type' => 'timestamp',
     *           'default' => 'CURRENT_TIMESTAMP',
     *       ],
     *   ], [
     *       'primary' => ['id'],
     *       'indexes' => [
     *           'idx_saga' => ['saga_id'],
     *           'idx_importance' => ['importance_score'],
     *       ],
     *       'unique' => [
     *           'uk_saga_name' => ['saga_id', 'canonical_name'],
     *       ],
     *       'engine' => 'InnoDB',
     *       'charset' => 'utf8mb4',
     *       'collation' => 'utf8mb4_unicode_ci',
     *   ]);
     */
    public function createTable(string $table, array $columns, array $options = []): void;

    /**
     * Drop a table
     *
     * @param string $table Table name (without prefix)
     * @return void
     */
    public function dropTable(string $table): void;

    /**
     * Drop a table if it exists
     *
     * @param string $table Table name (without prefix)
     * @return void
     */
    public function dropTableIfExists(string $table): void;

    /**
     * Rename a table
     *
     * @param string $from Current table name (without prefix)
     * @param string $to New table name (without prefix)
     * @return void
     */
    public function renameTable(string $from, string $to): void;

    /**
     * Check if a column exists
     *
     * @param string $table Table name (without prefix)
     * @param string $column Column name
     * @return bool
     */
    public function columnExists(string $table, string $column): bool;

    /**
     * Add a column to a table
     *
     * @param string $table Table name (without prefix)
     * @param string $column Column name
     * @param array<string, mixed> $definition Column definition
     * @return void
     *
     * @example
     *   $db->schema()->addColumn('entities', 'deleted_at', [
     *       'type' => 'timestamp',
     *       'nullable' => true,
     *       'after' => 'updated_at',
     *   ]);
     */
    public function addColumn(string $table, string $column, array $definition): void;

    /**
     * Modify a column
     *
     * @param string $table Table name (without prefix)
     * @param string $column Column name
     * @param array<string, mixed> $definition New column definition
     * @return void
     */
    public function modifyColumn(string $table, string $column, array $definition): void;

    /**
     * Drop a column
     *
     * @param string $table Table name (without prefix)
     * @param string $column Column name
     * @return void
     */
    public function dropColumn(string $table, string $column): void;

    /**
     * Rename a column
     *
     * @param string $table Table name (without prefix)
     * @param string $from Current column name
     * @param string $to New column name
     * @return void
     */
    public function renameColumn(string $table, string $from, string $to): void;

    /**
     * Add an index
     *
     * @param string $table Table name (without prefix)
     * @param string $name Index name
     * @param array<string> $columns Column names
     * @param string $type Index type (INDEX, UNIQUE, FULLTEXT, SPATIAL)
     * @return void
     *
     * @example
     *   $db->schema()->addIndex('entities', 'idx_slug', ['slug']);
     *   $db->schema()->addIndex('entities', 'uk_saga_name', ['saga_id', 'canonical_name'], 'UNIQUE');
     */
    public function addIndex(string $table, string $name, array $columns, string $type = 'INDEX'): void;

    /**
     * Drop an index
     *
     * @param string $table Table name (without prefix)
     * @param string $name Index name
     * @return void
     */
    public function dropIndex(string $table, string $name): void;

    /**
     * Check if an index exists
     *
     * @param string $table Table name (without prefix)
     * @param string $name Index name
     * @return bool
     */
    public function indexExists(string $table, string $name): bool;

    /**
     * Add a foreign key constraint
     *
     * @param string $table Table name (without prefix)
     * @param string $name Constraint name
     * @param array<string> $columns Local column names
     * @param string $referencedTable Referenced table name (without prefix)
     * @param array<string> $referencedColumns Referenced column names
     * @param string $onDelete ON DELETE action (CASCADE, SET NULL, RESTRICT, NO ACTION)
     * @param string $onUpdate ON UPDATE action
     * @return void
     *
     * @example
     *   $db->schema()->addForeignKey(
     *       'entities',
     *       'fk_entities_saga',
     *       ['saga_id'],
     *       'sagas',
     *       ['id'],
     *       'CASCADE',
     *       'CASCADE'
     *   );
     */
    public function addForeignKey(
        string $table,
        string $name,
        array $columns,
        string $referencedTable,
        array $referencedColumns,
        string $onDelete = 'CASCADE',
        string $onUpdate = 'CASCADE'
    ): void;

    /**
     * Drop a foreign key constraint
     *
     * @param string $table Table name (without prefix)
     * @param string $name Constraint name
     * @return void
     */
    public function dropForeignKey(string $table, string $name): void;

    /**
     * Get table columns information
     *
     * @param string $table Table name (without prefix)
     * @return array<string, array<string, mixed>>
     */
    public function getColumns(string $table): array;

    /**
     * Get table indexes information
     *
     * @param string $table Table name (without prefix)
     * @return array<string, array<string, mixed>>
     */
    public function getIndexes(string $table): array;

    /**
     * Truncate a table (delete all rows)
     *
     * @param string $table Table name (without prefix)
     * @return void
     */
    public function truncate(string $table): void;

    /**
     * Execute raw DDL statement
     *
     * @param string $sql DDL SQL statement
     * @return void
     */
    public function raw(string $sql): void;
}
