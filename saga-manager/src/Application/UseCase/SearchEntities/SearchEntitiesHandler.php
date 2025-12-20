<?php
declare(strict_types=1);

namespace SagaManager\Application\UseCase\SearchEntities;

use SagaManager\Application\DTO\EntityDTO;
use SagaManager\Application\DTO\SearchEntitiesResult;
use SagaManager\Application\Query\QueryHandlerInterface;
use SagaManager\Application\Query\QueryInterface;
use SagaManager\Domain\Entity\EntityType;
use SagaManager\Domain\Entity\SagaId;
use SagaManager\Domain\Exception\ValidationException;
use SagaManager\Domain\Repository\EntityRepositoryInterface;

/**
 * Search Entities Handler
 *
 * Application service that searches entities with filters and pagination.
 * Supports filtering by saga and entity type.
 *
 * @implements QueryHandlerInterface<SearchEntitiesQuery, SearchEntitiesResult>
 */
final readonly class SearchEntitiesHandler implements QueryHandlerInterface
{
    private const MAX_LIMIT = 100;
    private const DEFAULT_LIMIT = 20;

    public function __construct(
        private EntityRepositoryInterface $entityRepository
    ) {
    }

    /**
     * Handle the search entities query
     *
     * @param SearchEntitiesQuery $query
     * @return SearchEntitiesResult
     * @throws ValidationException When parameters are invalid
     */
    public function handle(QueryInterface $query): SearchEntitiesResult
    {
        if (!$query instanceof SearchEntitiesQuery) {
            throw new \InvalidArgumentException(
                sprintf('Expected %s, got %s', SearchEntitiesQuery::class, get_class($query))
            );
        }

        // Validate input
        $this->validateQuery($query);

        $sagaId = new SagaId($query->sagaId);
        $limit = min($query->limit, self::MAX_LIMIT);
        $offset = max($query->offset, 0);

        // Fetch entities based on filters
        if ($query->type !== null) {
            $entityType = EntityType::from($query->type);
            $entities = $this->entityRepository->findBySagaAndType(
                sagaId: $sagaId,
                type: $entityType,
                limit: $limit,
                offset: $offset
            );
        } else {
            $entities = $this->entityRepository->findBySaga(
                sagaId: $sagaId,
                limit: $limit,
                offset: $offset
            );
        }

        // Get total count for pagination
        $total = $this->entityRepository->countBySaga($sagaId);

        // Convert to DTOs
        $entityDTOs = array_map(
            fn($entity) => EntityDTO::fromEntity($entity),
            $entities
        );

        return new SearchEntitiesResult(
            entities: $entityDTOs,
            total: $total,
            limit: $limit,
            offset: $offset
        );
    }

    private function validateQuery(SearchEntitiesQuery $query): void
    {
        if ($query->sagaId <= 0) {
            throw new ValidationException('saga_id must be positive');
        }

        if ($query->limit < 1) {
            throw new ValidationException('limit must be at least 1');
        }

        if ($query->offset < 0) {
            throw new ValidationException('offset cannot be negative');
        }

        if ($query->type !== null) {
            try {
                EntityType::from($query->type);
            } catch (\ValueError $e) {
                throw new ValidationException(
                    sprintf('Invalid entity type: %s', $query->type),
                    0,
                    $e
                );
            }
        }
    }
}
