<?php
declare(strict_types=1);

namespace SagaManager\Domain\Entity;

use SagaManager\Domain\Exception\ValidationException;

/**
 * Timeline Event ID Value Object
 */
final readonly class TimelineEventId
{
    private int $value;

    public function __construct(int $value)
    {
        if ($value <= 0) {
            throw new ValidationException('Timeline event ID must be positive');
        }

        $this->value = $value;
    }

    public function value(): int
    {
        return $this->value;
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }

    public function __toString(): string
    {
        return (string) $this->value;
    }
}
