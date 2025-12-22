<?php
declare(strict_types=1);

namespace SagaManager\Application\Query\GetAttributeDefinitions;

use SagaManager\Application\Query\QueryHandlerInterface;
use SagaManager\Application\Query\QueryInterface;
use SagaManager\Domain\Entity\AttributeDefinition;
use SagaManager\Domain\Entity\EntityType;
use SagaManager\Domain\Repository\AttributeDefinitionRepositoryInterface;

/**
 * Get Attribute Definitions Handler
 *
 * Retrieves attribute definitions, optionally filtered by entity type.
 *
 * @implements QueryHandlerInterface<GetAttributeDefinitionsQuery, AttributeDefinition[]>
 */
final readonly class GetAttributeDefinitionsHandler implements QueryHandlerInterface
{
    public function __construct(
        private AttributeDefinitionRepositoryInterface $definitionRepository
    ) {}

    /**
     * Handle the get attribute definitions query
     *
     * @param GetAttributeDefinitionsQuery $query
     * @return AttributeDefinition[]
     */
    public function handle(QueryInterface $query): array
    {
        if (!$query instanceof GetAttributeDefinitionsQuery) {
            throw new \InvalidArgumentException(
                sprintf('Expected %s, got %s', GetAttributeDefinitionsQuery::class, get_class($query))
            );
        }

        if ($query->entityType !== null) {
            $entityType = EntityType::from($query->entityType);
            return $this->definitionRepository->findByEntityType($entityType);
        }

        // Get definitions for all entity types
        $allDefinitions = [];
        foreach (EntityType::cases() as $type) {
            $definitions = $this->definitionRepository->findByEntityType($type);
            $allDefinitions = array_merge($allDefinitions, $definitions);
        }

        return $allDefinitions;
    }
}
