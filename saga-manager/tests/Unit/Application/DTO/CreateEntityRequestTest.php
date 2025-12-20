<?php
declare(strict_types=1);

namespace SagaManager\Tests\Unit\Application\DTO;

use PHPUnit\Framework\TestCase;
use SagaManager\Application\DTO\CreateEntityRequest;
use SagaManager\Domain\Exception\ValidationException;

/**
 * Unit tests for CreateEntityRequest DTO
 *
 * Tests input validation logic.
 */
final class CreateEntityRequestTest extends TestCase
{
    public function test_from_array_creates_valid_request(): void
    {
        // Arrange
        $data = [
            'saga_id' => 1,
            'type' => 'character',
            'canonical_name' => 'Luke Skywalker',
            'slug' => 'luke-skywalker',
            'importance_score' => 95,
            'wp_post_id' => 123,
        ];

        // Act
        $request = CreateEntityRequest::fromArray($data);

        // Assert
        $this->assertEquals(1, $request->sagaId);
        $this->assertEquals('character', $request->type);
        $this->assertEquals('Luke Skywalker', $request->canonicalName);
        $this->assertEquals('luke-skywalker', $request->slug);
        $this->assertEquals(95, $request->importanceScore);
        $this->assertEquals(123, $request->wpPostId);
    }

    public function test_from_array_with_optional_fields(): void
    {
        // Arrange
        $data = [
            'saga_id' => 1,
            'type' => 'location',
            'canonical_name' => 'Tatooine',
            'slug' => 'tatooine',
        ];

        // Act
        $request = CreateEntityRequest::fromArray($data);

        // Assert
        $this->assertNull($request->importanceScore);
        $this->assertNull($request->wpPostId);
    }

    public function test_from_array_throws_exception_for_missing_saga_id(): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('saga_id is required');

        CreateEntityRequest::fromArray([
            'type' => 'character',
            'canonical_name' => 'Test',
            'slug' => 'test',
        ]);
    }

    public function test_from_array_throws_exception_for_missing_type(): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('type is required');

        CreateEntityRequest::fromArray([
            'saga_id' => 1,
            'canonical_name' => 'Test',
            'slug' => 'test',
        ]);
    }

    public function test_from_array_throws_exception_for_missing_canonical_name(): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('canonical_name is required');

        CreateEntityRequest::fromArray([
            'saga_id' => 1,
            'type' => 'character',
            'slug' => 'test',
        ]);
    }

    public function test_from_array_throws_exception_for_missing_slug(): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('slug is required');

        CreateEntityRequest::fromArray([
            'saga_id' => 1,
            'type' => 'character',
            'canonical_name' => 'Test',
        ]);
    }

    public function test_validation_fails_for_invalid_saga_id(): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('saga_id must be positive');

        new CreateEntityRequest(
            sagaId: 0,
            type: 'character',
            canonicalName: 'Test',
            slug: 'test'
        );
    }

    public function test_validation_fails_for_empty_canonical_name(): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('canonical_name cannot be empty');

        new CreateEntityRequest(
            sagaId: 1,
            type: 'character',
            canonicalName: '   ',
            slug: 'test'
        );
    }

    public function test_validation_fails_for_empty_slug(): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('slug cannot be empty');

        new CreateEntityRequest(
            sagaId: 1,
            type: 'character',
            canonicalName: 'Test',
            slug: '   '
        );
    }

    public function test_validation_fails_for_invalid_type(): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('type must be one of');

        new CreateEntityRequest(
            sagaId: 1,
            type: 'invalid_type',
            canonicalName: 'Test',
            slug: 'test'
        );
    }

    public function test_validation_fails_for_importance_score_below_zero(): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('importance_score must be between 0 and 100');

        new CreateEntityRequest(
            sagaId: 1,
            type: 'character',
            canonicalName: 'Test',
            slug: 'test',
            importanceScore: -1
        );
    }

    public function test_validation_fails_for_importance_score_above_100(): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('importance_score must be between 0 and 100');

        new CreateEntityRequest(
            sagaId: 1,
            type: 'character',
            canonicalName: 'Test',
            slug: 'test',
            importanceScore: 101
        );
    }

    /**
     * @dataProvider validTypeProvider
     */
    public function test_validation_accepts_all_valid_types(string $type): void
    {
        $request = new CreateEntityRequest(
            sagaId: 1,
            type: $type,
            canonicalName: 'Test',
            slug: 'test'
        );

        $this->assertEquals($type, $request->type);
    }

    public function validTypeProvider(): array
    {
        return [
            ['character'],
            ['location'],
            ['event'],
            ['faction'],
            ['artifact'],
            ['concept'],
        ];
    }
}
