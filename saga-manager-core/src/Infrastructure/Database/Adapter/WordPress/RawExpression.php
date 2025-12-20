<?php

declare(strict_types=1);

namespace SagaManagerCore\Infrastructure\Database\Adapter\WordPress;

/**
 * Raw SQL Expression
 *
 * Used to bypass value escaping in the query builder for raw SQL expressions.
 * Use with caution - values are NOT escaped.
 */
final class RawExpression
{
    private string $value;

    public function __construct(string $value)
    {
        $this->value = $value;
    }

    public function getValue(): string
    {
        return $this->value;
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
