<?php
declare(strict_types=1);

namespace SagaManager\Tests\Unit\Domain\Entity;

use PHPUnit\Framework\TestCase;
use SagaManager\Domain\Entity\TokenCount;
use SagaManager\Domain\Exception\ValidationException;

class TokenCountTest extends TestCase
{
    public function test_can_create_with_valid_count(): void
    {
        $count = new TokenCount(500);

        $this->assertSame(500, $count->value());
    }

    public function test_can_create_with_zero(): void
    {
        $count = new TokenCount(0);

        $this->assertSame(0, $count->value());
        $this->assertTrue($count->isEmpty());
    }

    public function test_can_create_with_maximum_value(): void
    {
        $count = new TokenCount(65535);

        $this->assertSame(65535, $count->value());
    }

    public function test_throws_exception_for_negative_value(): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Token count must be between 0 and 65535');

        new TokenCount(-1);
    }

    public function test_throws_exception_for_value_over_maximum(): void
    {
        $this->expectException(ValidationException::class);

        new TokenCount(65536);
    }

    public function test_is_empty_returns_true_for_zero(): void
    {
        $count = new TokenCount(0);

        $this->assertTrue($count->isEmpty());
    }

    public function test_is_empty_returns_false_for_non_zero(): void
    {
        $count = new TokenCount(10);

        $this->assertFalse($count->isEmpty());
    }

    public function test_is_large_returns_true_for_over_1000(): void
    {
        $count = new TokenCount(1001);

        $this->assertTrue($count->isLarge());
    }

    public function test_is_large_returns_false_for_1000_or_less(): void
    {
        $this->assertFalse((new TokenCount(1000))->isLarge());
        $this->assertFalse((new TokenCount(500))->isLarge());
    }

    public function test_zero_factory_method(): void
    {
        $count = TokenCount::zero();

        $this->assertSame(0, $count->value());
        $this->assertTrue($count->isEmpty());
    }

    public function test_equals_returns_true_for_same_value(): void
    {
        $count1 = new TokenCount(500);
        $count2 = new TokenCount(500);

        $this->assertTrue($count1->equals($count2));
    }

    public function test_equals_returns_false_for_different_value(): void
    {
        $count1 = new TokenCount(500);
        $count2 = new TokenCount(600);

        $this->assertFalse($count1->equals($count2));
    }

    public function test_to_string_returns_string_value(): void
    {
        $count = new TokenCount(1234);

        $this->assertSame('1234', (string) $count);
    }
}
