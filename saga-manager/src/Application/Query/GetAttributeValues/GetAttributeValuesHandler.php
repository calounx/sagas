<?php
declare(strict_types=1);

namespace SagaManager\Application\Query\GetAttributeValues;

use SagaManager\Application\Query\QueryHandlerInterface;
use SagaManager\Application\Query\QueryInterface;
use SagaManager\Domain\Entity\AttributeValue;
use SagaManager\Domain\Entity\EntityId;
use SagaManager\Domain\Repository\AttributeValueRepositoryInterface;
use SagaManager\Domain\Repository\EntityRepositoryInterface;

/**
 * Get Attribute Values Handler
 *
 * Retrieves all attribute values for an entity.
 *
 * @implements QueryHandlerInterface<GetAttributeValuesQuery, array<string, AttributeValue>>
 */
final readonly class GetAttributeValuesHandler implements QueryHandlerInterface
{
    public function __construct(
        private EntityRepositoryInterface $entityRepository,
        private AttributeValueRepositoryInterface $valueRepository
    ) {}

    /**
     * Handle the get attribute values query
     *
     * @param GetAttributeValuesQuery $query
     * @return array<string, AttributeValue> Keyed by attribute_key
     * @throws \SagaManager\Domain\Exception\EntityNotFoundException When entity not found
     */
    public function handle(QueryInterface $query): array
    {
        if (!$query instanceof GetAttributeValuesQuery) {
            throw new \InvalidArgumentException(
                sprintf('Expected %s, got %s', GetAttributeValuesQuery::class, get_class($query))
            );
        }

        $entityId = new EntityId($query->entityId);

        // Verify entity exists
        $this->entityRepository->findById($entityId);

        return $this->valueRepository->findByEntity($entityId);
    }
}
