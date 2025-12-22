<?php
declare(strict_types=1);

namespace SagaManager\Application\Query\GetQualityMetrics;

use SagaManager\Application\Query\QueryHandlerInterface;
use SagaManager\Application\Query\QueryInterface;
use SagaManager\Domain\Entity\EntityId;
use SagaManager\Domain\Entity\QualityMetrics;
use SagaManager\Domain\Repository\QualityMetricsRepositoryInterface;

/**
 * Get Quality Metrics Handler
 *
 * @implements QueryHandlerInterface<GetQualityMetricsQuery, ?QualityMetrics>
 */
final readonly class GetQualityMetricsHandler implements QueryHandlerInterface
{
    public function __construct(
        private QualityMetricsRepositoryInterface $repository
    ) {}

    public function handle(QueryInterface $query): ?QualityMetrics
    {
        if (!$query instanceof GetQualityMetricsQuery) {
            throw new \InvalidArgumentException(
                sprintf('Expected %s, got %s', GetQualityMetricsQuery::class, get_class($query))
            );
        }

        return $this->repository->findByEntityId(new EntityId($query->entityId));
    }
}
