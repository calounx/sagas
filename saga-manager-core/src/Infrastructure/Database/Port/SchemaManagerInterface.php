<?php

declare(strict_types=1);

namespace SagaManagerCore\Infrastructure\Database\Port;

/**
 * Schema Manager Port Interface
 *
 * Manages database schema operations including table creation,
 * modification, and migrations.
 */
interface SchemaManagerInterface
{
    /**
     * Create a table if it doesn't exist
     *
     * @param string $table Table name (without prefix)
     * @param string $definition SQL table definition (columns, keys, etc.)
     * @param array<string, mixed> $options Engine, charset, collation options
     * @return bool True if table was created, false if already exists
     * @throws \SagaManagerCore\Domain\Exception\DatabaseException On failure
     */
    public function createTable(string $table, string $definition, array $options = []): bool;

    /**
     * Create a table using raw SQL (with dbDelta for WordPress)
     *
     * @param string $sql Full CREATE TABLE SQL statement
     * @return bool True if operation succeeded
     * @throws \SagaManagerCore\Domain\Exception\DatabaseException On failure
     */
    public function createTableRaw(string $sql): bool;

    /**
     * Drop a table if it exists
     *
     * @param string $table Table name (without prefix)
     * @throws \SagaManagerCore\Domain\Exception\DatabaseException On failure
     */
    public function dropTable(string $table): void;

    /**
     * Drop multiple tables (respecting foreign key constraints)
     *
     * @param array<string> $tables Table names (without prefix)
     * @throws \SagaManagerCore\Domain\Exception\DatabaseException On failure
     */
    public function dropTables(array $tables): void;

    /**
     * Check if a table exists
     *
     * @param string $table Table name (without prefix)
     */
    public function tableExists(string $table): bool;

    /**
     * Add a column to an existing table
     *
     * @param string $table Table name (without prefix)
     * @param string $column Column name
     * @param string $definition Column definition (type, constraints)
     * @param string|null $after Column to add after (optional)
     * @throws \SagaManagerCore\Domain\Exception\DatabaseException On failure
     */
    public function addColumn(string $table, string $column, string $definition, ?string $after = null): void;

    /**
     * Modify an existing column
     *
     * @param string $table Table name (without prefix)
     * @param string $column Column name
     * @param string $definition New column definition
     * @throws \SagaManagerCore\Domain\Exception\DatabaseException On failure
     */
    public function modifyColumn(string $table, string $column, string $definition): void;

    /**
     * Drop a column from a table
     *
     * @param string $table Table name (without prefix)
     * @param string $column Column name
     * @throws \SagaManagerCore\Domain\Exception\DatabaseException On failure
     */
    public function dropColumn(string $table, string $column): void;

    /**
     * Check if a column exists
     *
     * @param string $table Table name (without prefix)
     * @param string $column Column name
     */
    public function columnExists(string $table, string $column): bool;

    /**
     * Add an index to a table
     *
     * @param string $table Table name (without prefix)
     * @param string $name Index name
     * @param string|array<string> $columns Column(s) to index
     * @param string $type Index type (INDEX, UNIQUE, FULLTEXT, SPATIAL)
     * @throws \SagaManagerCore\Domain\Exception\DatabaseException On failure
     */
    public function addIndex(string $table, string $name, string|array $columns, string $type = 'INDEX'): void;

    /**
     * Drop an index from a table
     *
     * @param string $table Table name (without prefix)
     * @param string $name Index name
     * @throws \SagaManagerCore\Domain\Exception\DatabaseException On failure
     */
    public function dropIndex(string $table, string $name): void;

    /**
     * Check if an index exists
     *
     * @param string $table Table name (without prefix)
     * @param string $name Index name
     */
    public function indexExists(string $table, string $name): bool;

    /**
     * Add a foreign key constraint
     *
     * @param string $table Table name (without prefix)
     * @param string $name Constraint name
     * @param string $column Local column
     * @param string $referenceTable Referenced table (without prefix)
     * @param string $referenceColumn Referenced column
     * @param string $onDelete ON DELETE action (CASCADE, SET NULL, RESTRICT, NO ACTION)
     * @param string $onUpdate ON UPDATE action
     * @throws \SagaManagerCore\Domain\Exception\DatabaseException On failure
     */
    public function addForeignKey(
        string $table,
        string $name,
        string $column,
        string $referenceTable,
        string $referenceColumn,
        string $onDelete = 'CASCADE',
        string $onUpdate = 'CASCADE'
    ): void;

    /**
     * Drop a foreign key constraint
     *
     * @param string $table Table name (without prefix)
     * @param string $name Constraint name
     * @throws \SagaManagerCore\Domain\Exception\DatabaseException On failure
     */
    public function dropForeignKey(string $table, string $name): void;

    /**
     * Check if a foreign key exists
     *
     * @param string $table Table name (without prefix)
     * @param string $name Constraint name
     */
    public function foreignKeyExists(string $table, string $name): bool;

    /**
     * Get all table names (saga tables only)
     *
     * @return array<string> Table names (without prefix)
     */
    public function getTables(): array;

    /**
     * Get column information for a table
     *
     * @param string $table Table name (without prefix)
     * @return array<string, array{
     *     name: string,
     *     type: string,
     *     null: bool,
     *     key: string,
     *     default: mixed,
     *     extra: string
     * }>
     */
    public function getColumns(string $table): array;

    /**
     * Get index information for a table
     *
     * @param string $table Table name (without prefix)
     * @return array<string, array{
     *     name: string,
     *     columns: array<string>,
     *     unique: bool,
     *     type: string
     * }>
     */
    public function getIndexes(string $table): array;

    /**
     * Get the current schema version
     */
    public function getSchemaVersion(): string;

    /**
     * Set the schema version
     *
     * @param string $version Version string
     */
    public function setSchemaVersion(string $version): void;

    /**
     * Run pending migrations
     *
     * @return array<string> List of executed migrations
     * @throws \SagaManagerCore\Domain\Exception\DatabaseException On failure
     */
    public function migrate(): array;

    /**
     * Rollback the last migration batch
     *
     * @return array<string> List of rolled back migrations
     * @throws \SagaManagerCore\Domain\Exception\DatabaseException On failure
     */
    public function rollbackMigration(): array;

    /**
     * Get the database charset and collation string
     *
     * @return string e.g., "DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"
     */
    public function getCharsetCollate(): string;
}
