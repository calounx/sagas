<?php
declare(strict_types=1);

namespace SagaManager\Tests\Unit\Domain\Entity;

use PHPUnit\Framework\TestCase;
use SagaManager\Domain\Entity\TimelineEventId;
use SagaManager\Domain\Exception\ValidationException;

class TimelineEventIdTest extends TestCase
{
    public function test_can_create_with_valid_positive_integer(): void
    {
        $id = new TimelineEventId(123);

        $this->assertSame(123, $id->value());
    }

    public function test_throws_exception_for_zero(): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Timeline event ID must be positive');

        new TimelineEventId(0);
    }

    public function test_throws_exception_for_negative_value(): void
    {
        $this->expectException(ValidationException::class);

        new TimelineEventId(-5);
    }

    public function test_equals_returns_true_for_same_value(): void
    {
        $id1 = new TimelineEventId(42);
        $id2 = new TimelineEventId(42);

        $this->assertTrue($id1->equals($id2));
    }

    public function test_equals_returns_false_for_different_value(): void
    {
        $id1 = new TimelineEventId(42);
        $id2 = new TimelineEventId(99);

        $this->assertFalse($id1->equals($id2));
    }

    public function test_to_string_returns_string_value(): void
    {
        $id = new TimelineEventId(789);

        $this->assertSame('789', (string) $id);
    }
}
