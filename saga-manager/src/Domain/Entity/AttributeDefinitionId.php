<?php
declare(strict_types=1);

namespace SagaManager\Domain\Entity;

use SagaManager\Domain\Exception\ValidationException;

/**
 * Attribute Definition ID Value Object
 *
 * Represents a unique identifier for attribute definitions
 */
final readonly class AttributeDefinitionId
{
    private int $value;

    public function __construct(int $value)
    {
        if ($value <= 0) {
            throw new ValidationException('Attribute Definition ID must be positive');
        }

        $this->value = $value;
    }

    public function value(): int
    {
        return $this->value;
    }

    public function equals(AttributeDefinitionId $other): bool
    {
        return $this->value === $other->value;
    }

    public function __toString(): string
    {
        return (string) $this->value;
    }
}
