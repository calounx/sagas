<?php
declare(strict_types=1);

namespace SagaManager\Application\UseCase\BulkGenerateEmbeddings;

use SagaManager\Application\Command\CommandHandlerInterface;
use SagaManager\Application\Command\CommandInterface;
use SagaManager\Domain\Entity\ContentFragment;
use SagaManager\Domain\Entity\ContentFragmentId;
use SagaManager\Domain\Repository\ContentFragmentRepositoryInterface;
use SagaManager\Domain\Service\EmbeddingServiceInterface;

/**
 * Bulk Generate Embeddings Handler
 *
 * @implements CommandHandlerInterface<BulkGenerateEmbeddingsCommand, array>
 */
final readonly class BulkGenerateEmbeddingsHandler implements CommandHandlerInterface
{
    public function __construct(
        private ContentFragmentRepositoryInterface $fragmentRepository,
        private EmbeddingServiceInterface $embeddingService
    ) {}

    /**
     * @return array{processed: int, failed: int, fragments: ContentFragment[]}
     */
    public function handle(CommandInterface $command): array
    {
        if (!$command instanceof BulkGenerateEmbeddingsCommand) {
            throw new \InvalidArgumentException(
                sprintf('Expected %s, got %s', BulkGenerateEmbeddingsCommand::class, get_class($command))
            );
        }

        // Get fragments to process
        if (!empty($command->fragmentIds)) {
            $fragments = [];
            foreach ($command->fragmentIds as $id) {
                $fragments[] = $this->fragmentRepository->findById(new ContentFragmentId($id));
            }
        } else {
            $fragments = $this->fragmentRepository->findWithoutEmbeddings($command->limit);
        }

        if (empty($fragments)) {
            return ['processed' => 0, 'failed' => 0, 'fragments' => []];
        }

        // Extract texts for batch processing
        $texts = array_map(fn($f) => $f->getFragmentText(), $fragments);

        // Generate embeddings in batch
        $vectors = $this->embeddingService->embedBatch($texts);

        $processed = 0;
        $failed = 0;
        $processedFragments = [];

        foreach ($fragments as $index => $fragment) {
            try {
                if (isset($vectors[$index])) {
                    $fragment->setEmbedding($vectors[$index]->toBinary());
                    $this->fragmentRepository->save($fragment);
                    $processedFragments[] = $fragment;
                    $processed++;
                } else {
                    $failed++;
                }
            } catch (\Exception $e) {
                error_log('[SAGA][EMBEDDING] Failed to save embedding: ' . $e->getMessage());
                $failed++;
            }
        }

        return [
            'processed' => $processed,
            'failed' => $failed,
            'fragments' => $processedFragments,
        ];
    }
}
