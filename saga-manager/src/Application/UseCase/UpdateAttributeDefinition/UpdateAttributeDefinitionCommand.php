<?php
declare(strict_types=1);

namespace SagaManager\Application\UseCase\UpdateAttributeDefinition;

use SagaManager\Application\Command\CommandInterface;

/**
 * Update Attribute Definition Command
 *
 * Command to update an existing attribute definition.
 * Note: entityType, attributeKey, and dataType cannot be changed after creation.
 */
final readonly class UpdateAttributeDefinitionCommand implements CommandInterface
{
    /**
     * @param int $id The ID of the attribute definition to update
     * @param string|null $displayName New display name (null to keep current)
     * @param bool|null $isSearchable New searchable flag (null to keep current)
     * @param bool|null $isRequired New required flag (null to keep current)
     * @param array|null $validationRule New validation rules (empty array to clear)
     * @param string|null $defaultValue New default value (empty string to clear)
     */
    public function __construct(
        public int $id,
        public ?string $displayName = null,
        public ?bool $isSearchable = null,
        public ?bool $isRequired = null,
        public ?array $validationRule = null,
        public ?string $defaultValue = null
    ) {}
}
