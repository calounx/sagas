<?php
declare(strict_types=1);

namespace SagaManagerCore\Application\DTO;

use SagaManagerCore\Domain\Exception\ValidationException;

/**
 * Request DTO for creating a new entity
 */
readonly class CreateEntityRequest
{
    public function __construct(
        public int $sagaId,
        public string $type,
        public string $canonicalName,
        public ?string $slug = null,
        public int $importanceScore = 50
    ) {
        $this->validate();
    }

    public static function fromArray(array $data): self
    {
        return new self(
            sagaId: (int) ($data['saga_id'] ?? 0),
            type: (string) ($data['type'] ?? ''),
            canonicalName: (string) ($data['canonical_name'] ?? ''),
            slug: isset($data['slug']) ? (string) $data['slug'] : null,
            importanceScore: (int) ($data['importance_score'] ?? 50)
        );
    }

    private function validate(): void
    {
        if ($this->sagaId <= 0) {
            throw new ValidationException('Saga ID must be a positive integer');
        }

        if (empty($this->type)) {
            throw new ValidationException('Entity type is required');
        }

        if (empty($this->canonicalName)) {
            throw new ValidationException('Canonical name is required');
        }

        if (strlen($this->canonicalName) > 255) {
            throw new ValidationException('Canonical name cannot exceed 255 characters');
        }

        if ($this->importanceScore < 0 || $this->importanceScore > 100) {
            throw new ValidationException('Importance score must be between 0 and 100');
        }
    }
}
