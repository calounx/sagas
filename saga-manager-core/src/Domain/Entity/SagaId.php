<?php
declare(strict_types=1);

namespace SagaManagerCore\Domain\Entity;

use SagaManagerCore\Domain\Exception\ValidationException;

/**
 * Value object representing a unique saga identifier
 */
readonly class SagaId
{
    private int $id;

    public function __construct(int $id)
    {
        if ($id <= 0) {
            throw new ValidationException('Saga ID must be a positive integer');
        }

        $this->id = $id;
    }

    public function value(): int
    {
        return $this->id;
    }

    public function equals(self $other): bool
    {
        return $this->id === $other->id;
    }

    public function __toString(): string
    {
        return (string) $this->id;
    }
}
