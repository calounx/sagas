<?php
declare(strict_types=1);

namespace SagaManager\Application\Query\GetEntitiesWithIssues;

use SagaManager\Application\Query\QueryInterface;

/**
 * Get Entities With Issues Query
 */
final readonly class GetEntitiesWithIssuesQuery implements QueryInterface
{
    public function __construct(
        public int $sagaId,
        public int $limit = 100,
        public int $offset = 0
    ) {}
}
