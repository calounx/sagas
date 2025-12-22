<?php
declare(strict_types=1);

namespace SagaManager\Tests\Unit\Domain\Entity;

use PHPUnit\Framework\TestCase;
use SagaManager\Domain\Entity\EmbeddingHash;
use SagaManager\Domain\Entity\EmbeddingVector;
use SagaManager\Domain\Exception\ValidationException;

class EmbeddingHashTest extends TestCase
{
    public function test_can_create_with_valid_hash(): void
    {
        $hash = str_repeat('a', 64);
        $embeddingHash = new EmbeddingHash($hash);

        $this->assertSame($hash, $embeddingHash->value());
    }

    public function test_normalizes_to_lowercase(): void
    {
        $hash = str_repeat('A', 64);
        $embeddingHash = new EmbeddingHash($hash);

        $this->assertSame(str_repeat('a', 64), $embeddingHash->value());
    }

    public function test_throws_exception_for_wrong_length(): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Embedding hash must be 64 characters');

        new EmbeddingHash('tooshort');
    }

    public function test_throws_exception_for_non_hex(): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('valid hexadecimal string');

        new EmbeddingHash(str_repeat('g', 64)); // 'g' is not hex
    }

    public function test_from_vector_creates_hash(): void
    {
        $vector = EmbeddingVector::fromArray(array_fill(0, 384, 0.5));
        $hash = EmbeddingHash::fromVector($vector);

        $this->assertSame(64, strlen($hash->value()));
        $this->assertTrue(ctype_xdigit($hash->value()));
    }

    public function test_from_text_creates_hash(): void
    {
        $hash = EmbeddingHash::fromText('Hello, World!');

        $this->assertSame(64, strlen($hash->value()));
        $this->assertTrue(ctype_xdigit($hash->value()));
    }

    public function test_same_text_produces_same_hash(): void
    {
        $hash1 = EmbeddingHash::fromText('Hello, World!');
        $hash2 = EmbeddingHash::fromText('Hello, World!');

        $this->assertTrue($hash1->equals($hash2));
    }

    public function test_different_text_produces_different_hash(): void
    {
        $hash1 = EmbeddingHash::fromText('Hello, World!');
        $hash2 = EmbeddingHash::fromText('Goodbye, World!');

        $this->assertFalse($hash1->equals($hash2));
    }

    public function test_to_string_returns_hash(): void
    {
        $hash = str_repeat('b', 64);
        $embeddingHash = new EmbeddingHash($hash);

        $this->assertSame($hash, (string) $embeddingHash);
    }

    public function test_equals_returns_true_for_same_hash(): void
    {
        $hash = str_repeat('c', 64);
        $embeddingHash1 = new EmbeddingHash($hash);
        $embeddingHash2 = new EmbeddingHash($hash);

        $this->assertTrue($embeddingHash1->equals($embeddingHash2));
    }

    public function test_equals_returns_false_for_different_hash(): void
    {
        $embeddingHash1 = new EmbeddingHash(str_repeat('a', 64));
        $embeddingHash2 = new EmbeddingHash(str_repeat('b', 64));

        $this->assertFalse($embeddingHash1->equals($embeddingHash2));
    }
}
