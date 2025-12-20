<?php
declare(strict_types=1);

namespace SagaManagerCore\Application\DTO;

use SagaManagerCore\Domain\Entity\SagaEntity;

/**
 * Data Transfer Object for entity data
 */
readonly class EntityDTO
{
    public function __construct(
        public ?int $id,
        public int $sagaId,
        public string $type,
        public string $typeLabel,
        public string $canonicalName,
        public string $slug,
        public int $importanceScore,
        public ?string $embeddingHash,
        public ?int $wpPostId,
        public string $createdAt,
        public string $updatedAt
    ) {
    }

    public static function fromEntity(SagaEntity $entity): self
    {
        return new self(
            id: $entity->getId()?->value(),
            sagaId: $entity->getSagaId()->value(),
            type: $entity->getType()->value,
            typeLabel: $entity->getType()->label(),
            canonicalName: $entity->getCanonicalName(),
            slug: $entity->getSlug(),
            importanceScore: $entity->getImportanceScore()->value(),
            embeddingHash: $entity->getEmbeddingHash(),
            wpPostId: $entity->getWpPostId(),
            createdAt: $entity->getCreatedAt()->format('c'),
            updatedAt: $entity->getUpdatedAt()->format('c')
        );
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'saga_id' => $this->sagaId,
            'type' => $this->type,
            'type_label' => $this->typeLabel,
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
