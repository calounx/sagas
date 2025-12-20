<?php
declare(strict_types=1);

namespace SagaManager\Tests\Unit\Domain\Entity;

use PHPUnit\Framework\TestCase;
use SagaManager\Domain\Entity\SagaId;
use SagaManager\Domain\Exception\ValidationException;

/**
 * Unit tests for SagaId value object
 *
 * @covers \SagaManager\Domain\Entity\SagaId
 */
final class SagaIdTest extends TestCase
{
    public function test_constructs_with_valid_positive_id(): void
    {
        $id = new SagaId(1);

        $this->assertSame(1, $id->value());
    }

    public function test_constructs_with_large_id(): void
    {
        $largeId = 999999999;
        $id = new SagaId($largeId);

        $this->assertSame($largeId, $id->value());
    }

    public function test_throws_exception_for_zero_id(): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Saga ID must be positive');

        new SagaId(0);
    }

    public function test_throws_exception_for_negative_id(): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Saga ID must be positive');

        new SagaId(-1);
    }

    public function test_equals_returns_true_for_same_value(): void
    {
        $id1 = new SagaId(10);
        $id2 = new SagaId(10);

        $this->assertTrue($id1->equals($id2));
        $this->assertTrue($id2->equals($id1));
    }

    public function test_equals_returns_false_for_different_values(): void
    {
        $id1 = new SagaId(10);
        $id2 = new SagaId(20);

        $this->assertFalse($id1->equals($id2));
        $this->assertFalse($id2->equals($id1));
    }

    public function test_to_string_returns_string_representation(): void
    {
        $id = new SagaId(456);

        $this->assertSame('456', (string) $id);
        $this->assertSame('456', $id->__toString());
    }

    public function test_value_object_is_immutable(): void
    {
        $id = new SagaId(50);
        $originalValue = $id->value();

        // Attempt to get value multiple times
        $id->value();
        $id->value();

        $this->assertSame($originalValue, $id->value());
    }

    public function test_different_instances_with_same_value_are_equal(): void
    {
        $id1 = new SagaId(7);
        $id2 = new SagaId(7);

        $this->assertNotSame($id1, $id2); // Different objects
        $this->assertTrue($id1->equals($id2)); // But equal values
    }
}
