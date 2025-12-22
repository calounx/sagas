<?php
declare(strict_types=1);

namespace SagaManager\Application\UseCase\BulkCreateContentFragments;

use SagaManager\Application\Command\CommandHandlerInterface;
use SagaManager\Application\Command\CommandInterface;
use SagaManager\Domain\Entity\ContentFragment;
use SagaManager\Domain\Entity\EntityId;
use SagaManager\Domain\Entity\TokenCount;
use SagaManager\Domain\Repository\ContentFragmentRepositoryInterface;
use SagaManager\Domain\Repository\EntityRepositoryInterface;

/**
 * Bulk Create Content Fragments Handler
 *
 * @implements CommandHandlerInterface<BulkCreateContentFragmentsCommand, ContentFragment[]>
 */
final readonly class BulkCreateContentFragmentsHandler implements CommandHandlerInterface
{
    public function __construct(
        private ContentFragmentRepositoryInterface $fragmentRepository,
        private EntityRepositoryInterface $entityRepository
    ) {}

    /**
     * @return ContentFragment[]
     */
    public function handle(CommandInterface $command): array
    {
        if (!$command instanceof BulkCreateContentFragmentsCommand) {
            throw new \InvalidArgumentException(
                sprintf('Expected %s, got %s', BulkCreateContentFragmentsCommand::class, get_class($command))
            );
        }

        $entityId = new EntityId($command->entityId);

        // Verify entity exists
        $this->entityRepository->findById($entityId);

        $createdFragments = [];

        foreach ($command->fragments as $fragmentData) {
            $tokenCount = isset($fragmentData['token_count'])
                ? new TokenCount($fragmentData['token_count'])
                : null;

            $fragment = new ContentFragment(
                entityId: $entityId,
                fragmentText: $fragmentData['text'],
                tokenCount: $tokenCount
            );

            $createdFragments[] = $fragment;
        }

        $this->fragmentRepository->saveMany($createdFragments);

        return $createdFragments;
    }
}
