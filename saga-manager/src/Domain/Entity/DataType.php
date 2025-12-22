<?php
declare(strict_types=1);

namespace SagaManager\Domain\Entity;

/**
 * Data Type Enumeration for EAV Attributes
 *
 * Defines the types of values that can be stored in attribute values
 */
enum DataType: string
{
    case STRING = 'string';
    case INT = 'int';
    case FLOAT = 'float';
    case BOOL = 'bool';
    case DATE = 'date';
    case TEXT = 'text';
    case JSON = 'json';

    public function label(): string
    {
        return match ($this) {
            self::STRING => 'String',
            self::INT => 'Integer',
            self::FLOAT => 'Decimal',
            self::BOOL => 'Boolean',
            self::DATE => 'Date',
            self::TEXT => 'Long Text',
            self::JSON => 'JSON Object',
        };
    }

    /**
     * Get the database column name for this data type
     */
    public function getValueColumn(): string
    {
        return match ($this) {
            self::STRING => 'value_string',
            self::INT => 'value_int',
            self::FLOAT => 'value_float',
            self::BOOL => 'value_bool',
            self::DATE => 'value_date',
            self::TEXT => 'value_text',
            self::JSON => 'value_json',
        };
    }

    /**
     * Check if this data type supports full-text search
     */
    public function isTextSearchable(): bool
    {
        return match ($this) {
            self::STRING, self::TEXT => true,
            default => false,
        };
    }

    /**
     * Get the wpdb format specifier for this data type
     */
    public function getWpdbFormat(): string
    {
        return match ($this) {
            self::INT, self::BOOL => '%d',
            self::FLOAT => '%f',
            default => '%s',
        };
    }
}
