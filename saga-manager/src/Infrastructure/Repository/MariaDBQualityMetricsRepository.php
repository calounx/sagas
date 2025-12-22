<?php
declare(strict_types=1);

namespace SagaManager\Infrastructure\Repository;

use SagaManager\Domain\Entity\EntityId;
use SagaManager\Domain\Entity\IssueCode;
use SagaManager\Domain\Entity\QualityMetrics;
use SagaManager\Domain\Entity\QualityScore;
use SagaManager\Domain\Entity\SagaId;
use SagaManager\Domain\Repository\QualityMetricsRepositoryInterface;
use SagaManager\Infrastructure\WordPress\WordPressTablePrefixAware;

/**
 * MariaDB Quality Metrics Repository
 *
 * Handles CRUD operations for quality metrics.
 */
class MariaDBQualityMetricsRepository extends WordPressTablePrefixAware implements QualityMetricsRepositoryInterface
{
    private const CACHE_GROUP = 'saga_quality_metrics';
    private const CACHE_TTL = 300; // 5 minutes

    /** @var array<int, QualityMetrics> */
    private array $identityMap = [];

    public function findByEntityId(EntityId $entityId): ?QualityMetrics
    {
        $id = $entityId->value();

        // Check identity map
        if (isset($this->identityMap[$id])) {
            return $this->identityMap[$id];
        }

        // Check cache
        $cacheKey = "metrics_{$id}";
        $cached = wp_cache_get($cacheKey, self::CACHE_GROUP);

        if ($cached !== false) {
            $metrics = $this->hydrate($cached);
            $this->identityMap[$id] = $metrics;
            return $metrics;
        }

        global $wpdb;
        $table = $this->getTableName('quality_metrics');

        $row = $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM {$table} WHERE entity_id = %d", $id),
            ARRAY_A
        );

        if (!$row) {
            return null;
        }

        wp_cache_set($cacheKey, $row, self::CACHE_GROUP, self::CACHE_TTL);

        $metrics = $this->hydrate($row);
        $this->identityMap[$id] = $metrics;

        return $metrics;
    }

    public function save(QualityMetrics $metrics): void
    {
        global $wpdb;
        $table = $this->getTableName('quality_metrics');

        $data = [
            'entity_id' => $metrics->getEntityId()->value(),
            'completeness_score' => $metrics->getCompletenessScore()->value(),
            'consistency_score' => $metrics->getConsistencyScore()->value(),
            'last_verified' => $metrics->getLastVerified()->format('Y-m-d H:i:s'),
            'issues' => json_encode(array_map(fn(IssueCode $i) => $i->value, $metrics->getIssues())),
        ];

        // Check if exists
        $existing = $wpdb->get_var($wpdb->prepare(
            "SELECT entity_id FROM {$table} WHERE entity_id = %d",
            $metrics->getEntityId()->value()
        ));

        if ($existing) {
            $wpdb->update(
                $table,
                $data,
                ['entity_id' => $metrics->getEntityId()->value()],
                ['%d', '%d', '%d', '%s', '%s'],
                ['%d']
            );
        } else {
            $wpdb->insert($table, $data, ['%d', '%d', '%d', '%s', '%s']);
        }

        // Invalidate cache
        $this->invalidateCache($metrics->getEntityId());
        $this->identityMap[$metrics->getEntityId()->value()] = $metrics;
    }

    public function delete(EntityId $entityId): void
    {
        global $wpdb;
        $table = $this->getTableName('quality_metrics');

        $wpdb->delete($table, ['entity_id' => $entityId->value()], ['%d']);

        $this->invalidateCache($entityId);
        unset($this->identityMap[$entityId->value()]);
    }

    /**
     * @return QualityMetrics[]
     */
    public function findWithIssues(SagaId $sagaId, int $limit = 100, int $offset = 0): array
    {
        global $wpdb;
        $metricsTable = $this->getTableName('quality_metrics');
        $entitiesTable = $this->getTableName('entities');

        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT qm.* FROM {$metricsTable} qm
                 INNER JOIN {$entitiesTable} e ON qm.entity_id = e.id
                 WHERE e.saga_id = %d
                 AND qm.issues != '[]'
                 AND qm.issues IS NOT NULL
                 ORDER BY JSON_LENGTH(qm.issues) DESC
                 LIMIT %d OFFSET %d",
                $sagaId->value(),
                $limit,
                $offset
            ),
            ARRAY_A
        );

        return array_map(fn(array $row) => $this->hydrate($row), $rows);
    }

    /**
     * @return QualityMetrics[]
     */
    public function findBelowThreshold(SagaId $sagaId, int $threshold = 70, int $limit = 100): array
    {
        global $wpdb;
        $metricsTable = $this->getTableName('quality_metrics');
        $entitiesTable = $this->getTableName('entities');

        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT qm.* FROM {$metricsTable} qm
                 INNER JOIN {$entitiesTable} e ON qm.entity_id = e.id
                 WHERE e.saga_id = %d
                 AND ((qm.completeness_score + qm.consistency_score) / 2) < %d
                 ORDER BY (qm.completeness_score + qm.consistency_score) ASC
                 LIMIT %d",
                $sagaId->value(),
                $threshold,
                $limit
            ),
            ARRAY_A
        );

        return array_map(fn(array $row) => $this->hydrate($row), $rows);
    }

    /**
     * @return EntityId[]
     */
    public function findNeedingVerification(SagaId $sagaId, int $maxAgeSeconds = 604800, int $limit = 100): array
    {
        global $wpdb;
        $metricsTable = $this->getTableName('quality_metrics');
        $entitiesTable = $this->getTableName('entities');

        // Find entities without metrics or with stale metrics
        $entityIds = $wpdb->get_col(
            $wpdb->prepare(
                "SELECT e.id FROM {$entitiesTable} e
                 LEFT JOIN {$metricsTable} qm ON e.id = qm.entity_id
                 WHERE e.saga_id = %d
                 AND (
                     qm.entity_id IS NULL
                     OR qm.last_verified < DATE_SUB(NOW(), INTERVAL %d SECOND)
                 )
                 ORDER BY qm.last_verified ASC, e.id ASC
                 LIMIT %d",
                $sagaId->value(),
                $maxAgeSeconds,
                $limit
            )
        );

        return array_map(fn(string $id) => new EntityId((int) $id), $entityIds);
    }

    /**
     * @return array{completeness: float, consistency: float, overall: float}
     */
    public function getAverageScores(SagaId $sagaId): array
    {
        global $wpdb;
        $metricsTable = $this->getTableName('quality_metrics');
        $entitiesTable = $this->getTableName('entities');

        $result = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT
                    AVG(qm.completeness_score) as avg_completeness,
                    AVG(qm.consistency_score) as avg_consistency,
                    AVG((qm.completeness_score + qm.consistency_score) / 2) as avg_overall
                 FROM {$metricsTable} qm
                 INNER JOIN {$entitiesTable} e ON qm.entity_id = e.id
                 WHERE e.saga_id = %d",
                $sagaId->value()
            ),
            ARRAY_A
        );

        return [
            'completeness' => (float) ($result['avg_completeness'] ?? 0),
            'consistency' => (float) ($result['avg_consistency'] ?? 0),
            'overall' => (float) ($result['avg_overall'] ?? 0),
        ];
    }

    /**
     * @return array{A: int, B: int, C: int, D: int}
     */
    public function countByGrade(SagaId $sagaId): array
    {
        global $wpdb;
        $metricsTable = $this->getTableName('quality_metrics');
        $entitiesTable = $this->getTableName('entities');

        $result = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT
                    CASE
                        WHEN ((qm.completeness_score + qm.consistency_score) / 2) >= 90 THEN 'A'
                        WHEN ((qm.completeness_score + qm.consistency_score) / 2) >= 70 THEN 'B'
                        WHEN ((qm.completeness_score + qm.consistency_score) / 2) >= 50 THEN 'C'
                        ELSE 'D'
                    END as grade,
                    COUNT(*) as count
                 FROM {$metricsTable} qm
                 INNER JOIN {$entitiesTable} e ON qm.entity_id = e.id
                 WHERE e.saga_id = %d
                 GROUP BY grade",
                $sagaId->value()
            ),
            ARRAY_A
        );

        $counts = ['A' => 0, 'B' => 0, 'C' => 0, 'D' => 0];

        foreach ($result as $row) {
            $counts[$row['grade']] = (int) $row['count'];
        }

        return $counts;
    }

    private function hydrate(array $row): QualityMetrics
    {
        $issues = [];
        $issueData = json_decode($row['issues'] ?? '[]', true);

        foreach ($issueData as $issueCode) {
            $issue = IssueCode::tryFrom($issueCode);
            if ($issue !== null) {
                $issues[] = $issue;
            }
        }

        return new QualityMetrics(
            entityId: new EntityId((int) $row['entity_id']),
            completenessScore: new QualityScore((int) $row['completeness_score']),
            consistencyScore: new QualityScore((int) $row['consistency_score']),
            lastVerified: new \DateTimeImmutable($row['last_verified']),
            issues: $issues
        );
    }

    private function invalidateCache(EntityId $entityId): void
    {
        wp_cache_delete("metrics_{$entityId->value()}", self::CACHE_GROUP);
    }
}
