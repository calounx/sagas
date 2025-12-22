<?php
declare(strict_types=1);

namespace SagaManager\Tests\Unit\Domain\Entity;

use PHPUnit\Framework\TestCase;
use SagaManager\Domain\Entity\ContentFragment;
use SagaManager\Domain\Entity\ContentFragmentId;
use SagaManager\Domain\Entity\EntityId;
use SagaManager\Domain\Entity\TokenCount;
use SagaManager\Domain\Exception\ValidationException;

class ContentFragmentTest extends TestCase
{
    private EntityId $entityId;

    protected function setUp(): void
    {
        $this->entityId = new EntityId(1);
    }

    public function test_can_create_basic_fragment(): void
    {
        $fragment = new ContentFragment(
            entityId: $this->entityId,
            fragmentText: 'This is a test fragment text.'
        );

        $this->assertTrue($this->entityId->equals($fragment->getEntityId()));
        $this->assertSame('This is a test fragment text.', $fragment->getFragmentText());
        $this->assertNull($fragment->getId());
        $this->assertNull($fragment->getEmbedding());
        $this->assertFalse($fragment->hasEmbedding());
        $this->assertGreaterThan(0, $fragment->getTokenCount()->value());
    }

    public function test_can_create_fragment_with_all_properties(): void
    {
        $createdAt = new \DateTimeImmutable('2024-01-01');

        $fragment = new ContentFragment(
            entityId: $this->entityId,
            fragmentText: 'Test fragment',
            tokenCount: new TokenCount(100),
            embedding: 'binary_embedding_data',
            id: new ContentFragmentId(42),
            createdAt: $createdAt
        );

        $this->assertSame(42, $fragment->getId()->value());
        $this->assertSame(100, $fragment->getTokenCount()->value());
        $this->assertSame('binary_embedding_data', $fragment->getEmbedding());
        $this->assertTrue($fragment->hasEmbedding());
        $this->assertSame($createdAt, $fragment->getCreatedAt());
    }

    public function test_throws_exception_for_empty_fragment_text(): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Fragment text cannot be empty');

        new ContentFragment(
            entityId: $this->entityId,
            fragmentText: ''
        );
    }

    public function test_throws_exception_for_whitespace_only_fragment_text(): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Fragment text cannot be empty');

        new ContentFragment(
            entityId: $this->entityId,
            fragmentText: '   '
        );
    }

    public function test_set_id_updates_fragment_id(): void
    {
        $fragment = new ContentFragment(
            entityId: $this->entityId,
            fragmentText: 'Test fragment'
        );

        $this->assertNull($fragment->getId());

        $fragment->setId(new ContentFragmentId(99));

        $this->assertSame(99, $fragment->getId()->value());
    }

    public function test_set_id_throws_exception_if_already_set(): void
    {
        $fragment = new ContentFragment(
            entityId: $this->entityId,
            fragmentText: 'Test fragment',
            id: new ContentFragmentId(42)
        );

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Content fragment ID cannot be changed once set');

        $fragment->setId(new ContentFragmentId(99));
    }

    public function test_update_fragment_text(): void
    {
        $fragment = new ContentFragment(
            entityId: $this->entityId,
            fragmentText: 'Original text',
            embedding: 'some_embedding'
        );

        $fragment->updateFragmentText('Updated text');

        $this->assertSame('Updated text', $fragment->getFragmentText());
        $this->assertNull($fragment->getEmbedding()); // Embedding cleared on text update
    }

    public function test_set_embedding(): void
    {
        $fragment = new ContentFragment(
            entityId: $this->entityId,
            fragmentText: 'Test fragment'
        );

        $this->assertFalse($fragment->hasEmbedding());

        $fragment->setEmbedding('new_embedding_data');

        $this->assertTrue($fragment->hasEmbedding());
        $this->assertSame('new_embedding_data', $fragment->getEmbedding());
    }

    public function test_clear_embedding(): void
    {
        $fragment = new ContentFragment(
            entityId: $this->entityId,
            fragmentText: 'Test fragment',
            embedding: 'some_embedding'
        );

        $this->assertTrue($fragment->hasEmbedding());

        $fragment->clearEmbedding();

        $this->assertFalse($fragment->hasEmbedding());
        $this->assertNull($fragment->getEmbedding());
    }

    public function test_get_preview_returns_full_text_when_short(): void
    {
        $fragment = new ContentFragment(
            entityId: $this->entityId,
            fragmentText: 'Short text'
        );

        $this->assertSame('Short text', $fragment->getPreview(100));
    }

    public function test_get_preview_truncates_long_text(): void
    {
        $longText = str_repeat('a', 200);
        $fragment = new ContentFragment(
            entityId: $this->entityId,
            fragmentText: $longText
        );

        $preview = $fragment->getPreview(100);

        $this->assertSame(100, mb_strlen($preview));
        $this->assertStringEndsWith('...', $preview);
    }

    public function test_contains_finds_term(): void
    {
        $fragment = new ContentFragment(
            entityId: $this->entityId,
            fragmentText: 'Luke Skywalker is a Jedi Knight'
        );

        $this->assertTrue($fragment->contains('Skywalker'));
        $this->assertTrue($fragment->contains('jedi')); // Case insensitive
        $this->assertFalse($fragment->contains('Vader'));
    }

    public function test_to_array(): void
    {
        $fragment = new ContentFragment(
            entityId: $this->entityId,
            fragmentText: 'Test fragment text',
            tokenCount: new TokenCount(50),
            id: new ContentFragmentId(42)
        );

        $array = $fragment->toArray();

        $this->assertSame(42, $array['id']);
        $this->assertSame(1, $array['entity_id']);
        $this->assertSame('Test fragment text', $array['fragment_text']);
        $this->assertFalse($array['has_embedding']);
        $this->assertSame(50, $array['token_count']);
        $this->assertArrayHasKey('created_at', $array);
        $this->assertArrayHasKey('preview', $array);
    }

    public function test_token_count_is_estimated_if_not_provided(): void
    {
        $text = str_repeat('word ', 100); // ~500 characters
        $fragment = new ContentFragment(
            entityId: $this->entityId,
            fragmentText: $text
        );

        // Rough estimate: ~4 chars per token
        $this->assertGreaterThan(100, $fragment->getTokenCount()->value());
    }

    public function test_created_at_is_set_on_construction(): void
    {
        $before = new \DateTimeImmutable();
        $fragment = new ContentFragment(
            entityId: $this->entityId,
            fragmentText: 'Test fragment'
        );
        $after = new \DateTimeImmutable();

        $this->assertGreaterThanOrEqual($before, $fragment->getCreatedAt());
        $this->assertLessThanOrEqual($after, $fragment->getCreatedAt());
    }
}
