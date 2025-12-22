<?php
declare(strict_types=1);

namespace SagaManager\Application\UseCase\UpdateQualityMetrics;

use SagaManager\Application\UseCase\CommandHandlerInterface;
use SagaManager\Application\UseCase\CommandInterface;
use SagaManager\Domain\Entity\EntityId;
use SagaManager\Domain\Entity\IssueCode;
use SagaManager\Domain\Entity\QualityMetrics;
use SagaManager\Domain\Entity\QualityScore;
use SagaManager\Domain\Repository\QualityMetricsRepositoryInterface;

/**
 * Update Quality Metrics Handler
 *
 * @implements CommandHandlerInterface<UpdateQualityMetricsCommand, QualityMetrics>
 */
final readonly class UpdateQualityMetricsHandler implements CommandHandlerInterface
{
    public function __construct(
        private QualityMetricsRepositoryInterface $repository
    ) {}

    public function handle(CommandInterface $command): QualityMetrics
    {
        if (!$command instanceof UpdateQualityMetricsCommand) {
            throw new \InvalidArgumentException(
                sprintf('Expected %s, got %s', UpdateQualityMetricsCommand::class, get_class($command))
            );
        }

        $entityId = new EntityId($command->entityId);

        // Parse issue codes
        $issues = [];
        foreach ($command->issueCodes as $code) {
            $issue = IssueCode::tryFrom($code);
            if ($issue !== null) {
                $issues[] = $issue;
            }
        }

        // Check if metrics exist
        $existing = $this->repository->findByEntityId($entityId);

        if ($existing) {
            // Update existing metrics
            $existing->updateCompletenessScore(new QualityScore($command->completenessScore));
            $existing->updateConsistencyScore(new QualityScore($command->consistencyScore));
            $existing->setIssues($issues);

            $this->repository->save($existing);

            return $existing;
        }

        // Create new metrics
        $metrics = new QualityMetrics(
            entityId: $entityId,
            completenessScore: new QualityScore($command->completenessScore),
            consistencyScore: new QualityScore($command->consistencyScore),
            issues: $issues
        );

        $this->repository->save($metrics);

        return $metrics;
    }
}
