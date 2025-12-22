<?php
declare(strict_types=1);

namespace SagaManager\Tests\Unit\Domain\Entity;

use PHPUnit\Framework\TestCase;
use SagaManager\Domain\Entity\ContentFragmentId;
use SagaManager\Domain\Exception\ValidationException;

class ContentFragmentIdTest extends TestCase
{
    public function test_can_create_with_valid_positive_integer(): void
    {
        $id = new ContentFragmentId(123);

        $this->assertSame(123, $id->value());
    }

    public function test_throws_exception_for_zero(): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Content fragment ID must be positive');

        new ContentFragmentId(0);
    }

    public function test_throws_exception_for_negative_value(): void
    {
        $this->expectException(ValidationException::class);

        new ContentFragmentId(-5);
    }

    public function test_equals_returns_true_for_same_value(): void
    {
        $id1 = new ContentFragmentId(42);
        $id2 = new ContentFragmentId(42);

        $this->assertTrue($id1->equals($id2));
    }

    public function test_equals_returns_false_for_different_value(): void
    {
        $id1 = new ContentFragmentId(42);
        $id2 = new ContentFragmentId(99);

        $this->assertFalse($id1->equals($id2));
    }

    public function test_to_string_returns_string_value(): void
    {
        $id = new ContentFragmentId(789);

        $this->assertSame('789', (string) $id);
    }
}
