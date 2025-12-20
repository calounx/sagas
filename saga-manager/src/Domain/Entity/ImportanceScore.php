<?php
declare(strict_types=1);

namespace SagaManager\Domain\Entity;

use SagaManager\Domain\Exception\InvalidImportanceScoreException;

/**
 * Importance Score Value Object
 *
 * Represents entity importance on a 0-100 scale
 */
final readonly class ImportanceScore
{
    private const MIN_SCORE = 0;
    private const MAX_SCORE = 100;
    private const DEFAULT_SCORE = 50;

    private int $value;

    public function __construct(int $value)
    {
        if ($value < self::MIN_SCORE || $value > self::MAX_SCORE) {
            throw new InvalidImportanceScoreException(
                sprintf('Importance score must be between %d and %d, got %d',
                    self::MIN_SCORE,
                    self::MAX_SCORE,
                    $value
                )
            );
        }

        $this->value = $value;
    }

    public static function default(): self
    {
        return new self(self::DEFAULT_SCORE);
    }

    public function value(): int
    {
        return $this->value;
    }

    public function isHighImportance(): bool
    {
        return $this->value >= 75;
    }

    public function isLowImportance(): bool
    {
        return $this->value <= 25;
    }

    public function equals(ImportanceScore $other): bool
    {
        return $this->value === $other->value;
    }

    public function __toString(): string
    {
        return (string) $this->value;
    }
}
