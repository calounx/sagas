<?php
declare(strict_types=1);

namespace SagaManagerCore\Domain\Port\Database\Schema;

/**
 * Enum representing database index types
 *
 * @example
 * ```php
 * $type = IndexType::UNIQUE;
 * echo $type->value; // 'UNIQUE'
 * ```
 */
enum IndexType: string
{
    case PRIMARY = 'PRIMARY';
    case UNIQUE = 'UNIQUE';
    case INDEX = 'INDEX';
    case FULLTEXT = 'FULLTEXT';
    case SPATIAL = 'SPATIAL';

    /**
     * Check if this index type enforces uniqueness
     */
    public function isUnique(): bool
    {
        return match ($this) {
            self::PRIMARY, self::UNIQUE => true,
            default => false,
        };
    }

    /**
     * Check if index can span multiple columns
     */
    public function supportsMultipleColumns(): bool
    {
        return match ($this) {
            self::SPATIAL => false,
            default => true,
        };
    }
}
