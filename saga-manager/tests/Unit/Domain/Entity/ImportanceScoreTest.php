<?php
declare(strict_types=1);

namespace SagaManager\Tests\Unit\Domain\Entity;

use PHPUnit\Framework\TestCase;
use SagaManager\Domain\Entity\ImportanceScore;
use SagaManager\Domain\Exception\InvalidImportanceScoreException;

/**
 * Unit tests for ImportanceScore value object
 *
 * @covers \SagaManager\Domain\Entity\ImportanceScore
 */
final class ImportanceScoreTest extends TestCase
{
    public function test_constructs_with_valid_score(): void
    {
        $score = new ImportanceScore(50);

        $this->assertSame(50, $score->value());
    }

    public function test_constructs_with_minimum_score(): void
    {
        $score = new ImportanceScore(0);

        $this->assertSame(0, $score->value());
    }

    public function test_constructs_with_maximum_score(): void
    {
        $score = new ImportanceScore(100);

        $this->assertSame(100, $score->value());
    }

    public function test_throws_exception_for_score_below_minimum(): void
    {
        $this->expectException(InvalidImportanceScoreException::class);
        $this->expectExceptionMessage('Importance score must be between 0 and 100, got -1');

        new ImportanceScore(-1);
    }

    public function test_throws_exception_for_score_above_maximum(): void
    {
        $this->expectException(InvalidImportanceScoreException::class);
        $this->expectExceptionMessage('Importance score must be between 0 and 100, got 101');

        new ImportanceScore(101);
    }

    public function test_default_score_is_fifty(): void
    {
        $score = ImportanceScore::default();

        $this->assertSame(50, $score->value());
    }

    public function test_is_high_importance_returns_true_for_score_75_and_above(): void
    {
        $score75 = new ImportanceScore(75);
        $score80 = new ImportanceScore(80);
        $score100 = new ImportanceScore(100);

        $this->assertTrue($score75->isHighImportance());
        $this->assertTrue($score80->isHighImportance());
        $this->assertTrue($score100->isHighImportance());
    }

    public function test_is_high_importance_returns_false_for_score_below_75(): void
    {
        $score0 = new ImportanceScore(0);
        $score50 = new ImportanceScore(50);
        $score74 = new ImportanceScore(74);

        $this->assertFalse($score0->isHighImportance());
        $this->assertFalse($score50->isHighImportance());
        $this->assertFalse($score74->isHighImportance());
    }

    public function test_is_low_importance_returns_true_for_score_25_and_below(): void
    {
        $score0 = new ImportanceScore(0);
        $score10 = new ImportanceScore(10);
        $score25 = new ImportanceScore(25);

        $this->assertTrue($score0->isLowImportance());
        $this->assertTrue($score10->isLowImportance());
        $this->assertTrue($score25->isLowImportance());
    }

    public function test_is_low_importance_returns_false_for_score_above_25(): void
    {
        $score26 = new ImportanceScore(26);
        $score50 = new ImportanceScore(50);
        $score100 = new ImportanceScore(100);

        $this->assertFalse($score26->isLowImportance());
        $this->assertFalse($score50->isLowImportance());
        $this->assertFalse($score100->isLowImportance());
    }

    public function test_equals_returns_true_for_same_value(): void
    {
        $score1 = new ImportanceScore(60);
        $score2 = new ImportanceScore(60);

        $this->assertTrue($score1->equals($score2));
        $this->assertTrue($score2->equals($score1));
    }

    public function test_equals_returns_false_for_different_values(): void
    {
        $score1 = new ImportanceScore(60);
        $score2 = new ImportanceScore(61);

        $this->assertFalse($score1->equals($score2));
        $this->assertFalse($score2->equals($score1));
    }

    public function test_to_string_returns_string_representation(): void
    {
        $score = new ImportanceScore(85);

        $this->assertSame('85', (string) $score);
        $this->assertSame('85', $score->__toString());
    }

    public function test_value_object_is_immutable(): void
    {
        $score = new ImportanceScore(42);
        $originalValue = $score->value();

        // Attempt to get value multiple times
        $score->value();
        $score->value();

        $this->assertSame($originalValue, $score->value());
    }

    /**
     * @dataProvider boundaryScoreProvider
     */
    public function test_boundary_values(int $scoreValue, bool $expectedHigh, bool $expectedLow): void
    {
        $score = new ImportanceScore($scoreValue);

        $this->assertSame($expectedHigh, $score->isHighImportance());
        $this->assertSame($expectedLow, $score->isLowImportance());
    }

    public static function boundaryScoreProvider(): array
    {
        return [
            'minimum (0)' => [0, false, true],
            'low boundary (25)' => [25, false, true],
            'just above low (26)' => [26, false, false],
            'middle (50)' => [50, false, false],
            'just below high (74)' => [74, false, false],
            'high boundary (75)' => [75, true, false],
            'maximum (100)' => [100, true, false],
        ];
    }
}
