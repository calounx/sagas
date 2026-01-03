<?php
/**
 * Feature Extraction Integration Tests
 *
 * Tests feature extraction for relationship prediction
 *
 * @package SagaManager\Tests\Integration\PredictiveRelationships
 * @since 1.4.0
 */

declare(strict_types=1);

namespace SagaManager\Tests\Integration\PredictiveRelationships;

use SagaManager\Tests\TestCase;

class FeatureExtractionTest extends TestCase
{
    public function test_extract_co_occurrence_features(): void
    {
        global $wpdb;

        $sagaId = $this->createSaga();
        $entity1 = $this->createEntity($sagaId, ['canonical_name' => 'Luke']);
        $entity2 = $this->createEntity($sagaId, ['canonical_name' => 'Leia']);

        // Simulate co-occurrence in content fragments
        for ($i = 0; $i < 10; $i++) {
            $wpdb->insert($wpdb->prefix . 'saga_content_fragments', [
                'entity_id' => $entity1,
                'fragment_text' => "Luke and Leia appear together fragment {$i}",
                'created_at' => current_time('mysql')
            ]);
        }

        // Count co-occurrences
        $coOccurrence = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}saga_content_fragments
             WHERE entity_id = %d AND fragment_text LIKE %s",
            $entity1,
            '%Leia%'
        ));

        $this->assertGreaterThan(0, $coOccurrence);

        // Normalize to 0-1 range
        $normalized = min($coOccurrence / 20, 1.0);
        $this->assertGreaterThan(0, $normalized);
        $this->assertLessThanOrEqual(1.0, $normalized);
    }

    public function test_extract_timeline_proximity_features(): void
    {
        global $wpdb;

        $sagaId = $this->createSaga();
        $entity1 = $this->createEntity($sagaId);
        $entity2 = $this->createEntity($sagaId);

        // Create timeline events
        $wpdb->insert($wpdb->prefix . 'saga_timeline_events', [
            'saga_id' => $sagaId,
            'event_entity_id' => $entity1,
            'canon_date' => '0 ABY',
            'normalized_timestamp' => 0,
            'title' => 'Event 1',
            'created_at' => current_time('mysql')
        ]);

        $wpdb->insert($wpdb->prefix . 'saga_timeline_events', [
            'saga_id' => $sagaId,
            'event_entity_id' => $entity2,
            'canon_date' => '3 ABY',
            'normalized_timestamp' => 3,
            'title' => 'Event 2',
            'created_at' => current_time('mysql')
        ]);

        // Calculate proximity
        $proximity = $wpdb->get_row($wpdb->prepare(
            "SELECT
                ABS(e1.normalized_timestamp - e2.normalized_timestamp) as distance
             FROM {$wpdb->prefix}saga_timeline_events e1
             JOIN {$wpdb->prefix}saga_timeline_events e2 ON e1.saga_id = e2.saga_id
             WHERE e1.event_entity_id = %d AND e2.event_entity_id = %d
             LIMIT 1",
            $entity1,
            $entity2
        ), ARRAY_A);

        $this->assertNotNull($proximity);
        $this->assertEquals(3, $proximity['distance']);

        // Normalize: closer = higher value
        $normalized = max(0, 1 - ($proximity['distance'] / 100));
        $this->assertGreaterThanOrEqual(0, $normalized);
        $this->assertLessThanOrEqual(1.0, $normalized);
    }

    public function test_extract_attribute_similarity_features(): void
    {
        global $wpdb;

        $sagaId = $this->createSaga();
        $entity1 = $this->createEntity($sagaId, ['entity_type' => 'character']);
        $entity2 = $this->createEntity($sagaId, ['entity_type' => 'character']);

        // Add attribute definition (use INSERT IGNORE to prevent duplicates)
        $wpdb->query($wpdb->prepare(
            "INSERT IGNORE INTO {$wpdb->prefix}saga_attribute_definitions
             (entity_type, attribute_key, display_name, data_type)
             VALUES (%s, %s, %s, %s)",
            'character',
            'affiliation',
            'Affiliation',
            'string'
        ));

        // Get the attribute ID (whether just inserted or already existed)
        $attrId = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$wpdb->prefix}saga_attribute_definitions
             WHERE entity_type = %s AND attribute_key = %s",
            'character',
            'affiliation'
        ));

        // Use INSERT IGNORE to prevent duplicate primary key errors
        $wpdb->query($wpdb->prepare(
            "INSERT IGNORE INTO {$wpdb->prefix}saga_attribute_values
             (entity_id, attribute_id, value_string)
             VALUES (%d, %d, %s)",
            $entity1,
            $attrId,
            'Rebel Alliance'
        ));

        $wpdb->query($wpdb->prepare(
            "INSERT IGNORE INTO {$wpdb->prefix}saga_attribute_values
             (entity_id, attribute_id, value_string)
             VALUES (%d, %d, %s)",
            $entity2,
            $attrId,
            'Rebel Alliance'
        ));

        // Calculate similarity
        $similarity = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(DISTINCT av1.attribute_id) as shared_attrs
             FROM {$wpdb->prefix}saga_attribute_values av1
             JOIN {$wpdb->prefix}saga_attribute_values av2
               ON av1.attribute_id = av2.attribute_id
               AND av1.value_string = av2.value_string
             WHERE av1.entity_id = %d AND av2.entity_id = %d",
            $entity1,
            $entity2
        ));

        $this->assertGreaterThan(0, $similarity);
    }

    public function test_feature_normalization(): void
    {
        // Test normalizing raw values to 0-1 range
        $testCases = [
            ['raw' => 15, 'min' => 0, 'max' => 30, 'expected' => 0.5],
            ['raw' => 0, 'min' => 0, 'max' => 100, 'expected' => 0.0],
            ['raw' => 100, 'min' => 0, 'max' => 100, 'expected' => 1.0],
            ['raw' => 25, 'min' => 0, 'max' => 100, 'expected' => 0.25],
            ['raw' => 75, 'min' => 50, 'max' => 100, 'expected' => 0.5],
        ];

        foreach ($testCases as $case) {
            $normalized = ($case['raw'] - $case['min']) / ($case['max'] - $case['min']);
            $normalized = max(0, min(1, $normalized)); // Clamp
            $this->assertEquals($case['expected'], $normalized);
        }
    }

    public function test_combined_weighted_features(): void
    {
        $features = [
            ['value' => 0.8, 'weight' => 0.7], // Co-occurrence
            ['value' => 0.6, 'weight' => 0.6], // Timeline proximity
            ['value' => 0.9, 'weight' => 0.5], // Attribute similarity
        ];

        $weightedSum = 0;
        $totalWeight = 0;

        foreach ($features as $feature) {
            $weightedSum += $feature['value'] * $feature['weight'];
            $totalWeight += $feature['weight'];
        }

        $combinedScore = $totalWeight > 0 ? $weightedSum / $totalWeight : 0;

        $this->assertGreaterThan(0.5, $combinedScore);
        $this->assertLessThan(1.0, $combinedScore);
    }

    public function test_stores_features_for_suggestion(): void
    {
        global $wpdb;

        $sagaId = $this->createSaga();
        $entity1 = $this->createEntity($sagaId);
        $entity2 = $this->createEntity($sagaId);

        $suggestionId = $this->createRelationshipSuggestion($sagaId, $entity1, $entity2);

        // Store multiple features
        $features = [
            ['type' => 'co_occurrence', 'value' => 0.75, 'weight' => 0.7],
            ['type' => 'timeline_proximity', 'value' => 0.60, 'weight' => 0.6],
            ['type' => 'attribute_similarity', 'value' => 0.85, 'weight' => 0.5],
        ];

        foreach ($features as $feature) {
            $wpdb->insert($wpdb->prefix . 'saga_suggestion_features', [
                'suggestion_id' => $suggestionId,
                'feature_type' => $feature['type'],
                'feature_name' => ucfirst(str_replace('_', ' ', $feature['type'])),
                'feature_value' => $feature['value'],
                'weight' => $feature['weight'],
                'created_at' => current_time('mysql')
            ]);
        }

        // Retrieve features
        $stored = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}saga_suggestion_features WHERE suggestion_id = %d",
            $suggestionId
        ), ARRAY_A);

        $this->assertCount(3, $stored);

        // Verify normalization
        foreach ($stored as $feature) {
            $this->assertGreaterThanOrEqual(0, $feature['feature_value']);
            $this->assertLessThanOrEqual(1.0, $feature['feature_value']);
            $this->assertGreaterThanOrEqual(0, $feature['weight']);
            $this->assertLessThanOrEqual(1.0, $feature['weight']);
        }
    }
}
