<?php
declare(strict_types=1);

namespace SagaManager\Tests\Unit\Domain\Entity;

use PHPUnit\Framework\TestCase;
use SagaManager\Domain\Entity\AttributeDefinitionId;
use SagaManager\Domain\Exception\ValidationException;

/**
 * Unit tests for AttributeDefinitionId value object
 *
 * @covers \SagaManager\Domain\Entity\AttributeDefinitionId
 */
final class AttributeDefinitionIdTest extends TestCase
{
    public function test_constructs_with_valid_id(): void
    {
        $id = new AttributeDefinitionId(1);

        $this->assertSame(1, $id->value());
    }

    public function test_constructs_with_large_id(): void
    {
        $id = new AttributeDefinitionId(999999);

        $this->assertSame(999999, $id->value());
    }

    public function test_throws_exception_for_zero(): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Attribute Definition ID must be positive');

        new AttributeDefinitionId(0);
    }

    public function test_throws_exception_for_negative_id(): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Attribute Definition ID must be positive');

        new AttributeDefinitionId(-1);
    }

    public function test_equals_returns_true_for_same_value(): void
    {
        $id1 = new AttributeDefinitionId(42);
        $id2 = new AttributeDefinitionId(42);

        $this->assertTrue($id1->equals($id2));
        $this->assertTrue($id2->equals($id1));
    }

    public function test_equals_returns_false_for_different_values(): void
    {
        $id1 = new AttributeDefinitionId(42);
        $id2 = new AttributeDefinitionId(43);

        $this->assertFalse($id1->equals($id2));
        $this->assertFalse($id2->equals($id1));
    }

    public function test_to_string_returns_string_representation(): void
    {
        $id = new AttributeDefinitionId(123);

        $this->assertSame('123', (string) $id);
        $this->assertSame('123', $id->__toString());
    }

    public function test_value_object_is_immutable(): void
    {
        $id = new AttributeDefinitionId(42);
        $originalValue = $id->value();

        // Multiple calls should return same value
        $id->value();
        $id->value();

        $this->assertSame($originalValue, $id->value());
    }
}
