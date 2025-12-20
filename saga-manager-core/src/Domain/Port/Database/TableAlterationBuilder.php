<?php
declare(strict_types=1);

namespace SagaManagerCore\Domain\Port\Database;

use SagaManagerCore\Domain\Port\Database\Schema\ColumnDefinition;
use SagaManagerCore\Domain\Port\Database\Schema\ForeignKeyDefinition;
use SagaManagerCore\Domain\Port\Database\Schema\IndexDefinition;

/**
 * Interface for building table alterations
 *
 * Used as a callback parameter in SchemaManagerInterface::alterTable().
 * Collects alterations to be executed in a single ALTER TABLE statement.
 *
 * @example
 * ```php
 * $schema->alterTable('saga_entities', function(TableAlterationBuilder $table) {
 *     // Add new columns
 *     $table->addColumn(
 *         ColumnDefinition::varchar('alias', 100)->nullable()->after('canonical_name')
 *     );
 *
 *     // Modify existing column
 *     $table->modifyColumn(
 *         ColumnDefinition::varchar('canonical_name', 500)->notNull()
 *     );
 *
 *     // Rename column
 *     $table->renameColumn('old_name', 'new_name');
 *
 *     // Drop column
 *     $table->dropColumn('deprecated_field');
 *
 *     // Add index
 *     $table->addIndex(
 *         IndexDefinition::index('idx_alias', ['alias'])
 *     );
 *
 *     // Drop index
 *     $table->dropIndex('idx_old');
 *
 *     // Add foreign key
 *     $table->addForeignKey(
 *         ForeignKeyDefinition::column('parent_id', 'saga_entities')
 *             ->onDeleteSetNull()
 *     );
 *
 *     // Drop foreign key
 *     $table->dropForeignKey('fk_legacy');
 *
 *     // Change table options
 *     $table->engine('InnoDB');
 *     $table->charset('utf8mb4');
 *     $table->collation('utf8mb4_unicode_ci');
 *     $table->comment('Updated entity storage');
 * });
 * ```
 */
interface TableAlterationBuilder
{
    /**
     * Add a new column
     *
     * @param ColumnDefinition $column Column to add
     * @return static
     */
    public function addColumn(ColumnDefinition $column): static;

    /**
     * Add multiple columns
     *
     * @param array<ColumnDefinition> $columns Columns to add
     * @return static
     */
    public function addColumns(array $columns): static;

    /**
     * Modify an existing column
     *
     * @param ColumnDefinition $column New column definition
     * @return static
     */
    public function modifyColumn(ColumnDefinition $column): static;

    /**
     * Rename a column
     *
     * @param string $from Current name
     * @param string $to New name
     * @return static
     */
    public function renameColumn(string $from, string $to): static;

    /**
     * Drop a column
     *
     * @param string $column Column name
     * @return static
     */
    public function dropColumn(string $column): static;

    /**
     * Drop multiple columns
     *
     * @param array<string> $columns Column names
     * @return static
     */
    public function dropColumns(array $columns): static;

    /**
     * Add an index
     *
     * @param IndexDefinition $index Index to add
     * @return static
     */
    public function addIndex(IndexDefinition $index): static;

    /**
     * Drop an index
     *
     * @param string $index Index name
     * @return static
     */
    public function dropIndex(string $index): static;

    /**
     * Add a foreign key constraint
     *
     * @param ForeignKeyDefinition $foreignKey Constraint to add
     * @return static
     */
    public function addForeignKey(ForeignKeyDefinition $foreignKey): static;

    /**
     * Drop a foreign key constraint
     *
     * @param string $foreignKey Constraint name
     * @return static
     */
    public function dropForeignKey(string $foreignKey): static;

    /**
     * Add/change primary key
     *
     * @param array<string> $columns Primary key columns
     * @return static
     */
    public function primaryKey(array $columns): static;

    /**
     * Drop primary key
     *
     * @return static
     */
    public function dropPrimaryKey(): static;

    /**
     * Change storage engine
     *
     * @param string $engine Engine name (InnoDB, MyISAM, etc.)
     * @return static
     */
    public function engine(string $engine): static;

    /**
     * Change default character set
     *
     * @param string $charset Character set
     * @return static
     */
    public function charset(string $charset): static;

    /**
     * Change default collation
     *
     * @param string $collation Collation name
     * @return static
     */
    public function collation(string $collation): static;

    /**
     * Change table comment
     *
     * @param string $comment New comment
     * @return static
     */
    public function comment(string $comment): static;

    /**
     * Change row format
     *
     * @param string $format Row format (DYNAMIC, COMPRESSED, etc.)
     * @return static
     */
    public function rowFormat(string $format): static;

    /**
     * Enable/disable auto increment
     *
     * @param int $value Starting value for auto increment
     * @return static
     */
    public function autoIncrement(int $value): static;

    /**
     * Get all pending alterations
     *
     * @return array<array{type: string, definition: mixed}> Pending alterations
     */
    public function getAlterations(): array;

    /**
     * Check if any alterations are pending
     *
     * @return bool True if alterations exist
     */
    public function hasAlterations(): bool;

    /**
     * Clear all pending alterations
     *
     * @return static
     */
    public function reset(): static;
}
