<?php
declare(strict_types=1);

namespace SagaManager\Application\Query;

/**
 * Query Handler Interface (CQRS Pattern)
 *
 * Defines the contract for query execution.
 * Each query should have one corresponding handler.
 *
 * @template TQuery of QueryInterface
 * @template TResult
 */
interface QueryHandlerInterface
{
    /**
     * Execute the query
     *
     * @param TQuery $query
     * @return TResult
     * @throws \SagaManager\Domain\Exception\SagaException
     */
    public function handle(QueryInterface $query): mixed;
}
