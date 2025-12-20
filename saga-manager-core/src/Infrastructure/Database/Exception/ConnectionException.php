<?php

declare(strict_types=1);

namespace SagaManagerCore\Infrastructure\Database\Exception;

use SagaManagerCore\Domain\Exception\DatabaseException;

/**
 * Exception thrown when database connection fails
 *
 * Covers connection establishment, loss, timeout, and authentication errors.
 */
class ConnectionException extends DatabaseException
{
    private ?string $sqlState;
    private ?int $driverErrorCode;

    public function __construct(
        string $message,
        ?string $sqlState = null,
        ?int $driverErrorCode = null,
        ?\Throwable $previous = null
    ) {
        parent::__construct($message, 0, $previous);
        $this->sqlState = $sqlState;
        $this->driverErrorCode = $driverErrorCode;
    }

    /**
     * Get the SQL state code (if available)
     */
    public function getSqlState(): ?string
    {
        return $this->sqlState;
    }

    /**
     * Get the driver-specific error code (if available)
     */
    public function getDriverErrorCode(): ?int
    {
        return $this->driverErrorCode;
    }

    /**
     * Create exception for connection refused
     */
    public static function refused(string $host, int $port): self
    {
        return new self(
            sprintf('Connection refused to %s:%d', $host, $port),
            'HY000',
            2002
        );
    }

    /**
     * Create exception for connection timeout
     */
    public static function timeout(float $seconds): self
    {
        return new self(
            sprintf('Connection timed out after %.2f seconds', $seconds),
            'HY000',
            2003
        );
    }

    /**
     * Create exception for authentication failure
     */
    public static function authenticationFailed(string $user): self
    {
        return new self(
            sprintf('Access denied for user "%s"', $user),
            '28000',
            1045
        );
    }

    /**
     * Create exception for lost connection
     */
    public static function lost(): self
    {
        return new self(
            'Server has gone away',
            'HY000',
            2006
        );
    }

    /**
     * Create exception from WordPress wpdb error
     */
    public static function fromWpdb(string $error): self
    {
        return new self(
            'WordPress database connection error: ' . $error
        );
    }
}
