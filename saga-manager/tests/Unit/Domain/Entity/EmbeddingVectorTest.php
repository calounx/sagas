<?php
declare(strict_types=1);

namespace SagaManager\Tests\Unit\Domain\Entity;

use PHPUnit\Framework\TestCase;
use SagaManager\Domain\Entity\EmbeddingVector;
use SagaManager\Domain\Exception\ValidationException;

class EmbeddingVectorTest extends TestCase
{
    public function test_can_create_from_valid_binary(): void
    {
        $binary = str_repeat("\x00\x00\x80\x3f", 384); // 384 floats of 1.0
        $vector = new EmbeddingVector($binary);

        $this->assertSame($binary, $vector->toBinary());
    }

    public function test_throws_exception_for_invalid_size(): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Embedding vector must be 1536 bytes');

        new EmbeddingVector('too short');
    }

    public function test_from_array_creates_valid_vector(): void
    {
        $floats = array_fill(0, 384, 0.5);
        $vector = EmbeddingVector::fromArray($floats);

        $this->assertSame(1536, strlen($vector->toBinary()));
    }

    public function test_from_array_throws_for_wrong_dimension_count(): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Expected 384 dimensions');

        $floats = array_fill(0, 100, 0.5);
        EmbeddingVector::fromArray($floats);
    }

    public function test_to_array_returns_correct_floats(): void
    {
        $original = array_fill(0, 384, 0.5);
        $vector = EmbeddingVector::fromArray($original);
        $result = $vector->toArray();

        $this->assertCount(384, $result);
        $this->assertEqualsWithDelta(0.5, $result[0], 0.0001);
    }

    public function test_cosine_similarity_identical_vectors(): void
    {
        $floats = array_fill(0, 384, 1.0);
        $vector1 = EmbeddingVector::fromArray($floats);
        $vector2 = EmbeddingVector::fromArray($floats);

        $similarity = $vector1->cosineSimilarity($vector2);

        $this->assertEqualsWithDelta(1.0, $similarity, 0.0001);
    }

    public function test_cosine_similarity_orthogonal_vectors(): void
    {
        $floats1 = array_fill(0, 384, 0.0);
        $floats1[0] = 1.0;

        $floats2 = array_fill(0, 384, 0.0);
        $floats2[1] = 1.0;

        $vector1 = EmbeddingVector::fromArray($floats1);
        $vector2 = EmbeddingVector::fromArray($floats2);

        $similarity = $vector1->cosineSimilarity($vector2);

        $this->assertEqualsWithDelta(0.0, $similarity, 0.0001);
    }

    public function test_euclidean_distance_identical_vectors(): void
    {
        $floats = array_fill(0, 384, 1.0);
        $vector1 = EmbeddingVector::fromArray($floats);
        $vector2 = EmbeddingVector::fromArray($floats);

        $distance = $vector1->euclideanDistance($vector2);

        $this->assertEqualsWithDelta(0.0, $distance, 0.0001);
    }

    public function test_get_dimensions_returns_384(): void
    {
        $this->assertSame(384, EmbeddingVector::getDimensions());
    }

    public function test_get_expected_size_returns_1536(): void
    {
        $this->assertSame(1536, EmbeddingVector::getExpectedSize());
    }

    public function test_equals_returns_true_for_same_binary(): void
    {
        $binary = str_repeat("\x00\x00\x80\x3f", 384);
        $vector1 = new EmbeddingVector($binary);
        $vector2 = new EmbeddingVector($binary);

        $this->assertTrue($vector1->equals($vector2));
    }

    public function test_equals_returns_false_for_different_binary(): void
    {
        $vector1 = EmbeddingVector::fromArray(array_fill(0, 384, 0.5));
        $vector2 = EmbeddingVector::fromArray(array_fill(0, 384, 0.6));

        $this->assertFalse($vector1->equals($vector2));
    }
}
