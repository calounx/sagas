<?php
declare(strict_types=1);

namespace SagaManager\Application\Query;

/**
 * Query Interface (CQRS Pattern)
 *
 * Marker interface for all queries that retrieve data.
 * Queries should not modify state.
 */
interface QueryInterface
{
}
