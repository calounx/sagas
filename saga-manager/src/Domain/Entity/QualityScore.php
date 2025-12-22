<?php
declare(strict_types=1);

namespace SagaManager\Domain\Entity;

use SagaManager\Domain\Exception\ValidationException;

/**
 * Quality Score Value Object
 *
 * Represents a percentage score from 0-100.
 */
final readonly class QualityScore
{
    private const MIN_SCORE = 0;
    private const MAX_SCORE = 100;

    public function __construct(
        private int $value
    ) {
        if ($value < self::MIN_SCORE || $value > self::MAX_SCORE) {
            throw new ValidationException(
                sprintf('Quality score must be between %d and %d, got %d', self::MIN_SCORE, self::MAX_SCORE, $value)
            );
        }
    }

    public function value(): int
    {
        return $this->value;
    }

    public function isExcellent(): bool
    {
        return $this->value >= 90;
    }

    public function isGood(): bool
    {
        return $this->value >= 70 && $this->value < 90;
    }

    public function isFair(): bool
    {
        return $this->value >= 50 && $this->value < 70;
    }

    public function isPoor(): bool
    {
        return $this->value < 50;
    }

    public function getGrade(): string
    {
        return match (true) {
            $this->isExcellent() => 'A',
            $this->isGood() => 'B',
            $this->isFair() => 'C',
            default => 'D',
        };
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
