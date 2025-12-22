<?php
declare(strict_types=1);

namespace SagaManager\Tests\Unit\Domain\Entity;

use PHPUnit\Framework\TestCase;
use SagaManager\Domain\Entity\DataType;

/**
 * Unit tests for DataType enum
 *
 * @covers \SagaManager\Domain\Entity\DataType
 */
final class DataTypeTest extends TestCase
{
    public function test_all_cases_exist(): void
    {
        $expected = ['string', 'int', 'float', 'bool', 'date', 'text', 'json'];
        $actual = array_map(fn(DataType $t) => $t->value, DataType::cases());

        $this->assertSame($expected, $actual);
    }

    /**
     * @dataProvider labelProvider
     */
    public function test_label_returns_correct_display_name(DataType $type, string $expectedLabel): void
    {
        $this->assertSame($expectedLabel, $type->label());
    }

    public static function labelProvider(): array
    {
        return [
            'string' => [DataType::STRING, 'String'],
            'int' => [DataType::INT, 'Integer'],
            'float' => [DataType::FLOAT, 'Decimal'],
            'bool' => [DataType::BOOL, 'Boolean'],
            'date' => [DataType::DATE, 'Date'],
            'text' => [DataType::TEXT, 'Long Text'],
            'json' => [DataType::JSON, 'JSON Object'],
        ];
    }

    /**
     * @dataProvider valueColumnProvider
     */
    public function test_get_value_column_returns_correct_column_name(DataType $type, string $expectedColumn): void
    {
        $this->assertSame($expectedColumn, $type->getValueColumn());
    }

    public static function valueColumnProvider(): array
    {
        return [
            'string' => [DataType::STRING, 'value_string'],
            'int' => [DataType::INT, 'value_int'],
            'float' => [DataType::FLOAT, 'value_float'],
            'bool' => [DataType::BOOL, 'value_bool'],
            'date' => [DataType::DATE, 'value_date'],
            'text' => [DataType::TEXT, 'value_text'],
            'json' => [DataType::JSON, 'value_json'],
        ];
    }

    /**
     * @dataProvider textSearchableProvider
     */
    public function test_is_text_searchable(DataType $type, bool $expected): void
    {
        $this->assertSame($expected, $type->isTextSearchable());
    }

    public static function textSearchableProvider(): array
    {
        return [
            'string is searchable' => [DataType::STRING, true],
            'text is searchable' => [DataType::TEXT, true],
            'int is not searchable' => [DataType::INT, false],
            'float is not searchable' => [DataType::FLOAT, false],
            'bool is not searchable' => [DataType::BOOL, false],
            'date is not searchable' => [DataType::DATE, false],
            'json is not searchable' => [DataType::JSON, false],
        ];
    }

    /**
     * @dataProvider wpdbFormatProvider
     */
    public function test_get_wpdb_format(DataType $type, string $expectedFormat): void
    {
        $this->assertSame($expectedFormat, $type->getWpdbFormat());
    }

    public static function wpdbFormatProvider(): array
    {
        return [
            'int uses %d' => [DataType::INT, '%d'],
            'bool uses %d' => [DataType::BOOL, '%d'],
            'float uses %f' => [DataType::FLOAT, '%f'],
            'string uses %s' => [DataType::STRING, '%s'],
            'text uses %s' => [DataType::TEXT, '%s'],
            'date uses %s' => [DataType::DATE, '%s'],
            'json uses %s' => [DataType::JSON, '%s'],
        ];
    }

    public function test_can_be_created_from_string(): void
    {
        $type = DataType::from('string');

        $this->assertSame(DataType::STRING, $type);
    }

    public function test_from_throws_on_invalid_value(): void
    {
        $this->expectException(\ValueError::class);

        DataType::from('invalid');
    }

    public function test_try_from_returns_null_on_invalid_value(): void
    {
        $type = DataType::tryFrom('invalid');

        $this->assertNull($type);
    }
}
