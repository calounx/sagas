<?php
declare(strict_types=1);

namespace SagaManagerCore\Domain\Port\Database;

use SagaManagerCore\Domain\Exception\DatabaseException;
use SagaManagerCore\Domain\Port\Database\Schema\ColumnDefinition;
use SagaManagerCore\Domain\Port\Database\Schema\ForeignKeyDefinition;
use SagaManagerCore\Domain\Port\Database\Schema\IndexDefinition;
use SagaManagerCore\Domain\Port\Database\Schema\TableDefinition;

/**
 * Port interface for database schema management
 *
 * Provides DDL operations for managing database structure.
 * All table names are provided WITHOUT prefix - implementations add it.
 *
 * @example
 * ```php
 * $schema = $db->schema();
 *
 * // Create a table
 * $table = TableDefinition::create('saga_entities')
 *     ->addColumn(ColumnDefinition::bigInt('id')->unsigned()->autoIncrement()->primary())
 *     ->addColumn(ColumnDefinition::int('saga_id')->unsigned()->notNull())
 *     ->addColumn(ColumnDefinition::varchar('canonical_name', 255)->notNull())
 *     ->addIndex(IndexDefinition::index('idx_saga', ['saga_id']))
 *     ->addForeignKey(ForeignKeyDefinition::column('saga_id', 'saga_sagas')->onDeleteCascade())
 *     ->withSagaDefaults();
 *
 * $schema->createTable($table);
 *
 * // Alter existing table
 * $schema->addColumn('saga_entities',
 *     ColumnDefinition::tinyInt('importance_score')
 *         ->unsigned()
 *         ->default('50')
 *         ->comment('0-100 scale')
 *         ->after('slug')
 * );
 *
 * $schema->addIndex('saga_entities',
 *     IndexDefinition::index('idx_importance', ['importance_score'])
 * );
 *
 * // Check table existence
 * if (!$schema->tableExists('saga_entities')) {
 *     $schema->createTable($entitiesTable);
 * }
 *
 * // Get current prefix
 * $prefix = $schema->getTablePrefix();
 * // Returns: 'wp_saga_'
 * ```
 */
interface SchemaManagerInterface
{
    /**
     * Create a new table
     *
     * @param TableDefinition $definition Complete table definition
     * @throws DatabaseException If table already exists or creation fails
     */
    public function createTable(TableDefinition $definition): void;

    /**
     * Create table if it doesn't exist
     *
     * @param TableDefinition $definition Complete table definition
     * @return bool True if table was created, false if already existed
     * @throws DatabaseException If creation fails
     */
    public function createTableIfNotExists(TableDefinition $definition): bool;

    /**
     * Drop a table
     *
     * @param string $table Table name (without prefix)
     * @throws DatabaseException If table doesn't exist or drop fails
     */
    public function dropTable(string $table): void;

    /**
     * Drop table if it exists
     *
     * @param string $table Table name (without prefix)
     * @return bool True if table was dropped, false if didn't exist
     */
    public function dropTableIfExists(string $table): bool;

    /**
     * Rename a table
     *
     * @param string $from Current table name (without prefix)
     * @param string $to New table name (without prefix)
     * @throws DatabaseException If rename fails
     */
    public function renameTable(string $from, string $to): void;

    /**
     * Truncate a table (delete all rows)
     *
     * @param string $table Table name (without prefix)
     * @throws DatabaseException If truncate fails
     */
    public function truncateTable(string $table): void;

    /**
     * Alter table using a callback
     *
     * Callback receives a TableAlterationBuilder for fluent modifications.
     *
     * @param string $table Table name (without prefix)
     * @param callable(TableAlterationBuilder): void $callback Alteration callback
     * @throws DatabaseException If alteration fails
     *
     * @example
     * ```php
     * $schema->alterTable('saga_entities', function(TableAlterationBuilder $t) {
     *     $t->addColumn(ColumnDefinition::varchar('nickname', 100)->nullable());
     *     $t->modifyColumn(ColumnDefinition::varchar('canonical_name', 500)->notNull());
     *     $t->dropColumn('legacy_field');
     *     $t->addIndex(IndexDefinition::index('idx_nickname', ['nickname']));
     * });
     * ```
     */
    public function alterTable(string $table, callable $callback): void;

    /**
     * Add a column to a table
     *
     * @param string $table Table name (without prefix)
     * @param ColumnDefinition $column Column definition
     * @throws DatabaseException If column exists or addition fails
     */
    public function addColumn(string $table, ColumnDefinition $column): void;

    /**
     * Modify an existing column
     *
     * @param string $table Table name (without prefix)
     * @param ColumnDefinition $column New column definition
     * @throws DatabaseException If column doesn't exist or modification fails
     */
    public function modifyColumn(string $table, ColumnDefinition $column): void;

    /**
     * Rename a column
     *
     * @param string $table Table name (without prefix)
     * @param string $from Current column name
     * @param string $to New column name
     * @throws DatabaseException If rename fails
     */
    public function renameColumn(string $table, string $from, string $to): void;

    /**
     * Drop a column from a table
     *
     * @param string $table Table name (without prefix)
     * @param string $column Column name to drop
     * @throws DatabaseException If column doesn't exist or drop fails
     */
    public function dropColumn(string $table, string $column): void;

    /**
     * Add an index to a table
     *
     * @param string $table Table name (without prefix)
     * @param IndexDefinition $index Index definition
     * @throws DatabaseException If index exists or addition fails
     */
    public function addIndex(string $table, IndexDefinition $index): void;

    /**
     * Drop an index from a table
     *
     * @param string $table Table name (without prefix)
     * @param string $index Index name to drop
     * @throws DatabaseException If index doesn't exist or drop fails
     */
    public function dropIndex(string $table, string $index): void;

    /**
     * Add a foreign key constraint
     *
     * @param string $table Table name (without prefix)
     * @param ForeignKeyDefinition $foreignKey Foreign key definition
     * @throws DatabaseException If constraint exists or addition fails
     */
    public function addForeignKey(string $table, ForeignKeyDefinition $foreignKey): void;

    /**
     * Drop a foreign key constraint
     *
     * @param string $table Table name (without prefix)
     * @param string $foreignKey Constraint name to drop
     * @throws DatabaseException If constraint doesn't exist or drop fails
     */
    public function dropForeignKey(string $table, string $foreignKey): void;

    /**
     * Add a primary key
     *
     * @param string $table Table name (without prefix)
     * @param array<string> $columns Column names for primary key
     * @throws DatabaseException If primary key exists or addition fails
     */
    public function addPrimaryKey(string $table, array $columns): void;

    /**
     * Drop the primary key
     *
     * @param string $table Table name (without prefix)
     * @throws DatabaseException If no primary key or drop fails
     */
    public function dropPrimaryKey(string $table): void;

    /**
     * Check if a table exists
     *
     * @param string $table Table name (without prefix)
     * @return bool True if table exists
     */
    public function tableExists(string $table): bool;

    /**
     * Check if a column exists
     *
     * @param string $table Table name (without prefix)
     * @param string $column Column name
     * @return bool True if column exists
     */
    public function columnExists(string $table, string $column): bool;

    /**
     * Check if an index exists
     *
     * @param string $table Table name (without prefix)
     * @param string $index Index name
     * @return bool True if index exists
     */
    public function indexExists(string $table, string $index): bool;

    /**
     * Check if a foreign key exists
     *
     * @param string $table Table name (without prefix)
     * @param string $foreignKey Constraint name
     * @return bool True if foreign key exists
     */
    public function foreignKeyExists(string $table, string $foreignKey): bool;

    /**
     * Get table columns information
     *
     * @param string $table Table name (without prefix)
     * @return array<string, array{
     *     name: string,
     *     type: string,
     *     nullable: bool,
     *     default: string|null,
     *     primary: bool,
     *     auto_increment: bool,
     *     comment: string|null
     * }>
     * @throws DatabaseException If table doesn't exist
     */
    public function getColumns(string $table): array;

    /**
     * Get table indexes information
     *
     * @param string $table Table name (without prefix)
     * @return array<string, array{
     *     name: string,
     *     type: string,
     *     columns: array<string>,
     *     unique: bool
     * }>
     * @throws DatabaseException If table doesn't exist
     */
    public function getIndexes(string $table): array;

    /**
     * Get table foreign keys information
     *
     * @param string $table Table name (without prefix)
     * @return array<string, array{
     *     name: string,
     *     columns: array<string>,
     *     reference_table: string,
     *     reference_columns: array<string>,
     *     on_delete: string,
     *     on_update: string
     * }>
     * @throws DatabaseException If table doesn't exist
     */
    public function getForeignKeys(string $table): array;

    /**
     * Get the table prefix
     *
     * @return string Full table prefix (e.g., 'wp_saga_')
     */
    public function getTablePrefix(): string;

    /**
     * Get the full prefixed table name
     *
     * @param string $table Table name (without prefix)
     * @return string Full table name with prefix
     */
    public function getTableName(string $table): string;

    /**
     * Get list of all saga tables
     *
     * @return array<string> Table names (without prefix)
     */
    public function getSagaTables(): array;

    /**
     * Generate CREATE TABLE SQL without executing
     *
     * Useful for debugging and migrations.
     *
     * @param TableDefinition $definition Table definition
     * @return string CREATE TABLE SQL statement
     */
    public function generateCreateTableSql(TableDefinition $definition): string;

    /**
     * Run raw DDL statement
     *
     * Use for complex alterations not covered by other methods.
     * SECURITY: Never include user input directly.
     *
     * @param string $sql DDL statement
     * @throws DatabaseException If execution fails
     */
    public function rawDdl(string $sql): void;
}
