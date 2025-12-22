<?php
declare(strict_types=1);

namespace SagaManager\Application\UseCase\GenerateEmbedding;

use SagaManager\Application\Command\CommandInterface;

/**
 * Generate Embedding Command
 */
final readonly class GenerateEmbeddingCommand implements CommandInterface
{
    public function __construct(
        public int $fragmentId
    ) {}
}
