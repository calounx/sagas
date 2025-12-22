<?php
declare(strict_types=1);

namespace SagaManager\Domain\Entity;

/**
 * Validation Rule Value Object
 *
 * Encapsulates validation configuration for attribute definitions.
 * Supports regex patterns, numeric ranges, string lengths, and enum constraints.
 */
final readonly class ValidationRule
{
    /**
     * @param string|null $regex Regex pattern for string validation
     * @param int|float|null $min Minimum value (int for INT, float for FLOAT, int for minLength)
     * @param int|float|null $max Maximum value (int for INT, float for FLOAT, int for maxLength)
     * @param array<string>|null $enum Allowed values for enum constraint
     */
    private function __construct(
        private ?string $regex,
        private int|float|null $min,
        private int|float|null $max,
        private ?int $minLength,
        private ?int $maxLength,
        private ?array $enum
    ) {}

    /**
     * Create ValidationRule from array configuration
     *
     * Expected format:
     * {
     *   "regex": "/pattern/",
     *   "min": 0,
     *   "max": 100,
     *   "minLength": 1,
     *   "maxLength": 255,
     *   "enum": ["value1", "value2"]
     * }
     */
    public static function fromArray(?array $config): ?self
    {
        if ($config === null || empty($config)) {
            return null;
        }

        return new self(
            regex: $config['regex'] ?? null,
            min: $config['min'] ?? null,
            max: $config['max'] ?? null,
            minLength: isset($config['minLength']) ? (int) $config['minLength'] : null,
            maxLength: isset($config['maxLength']) ? (int) $config['maxLength'] : null,
            enum: $config['enum'] ?? null
        );
    }

    /**
     * Validate a value against this rule
     */
    public function validate(mixed $value, DataType $dataType): bool
    {
        if ($value === null) {
            return true; // Null handling is done by isRequired
        }

        // Regex validation (for STRING/TEXT types)
        if ($this->regex !== null && is_string($value)) {
            if (@preg_match($this->regex, $value) !== 1) {
                return false;
            }
        }

        // Enum validation
        if ($this->enum !== null) {
            if (!in_array($value, $this->enum, true)) {
                return false;
            }
        }

        // Numeric range validation
        if ($dataType === DataType::INT || $dataType === DataType::FLOAT) {
            if (is_numeric($value)) {
                if ($this->min !== null && $value < $this->min) {
                    return false;
                }
                if ($this->max !== null && $value > $this->max) {
                    return false;
                }
            }
        }

        // String length validation
        if (is_string($value) && ($dataType === DataType::STRING || $dataType === DataType::TEXT)) {
            $length = mb_strlen($value);
            if ($this->minLength !== null && $length < $this->minLength) {
                return false;
            }
            if ($this->maxLength !== null && $length > $this->maxLength) {
                return false;
            }
        }

        return true;
    }

    /**
     * Get validation error message for a failed value
     */
    public function getErrorMessage(mixed $value, DataType $dataType): ?string
    {
        if ($this->regex !== null && is_string($value)) {
            if (@preg_match($this->regex, $value) !== 1) {
                return sprintf('Value must match pattern: %s', $this->regex);
            }
        }

        if ($this->enum !== null && !in_array($value, $this->enum, true)) {
            return sprintf('Value must be one of: %s', implode(', ', $this->enum));
        }

        if (($dataType === DataType::INT || $dataType === DataType::FLOAT) && is_numeric($value)) {
            if ($this->min !== null && $value < $this->min) {
                return sprintf('Value must be at least %s', $this->min);
            }
            if ($this->max !== null && $value > $this->max) {
                return sprintf('Value must be at most %s', $this->max);
            }
        }

        if (is_string($value)) {
            $length = mb_strlen($value);
            if ($this->minLength !== null && $length < $this->minLength) {
                return sprintf('Value must be at least %d characters', $this->minLength);
            }
            if ($this->maxLength !== null && $length > $this->maxLength) {
                return sprintf('Value must be at most %d characters', $this->maxLength);
            }
        }

        return null;
    }

    /**
     * Convert to array for JSON serialization
     */
    public function toArray(): array
    {
        $config = [];

        if ($this->regex !== null) {
            $config['regex'] = $this->regex;
        }
        if ($this->min !== null) {
            $config['min'] = $this->min;
        }
        if ($this->max !== null) {
            $config['max'] = $this->max;
        }
        if ($this->minLength !== null) {
            $config['minLength'] = $this->minLength;
        }
        if ($this->maxLength !== null) {
            $config['maxLength'] = $this->maxLength;
        }
        if ($this->enum !== null) {
            $config['enum'] = $this->enum;
        }

        return $config;
    }

    /**
     * Convert to JSON string for database storage
     */
    public function toJson(): string
    {
        return json_encode($this->toArray(), JSON_THROW_ON_ERROR);
    }

    public function getRegex(): ?string
    {
        return $this->regex;
    }

    public function getMin(): int|float|null
    {
        return $this->min;
    }

    public function getMax(): int|float|null
    {
        return $this->max;
    }

    public function getMinLength(): ?int
    {
        return $this->minLength;
    }

    public function getMaxLength(): ?int
    {
        return $this->maxLength;
    }

    /**
     * @return array<string>|null
     */
    public function getEnum(): ?array
    {
        return $this->enum;
    }

    public function hasConstraints(): bool
    {
        return $this->regex !== null
            || $this->min !== null
            || $this->max !== null
            || $this->minLength !== null
            || $this->maxLength !== null
            || $this->enum !== null;
    }
}
