<?php

declare(strict_types=1);

namespace SagaManager\Infrastructure\Database\Adapter\WordPress;

use SagaManager\Infrastructure\Database\AbstractResultSet;

/**
 * WordPress Result Set
 *
 * Result set implementation for WordPress wpdb queries.
 */
final class WordPressResultSet extends AbstractResultSet
{
    /**
     * Create from wpdb results
     *
     * @param array<int, object|array<string, mixed>>|null $results wpdb query results
     * @return self
     */
    public static function fromWpdbResults(?array $results): self
    {
        if ($results === null) {
            return new self([]);
        }

        // Convert objects to arrays if necessary
        $rows = array_map(function ($row) {
            if (is_object($row)) {
                return (array) $row;
            }
            return $row;
        }, $results);

        return new self($rows);
    }

    /**
     * Create from single wpdb row
     *
     * @param object|array<string, mixed>|null $row
     * @return self
     */
    public static function fromWpdbRow(object|array|null $row): self
    {
        if ($row === null) {
            return new self([]);
        }

        $rowArray = is_object($row) ? (array) $row : $row;
        return new self([$rowArray]);
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
