<?php
declare(strict_types=1);

namespace SagaManager\Application\UseCase\DeleteRelationship;

use SagaManager\Application\Command\CommandInterface;

/**
 * Delete Relationship Command
 */
final readonly class DeleteRelationshipCommand implements CommandInterface
{
    public function __construct(
        public int $id
    ) {}
}
