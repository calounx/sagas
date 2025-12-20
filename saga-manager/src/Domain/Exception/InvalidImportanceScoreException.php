<?php
declare(strict_types=1);

namespace SagaManager\Domain\Exception;

/**
 * Exception thrown when importance score is invalid (not in 0-100 range)
 */
class InvalidImportanceScoreException extends ValidationException
{
}
