<?php
declare(strict_types=1);

namespace SagaManager\Application\Query\GetQualityMetrics;

use SagaManager\Application\Query\QueryInterface;

/**
 * Get Quality Metrics Query
 */
final readonly class GetQualityMetricsQuery implements QueryInterface
{
    public function __construct(
        public int $entityId
    ) {}
}
