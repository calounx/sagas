<?php
declare(strict_types=1);

namespace SagaManagerCore\Domain\Port\Database\Schema;

/**
 * Enum representing foreign key referential actions
 *
 * Defines the action to take when a referenced row is updated or deleted.
 *
 * @example
 * ```php
 * $action = ReferentialAction::CASCADE;
 * echo $action->value; // 'CASCADE'
 * ```
 */
enum ReferentialAction: string
{
    case RESTRICT = 'RESTRICT';
    case CASCADE = 'CASCADE';
    case SET_NULL = 'SET NULL';
    case NO_ACTION = 'NO ACTION';
    case SET_DEFAULT = 'SET DEFAULT';

    /**
     * Check if action can cause data modification in child table
     */
    public function modifiesChildData(): bool
    {
        return match ($this) {
            self::CASCADE, self::SET_NULL, self::SET_DEFAULT => true,
            default => false,
        };
    }

    /**
     * Check if action can cause child rows to be deleted
     */
    public function canDeleteChildRows(): bool
    {
        return $this === self::CASCADE;
    }
}
