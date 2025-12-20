<?php

declare(strict_types=1);

namespace SagaManagerCore\Infrastructure\Database\Exception;

use SagaManagerCore\Domain\Exception\DatabaseException;

/**
 * Exception thrown when schema operations fail
 *
 * Covers table creation, modification, and migration errors.
 */
class SchemaException extends DatabaseException
{
    private ?string $tableName;
    private ?string $columnName;
    private ?string $constraintName;

    public function __construct(
        string $message,
        ?string $tableName = null,
        ?string $columnName = null,
        ?string $constraintName = null,
        ?\Throwable $previous = null
    ) {
        parent::__construct($message, 0, $previous);
        $this->tableName = $tableName;
        $this->columnName = $columnName;
        $this->constraintName = $constraintName;
    }

    /**
     * Get the table name involved in the error
     */
    public function getTableName(): ?string
    {
        return $this->tableName;
    }

    /**
     * Get the column name involved in the error
     */
    public function getColumnName(): ?string
    {
        return $this->columnName;
    }

    /**
     * Get the constraint name involved in the error
     */
    public function getConstraintName(): ?string
    {
        return $this->constraintName;
    }

    /**
     * Create exception for table creation failure
     */
    public static function tableCreationFailed(string $table, string $reason): self
    {
        return new self(
            sprintf('Failed to create table "%s": %s', $table, $reason),
            $table
        );
    }

    /**
     * Create exception for table already exists
     */
    public static function tableAlreadyExists(string $table): self
    {
        return new self(
            sprintf('Table "%s" already exists', $table),
            $table
        );
    }

    /**
     * Create exception for table not found
     */
    public static function tableNotFound(string $table): self
    {
        return new self(
            sprintf('Table "%s" does not exist', $table),
            $table
        );
    }

    /**
     * Create exception for column addition failure
     */
    public static function columnAddFailed(string $table, string $column, string $reason): self
    {
        return new self(
            sprintf('Failed to add column "%s" to table "%s": %s', $column, $table, $reason),
            $table,
            $column
        );
    }

    /**
     * Create exception for column already exists
     */
    public static function columnAlreadyExists(string $table, string $column): self
    {
        return new self(
            sprintf('Column "%s" already exists in table "%s"', $column, $table),
            $table,
            $column
        );
    }

    /**
     * Create exception for column not found
     */
    public static function columnNotFound(string $table, string $column): self
    {
        return new self(
            sprintf('Column "%s" does not exist in table "%s"', $column, $table),
            $table,
            $column
        );
    }

    /**
     * Create exception for index creation failure
     */
    public static function indexCreationFailed(string $table, string $index, string $reason): self
    {
        return new self(
            sprintf('Failed to create index "%s" on table "%s": %s', $index, $table, $reason),
            $table,
            null,
            $index
        );
    }

    /**
     * Create exception for foreign key creation failure
     */
    public static function foreignKeyFailed(string $table, string $constraint, string $reason): self
    {
        return new self(
            sprintf('Failed to create foreign key "%s" on table "%s": %s', $constraint, $table, $reason),
            $table,
            null,
            $constraint
        );
    }

    /**
     * Create exception for migration failure
     */
    public static function migrationFailed(string $migration, string $reason): self
    {
        return new self(
            sprintf('Migration "%s" failed: %s', $migration, $reason)
        );
    }

    /**
     * Create exception for invalid schema version
     */
    public static function invalidSchemaVersion(string $current, string $expected): self
    {
        return new self(
            sprintf('Invalid schema version: current "%s", expected "%s"', $current, $expected)
        );
    }

    /**
     * Create exception from WordPress dbDelta error
     */
    public static function fromDbDelta(string $table, string $error): self
    {
        return new self(
            sprintf('dbDelta error for table "%s": %s', $table, $error),
            $table
        );
    }
}
