<?php
declare(strict_types=1);

namespace SagaManager\Domain\Entity;

/**
 * Normalized Timestamp Value Object
 *
 * Unix-like BIGINT timestamp for chronological sorting across different saga calendars.
 * Allows consistent ordering regardless of the saga's date format.
 */
final readonly class NormalizedTimestamp
{
    private int $value;

    public function __construct(int $value)
    {
        $this->value = $value;
    }

    public function value(): int
    {
        return $this->value;
    }

    /**
     * Check if this timestamp is before another
     */
    public function isBefore(self $other): bool
    {
        return $this->value < $other->value;
    }

    /**
     * Check if this timestamp is after another
     */
    public function isAfter(self $other): bool
    {
        return $this->value > $other->value;
    }

    /**
     * Check if timestamp falls within a range
     */
    public function isBetween(self $start, self $end): bool
    {
        return $this->value >= $start->value && $this->value <= $end->value;
    }

    /**
     * Get the difference from another timestamp
     */
    public function difference(self $other): int
    {
        return abs($this->value - $other->value);
    }

    /**
     * Create a timestamp representing "epoch" (year 0)
     */
    public static function epoch(): self
    {
        return new self(0);
    }

    /**
     * Create from a relative year (positive = after epoch, negative = before)
     */
    public static function fromYear(int $year): self
    {
        // Simple conversion: 1 year = 31536000 seconds (365 days)
        return new self($year * 31536000);
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
