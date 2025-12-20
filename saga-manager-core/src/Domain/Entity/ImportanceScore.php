<?php
declare(strict_types=1);

namespace SagaManagerCore\Domain\Entity;

use SagaManagerCore\Domain\Exception\InvalidImportanceScoreException;

/**
 * Value object representing an entity's importance score (0-100)
 */
readonly class ImportanceScore
{
    private const MIN = 0;
    private const MAX = 100;
    private const DEFAULT = 50;

    private int $score;

    public function __construct(int $score)
    {
        if ($score < self::MIN || $score > self::MAX) {
            throw new InvalidImportanceScoreException(
                sprintf('Importance score must be between %d and %d, got %d', self::MIN, self::MAX, $score)
            );
        }

        $this->score = $score;
    }

    public static function default(): self
    {
        return new self(self::DEFAULT);
    }

    public function value(): int
    {
        return $this->score;
    }

    public function isHighImportance(): bool
    {
        return $this->score >= 75;
    }

    public function isMediumImportance(): bool
    {
        return $this->score >= 40 && $this->score < 75;
    }

    public function isLowImportance(): bool
    {
        return $this->score < 40;
    }

    public function equals(self $other): bool
    {
        return $this->score === $other->score;
    }

    public function __toString(): string
    {
        return (string) $this->score;
    }
}
