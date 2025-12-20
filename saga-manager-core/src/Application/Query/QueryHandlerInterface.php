<?php
declare(strict_types=1);

namespace SagaManagerCore\Application\Query;

/**
 * Interface for query handlers
 */
interface QueryHandlerInterface
{
    public function handle(QueryInterface $query): mixed;
}
