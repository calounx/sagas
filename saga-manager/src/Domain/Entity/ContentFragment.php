<?php
declare(strict_types=1);

namespace SagaManager\Domain\Entity;

use SagaManager\Domain\Exception\ValidationException;

/**
 * Content Fragment Entity
 *
 * Represents a text fragment from an entity for semantic search.
 * Fragments are indexed with full-text search and can have embeddings for vector similarity.
 */
class ContentFragment
{
    private ?ContentFragmentId $id;
    private EntityId $entityId;
    private string $fragmentText;
    private ?string $embedding;
    private TokenCount $tokenCount;
    private \DateTimeImmutable $createdAt;

    public function __construct(
        EntityId $entityId,
        string $fragmentText,
        ?TokenCount $tokenCount = null,
        ?string $embedding = null,
        ?ContentFragmentId $id = null,
        ?\DateTimeImmutable $createdAt = null
    ) {
        $this->validateFragmentText($fragmentText);

        $this->id = $id;
        $this->entityId = $entityId;
        $this->fragmentText = $fragmentText;
        $this->embedding = $embedding;
        $this->tokenCount = $tokenCount ?? $this->estimateTokenCount($fragmentText);
        $this->createdAt = $createdAt ?? new \DateTimeImmutable();
    }

    private function validateFragmentText(string $text): void
    {
        if (trim($text) === '') {
            throw new ValidationException('Fragment text cannot be empty');
        }

        if (mb_strlen($text) > 65535) {
            throw new ValidationException('Fragment text exceeds maximum length of 65535 characters');
        }
    }

    private function estimateTokenCount(string $text): TokenCount
    {
        // Rough estimation: ~4 characters per token on average
        $count = (int) ceil(mb_strlen($text) / 4);
        return new TokenCount(min($count, 65535));
    }

    public function getId(): ?ContentFragmentId
    {
        return $this->id;
    }

    public function setId(ContentFragmentId $id): void
    {
        if ($this->id !== null) {
            throw new ValidationException('Content fragment ID cannot be changed once set');
        }

        $this->id = $id;
    }

    public function getEntityId(): EntityId
    {
        return $this->entityId;
    }

    public function getFragmentText(): string
    {
        return $this->fragmentText;
    }

    public function updateFragmentText(string $text): void
    {
        $this->validateFragmentText($text);
        $this->fragmentText = $text;
        $this->tokenCount = $this->estimateTokenCount($text);
        // Clear embedding when text changes
        $this->embedding = null;
    }

    public function getEmbedding(): ?string
    {
        return $this->embedding;
    }

    public function setEmbedding(string $embedding): void
    {
        $this->embedding = $embedding;
    }

    public function hasEmbedding(): bool
    {
        return $this->embedding !== null;
    }

    public function clearEmbedding(): void
    {
        $this->embedding = null;
    }

    public function getTokenCount(): TokenCount
    {
        return $this->tokenCount;
    }

    public function setTokenCount(TokenCount $tokenCount): void
    {
        $this->tokenCount = $tokenCount;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    /**
     * Get a preview of the fragment text
     */
    public function getPreview(int $maxLength = 100): string
    {
        if (mb_strlen($this->fragmentText) <= $maxLength) {
            return $this->fragmentText;
        }

        return mb_substr($this->fragmentText, 0, $maxLength - 3) . '...';
    }

    /**
     * Check if fragment contains a search term (case-insensitive)
     */
    public function contains(string $term): bool
    {
        return mb_stripos($this->fragmentText, $term) !== false;
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id?->value(),
            'entity_id' => $this->entityId->value(),
            'fragment_text' => $this->fragmentText,
            'preview' => $this->getPreview(),
            'has_embedding' => $this->hasEmbedding(),
            'token_count' => $this->tokenCount->value(),
            'created_at' => $this->createdAt->format('c'),
        ];
    }
}
