<?php
declare(strict_types=1);

namespace SagaManager\Application\UseCase\GetEntity;

use SagaManager\Application\Query\QueryInterface;

/**
 * Get Entity Query
 *
 * Query to retrieve a single entity by ID.
 * Immutable query object following CQRS pattern.
 */
final readonly class GetEntityQuery implements QueryInterface
{
    public function __construct(
        public int $entityId
    ) {
    }
}
