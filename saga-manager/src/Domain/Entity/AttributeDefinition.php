<?php
declare(strict_types=1);

namespace SagaManager\Domain\Entity;

use SagaManager\Domain\Exception\ValidationException;

/**
 * Attribute Definition Entity
 *
 * Defines the schema for an EAV attribute.
 * Mutable entity - properties can be updated after creation.
 */
class AttributeDefinition
{
    private ?AttributeDefinitionId $id;
    private EntityType $entityType;
    private string $attributeKey;
    private string $displayName;
    private DataType $dataType;
    private bool $isSearchable;
    private bool $isRequired;
    private ?ValidationRule $validationRule;
    private ?string $defaultValue;
    private \DateTimeImmutable $createdAt;

    public function __construct(
        EntityType $entityType,
        string $attributeKey,
        string $displayName,
        DataType $dataType,
        bool $isSearchable = false,
        bool $isRequired = false,
        ?ValidationRule $validationRule = null,
        ?string $defaultValue = null,
        ?AttributeDefinitionId $id = null,
        ?\DateTimeImmutable $createdAt = null
    ) {
        $this->validateAttributeKey($attributeKey);
        $this->validateDisplayName($displayName);

        $this->id = $id;
        $this->entityType = $entityType;
        $this->attributeKey = $attributeKey;
        $this->displayName = $displayName;
        $this->dataType = $dataType;
        $this->isSearchable = $isSearchable;
        $this->isRequired = $isRequired;
        $this->validationRule = $validationRule;
        $this->defaultValue = $defaultValue;
        $this->createdAt = $createdAt ?? new \DateTimeImmutable();
    }

    public function getId(): ?AttributeDefinitionId
    {
        return $this->id;
    }

    public function setId(AttributeDefinitionId $id): void
    {
        if ($this->id !== null) {
            throw new ValidationException('Cannot change attribute definition ID once set');
        }
        $this->id = $id;
    }

    public function getEntityType(): EntityType
    {
        return $this->entityType;
    }

    public function getAttributeKey(): string
    {
        return $this->attributeKey;
    }

    public function getDisplayName(): string
    {
        return $this->displayName;
    }

    public function updateDisplayName(string $displayName): void
    {
        $this->validateDisplayName($displayName);
        $this->displayName = $displayName;
    }

    public function getDataType(): DataType
    {
        return $this->dataType;
    }

    public function isSearchable(): bool
    {
        return $this->isSearchable;
    }

    public function setSearchable(bool $isSearchable): void
    {
        $this->isSearchable = $isSearchable;
    }

    public function isRequired(): bool
    {
        return $this->isRequired;
    }

    public function setRequired(bool $isRequired): void
    {
        $this->isRequired = $isRequired;
    }

    public function getValidationRule(): ?ValidationRule
    {
        return $this->validationRule;
    }

    public function updateValidationRule(?ValidationRule $rule): void
    {
        $this->validationRule = $rule;
    }

    public function getDefaultValue(): ?string
    {
        return $this->defaultValue;
    }

    public function updateDefaultValue(?string $defaultValue): void
    {
        $this->defaultValue = $defaultValue;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    /**
     * Validate a value against this definition's rules
     */
    public function validateValue(mixed $value): bool
    {
        // Handle null values
        if ($value === null) {
            return !$this->isRequired;
        }

        // Apply validation rules if present
        if ($this->validationRule !== null) {
            return $this->validationRule->validate($value, $this->dataType);
        }

        return true;
    }

    /**
     * Get validation error message for a value
     */
    public function getValidationError(mixed $value): ?string
    {
        if ($value === null && $this->isRequired) {
            return sprintf('Attribute "%s" is required', $this->displayName);
        }

        if ($value !== null && $this->validationRule !== null) {
            return $this->validationRule->getErrorMessage($value, $this->dataType);
        }

        return null;
    }

    /**
     * Create an AttributeValue for this definition
     */
    public function createValue(EntityId $entityId, mixed $value): AttributeValue
    {
        if ($this->id === null) {
            throw new ValidationException('Cannot create value for unsaved attribute definition');
        }

        return AttributeValue::create(
            entityId: $entityId,
            attributeId: $this->id,
            attributeKey: $this->attributeKey,
            dataType: $this->dataType,
            value: $value
        );
    }

    /**
     * Get the typed default value
     */
    public function getTypedDefaultValue(): mixed
    {
        if ($this->defaultValue === null) {
            return null;
        }

        return match ($this->dataType) {
            DataType::INT => (int) $this->defaultValue,
            DataType::FLOAT => (float) $this->defaultValue,
            DataType::BOOL => in_array(strtolower($this->defaultValue), ['true', '1', 'yes'], true),
            DataType::JSON => json_decode($this->defaultValue, true),
            default => $this->defaultValue,
        };
    }

    private function validateAttributeKey(string $key): void
    {
        $key = trim($key);

        if (empty($key)) {
            throw new ValidationException('Attribute key cannot be empty');
        }

        if (strlen($key) > 100) {
            throw new ValidationException('Attribute key cannot exceed 100 characters');
        }

        if (!preg_match('/^[a-z][a-z0-9_]*$/', $key)) {
            throw new ValidationException(
                'Attribute key must start with lowercase letter and contain only lowercase letters, numbers, and underscores'
            );
        }
    }

    private function validateDisplayName(string $name): void
    {
        $name = trim($name);

        if (empty($name)) {
            throw new ValidationException('Display name cannot be empty');
        }

        if (strlen($name) > 150) {
            throw new ValidationException('Display name cannot exceed 150 characters');
        }
    }
}
