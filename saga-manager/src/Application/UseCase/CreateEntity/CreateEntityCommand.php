<?php
declare(strict_types=1);

namespace SagaManager\Application\UseCase\CreateEntity;

use SagaManager\Application\Command\CommandInterface;

/**
 * Create Entity Command
 *
 * Command to create a new saga entity.
 * Immutable command object following CQRS pattern.
 */
final readonly class CreateEntityCommand implements CommandInterface
{
    public function __construct(
        public int $sagaId,
        public string $type,
        public string $canonicalName,
        public string $slug,
        public ?int $importanceScore = null,
        public ?int $wpPostId = null
    ) {
    }
}
