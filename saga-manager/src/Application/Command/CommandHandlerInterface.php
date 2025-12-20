<?php
declare(strict_types=1);

namespace SagaManager\Application\Command;

/**
 * Command Handler Interface (CQRS Pattern)
 *
 * Defines the contract for command execution.
 * Each command should have one corresponding handler.
 *
 * @template TCommand of CommandInterface
 * @template TResult
 */
interface CommandHandlerInterface
{
    /**
     * Execute the command
     *
     * @param TCommand $command
     * @return TResult
     * @throws \SagaManager\Domain\Exception\SagaException
     */
    public function handle(CommandInterface $command): mixed;
}
