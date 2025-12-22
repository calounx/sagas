<?php
declare(strict_types=1);

namespace SagaManager\Domain\Entity;

use SagaManager\Domain\Exception\ValidationException;

/**
 * Canon Date Value Object
 *
 * Represents a date in the saga's canonical format (e.g., "10,191 AG", "19 BBY").
 * Immutable and validates format.
 */
final readonly class CanonDate
{
    private string $value;

    public function __construct(string $value)
    {
        $trimmed = trim($value);

        if ($trimmed === '') {
            throw new ValidationException('Canon date cannot be empty');
        }

        if (mb_strlen($trimmed) > 100) {
            throw new ValidationException('Canon date cannot exceed 100 characters');
        }

        $this->value = $trimmed;
    }

    public function value(): string
    {
        return $this->value;
    }

    /**
     * Check if this date contains a specific era marker
     */
    public function hasEra(string $era): bool
    {
        return mb_stripos($this->value, $era) !== false;
    }

    /**
     * Extract numeric component from the date
     */
    public function getNumericValue(): ?int
    {
        // Remove commas and extract numbers
        $cleaned = preg_replace('/[,\s]/', '', $this->value);
        if (preg_match('/(-?\d+)/', $cleaned, $matches)) {
            return (int) $matches[1];
        }

        return null;
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
