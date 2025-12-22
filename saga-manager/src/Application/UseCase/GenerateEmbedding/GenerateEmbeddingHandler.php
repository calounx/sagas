<?php
declare(strict_types=1);

namespace SagaManager\Application\UseCase\GenerateEmbedding;

use SagaManager\Application\Command\CommandHandlerInterface;
use SagaManager\Application\Command\CommandInterface;
use SagaManager\Domain\Entity\ContentFragment;
use SagaManager\Domain\Entity\ContentFragmentId;
use SagaManager\Domain\Repository\ContentFragmentRepositoryInterface;
use SagaManager\Domain\Service\EmbeddingServiceInterface;

/**
 * Generate Embedding Handler
 *
 * @implements CommandHandlerInterface<GenerateEmbeddingCommand, ContentFragment>
 */
final readonly class GenerateEmbeddingHandler implements CommandHandlerInterface
{
    public function __construct(
        private ContentFragmentRepositoryInterface $fragmentRepository,
        private EmbeddingServiceInterface $embeddingService
    ) {}

    public function handle(CommandInterface $command): ContentFragment
    {
        if (!$command instanceof GenerateEmbeddingCommand) {
            throw new \InvalidArgumentException(
                sprintf('Expected %s, got %s', GenerateEmbeddingCommand::class, get_class($command))
            );
        }

        $fragmentId = new ContentFragmentId($command->fragmentId);
        $fragment = $this->fragmentRepository->findById($fragmentId);

        // Generate embedding
        $vector = $this->embeddingService->embed($fragment->getFragmentText());

        // Store embedding as binary
        $fragment->setEmbedding($vector->toBinary());

        $this->fragmentRepository->save($fragment);

        return $fragment;
    }
}
