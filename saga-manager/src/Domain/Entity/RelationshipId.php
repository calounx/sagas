<?php
declare(strict_types=1);

namespace SagaManager\Domain\Entity;

use SagaManager\Domain\Exception\ValidationException;

/**
 * Relationship ID Value Object
 *
 * Represents a unique identifier for entity relationships
 */
final readonly class RelationshipId
{
    private int $value;

    public function __construct(int $value)
    {
        if ($value <= 0) {
            throw new ValidationException('Relationship ID must be positive');
        }

        $this->value = $value;
    }

    public function value(): int
    {
        return $this->value;
    }

    public function equals(RelationshipId $other): bool
    {
        return $this->value === $other->value;
    }

    public function __toString(): string
    {
        return (string) $this->value;
    }
}
