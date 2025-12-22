<?php
declare(strict_types=1);

namespace SagaManager\Application\UseCase\UpdateRelationship;

use SagaManager\Application\Command\CommandInterface;

/**
 * Update Relationship Command
 */
final readonly class UpdateRelationshipCommand implements CommandInterface
{
    public function __construct(
        public int $id,
        public ?string $relationshipType = null,
        public ?int $strength = null,
        public ?string $validFrom = null,
        public ?string $validUntil = null,
        public ?array $metadata = null,
        public bool $clearValidFrom = false,
        public bool $clearValidUntil = false,
        public bool $clearMetadata = false
    ) {}
}
