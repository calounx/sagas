<?php
declare(strict_types=1);

namespace SagaManager\Application\UseCase\DeleteEntity;

use SagaManager\Application\Command\CommandInterface;

/**
 * Delete Entity Command
 *
 * Command to delete a saga entity.
 * Immutable command object following CQRS pattern.
 */
final readonly class DeleteEntityCommand implements CommandInterface
{
    public function __construct(
        public int $entityId
    ) {
    }
}
