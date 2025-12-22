<?php
declare(strict_types=1);

namespace SagaManager\Application\UseCase\BulkSetAttributeValues;

use SagaManager\Application\Command\CommandInterface;

/**
 * Bulk Set Attribute Values Command
 *
 * Command to set multiple attribute values for an entity in a single transaction.
 */
final readonly class BulkSetAttributeValuesCommand implements CommandInterface
{
    /**
     * @param int $entityId The entity to set attributes on
     * @param array<string, mixed> $attributes Key-value pairs of attribute_key => value
     */
    public function __construct(
        public int $entityId,
        public array $attributes
    ) {}
}
