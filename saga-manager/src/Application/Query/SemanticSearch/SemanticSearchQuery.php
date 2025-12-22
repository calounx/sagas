<?php
declare(strict_types=1);

namespace SagaManager\Application\Query\SemanticSearch;

use SagaManager\Application\Query\QueryInterface;

/**
 * Semantic Search Query
 */
final readonly class SemanticSearchQuery implements QueryInterface
{
    public function __construct(
        public string $queryText,
        public int $limit = 10,
        public float $minSimilarity = 0.5,
        public ?int $entityId = null
    ) {}
}
