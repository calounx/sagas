<?php
/**
 * Integration Tests for Summary Generation Workflow
 *
 * @package SagaManager
 * @subpackage Tests\Integration\SummaryGenerator
 */

declare(strict_types=1);

namespace SagaManager\Tests\Integration\SummaryGenerator;

use SagaManager\Tests\TestCase;
use SagaManager\AI\Entities\SummaryRequest;
use SagaManager\AI\Entities\SummaryType;
use SagaManager\AI\Entities\SummaryScope;
use SagaManager\AI\Entities\RequestStatus;
use SagaManager\AI\Entities\AIProvider;
use SagaManager\AI\SummaryRepository;

class SummaryWorkflowTest extends TestCase
{
    private SummaryRepository $repository;

    public function setUp(): void
    {
        parent::setUp();
        $this->repository = new SummaryRepository();

        // Ensure tables exist
        $this->assertTableExists('saga_summary_requests');
        $this->assertTableExists('saga_generated_summaries');
        $this->assertTableExists('saga_summary_templates');
        $this->assertTableExists('saga_summary_feedback');
    }

    /**
     * Test complete summary generation workflow
     */
    public function test_complete_summary_workflow(): void
    {
        // 1. Create saga and entities
        $saga_id = $this->createSaga(['name' => 'Star Wars Test']);
        $entity_id = $this->createEntity($saga_id, [
            'entity_type' => 'character',
            'canonical_name' => 'Luke Skywalker'
        ]);

        // 2. Create summary request
        $request = new SummaryRequest(
            id: null,
            saga_id: $saga_id,
            user_id: $this->createTestUser(),
            summary_type: SummaryType::CHARACTER_ARC,
            entity_id: $entity_id,
            scope: SummaryScope::FULL,
            scope_params: [],
            status: RequestStatus::PENDING,
            priority: 5,
            ai_provider: AIProvider::OPENAI,
            ai_model: 'gpt-4'
        );

        $request_id = $this->repository->createRequest($request);
        $this->assertGreaterThan(0, $request_id);

        // 3. Verify request was stored
        $stored_request = $this->repository->findRequestById($request_id);
        $this->assertNotNull($stored_request);
        $this->assertEquals($saga_id, $stored_request->saga_id);
        $this->assertEquals($entity_id, $stored_request->entity_id);
        $this->assertEquals(RequestStatus::PENDING, $stored_request->status);

        // 4. Update request to generating
        $generating = $stored_request->withStatus(RequestStatus::GENERATING);
        $this->repository->updateRequest($generating);

        // 5. Verify status update
        $updated = $this->repository->findRequestById($request_id);
        $this->assertEquals(RequestStatus::GENERATING, $updated->status);
        $this->assertNotNull($updated->started_at);

        // 6. Complete request
        $completed = $updated->withStatus(RequestStatus::COMPLETED);
        $this->repository->updateRequest($completed);

        // 7. Verify completion
        $final = $this->repository->findRequestById($request_id);
        $this->assertEquals(RequestStatus::COMPLETED, $final->status);
        $this->assertNotNull($final->completed_at);
        $this->assertNotNull($final->processing_time);
    }

    /**
     * Test summary storage and retrieval
     */
    public function test_summary_storage_and_retrieval(): void
    {
        global $wpdb;

        $saga_id = $this->createSaga();
        $entity_id = $this->createEntity($saga_id);

        $request = new SummaryRequest(
            id: null,
            saga_id: $saga_id,
            user_id: $this->createTestUser(),
            summary_type: SummaryType::CHARACTER_ARC,
            entity_id: $entity_id,
            scope: SummaryScope::FULL,
            scope_params: [],
            status: RequestStatus::PENDING,
            priority: 5,
            ai_provider: AIProvider::OPENAI,
            ai_model: 'gpt-4'
        );

        $request_id = $this->repository->createRequest($request);

        // Create summary data
        $summary_data = [
            'request_id' => $request_id,
            'saga_id' => $saga_id,
            'entity_id' => $entity_id,
            'summary_type' => 'character_arc',
            'version' => 1,
            'title' => 'Test Character Arc',
            'summary_text' => 'This is a test summary with detailed character analysis.',
            'word_count' => 9,
            'key_points' => json_encode(['Point 1', 'Point 2']),
            'metadata' => json_encode(['source_entities' => [$entity_id]]),
            'quality_score' => 85.0,
            'readability_score' => 75.0,
            'is_current' => 1,
            'cache_key' => md5('test_key'),
            'ai_model' => 'gpt-4',
            'token_count' => 1500,
            'generation_cost' => 0.08,
        ];

        $result = $wpdb->insert(
            $wpdb->prefix . 'saga_generated_summaries',
            $summary_data
        );

        $this->assertEquals(1, $result);
        $summary_id = $wpdb->insert_id;

        // Retrieve summary
        $summary = $this->repository->findById($summary_id);
        $this->assertNotNull($summary);
        $this->assertEquals('Test Character Arc', $summary->title);
        $this->assertEquals(9, $summary->word_count);
        $this->assertEquals(85.0, $summary->quality_score);
        $this->assertTrue($summary->is_current);
    }

    /**
     * Test finding summaries by saga
     */
    public function test_finds_summaries_by_saga(): void
    {
        $saga_id = $this->createSaga();
        $user_id = $this->createTestUser();

        // Create multiple summary requests
        for ($i = 0; $i < 3; $i++) {
            $request = new SummaryRequest(
                id: null,
                saga_id: $saga_id,
                user_id: $user_id,
                summary_type: SummaryType::TIMELINE,
                entity_id: null,
                scope: SummaryScope::FULL,
                scope_params: [],
                status: RequestStatus::COMPLETED,
                priority: 5,
                ai_provider: AIProvider::OPENAI,
                ai_model: 'gpt-4'
            );

            $this->repository->createRequest($request);
        }

        // Find all requests for saga
        $summaries = $this->repository->findBySaga($saga_id, []);
        $this->assertGreaterThanOrEqual(3, count($summaries));
    }

    /**
     * Test cache key lookup
     */
    public function test_finds_summary_by_cache_key(): void
    {
        global $wpdb;

        $saga_id = $this->createSaga();
        $request_id = $this->repository->createRequest(
            new SummaryRequest(
                id: null,
                saga_id: $saga_id,
                user_id: $this->createTestUser(),
                summary_type: SummaryType::RELATIONSHIP,
                entity_id: null,
                scope: SummaryScope::FULL,
                scope_params: [],
                status: RequestStatus::COMPLETED,
                priority: 5,
                ai_provider: AIProvider::ANTHROPIC,
                ai_model: 'claude-3-opus'
            )
        );

        $cache_key = md5('unique_test_key_' . time());

        // Insert summary
        $wpdb->insert(
            $wpdb->prefix . 'saga_generated_summaries',
            [
                'request_id' => $request_id,
                'saga_id' => $saga_id,
                'summary_type' => 'relationship',
                'version' => 1,
                'title' => 'Cached Summary',
                'summary_text' => 'Test content',
                'word_count' => 2,
                'key_points' => json_encode([]),
                'metadata' => json_encode([]),
                'is_current' => 1,
                'cache_key' => $cache_key,
                'ai_model' => 'claude-3-opus',
                'token_count' => 500,
                'generation_cost' => 0.03,
            ]
        );

        // Find by cache key
        $summary = $this->repository->findByCacheKey($cache_key);
        $this->assertNotNull($summary);
        $this->assertEquals($cache_key, $summary->cache_key);
        $this->assertEquals('Cached Summary', $summary->title);
    }

    /**
     * Test summary statistics calculation
     */
    public function test_calculates_summary_statistics(): void
    {
        global $wpdb;

        $saga_id = $this->createSaga();
        $user_id = $this->createTestUser();

        // Create multiple summaries with different quality scores
        $qualities = [90.0, 85.0, 75.0];

        foreach ($qualities as $quality) {
            $request_id = $this->repository->createRequest(
                new SummaryRequest(
                    id: null,
                    saga_id: $saga_id,
                    user_id: $user_id,
                    summary_type: SummaryType::TIMELINE,
                    entity_id: null,
                    scope: SummaryScope::FULL,
                    scope_params: [],
                    status: RequestStatus::COMPLETED,
                    priority: 5,
                    ai_provider: AIProvider::OPENAI,
                    ai_model: 'gpt-4'
                )
            );

            $wpdb->insert(
                $wpdb->prefix . 'saga_generated_summaries',
                [
                    'request_id' => $request_id,
                    'saga_id' => $saga_id,
                    'summary_type' => 'timeline',
                    'version' => 1,
                    'title' => 'Test Summary',
                    'summary_text' => 'Test',
                    'word_count' => 1,
                    'key_points' => json_encode([]),
                    'metadata' => json_encode([]),
                    'quality_score' => $quality,
                    'is_current' => 1,
                    'cache_key' => md5('test_' . $quality),
                    'ai_model' => 'gpt-4',
                    'token_count' => 1000,
                    'generation_cost' => 0.05,
                ]
            );
        }

        $stats = $this->repository->getStatistics($saga_id);

        $this->assertIsArray($stats);
        $this->assertArrayHasKey('total_summaries', $stats);
        $this->assertArrayHasKey('avg_quality_score', $stats);
        $this->assertGreaterThanOrEqual(3, $stats['total_summaries']);
        $this->assertGreaterThan(0, $stats['avg_quality_score']);
    }

    /**
     * Test version management
     */
    public function test_manages_summary_versions(): void
    {
        global $wpdb;

        $saga_id = $this->createSaga();
        $entity_id = $this->createEntity($saga_id);

        $request_id = $this->repository->createRequest(
            new SummaryRequest(
                id: null,
                saga_id: $saga_id,
                user_id: $this->createTestUser(),
                summary_type: SummaryType::CHARACTER_ARC,
                entity_id: $entity_id,
                scope: SummaryScope::FULL,
                scope_params: [],
                status: RequestStatus::COMPLETED,
                priority: 5,
                ai_provider: AIProvider::OPENAI,
                ai_model: 'gpt-4'
            )
        );

        // Insert version 1
        $wpdb->insert(
            $wpdb->prefix . 'saga_generated_summaries',
            [
                'request_id' => $request_id,
                'saga_id' => $saga_id,
                'entity_id' => $entity_id,
                'summary_type' => 'character_arc',
                'version' => 1,
                'title' => 'Version 1',
                'summary_text' => 'First version',
                'word_count' => 2,
                'key_points' => json_encode([]),
                'metadata' => json_encode([]),
                'is_current' => 1,
                'cache_key' => md5('v1'),
                'ai_model' => 'gpt-4',
                'token_count' => 500,
                'generation_cost' => 0.02,
            ]
        );

        $v1_id = $wpdb->insert_id;

        // Insert version 2
        $wpdb->insert(
            $wpdb->prefix . 'saga_generated_summaries',
            [
                'request_id' => $request_id,
                'saga_id' => $saga_id,
                'entity_id' => $entity_id,
                'summary_type' => 'character_arc',
                'version' => 2,
                'title' => 'Version 2',
                'summary_text' => 'Second version',
                'word_count' => 2,
                'key_points' => json_encode([]),
                'metadata' => json_encode([]),
                'is_current' => 1,
                'regeneration_reason' => 'User requested update',
                'cache_key' => md5('v2'),
                'ai_model' => 'gpt-4',
                'token_count' => 600,
                'generation_cost' => 0.03,
            ]
        );

        $v2_id = $wpdb->insert_id;

        // Mark v1 as old
        $wpdb->update(
            $wpdb->prefix . 'saga_generated_summaries',
            ['is_current' => 0],
            ['id' => $v1_id]
        );

        // Verify versions
        $current = $this->repository->findByRequest($request_id);
        $this->assertNotNull($current);
        $this->assertEquals(2, $current->version);
        $this->assertEquals('Version 2', $current->title);
        $this->assertTrue($current->is_current);
    }

    /**
     * Test cascade delete on saga deletion
     */
    public function test_cascades_delete_on_saga_deletion(): void
    {
        global $wpdb;

        $saga_id = $this->createSaga();
        $user_id = $this->createTestUser();

        // Create summary request
        $request = new SummaryRequest(
            id: null,
            saga_id: $saga_id,
            user_id: $user_id,
            summary_type: SummaryType::TIMELINE,
            entity_id: null,
            scope: SummaryScope::FULL,
            scope_params: [],
            status: RequestStatus::COMPLETED,
            priority: 5,
            ai_provider: AIProvider::OPENAI,
            ai_model: 'gpt-4'
        );

        $request_id = $this->repository->createRequest($request);

        // Verify request exists
        $count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}saga_summary_requests WHERE id = %d",
            $request_id
        ));
        $this->assertEquals(1, $count);

        // Delete saga
        $wpdb->delete(
            $wpdb->prefix . 'saga_sagas',
            ['id' => $saga_id]
        );

        // Verify request was cascaded
        $count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}saga_summary_requests WHERE id = %d",
            $request_id
        ));
        $this->assertEquals(0, $count);
    }

    /**
     * Test summary search
     */
    public function test_searches_summaries(): void
    {
        global $wpdb;

        $saga_id = $this->createSaga();
        $request_id = $this->repository->createRequest(
            new SummaryRequest(
                id: null,
                saga_id: $saga_id,
                user_id: $this->createTestUser(),
                summary_type: SummaryType::CHARACTER_ARC,
                entity_id: $this->createEntity($saga_id),
                scope: SummaryScope::FULL,
                scope_params: [],
                status: RequestStatus::COMPLETED,
                priority: 5,
                ai_provider: AIProvider::OPENAI,
                ai_model: 'gpt-4'
            )
        );

        // Insert searchable summary
        $wpdb->insert(
            $wpdb->prefix . 'saga_generated_summaries',
            [
                'request_id' => $request_id,
                'saga_id' => $saga_id,
                'summary_type' => 'character_arc',
                'version' => 1,
                'title' => 'Luke Skywalker Journey',
                'summary_text' => 'The young moisture farmer becomes a legendary Jedi Knight',
                'word_count' => 9,
                'key_points' => json_encode([]),
                'metadata' => json_encode([]),
                'is_current' => 1,
                'cache_key' => md5('search_test'),
                'ai_model' => 'gpt-4',
                'token_count' => 1000,
                'generation_cost' => 0.05,
            ]
        );

        // Search for "Jedi"
        $results = $this->repository->search($saga_id, 'Jedi');

        $this->assertIsArray($results);
        $this->assertGreaterThan(0, count($results));

        $found = false;
        foreach ($results as $result) {
            if (strpos($result->summary_text, 'Jedi') !== false) {
                $found = true;
                break;
            }
        }
        $this->assertTrue($found);
    }
}
