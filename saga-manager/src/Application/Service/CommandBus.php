<?php
declare(strict_types=1);

namespace SagaManager\Application\Service;

use SagaManager\Application\Command\CommandHandlerInterface;
use SagaManager\Application\Command\CommandInterface;

/**
 * Command Bus
 *
 * Dispatches commands to their respective handlers.
 * Implements the Command Bus pattern for decoupling.
 */
final class CommandBus
{
    /**
     * @var array<class-string<CommandInterface>, CommandHandlerInterface>
     */
    private array $handlers = [];

    /**
     * Register a command handler
     *
     * @template TCommand of CommandInterface
     * @param class-string<TCommand> $commandClass
     * @param CommandHandlerInterface<TCommand, mixed> $handler
     */
    public function register(string $commandClass, CommandHandlerInterface $handler): void
    {
        $this->handlers[$commandClass] = $handler;
    }

    /**
     * Dispatch a command to its handler
     *
     * @template TCommand of CommandInterface
     * @template TResult
     * @param TCommand $command
     * @return TResult
     * @throws \RuntimeException When no handler registered
     * @throws \SagaManager\Domain\Exception\SagaException
     */
    public function dispatch(CommandInterface $command): mixed
    {
        $commandClass = get_class($command);

        if (!isset($this->handlers[$commandClass])) {
            throw new \RuntimeException(
                sprintf('No handler registered for command: %s', $commandClass)
            );
        }

        return $this->handlers[$commandClass]->handle($command);
    }
}
