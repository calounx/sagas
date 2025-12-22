<?php
declare(strict_types=1);

namespace SagaManager\Application\UseCase\RecalculateQualityMetrics;

use SagaManager\Application\UseCase\CommandHandlerInterface;
use SagaManager\Application\UseCase\CommandInterface;
use SagaManager\Domain\Entity\EntityId;
use SagaManager\Domain\Entity\IssueCode;
use SagaManager\Domain\Entity\QualityMetrics;
use SagaManager\Domain\Entity\QualityScore;
use SagaManager\Domain\Entity\SagaId;
use SagaManager\Domain\Repository\QualityMetricsRepositoryInterface;
use SagaManager\Domain\Repository\EntityRepositoryInterface;
use SagaManager\Domain\Repository\EntityRelationshipRepositoryInterface;
use SagaManager\Domain\Repository\ContentFragmentRepositoryInterface;
use SagaManager\Domain\Repository\TimelineEventRepositoryInterface;

/**
 * Recalculate Quality Metrics Handler
 *
 * Performs quality analysis on entities and updates their metrics.
 *
 * @implements CommandHandlerInterface<RecalculateQualityMetricsCommand, array{processed: int, updated: int}>
 */
final readonly class RecalculateQualityMetricsHandler implements CommandHandlerInterface
{
    public function __construct(
        private QualityMetricsRepositoryInterface $metricsRepository,
        private EntityRepositoryInterface $entityRepository,
        private EntityRelationshipRepositoryInterface $relationshipRepository,
        private ContentFragmentRepositoryInterface $fragmentRepository,
        private TimelineEventRepositoryInterface $timelineRepository
    ) {}

    /**
     * @return array{processed: int, updated: int}
     */
    public function handle(CommandInterface $command): array
    {
        if (!$command instanceof RecalculateQualityMetricsCommand) {
            throw new \InvalidArgumentException(
                sprintf('Expected %s, got %s', RecalculateQualityMetricsCommand::class, get_class($command))
            );
        }

        $sagaId = new SagaId($command->sagaId);
        $processed = 0;
        $updated = 0;

        // Get entities to process
        if ($command->entityId !== null) {
            $entityIds = [new EntityId($command->entityId)];
        } else {
            $entityIds = $this->metricsRepository->findNeedingVerification(
                $sagaId,
                604800, // 7 days
                $command->limit
            );
        }

        foreach ($entityIds as $entityId) {
            $processed++;

            $entity = $this->entityRepository->findById($entityId);
            if (!$entity) {
                continue;
            }

            // Calculate completeness
            $completenessResult = $this->calculateCompleteness($entityId);

            // Calculate consistency
            $consistencyResult = $this->calculateConsistency($entityId);

            // Merge issues
            $issues = array_merge($completenessResult['issues'], $consistencyResult['issues']);

            // Create or update metrics
            $metrics = new QualityMetrics(
                entityId: $entityId,
                completenessScore: new QualityScore($completenessResult['score']),
                consistencyScore: new QualityScore($consistencyResult['score']),
                issues: $issues
            );

            $this->metricsRepository->save($metrics);
            $updated++;
        }

        return [
            'processed' => $processed,
            'updated' => $updated,
        ];
    }

    /**
     * Calculate completeness score for an entity
     *
     * @return array{score: int, issues: IssueCode[]}
     */
    private function calculateCompleteness(EntityId $entityId): array
    {
        $score = 100;
        $issues = [];

        // Check for content fragments
        $fragments = $this->fragmentRepository->findByEntityId($entityId);
        if (empty($fragments)) {
            $score -= 20;
            $issues[] = IssueCode::MISSING_FRAGMENTS;
        } else {
            // Check for embeddings
            $hasEmbedding = false;
            foreach ($fragments as $fragment) {
                if ($fragment->hasEmbedding()) {
                    $hasEmbedding = true;
                    break;
                }
            }
            if (!$hasEmbedding) {
                $score -= 10;
                $issues[] = IssueCode::NO_EMBEDDING;
            }
        }

        // Check for relationships
        $relationships = $this->relationshipRepository->findBySourceEntity($entityId);
        if (empty($relationships)) {
            $score -= 15;
            $issues[] = IssueCode::MISSING_RELATIONSHIPS;
        }

        // Check for timeline events
        $events = $this->timelineRepository->findByEntity($entityId);
        if (empty($events)) {
            $score -= 10;
            $issues[] = IssueCode::MISSING_TIMELINE;
        }

        return [
            'score' => max(0, $score),
            'issues' => $issues,
        ];
    }

    /**
     * Calculate consistency score for an entity
     *
     * @return array{score: int, issues: IssueCode[]}
     */
    private function calculateConsistency(EntityId $entityId): array
    {
        $score = 100;
        $issues = [];

        // Check for orphan relationships (targets that don't exist)
        $relationships = $this->relationshipRepository->findBySourceEntity($entityId);
        foreach ($relationships as $relationship) {
            $targetEntity = $this->entityRepository->findById($relationship->getTargetEntityId());
            if (!$targetEntity) {
                $score -= 20;
                $issues[] = IssueCode::ORPHAN_RELATIONSHIP;
                break; // Only penalize once
            }
        }

        // Check for circular relationships (A -> B -> A)
        $visited = [$entityId->value()];
        foreach ($relationships as $relationship) {
            $targetId = $relationship->getTargetEntityId();
            if (in_array($targetId->value(), $visited)) {
                $score -= 15;
                $issues[] = IssueCode::CIRCULAR_RELATIONSHIP;
                break;
            }

            // Check one level deeper
            $secondLevel = $this->relationshipRepository->findBySourceEntity($targetId);
            foreach ($secondLevel as $secondRel) {
                if ($secondRel->getTargetEntityId()->value() === $entityId->value()) {
                    $score -= 15;
                    $issues[] = IssueCode::CIRCULAR_RELATIONSHIP;
                    break 2;
                }
            }
        }

        // Check for duplicate relationships
        $relationshipSignatures = [];
        foreach ($relationships as $relationship) {
            $signature = $relationship->getTargetEntityId()->value() . ':' . $relationship->getType();
            if (in_array($signature, $relationshipSignatures)) {
                $score -= 10;
                $issues[] = IssueCode::DUPLICATE_RELATIONSHIP;
                break;
            }
            $relationshipSignatures[] = $signature;
        }

        return [
            'score' => max(0, $score),
            'issues' => array_unique($issues, SORT_REGULAR),
        ];
    }
}
