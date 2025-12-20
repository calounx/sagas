<?php
declare(strict_types=1);

namespace SagaManagerCore\Domain\Port\Database\Schema;

/**
 * Enum representing database column types
 *
 * Covers standard SQL types used in Saga Manager schema.
 * Types are mapped to specific database implementations in infrastructure layer.
 *
 * @example
 * ```php
 * $type = ColumnType::VARCHAR;
 * echo $type->requiresLength(); // true
 * echo $type->isNumeric(); // false
 * ```
 */
enum ColumnType: string
{
    // Integer types
    case TINYINT = 'TINYINT';
    case SMALLINT = 'SMALLINT';
    case MEDIUMINT = 'MEDIUMINT';
    case INT = 'INT';
    case BIGINT = 'BIGINT';

    // Floating point types
    case FLOAT = 'FLOAT';
    case DOUBLE = 'DOUBLE';
    case DECIMAL = 'DECIMAL';

    // String types
    case CHAR = 'CHAR';
    case VARCHAR = 'VARCHAR';
    case TEXT = 'TEXT';
    case MEDIUMTEXT = 'MEDIUMTEXT';
    case LONGTEXT = 'LONGTEXT';

    // Binary types
    case BINARY = 'BINARY';
    case VARBINARY = 'VARBINARY';
    case BLOB = 'BLOB';
    case MEDIUMBLOB = 'MEDIUMBLOB';
    case LONGBLOB = 'LONGBLOB';

    // Date/Time types
    case DATE = 'DATE';
    case TIME = 'TIME';
    case DATETIME = 'DATETIME';
    case TIMESTAMP = 'TIMESTAMP';
    case YEAR = 'YEAR';

    // Other types
    case BOOLEAN = 'BOOLEAN';
    case ENUM = 'ENUM';
    case SET = 'SET';
    case JSON = 'JSON';

    /**
     * Check if column type requires a length specification
     */
    public function requiresLength(): bool
    {
        return match ($this) {
            self::CHAR, self::VARCHAR, self::BINARY, self::VARBINARY => true,
            default => false,
        };
    }

    /**
     * Check if column type supports length specification (optional)
     */
    public function supportsLength(): bool
    {
        return match ($this) {
            self::TINYINT, self::SMALLINT, self::MEDIUMINT, self::INT, self::BIGINT,
            self::CHAR, self::VARCHAR, self::BINARY, self::VARBINARY => true,
            default => false,
        };
    }

    /**
     * Check if column type requires precision/scale
     */
    public function requiresPrecision(): bool
    {
        return $this === self::DECIMAL;
    }

    /**
     * Check if column type supports precision/scale
     */
    public function supportsPrecision(): bool
    {
        return match ($this) {
            self::DECIMAL, self::FLOAT, self::DOUBLE => true,
            default => false,
        };
    }

    /**
     * Check if column type is numeric
     */
    public function isNumeric(): bool
    {
        return match ($this) {
            self::TINYINT, self::SMALLINT, self::MEDIUMINT, self::INT, self::BIGINT,
            self::FLOAT, self::DOUBLE, self::DECIMAL => true,
            default => false,
        };
    }

    /**
     * Check if column type is a string type
     */
    public function isString(): bool
    {
        return match ($this) {
            self::CHAR, self::VARCHAR, self::TEXT, self::MEDIUMTEXT, self::LONGTEXT => true,
            default => false,
        };
    }

    /**
     * Check if column type is a binary type
     */
    public function isBinary(): bool
    {
        return match ($this) {
            self::BINARY, self::VARBINARY, self::BLOB, self::MEDIUMBLOB, self::LONGBLOB => true,
            default => false,
        };
    }

    /**
     * Check if column type is a date/time type
     */
    public function isDateTime(): bool
    {
        return match ($this) {
            self::DATE, self::TIME, self::DATETIME, self::TIMESTAMP, self::YEAR => true,
            default => false,
        };
    }

    /**
     * Check if column type supports unsigned modifier
     */
    public function supportsUnsigned(): bool
    {
        return match ($this) {
            self::TINYINT, self::SMALLINT, self::MEDIUMINT, self::INT, self::BIGINT,
            self::FLOAT, self::DOUBLE, self::DECIMAL => true,
            default => false,
        };
    }

    /**
     * Check if column type supports auto increment
     */
    public function supportsAutoIncrement(): bool
    {
        return match ($this) {
            self::TINYINT, self::SMALLINT, self::MEDIUMINT, self::INT, self::BIGINT => true,
            default => false,
        };
    }

    /**
     * Check if column type requires enum values
     */
    public function requiresValues(): bool
    {
        return match ($this) {
            self::ENUM, self::SET => true,
            default => false,
        };
    }

    /**
     * Get default length for types that require it
     */
    public function getDefaultLength(): ?int
    {
        return match ($this) {
            self::CHAR => 1,
            self::VARCHAR => 255,
            self::BINARY => 1,
            self::VARBINARY => 255,
            default => null,
        };
    }
}
