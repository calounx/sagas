<?php
declare(strict_types=1);

namespace SagaManager\Tests\Unit\Domain\Entity;

use PHPUnit\Framework\TestCase;
use SagaManager\Domain\Entity\RelationshipStrength;
use SagaManager\Domain\Exception\ValidationException;

class RelationshipStrengthTest extends TestCase
{
    public function test_can_create_with_valid_strength(): void
    {
        $strength = new RelationshipStrength(50);

        $this->assertSame(50, $strength->value());
    }

    public function test_can_create_with_minimum_value(): void
    {
        $strength = new RelationshipStrength(0);

        $this->assertSame(0, $strength->value());
    }

    public function test_can_create_with_maximum_value(): void
    {
        $strength = new RelationshipStrength(100);

        $this->assertSame(100, $strength->value());
    }

    public function test_throws_exception_for_negative_value(): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Relationship strength must be between 0 and 100');

        new RelationshipStrength(-1);
    }

    public function test_throws_exception_for_value_over_100(): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Relationship strength must be between 0 and 100');

        new RelationshipStrength(101);
    }

    public function test_is_strong_returns_true_for_70_and_above(): void
    {
        $this->assertTrue((new RelationshipStrength(70))->isStrong());
        $this->assertTrue((new RelationshipStrength(85))->isStrong());
        $this->assertTrue((new RelationshipStrength(100))->isStrong());
    }

    public function test_is_strong_returns_false_for_below_70(): void
    {
        $this->assertFalse((new RelationshipStrength(69))->isStrong());
        $this->assertFalse((new RelationshipStrength(50))->isStrong());
    }

    public function test_is_weak_returns_true_for_30_and_below(): void
    {
        $this->assertTrue((new RelationshipStrength(30))->isWeak());
        $this->assertTrue((new RelationshipStrength(15))->isWeak());
        $this->assertTrue((new RelationshipStrength(0))->isWeak());
    }

    public function test_is_weak_returns_false_for_above_30(): void
    {
        $this->assertFalse((new RelationshipStrength(31))->isWeak());
        $this->assertFalse((new RelationshipStrength(50))->isWeak());
    }

    public function test_is_moderate_returns_true_for_between_31_and_69(): void
    {
        $this->assertTrue((new RelationshipStrength(31))->isModerate());
        $this->assertTrue((new RelationshipStrength(50))->isModerate());
        $this->assertTrue((new RelationshipStrength(69))->isModerate());
    }

    public function test_is_moderate_returns_false_for_weak_or_strong(): void
    {
        $this->assertFalse((new RelationshipStrength(30))->isModerate());
        $this->assertFalse((new RelationshipStrength(70))->isModerate());
    }

    public function test_label_returns_correct_string(): void
    {
        $this->assertSame('Weak', (new RelationshipStrength(20))->label());
        $this->assertSame('Moderate', (new RelationshipStrength(50))->label());
        $this->assertSame('Strong', (new RelationshipStrength(80))->label());
    }

    public function test_equals_returns_true_for_same_value(): void
    {
        $strength1 = new RelationshipStrength(75);
        $strength2 = new RelationshipStrength(75);

        $this->assertTrue($strength1->equals($strength2));
    }

    public function test_equals_returns_false_for_different_value(): void
    {
        $strength1 = new RelationshipStrength(75);
        $strength2 = new RelationshipStrength(25);

        $this->assertFalse($strength1->equals($strength2));
    }

    public function test_default_creates_moderate_strength(): void
    {
        $strength = RelationshipStrength::default();

        $this->assertSame(50, $strength->value());
        $this->assertTrue($strength->isModerate());
    }

    public function test_strong_creates_strong_strength(): void
    {
        $strength = RelationshipStrength::strong();

        $this->assertSame(85, $strength->value());
        $this->assertTrue($strength->isStrong());
    }

    public function test_weak_creates_weak_strength(): void
    {
        $strength = RelationshipStrength::weak();

        $this->assertSame(15, $strength->value());
        $this->assertTrue($strength->isWeak());
    }
}
