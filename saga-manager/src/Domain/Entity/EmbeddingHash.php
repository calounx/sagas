<?php
declare(strict_types=1);

namespace SagaManager\Domain\Entity;

use SagaManager\Domain\Exception\ValidationException;

/**
 * Embedding Hash Value Object
 *
 * SHA256 hash of embedding vector for duplicate detection.
 */
final readonly class EmbeddingHash
{
    private const LENGTH = 64; // SHA256 hex string length

    private string $hash;

    public function __construct(string $hash)
    {
        $this->validate($hash);
        $this->hash = strtolower($hash);
    }

    private function validate(string $hash): void
    {
        if (strlen($hash) !== self::LENGTH) {
            throw new ValidationException(
                sprintf('Embedding hash must be %d characters, got %d', self::LENGTH, strlen($hash))
            );
        }

        if (!ctype_xdigit($hash)) {
            throw new ValidationException('Embedding hash must be a valid hexadecimal string');
        }
    }

    /**
     * Create hash from embedding vector
     */
    public static function fromVector(EmbeddingVector $vector): self
    {
        $hash = hash('sha256', $vector->toBinary());
        return new self($hash);
    }

    /**
     * Create hash from text content
     */
    public static function fromText(string $text): self
    {
        $hash = hash('sha256', $text);
        return new self($hash);
    }

    public function value(): string
    {
        return $this->hash;
    }

    public function equals(self $other): bool
    {
        return $this->hash === $other->hash;
    }

    public function __toString(): string
    {
        return $this->hash;
    }
}
