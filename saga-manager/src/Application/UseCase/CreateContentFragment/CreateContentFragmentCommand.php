<?php
declare(strict_types=1);

namespace SagaManager\Application\UseCase\CreateContentFragment;

use SagaManager\Application\Command\CommandInterface;

/**
 * Create Content Fragment Command
 */
final readonly class CreateContentFragmentCommand implements CommandInterface
{
    public function __construct(
        public int $entityId,
        public string $fragmentText,
        public ?int $tokenCount = null
    ) {}
}
