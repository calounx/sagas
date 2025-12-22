<?php
declare(strict_types=1);

namespace SagaManager\Domain\Entity;

use SagaManager\Domain\Exception\ValidationException;

/**
 * Embedding Vector Value Object
 *
 * Represents a 384-dimensional float32 vector embedding.
 * Stored as binary blob for efficient storage and retrieval.
 */
final readonly class EmbeddingVector
{
    private const DIMENSIONS = 384;
    private const BYTES_PER_FLOAT = 4;
    private const EXPECTED_SIZE = self::DIMENSIONS * self::BYTES_PER_FLOAT; // 1536 bytes

    private string $binary;

    public function __construct(string $binary)
    {
        $this->validateBinary($binary);
        $this->binary = $binary;
    }

    private function validateBinary(string $binary): void
    {
        $length = strlen($binary);

        if ($length !== self::EXPECTED_SIZE) {
            throw new ValidationException(
                sprintf(
                    'Embedding vector must be %d bytes (%d dimensions x %d bytes), got %d bytes',
                    self::EXPECTED_SIZE,
                    self::DIMENSIONS,
                    self::BYTES_PER_FLOAT,
                    $length
                )
            );
        }
    }

    /**
     * Create from array of floats
     *
     * @param float[] $floats
     */
    public static function fromArray(array $floats): self
    {
        if (count($floats) !== self::DIMENSIONS) {
            throw new ValidationException(
                sprintf('Expected %d dimensions, got %d', self::DIMENSIONS, count($floats))
            );
        }

        $binary = '';
        foreach ($floats as $float) {
            $binary .= pack('f', $float);
        }

        return new self($binary);
    }

    /**
     * Get the raw binary representation
     */
    public function toBinary(): string
    {
        return $this->binary;
    }

    /**
     * Convert to array of floats
     *
     * @return float[]
     */
    public function toArray(): array
    {
        $floats = [];
        for ($i = 0; $i < self::DIMENSIONS; $i++) {
            $offset = $i * self::BYTES_PER_FLOAT;
            $unpacked = unpack('f', substr($this->binary, $offset, self::BYTES_PER_FLOAT));
            $floats[] = $unpacked[1];
        }

        return $floats;
    }

    /**
     * Calculate cosine similarity with another vector
     */
    public function cosineSimilarity(self $other): float
    {
        $a = $this->toArray();
        $b = $other->toArray();

        $dotProduct = 0.0;
        $normA = 0.0;
        $normB = 0.0;

        for ($i = 0; $i < self::DIMENSIONS; $i++) {
            $dotProduct += $a[$i] * $b[$i];
            $normA += $a[$i] * $a[$i];
            $normB += $b[$i] * $b[$i];
        }

        $normA = sqrt($normA);
        $normB = sqrt($normB);

        if ($normA == 0 || $normB == 0) {
            return 0.0;
        }

        return $dotProduct / ($normA * $normB);
    }

    /**
     * Calculate Euclidean distance with another vector
     */
    public function euclideanDistance(self $other): float
    {
        $a = $this->toArray();
        $b = $other->toArray();

        $sum = 0.0;
        for ($i = 0; $i < self::DIMENSIONS; $i++) {
            $diff = $a[$i] - $b[$i];
            $sum += $diff * $diff;
        }

        return sqrt($sum);
    }

    public static function getDimensions(): int
    {
        return self::DIMENSIONS;
    }

    public static function getExpectedSize(): int
    {
        return self::EXPECTED_SIZE;
    }

    public function equals(self $other): bool
    {
        return $this->binary === $other->binary;
    }
}
