<?php
declare(strict_types=1);

namespace SagaManager\Application\UseCase\CreateContentFragment;

use SagaManager\Application\Command\CommandHandlerInterface;
use SagaManager\Application\Command\CommandInterface;
use SagaManager\Domain\Entity\ContentFragment;
use SagaManager\Domain\Entity\EntityId;
use SagaManager\Domain\Entity\TokenCount;
use SagaManager\Domain\Repository\ContentFragmentRepositoryInterface;
use SagaManager\Domain\Repository\EntityRepositoryInterface;

/**
 * Create Content Fragment Handler
 *
 * @implements CommandHandlerInterface<CreateContentFragmentCommand, ContentFragment>
 */
final readonly class CreateContentFragmentHandler implements CommandHandlerInterface
{
    public function __construct(
        private ContentFragmentRepositoryInterface $fragmentRepository,
        private EntityRepositoryInterface $entityRepository
    ) {}

    public function handle(CommandInterface $command): ContentFragment
    {
        if (!$command instanceof CreateContentFragmentCommand) {
            throw new \InvalidArgumentException(
                sprintf('Expected %s, got %s', CreateContentFragmentCommand::class, get_class($command))
            );
        }

        $entityId = new EntityId($command->entityId);

        // Verify entity exists
        $this->entityRepository->findById($entityId);

        $tokenCount = $command->tokenCount !== null
            ? new TokenCount($command->tokenCount)
            : null;

        $fragment = new ContentFragment(
            entityId: $entityId,
            fragmentText: $command->fragmentText,
            tokenCount: $tokenCount
        );

        $this->fragmentRepository->save($fragment);

        return $fragment;
    }
}
