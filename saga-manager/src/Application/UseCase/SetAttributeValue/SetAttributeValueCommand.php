<?php
declare(strict_types=1);

namespace SagaManager\Application\UseCase\SetAttributeValue;

use SagaManager\Application\Command\CommandInterface;

/**
 * Set Attribute Value Command
 *
 * Command to set a single attribute value for an entity.
 */
final readonly class SetAttributeValueCommand implements CommandInterface
{
    /**
     * @param int $entityId The entity to set the attribute on
     * @param string $attributeKey The attribute key to set
     * @param mixed $value The value to set (type must match attribute's data type)
     */
    public function __construct(
        public int $entityId,
        public string $attributeKey,
        public mixed $value
    ) {}
}
