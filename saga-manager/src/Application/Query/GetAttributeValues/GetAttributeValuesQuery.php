<?php
declare(strict_types=1);

namespace SagaManager\Application\Query\GetAttributeValues;

use SagaManager\Application\Query\QueryInterface;

/**
 * Get Attribute Values Query
 *
 * Query to retrieve attribute values for an entity.
 */
final readonly class GetAttributeValuesQuery implements QueryInterface
{
    public function __construct(
        public int $entityId
    ) {}
}
