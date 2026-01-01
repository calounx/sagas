<?php
/**
 * Extraction Workflow Integration Tests
 *
 * Tests the complete entity extraction workflow from job creation to batch entity creation
 *
 * @package SagaManager\Tests\Integration\EntityExtractor
 * @since 1.4.0
 */

declare(strict_types=1);

namespace SagaManager\Tests\Integration\EntityExtractor;

use SagaManager\Tests\TestCase;

class ExtractionWorkflowTest extends TestCase
{
    /**
     * Test extraction jobs table exists
     */
    public function test_extraction_jobs_table_exists(): void
    {
        $this->assertTableExists('saga_extraction_jobs');
        $this->assertTableHasColumns('saga_extraction_jobs', [
            'id', 'saga_id', 'user_id', 'source_text', 'status',
            'total_entities_found', 'entities_created', 'ai_provider'
        ]);
    }

    /**
     * Test extracted entities table exists
     */
    public function test_extracted_entities_table_exists(): void
    {
        $this->assertTableExists('saga_extracted_entities');
        $this->assertTableHasColumns('saga_extracted_entities', [
            'id', 'job_id', 'entity_type', 'canonical_name',
            'confidence_score', 'status', 'created_entity_id'
        ]);
    }

    /**
     * Test complete extraction workflow
     */
    public function test_complete_extraction_workflow(): void
    {
        global $wpdb;

        $sagaId = $this->createSaga();
        $userId = $this->createTestUser();

        // Step 1: Create extraction job
        $jobId = $this->createExtractionJob($sagaId, $userId, [
            'status' => 'pending',
            'source_text' => 'Luke Skywalker was a Jedi Knight...'
        ]);

        $this->assertGreaterThan(0, $jobId);

        // Step 2: Create extracted entities
        $entityId1 = $this->createExtractedEntity($jobId, [
            'entity_type' => 'character',
            'canonical_name' => 'Luke Skywalker',
            'confidence_score' => 95.0,
            'status' => 'pending'
        ]);

        $entityId2 = $this->createExtractedEntity($jobId, [
            'entity_type' => 'faction',
            'canonical_name' => 'Jedi Order',
            'confidence_score' => 88.0,
            'status' => 'pending'
        ]);

        // Step 3: User approves entities
        $wpdb->update(
            $wpdb->prefix . 'saga_extracted_entities',
            ['status' => 'approved', 'reviewed_by' => $userId],
            ['id' => $entityId1]
        );

        $wpdb->update(
            $wpdb->prefix . 'saga_extracted_entities',
            ['status' => 'approved', 'reviewed_by' => $userId],
            ['id' => $entityId2]
        );

        // Step 4: Create actual saga entities
        $createdEntity1 = $this->createEntity($sagaId, [
            'canonical_name' => 'Luke Skywalker',
            'entity_type' => 'character'
        ]);

        $createdEntity2 = $this->createEntity($sagaId, [
            'canonical_name' => 'Jedi Order',
            'entity_type' => 'faction'
        ]);

        // Step 5: Link created entities
        $wpdb->update(
            $wpdb->prefix . 'saga_extracted_entities',
            ['created_entity_id' => $createdEntity1, 'status' => 'created'],
            ['id' => $entityId1]
        );

        $wpdb->update(
            $wpdb->prefix . 'saga_extracted_entities',
            ['created_entity_id' => $createdEntity2, 'status' => 'created'],
            ['id' => $entityId2]
        );

        // Step 6: Update job statistics
        $wpdb->update(
            $wpdb->prefix . 'saga_extraction_jobs',
            [
                'status' => 'completed',
                'total_entities_found' => 2,
                'entities_created' => 2,
                'completed_at' => current_time('mysql')
            ],
            ['id' => $jobId]
        );

        // Verify workflow completed
        $job = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}saga_extraction_jobs WHERE id = %d",
            $jobId
        ), ARRAY_A);

        $this->assertEquals('completed', $job['status']);
        $this->assertEquals(2, $job['entities_created']);

        $entities = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}saga_extracted_entities WHERE job_id = %d",
            $jobId
        ), ARRAY_A);

        $this->assertCount(2, $entities);
        foreach ($entities as $entity) {
            $this->assertEquals('created', $entity['status']);
            $this->assertNotNull($entity['created_entity_id']);
        }
    }

    /**
     * Test duplicate detection
     */
    public function test_duplicate_detection(): void
    {
        global $wpdb;

        $sagaId = $this->createSaga();
        $userId = $this->createTestUser();

        // Create existing entity
        $existingId = $this->createEntity($sagaId, [
            'canonical_name' => 'Luke Skywalker',
            'entity_type' => 'character'
        ]);

        // Create extraction job
        $jobId = $this->createExtractionJob($sagaId, $userId);

        // Extract duplicate
        $extractedId = $this->createExtractedEntity($jobId, [
            'canonical_name' => 'Luke Skywalker',
            'status' => 'pending'
        ]);

        // Mark as duplicate
        $wpdb->update(
            $wpdb->prefix . 'saga_extracted_entities',
            [
                'status' => 'duplicate',
                'duplicate_of' => $existingId,
                'duplicate_similarity' => 98.5
            ],
            ['id' => $extractedId]
        );

        $extracted = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}saga_extracted_entities WHERE id = %d",
            $extractedId
        ), ARRAY_A);

        $this->assertEquals('duplicate', $extracted['status']);
        $this->assertEquals($existingId, $extracted['duplicate_of']);
        $this->assertEquals(98.5, (float)$extracted['duplicate_similarity']);
    }

    /**
     * Test batch entity creation
     */
    public function test_batch_entity_creation(): void
    {
        global $wpdb;

        $sagaId = $this->createSaga();
        $userId = $this->createTestUser();
        $jobId = $this->createExtractionJob($sagaId, $userId);

        // Create multiple approved entities
        $extractedIds = [];
        for ($i = 1; $i <= 5; $i++) {
            $extractedIds[] = $this->createExtractedEntity($jobId, [
                'canonical_name' => "Entity {$i}",
                'status' => 'approved'
            ]);
        }

        // Batch create saga entities
        foreach ($extractedIds as $extractedId) {
            $extracted = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}saga_extracted_entities WHERE id = %d",
                $extractedId
            ), ARRAY_A);

            $createdId = $this->createEntity($sagaId, [
                'canonical_name' => $extracted['canonical_name'],
                'entity_type' => $extracted['entity_type']
            ]);

            $wpdb->update(
                $wpdb->prefix . 'saga_extracted_entities',
                ['created_entity_id' => $createdId, 'status' => 'created'],
                ['id' => $extractedId]
            );
        }

        // Verify all created
        $created = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}saga_extracted_entities
             WHERE job_id = %d AND status = 'created'",
            $jobId
        ));

        $this->assertEquals(5, $created);
    }

    /**
     * Test extraction job statistics
     */
    public function test_extraction_job_statistics(): void
    {
        global $wpdb;

        $sagaId = $this->createSaga();
        $userId = $this->createTestUser();
        $jobId = $this->createExtractionJob($sagaId, $userId);

        // Create entities with different statuses
        $this->createExtractedEntity($jobId, ['status' => 'approved']);
        $this->createExtractedEntity($jobId, ['status' => 'approved']);
        $this->createExtractedEntity($jobId, ['status' => 'rejected']);
        $this->createExtractedEntity($jobId, ['status' => 'duplicate']);

        // Calculate stats
        $stats = $wpdb->get_row($wpdb->prepare(
            "SELECT
                COUNT(*) as total,
                SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) as approved,
                SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) as rejected,
                SUM(CASE WHEN status = 'duplicate' THEN 1 ELSE 0 END) as duplicates,
                AVG(confidence_score) as avg_confidence
             FROM {$wpdb->prefix}saga_extracted_entities
             WHERE job_id = %d",
            $jobId
        ), ARRAY_A);

        $this->assertEquals(4, $stats['total']);
        $this->assertEquals(2, $stats['approved']);
        $this->assertEquals(1, $stats['rejected']);
        $this->assertEquals(1, $stats['duplicates']);
    }

    /**
     * Test feedback recording
     */
    public function test_feedback_recording(): void
    {
        global $wpdb;

        $sagaId = $this->createSaga();
        $userId = $this->createTestUser();
        $jobId = $this->createExtractionJob($sagaId, $userId);

        $extractedId = $this->createExtractedEntity($jobId, [
            'status' => 'pending'
        ]);

        // User rejects with feedback
        $wpdb->update(
            $wpdb->prefix . 'saga_extracted_entities',
            [
                'status' => 'rejected',
                'reviewed_by' => $userId,
                'reviewed_at' => current_time('mysql')
            ],
            ['id' => $extractedId]
        );

        $entity = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}saga_extracted_entities WHERE id = %d",
            $extractedId
        ), ARRAY_A);

        $this->assertEquals('rejected', $entity['status']);
        $this->assertEquals($userId, $entity['reviewed_by']);
        $this->assertNotNull($entity['reviewed_at']);
    }

    /**
     * Test cascade delete when job is deleted
     */
    public function test_cascade_delete_extracted_entities(): void
    {
        global $wpdb;

        $sagaId = $this->createSaga();
        $userId = $this->createTestUser();
        $jobId = $this->createExtractionJob($sagaId, $userId);

        $this->createExtractedEntity($jobId);
        $this->createExtractedEntity($jobId);

        // Delete job
        $wpdb->delete(
            $wpdb->prefix . 'saga_extraction_jobs',
            ['id' => $jobId]
        );

        // Check entities are deleted
        $remaining = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}saga_extracted_entities WHERE job_id = %d",
            $jobId
        ));

        $this->assertEquals(0, $remaining);
    }
}
