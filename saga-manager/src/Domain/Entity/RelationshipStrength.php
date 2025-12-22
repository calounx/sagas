<?php
declare(strict_types=1);

namespace SagaManager\Domain\Entity;

use SagaManager\Domain\Exception\ValidationException;

/**
 * Relationship Strength Value Object
 *
 * Represents the strength/intensity of a relationship between entities (0-100 scale).
 * Higher values indicate stronger relationships.
 */
final readonly class RelationshipStrength
{
    private const MIN_STRENGTH = 0;
    private const MAX_STRENGTH = 100;
    private const DEFAULT_STRENGTH = 50;

    private const WEAK_THRESHOLD = 25;
    private const STRONG_THRESHOLD = 75;

    private int $value;

    public function __construct(int $value)
    {
        if ($value < self::MIN_STRENGTH || $value > self::MAX_STRENGTH) {
            throw new ValidationException(
                sprintf(
                    'Relationship strength must be between %d and %d, got %d',
                    self::MIN_STRENGTH,
                    self::MAX_STRENGTH,
                    $value
                )
            );
        }

        $this->value = $value;
    }

    public static function default(): self
    {
        return new self(self::DEFAULT_STRENGTH);
    }

    public static function weak(): self
    {
        return new self(self::WEAK_THRESHOLD);
    }

    public static function strong(): self
    {
        return new self(self::STRONG_THRESHOLD);
    }

    public static function maximum(): self
    {
        return new self(self::MAX_STRENGTH);
    }

    public function value(): int
    {
        return $this->value;
    }

    /**
     * Check if this is a strong relationship (>= 75)
     */
    public function isStrong(): bool
    {
        return $this->value >= self::STRONG_THRESHOLD;
    }

    /**
     * Check if this is a weak relationship (<= 25)
     */
    public function isWeak(): bool
    {
        return $this->value <= self::WEAK_THRESHOLD;
    }

    /**
     * Check if this is a moderate relationship (26-74)
     */
    public function isModerate(): bool
    {
        return !$this->isWeak() && !$this->isStrong();
    }

    /**
     * Get a descriptive label for the strength level
     */
    public function label(): string
    {
        if ($this->isStrong()) {
            return 'Strong';
        }
        if ($this->isWeak()) {
            return 'Weak';
        }
        return 'Moderate';
    }

    public function equals(RelationshipStrength $other): bool
    {
        return $this->value === $other->value;
    }

    public function __toString(): string
    {
        return (string) $this->value;
    }
}
