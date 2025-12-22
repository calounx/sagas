<?php
declare(strict_types=1);

namespace SagaManager\Application\Query\GetContentFragments;

use SagaManager\Application\Query\QueryHandlerInterface;
use SagaManager\Application\Query\QueryInterface;
use SagaManager\Domain\Entity\ContentFragment;
use SagaManager\Domain\Entity\EntityId;
use SagaManager\Domain\Repository\ContentFragmentRepositoryInterface;

/**
 * Get Content Fragments Handler
 *
 * @implements QueryHandlerInterface<GetContentFragmentsQuery, ContentFragment[]>
 */
final readonly class GetContentFragmentsHandler implements QueryHandlerInterface
{
    public function __construct(
        private ContentFragmentRepositoryInterface $fragmentRepository
    ) {}

    /**
     * @return ContentFragment[]
     */
    public function handle(QueryInterface $query): array
    {
        if (!$query instanceof GetContentFragmentsQuery) {
            throw new \InvalidArgumentException(
                sprintf('Expected %s, got %s', GetContentFragmentsQuery::class, get_class($query))
            );
        }

        $entityId = new EntityId($query->entityId);

        return $this->fragmentRepository->findByEntityPaginated(
            $entityId,
            $query->limit,
            $query->offset
        );
    }
}
