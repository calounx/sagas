<?php
declare(strict_types=1);

namespace SagaManagerCore\Application\Command;

/**
 * Interface for command handlers
 */
interface CommandHandlerInterface
{
    public function handle(CommandInterface $command): mixed;
}
