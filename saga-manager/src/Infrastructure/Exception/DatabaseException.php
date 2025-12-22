<?php
declare(strict_types=1);

namespace SagaManager\Infrastructure\Exception;

/**
 * Exception thrown when a database operation fails
 *
 * This exception is part of the Infrastructure layer and represents
 * database-specific errors (connection failures, query errors, etc.)
 */
class DatabaseException extends \RuntimeException
{
}
