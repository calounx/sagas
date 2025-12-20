<?php
declare(strict_types=1);

namespace SagaManager\Tests\Unit\Domain\Entity;

use PHPUnit\Framework\TestCase;
use SagaManager\Domain\Entity\EntityId;
use SagaManager\Domain\Exception\ValidationException;

/**
 * Unit tests for EntityId value object
 *
 * @covers \SagaManager\Domain\Entity\EntityId
 */
final class EntityIdTest extends TestCase
{
    public function test_constructs_with_valid_positive_id(): void
    {
        $id = new EntityId(1);

        $this->assertSame(1, $id->value());
    }

    public function test_constructs_with_large_id(): void
    {
        $largeId = 999999999;
        $id = new EntityId($largeId);

        $this->assertSame($largeId, $id->value());
    }

    public function test_throws_exception_for_zero_id(): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Entity ID must be positive');

        new EntityId(0);
    }

    public function test_throws_exception_for_negative_id(): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Entity ID must be positive');

        new EntityId(-1);
    }

    public function test_equals_returns_true_for_same_value(): void
    {
        $id1 = new EntityId(42);
        $id2 = new EntityId(42);

        $this->assertTrue($id1->equals($id2));
        $this->assertTrue($id2->equals($id1));
    }

    public function test_equals_returns_false_for_different_values(): void
    {
        $id1 = new EntityId(42);
        $id2 = new EntityId(43);

        $this->assertFalse($id1->equals($id2));
        $this->assertFalse($id2->equals($id1));
    }

    public function test_to_string_returns_string_representation(): void
    {
        $id = new EntityId(123);

        $this->assertSame('123', (string) $id);
        $this->assertSame('123', $id->__toString());
    }

    public function test_value_object_is_immutable(): void
    {
        $id = new EntityId(100);
        $originalValue = $id->value();

        // Attempt to get value multiple times
        $id->value();
        $id->value();

        $this->assertSame($originalValue, $id->value());
    }

    public function test_different_instances_with_same_value_are_equal(): void
    {
        $id1 = new EntityId(5);
        $id2 = new EntityId(5);

        $this->assertNotSame($id1, $id2); // Different objects
        $this->assertTrue($id1->equals($id2)); // But equal values
    }
}
