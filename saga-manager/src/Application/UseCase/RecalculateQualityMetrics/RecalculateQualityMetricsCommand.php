<?php
declare(strict_types=1);

namespace SagaManager\Application\UseCase\RecalculateQualityMetrics;

use SagaManager\Application\UseCase\CommandInterface;

/**
 * Recalculate Quality Metrics Command
 *
 * Triggers recalculation of quality metrics for entities in a saga.
 */
final readonly class RecalculateQualityMetricsCommand implements CommandInterface
{
    /**
     * @param int $sagaId The saga ID to recalculate metrics for
     * @param int|null $entityId Optional specific entity ID (null = all stale entities)
     * @param int $limit Maximum number of entities to process
     */
    public function __construct(
        public int $sagaId,
        public ?int $entityId = null,
        public int $limit = 100
    ) {}
}
