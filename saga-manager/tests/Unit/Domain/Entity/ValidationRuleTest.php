<?php
declare(strict_types=1);

namespace SagaManager\Tests\Unit\Domain\Entity;

use PHPUnit\Framework\TestCase;
use SagaManager\Domain\Entity\DataType;
use SagaManager\Domain\Entity\ValidationRule;

/**
 * Unit tests for ValidationRule value object
 *
 * @covers \SagaManager\Domain\Entity\ValidationRule
 */
final class ValidationRuleTest extends TestCase
{
    public function test_from_array_returns_null_for_null_config(): void
    {
        $rule = ValidationRule::fromArray(null);

        $this->assertNull($rule);
    }

    public function test_from_array_returns_null_for_empty_config(): void
    {
        $rule = ValidationRule::fromArray([]);

        $this->assertNull($rule);
    }

    public function test_from_array_creates_rule_with_regex(): void
    {
        $rule = ValidationRule::fromArray(['regex' => '/^[A-Z]+$/']);

        $this->assertNotNull($rule);
        $this->assertSame('/^[A-Z]+$/', $rule->getRegex());
    }

    public function test_from_array_creates_rule_with_numeric_range(): void
    {
        $rule = ValidationRule::fromArray(['min' => 0, 'max' => 100]);

        $this->assertNotNull($rule);
        $this->assertSame(0, $rule->getMin());
        $this->assertSame(100, $rule->getMax());
    }

    public function test_from_array_creates_rule_with_enum(): void
    {
        $rule = ValidationRule::fromArray(['enum' => ['red', 'green', 'blue']]);

        $this->assertNotNull($rule);
        $this->assertSame(['red', 'green', 'blue'], $rule->getEnum());
    }

    public function test_from_array_creates_rule_with_length_constraints(): void
    {
        $rule = ValidationRule::fromArray(['minLength' => 1, 'maxLength' => 255]);

        $this->assertNotNull($rule);
        $this->assertSame(1, $rule->getMinLength());
        $this->assertSame(255, $rule->getMaxLength());
    }

    public function test_validate_returns_true_for_null_value(): void
    {
        $rule = ValidationRule::fromArray(['min' => 0, 'max' => 100]);

        $this->assertTrue($rule->validate(null, DataType::INT));
    }

    public function test_validate_regex_passes_for_matching_value(): void
    {
        $rule = ValidationRule::fromArray(['regex' => '/^[A-Z]{3}$/']);

        $this->assertTrue($rule->validate('ABC', DataType::STRING));
    }

    public function test_validate_regex_fails_for_non_matching_value(): void
    {
        $rule = ValidationRule::fromArray(['regex' => '/^[A-Z]{3}$/']);

        $this->assertFalse($rule->validate('abc', DataType::STRING));
        $this->assertFalse($rule->validate('AB', DataType::STRING));
        $this->assertFalse($rule->validate('ABCD', DataType::STRING));
    }

    public function test_validate_enum_passes_for_valid_value(): void
    {
        $rule = ValidationRule::fromArray(['enum' => ['active', 'inactive', 'pending']]);

        $this->assertTrue($rule->validate('active', DataType::STRING));
        $this->assertTrue($rule->validate('pending', DataType::STRING));
    }

    public function test_validate_enum_fails_for_invalid_value(): void
    {
        $rule = ValidationRule::fromArray(['enum' => ['active', 'inactive']]);

        $this->assertFalse($rule->validate('unknown', DataType::STRING));
    }

    public function test_validate_int_range_passes_for_valid_value(): void
    {
        $rule = ValidationRule::fromArray(['min' => 1, 'max' => 10]);

        $this->assertTrue($rule->validate(1, DataType::INT));
        $this->assertTrue($rule->validate(5, DataType::INT));
        $this->assertTrue($rule->validate(10, DataType::INT));
    }

    public function test_validate_int_range_fails_for_out_of_range_value(): void
    {
        $rule = ValidationRule::fromArray(['min' => 1, 'max' => 10]);

        $this->assertFalse($rule->validate(0, DataType::INT));
        $this->assertFalse($rule->validate(11, DataType::INT));
    }

    public function test_validate_float_range_passes_for_valid_value(): void
    {
        $rule = ValidationRule::fromArray(['min' => 0.0, 'max' => 1.0]);

        $this->assertTrue($rule->validate(0.0, DataType::FLOAT));
        $this->assertTrue($rule->validate(0.5, DataType::FLOAT));
        $this->assertTrue($rule->validate(1.0, DataType::FLOAT));
    }

    public function test_validate_float_range_fails_for_out_of_range_value(): void
    {
        $rule = ValidationRule::fromArray(['min' => 0.0, 'max' => 1.0]);

        $this->assertFalse($rule->validate(-0.1, DataType::FLOAT));
        $this->assertFalse($rule->validate(1.1, DataType::FLOAT));
    }

    public function test_validate_string_length_passes_for_valid_length(): void
    {
        $rule = ValidationRule::fromArray(['minLength' => 3, 'maxLength' => 10]);

        $this->assertTrue($rule->validate('abc', DataType::STRING));
        $this->assertTrue($rule->validate('abcdefghij', DataType::STRING));
    }

    public function test_validate_string_length_fails_for_invalid_length(): void
    {
        $rule = ValidationRule::fromArray(['minLength' => 3, 'maxLength' => 10]);

        $this->assertFalse($rule->validate('ab', DataType::STRING));
        $this->assertFalse($rule->validate('abcdefghijk', DataType::STRING));
    }

    public function test_to_array_returns_correct_structure(): void
    {
        $rule = ValidationRule::fromArray([
            'regex' => '/^test$/',
            'min' => 0,
            'max' => 100,
            'minLength' => 1,
            'maxLength' => 50,
            'enum' => ['a', 'b'],
        ]);

        $array = $rule->toArray();

        $this->assertSame('/^test$/', $array['regex']);
        $this->assertSame(0, $array['min']);
        $this->assertSame(100, $array['max']);
        $this->assertSame(1, $array['minLength']);
        $this->assertSame(50, $array['maxLength']);
        $this->assertSame(['a', 'b'], $array['enum']);
    }

    public function test_to_json_returns_valid_json(): void
    {
        $rule = ValidationRule::fromArray(['min' => 0, 'max' => 100]);

        $json = $rule->toJson();
        $decoded = json_decode($json, true);

        $this->assertSame(0, $decoded['min']);
        $this->assertSame(100, $decoded['max']);
    }

    public function test_has_constraints_returns_true_when_constraints_exist(): void
    {
        $rule = ValidationRule::fromArray(['min' => 0]);

        $this->assertTrue($rule->hasConstraints());
    }

    public function test_get_error_message_returns_regex_error(): void
    {
        $rule = ValidationRule::fromArray(['regex' => '/^[A-Z]+$/']);

        $error = $rule->getErrorMessage('abc', DataType::STRING);

        $this->assertStringContainsString('pattern', $error);
    }

    public function test_get_error_message_returns_enum_error(): void
    {
        $rule = ValidationRule::fromArray(['enum' => ['yes', 'no']]);

        $error = $rule->getErrorMessage('maybe', DataType::STRING);

        $this->assertStringContainsString('one of', $error);
    }

    public function test_get_error_message_returns_min_error(): void
    {
        $rule = ValidationRule::fromArray(['min' => 10]);

        $error = $rule->getErrorMessage(5, DataType::INT);

        $this->assertStringContainsString('at least', $error);
    }

    public function test_get_error_message_returns_max_error(): void
    {
        $rule = ValidationRule::fromArray(['max' => 10]);

        $error = $rule->getErrorMessage(15, DataType::INT);

        $this->assertStringContainsString('at most', $error);
    }

    public function test_get_error_message_returns_null_for_valid_value(): void
    {
        $rule = ValidationRule::fromArray(['min' => 0, 'max' => 100]);

        $error = $rule->getErrorMessage(50, DataType::INT);

        $this->assertNull($error);
    }
}
