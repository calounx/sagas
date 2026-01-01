<?php
/**
 * ExtractedEntity Value Object Unit Tests
 *
 * @package SagaManager\Tests\Unit\EntityExtractor
 * @since 1.4.0
 */

declare(strict_types=1);

namespace SagaManager\Tests\Unit\EntityExtractor;

use SagaManager\Tests\TestCase;
use SagaManager\AI\EntityExtractor\Entities\ExtractedEntity;
use SagaManager\AI\EntityExtractor\Entities\ExtractedEntityStatus;
use SagaManager\AI\EntityExtractor\Entities\EntityType;

class ExtractedEntityTest extends TestCase
{
    public function test_can_create_extracted_entity(): void
    {
        $entity = new ExtractedEntity(
            id: 1,
            job_id: 10,
            entity_type: EntityType::CHARACTER,
            canonical_name: 'Luke Skywalker',
            alternative_names: ['Luke', 'Skywalker'],
            description: 'Jedi Knight',
            attributes: ['age' => 25, 'homeworld' => 'Tatooine'],
            context_snippet: 'Luke was a farm boy...',
            confidence_score: 85.0,
            chunk_index: 0,
            position_in_text: 100,
            status: ExtractedEntityStatus::PENDING,
            duplicate_of: null,
            duplicate_similarity: null,
            created_entity_id: null,
            reviewed_by: null,
            reviewed_at: null,
            created_at: time()
        );

        $this->assertInstanceOf(ExtractedEntity::class, $entity);
        $this->assertEquals('Luke Skywalker', $entity->canonical_name);
        $this->assertEquals(85.0, $entity->confidence_score);
        $this->assertEquals(EntityType::CHARACTER, $entity->entity_type);
    }

    public function test_validates_empty_canonical_name(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Canonical name cannot be empty');

        new ExtractedEntity(
            id: null,
            job_id: 1,
            entity_type: EntityType::CHARACTER,
            canonical_name: '   ',
            alternative_names: [],
            description: null,
            attributes: [],
            context_snippet: null,
            confidence_score: 50.0,
            chunk_index: 0,
            position_in_text: null,
            status: ExtractedEntityStatus::PENDING,
            duplicate_of: null,
            duplicate_similarity: null,
            created_entity_id: null,
            reviewed_by: null,
            reviewed_at: null,
            created_at: time()
        );
    }

    public function test_validates_confidence_score_range(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Confidence score must be between 0 and 100');

        new ExtractedEntity(
            id: null,
            job_id: 1,
            entity_type: EntityType::CHARACTER,
            canonical_name: 'Test',
            alternative_names: [],
            description: null,
            attributes: [],
            context_snippet: null,
            confidence_score: 150.0,
            chunk_index: 0,
            position_in_text: null,
            status: ExtractedEntityStatus::PENDING,
            duplicate_of: null,
            duplicate_similarity: null,
            created_entity_id: null,
            reviewed_by: null,
            reviewed_at: null,
            created_at: time()
        );
    }

    public function test_validates_duplicate_similarity_range(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new ExtractedEntity(
            id: null,
            job_id: 1,
            entity_type: EntityType::CHARACTER,
            canonical_name: 'Test',
            alternative_names: [],
            description: null,
            attributes: [],
            context_snippet: null,
            confidence_score: 50.0,
            chunk_index: 0,
            position_in_text: null,
            status: ExtractedEntityStatus::PENDING,
            duplicate_of: null,
            duplicate_similarity: 110.0,
            created_entity_id: null,
            reviewed_by: null,
            reviewed_at: null,
            created_at: time()
        );
    }

    public function test_validates_chunk_index_not_negative(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new ExtractedEntity(
            id: null,
            job_id: 1,
            entity_type: EntityType::CHARACTER,
            canonical_name: 'Test',
            alternative_names: [],
            description: null,
            attributes: [],
            context_snippet: null,
            confidence_score: 50.0,
            chunk_index: -1,
            position_in_text: null,
            status: ExtractedEntityStatus::PENDING,
            duplicate_of: null,
            duplicate_similarity: null,
            created_entity_id: null,
            reviewed_by: null,
            reviewed_at: null,
            created_at: time()
        );
    }

    public function test_get_confidence_level(): void
    {
        $highEntity = new ExtractedEntity(
            id: null, job_id: 1, entity_type: EntityType::CHARACTER,
            canonical_name: 'High', alternative_names: [], description: null,
            attributes: [], context_snippet: null, confidence_score: 85.0,
            chunk_index: 0, position_in_text: null, status: ExtractedEntityStatus::PENDING,
            duplicate_of: null, duplicate_similarity: null, created_entity_id: null,
            reviewed_by: null, reviewed_at: null, created_at: time()
        );

        $mediumEntity = new ExtractedEntity(
            id: null, job_id: 1, entity_type: EntityType::CHARACTER,
            canonical_name: 'Medium', alternative_names: [], description: null,
            attributes: [], context_snippet: null, confidence_score: 65.0,
            chunk_index: 0, position_in_text: null, status: ExtractedEntityStatus::PENDING,
            duplicate_of: null, duplicate_similarity: null, created_entity_id: null,
            reviewed_by: null, reviewed_at: null, created_at: time()
        );

        $lowEntity = new ExtractedEntity(
            id: null, job_id: 1, entity_type: EntityType::CHARACTER,
            canonical_name: 'Low', alternative_names: [], description: null,
            attributes: [], context_snippet: null, confidence_score: 45.0,
            chunk_index: 0, position_in_text: null, status: ExtractedEntityStatus::PENDING,
            duplicate_of: null, duplicate_similarity: null, created_entity_id: null,
            reviewed_by: null, reviewed_at: null, created_at: time()
        );

        $this->assertEquals('high', $highEntity->getConfidenceLevel());
        $this->assertEquals('medium', $mediumEntity->getConfidenceLevel());
        $this->assertEquals('low', $lowEntity->getConfidenceLevel());
    }

    public function test_approve_changes_status(): void
    {
        $entity = new ExtractedEntity(
            id: 1, job_id: 1, entity_type: EntityType::CHARACTER,
            canonical_name: 'Test', alternative_names: [], description: null,
            attributes: [], context_snippet: null, confidence_score: 50.0,
            chunk_index: 0, position_in_text: null, status: ExtractedEntityStatus::PENDING,
            duplicate_of: null, duplicate_similarity: null, created_entity_id: null,
            reviewed_by: null, reviewed_at: null, created_at: time()
        );

        $approved = $entity->approve(5);

        $this->assertEquals(ExtractedEntityStatus::APPROVED, $approved->status);
        $this->assertEquals(5, $approved->reviewed_by);
        $this->assertNotNull($approved->reviewed_at);

        // Original unchanged
        $this->assertEquals(ExtractedEntityStatus::PENDING, $entity->status);
    }

    public function test_reject_changes_status(): void
    {
        $entity = new ExtractedEntity(
            id: 1, job_id: 1, entity_type: EntityType::CHARACTER,
            canonical_name: 'Test', alternative_names: [], description: null,
            attributes: [], context_snippet: null, confidence_score: 50.0,
            chunk_index: 0, position_in_text: null, status: ExtractedEntityStatus::PENDING,
            duplicate_of: null, duplicate_similarity: null, created_entity_id: null,
            reviewed_by: null, reviewed_at: null, created_at: time()
        );

        $rejected = $entity->reject(3);

        $this->assertEquals(ExtractedEntityStatus::REJECTED, $rejected->status);
        $this->assertEquals(3, $rejected->reviewed_by);
    }

    public function test_mark_duplicate(): void
    {
        $entity = new ExtractedEntity(
            id: 1, job_id: 1, entity_type: EntityType::CHARACTER,
            canonical_name: 'Test', alternative_names: [], description: null,
            attributes: [], context_snippet: null, confidence_score: 50.0,
            chunk_index: 0, position_in_text: null, status: ExtractedEntityStatus::PENDING,
            duplicate_of: null, duplicate_similarity: null, created_entity_id: null,
            reviewed_by: null, reviewed_at: null, created_at: time()
        );

        $duplicate = $entity->markDuplicate(100, 95.5);

        $this->assertEquals(ExtractedEntityStatus::DUPLICATE, $duplicate->status);
        $this->assertEquals(100, $duplicate->duplicate_of);
        $this->assertEquals(95.5, $duplicate->duplicate_similarity);
    }

    public function test_mark_created(): void
    {
        $entity = new ExtractedEntity(
            id: 1, job_id: 1, entity_type: EntityType::CHARACTER,
            canonical_name: 'Test', alternative_names: [], description: null,
            attributes: [], context_snippet: null, confidence_score: 50.0,
            chunk_index: 0, position_in_text: null, status: ExtractedEntityStatus::APPROVED,
            duplicate_of: null, duplicate_similarity: null, created_entity_id: null,
            reviewed_by: null, reviewed_at: null, created_at: time()
        );

        $created = $entity->markCreated(200);

        $this->assertEquals(ExtractedEntityStatus::CREATED, $created->status);
        $this->assertEquals(200, $created->created_entity_id);
    }

    public function test_get_quality_score(): void
    {
        $entity = new ExtractedEntity(
            id: null, job_id: 1, entity_type: EntityType::CHARACTER,
            canonical_name: 'Test',
            alternative_names: ['Alias1', 'Alias2'],
            description: 'A detailed description',
            attributes: ['key' => 'value'],
            context_snippet: 'Some context text',
            confidence_score: 80.0,
            chunk_index: 0, position_in_text: null, status: ExtractedEntityStatus::PENDING,
            duplicate_of: null, duplicate_similarity: null, created_entity_id: null,
            reviewed_by: null, reviewed_at: null, created_at: time()
        );

        $quality = $entity->getQualityScore();

        // Should be high due to completeness and confidence
        $this->assertGreaterThan(70.0, $quality);
        $this->assertLessThanOrEqual(100.0, $quality);
    }

    public function test_get_all_names(): void
    {
        $entity = new ExtractedEntity(
            id: null, job_id: 1, entity_type: EntityType::CHARACTER,
            canonical_name: 'Luke Skywalker',
            alternative_names: ['Luke', 'Skywalker'],
            description: null, attributes: [], context_snippet: null,
            confidence_score: 50.0, chunk_index: 0, position_in_text: null,
            status: ExtractedEntityStatus::PENDING, duplicate_of: null,
            duplicate_similarity: null, created_entity_id: null,
            reviewed_by: null, reviewed_at: null, created_at: time()
        );

        $names = $entity->getAllNames();

        $this->assertContains('Luke Skywalker', $names);
        $this->assertContains('Luke', $names);
        $this->assertContains('Skywalker', $names);
    }

    public function test_has_attribute(): void
    {
        $entity = new ExtractedEntity(
            id: null, job_id: 1, entity_type: EntityType::CHARACTER,
            canonical_name: 'Test', alternative_names: [],
            description: null, attributes: ['age' => 25, 'role' => 'hero'],
            context_snippet: null, confidence_score: 50.0,
            chunk_index: 0, position_in_text: null, status: ExtractedEntityStatus::PENDING,
            duplicate_of: null, duplicate_similarity: null, created_entity_id: null,
            reviewed_by: null, reviewed_at: null, created_at: time()
        );

        $this->assertTrue($entity->hasAttribute('age'));
        $this->assertTrue($entity->hasAttribute('role'));
        $this->assertFalse($entity->hasAttribute('missing'));
    }

    public function test_get_attribute(): void
    {
        $entity = new ExtractedEntity(
            id: null, job_id: 1, entity_type: EntityType::CHARACTER,
            canonical_name: 'Test', alternative_names: [],
            description: null, attributes: ['age' => 25],
            context_snippet: null, confidence_score: 50.0,
            chunk_index: 0, position_in_text: null, status: ExtractedEntityStatus::PENDING,
            duplicate_of: null, duplicate_similarity: null, created_entity_id: null,
            reviewed_by: null, reviewed_at: null, created_at: time()
        );

        $this->assertEquals(25, $entity->getAttribute('age'));
        $this->assertEquals('default', $entity->getAttribute('missing', 'default'));
    }

    public function test_needs_review(): void
    {
        $pending = new ExtractedEntity(
            id: null, job_id: 1, entity_type: EntityType::CHARACTER,
            canonical_name: 'Test', alternative_names: [], description: null,
            attributes: [], context_snippet: null, confidence_score: 50.0,
            chunk_index: 0, position_in_text: null, status: ExtractedEntityStatus::PENDING,
            duplicate_of: null, duplicate_similarity: null, created_entity_id: null,
            reviewed_by: null, reviewed_at: null, created_at: time()
        );

        $rejected = new ExtractedEntity(
            id: null, job_id: 1, entity_type: EntityType::CHARACTER,
            canonical_name: 'Test', alternative_names: [], description: null,
            attributes: [], context_snippet: null, confidence_score: 50.0,
            chunk_index: 0, position_in_text: null, status: ExtractedEntityStatus::REJECTED,
            duplicate_of: null, duplicate_similarity: null, created_entity_id: null,
            reviewed_by: null, reviewed_at: null, created_at: time()
        );

        $this->assertTrue($pending->needsReview());
        $this->assertFalse($rejected->needsReview());
    }

    public function test_converts_to_array(): void
    {
        $entity = new ExtractedEntity(
            id: 1, job_id: 10, entity_type: EntityType::LOCATION,
            canonical_name: 'Tatooine', alternative_names: ['Tattoo'],
            description: 'Desert planet', attributes: ['climate' => 'arid'],
            context_snippet: 'A harsh desert world...', confidence_score: 90.0,
            chunk_index: 2, position_in_text: 500, status: ExtractedEntityStatus::APPROVED,
            duplicate_of: null, duplicate_similarity: null, created_entity_id: 150,
            reviewed_by: 5, reviewed_at: time(), created_at: time()
        );

        $array = $entity->toArray();

        $this->assertArrayHasKey('id', $array);
        $this->assertArrayHasKey('job_id', $array);
        $this->assertArrayHasKey('entity_type', $array);
        $this->assertArrayHasKey('canonical_name', $array);
        $this->assertArrayHasKey('confidence_score', $array);
        $this->assertEquals('location', $array['entity_type']);
        $this->assertEquals('approved', $array['status']);
    }

    public function test_creates_from_array(): void
    {
        $data = [
            'id' => 1,
            'job_id' => 10,
            'entity_type' => 'character',
            'canonical_name' => 'Han Solo',
            'alternative_names' => json_encode(['Han']),
            'description' => 'Smuggler',
            'attributes' => json_encode(['ship' => 'Millennium Falcon']),
            'context_snippet' => 'Han was a pilot...',
            'confidence_score' => 88.0,
            'chunk_index' => 1,
            'position_in_text' => 200,
            'status' => 'pending',
            'duplicate_of' => null,
            'duplicate_similarity' => null,
            'created_entity_id' => null,
            'reviewed_by' => null,
            'reviewed_at' => null,
            'created_at' => date('Y-m-d H:i:s')
        ];

        $entity = ExtractedEntity::fromArray($data);

        $this->assertInstanceOf(ExtractedEntity::class, $entity);
        $this->assertEquals('Han Solo', $entity->canonical_name);
        $this->assertEquals(EntityType::CHARACTER, $entity->entity_type);
        $this->assertEquals(88.0, $entity->confidence_score);
    }
}
