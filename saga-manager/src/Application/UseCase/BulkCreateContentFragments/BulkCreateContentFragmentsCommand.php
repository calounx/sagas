<?php
declare(strict_types=1);

namespace SagaManager\Application\UseCase\BulkCreateContentFragments;

use SagaManager\Application\Command\CommandInterface;

/**
 * Bulk Create Content Fragments Command
 */
final readonly class BulkCreateContentFragmentsCommand implements CommandInterface
{
    /**
     * @param int $entityId
     * @param array<array{text: string, token_count?: int}> $fragments
     */
    public function __construct(
        public int $entityId,
        public array $fragments
    ) {}
}
