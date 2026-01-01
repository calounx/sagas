<?php
/**
 * Factory Trait for Test Data Creation
 *
 * Provides helper methods to create test data for Saga Manager features.
 *
 * @package SagaManager
 * @subpackage Tests
 */

namespace SagaManager\Tests;

/**
 * Factory trait for creating test entities
 */
trait FactoryTrait
{
    /**
     * Create a test saga
     *
     * @param array $args Optional arguments
     * @return int Saga ID
     */
    protected function createSaga(array $args = []): int
    {
        global $wpdb;

        $defaults = [
            'name' => 'Test Saga ' . uniqid(),
            'universe' => 'Test Universe',
            'calendar_type' => 'absolute',
            'calendar_config' => json_encode(['format' => 'Y-m-d'])
        ];

        $data = array_merge($defaults, $args);

        $wpdb->insert(
            $wpdb->prefix . 'saga_sagas',
            $data
        );

        return (int)$wpdb->insert_id;
    }

    /**
     * Create a test entity
     *
     * @param int $saga_id Saga ID
     * @param array $args Optional arguments
     * @return int Entity ID
     */
    protected function createEntity(int $saga_id, array $args = []): int
    {
        global $wpdb;

        $defaults = [
            'saga_id' => $saga_id,
            'entity_type' => 'character',
            'canonical_name' => 'Test Entity ' . uniqid(),
            'slug' => 'test-entity-' . uniqid(),
            'importance_score' => 50
        ];

        $data = array_merge($defaults, $args);

        $wpdb->insert(
            $wpdb->prefix . 'saga_entities',
            $data
        );

        return (int)$wpdb->insert_id;
    }

    /**
     * Create a test consistency issue
     *
     * @param int $saga_id Saga ID
     * @param array $args Optional arguments
     * @return int Issue ID
     */
    protected function createConsistencyIssue(int $saga_id, array $args = []): int
    {
        global $wpdb;

        $defaults = [
            'saga_id' => $saga_id,
            'issue_type' => 'timeline',
            'severity' => 'medium',
            'description' => 'Test issue',
            'status' => 'open',
            'ai_confidence' => 0.85
        ];

        $data = array_merge($defaults, $args);

        $wpdb->insert(
            $wpdb->prefix . 'saga_consistency_issues',
            $data
        );

        return (int)$wpdb->insert_id;
    }

    /**
     * Create a test extraction job
     *
     * @param int $saga_id Saga ID
     * @param int $user_id User ID
     * @param array $args Optional arguments
     * @return int Job ID
     */
    protected function createExtractionJob(int $saga_id, int $user_id, array $args = []): int
    {
        global $wpdb;

        $defaults = [
            'saga_id' => $saga_id,
            'user_id' => $user_id,
            'source_text' => 'Test text for extraction',
            'source_type' => 'manual',
            'chunk_size' => 5000,
            'total_chunks' => 1,
            'status' => 'pending',
            'ai_provider' => 'openai',
            'ai_model' => 'gpt-4'
        ];

        $data = array_merge($defaults, $args);

        $wpdb->insert(
            $wpdb->prefix . 'saga_extraction_jobs',
            $data
        );

        return (int)$wpdb->insert_id;
    }

    /**
     * Create a test extracted entity
     *
     * @param int $job_id Job ID
     * @param array $args Optional arguments
     * @return int Extracted entity ID
     */
    protected function createExtractedEntity(int $job_id, array $args = []): int
    {
        global $wpdb;

        $defaults = [
            'job_id' => $job_id,
            'entity_type' => 'character',
            'canonical_name' => 'Extracted Entity ' . uniqid(),
            'alternative_names' => json_encode([]),
            'attributes' => json_encode([]),
            'confidence_score' => 85.0,
            'chunk_index' => 0,
            'status' => 'pending'
        ];

        $data = array_merge($defaults, $args);

        $wpdb->insert(
            $wpdb->prefix . 'saga_extracted_entities',
            $data
        );

        return (int)$wpdb->insert_id;
    }

    /**
     * Create a test relationship suggestion
     *
     * @param int $saga_id Saga ID
     * @param int $source_id Source entity ID
     * @param int $target_id Target entity ID
     * @param array $args Optional arguments
     * @return int Suggestion ID
     */
    protected function createRelationshipSuggestion(
        int $saga_id,
        int $source_id,
        int $target_id,
        array $args = []
    ): int {
        global $wpdb;

        $defaults = [
            'saga_id' => $saga_id,
            'source_entity_id' => $source_id,
            'target_entity_id' => $target_id,
            'suggested_type' => 'ally',
            'confidence_score' => 75.0,
            'strength' => 50,
            'reasoning' => 'Test reasoning',
            'evidence' => json_encode([]),
            'suggestion_method' => 'content',
            'ai_model' => 'gpt-4',
            'status' => 'pending',
            'priority_score' => 50.0
        ];

        $data = array_merge($defaults, $args);

        $wpdb->insert(
            $wpdb->prefix . 'saga_relationship_suggestions',
            $data
        );

        return (int)$wpdb->insert_id;
    }

    /**
     * Create multiple test entities at once
     *
     * @param int $saga_id Saga ID
     * @param int $count Number of entities to create
     * @return array Array of entity IDs
     */
    protected function createEntities(int $saga_id, int $count): array
    {
        $ids = [];

        for ($i = 0; $i < $count; $i++) {
            $ids[] = $this->createEntity($saga_id, [
                'canonical_name' => "Entity {$i}",
                'slug' => "entity-{$i}"
            ]);
        }

        return $ids;
    }
}
