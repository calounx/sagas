<?php

declare(strict_types=1);

namespace SagaManager\Infrastructure\Database\Adapter\InMemory;

use SagaManager\Infrastructure\Database\AbstractResultSet;

/**
 * In-Memory Result Set
 *
 * Result set implementation for in-memory database queries.
 */
final class InMemoryResultSet extends AbstractResultSet
{
    /**
     * Create empty result set
     *
     * @return self
     */
    public static function empty(): self
    {
        return new self([]);
    }

    /**
     * Create from array of rows
     *
     * @param array<int, array<string, mixed>> $rows
     * @return self
     */
    public static function fromArray(array $rows): self
    {
        return new self($rows);
    }
}
