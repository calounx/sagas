<?php
declare(strict_types=1);

namespace SagaManager\Application\UseCase\DeleteAttributeDefinition;

use SagaManager\Application\Command\CommandInterface;

/**
 * Delete Attribute Definition Command
 *
 * Command to delete an attribute definition.
 * Warning: This will cascade delete all associated attribute values.
 */
final readonly class DeleteAttributeDefinitionCommand implements CommandInterface
{
    public function __construct(
        public int $id
    ) {}
}
