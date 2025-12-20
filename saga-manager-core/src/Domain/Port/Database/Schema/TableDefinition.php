<?php
declare(strict_types=1);

namespace SagaManagerCore\Domain\Port\Database\Schema;

use SagaManagerCore\Domain\Exception\ValidationException;

/**
 * Value object representing a complete database table definition
 *
 * Immutable, comprehensive definition of a table including columns, indexes,
 * foreign keys, and table options. Used by SchemaManagerInterface for DDL.
 *
 * @example
 * ```php
 * // Build table using fluent interface
 * $table = TableDefinition::create('entities')
 *     ->addColumn(
 *         ColumnDefinition::bigInt('id')
 *             ->unsigned()
 *             ->autoIncrement()
 *             ->primary()
 *     )
 *     ->addColumn(
 *         ColumnDefinition::int('saga_id')
 *             ->unsigned()
 *             ->notNull()
 *     )
 *     ->addColumn(
 *         ColumnDefinition::enum('entity_type', ['character', 'location', 'event'])
 *             ->notNull()
 *     )
 *     ->addColumn(
 *         ColumnDefinition::varchar('canonical_name', 255)
 *             ->notNull()
 *     )
 *     ->addColumn(
 *         ColumnDefinition::timestamp('created_at')
 *             ->default('CURRENT_TIMESTAMP')
 *     )
 *     ->addIndex(
 *         IndexDefinition::index('idx_saga_type', ['saga_id', 'entity_type'])
 *     )
 *     ->addIndex(
 *         IndexDefinition::unique('uk_saga_name', ['saga_id', 'canonical_name'])
 *     )
 *     ->addForeignKey(
 *         ForeignKeyDefinition::column('saga_id', 'saga_sagas')
 *             ->onDeleteCascade()
 *     )
 *     ->engine('InnoDB')
 *     ->charset('utf8mb4')
 *     ->collation('utf8mb4_unicode_ci')
 *     ->comment('Core entity storage');
 * ```
 */
readonly class TableDefinition
{
    /**
     * @param string $name Table name (without prefix)
     * @param array<string, ColumnDefinition> $columns Columns keyed by name
     * @param array<string, IndexDefinition> $indexes Indexes keyed by name
     * @param array<string, ForeignKeyDefinition> $foreignKeys Foreign keys keyed by name
     * @param array<string> $primaryKey Primary key column names
     * @param string|null $engine Storage engine
     * @param string|null $charset Default character set
     * @param string|null $collation Default collation
     * @param string|null $comment Table comment
     * @param string|null $rowFormat Row format (COMPACT, DYNAMIC, COMPRESSED, etc.)
     * @param array<string, string> $checkConstraints CHECK constraints keyed by name
     */
    private function __construct(
        public string $name,
        public array $columns = [],
        public array $indexes = [],
        public array $foreignKeys = [],
        public array $primaryKey = [],
        public ?string $engine = null,
        public ?string $charset = null,
        public ?string $collation = null,
        public ?string $comment = null,
        public ?string $rowFormat = null,
        public array $checkConstraints = [],
    ) {
        $this->validate();
    }

    /**
     * Validate table definition
     *
     * @throws ValidationException
     */
    private function validate(): void
    {
        if (empty($this->name)) {
            throw new ValidationException('Table name cannot be empty');
        }

        if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $this->name)) {
            throw new ValidationException(
                "Invalid table name '{$this->name}': must start with letter or underscore"
            );
        }

        // Validate primary key columns exist
        foreach ($this->primaryKey as $columnName) {
            if (!isset($this->columns[$columnName])) {
                throw new ValidationException(
                    "Primary key column '{$columnName}' not found in table definition"
                );
            }
        }

        // Validate index columns exist
        foreach ($this->indexes as $index) {
            foreach ($index->columns as $columnName) {
                if (!isset($this->columns[$columnName])) {
                    throw new ValidationException(
                        "Index '{$index->name}' references non-existent column '{$columnName}'"
                    );
                }
            }
        }

        // Validate foreign key columns exist
        foreach ($this->foreignKeys as $fk) {
            foreach ($fk->columns as $columnName) {
                if (!isset($this->columns[$columnName])) {
                    throw new ValidationException(
                        "Foreign key '{$fk->name}' references non-existent column '{$columnName}'"
                    );
                }
            }
        }

        // Validate row format
        if ($this->rowFormat !== null) {
            $validFormats = ['DEFAULT', 'DYNAMIC', 'FIXED', 'COMPRESSED', 'REDUNDANT', 'COMPACT'];
            if (!in_array(strtoupper($this->rowFormat), $validFormats, true)) {
                throw new ValidationException(
                    "Invalid row format '{$this->rowFormat}'"
                );
            }
        }
    }

    // Factory methods

    /**
     * Create a new table definition
     */
    public static function create(string $name): self
    {
        return new self(name: $name);
    }

    // Column methods

    /**
     * Add a column to the table
     */
    public function addColumn(ColumnDefinition $column): self
    {
        $columns = $this->columns;
        $columns[$column->name] = $column;

        // Auto-detect primary key from column definition
        $primaryKey = $this->primaryKey;
        if ($column->primary && !in_array($column->name, $primaryKey, true)) {
            $primaryKey[] = $column->name;
        }

        return new self(
            name: $this->name,
            columns: $columns,
            indexes: $this->indexes,
            foreignKeys: $this->foreignKeys,
            primaryKey: $primaryKey,
            engine: $this->engine,
            charset: $this->charset,
            collation: $this->collation,
            comment: $this->comment,
            rowFormat: $this->rowFormat,
            checkConstraints: $this->checkConstraints,
        );
    }

    /**
     * Add multiple columns
     *
     * @param array<ColumnDefinition> $columns
     */
    public function addColumns(array $columns): self
    {
        $result = $this;
        foreach ($columns as $column) {
            $result = $result->addColumn($column);
        }
        return $result;
    }

    /**
     * Remove a column
     */
    public function removeColumn(string $columnName): self
    {
        $columns = $this->columns;
        unset($columns[$columnName]);

        $primaryKey = array_filter(
            $this->primaryKey,
            fn($col) => $col !== $columnName
        );

        return new self(
            name: $this->name,
            columns: $columns,
            indexes: $this->indexes,
            foreignKeys: $this->foreignKeys,
            primaryKey: array_values($primaryKey),
            engine: $this->engine,
            charset: $this->charset,
            collation: $this->collation,
            comment: $this->comment,
            rowFormat: $this->rowFormat,
            checkConstraints: $this->checkConstraints,
        );
    }

    /**
     * Check if a column exists
     */
    public function hasColumn(string $columnName): bool
    {
        return isset($this->columns[$columnName]);
    }

    /**
     * Get a column by name
     */
    public function getColumn(string $columnName): ?ColumnDefinition
    {
        return $this->columns[$columnName] ?? null;
    }

    // Index methods

    /**
     * Add an index
     */
    public function addIndex(IndexDefinition $index): self
    {
        $indexes = $this->indexes;
        $indexes[$index->name] = $index;

        return new self(
            name: $this->name,
            columns: $this->columns,
            indexes: $indexes,
            foreignKeys: $this->foreignKeys,
            primaryKey: $this->primaryKey,
            engine: $this->engine,
            charset: $this->charset,
            collation: $this->collation,
            comment: $this->comment,
            rowFormat: $this->rowFormat,
            checkConstraints: $this->checkConstraints,
        );
    }

    /**
     * Add multiple indexes
     *
     * @param array<IndexDefinition> $indexes
     */
    public function addIndexes(array $indexes): self
    {
        $result = $this;
        foreach ($indexes as $index) {
            $result = $result->addIndex($index);
        }
        return $result;
    }

    /**
     * Remove an index
     */
    public function removeIndex(string $indexName): self
    {
        $indexes = $this->indexes;
        unset($indexes[$indexName]);

        return new self(
            name: $this->name,
            columns: $this->columns,
            indexes: $indexes,
            foreignKeys: $this->foreignKeys,
            primaryKey: $this->primaryKey,
            engine: $this->engine,
            charset: $this->charset,
            collation: $this->collation,
            comment: $this->comment,
            rowFormat: $this->rowFormat,
            checkConstraints: $this->checkConstraints,
        );
    }

    /**
     * Check if an index exists
     */
    public function hasIndex(string $indexName): bool
    {
        return isset($this->indexes[$indexName]);
    }

    // Foreign key methods

    /**
     * Add a foreign key
     */
    public function addForeignKey(ForeignKeyDefinition $foreignKey): self
    {
        $foreignKeys = $this->foreignKeys;
        $foreignKeys[$foreignKey->name] = $foreignKey;

        return new self(
            name: $this->name,
            columns: $this->columns,
            indexes: $this->indexes,
            foreignKeys: $foreignKeys,
            primaryKey: $this->primaryKey,
            engine: $this->engine,
            charset: $this->charset,
            collation: $this->collation,
            comment: $this->comment,
            rowFormat: $this->rowFormat,
            checkConstraints: $this->checkConstraints,
        );
    }

    /**
     * Add multiple foreign keys
     *
     * @param array<ForeignKeyDefinition> $foreignKeys
     */
    public function addForeignKeys(array $foreignKeys): self
    {
        $result = $this;
        foreach ($foreignKeys as $fk) {
            $result = $result->addForeignKey($fk);
        }
        return $result;
    }

    /**
     * Remove a foreign key
     */
    public function removeForeignKey(string $fkName): self
    {
        $foreignKeys = $this->foreignKeys;
        unset($foreignKeys[$fkName]);

        return new self(
            name: $this->name,
            columns: $this->columns,
            indexes: $this->indexes,
            foreignKeys: $foreignKeys,
            primaryKey: $this->primaryKey,
            engine: $this->engine,
            charset: $this->charset,
            collation: $this->collation,
            comment: $this->comment,
            rowFormat: $this->rowFormat,
            checkConstraints: $this->checkConstraints,
        );
    }

    /**
     * Check if a foreign key exists
     */
    public function hasForeignKey(string $fkName): bool
    {
        return isset($this->foreignKeys[$fkName]);
    }

    // Primary key methods

    /**
     * Set primary key columns
     *
     * @param array<string> $columns Column names
     */
    public function primaryKey(array $columns): self
    {
        return new self(
            name: $this->name,
            columns: $this->columns,
            indexes: $this->indexes,
            foreignKeys: $this->foreignKeys,
            primaryKey: $columns,
            engine: $this->engine,
            charset: $this->charset,
            collation: $this->collation,
            comment: $this->comment,
            rowFormat: $this->rowFormat,
            checkConstraints: $this->checkConstraints,
        );
    }

    /**
     * Check if table has a primary key
     */
    public function hasPrimaryKey(): bool
    {
        return !empty($this->primaryKey);
    }

    // Check constraint methods

    /**
     * Add a CHECK constraint
     *
     * @param string $name Constraint name
     * @param string $expression Check expression (e.g., 'importance_score BETWEEN 0 AND 100')
     */
    public function addCheck(string $name, string $expression): self
    {
        $checks = $this->checkConstraints;
        $checks[$name] = $expression;

        return new self(
            name: $this->name,
            columns: $this->columns,
            indexes: $this->indexes,
            foreignKeys: $this->foreignKeys,
            primaryKey: $this->primaryKey,
            engine: $this->engine,
            charset: $this->charset,
            collation: $this->collation,
            comment: $this->comment,
            rowFormat: $this->rowFormat,
            checkConstraints: $checks,
        );
    }

    /**
     * Remove a CHECK constraint
     */
    public function removeCheck(string $name): self
    {
        $checks = $this->checkConstraints;
        unset($checks[$name]);

        return new self(
            name: $this->name,
            columns: $this->columns,
            indexes: $this->indexes,
            foreignKeys: $this->foreignKeys,
            primaryKey: $this->primaryKey,
            engine: $this->engine,
            charset: $this->charset,
            collation: $this->collation,
            comment: $this->comment,
            rowFormat: $this->rowFormat,
            checkConstraints: $checks,
        );
    }

    // Table options

    /**
     * Set storage engine
     */
    public function engine(string $engine): self
    {
        return new self(
            name: $this->name,
            columns: $this->columns,
            indexes: $this->indexes,
            foreignKeys: $this->foreignKeys,
            primaryKey: $this->primaryKey,
            engine: $engine,
            charset: $this->charset,
            collation: $this->collation,
            comment: $this->comment,
            rowFormat: $this->rowFormat,
            checkConstraints: $this->checkConstraints,
        );
    }

    /**
     * Set InnoDB engine
     */
    public function innoDB(): self
    {
        return $this->engine('InnoDB');
    }

    /**
     * Set character set
     */
    public function charset(string $charset): self
    {
        return new self(
            name: $this->name,
            columns: $this->columns,
            indexes: $this->indexes,
            foreignKeys: $this->foreignKeys,
            primaryKey: $this->primaryKey,
            engine: $this->engine,
            charset: $charset,
            collation: $this->collation,
            comment: $this->comment,
            rowFormat: $this->rowFormat,
            checkConstraints: $this->checkConstraints,
        );
    }

    /**
     * Set collation
     */
    public function collation(string $collation): self
    {
        return new self(
            name: $this->name,
            columns: $this->columns,
            indexes: $this->indexes,
            foreignKeys: $this->foreignKeys,
            primaryKey: $this->primaryKey,
            engine: $this->engine,
            charset: $this->charset,
            collation: $collation,
            comment: $this->comment,
            rowFormat: $this->rowFormat,
            checkConstraints: $this->checkConstraints,
        );
    }

    /**
     * Set table comment
     */
    public function comment(string $comment): self
    {
        return new self(
            name: $this->name,
            columns: $this->columns,
            indexes: $this->indexes,
            foreignKeys: $this->foreignKeys,
            primaryKey: $this->primaryKey,
            engine: $this->engine,
            charset: $this->charset,
            collation: $this->collation,
            comment: $comment,
            rowFormat: $this->rowFormat,
            checkConstraints: $this->checkConstraints,
        );
    }

    /**
     * Set row format
     */
    public function rowFormat(string $format): self
    {
        return new self(
            name: $this->name,
            columns: $this->columns,
            indexes: $this->indexes,
            foreignKeys: $this->foreignKeys,
            primaryKey: $this->primaryKey,
            engine: $this->engine,
            charset: $this->charset,
            collation: $this->collation,
            comment: $this->comment,
            rowFormat: strtoupper($format),
            checkConstraints: $this->checkConstraints,
        );
    }

    /**
     * Use compressed row format
     */
    public function compressed(): self
    {
        return $this->rowFormat('COMPRESSED');
    }

    /**
     * Use dynamic row format
     */
    public function dynamic(): self
    {
        return $this->rowFormat('DYNAMIC');
    }

    /**
     * Apply common Saga Manager defaults
     */
    public function withSagaDefaults(): self
    {
        return $this
            ->engine('InnoDB')
            ->charset('utf8mb4')
            ->collation('utf8mb4_unicode_ci');
    }
}
