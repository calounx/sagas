<?php
declare(strict_types=1);

namespace SagaManager\Application\UseCase\CreateRelationship;

use SagaManager\Application\Command\CommandInterface;

/**
 * Create Relationship Command
 */
final readonly class CreateRelationshipCommand implements CommandInterface
{
    public function __construct(
        public int $sourceEntityId,
        public int $targetEntityId,
        public string $relationshipType,
        public ?int $strength = null,
        public ?string $validFrom = null,
        public ?string $validUntil = null,
        public ?array $metadata = null
    ) {}
}
