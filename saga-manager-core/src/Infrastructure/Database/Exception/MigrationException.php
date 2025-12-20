<?php
declare(strict_types=1);

namespace SagaManagerCore\Infrastructure\Database\Exception;

/**
 * Exception thrown when migration operations fail
 *
 * @package SagaManagerCore\Infrastructure\Database\Exception
 */
class MigrationException extends \RuntimeException implements DatabaseExceptionInterface
{
    private ?string $sql = null;
    /** @var array<mixed> */
    private array $bindings = [];
    private ?string $errorCode = null;
    private ?string $migrationName = null;

    public static function migrationFailed(string $name, string $error, ?\Throwable $previous = null): self
    {
        $exception = new self(
            sprintf('Migration [%s] failed: %s', $name, $error),
            0,
            $previous
        );
        $exception->migrationName = $name;
        return $exception;
    }

    public static function rollbackFailed(string $name, string $error, ?\Throwable $previous = null): self
    {
        $exception = new self(
            sprintf('Rollback of migration [%s] failed: %s', $name, $error),
            0,
            $previous
        );
        $exception->migrationName = $name;
        return $exception;
    }

    public static function migrationNotFound(string $name): self
    {
        $exception = new self(
            sprintf('Migration [%s] not found', $name)
        );
        $exception->migrationName = $name;
        return $exception;
    }

    public static function migrationAlreadyRan(string $name): self
    {
        $exception = new self(
            sprintf('Migration [%s] has already been executed', $name)
        );
        $exception->migrationName = $name;
        return $exception;
    }

    public static function migrationNotRan(string $name): self
    {
        $exception = new self(
            sprintf('Migration [%s] has not been executed and cannot be rolled back', $name)
        );
        $exception->migrationName = $name;
        return $exception;
    }

    public static function invalidMigration(string $name, string $reason): self
    {
        $exception = new self(
            sprintf('Invalid migration [%s]: %s', $name, $reason)
        );
        $exception->migrationName = $name;
        return $exception;
    }

    public static function loadFailed(string $path, string $error): self
    {
        return new self(
            sprintf('Failed to load migrations from [%s]: %s', $path, $error)
        );
    }

    public static function generateFailed(string $name, string $error): self
    {
        $exception = new self(
            sprintf('Failed to generate migration [%s]: %s', $name, $error)
        );
        $exception->migrationName = $name;
        return $exception;
    }

    public function getMigrationName(): ?string
    {
        return $this->migrationName;
    }

    public function getSql(): ?string
    {
        return $this->sql;
    }

    public function getBindings(): array
    {
        return $this->bindings;
    }

    public function getErrorCode(): ?string
    {
        return $this->errorCode;
    }

    public function withSql(string $sql): self
    {
        $this->sql = $sql;
        return $this;
    }
}
