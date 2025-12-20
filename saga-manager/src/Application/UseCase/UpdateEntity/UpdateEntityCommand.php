<?php
declare(strict_types=1);

namespace SagaManager\Application\UseCase\UpdateEntity;

use SagaManager\Application\Command\CommandInterface;

/**
 * Update Entity Command
 *
 * Command to update an existing saga entity.
 * Immutable command object following CQRS pattern.
 */
final readonly class UpdateEntityCommand implements CommandInterface
{
    public function __construct(
        public int $entityId,
        public ?string $canonicalName = null,
        public ?string $slug = null,
        public ?int $importanceScore = null
    ) {
    }

    public function hasChanges(): bool
    {
        return $this->canonicalName !== null
            || $this->slug !== null
            || $this->importanceScore !== null;
    }
}
