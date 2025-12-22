<?php
declare(strict_types=1);

namespace SagaManager\Tests\Unit\Domain\Entity;

use PHPUnit\Framework\TestCase;
use SagaManager\Domain\Entity\EntityId;
use SagaManager\Domain\Entity\IssueCode;
use SagaManager\Domain\Entity\QualityMetrics;
use SagaManager\Domain\Entity\QualityScore;

class QualityMetricsTest extends TestCase
{
    private EntityId $entityId;

    protected function setUp(): void
    {
        $this->entityId = new EntityId(1);
    }

    public function test_can_create_basic_metrics(): void
    {
        $metrics = new QualityMetrics(
            entityId: $this->entityId,
            completenessScore: new QualityScore(80),
            consistencyScore: new QualityScore(90)
        );

        $this->assertTrue($this->entityId->equals($metrics->getEntityId()));
        $this->assertSame(80, $metrics->getCompletenessScore()->value());
        $this->assertSame(90, $metrics->getConsistencyScore()->value());
        $this->assertSame([], $metrics->getIssues());
        $this->assertInstanceOf(\DateTimeImmutable::class, $metrics->getLastVerified());
    }

    public function test_can_create_metrics_with_issues(): void
    {
        $issues = [IssueCode::MISSING_DESCRIPTION, IssueCode::NO_EMBEDDING];

        $metrics = new QualityMetrics(
            entityId: $this->entityId,
            completenessScore: new QualityScore(60),
            consistencyScore: new QualityScore(70),
            issues: $issues
        );

        $this->assertSame($issues, $metrics->getIssues());
        $this->assertTrue($metrics->hasIssue(IssueCode::MISSING_DESCRIPTION));
        $this->assertFalse($metrics->hasIssue(IssueCode::CIRCULAR_RELATIONSHIP));
    }

    public function test_get_overall_score_calculates_average(): void
    {
        $metrics = new QualityMetrics(
            entityId: $this->entityId,
            completenessScore: new QualityScore(80),
            consistencyScore: new QualityScore(90)
        );

        $this->assertSame(85, $metrics->getOverallScore()->value());
    }

    public function test_has_critical_issues_returns_true(): void
    {
        $metrics = new QualityMetrics(
            entityId: $this->entityId,
            completenessScore: new QualityScore(80),
            consistencyScore: new QualityScore(90),
            issues: [IssueCode::CIRCULAR_RELATIONSHIP] // Severity 5
        );

        $this->assertTrue($metrics->hasCriticalIssues());
    }

    public function test_has_critical_issues_returns_false(): void
    {
        $metrics = new QualityMetrics(
            entityId: $this->entityId,
            completenessScore: new QualityScore(80),
            consistencyScore: new QualityScore(90),
            issues: [IssueCode::MISSING_DESCRIPTION] // Severity 1
        );

        $this->assertFalse($metrics->hasCriticalIssues());
    }

    public function test_passes_threshold_with_good_score(): void
    {
        $metrics = new QualityMetrics(
            entityId: $this->entityId,
            completenessScore: new QualityScore(80),
            consistencyScore: new QualityScore(90)
        );

        $this->assertTrue($metrics->passesThreshold(70));
    }

    public function test_passes_threshold_fails_with_low_score(): void
    {
        $metrics = new QualityMetrics(
            entityId: $this->entityId,
            completenessScore: new QualityScore(50),
            consistencyScore: new QualityScore(60)
        );

        $this->assertFalse($metrics->passesThreshold(70));
    }

    public function test_passes_threshold_fails_with_critical_issues(): void
    {
        $metrics = new QualityMetrics(
            entityId: $this->entityId,
            completenessScore: new QualityScore(90),
            consistencyScore: new QualityScore(90),
            issues: [IssueCode::CIRCULAR_RELATIONSHIP]
        );

        $this->assertFalse($metrics->passesThreshold(70));
    }

    public function test_get_issues_by_category(): void
    {
        $metrics = new QualityMetrics(
            entityId: $this->entityId,
            completenessScore: new QualityScore(70),
            consistencyScore: new QualityScore(70),
            issues: [
                IssueCode::MISSING_DESCRIPTION, // completeness
                IssueCode::MISSING_ATTRIBUTES,  // completeness
                IssueCode::ORPHAN_RELATIONSHIP, // consistency
            ]
        );

        $completenessIssues = $metrics->getIssuesByCategory('completeness');
        $consistencyIssues = $metrics->getIssuesByCategory('consistency');

        $this->assertCount(2, $completenessIssues);
        $this->assertCount(1, $consistencyIssues);
    }

    public function test_get_issue_severity_counts(): void
    {
        $metrics = new QualityMetrics(
            entityId: $this->entityId,
            completenessScore: new QualityScore(70),
            consistencyScore: new QualityScore(70),
            issues: [
                IssueCode::MISSING_DESCRIPTION, // Severity 1
                IssueCode::SHORT_DESCRIPTION,   // Severity 1
                IssueCode::NO_EMBEDDING,        // Severity 2
                IssueCode::ORPHAN_RELATIONSHIP, // Severity 4
            ]
        );

        $counts = $metrics->getIssueSeverityCounts();

        $this->assertSame(2, $counts[1]);
        $this->assertSame(1, $counts[2]);
        $this->assertSame(0, $counts[3]);
        $this->assertSame(1, $counts[4]);
        $this->assertSame(0, $counts[5]);
    }

    public function test_needs_attention_with_issues(): void
    {
        $metrics = new QualityMetrics(
            entityId: $this->entityId,
            completenessScore: new QualityScore(90),
            consistencyScore: new QualityScore(90),
            issues: [IssueCode::MISSING_DESCRIPTION]
        );

        $this->assertTrue($metrics->needsAttention());
    }

    public function test_needs_attention_with_poor_score(): void
    {
        $metrics = new QualityMetrics(
            entityId: $this->entityId,
            completenessScore: new QualityScore(30),
            consistencyScore: new QualityScore(40)
        );

        $this->assertTrue($metrics->needsAttention());
    }

    public function test_needs_attention_false_when_good(): void
    {
        $metrics = new QualityMetrics(
            entityId: $this->entityId,
            completenessScore: new QualityScore(80),
            consistencyScore: new QualityScore(90)
        );

        $this->assertFalse($metrics->needsAttention());
    }

    public function test_update_scores(): void
    {
        $metrics = new QualityMetrics(
            entityId: $this->entityId,
            completenessScore: new QualityScore(70),
            consistencyScore: new QualityScore(70)
        );

        $metrics->updateCompletenessScore(new QualityScore(80));
        $metrics->updateConsistencyScore(new QualityScore(90));

        $this->assertSame(80, $metrics->getCompletenessScore()->value());
        $this->assertSame(90, $metrics->getConsistencyScore()->value());
    }

    public function test_add_issue(): void
    {
        $metrics = new QualityMetrics(
            entityId: $this->entityId,
            completenessScore: new QualityScore(70),
            consistencyScore: new QualityScore(70)
        );

        $metrics->addIssue(IssueCode::MISSING_DESCRIPTION);

        $this->assertTrue($metrics->hasIssue(IssueCode::MISSING_DESCRIPTION));
        $this->assertCount(1, $metrics->getIssues());

        // Adding same issue again should not duplicate
        $metrics->addIssue(IssueCode::MISSING_DESCRIPTION);
        $this->assertCount(1, $metrics->getIssues());
    }

    public function test_remove_issue(): void
    {
        $metrics = new QualityMetrics(
            entityId: $this->entityId,
            completenessScore: new QualityScore(70),
            consistencyScore: new QualityScore(70),
            issues: [IssueCode::MISSING_DESCRIPTION, IssueCode::NO_EMBEDDING]
        );

        $metrics->removeIssue(IssueCode::MISSING_DESCRIPTION);

        $this->assertFalse($metrics->hasIssue(IssueCode::MISSING_DESCRIPTION));
        $this->assertTrue($metrics->hasIssue(IssueCode::NO_EMBEDDING));
        $this->assertCount(1, $metrics->getIssues());
    }

    public function test_to_array(): void
    {
        $metrics = new QualityMetrics(
            entityId: $this->entityId,
            completenessScore: new QualityScore(80),
            consistencyScore: new QualityScore(90),
            issues: [IssueCode::MISSING_DESCRIPTION]
        );

        $array = $metrics->toArray();

        $this->assertSame(1, $array['entity_id']);
        $this->assertSame(80, $array['completeness_score']);
        $this->assertSame(90, $array['consistency_score']);
        $this->assertSame(85, $array['overall_score']);
        $this->assertSame('B', $array['overall_grade']);
        $this->assertArrayHasKey('last_verified', $array);
        $this->assertCount(1, $array['issues']);
        $this->assertSame('missing_description', $array['issues'][0]['code']);
        $this->assertFalse($array['has_critical_issues']);
        $this->assertTrue($array['needs_attention']);
    }

    public function test_is_stale(): void
    {
        $metrics = new QualityMetrics(
            entityId: $this->entityId,
            completenessScore: new QualityScore(80),
            consistencyScore: new QualityScore(90),
            lastVerified: new \DateTimeImmutable('-8 days')
        );

        // Default max age is 7 days (604800 seconds)
        $this->assertTrue($metrics->isStale());
        $this->assertFalse($metrics->isStale(1000000)); // 11.5 days
    }
}
