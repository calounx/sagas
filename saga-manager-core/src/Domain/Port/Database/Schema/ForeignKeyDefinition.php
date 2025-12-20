<?php
declare(strict_types=1);

namespace SagaManagerCore\Domain\Port\Database\Schema;

use SagaManagerCore\Domain\Exception\ValidationException;

/**
 * Value object representing a foreign key constraint definition
 *
 * Immutable definition of a foreign key with referential actions.
 * Used by SchemaManagerInterface for DDL operations.
 *
 * @example
 * ```php
 * // Simple foreign key with CASCADE delete
 * $fk = ForeignKeyDefinition::create(
 *     name: 'fk_entity_saga',
 *     columns: ['saga_id'],
 *     referenceTable: 'saga_sagas',
 *     referenceColumns: ['id']
 * )->onDeleteCascade();
 *
 * // Composite foreign key
 * $compositeFk = ForeignKeyDefinition::create(
 *     name: 'fk_composite',
 *     columns: ['saga_id', 'entity_type'],
 *     referenceTable: 'saga_type_definitions',
 *     referenceColumns: ['saga_id', 'type']
 * )->onDeleteRestrict()
 *   ->onUpdateCascade();
 *
 * // Quick factory for single-column FK
 * $simpleFk = ForeignKeyDefinition::column(
 *     column: 'saga_id',
 *     referenceTable: 'saga_sagas'
 * )->onDeleteCascade();
 * ```
 */
readonly class ForeignKeyDefinition
{
    /**
     * @param string $name Constraint name
     * @param array<string> $columns Local column names
     * @param string $referenceTable Referenced table name (without prefix)
     * @param array<string> $referenceColumns Referenced column names
     * @param ReferentialAction $onDelete Action on parent delete
     * @param ReferentialAction $onUpdate Action on parent update
     */
    private function __construct(
        public string $name,
        public array $columns,
        public string $referenceTable,
        public array $referenceColumns,
        public ReferentialAction $onDelete = ReferentialAction::RESTRICT,
        public ReferentialAction $onUpdate = ReferentialAction::RESTRICT,
    ) {
        $this->validate();
    }

    /**
     * Validate foreign key definition
     *
     * @throws ValidationException
     */
    private function validate(): void
    {
        if (empty($this->name)) {
            throw new ValidationException('Foreign key name cannot be empty');
        }

        if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $this->name)) {
            throw new ValidationException(
                "Invalid foreign key name '{$this->name}': must start with letter or underscore"
            );
        }

        if (empty($this->columns)) {
            throw new ValidationException('Foreign key must have at least one column');
        }

        if (empty($this->referenceTable)) {
            throw new ValidationException('Reference table cannot be empty');
        }

        if (empty($this->referenceColumns)) {
            throw new ValidationException('Foreign key must reference at least one column');
        }

        if (count($this->columns) !== count($this->referenceColumns)) {
            throw new ValidationException(
                'Number of local columns must match number of reference columns'
            );
        }

        foreach ($this->columns as $column) {
            if (empty($column) || !is_string($column)) {
                throw new ValidationException('Column name must be a non-empty string');
            }
        }

        foreach ($this->referenceColumns as $column) {
            if (empty($column) || !is_string($column)) {
                throw new ValidationException('Reference column name must be a non-empty string');
            }
        }
    }

    // Factory methods

    /**
     * Create a foreign key definition
     *
     * @param string $name Constraint name
     * @param array<string> $columns Local column names
     * @param string $referenceTable Referenced table name
     * @param array<string> $referenceColumns Referenced column names
     */
    public static function create(
        string $name,
        array $columns,
        string $referenceTable,
        array $referenceColumns,
    ): self {
        return new self(
            name: $name,
            columns: $columns,
            referenceTable: $referenceTable,
            referenceColumns: $referenceColumns,
        );
    }

    /**
     * Create a simple single-column foreign key
     *
     * Assumes the reference column is 'id' if not specified.
     *
     * @param string $column Local column name
     * @param string $referenceTable Referenced table name
     * @param string $referenceColumn Referenced column name (defaults to 'id')
     * @param string|null $name Constraint name (auto-generated if null)
     */
    public static function column(
        string $column,
        string $referenceTable,
        string $referenceColumn = 'id',
        ?string $name = null,
    ): self {
        $name ??= "fk_{$column}";

        return new self(
            name: $name,
            columns: [$column],
            referenceTable: $referenceTable,
            referenceColumns: [$referenceColumn],
        );
    }

    // Fluent modifier methods

    /**
     * Set ON DELETE action
     */
    public function onDelete(ReferentialAction $action): self
    {
        return new self(
            name: $this->name,
            columns: $this->columns,
            referenceTable: $this->referenceTable,
            referenceColumns: $this->referenceColumns,
            onDelete: $action,
            onUpdate: $this->onUpdate,
        );
    }

    /**
     * Set ON UPDATE action
     */
    public function onUpdate(ReferentialAction $action): self
    {
        return new self(
            name: $this->name,
            columns: $this->columns,
            referenceTable: $this->referenceTable,
            referenceColumns: $this->referenceColumns,
            onDelete: $this->onDelete,
            onUpdate: $action,
        );
    }

    /**
     * Set ON DELETE CASCADE
     */
    public function onDeleteCascade(): self
    {
        return $this->onDelete(ReferentialAction::CASCADE);
    }

    /**
     * Set ON DELETE SET NULL
     */
    public function onDeleteSetNull(): self
    {
        return $this->onDelete(ReferentialAction::SET_NULL);
    }

    /**
     * Set ON DELETE RESTRICT
     */
    public function onDeleteRestrict(): self
    {
        return $this->onDelete(ReferentialAction::RESTRICT);
    }

    /**
     * Set ON DELETE NO ACTION
     */
    public function onDeleteNoAction(): self
    {
        return $this->onDelete(ReferentialAction::NO_ACTION);
    }

    /**
     * Set ON UPDATE CASCADE
     */
    public function onUpdateCascade(): self
    {
        return $this->onUpdate(ReferentialAction::CASCADE);
    }

    /**
     * Set ON UPDATE SET NULL
     */
    public function onUpdateSetNull(): self
    {
        return $this->onUpdate(ReferentialAction::SET_NULL);
    }

    /**
     * Set ON UPDATE RESTRICT
     */
    public function onUpdateRestrict(): self
    {
        return $this->onUpdate(ReferentialAction::RESTRICT);
    }

    /**
     * Set ON UPDATE NO ACTION
     */
    public function onUpdateNoAction(): self
    {
        return $this->onUpdate(ReferentialAction::NO_ACTION);
    }

    /**
     * Check if this is a composite (multi-column) foreign key
     */
    public function isComposite(): bool
    {
        return count($this->columns) > 1;
    }

    /**
     * Check if ON DELETE will cascade changes
     */
    public function cascadesOnDelete(): bool
    {
        return $this->onDelete === ReferentialAction::CASCADE;
    }

    /**
     * Check if ON UPDATE will cascade changes
     */
    public function cascadesOnUpdate(): bool
    {
        return $this->onUpdate === ReferentialAction::CASCADE;
    }
}
