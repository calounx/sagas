<?php
declare(strict_types=1);

namespace SagaManager\Application\UseCase\BulkGenerateEmbeddings;

use SagaManager\Application\Command\CommandInterface;

/**
 * Bulk Generate Embeddings Command
 */
final readonly class BulkGenerateEmbeddingsCommand implements CommandInterface
{
    /**
     * @param int[] $fragmentIds
     */
    public function __construct(
        public array $fragmentIds = [],
        public int $limit = 100
    ) {}
}
