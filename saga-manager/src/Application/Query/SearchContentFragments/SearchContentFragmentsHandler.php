<?php
declare(strict_types=1);

namespace SagaManager\Application\Query\SearchContentFragments;

use SagaManager\Application\Query\QueryHandlerInterface;
use SagaManager\Application\Query\QueryInterface;
use SagaManager\Domain\Entity\ContentFragment;
use SagaManager\Domain\Entity\EntityId;
use SagaManager\Domain\Repository\ContentFragmentRepositoryInterface;

/**
 * Search Content Fragments Handler
 *
 * @implements QueryHandlerInterface<SearchContentFragmentsQuery, ContentFragment[]>
 */
final readonly class SearchContentFragmentsHandler implements QueryHandlerInterface
{
    public function __construct(
        private ContentFragmentRepositoryInterface $fragmentRepository
    ) {}

    /**
     * @return ContentFragment[]
     */
    public function handle(QueryInterface $query): array
    {
        if (!$query instanceof SearchContentFragmentsQuery) {
            throw new \InvalidArgumentException(
                sprintf('Expected %s, got %s', SearchContentFragmentsQuery::class, get_class($query))
            );
        }

        if ($query->entityId !== null) {
            return $this->fragmentRepository->searchByEntity(
                new EntityId($query->entityId),
                $query->searchTerm
            );
        }

        return $this->fragmentRepository->search($query->searchTerm, $query->limit);
    }
}
