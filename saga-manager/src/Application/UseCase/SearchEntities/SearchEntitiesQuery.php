<?php
declare(strict_types=1);

namespace SagaManager\Application\UseCase\SearchEntities;

use SagaManager\Application\Query\QueryInterface;

/**
 * Search Entities Query
 *
 * Query to search entities with filters and pagination.
 * Immutable query object following CQRS pattern.
 */
final readonly class SearchEntitiesQuery implements QueryInterface
{
    public function __construct(
        public int $sagaId,
        public ?string $type = null,
        public int $limit = 20,
        public int $offset = 0
    ) {
    }
}
