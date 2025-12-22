<?php
declare(strict_types=1);

namespace SagaManager\Application\Query\GetRelationships;

use SagaManager\Application\Query\QueryHandlerInterface;
use SagaManager\Application\Query\QueryInterface;
use SagaManager\Domain\Entity\EntityId;
use SagaManager\Domain\Entity\Relationship;
use SagaManager\Domain\Repository\RelationshipRepositoryInterface;

/**
 * Get Relationships Handler
 *
 * @implements QueryHandlerInterface<GetRelationshipsQuery, Relationship[]>
 */
final readonly class GetRelationshipsHandler implements QueryHandlerInterface
{
    public function __construct(
        private RelationshipRepositoryInterface $relationshipRepository
    ) {}

    /**
     * @return Relationship[]
     */
    public function handle(QueryInterface $query): array
    {
        if (!$query instanceof GetRelationshipsQuery) {
            throw new \InvalidArgumentException(
                sprintf('Expected %s, got %s', GetRelationshipsQuery::class, get_class($query))
            );
        }

        if ($query->entityId !== null) {
            $entityId = new EntityId($query->entityId);

            if ($query->currentOnly) {
                return $this->relationshipRepository->findCurrentByEntity($entityId);
            }

            return $this->relationshipRepository->findByEntity($entityId, $query->type);
        }

        if ($query->type !== null) {
            return $this->relationshipRepository->findByType($query->type);
        }

        return [];
    }
}
