<?php
/**
 * Unit Tests for GeneratedSummary Value Object
 *
 * @package SagaManager
 * @subpackage Tests\Unit\SummaryGenerator
 */

declare(strict_types=1);

namespace SagaManager\Tests\Unit\SummaryGenerator;

use SagaManager\Tests\TestCase;
use SagaManager\AI\Entities\GeneratedSummary;
use SagaManager\AI\Entities\SummaryType;

class GeneratedSummaryTest extends TestCase
{
    /**
     * Test basic summary creation
     */
    public function test_can_create_generated_summary(): void
    {
        $summary = new GeneratedSummary(
            id: null,
            request_id: 1,
            saga_id: 1,
            entity_id: 123,
            summary_type: SummaryType::CHARACTER_ARC,
            version: 1,
            title: 'Luke Skywalker Character Arc',
            summary_text: 'This is a comprehensive summary of Luke\'s journey...',
            word_count: 150,
            key_points: ['Orphan', 'Jedi Training', 'Hero'],
            metadata: ['themes' => ['redemption', 'coming-of-age']],
            quality_score: 85.0,
            readability_score: 75.0,
            is_current: true,
            regeneration_reason: null,
            cache_key: md5('test'),
            cache_expires_at: time() + 3600,
            ai_model: 'gpt-4',
            token_count: 2000,
            generation_cost: 0.10
        );

        $this->assertNull($summary->id);
        $this->assertEquals(1, $summary->request_id);
        $this->assertEquals('Luke Skywalker Character Arc', $summary->title);
        $this->assertEquals(150, $summary->word_count);
        $this->assertEquals(85.0, $summary->quality_score);
        $this->assertTrue($summary->is_current);
    }

    /**
     * Test title validation
     */
    public function test_validates_title_not_empty(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Summary title cannot be empty');

        new GeneratedSummary(
            id: null,
            request_id: 1,
            saga_id: 1,
            entity_id: null,
            summary_type: SummaryType::TIMELINE,
            version: 1,
            title: '', // Invalid
            summary_text: 'Valid text',
            word_count: 50,
            key_points: [],
            metadata: [],
            quality_score: null,
            readability_score: null,
            is_current: true,
            regeneration_reason: null,
            cache_key: md5('test'),
            cache_expires_at: null,
            ai_model: 'gpt-4',
            token_count: 500,
            generation_cost: 0.05
        );
    }

    /**
     * Test summary text validation
     */
    public function test_validates_summary_text_not_empty(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Summary text cannot be empty');

        new GeneratedSummary(
            id: null,
            request_id: 1,
            saga_id: 1,
            entity_id: null,
            summary_type: SummaryType::TIMELINE,
            version: 1,
            title: 'Valid Title',
            summary_text: '', // Invalid
            word_count: 0,
            key_points: [],
            metadata: [],
            quality_score: null,
            readability_score: null,
            is_current: true,
            regeneration_reason: null,
            cache_key: md5('test'),
            cache_expires_at: null,
            ai_model: 'gpt-4',
            token_count: 100,
            generation_cost: 0.01
        );
    }

    /**
     * Test quality score validation
     */
    public function test_validates_quality_score_range(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Quality score must be between 0 and 100');

        new GeneratedSummary(
            id: null,
            request_id: 1,
            saga_id: 1,
            entity_id: 100,
            summary_type: SummaryType::FACTION,
            version: 1,
            title: 'Faction Summary',
            summary_text: 'A detailed faction analysis...',
            word_count: 200,
            key_points: [],
            metadata: [],
            quality_score: 150.0, // Invalid
            readability_score: 60.0,
            is_current: true,
            regeneration_reason: null,
            cache_key: md5('test'),
            cache_expires_at: null,
            ai_model: 'claude-3-opus',
            token_count: 1500,
            generation_cost: 0.08
        );
    }

    /**
     * Test version validation
     */
    public function test_validates_version_minimum(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Version must be >= 1');

        new GeneratedSummary(
            id: null,
            request_id: 1,
            saga_id: 1,
            entity_id: null,
            summary_type: SummaryType::RELATIONSHIP,
            version: 0, // Invalid
            title: 'Relationship Summary',
            summary_text: 'A network analysis...',
            word_count: 100,
            key_points: [],
            metadata: [],
            quality_score: null,
            readability_score: null,
            is_current: true,
            regeneration_reason: null,
            cache_key: md5('test'),
            cache_expires_at: null,
            ai_model: 'gpt-4',
            token_count: 800,
            generation_cost: 0.04
        );
    }

    /**
     * Test word count calculation
     */
    public function test_calculates_word_count(): void
    {
        $text = 'This is a simple test with exactly ten words here.';
        $count = GeneratedSummary::calculateWordCount($text);

        $this->assertEquals(10, $count);
    }

    /**
     * Test readability score calculation
     */
    public function test_calculates_readability_score(): void
    {
        $text = 'This is a simple test. It has short sentences. Easy to read.';
        $score = GeneratedSummary::calculateReadabilityScore($text);

        $this->assertIsFloat($score);
        $this->assertGreaterThanOrEqual(0, $score);
        $this->assertLessThanOrEqual(100, $score);
    }

    /**
     * Test cache key generation
     */
    public function test_generates_cache_key(): void
    {
        $key1 = GeneratedSummary::generateCacheKey(
            1,
            SummaryType::CHARACTER_ARC,
            123,
            []
        );

        $key2 = GeneratedSummary::generateCacheKey(
            1,
            SummaryType::CHARACTER_ARC,
            123,
            []
        );

        $key3 = GeneratedSummary::generateCacheKey(
            1,
            SummaryType::CHARACTER_ARC,
            456, // Different entity
            []
        );

        $this->assertEquals($key1, $key2); // Same params = same key
        $this->assertNotEquals($key1, $key3); // Different params = different key
        $this->assertEquals(32, strlen($key1)); // MD5 hash length
    }

    /**
     * Test version increment
     */
    public function test_creates_new_version(): void
    {
        $original = new GeneratedSummary(
            id: 1,
            request_id: 1,
            saga_id: 1,
            entity_id: 123,
            summary_type: SummaryType::CHARACTER_ARC,
            version: 1,
            title: 'Original Summary',
            summary_text: 'Original text',
            word_count: 50,
            key_points: [],
            metadata: [],
            quality_score: 70.0,
            readability_score: 65.0,
            is_current: true,
            regeneration_reason: null,
            cache_key: md5('test'),
            cache_expires_at: null,
            ai_model: 'gpt-4',
            token_count: 500,
            generation_cost: 0.03
        );

        $new_version = $original->withNewVersion('User requested update');

        $this->assertNull($new_version->id); // New record
        $this->assertEquals(2, $new_version->version);
        $this->assertTrue($new_version->is_current);
        $this->assertEquals('User requested update', $new_version->regeneration_reason);
        $this->assertEquals(1, $original->version); // Original unchanged
    }

    /**
     * Test marking as old version
     */
    public function test_marks_as_old_version(): void
    {
        $summary = new GeneratedSummary(
            id: 1,
            request_id: 1,
            saga_id: 1,
            entity_id: null,
            summary_type: SummaryType::TIMELINE,
            version: 1,
            title: 'Timeline Summary',
            summary_text: 'Summary text',
            word_count: 100,
            key_points: [],
            metadata: [],
            quality_score: null,
            readability_score: null,
            is_current: true,
            regeneration_reason: null,
            cache_key: md5('test'),
            cache_expires_at: null,
            ai_model: 'gpt-4',
            token_count: 1000,
            generation_cost: 0.05,
            created_at: time() - 3600
        );

        $updated = $summary->markAsOldVersion();

        $this->assertFalse($updated->is_current);
        $this->assertTrue($summary->is_current); // Original unchanged
    }

    /**
     * Test cache expiration update
     */
    public function test_updates_cache_expiration(): void
    {
        $summary = new GeneratedSummary(
            id: 1,
            request_id: 1,
            saga_id: 1,
            entity_id: null,
            summary_type: SummaryType::RELATIONSHIP,
            version: 1,
            title: 'Test Summary',
            summary_text: 'Test text',
            word_count: 50,
            key_points: [],
            metadata: [],
            quality_score: null,
            readability_score: null,
            is_current: true,
            regeneration_reason: null,
            cache_key: md5('test'),
            cache_expires_at: null,
            ai_model: 'gpt-4',
            token_count: 500,
            generation_cost: 0.02
        );

        $ttl = 7200; // 2 hours
        $updated = $summary->withCacheExpiration($ttl);

        $this->assertNotNull($updated->cache_expires_at);
        $this->assertGreaterThan(time(), $updated->cache_expires_at);
    }

    /**
     * Test cache expiration check
     */
    public function test_checks_cache_expiration(): void
    {
        $not_expired = new GeneratedSummary(
            id: 1,
            request_id: 1,
            saga_id: 1,
            entity_id: null,
            summary_type: SummaryType::TIMELINE,
            version: 1,
            title: 'Test',
            summary_text: 'Text',
            word_count: 50,
            key_points: [],
            metadata: [],
            quality_score: null,
            readability_score: null,
            is_current: true,
            regeneration_reason: null,
            cache_key: md5('test1'),
            cache_expires_at: time() + 3600, // Future
            ai_model: 'gpt-4',
            token_count: 500,
            generation_cost: 0.02
        );

        $expired = new GeneratedSummary(
            id: 2,
            request_id: 2,
            saga_id: 1,
            entity_id: null,
            summary_type: SummaryType::TIMELINE,
            version: 1,
            title: 'Test',
            summary_text: 'Text',
            word_count: 50,
            key_points: [],
            metadata: [],
            quality_score: null,
            readability_score: null,
            is_current: true,
            regeneration_reason: null,
            cache_key: md5('test2'),
            cache_expires_at: time() - 3600, // Past
            ai_model: 'gpt-4',
            token_count: 500,
            generation_cost: 0.02
        );

        $this->assertFalse($not_expired->isCacheExpired());
        $this->assertTrue($expired->isCacheExpired());
    }

    /**
     * Test quality label
     */
    public function test_gets_quality_label(): void
    {
        $excellent = new GeneratedSummary(
            id: 1,
            request_id: 1,
            saga_id: 1,
            entity_id: null,
            summary_type: SummaryType::CHARACTER_ARC,
            version: 1,
            title: 'Test',
            summary_text: 'Text',
            word_count: 100,
            key_points: [],
            metadata: [],
            quality_score: 95.0,
            readability_score: null,
            is_current: true,
            regeneration_reason: null,
            cache_key: md5('test'),
            cache_expires_at: null,
            ai_model: 'gpt-4',
            token_count: 1000,
            generation_cost: 0.05
        );

        $this->assertEquals('Excellent', $excellent->getQualityLabel());

        $poor = new GeneratedSummary(
            id: 2,
            request_id: 2,
            saga_id: 1,
            entity_id: null,
            summary_type: SummaryType::TIMELINE,
            version: 1,
            title: 'Test',
            summary_text: 'Text',
            word_count: 50,
            key_points: [],
            metadata: [],
            quality_score: 45.0,
            readability_score: null,
            is_current: true,
            regeneration_reason: null,
            cache_key: md5('test2'),
            cache_expires_at: null,
            ai_model: 'gpt-4',
            token_count: 500,
            generation_cost: 0.02
        );

        $this->assertEquals('Poor', $poor->getQualityLabel());
    }

    /**
     * Test source reference detection
     */
    public function test_detects_source_references(): void
    {
        $with_sources = new GeneratedSummary(
            id: 1,
            request_id: 1,
            saga_id: 1,
            entity_id: 123,
            summary_type: SummaryType::CHARACTER_ARC,
            version: 1,
            title: 'Test',
            summary_text: 'Text',
            word_count: 100,
            key_points: [],
            metadata: [
                'source_entities' => [1, 2, 3],
                'source_events' => [10, 20],
            ],
            quality_score: null,
            readability_score: null,
            is_current: true,
            regeneration_reason: null,
            cache_key: md5('test'),
            cache_expires_at: null,
            ai_model: 'gpt-4',
            token_count: 1000,
            generation_cost: 0.05
        );

        $without_sources = new GeneratedSummary(
            id: 2,
            request_id: 2,
            saga_id: 1,
            entity_id: null,
            summary_type: SummaryType::TIMELINE,
            version: 1,
            title: 'Test',
            summary_text: 'Text',
            word_count: 50,
            key_points: [],
            metadata: [],
            quality_score: null,
            readability_score: null,
            is_current: true,
            regeneration_reason: null,
            cache_key: md5('test2'),
            cache_expires_at: null,
            ai_model: 'gpt-4',
            token_count: 500,
            generation_cost: 0.02
        );

        $this->assertTrue($with_sources->hasSourceReferences());
        $this->assertEquals(5, $with_sources->getSourceReferenceCount());
        $this->assertFalse($without_sources->hasSourceReferences());
        $this->assertEquals(0, $without_sources->getSourceReferenceCount());
    }

    /**
     * Test key point extraction
     */
    public function test_extracts_key_points(): void
    {
        $markdown_list = "Summary text\n\n- First point\n- Second point\n- Third point";
        $points = GeneratedSummary::extractKeyPoints($markdown_list, 5);

        $this->assertCount(3, $points);
        $this->assertEquals('First point', $points[0]);
    }

    /**
     * Test array conversion
     */
    public function test_converts_to_array(): void
    {
        $summary = new GeneratedSummary(
            id: 1,
            request_id: 1,
            saga_id: 1,
            entity_id: 123,
            summary_type: SummaryType::CHARACTER_ARC,
            version: 2,
            title: 'Character Summary',
            summary_text: 'Detailed summary...',
            word_count: 200,
            key_points: ['Point 1', 'Point 2'],
            metadata: ['source_entities' => [1, 2]],
            quality_score: 88.0,
            readability_score: 72.0,
            is_current: true,
            regeneration_reason: 'User request',
            cache_key: md5('test'),
            cache_expires_at: time() + 3600,
            ai_model: 'gpt-4',
            token_count: 2500,
            generation_cost: 0.12
        );

        $array = $summary->toArray();

        $this->assertIsArray($array);
        $this->assertEquals(1, $array['id']);
        $this->assertEquals('character_arc', $array['summary_type']);
        $this->assertEquals('Character Arc', $array['summary_type_label']);
        $this->assertEquals(2, $array['version']);
        $this->assertEquals('Good', $array['quality_label']);
        $this->assertTrue($array['has_source_references']);
        $this->assertEquals(2, $array['source_reference_count']);
    }
}
