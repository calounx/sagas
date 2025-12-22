<?php
declare(strict_types=1);

namespace SagaManager\Application\UseCase\CreateAttributeDefinition;

use SagaManager\Application\Command\CommandInterface;

/**
 * Create Attribute Definition Command
 *
 * Command to create a new attribute definition for an entity type.
 */
final readonly class CreateAttributeDefinitionCommand implements CommandInterface
{
    /**
     * @param string $entityType Entity type (character, location, event, faction, artifact, concept)
     * @param string $attributeKey Unique key for the attribute (lowercase, underscores)
     * @param string $displayName Human-readable display name
     * @param string $dataType Data type (string, int, float, bool, date, text, json)
     * @param bool $isSearchable Whether this attribute should be indexed for search
     * @param bool $isRequired Whether this attribute is required for entities
     * @param array|null $validationRule Validation configuration (regex, min, max, enum, etc.)
     * @param string|null $defaultValue Default value for new entities
     */
    public function __construct(
        public string $entityType,
        public string $attributeKey,
        public string $displayName,
        public string $dataType,
        public bool $isSearchable = false,
        public bool $isRequired = false,
        public ?array $validationRule = null,
        public ?string $defaultValue = null
    ) {}
}
