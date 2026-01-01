<?php
declare(strict_types=1);

/**
 * Integration test for Predictive Relationships feature
 *
 * Tests the complete workflow from generation to learning
 */

namespace SagaManager\Tests\Integration;

use SagaManager\AI\ValueObjects\RelationshipSuggestion;
use SagaManager\AI\ValueObjects\SuggestionFeature;
use SagaManager\AI\ValueObjects\SuggestionFeedback;
use SagaManager\AI\Services\FeatureExtractionService;
use SagaManager\AI\Services\RelationshipPredictionService;
use SagaManager\AI\Services\LearningService;
use SagaManager\AI\Services\SuggestionRepository;
use SagaManager\AI\SuggestionBackgroundProcessor;

/**
 * Integration test class
 *
 * Run with: vendor/bin/phpunit tests/integration/SuggestionsIntegrationTest.php
 */
class SuggestionsIntegrationTest extends \WP_UnitTestCase
{
    private SuggestionRepository $repository;
    private FeatureExtractionService $featureService;
    private RelationshipPredictionService $predictionService;
    private LearningService $learningService;
    private SuggestionBackgroundProcessor $backgroundProcessor;

    private int $sagaId;
    private int $entity1Id;
    private int $entity2Id;

    public function setUp(): void
    {
        parent::setUp();

        global $wpdb;

        // Initialize services
        $this->repository = new SuggestionRepository($wpdb);
        $this->featureService = new FeatureExtractionService($wpdb);
        $this->predictionService = new RelationshipPredictionService(
            $this->featureService,
            $this->repository
        );
        $this->learningService = new LearningService($this->repository);
        $this->backgroundProcessor = new SuggestionBackgroundProcessor(
            $this->predictionService,
            $this->repository
        );

        // Create test data
        $this->createTestData();
    }

    private function createTestData(): void
    {
        global $wpdb;

        // Create saga
        $wpdb->insert(
            $wpdb->prefix . 'saga_sagas',
            [
                'name' => 'Test Saga',
                'universe' => 'Test Universe',
                'calendar_type' => 'absolute',
                'calendar_config' => json_encode([]),
            ]
        );
        $this->sagaId = $wpdb->insert_id;

        // Create entities
        $wpdb->insert(
            $wpdb->prefix . 'saga_entities',
            [
                'saga_id' => $this->sagaId,
                'entity_type' => 'character',
                'canonical_name' => 'Luke Skywalker',
                'slug' => 'luke-skywalker',
                'importance_score' => 95,
            ]
        );
        $this->entity1Id = $wpdb->insert_id;

        $wpdb->insert(
            $wpdb->prefix . 'saga_entities',
            [
                'saga_id' => $this->sagaId,
                'entity_type' => 'character',
                'canonical_name' => 'Darth Vader',
                'slug' => 'darth-vader',
                'importance_score' => 90,
            ]
        );
        $this->entity2Id = $wpdb->insert_id;

        // Create some content fragments for co-occurrence
        $wpdb->insert(
            $wpdb->prefix . 'saga_content_fragments',
            [
                'entity_id' => $this->entity1Id,
                'fragment_text' => 'Luke Skywalker fought against Darth Vader in an epic battle.',
                'token_count' => 10,
            ]
        );

        $wpdb->insert(
            $wpdb->prefix . 'saga_content_fragments',
            [
                'entity_id' => $this->entity2Id,
                'fragment_text' => 'Darth Vader revealed he was Luke Skywalker\'s father.',
                'token_count' => 9,
            ]
        );
    }

    public function tearDown(): void
    {
        global $wpdb;

        // Clean up test data
        $wpdb->delete($wpdb->prefix . 'saga_sagas', ['id' => $this->sagaId]);
        $wpdb->delete($wpdb->prefix . 'saga_entities', ['saga_id' => $this->sagaId]);

        parent::tearDown();
    }

    /**
     * Test feature extraction
     */
    public function test_feature_extraction(): void
    {
        $features = $this->featureService->extractFeatures(
            $this->sagaId,
            $this->entity1Id,
            $this->entity2Id
        );

        $this->assertIsArray($features);
        $this->assertNotEmpty($features);

        // Check for expected features
        $featureNames = array_map(fn($f) => $f->getName(), $features);

        $this->assertContains('co_occurrence_count', $featureNames);
        $this->assertContains('importance_product', $featureNames);
        $this->assertContains('type_compatibility', $featureNames);

        // Verify co-occurrence is detected
        $coOccurrence = array_filter($features, fn($f) => $f->getName() === 'co_occurrence_count');
        $coOccurrence = reset($coOccurrence);

        $this->assertNotNull($coOccurrence);
        $this->assertGreaterThan(0, $coOccurrence->getValue());
    }

    /**
     * Test suggestion generation
     */
    public function test_suggestion_generation(): void
    {
        $suggestion = $this->predictionService->generateSuggestion(
            $this->sagaId,
            $this->entity1Id,
            $this->entity2Id
        );

        $this->assertInstanceOf(RelationshipSuggestion::class, $suggestion);
        $this->assertEquals($this->sagaId, $suggestion->getSagaId());
        $this->assertEquals($this->entity1Id, $suggestion->getSourceEntityId());
        $this->assertEquals($this->entity2Id, $suggestion->getTargetEntityId());

        // Confidence should be between 0 and 1
        $this->assertGreaterThanOrEqual(0.0, $suggestion->getConfidence());
        $this->assertLessThanOrEqual(1.0, $suggestion->getConfidence());

        // Strength should be between 0 and 100
        $this->assertGreaterThanOrEqual(0, $suggestion->getSuggestedStrength());
        $this->assertLessThanOrEqual(100, $suggestion->getSuggestedStrength());

        // Should have reasoning
        $this->assertNotEmpty($suggestion->getReasoning());

        // Should be saved to database
        $this->assertGreaterThan(0, $suggestion->getId());
    }

    /**
     * Test suggestion retrieval
     */
    public function test_suggestion_retrieval(): void
    {
        // Generate suggestion
        $suggestion = $this->predictionService->generateSuggestion(
            $this->sagaId,
            $this->entity1Id,
            $this->entity2Id
        );

        // Retrieve by ID
        $retrieved = $this->repository->findById($suggestion->getId());

        $this->assertNotNull($retrieved);
        $this->assertEquals($suggestion->getId(), $retrieved->getId());
        $this->assertEquals($suggestion->getSuggestedType(), $retrieved->getSuggestedType());

        // Retrieve by saga
        $suggestions = $this->repository->findBySaga($this->sagaId, 'pending', 10);

        $this->assertCount(1, $suggestions);
        $this->assertEquals($suggestion->getId(), $suggestions[0]->getId());
    }

    /**
     * Test feature storage and retrieval
     */
    public function test_feature_storage(): void
    {
        // Generate suggestion (stores features)
        $suggestion = $this->predictionService->generateSuggestion(
            $this->sagaId,
            $this->entity1Id,
            $this->entity2Id
        );

        // Retrieve features
        $features = $this->repository->getFeatures($suggestion->getId());

        $this->assertIsArray($features);
        $this->assertNotEmpty($features);

        foreach ($features as $feature) {
            $this->assertInstanceOf(SuggestionFeature::class, $feature);
            $this->assertNotEmpty($feature->getName());
            $this->assertIsFloat($feature->getValue());
            $this->assertIsFloat($feature->getWeight());
        }
    }

    /**
     * Test feedback recording
     */
    public function test_feedback_recording(): void
    {
        // Generate suggestion
        $suggestion = $this->predictionService->generateSuggestion(
            $this->sagaId,
            $this->entity1Id,
            $this->entity2Id
        );

        // Record acceptance feedback
        $feedback = new SuggestionFeedback(
            $suggestion->getId(),
            'accepted',
            null,
            null,
            'Test acceptance'
        );

        $feedbackId = $this->learningService->recordFeedback($feedback);

        $this->assertGreaterThan(0, $feedbackId);

        // Verify suggestion status updated
        $updated = $this->repository->findById($suggestion->getId());
        $this->assertEquals('accepted', $updated->getStatus());
    }

    /**
     * Test learning weight updates
     */
    public function test_learning_weight_updates(): void
    {
        // Generate multiple suggestions
        for ($i = 0; $i < 15; $i++) {
            $suggestion = $this->predictionService->generateSuggestion(
                $this->sagaId,
                $this->entity1Id,
                $this->entity2Id
            );

            // Provide feedback
            $action = $i < 10 ? 'accepted' : 'rejected';
            $feedback = new SuggestionFeedback(
                $suggestion->getId(),
                $action,
                null,
                null,
                "Test feedback {$i}"
            );

            $this->learningService->recordFeedback($feedback);
        }

        // Trigger weight update
        $this->learningService->updateFeatureWeights($this->sagaId);

        // Verify weights were updated
        $weight = $this->repository->getWeights($this->sagaId, 'co_occurrence_count');

        $this->assertNotNull($weight);
        // Weight should be > 1.0 since we accepted more than rejected
        $this->assertGreaterThan(1.0, $weight);
    }

    /**
     * Test accuracy metrics
     */
    public function test_accuracy_metrics(): void
    {
        // Generate and provide feedback
        for ($i = 0; $i < 10; $i++) {
            $suggestion = $this->predictionService->generateSuggestion(
                $this->sagaId,
                $this->entity1Id,
                $this->entity2Id
            );

            $action = $i < 7 ? 'accepted' : 'rejected'; // 70% acceptance
            $feedback = new SuggestionFeedback(
                $suggestion->getId(),
                $action,
                null,
                null,
                "Test feedback"
            );

            $this->learningService->recordFeedback($feedback);
        }

        // Get metrics
        $metrics = $this->learningService->getAccuracyMetrics($this->sagaId);

        $this->assertIsArray($metrics);
        $this->assertArrayHasKey('total_accepted', $metrics);
        $this->assertArrayHasKey('total_rejected', $metrics);
        $this->assertArrayHasKey('acceptance_rate', $metrics);
        $this->assertArrayHasKey('avg_confidence', $metrics);

        $this->assertEquals(7, $metrics['total_accepted']);
        $this->assertEquals(3, $metrics['total_rejected']);
        $this->assertEquals(70.0, $metrics['acceptance_rate']);
    }

    /**
     * Test background job scheduling
     */
    public function test_background_job_scheduling(): void
    {
        $result = $this->backgroundProcessor->scheduleGenerationJob($this->sagaId);

        $this->assertTrue($result);

        // Verify transient was created
        $progress = get_transient("saga_suggestion_generation_{$this->sagaId}");

        $this->assertNotFalse($progress);
        $this->assertEquals('queued', $progress['status']);
    }

    /**
     * Test rate limiting
     */
    public function test_rate_limiting(): void
    {
        // Schedule 5 jobs (should work)
        for ($i = 0; $i < 5; $i++) {
            $result = $this->backgroundProcessor->scheduleGenerationJob($this->sagaId);
            $this->assertTrue($result);

            // Clean up transient for next iteration
            delete_transient("saga_suggestion_generation_{$this->sagaId}");
        }

        // 6th job should fail (rate limited)
        $result = $this->backgroundProcessor->scheduleGenerationJob($this->sagaId);
        $this->assertFalse($result);
    }

    /**
     * Test complete workflow
     */
    public function test_complete_workflow(): void
    {
        // 1. Generate suggestion
        $suggestion = $this->predictionService->generateSuggestion(
            $this->sagaId,
            $this->entity1Id,
            $this->entity2Id
        );

        $this->assertNotNull($suggestion);

        // 2. Retrieve and verify
        $retrieved = $this->repository->findById($suggestion->getId());
        $this->assertEquals('pending', $retrieved->getStatus());

        // 3. Get features
        $features = $this->repository->getFeatures($suggestion->getId());
        $this->assertNotEmpty($features);

        // 4. Accept suggestion
        $feedback = new SuggestionFeedback(
            $suggestion->getId(),
            'accepted',
            null,
            null,
            'Integration test acceptance'
        );

        $this->learningService->recordFeedback($feedback);

        // 5. Verify status updated
        $accepted = $this->repository->findById($suggestion->getId());
        $this->assertEquals('accepted', $accepted->getStatus());

        // 6. Get metrics
        $metrics = $this->learningService->getAccuracyMetrics($this->sagaId);
        $this->assertEquals(1, $metrics['total_accepted']);

        // 7. Update weights
        $this->learningService->updateFeatureWeights($this->sagaId);

        // 8. Generate new suggestion (should use updated weights)
        $newSuggestion = $this->predictionService->generateSuggestion(
            $this->sagaId,
            $this->entity1Id,
            $this->entity2Id
        );

        $this->assertNotNull($newSuggestion);
        // Confidence might be different with updated weights
        $this->assertGreaterThanOrEqual(0.0, $newSuggestion->getConfidence());
    }
}
