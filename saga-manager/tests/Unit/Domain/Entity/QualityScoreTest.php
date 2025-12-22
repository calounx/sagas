<?php
declare(strict_types=1);

namespace SagaManager\Tests\Unit\Domain\Entity;

use PHPUnit\Framework\TestCase;
use SagaManager\Domain\Entity\QualityScore;
use SagaManager\Domain\Exception\ValidationException;

class QualityScoreTest extends TestCase
{
    public function test_can_create_with_valid_score(): void
    {
        $score = new QualityScore(75);

        $this->assertSame(75, $score->value());
    }

    public function test_can_create_with_minimum_score(): void
    {
        $score = new QualityScore(0);

        $this->assertSame(0, $score->value());
    }

    public function test_can_create_with_maximum_score(): void
    {
        $score = new QualityScore(100);

        $this->assertSame(100, $score->value());
    }

    public function test_throws_exception_for_negative_score(): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Quality score must be between 0 and 100');

        new QualityScore(-1);
    }

    public function test_throws_exception_for_score_over_100(): void
    {
        $this->expectException(ValidationException::class);

        new QualityScore(101);
    }

    public function test_is_excellent_returns_true_for_90_plus(): void
    {
        $this->assertTrue((new QualityScore(90))->isExcellent());
        $this->assertTrue((new QualityScore(95))->isExcellent());
        $this->assertTrue((new QualityScore(100))->isExcellent());
        $this->assertFalse((new QualityScore(89))->isExcellent());
    }

    public function test_is_good_returns_true_for_70_to_89(): void
    {
        $this->assertTrue((new QualityScore(70))->isGood());
        $this->assertTrue((new QualityScore(80))->isGood());
        $this->assertTrue((new QualityScore(89))->isGood());
        $this->assertFalse((new QualityScore(69))->isGood());
        $this->assertFalse((new QualityScore(90))->isGood());
    }

    public function test_is_fair_returns_true_for_50_to_69(): void
    {
        $this->assertTrue((new QualityScore(50))->isFair());
        $this->assertTrue((new QualityScore(60))->isFair());
        $this->assertTrue((new QualityScore(69))->isFair());
        $this->assertFalse((new QualityScore(49))->isFair());
        $this->assertFalse((new QualityScore(70))->isFair());
    }

    public function test_is_poor_returns_true_for_below_50(): void
    {
        $this->assertTrue((new QualityScore(0))->isPoor());
        $this->assertTrue((new QualityScore(25))->isPoor());
        $this->assertTrue((new QualityScore(49))->isPoor());
        $this->assertFalse((new QualityScore(50))->isPoor());
    }

    public function test_get_grade_returns_correct_grades(): void
    {
        $this->assertSame('A', (new QualityScore(95))->getGrade());
        $this->assertSame('B', (new QualityScore(75))->getGrade());
        $this->assertSame('C', (new QualityScore(55))->getGrade());
        $this->assertSame('D', (new QualityScore(35))->getGrade());
    }

    public function test_equals_returns_true_for_same_value(): void
    {
        $score1 = new QualityScore(75);
        $score2 = new QualityScore(75);

        $this->assertTrue($score1->equals($score2));
    }

    public function test_equals_returns_false_for_different_value(): void
    {
        $score1 = new QualityScore(75);
        $score2 = new QualityScore(80);

        $this->assertFalse($score1->equals($score2));
    }

    public function test_to_string_returns_string_value(): void
    {
        $score = new QualityScore(85);

        $this->assertSame('85', (string) $score);
    }
}
