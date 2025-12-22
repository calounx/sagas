<?php
declare(strict_types=1);

namespace SagaManager\Application\UseCase\UpdateQualityMetrics;

use SagaManager\Application\UseCase\CommandInterface;

/**
 * Update Quality Metrics Command
 *
 * Updates quality metrics for a specific entity.
 */
final readonly class UpdateQualityMetricsCommand implements CommandInterface
{
    /**
     * @param int $entityId The entity ID to update metrics for
     * @param int $completenessScore Completeness score (0-100)
     * @param int $consistencyScore Consistency score (0-100)
     * @param string[] $issueCodes Array of issue code strings
     */
    public function __construct(
        public int $entityId,
        public int $completenessScore,
        public int $consistencyScore,
        public array $issueCodes = []
    ) {}
}
