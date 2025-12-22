<?php
declare(strict_types=1);

namespace SagaManager\Application\UseCase\DeleteContentFragment;

use SagaManager\Application\Command\CommandInterface;

/**
 * Delete Content Fragment Command
 */
final readonly class DeleteContentFragmentCommand implements CommandInterface
{
    public function __construct(
        public int $id
    ) {}
}
