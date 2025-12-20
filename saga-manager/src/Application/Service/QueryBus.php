<?php
declare(strict_types=1);

namespace SagaManager\Application\Service;

use SagaManager\Application\Query\QueryHandlerInterface;
use SagaManager\Application\Query\QueryInterface;

/**
 * Query Bus
 *
 * Dispatches queries to their respective handlers.
 * Implements the Query Bus pattern for decoupling.
 */
final class QueryBus
{
    /**
     * @var array<class-string<QueryInterface>, QueryHandlerInterface>
     */
    private array $handlers = [];

    /**
     * Register a query handler
     *
     * @template TQuery of QueryInterface
     * @param class-string<TQuery> $queryClass
     * @param QueryHandlerInterface<TQuery, mixed> $handler
     */
    public function register(string $queryClass, QueryHandlerInterface $handler): void
    {
        $this->handlers[$queryClass] = $handler;
    }

    /**
     * Dispatch a query to its handler
     *
     * @template TQuery of QueryInterface
     * @template TResult
     * @param TQuery $query
     * @return TResult
     * @throws \RuntimeException When no handler registered
     * @throws \SagaManager\Domain\Exception\SagaException
     */
    public function dispatch(QueryInterface $query): mixed
    {
        $queryClass = get_class($query);

        if (!isset($this->handlers[$queryClass])) {
            throw new \RuntimeException(
                sprintf('No handler registered for query: %s', $queryClass)
            );
        }

        return $this->handlers[$queryClass]->handle($query);
    }
}
