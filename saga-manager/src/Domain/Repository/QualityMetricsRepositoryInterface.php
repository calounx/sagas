<?php
declare(strict_types=1);

namespace SagaManager\Domain\Repository;

use SagaManager\Domain\Entity\EntityId;
use SagaManager\Domain\Entity\QualityMetrics;
use SagaManager\Domain\Entity\SagaId;

/**
 * Quality Metrics Repository Interface (Port)
 */
interface QualityMetricsRepositoryInterface
{
    /**
     * Find quality metrics by entity ID
     */
    public function findByEntityId(EntityId $entityId): ?QualityMetrics;

    /**
     * Save quality metrics (insert or update)
     */
    public function save(QualityMetrics $metrics): void;

    /**
     * Delete quality metrics for an entity
     */
    public function delete(EntityId $entityId): void;

    /**
     * Find entities with issues
     *
     * @return QualityMetrics[]
     */
    public function findWithIssues(SagaId $sagaId, int $limit = 100, int $offset = 0): array;

    /**
     * Find entities below quality threshold
     *
     * @return QualityMetrics[]
     */
    public function findBelowThreshold(SagaId $sagaId, int $threshold = 70, int $limit = 100): array;

    /**
     * Find entities needing verification (stale metrics)
     *
     * @return EntityId[]
     */
    public function findNeedingVerification(SagaId $sagaId, int $maxAgeSeconds = 604800, int $limit = 100): array;

    /**
     * Get average scores for a saga
     *
     * @return array{completeness: float, consistency: float, overall: float}
     */
    public function getAverageScores(SagaId $sagaId): array;

    /**
     * Count entities by quality grade
     *
     * @return array{A: int, B: int, C: int, D: int}
     */
    public function countByGrade(SagaId $sagaId): array;
}
