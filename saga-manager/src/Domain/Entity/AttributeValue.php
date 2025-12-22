<?php
declare(strict_types=1);

namespace SagaManager\Domain\Entity;

/**
 * Attribute Value - Value Object
 *
 * Represents a single attribute value for an entity.
 * Immutable - to change a value, create a new instance using withValue().
 */
final readonly class AttributeValue
{
    private function __construct(
        private EntityId $entityId,
        private AttributeDefinitionId $attributeId,
        private string $attributeKey,
        private DataType $dataType,
        private mixed $value,
        private \DateTimeImmutable $updatedAt
    ) {}

    /**
     * Create a new AttributeValue
     */
    public static function create(
        EntityId $entityId,
        AttributeDefinitionId $attributeId,
        string $attributeKey,
        DataType $dataType,
        mixed $value,
        ?\DateTimeImmutable $updatedAt = null
    ): self {
        $typedValue = self::castValue($value, $dataType);

        return new self(
            entityId: $entityId,
            attributeId: $attributeId,
            attributeKey: $attributeKey,
            dataType: $dataType,
            value: $typedValue,
            updatedAt: $updatedAt ?? new \DateTimeImmutable()
        );
    }

    /**
     * Create from database row data
     */
    public static function fromRow(array $row, EntityId $entityId): self
    {
        $dataType = DataType::from($row['data_type']);

        return new self(
            entityId: $entityId,
            attributeId: new AttributeDefinitionId((int) $row['attribute_id']),
            attributeKey: $row['attribute_key'],
            dataType: $dataType,
            value: self::extractValueFromRow($row, $dataType),
            updatedAt: new \DateTimeImmutable($row['updated_at'])
        );
    }

    /**
     * Cast value to the appropriate type based on DataType
     */
    private static function castValue(mixed $value, DataType $dataType): mixed
    {
        if ($value === null) {
            return null;
        }

        return match ($dataType) {
            DataType::STRING => (string) $value,
            DataType::INT => (int) $value,
            DataType::FLOAT => (float) $value,
            DataType::BOOL => (bool) $value,
            DataType::DATE => $value instanceof \DateTimeInterface
                ? $value->format('Y-m-d')
                : (string) $value,
            DataType::TEXT => (string) $value,
            DataType::JSON => is_string($value)
                ? json_decode($value, true)
                : (array) $value,
        };
    }

    /**
     * Extract value from database row based on data type
     */
    private static function extractValueFromRow(array $row, DataType $dataType): mixed
    {
        $column = $dataType->getValueColumn();
        $rawValue = $row[$column] ?? null;

        if ($rawValue === null) {
            return null;
        }

        return match ($dataType) {
            DataType::STRING => (string) $rawValue,
            DataType::INT => (int) $rawValue,
            DataType::FLOAT => (float) $rawValue,
            DataType::BOOL => (bool) $rawValue,
            DataType::DATE => (string) $rawValue,
            DataType::TEXT => (string) $rawValue,
            DataType::JSON => is_string($rawValue)
                ? json_decode($rawValue, true)
                : $rawValue,
        };
    }

    public function getEntityId(): EntityId
    {
        return $this->entityId;
    }

    public function getAttributeId(): AttributeDefinitionId
    {
        return $this->attributeId;
    }

    public function getAttributeKey(): string
    {
        return $this->attributeKey;
    }

    public function getDataType(): DataType
    {
        return $this->dataType;
    }

    public function getValue(): mixed
    {
        return $this->value;
    }

    public function getUpdatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }

    /**
     * Check if the value is null
     */
    public function isNull(): bool
    {
        return $this->value === null;
    }

    /**
     * Create a new AttributeValue with a different value
     */
    public function withValue(mixed $newValue): self
    {
        return self::create(
            entityId: $this->entityId,
            attributeId: $this->attributeId,
            attributeKey: $this->attributeKey,
            dataType: $this->dataType,
            value: $newValue
        );
    }

    /**
     * Get value formatted for database storage
     */
    public function getStorageValue(): mixed
    {
        if ($this->value === null) {
            return null;
        }

        return match ($this->dataType) {
            DataType::JSON => json_encode($this->value, JSON_THROW_ON_ERROR),
            DataType::BOOL => $this->value ? 1 : 0,
            default => $this->value,
        };
    }

    /**
     * Check equality with another AttributeValue
     */
    public function equals(AttributeValue $other): bool
    {
        return $this->entityId->equals($other->entityId)
            && $this->attributeId->equals($other->attributeId)
            && $this->value === $other->value;
    }

    public function __toString(): string
    {
        if ($this->value === null) {
            return '';
        }

        return match ($this->dataType) {
            DataType::JSON => json_encode($this->value, JSON_THROW_ON_ERROR),
            DataType::BOOL => $this->value ? 'true' : 'false',
            default => (string) $this->value,
        };
    }
}
