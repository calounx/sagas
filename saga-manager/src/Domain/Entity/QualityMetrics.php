<?php
declare(strict_types=1);

namespace SagaManager\Domain\Entity;

/**
 * Quality Metrics Entity
 *
 * Tracks completeness and consistency scores for an entity.
 */
class QualityMetrics
{
    private EntityId $entityId;
    private QualityScore $completenessScore;
    private QualityScore $consistencyScore;
    private \DateTimeImmutable $lastVerified;
    /** @var IssueCode[] */
    private array $issues;

    /**
     * @param IssueCode[] $issues
     */
    public function __construct(
        EntityId $entityId,
        QualityScore $completenessScore,
        QualityScore $consistencyScore,
        ?\DateTimeImmutable $lastVerified = null,
        array $issues = []
    ) {
        $this->entityId = $entityId;
        $this->completenessScore = $completenessScore;
        $this->consistencyScore = $consistencyScore;
        $this->lastVerified = $lastVerified ?? new \DateTimeImmutable();
        $this->issues = $issues;
    }

    public function getEntityId(): EntityId
    {
        return $this->entityId;
    }

    public function getCompletenessScore(): QualityScore
    {
        return $this->completenessScore;
    }

    public function getConsistencyScore(): QualityScore
    {
        return $this->consistencyScore;
    }

    public function getLastVerified(): \DateTimeImmutable
    {
        return $this->lastVerified;
    }

    /**
     * @return IssueCode[]
     */
    public function getIssues(): array
    {
        return $this->issues;
    }

    /**
     * Get overall quality score (average of completeness and consistency)
     */
    public function getOverallScore(): QualityScore
    {
        $average = (int) round(
            ($this->completenessScore->value() + $this->consistencyScore->value()) / 2
        );

        return new QualityScore($average);
    }

    /**
     * Check if entity has critical issues
     */
    public function hasCriticalIssues(): bool
    {
        foreach ($this->issues as $issue) {
            if ($issue->isCritical()) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if entity passes quality threshold
     */
    public function passesThreshold(int $minScore = 70): bool
    {
        return $this->getOverallScore()->value() >= $minScore && !$this->hasCriticalIssues();
    }

    /**
     * Get issues filtered by category
     *
     * @return IssueCode[]
     */
    public function getIssuesByCategory(string $category): array
    {
        return array_filter($this->issues, fn(IssueCode $issue) => $issue->category() === $category);
    }

    /**
     * Get count of issues by severity
     *
     * @return array<int, int> Severity level => count
     */
    public function getIssueSeverityCounts(): array
    {
        $counts = [1 => 0, 2 => 0, 3 => 0, 4 => 0, 5 => 0];

        foreach ($this->issues as $issue) {
            $counts[$issue->severity()]++;
        }

        return $counts;
    }

    /**
     * Check if entity needs attention (has issues or low score)
     */
    public function needsAttention(): bool
    {
        return !empty($this->issues) || $this->getOverallScore()->isPoor();
    }

    /**
     * Update completeness score
     */
    public function updateCompletenessScore(QualityScore $score): void
    {
        $this->completenessScore = $score;
        $this->touch();
    }

    /**
     * Update consistency score
     */
    public function updateConsistencyScore(QualityScore $score): void
    {
        $this->consistencyScore = $score;
        $this->touch();
    }

    /**
     * Set issues list
     *
     * @param IssueCode[] $issues
     */
    public function setIssues(array $issues): void
    {
        $this->issues = $issues;
        $this->touch();
    }

    /**
     * Add an issue
     */
    public function addIssue(IssueCode $issue): void
    {
        if (!in_array($issue, $this->issues, true)) {
            $this->issues[] = $issue;
            $this->touch();
        }
    }

    /**
     * Remove an issue
     */
    public function removeIssue(IssueCode $issue): void
    {
        $this->issues = array_values(array_filter(
            $this->issues,
            fn(IssueCode $i) => $i !== $issue
        ));
        $this->touch();
    }

    /**
     * Check if entity has specific issue
     */
    public function hasIssue(IssueCode $issue): bool
    {
        return in_array($issue, $this->issues, true);
    }

    /**
     * Update verification timestamp
     */
    public function touch(): void
    {
        $this->lastVerified = new \DateTimeImmutable();
    }

    /**
     * Check if verification is stale
     */
    public function isStale(int $maxAgeSeconds = 604800): bool
    {
        $age = time() - $this->lastVerified->getTimestamp();
        return $age > $maxAgeSeconds;
    }

    /**
     * Convert to array for serialization
     */
    public function toArray(): array
    {
        return [
            'entity_id' => $this->entityId->value(),
            'completeness_score' => $this->completenessScore->value(),
            'consistency_score' => $this->consistencyScore->value(),
            'overall_score' => $this->getOverallScore()->value(),
            'overall_grade' => $this->getOverallScore()->getGrade(),
            'last_verified' => $this->lastVerified->format('c'),
            'issues' => array_map(fn(IssueCode $i) => [
                'code' => $i->value,
                'label' => $i->label(),
                'severity' => $i->severity(),
                'category' => $i->category(),
                'critical' => $i->isCritical(),
            ], $this->issues),
            'has_critical_issues' => $this->hasCriticalIssues(),
            'needs_attention' => $this->needsAttention(),
        ];
    }
}
