<?php
declare(strict_types=1);

namespace SagaManager\Application\UseCase\DeleteContentFragment;

use SagaManager\Application\Command\CommandHandlerInterface;
use SagaManager\Application\Command\CommandInterface;
use SagaManager\Domain\Entity\ContentFragmentId;
use SagaManager\Domain\Repository\ContentFragmentRepositoryInterface;

/**
 * Delete Content Fragment Handler
 *
 * @implements CommandHandlerInterface<DeleteContentFragmentCommand, void>
 */
final readonly class DeleteContentFragmentHandler implements CommandHandlerInterface
{
    public function __construct(
        private ContentFragmentRepositoryInterface $fragmentRepository
    ) {}

    public function handle(CommandInterface $command): void
    {
        if (!$command instanceof DeleteContentFragmentCommand) {
            throw new \InvalidArgumentException(
                sprintf('Expected %s, got %s', DeleteContentFragmentCommand::class, get_class($command))
            );
        }

        $id = new ContentFragmentId($command->id);

        // Verify exists
        $this->fragmentRepository->findById($id);

        $this->fragmentRepository->delete($id);
    }
}
