<?php
declare(strict_types=1);

namespace SagaManager\Application\Query\GetEntitiesWithIssues;

use SagaManager\Application\Query\QueryHandlerInterface;
use SagaManager\Application\Query\QueryInterface;
use SagaManager\Domain\Entity\QualityMetrics;
use SagaManager\Domain\Entity\SagaId;
use SagaManager\Domain\Repository\QualityMetricsRepositoryInterface;

/**
 * Get Entities With Issues Handler
 *
 * @implements QueryHandlerInterface<GetEntitiesWithIssuesQuery, QualityMetrics[]>
 */
final readonly class GetEntitiesWithIssuesHandler implements QueryHandlerInterface
{
    public function __construct(
        private QualityMetricsRepositoryInterface $repository
    ) {}

    /**
     * @return QualityMetrics[]
     */
    public function handle(QueryInterface $query): array
    {
        if (!$query instanceof GetEntitiesWithIssuesQuery) {
            throw new \InvalidArgumentException(
                sprintf('Expected %s, got %s', GetEntitiesWithIssuesQuery::class, get_class($query))
            );
        }

        return $this->repository->findWithIssues(
            new SagaId($query->sagaId),
            $query->limit,
            $query->offset
        );
    }
}
