<?php
declare(strict_types=1);

namespace SagaManagerCore\Domain\Port\Database\Schema;

use SagaManagerCore\Domain\Exception\ValidationException;

/**
 * Value object representing a database index definition
 *
 * Immutable definition of a table index with all its properties.
 * Used by SchemaManagerInterface for DDL operations.
 *
 * @example
 * ```php
 * // Simple index on single column
 * $index = IndexDefinition::index('idx_saga_type', ['saga_id', 'entity_type']);
 *
 * // Unique index
 * $unique = IndexDefinition::unique('uk_saga_name', ['saga_id', 'canonical_name']);
 *
 * // Full-text index for search
 * $fulltext = IndexDefinition::fulltext('ft_fragment', ['fragment_text']);
 *
 * // Index with prefix lengths
 * $prefixed = IndexDefinition::index('idx_searchable', ['attribute_id', 'value_string'])
 *     ->withPrefixLength('value_string', 100);
 * ```
 */
readonly class IndexDefinition
{
    /**
     * @param string $name Index name
     * @param IndexType $type Index type
     * @param array<string> $columns Column names in index order
     * @param array<string, int> $prefixLengths Optional prefix lengths for columns
     * @param string|null $comment Index comment
     * @param string|null $algorithm Index algorithm (BTREE, HASH)
     */
    private function __construct(
        public string $name,
        public IndexType $type,
        public array $columns,
        public array $prefixLengths = [],
        public ?string $comment = null,
        public ?string $algorithm = null,
    ) {
        $this->validate();
    }

    /**
     * Validate index definition
     *
     * @throws ValidationException
     */
    private function validate(): void
    {
        if (empty($this->name)) {
            throw new ValidationException('Index name cannot be empty');
        }

        if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $this->name)) {
            throw new ValidationException(
                "Invalid index name '{$this->name}': must start with letter or underscore"
            );
        }

        if (empty($this->columns)) {
            throw new ValidationException('Index must have at least one column');
        }

        if (!$this->type->supportsMultipleColumns() && count($this->columns) > 1) {
            throw new ValidationException(
                "Index type {$this->type->value} does not support multiple columns"
            );
        }

        foreach ($this->columns as $column) {
            if (empty($column) || !is_string($column)) {
                throw new ValidationException('Column name must be a non-empty string');
            }
        }

        foreach ($this->prefixLengths as $column => $length) {
            if (!in_array($column, $this->columns, true)) {
                throw new ValidationException(
                    "Prefix length specified for column '{$column}' not in index"
                );
            }
            if ($length <= 0) {
                throw new ValidationException(
                    "Prefix length for column '{$column}' must be positive"
                );
            }
        }

        if ($this->algorithm !== null && !in_array($this->algorithm, ['BTREE', 'HASH'], true)) {
            throw new ValidationException(
                "Invalid index algorithm '{$this->algorithm}': must be BTREE or HASH"
            );
        }
    }

    // Factory methods

    /**
     * Create a standard index
     *
     * @param string $name Index name
     * @param array<string> $columns Column names
     */
    public static function index(string $name, array $columns): self
    {
        return new self(name: $name, type: IndexType::INDEX, columns: $columns);
    }

    /**
     * Create a unique index
     *
     * @param string $name Index name
     * @param array<string> $columns Column names
     */
    public static function unique(string $name, array $columns): self
    {
        return new self(name: $name, type: IndexType::UNIQUE, columns: $columns);
    }

    /**
     * Create a primary key index
     *
     * @param array<string> $columns Column names
     */
    public static function primary(array $columns): self
    {
        return new self(name: 'PRIMARY', type: IndexType::PRIMARY, columns: $columns);
    }

    /**
     * Create a full-text index
     *
     * @param string $name Index name
     * @param array<string> $columns Column names
     */
    public static function fulltext(string $name, array $columns): self
    {
        return new self(name: $name, type: IndexType::FULLTEXT, columns: $columns);
    }

    /**
     * Create a spatial index
     *
     * @param string $name Index name
     * @param string $column Column name
     */
    public static function spatial(string $name, string $column): self
    {
        return new self(name: $name, type: IndexType::SPATIAL, columns: [$column]);
    }

    /**
     * Create index with explicit type
     *
     * @param string $name Index name
     * @param IndexType $type Index type
     * @param array<string> $columns Column names
     */
    public static function create(string $name, IndexType $type, array $columns): self
    {
        return new self(name: $name, type: $type, columns: $columns);
    }

    // Fluent modifier methods

    /**
     * Set prefix length for a column
     *
     * @param string $column Column name
     * @param int $length Prefix length
     */
    public function withPrefixLength(string $column, int $length): self
    {
        $prefixLengths = $this->prefixLengths;
        $prefixLengths[$column] = $length;

        return new self(
            name: $this->name,
            type: $this->type,
            columns: $this->columns,
            prefixLengths: $prefixLengths,
            comment: $this->comment,
            algorithm: $this->algorithm,
        );
    }

    /**
     * Set index comment
     */
    public function comment(string $comment): self
    {
        return new self(
            name: $this->name,
            type: $this->type,
            columns: $this->columns,
            prefixLengths: $this->prefixLengths,
            comment: $comment,
            algorithm: $this->algorithm,
        );
    }

    /**
     * Set index algorithm (BTREE or HASH)
     */
    public function algorithm(string $algorithm): self
    {
        return new self(
            name: $this->name,
            type: $this->type,
            columns: $this->columns,
            prefixLengths: $this->prefixLengths,
            comment: $this->comment,
            algorithm: strtoupper($algorithm),
        );
    }

    /**
     * Use BTREE algorithm
     */
    public function btree(): self
    {
        return $this->algorithm('BTREE');
    }

    /**
     * Use HASH algorithm
     */
    public function hash(): self
    {
        return $this->algorithm('HASH');
    }

    /**
     * Check if this is a composite (multi-column) index
     */
    public function isComposite(): bool
    {
        return count($this->columns) > 1;
    }

    /**
     * Get the prefix length for a column, or null if none specified
     */
    public function getPrefixLength(string $column): ?int
    {
        return $this->prefixLengths[$column] ?? null;
    }
}
