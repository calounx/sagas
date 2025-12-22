<?php
declare(strict_types=1);

namespace SagaManager\Application\Query\GetRelationships;

use SagaManager\Application\Query\QueryInterface;

/**
 * Get Relationships Query
 */
final readonly class GetRelationshipsQuery implements QueryInterface
{
    public function __construct(
        public ?int $entityId = null,
        public ?string $type = null,
        public bool $currentOnly = false
    ) {}
}
