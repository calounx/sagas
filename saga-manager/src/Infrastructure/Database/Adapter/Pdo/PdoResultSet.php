<?php

declare(strict_types=1);

namespace SagaManager\Infrastructure\Database\Adapter\Pdo;

use SagaManager\Infrastructure\Database\AbstractResultSet;

/**
 * PDO Result Set
 *
 * Result set implementation for PDO queries.
 */
final class PdoResultSet extends AbstractResultSet
{
    /**
     * Create from PDO statement
     *
     * @param \PDOStatement $statement
     * @return self
     */
    public static function fromStatement(\PDOStatement $statement): self
    {
        $rows = $statement->fetchAll(\PDO::FETCH_ASSOC);
        return new self($rows ?: []);
    }

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
