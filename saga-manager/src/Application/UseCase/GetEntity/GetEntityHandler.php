<?php
declare(strict_types=1);

namespace SagaManager\Application\UseCase\GetEntity;

use SagaManager\Application\DTO\EntityDTO;
use SagaManager\Application\Query\QueryHandlerInterface;
use SagaManager\Application\Query\QueryInterface;
use SagaManager\Domain\Entity\EntityId;
use SagaManager\Domain\Exception\EntityNotFoundException;
use SagaManager\Domain\Exception\ValidationException;
use SagaManager\Domain\Repository\EntityRepositoryInterface;

/**
 * Get Entity Handler
 *
 * Application service that retrieves a single entity.
 * Fetches from repository and converts to DTO.
 *
 * @implements QueryHandlerInterface<GetEntityQuery, EntityDTO>
 */
final readonly class GetEntityHandler implements QueryHandlerInterface
{
    public function __construct(
        private EntityRepositoryInterface $entityRepository
    ) {
    }

    /**
     * Handle the get entity query
     *
     * @param GetEntityQuery $query
     * @return EntityDTO
     * @throws EntityNotFoundException When entity not found
     * @throws ValidationException When entity ID is invalid
     */
    public function handle(QueryInterface $query): EntityDTO
    {
        if (!$query instanceof GetEntityQuery) {
            throw new \InvalidArgumentException(
                sprintf('Expected %s, got %s', GetEntityQuery::class, get_class($query))
            );
        }

        if ($query->entityId <= 0) {
            throw new ValidationException('Entity ID must be positive');
        }

        $entityId = new EntityId($query->entityId);

        // Fetch entity from repository (throws EntityNotFoundException if not found)
        $entity = $this->entityRepository->findById($entityId);

        // Convert to DTO for presentation layer
        return EntityDTO::fromEntity($entity);
    }
}
