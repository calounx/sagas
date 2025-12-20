<?php
declare(strict_types=1);

namespace SagaManager\Application\DTO;

use SagaManager\Domain\Entity\SagaEntity;

/**
 * Entity Data Transfer Object
 *
 * Immutable representation of an entity for API responses.
 * Decouples domain models from API layer.
 */
final readonly class EntityDTO
{
    public function __construct(
        public int $id,
        public int $sagaId,
        public string $type,
        public string $canonicalName,
        public string $slug,
        public int $importanceScore,
        public ?string $embeddingHash,
        public ?int $wpPostId,
        public string $createdAt,
        public string $updatedAt
    ) {
    }

    /**
     * Create DTO from domain entity
     */
    public static function fromEntity(SagaEntity $entity): self
    {
        $id = $entity->getId();
        if ($id === null) {
            throw new \LogicException('Cannot create DTO from entity without ID');
        }

        return new self(
            id: $id->value(),
            sagaId: $entity->getSagaId()->value(),
            type: $entity->getType()->value,
            canonicalName: $entity->getCanonicalName(),
            slug: $entity->getSlug(),
            importanceScore: $entity->getImportanceScore()->value(),
            embeddingHash: $entity->getEmbeddingHash(),
            wpPostId: $entity->getWpPostId(),
            createdAt: $entity->getCreatedAt()->format(\DateTimeInterface::ATOM),
            updatedAt: $entity->getUpdatedAt()->format(\DateTimeInterface::ATOM)
        );
    }

    /**
     * Convert to array for JSON serialization
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'saga_id' => $this->sagaId,
            'type' => $this->type,
            'canonical_name' => $this->canonicalName,
            'slug' => $this->slug,
            'importance_score' => $this->importanceScore,
            'embedding_hash' => $this->embeddingHash,
            'wp_post_id' => $this->wpPostId,
            'created_at' => $this->createdAt,
            'updated_at' => $this->updatedAt,
        ];
    }
}
