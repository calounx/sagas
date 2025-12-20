<?php
declare(strict_types=1);

namespace SagaManagerCore\Infrastructure\Database\Exception;

/**
 * Base interface for all database exceptions
 *
 * @package SagaManagerCore\Infrastructure\Database\Exception
 */
interface DatabaseExceptionInterface extends \Throwable
{
    /**
     * Get the SQL query that caused the exception (if available)
     */
    public function getSql(): ?string;

    /**
     * Get parameter bindings (if available)
     *
     * @return array<mixed>
     */
    public function getBindings(): array;

    /**
     * Get the database error code (if available)
     */
    public function getErrorCode(): ?string;
}
