<?php
declare(strict_types=1);

namespace SagaManager\Application\DTO;

use SagaManager\Domain\Exception\ValidationException;

/**
 * Create Entity Request DTO
 *
 * Input validation for entity creation.
 * Validates before passing to domain layer.
 */
final readonly class CreateEntityRequest
{
    public function __construct(
        public int $sagaId,
        public string $type,
        public string $canonicalName,
        public string $slug,
        public ?int $importanceScore = null,
        public ?int $wpPostId = null
    ) {
        $this->validate();
    }

    /**
     * Create from array (e.g., from HTTP request)
     *
     * @param array<string, mixed> $data
     * @throws ValidationException
     */
    public static function fromArray(array $data): self
    {
        return new self(
            sagaId: (int) ($data['saga_id'] ?? throw new ValidationException('saga_id is required')),
            type: (string) ($data['type'] ?? throw new ValidationException('type is required')),
            canonicalName: (string) ($data['canonical_name'] ?? throw new ValidationException('canonical_name is required')),
            slug: (string) ($data['slug'] ?? throw new ValidationException('slug is required')),
            importanceScore: isset($data['importance_score']) ? (int) $data['importance_score'] : null,
            wpPostId: isset($data['wp_post_id']) ? (int) $data['wp_post_id'] : null
        );
    }

    private function validate(): void
    {
        if ($this->sagaId <= 0) {
            throw new ValidationException('saga_id must be positive');
        }

        if (empty(trim($this->canonicalName))) {
            throw new ValidationException('canonical_name cannot be empty');
        }

        if (empty(trim($this->slug))) {
            throw new ValidationException('slug cannot be empty');
        }

        $validTypes = ['character', 'location', 'event', 'faction', 'artifact', 'concept'];
        if (!in_array($this->type, $validTypes, true)) {
            throw new ValidationException(
                sprintf('type must be one of: %s', implode(', ', $validTypes))
            );
        }

        if ($this->importanceScore !== null && ($this->importanceScore < 0 || $this->importanceScore > 100)) {
            throw new ValidationException('importance_score must be between 0 and 100');
        }
    }
}
