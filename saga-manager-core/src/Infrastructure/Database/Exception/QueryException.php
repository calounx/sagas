<?php

declare(strict_types=1);

namespace SagaManagerCore\Infrastructure\Database\Exception;

use SagaManagerCore\Domain\Exception\DatabaseException;

/**
 * Exception thrown when a database query fails
 *
 * Contains query context for debugging while protecting sensitive data.
 */
class QueryException extends DatabaseException
{
    private string $sql;
    /** @var array<mixed> */
    private array $bindings;
    private ?string $sqlState;
    private ?int $driverErrorCode;

    /**
     * @param string $message Error message
     * @param string $sql The SQL query (may be truncated for security)
     * @param array<mixed> $bindings Parameter bindings
     * @param string|null $sqlState SQL state code
     * @param int|null $driverErrorCode Driver-specific error code
     * @param \Throwable|null $previous Previous exception
     */
    public function __construct(
        string $message,
        string $sql = '',
        array $bindings = [],
        ?string $sqlState = null,
        ?int $driverErrorCode = null,
        ?\Throwable $previous = null
    ) {
        parent::__construct($message, 0, $previous);
        $this->sql = $this->sanitizeSql($sql);
        $this->bindings = $this->sanitizeBindings($bindings);
        $this->sqlState = $sqlState;
        $this->driverErrorCode = $driverErrorCode;
    }

    /**
     * Get the SQL query (sanitized)
     */
    public function getSql(): string
    {
        return $this->sql;
    }

    /**
     * Get the parameter bindings (sanitized)
     *
     * @return array<mixed>
     */
    public function getBindings(): array
    {
        return $this->bindings;
    }

    /**
     * Get the SQL state code
     */
    public function getSqlState(): ?string
    {
        return $this->sqlState;
    }

    /**
     * Get the driver-specific error code
     */
    public function getDriverErrorCode(): ?int
    {
        return $this->driverErrorCode;
    }

    /**
     * Check if this is a duplicate key error
     */
    public function isDuplicateKey(): bool
    {
        return $this->driverErrorCode === 1062
            || ($this->sqlState !== null && str_starts_with($this->sqlState, '23'));
    }

    /**
     * Check if this is a foreign key constraint error
     */
    public function isForeignKeyViolation(): bool
    {
        return $this->driverErrorCode === 1451
            || $this->driverErrorCode === 1452;
    }

    /**
     * Check if this is a deadlock error (retry recommended)
     */
    public function isDeadlock(): bool
    {
        return $this->driverErrorCode === 1213
            || $this->sqlState === '40001';
    }

    /**
     * Check if this is a lock timeout error (retry recommended)
     */
    public function isLockTimeout(): bool
    {
        return $this->driverErrorCode === 1205;
    }

    /**
     * Check if the error is retryable
     */
    public function isRetryable(): bool
    {
        return $this->isDeadlock() || $this->isLockTimeout();
    }

    /**
     * Create exception for syntax error
     */
    public static function syntaxError(string $sql, string $error): self
    {
        return new self(
            'SQL syntax error: ' . $error,
            $sql,
            [],
            '42000',
            1064
        );
    }

    /**
     * Create exception for table not found
     */
    public static function tableNotFound(string $table): self
    {
        return new self(
            sprintf("Table '%s' doesn't exist", $table),
            '',
            [],
            '42S02',
            1146
        );
    }

    /**
     * Create exception for column not found
     */
    public static function columnNotFound(string $column): self
    {
        return new self(
            sprintf("Unknown column '%s'", $column),
            '',
            [],
            '42S22',
            1054
        );
    }

    /**
     * Create exception from WordPress wpdb error
     */
    public static function fromWpdb(string $error, string $sql = ''): self
    {
        $driverCode = null;
        $sqlState = null;

        // Parse common MySQL/MariaDB error codes
        if (preg_match('/Duplicate entry .+ for key/i', $error)) {
            $driverCode = 1062;
            $sqlState = '23000';
        } elseif (preg_match('/foreign key constraint fails/i', $error)) {
            $driverCode = 1452;
            $sqlState = '23000';
        } elseif (preg_match('/Deadlock found/i', $error)) {
            $driverCode = 1213;
            $sqlState = '40001';
        }

        return new self(
            'WordPress database query error: ' . $error,
            $sql,
            [],
            $sqlState,
            $driverCode
        );
    }

    /**
     * Sanitize SQL for logging (truncate and redact sensitive data)
     */
    private function sanitizeSql(string $sql): string
    {
        $maxLength = 500;

        if (strlen($sql) > $maxLength) {
            return substr($sql, 0, $maxLength) . '... [TRUNCATED]';
        }

        return $sql;
    }

    /**
     * Sanitize bindings for logging (redact sensitive values)
     *
     * @param array<mixed> $bindings
     * @return array<mixed>
     */
    private function sanitizeBindings(array $bindings): array
    {
        $sensitiveKeys = ['password', 'secret', 'token', 'key', 'auth'];
        $sanitized = [];

        foreach ($bindings as $key => $value) {
            $keyLower = strtolower((string) $key);

            foreach ($sensitiveKeys as $sensitive) {
                if (str_contains($keyLower, $sensitive)) {
                    $sanitized[$key] = '[REDACTED]';
                    continue 2;
                }
            }

            if (is_string($value) && strlen($value) > 100) {
                $sanitized[$key] = substr($value, 0, 100) . '... [TRUNCATED]';
            } else {
                $sanitized[$key] = $value;
            }
        }

        return $sanitized;
    }
}
