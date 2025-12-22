<?php
declare(strict_types=1);

namespace SagaManager\Application\Query\GetContentFragments;

use SagaManager\Application\Query\QueryInterface;

/**
 * Get Content Fragments Query
 */
final readonly class GetContentFragmentsQuery implements QueryInterface
{
    public function __construct(
        public int $entityId,
        public int $limit = 50,
        public int $offset = 0
    ) {}
}
