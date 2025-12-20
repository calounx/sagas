<?php
declare(strict_types=1);

namespace SagaManager\Tests\Unit\Application\UseCase\CreateEntity;

use PHPUnit\Framework\TestCase;
use SagaManager\Application\UseCase\CreateEntity\CreateEntityCommand;
use SagaManager\Application\UseCase\CreateEntity\CreateEntityHandler;
use SagaManager\Domain\Entity\EntityId;
use SagaManager\Domain\Entity\EntityType;
use SagaManager\Domain\Entity\ImportanceScore;
use SagaManager\Domain\Entity\SagaEntity;
use SagaManager\Domain\Entity\SagaId;
use SagaManager\Domain\Exception\DuplicateEntityException;
use SagaManager\Domain\Exception\ValidationException;
use SagaManager\Domain\Repository\EntityRepositoryInterface;

/**
 * Unit tests for CreateEntityHandler
 *
 * Tests application logic without database dependencies.
 * Uses mock repository for isolation.
 */
final class CreateEntityHandlerTest extends TestCase
{
    private EntityRepositoryInterface $mockRepository;
    private CreateEntityHandler $handler;

    protected function setUp(): void
    {
        $this->mockRepository = $this->createMock(EntityRepositoryInterface::class);
        $this->handler = new CreateEntityHandler($this->mockRepository);
    }

    public function test_handle_creates_entity_successfully(): void
    {
        // Arrange
        $command = new CreateEntityCommand(
            sagaId: 1,
            type: 'character',
            canonicalName: 'Luke Skywalker',
            slug: 'luke-skywalker',
            importanceScore: 95
        );

        $this->mockRepository
            ->expects($this->once())
            ->method('findBySagaAndName')
            ->with(
                $this->callback(fn($id) => $id instanceof SagaId && $id->value() === 1),
                'Luke Skywalker'
            )
            ->willReturn(null);

        $this->mockRepository
            ->expects($this->once())
            ->method('findBySlug')
            ->with('luke-skywalker')
            ->willReturn(null);

        $savedEntity = null;
        $this->mockRepository
            ->expects($this->once())
            ->method('save')
            ->willReturnCallback(function(SagaEntity $entity) use (&$savedEntity) {
                $savedEntity = $entity;
                $entity->setId(new EntityId(42));
            });

        // Act
        $result = $this->handler->handle($command);

        // Assert
        $this->assertInstanceOf(EntityId::class, $result);
        $this->assertEquals(42, $result->value());
        $this->assertEquals('Luke Skywalker', $savedEntity->getCanonicalName());
        $this->assertEquals('luke-skywalker', $savedEntity->getSlug());
        $this->assertEquals(EntityType::CHARACTER, $savedEntity->getType());
        $this->assertEquals(95, $savedEntity->getImportanceScore()->value());
    }

    public function test_handle_throws_exception_for_duplicate_name(): void
    {
        // Arrange
        $command = new CreateEntityCommand(
            sagaId: 1,
            type: 'character',
            canonicalName: 'Luke Skywalker',
            slug: 'luke-skywalker'
        );

        $existingEntity = new SagaEntity(
            sagaId: new SagaId(1),
            type: EntityType::CHARACTER,
            canonicalName: 'Luke Skywalker',
            slug: 'luke-skywalker-old',
            id: new EntityId(1)
        );

        $this->mockRepository
            ->method('findBySagaAndName')
            ->willReturn($existingEntity);

        // Assert
        $this->expectException(DuplicateEntityException::class);
        $this->expectExceptionMessage('Entity with name "Luke Skywalker" already exists');

        // Act
        $this->handler->handle($command);
    }

    public function test_handle_throws_exception_for_duplicate_slug(): void
    {
        // Arrange
        $command = new CreateEntityCommand(
            sagaId: 1,
            type: 'character',
            canonicalName: 'Luke Skywalker',
            slug: 'luke-skywalker'
        );

        $existingEntity = new SagaEntity(
            sagaId: new SagaId(2),
            type: EntityType::CHARACTER,
            canonicalName: 'Different Name',
            slug: 'luke-skywalker',
            id: new EntityId(99)
        );

        $this->mockRepository
            ->method('findBySagaAndName')
            ->willReturn(null);

        $this->mockRepository
            ->method('findBySlug')
            ->willReturn($existingEntity);

        // Assert
        $this->expectException(DuplicateEntityException::class);
        $this->expectExceptionMessage('Entity with slug "luke-skywalker" already exists');

        // Act
        $this->handler->handle($command);
    }

    public function test_handle_uses_default_importance_score_when_null(): void
    {
        // Arrange
        $command = new CreateEntityCommand(
            sagaId: 1,
            type: 'character',
            canonicalName: 'Han Solo',
            slug: 'han-solo',
            importanceScore: null
        );

        $this->mockRepository->method('findBySagaAndName')->willReturn(null);
        $this->mockRepository->method('findBySlug')->willReturn(null);

        $savedEntity = null;
        $this->mockRepository
            ->method('save')
            ->willReturnCallback(function(SagaEntity $entity) use (&$savedEntity) {
                $savedEntity = $entity;
                $entity->setId(new EntityId(1));
            });

        // Act
        $this->handler->handle($command);

        // Assert
        $this->assertEquals(50, $savedEntity->getImportanceScore()->value());
    }

    public function test_handle_links_to_wordpress_post_when_provided(): void
    {
        // Arrange
        $command = new CreateEntityCommand(
            sagaId: 1,
            type: 'location',
            canonicalName: 'Tatooine',
            slug: 'tatooine',
            wpPostId: 123
        );

        $this->mockRepository->method('findBySagaAndName')->willReturn(null);
        $this->mockRepository->method('findBySlug')->willReturn(null);

        $savedEntity = null;
        $this->mockRepository
            ->method('save')
            ->willReturnCallback(function(SagaEntity $entity) use (&$savedEntity) {
                $savedEntity = $entity;
                $entity->setId(new EntityId(1));
            });

        // Act
        $this->handler->handle($command);

        // Assert
        $this->assertEquals(123, $savedEntity->getWpPostId());
    }

    public function test_handle_throws_exception_for_invalid_command_type(): void
    {
        // Arrange
        $invalidCommand = $this->createMock(\SagaManager\Application\Command\CommandInterface::class);

        // Assert
        $this->expectException(\InvalidArgumentException::class);

        // Act
        $this->handler->handle($invalidCommand);
    }

    public function test_handle_validates_entity_type(): void
    {
        // This test verifies that EntityType::from() throws ValueError for invalid types
        $this->expectException(\ValueError::class);

        // Act
        new CreateEntityCommand(
            sagaId: 1,
            type: 'invalid_type',
            canonicalName: 'Test',
            slug: 'test'
        );

        // The EntityType::from() in handler will throw ValueError
    }
}
