<?php
declare(strict_types=1);

namespace SagaManagerCore\Domain\Port\Database\Schema;

use SagaManagerCore\Domain\Exception\ValidationException;

/**
 * Value object representing a database column definition
 *
 * Immutable definition of a table column with all its properties.
 * Used by SchemaManagerInterface for DDL operations.
 *
 * @example
 * ```php
 * // Simple varchar column
 * $nameColumn = ColumnDefinition::varchar('canonical_name', 255)
 *     ->notNull()
 *     ->comment('Entity canonical name');
 *
 * // Auto-incrementing primary key
 * $idColumn = ColumnDefinition::bigInt('id')
 *     ->unsigned()
 *     ->autoIncrement()
 *     ->primary();
 *
 * // Timestamp with default
 * $createdAt = ColumnDefinition::timestamp('created_at')
 *     ->default('CURRENT_TIMESTAMP');
 *
 * // Enum column
 * $entityType = ColumnDefinition::enum('entity_type', [
 *     'character', 'location', 'event', 'faction', 'artifact', 'concept'
 * ])->notNull();
 * ```
 */
readonly class ColumnDefinition
{
    /**
     * @param string $name Column name
     * @param ColumnType $type Column type
     * @param int|null $length Length for VARCHAR, CHAR, etc.
     * @param int|null $precision Precision for DECIMAL
     * @param int|null $scale Scale for DECIMAL
     * @param bool $nullable Whether column allows NULL
     * @param bool $unsigned Whether numeric column is unsigned
     * @param bool $autoIncrement Whether column auto increments
     * @param bool $primary Whether column is primary key
     * @param string|null $default Default value (use 'CURRENT_TIMESTAMP' for timestamp defaults)
     * @param string|null $comment Column comment
     * @param array<string>|null $enumValues Values for ENUM/SET types
     * @param string|null $after Column to place this column after (for ALTER TABLE)
     * @param string|null $collation Character collation
     * @param string|null $charset Character set
     */
    private function __construct(
        public string $name,
        public ColumnType $type,
        public ?int $length = null,
        public ?int $precision = null,
        public ?int $scale = null,
        public bool $nullable = true,
        public bool $unsigned = false,
        public bool $autoIncrement = false,
        public bool $primary = false,
        public ?string $default = null,
        public ?string $comment = null,
        public ?array $enumValues = null,
        public ?string $after = null,
        public ?string $collation = null,
        public ?string $charset = null,
    ) {
        $this->validate();
    }

    /**
     * Validate column definition consistency
     *
     * @throws ValidationException
     */
    private function validate(): void
    {
        if (empty($this->name)) {
            throw new ValidationException('Column name cannot be empty');
        }

        if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $this->name)) {
            throw new ValidationException(
                "Invalid column name '{$this->name}': must start with letter or underscore"
            );
        }

        if ($this->type->requiresLength() && $this->length === null) {
            throw new ValidationException(
                "Column type {$this->type->value} requires a length specification"
            );
        }

        if ($this->type->requiresPrecision() && $this->precision === null) {
            throw new ValidationException(
                "Column type {$this->type->value} requires precision specification"
            );
        }

        if ($this->type->requiresValues() && empty($this->enumValues)) {
            throw new ValidationException(
                "Column type {$this->type->value} requires enum values"
            );
        }

        if ($this->unsigned && !$this->type->supportsUnsigned()) {
            throw new ValidationException(
                "Column type {$this->type->value} does not support UNSIGNED"
            );
        }

        if ($this->autoIncrement && !$this->type->supportsAutoIncrement()) {
            throw new ValidationException(
                "Column type {$this->type->value} does not support AUTO_INCREMENT"
            );
        }

        if ($this->length !== null && !$this->type->supportsLength()) {
            throw new ValidationException(
                "Column type {$this->type->value} does not support length specification"
            );
        }
    }

    // Factory methods for common column types

    /**
     * Create a TINYINT column
     */
    public static function tinyInt(string $name, ?int $length = null): self
    {
        return new self(name: $name, type: ColumnType::TINYINT, length: $length);
    }

    /**
     * Create a SMALLINT column
     */
    public static function smallInt(string $name, ?int $length = null): self
    {
        return new self(name: $name, type: ColumnType::SMALLINT, length: $length);
    }

    /**
     * Create an INT column
     */
    public static function int(string $name, ?int $length = null): self
    {
        return new self(name: $name, type: ColumnType::INT, length: $length);
    }

    /**
     * Create a BIGINT column
     */
    public static function bigInt(string $name, ?int $length = null): self
    {
        return new self(name: $name, type: ColumnType::BIGINT, length: $length);
    }

    /**
     * Create a FLOAT column
     */
    public static function float(string $name): self
    {
        return new self(name: $name, type: ColumnType::FLOAT);
    }

    /**
     * Create a DOUBLE column
     */
    public static function double(string $name): self
    {
        return new self(name: $name, type: ColumnType::DOUBLE);
    }

    /**
     * Create a DECIMAL column
     */
    public static function decimal(string $name, int $precision = 10, int $scale = 2): self
    {
        return new self(
            name: $name,
            type: ColumnType::DECIMAL,
            precision: $precision,
            scale: $scale
        );
    }

    /**
     * Create a CHAR column
     */
    public static function char(string $name, int $length = 1): self
    {
        return new self(name: $name, type: ColumnType::CHAR, length: $length);
    }

    /**
     * Create a VARCHAR column
     */
    public static function varchar(string $name, int $length = 255): self
    {
        return new self(name: $name, type: ColumnType::VARCHAR, length: $length);
    }

    /**
     * Create a TEXT column
     */
    public static function text(string $name): self
    {
        return new self(name: $name, type: ColumnType::TEXT);
    }

    /**
     * Create a MEDIUMTEXT column
     */
    public static function mediumText(string $name): self
    {
        return new self(name: $name, type: ColumnType::MEDIUMTEXT);
    }

    /**
     * Create a LONGTEXT column
     */
    public static function longText(string $name): self
    {
        return new self(name: $name, type: ColumnType::LONGTEXT);
    }

    /**
     * Create a BLOB column
     */
    public static function blob(string $name): self
    {
        return new self(name: $name, type: ColumnType::BLOB);
    }

    /**
     * Create a DATE column
     */
    public static function date(string $name): self
    {
        return new self(name: $name, type: ColumnType::DATE);
    }

    /**
     * Create a DATETIME column
     */
    public static function datetime(string $name): self
    {
        return new self(name: $name, type: ColumnType::DATETIME);
    }

    /**
     * Create a TIMESTAMP column
     */
    public static function timestamp(string $name): self
    {
        return new self(name: $name, type: ColumnType::TIMESTAMP);
    }

    /**
     * Create a BOOLEAN column
     */
    public static function boolean(string $name): self
    {
        return new self(name: $name, type: ColumnType::BOOLEAN);
    }

    /**
     * Create an ENUM column
     *
     * @param string $name Column name
     * @param array<string> $values Allowed enum values
     */
    public static function enum(string $name, array $values): self
    {
        return new self(name: $name, type: ColumnType::ENUM, enumValues: $values);
    }

    /**
     * Create a JSON column
     */
    public static function json(string $name): self
    {
        return new self(name: $name, type: ColumnType::JSON);
    }

    /**
     * Create a generic column with specified type
     */
    public static function create(string $name, ColumnType $type): self
    {
        $length = $type->requiresLength() ? $type->getDefaultLength() : null;
        return new self(name: $name, type: $type, length: $length);
    }

    // Fluent modifier methods (return new instance)

    /**
     * Set column length
     */
    public function length(int $length): self
    {
        return new self(
            name: $this->name,
            type: $this->type,
            length: $length,
            precision: $this->precision,
            scale: $this->scale,
            nullable: $this->nullable,
            unsigned: $this->unsigned,
            autoIncrement: $this->autoIncrement,
            primary: $this->primary,
            default: $this->default,
            comment: $this->comment,
            enumValues: $this->enumValues,
            after: $this->after,
            collation: $this->collation,
            charset: $this->charset,
        );
    }

    /**
     * Make column NOT NULL
     */
    public function notNull(): self
    {
        return new self(
            name: $this->name,
            type: $this->type,
            length: $this->length,
            precision: $this->precision,
            scale: $this->scale,
            nullable: false,
            unsigned: $this->unsigned,
            autoIncrement: $this->autoIncrement,
            primary: $this->primary,
            default: $this->default,
            comment: $this->comment,
            enumValues: $this->enumValues,
            after: $this->after,
            collation: $this->collation,
            charset: $this->charset,
        );
    }

    /**
     * Make column nullable (default)
     */
    public function nullable(): self
    {
        return new self(
            name: $this->name,
            type: $this->type,
            length: $this->length,
            precision: $this->precision,
            scale: $this->scale,
            nullable: true,
            unsigned: $this->unsigned,
            autoIncrement: $this->autoIncrement,
            primary: $this->primary,
            default: $this->default,
            comment: $this->comment,
            enumValues: $this->enumValues,
            after: $this->after,
            collation: $this->collation,
            charset: $this->charset,
        );
    }

    /**
     * Make numeric column unsigned
     */
    public function unsigned(): self
    {
        return new self(
            name: $this->name,
            type: $this->type,
            length: $this->length,
            precision: $this->precision,
            scale: $this->scale,
            nullable: $this->nullable,
            unsigned: true,
            autoIncrement: $this->autoIncrement,
            primary: $this->primary,
            default: $this->default,
            comment: $this->comment,
            enumValues: $this->enumValues,
            after: $this->after,
            collation: $this->collation,
            charset: $this->charset,
        );
    }

    /**
     * Make column auto increment
     */
    public function autoIncrement(): self
    {
        return new self(
            name: $this->name,
            type: $this->type,
            length: $this->length,
            precision: $this->precision,
            scale: $this->scale,
            nullable: false,
            unsigned: $this->unsigned,
            autoIncrement: true,
            primary: $this->primary,
            default: $this->default,
            comment: $this->comment,
            enumValues: $this->enumValues,
            after: $this->after,
            collation: $this->collation,
            charset: $this->charset,
        );
    }

    /**
     * Make column primary key
     */
    public function primary(): self
    {
        return new self(
            name: $this->name,
            type: $this->type,
            length: $this->length,
            precision: $this->precision,
            scale: $this->scale,
            nullable: false,
            unsigned: $this->unsigned,
            autoIncrement: $this->autoIncrement,
            primary: true,
            default: $this->default,
            comment: $this->comment,
            enumValues: $this->enumValues,
            after: $this->after,
            collation: $this->collation,
            charset: $this->charset,
        );
    }

    /**
     * Set default value
     *
     * Use 'CURRENT_TIMESTAMP' for timestamp columns
     */
    public function default(string $value): self
    {
        return new self(
            name: $this->name,
            type: $this->type,
            length: $this->length,
            precision: $this->precision,
            scale: $this->scale,
            nullable: $this->nullable,
            unsigned: $this->unsigned,
            autoIncrement: $this->autoIncrement,
            primary: $this->primary,
            default: $value,
            comment: $this->comment,
            enumValues: $this->enumValues,
            after: $this->after,
            collation: $this->collation,
            charset: $this->charset,
        );
    }

    /**
     * Set column comment
     */
    public function comment(string $comment): self
    {
        return new self(
            name: $this->name,
            type: $this->type,
            length: $this->length,
            precision: $this->precision,
            scale: $this->scale,
            nullable: $this->nullable,
            unsigned: $this->unsigned,
            autoIncrement: $this->autoIncrement,
            primary: $this->primary,
            default: $this->default,
            comment: $comment,
            enumValues: $this->enumValues,
            after: $this->after,
            collation: $this->collation,
            charset: $this->charset,
        );
    }

    /**
     * Place column after another column (for ALTER TABLE)
     */
    public function after(string $column): self
    {
        return new self(
            name: $this->name,
            type: $this->type,
            length: $this->length,
            precision: $this->precision,
            scale: $this->scale,
            nullable: $this->nullable,
            unsigned: $this->unsigned,
            autoIncrement: $this->autoIncrement,
            primary: $this->primary,
            default: $this->default,
            comment: $this->comment,
            enumValues: $this->enumValues,
            after: $column,
            collation: $this->collation,
            charset: $this->charset,
        );
    }

    /**
     * Set character collation
     */
    public function collation(string $collation): self
    {
        return new self(
            name: $this->name,
            type: $this->type,
            length: $this->length,
            precision: $this->precision,
            scale: $this->scale,
            nullable: $this->nullable,
            unsigned: $this->unsigned,
            autoIncrement: $this->autoIncrement,
            primary: $this->primary,
            default: $this->default,
            comment: $this->comment,
            enumValues: $this->enumValues,
            after: $this->after,
            collation: $collation,
            charset: $this->charset,
        );
    }

    /**
     * Set character set
     */
    public function charset(string $charset): self
    {
        return new self(
            name: $this->name,
            type: $this->type,
            length: $this->length,
            precision: $this->precision,
            scale: $this->scale,
            nullable: $this->nullable,
            unsigned: $this->unsigned,
            autoIncrement: $this->autoIncrement,
            primary: $this->primary,
            default: $this->default,
            comment: $this->comment,
            enumValues: $this->enumValues,
            after: $this->after,
            collation: $this->collation,
            charset: $charset,
        );
    }
}
