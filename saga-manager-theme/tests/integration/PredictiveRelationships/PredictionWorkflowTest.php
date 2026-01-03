<?php
/**
 * Prediction Workflow Integration Tests
 *
 * Tests relationship prediction workflow and machine learning feedback loop
 *
 * @package SagaManager\Tests\Integration\PredictiveRelationships
 * @since 1.4.0
 */

declare(strict_types=1);

namespace SagaManager\Tests\Integration\PredictiveRelationships;

use SagaManager\Tests\TestCase;

class PredictionWorkflowTest extends TestCase
{
    public function test_relationship_suggestions_table_exists(): void
    {
        $this->assertTableExists('saga_relationship_suggestions');
        $this->assertTableHasColumns('saga_relationship_suggestions', [
            'id', 'saga_id', 'source_entity_id', 'target_entity_id',
            'suggested_type', 'confidence_score', 'status', 'suggestion_method'
        ]);
    }

    public function test_suggestion_features_table_exists(): void
    {
        $this->assertTableExists('saga_suggestion_features');
    }

    public function test_complete_prediction_workflow(): void
    {
        global $wpdb;

        $sagaId = $this->createSaga();
        $entity1 = $this->createEntity($sagaId, ['canonical_name' => 'Luke']);
        $entity2 = $this->createEntity($sagaId, ['canonical_name' => 'Vader']);

        // Create suggestion
        $suggestionId = $this->createRelationshipSuggestion(
            $sagaId, $entity1, $entity2,
            ['suggested_type' => 'family', 'confidence_score' => 85.0]
        );

        // Add features
        $wpdb->insert($wpdb->prefix . 'saga_suggestion_features', [
            'suggestion_id' => $suggestionId,
            'feature_type' => 'co_occurrence',
            'feature_name' => 'Co-occurrence',
            'feature_value' => 0.75,
            'weight' => 0.7,
            'created_at' => current_time('mysql')
        ]);

        // User accepts
        $wpdb->update(
            $wpdb->prefix . 'saga_relationship_suggestions',
            [
                'status' => 'accepted',
                'user_action_type' => 'accept',
                'accepted_at' => current_time('mysql'),
                'actioned_by' => 1
            ],
            ['id' => $suggestionId]
        );

        // Record feedback
        $wpdb->insert($wpdb->prefix . 'saga_suggestion_feedback', [
            'suggestion_id' => $suggestionId,
            'user_id' => 1,
            'action_type' => 'accept',
            'was_correct' => 1,
            'created_at' => current_time('mysql')
        ]);

        $suggestion = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}saga_relationship_suggestions WHERE id = %d",
            $suggestionId
        ), ARRAY_A);

        $this->assertEquals('accepted', $suggestion['status']);
    }

    public function test_learning_from_feedback(): void
    {
        global $wpdb;

        $sagaId = $this->createSaga();
        $entity1 = $this->createEntity($sagaId);
        $entity2 = $this->createEntity($sagaId);

        $suggestionId = $this->createRelationshipSuggestion($sagaId, $entity1, $entity2);

        // Record multiple feedback entries
        foreach (['accept', 'accept', 'reject'] as $i => $action) {
            $wpdb->insert($wpdb->prefix . 'saga_suggestion_feedback', [
                'suggestion_id' => $suggestionId,
                'user_id' => 1,
                'action_type' => $action,
                'was_correct' => $action === 'accept' ? 1 : 0,
                'created_at' => current_time('mysql')
            ]);
        }

        $stats = $wpdb->get_row($wpdb->prepare(
            "SELECT
                COUNT(*) as total,
                SUM(was_correct) as correct,
                AVG(was_correct) as accuracy
             FROM {$wpdb->prefix}saga_suggestion_feedback
             WHERE suggestion_id = %d",
            $suggestionId
        ), ARRAY_A);

        $this->assertEquals(3, (int)$stats['total']);
        $this->assertEquals(2, (int)$stats['correct']);
        $this->assertEquals(0.67, round((float)$stats['accuracy'], 2));
    }

    public function test_accuracy_metrics_calculation(): void
    {
        global $wpdb;

        $sagaId = $this->createSaga();

        // Create accepted suggestions
        for ($i = 0; $i < 7; $i++) {
            $entity1 = $this->createEntity($sagaId);
            $entity2 = $this->createEntity($sagaId);
            $this->createRelationshipSuggestion($sagaId, $entity1, $entity2, [
                'status' => 'accepted'
            ]);
        }

        // Create rejected suggestions
        for ($i = 0; $i < 3; $i++) {
            $entity1 = $this->createEntity($sagaId);
            $entity2 = $this->createEntity($sagaId);
            $this->createRelationshipSuggestion($sagaId, $entity1, $entity2, [
                'status' => 'rejected'
            ]);
        }

        $metrics = $wpdb->get_row($wpdb->prepare(
            "SELECT
                COUNT(*) as total,
                SUM(CASE WHEN status = 'accepted' THEN 1 ELSE 0 END) as accepted,
                SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) as rejected,
                (SUM(CASE WHEN status = 'accepted' THEN 1 ELSE 0 END) / COUNT(*)) * 100 as acceptance_rate
             FROM {$wpdb->prefix}saga_relationship_suggestions
             WHERE saga_id = %d AND status IN ('accepted', 'rejected')",
            $sagaId
        ), ARRAY_A);

        $this->assertEquals(10, (int)$metrics['total']);
        $this->assertEquals(7, (int)$metrics['accepted']);
        $this->assertEquals(70.0, round((float)$metrics['acceptance_rate'], 1));
    }
}
