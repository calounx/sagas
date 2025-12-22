<?php
declare(strict_types=1);

namespace SagaManager\Tests\Unit\Domain\Entity;

use PHPUnit\Framework\TestCase;
use SagaManager\Domain\Entity\AttributeDefinition;
use SagaManager\Domain\Entity\AttributeDefinitionId;
use SagaManager\Domain\Entity\DataType;
use SagaManager\Domain\Entity\EntityId;
use SagaManager\Domain\Entity\EntityType;
use SagaManager\Domain\Entity\ValidationRule;
use SagaManager\Domain\Exception\ValidationException;

/**
 * Unit tests for AttributeDefinition entity
 *
 * @covers \SagaManager\Domain\Entity\AttributeDefinition
 */
final class AttributeDefinitionTest extends TestCase
{
    public function test_constructs_with_required_parameters(): void
    {
        $definition = new AttributeDefinition(
            entityType: EntityType::CHARACTER,
            attributeKey: 'birth_year',
            displayName: 'Birth Year',
            dataType: DataType::INT
        );

        $this->assertSame(EntityType::CHARACTER, $definition->getEntityType());
        $this->assertSame('birth_year', $definition->getAttributeKey());
        $this->assertSame('Birth Year', $definition->getDisplayName());
        $this->assertSame(DataType::INT, $definition->getDataType());
        $this->assertFalse($definition->isSearchable());
        $this->assertFalse($definition->isRequired());
        $this->assertNull($definition->getValidationRule());
        $this->assertNull($definition->getDefaultValue());
        $this->assertNull($definition->getId());
    }

    public function test_constructs_with_all_parameters(): void
    {
        $validationRule = ValidationRule::fromArray(['min' => 0, 'max' => 100]);
        $createdAt = new \DateTimeImmutable('2024-01-01');

        $definition = new AttributeDefinition(
            entityType: EntityType::CHARACTER,
            attributeKey: 'power_level',
            displayName: 'Power Level',
            dataType: DataType::INT,
            isSearchable: true,
            isRequired: true,
            validationRule: $validationRule,
            defaultValue: '50',
            id: new AttributeDefinitionId(1),
            createdAt: $createdAt
        );

        $this->assertTrue($definition->isSearchable());
        $this->assertTrue($definition->isRequired());
        $this->assertNotNull($definition->getValidationRule());
        $this->assertSame('50', $definition->getDefaultValue());
        $this->assertSame(1, $definition->getId()->value());
        $this->assertSame($createdAt, $definition->getCreatedAt());
    }

    public function test_throws_exception_for_empty_attribute_key(): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Attribute key cannot be empty');

        new AttributeDefinition(
            entityType: EntityType::CHARACTER,
            attributeKey: '',
            displayName: 'Test',
            dataType: DataType::STRING
        );
    }

    public function test_throws_exception_for_whitespace_only_attribute_key(): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Attribute key cannot be empty');

        new AttributeDefinition(
            entityType: EntityType::CHARACTER,
            attributeKey: '   ',
            displayName: 'Test',
            dataType: DataType::STRING
        );
    }

    public function test_throws_exception_for_attribute_key_exceeding_max_length(): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Attribute key cannot exceed 100 characters');

        new AttributeDefinition(
            entityType: EntityType::CHARACTER,
            attributeKey: str_repeat('a', 101),
            displayName: 'Test',
            dataType: DataType::STRING
        );
    }

    public function test_throws_exception_for_invalid_attribute_key_format(): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('lowercase letter');

        new AttributeDefinition(
            entityType: EntityType::CHARACTER,
            attributeKey: 'InvalidKey',
            displayName: 'Test',
            dataType: DataType::STRING
        );
    }

    public function test_throws_exception_for_attribute_key_starting_with_number(): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('start with lowercase letter');

        new AttributeDefinition(
            entityType: EntityType::CHARACTER,
            attributeKey: '1test',
            displayName: 'Test',
            dataType: DataType::STRING
        );
    }

    public function test_throws_exception_for_empty_display_name(): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Display name cannot be empty');

        new AttributeDefinition(
            entityType: EntityType::CHARACTER,
            attributeKey: 'test',
            displayName: '',
            dataType: DataType::STRING
        );
    }

    public function test_throws_exception_for_display_name_exceeding_max_length(): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Display name cannot exceed 150 characters');

        new AttributeDefinition(
            entityType: EntityType::CHARACTER,
            attributeKey: 'test',
            displayName: str_repeat('A', 151),
            dataType: DataType::STRING
        );
    }

    public function test_set_id_sets_id_once(): void
    {
        $definition = new AttributeDefinition(
            entityType: EntityType::CHARACTER,
            attributeKey: 'test',
            displayName: 'Test',
            dataType: DataType::STRING
        );

        $id = new AttributeDefinitionId(42);
        $definition->setId($id);

        $this->assertSame(42, $definition->getId()->value());
    }

    public function test_set_id_throws_exception_when_already_set(): void
    {
        $definition = new AttributeDefinition(
            entityType: EntityType::CHARACTER,
            attributeKey: 'test',
            displayName: 'Test',
            dataType: DataType::STRING,
            id: new AttributeDefinitionId(1)
        );

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Cannot change attribute definition ID once set');

        $definition->setId(new AttributeDefinitionId(2));
    }

    public function test_update_display_name(): void
    {
        $definition = new AttributeDefinition(
            entityType: EntityType::CHARACTER,
            attributeKey: 'test',
            displayName: 'Original Name',
            dataType: DataType::STRING
        );

        $definition->updateDisplayName('New Name');

        $this->assertSame('New Name', $definition->getDisplayName());
    }

    public function test_set_searchable(): void
    {
        $definition = new AttributeDefinition(
            entityType: EntityType::CHARACTER,
            attributeKey: 'test',
            displayName: 'Test',
            dataType: DataType::STRING
        );

        $this->assertFalse($definition->isSearchable());

        $definition->setSearchable(true);
        $this->assertTrue($definition->isSearchable());

        $definition->setSearchable(false);
        $this->assertFalse($definition->isSearchable());
    }

    public function test_set_required(): void
    {
        $definition = new AttributeDefinition(
            entityType: EntityType::CHARACTER,
            attributeKey: 'test',
            displayName: 'Test',
            dataType: DataType::STRING
        );

        $this->assertFalse($definition->isRequired());

        $definition->setRequired(true);
        $this->assertTrue($definition->isRequired());

        $definition->setRequired(false);
        $this->assertFalse($definition->isRequired());
    }

    public function test_update_validation_rule(): void
    {
        $definition = new AttributeDefinition(
            entityType: EntityType::CHARACTER,
            attributeKey: 'test',
            displayName: 'Test',
            dataType: DataType::INT
        );

        $this->assertNull($definition->getValidationRule());

        $rule = ValidationRule::fromArray(['min' => 0, 'max' => 100]);
        $definition->updateValidationRule($rule);

        $this->assertNotNull($definition->getValidationRule());
        $this->assertSame(0, $definition->getValidationRule()->getMin());

        $definition->updateValidationRule(null);
        $this->assertNull($definition->getValidationRule());
    }

    public function test_update_default_value(): void
    {
        $definition = new AttributeDefinition(
            entityType: EntityType::CHARACTER,
            attributeKey: 'test',
            displayName: 'Test',
            dataType: DataType::STRING
        );

        $definition->updateDefaultValue('default');
        $this->assertSame('default', $definition->getDefaultValue());

        $definition->updateDefaultValue(null);
        $this->assertNull($definition->getDefaultValue());
    }

    public function test_validate_value_returns_true_for_valid_value(): void
    {
        $definition = new AttributeDefinition(
            entityType: EntityType::CHARACTER,
            attributeKey: 'score',
            displayName: 'Score',
            dataType: DataType::INT,
            validationRule: ValidationRule::fromArray(['min' => 0, 'max' => 100])
        );

        $this->assertTrue($definition->validateValue(50));
        $this->assertTrue($definition->validateValue(0));
        $this->assertTrue($definition->validateValue(100));
    }

    public function test_validate_value_returns_false_for_invalid_value(): void
    {
        $definition = new AttributeDefinition(
            entityType: EntityType::CHARACTER,
            attributeKey: 'score',
            displayName: 'Score',
            dataType: DataType::INT,
            validationRule: ValidationRule::fromArray(['min' => 0, 'max' => 100])
        );

        $this->assertFalse($definition->validateValue(-1));
        $this->assertFalse($definition->validateValue(101));
    }

    public function test_validate_value_handles_required_null(): void
    {
        $required = new AttributeDefinition(
            entityType: EntityType::CHARACTER,
            attributeKey: 'name',
            displayName: 'Name',
            dataType: DataType::STRING,
            isRequired: true
        );

        $optional = new AttributeDefinition(
            entityType: EntityType::CHARACTER,
            attributeKey: 'nickname',
            displayName: 'Nickname',
            dataType: DataType::STRING,
            isRequired: false
        );

        $this->assertFalse($required->validateValue(null));
        $this->assertTrue($optional->validateValue(null));
    }

    public function test_get_validation_error_returns_required_error(): void
    {
        $definition = new AttributeDefinition(
            entityType: EntityType::CHARACTER,
            attributeKey: 'name',
            displayName: 'Name',
            dataType: DataType::STRING,
            isRequired: true
        );

        $error = $definition->getValidationError(null);

        $this->assertStringContainsString('required', $error);
    }

    public function test_create_value_returns_attribute_value(): void
    {
        $definition = new AttributeDefinition(
            entityType: EntityType::CHARACTER,
            attributeKey: 'age',
            displayName: 'Age',
            dataType: DataType::INT,
            id: new AttributeDefinitionId(1)
        );

        $entityId = new EntityId(42);
        $value = $definition->createValue($entityId, 25);

        $this->assertSame(42, $value->getEntityId()->value());
        $this->assertSame(1, $value->getAttributeId()->value());
        $this->assertSame('age', $value->getAttributeKey());
        $this->assertSame(DataType::INT, $value->getDataType());
        $this->assertSame(25, $value->getValue());
    }

    public function test_create_value_throws_when_id_not_set(): void
    {
        $definition = new AttributeDefinition(
            entityType: EntityType::CHARACTER,
            attributeKey: 'age',
            displayName: 'Age',
            dataType: DataType::INT
        );

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Cannot create value for unsaved attribute definition');

        $definition->createValue(new EntityId(1), 25);
    }

    /**
     * @dataProvider typedDefaultValueProvider
     */
    public function test_get_typed_default_value(DataType $dataType, ?string $defaultValue, mixed $expected): void
    {
        $definition = new AttributeDefinition(
            entityType: EntityType::CHARACTER,
            attributeKey: 'test',
            displayName: 'Test',
            dataType: $dataType,
            defaultValue: $defaultValue
        );

        $this->assertSame($expected, $definition->getTypedDefaultValue());
    }

    public static function typedDefaultValueProvider(): array
    {
        return [
            'null default' => [DataType::STRING, null, null],
            'string default' => [DataType::STRING, 'hello', 'hello'],
            'int default' => [DataType::INT, '42', 42],
            'float default' => [DataType::FLOAT, '3.14', 3.14],
            'bool true' => [DataType::BOOL, 'true', true],
            'bool 1' => [DataType::BOOL, '1', true],
            'bool yes' => [DataType::BOOL, 'yes', true],
            'bool false' => [DataType::BOOL, 'false', false],
        ];
    }
}
