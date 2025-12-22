<?php
declare(strict_types=1);

namespace SagaManager\Domain\Entity;

use SagaManager\Domain\Exception\ValidationException;

/**
 * Token Count Value Object
 *
 * Represents the number of tokens in a content fragment.
 * Valid range: 0-65535 (SMALLINT UNSIGNED)
 */
final readonly class TokenCount
{
    private const MIN = 0;
    private const MAX = 65535;

    private int $value;

    public function __construct(int $value)
    {
        if ($value < self::MIN || $value > self::MAX) {
            throw new ValidationException(
                sprintf('Token count must be between %d and %d, got %d', self::MIN, self::MAX, $value)
            );
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

    public function isEmpty(): bool
    {
        return $this->value === 0;
    }

    public function isLarge(): bool
    {
        return $this->value > 1000;
    }

    public static function zero(): self
    {
        return new self(0);
    }

    public function __toString(): string
    {
        return (string) $this->value;
    }
}
