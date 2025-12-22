<?php
declare(strict_types=1);

namespace SagaManager\Application\Query\GetAttributeDefinitions;

use SagaManager\Application\Query\QueryInterface;

/**
 * Get Attribute Definitions Query
 *
 * Query to retrieve attribute definitions, optionally filtered by entity type.
 */
final readonly class GetAttributeDefinitionsQuery implements QueryInterface
{
    /**
     * @param string|null $entityType Filter by entity type (null for all types)
     */
    public function __construct(
        public ?string $entityType = null
    ) {}
}
