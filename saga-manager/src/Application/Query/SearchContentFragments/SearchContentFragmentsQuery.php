<?php
declare(strict_types=1);

namespace SagaManager\Application\Query\SearchContentFragments;

use SagaManager\Application\Query\QueryInterface;

/**
 * Search Content Fragments Query
 */
final readonly class SearchContentFragmentsQuery implements QueryInterface
{
    public function __construct(
        public string $searchTerm,
        public ?int $entityId = null,
        public int $limit = 50
    ) {}
}
