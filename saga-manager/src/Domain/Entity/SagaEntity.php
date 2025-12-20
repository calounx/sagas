<?php
declare(strict_types=1);

namespace SagaManager\Domain\Entity;

use SagaManager\Domain\Exception\ValidationException;

/**
 * Saga Entity Domain Model
 *
 * Core aggregate root for all saga entities (characters, locations, events, etc.)
 */
class SagaEntity
{
    private ?EntityId $id;
    private SagaId $sagaId;
    private EntityType $type;
    private string $canonicalName;
    private string $slug;
    private ImportanceScore $importanceScore;
    private ?string $embeddingHash;
    private ?int $wpPostId;
    private \DateTimeImmutable $createdAt;
    private \DateTimeImmutable $updatedAt;

    public function __construct(
        SagaId $sagaId,
        EntityType $type,
        string $canonicalName,
        string $slug,
        ?ImportanceScore $importanceScore = null,
        ?EntityId $id = null,
        ?string $embeddingHash = null,
        ?int $wpPostId = null,
        ?\DateTimeImmutable $createdAt = null,
        ?\DateTimeImmutable $updatedAt = null
    ) {
        $this->validateCanonicalName($canonicalName);
        $this->validateSlug($slug);

        $this->id = $id;
        $this->sagaId = $sagaId;
        $this->type = $type;
        $this->canonicalName = $canonicalName;
        $this->slug = $slug;
        $this->importanceScore = $importanceScore ?? ImportanceScore::default();
        $this->embeddingHash = $embeddingHash;
        $this->wpPostId = $wpPostId;
        $this->createdAt = $createdAt ?? new \DateTimeImmutable();
        $this->updatedAt = $updatedAt ?? new \DateTimeImmutable();
    }

    public function getId(): ?EntityId
    {
        return $this->id;
    }

    public function setId(EntityId $id): void
    {
        if ($this->id !== null) {
            throw new ValidationException('Cannot change entity ID once set');
        }
        $this->id = $id;
    }

    public function getSagaId(): SagaId
    {
        return $this->sagaId;
    }

    public function getType(): EntityType
    {
        return $this->type;
    }

    public function getCanonicalName(): string
    {
        return $this->canonicalName;
    }

    public function updateCanonicalName(string $name): void
    {
        $this->validateCanonicalName($name);
        $this->canonicalName = $name;
        $this->touch();
    }

    public function getSlug(): string
    {
        return $this->slug;
    }

    public function updateSlug(string $slug): void
    {
        $this->validateSlug($slug);
        $this->slug = $slug;
        $this->touch();
    }

    public function getImportanceScore(): ImportanceScore
    {
        return $this->importanceScore;
    }

    public function setImportanceScore(ImportanceScore $score): void
    {
        $this->importanceScore = $score;
        $this->touch();
    }

    public function getEmbeddingHash(): ?string
    {
        return $this->embeddingHash;
    }

    public function setEmbeddingHash(string $hash): void
    {
        $this->embeddingHash = $hash;
        $this->touch();
    }

    public function getWpPostId(): ?int
    {
        return $this->wpPostId;
    }

    public function linkToWpPost(int $postId): void
    {
        $this->wpPostId = $postId;
        $this->touch();
    }

    public function unlinkFromWpPost(): void
    {
        $this->wpPostId = null;
        $this->touch();
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }

    private function touch(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }

    private function validateCanonicalName(string $name): void
    {
        $name = trim($name);

        if (empty($name)) {
            throw new ValidationException('Canonical name cannot be empty');
        }

        if (strlen($name) > 255) {
            throw new ValidationException('Canonical name cannot exceed 255 characters');
        }
    }

    private function validateSlug(string $slug): void
    {
        $slug = trim($slug);

        if (empty($slug)) {
            throw new ValidationException('Slug cannot be empty');
        }

        if (strlen($slug) > 255) {
            throw new ValidationException('Slug cannot exceed 255 characters');
        }

        if (!preg_match('/^[a-z0-9-]+$/', $slug)) {
            throw new ValidationException('Slug must contain only lowercase letters, numbers, and hyphens');
        }
    }
}
