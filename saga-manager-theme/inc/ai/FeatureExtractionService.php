<?php
/**
 * Feature Extraction Service
 *
 * Extracts features from entity pairs for relationship prediction.
 * Features include co-occurrence, timeline proximity, attribute similarity,
 * content similarity, and network centrality.
 *
 * @package SagaManager
 * @subpackage AI\PredictiveRelationships
 * @since 1.4.0
 */

declare(strict_types=1);

namespace SagaManager\AI\PredictiveRelationships;

use SagaManager\AI\PredictiveRelationships\Entities\FeatureType;
use SagaManager\AI\PredictiveRelationships\Entities\SuggestionFeature;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Feature Extraction Service
 *
 * Analyzes entity pairs to extract relationship prediction features.
 */
class FeatureExtractionService
{
    private string $entities_table;
    private string $relationships_table;
    private string $timeline_table;
    private string $content_table;
    private int $cache_ttl = 300; // 5 minutes

    /**
     * Constructor
     */
    public function __construct()
    {
        global $wpdb;
        $this->entities_table = $wpdb->prefix . 'saga_entities';
        $this->relationships_table = $wpdb->prefix . 'saga_entity_relationships';
        $this->timeline_table = $wpdb->prefix . 'saga_timeline_events';
        $this->content_table = $wpdb->prefix . 'saga_content_fragments';
    }

    /**
     * Extract all features for an entity pair
     *
     * @param int $entity1_id First entity ID
     * @param int $entity2_id Second entity ID
     * @param int $saga_id Saga ID
     * @return array Array of feature data [type => value]
     * @throws \Exception If extraction fails
     */
    public function extractFeatures(int $entity1_id, int $entity2_id, int $saga_id): array
    {
        if ($entity1_id === $entity2_id) {
            throw new \InvalidArgumentException('Cannot extract features for same entity');
        }

        $features = [];

        try {
            // Co-occurrence in content
            $features[FeatureType::CO_OCCURRENCE->value] = $this->calculateCoOccurrence(
                $entity1_id,
                $entity2_id
            );

            // Timeline proximity
            $features[FeatureType::TIMELINE_PROXIMITY->value] = $this->calculateTimelineProximity(
                $entity1_id,
                $entity2_id,
                $saga_id
            );

            // Attribute similarity
            $features[FeatureType::ATTRIBUTE_SIMILARITY->value] = $this->calculateAttributeSimilarity(
                $entity1_id,
                $entity2_id
            );

            // Shared location
            $features[FeatureType::SHARED_LOCATION->value] = $this->calculateSharedLocations(
                $entity1_id,
                $entity2_id
            );

            // Shared faction
            $features[FeatureType::SHARED_FACTION->value] = $this->calculateSharedFaction(
                $entity1_id,
                $entity2_id
            );

            // Network centrality
            $features[FeatureType::NETWORK_CENTRALITY->value] = $this->calculateNetworkCentrality(
                $entity1_id,
                $entity2_id,
                $saga_id
            );

            // Mention frequency
            $features[FeatureType::MENTION_FREQUENCY->value] = $this->calculateMentionFrequency(
                $entity1_id,
                $entity2_id
            );

            error_log(sprintf(
                '[SAGA][PREDICTIVE] Extracted %d features for entities %d-%d',
                count($features),
                $entity1_id,
                $entity2_id
            ));

            return $features;

        } catch (\Exception $e) {
            error_log(sprintf(
                '[SAGA][PREDICTIVE][ERROR] Feature extraction failed for %d-%d: %s',
                $entity1_id,
                $entity2_id,
                $e->getMessage()
            ));
            throw $e;
        }
    }

    /**
     * Calculate co-occurrence frequency
     *
     * How often entities appear together in content fragments
     *
     * @param int $entity1_id First entity ID
     * @param int $entity2_id Second entity ID
     * @return float Normalized value 0-1
     */
    public function calculateCoOccurrence(int $entity1_id, int $entity2_id): float
    {
        global $wpdb;

        $cache_key = "cooccur_{$entity1_id}_{$entity2_id}";
        $cached = wp_cache_get($cache_key, 'saga');
        if ($cached !== false) {
            return (float)$cached;
        }

        // Count content fragments where both entities appear
        $query = $wpdb->prepare(
            "SELECT COUNT(DISTINCT cf1.id) as co_count,
                    (SELECT COUNT(*) FROM {$this->content_table} WHERE entity_id = %d) as total1,
                    (SELECT COUNT(*) FROM {$this->content_table} WHERE entity_id = %d) as total2
             FROM {$this->content_table} cf1
             INNER JOIN {$this->content_table} cf2
                ON cf1.id = cf2.id
             WHERE cf1.entity_id = %d AND cf2.entity_id = %d",
            $entity1_id,
            $entity2_id,
            $entity1_id,
            $entity2_id
        );

        $result = $wpdb->get_row($query, ARRAY_A);

        if (!$result || !$result['total1'] || !$result['total2']) {
            wp_cache_set($cache_key, 0, 'saga', $this->cache_ttl);
            return 0.0;
        }

        $co_count = (int)$result['co_count'];
        $total = min((int)$result['total1'], (int)$result['total2']);

        $normalized = $this->normalizeFeature($co_count, 0, max($total, 1));

        wp_cache_set($cache_key, $normalized, 'saga', $this->cache_ttl);

        return $normalized;
    }

    /**
     * Calculate timeline proximity
     *
     * How close entities are in timeline events
     *
     * @param int $entity1_id First entity ID
     * @param int $entity2_id Second entity ID
     * @param int $saga_id Saga ID
     * @return float Normalized value 0-1
     */
    public function calculateTimelineProximity(int $entity1_id, int $entity2_id, int $saga_id): float
    {
        global $wpdb;

        // Find events with both entities
        $query = $wpdb->prepare(
            "SELECT COUNT(*) as shared_events,
                    AVG(ABS(t1.normalized_timestamp - t2.normalized_timestamp)) as avg_distance
             FROM {$this->timeline_table} t1
             INNER JOIN {$this->timeline_table} t2
                ON t1.saga_id = t2.saga_id
             WHERE t1.saga_id = %d
               AND JSON_CONTAINS(t1.participants, %s)
               AND JSON_CONTAINS(t2.participants, %s)
               AND t1.id != t2.id",
            $saga_id,
            json_encode($entity1_id),
            json_encode($entity2_id)
        );

        $result = $wpdb->get_row($query, ARRAY_A);

        if (!$result || !$result['shared_events']) {
            return 0.0;
        }

        $shared_events = (int)$result['shared_events'];
        $avg_distance = (float)$result['avg_distance'];

        // More shared events = higher score
        // Closer in time = higher score
        $event_score = $this->normalizeFeature($shared_events, 0, 10);
        $proximity_score = $avg_distance > 0 ? 1 / (1 + log($avg_distance + 1)) : 1.0;

        return ($event_score * 0.6) + ($proximity_score * 0.4);
    }

    /**
     * Calculate attribute similarity
     *
     * How similar are entity attributes (faction, type, etc)
     *
     * @param int $entity1_id First entity ID
     * @param int $entity2_id Second entity ID
     * @return float Normalized value 0-1
     */
    public function calculateAttributeSimilarity(int $entity1_id, int $entity2_id): float
    {
        global $wpdb;

        $entity1 = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->entities_table} WHERE id = %d",
            $entity1_id
        ), ARRAY_A);

        $entity2 = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->entities_table} WHERE id = %d",
            $entity2_id
        ), ARRAY_A);

        if (!$entity1 || !$entity2) {
            return 0.0;
        }

        $similarity = 0.0;
        $attributes_checked = 0;

        // Same entity type
        if ($entity1['entity_type'] === $entity2['entity_type']) {
            $similarity += 0.3;
        }
        $attributes_checked++;

        // Similar importance score (within 20 points)
        $importance_diff = abs($entity1['importance_score'] - $entity2['importance_score']);
        if ($importance_diff <= 20) {
            $similarity += 0.2;
        }
        $attributes_checked++;

        return $similarity / $attributes_checked;
    }

    /**
     * Calculate shared locations
     *
     * Do entities share common locations?
     *
     * @param int $entity1_id First entity ID
     * @param int $entity2_id Second entity ID
     * @return float Normalized value 0-1
     */
    public function calculateSharedLocations(int $entity1_id, int $entity2_id): float
    {
        global $wpdb;

        // Get location relationships for both entities
        $query = $wpdb->prepare(
            "SELECT COUNT(*) as shared_locations
             FROM {$this->relationships_table} r1
             INNER JOIN {$this->relationships_table} r2
                ON r1.target_entity_id = r2.target_entity_id
             INNER JOIN {$this->entities_table} e
                ON r1.target_entity_id = e.id
             WHERE r1.source_entity_id = %d
               AND r2.source_entity_id = %d
               AND e.entity_type = 'location'
               AND r1.relationship_type IN ('located_at', 'visited', 'lives_in')
               AND r2.relationship_type IN ('located_at', 'visited', 'lives_in')",
            $entity1_id,
            $entity2_id
        );

        $result = $wpdb->get_var($query);

        return $this->normalizeFeature((int)$result, 0, 5);
    }

    /**
     * Calculate shared faction membership
     *
     * Are entities in the same faction?
     *
     * @param int $entity1_id First entity ID
     * @param int $entity2_id Second entity ID
     * @return float Binary value 0 or 1
     */
    public function calculateSharedFaction(int $entity1_id, int $entity2_id): float
    {
        global $wpdb;

        // Check if both entities belong to same faction
        $query = $wpdb->prepare(
            "SELECT COUNT(*) as shared_factions
             FROM {$this->relationships_table} r1
             INNER JOIN {$this->relationships_table} r2
                ON r1.target_entity_id = r2.target_entity_id
             INNER JOIN {$this->entities_table} e
                ON r1.target_entity_id = e.id
             WHERE r1.source_entity_id = %d
               AND r2.source_entity_id = %d
               AND e.entity_type = 'faction'
               AND r1.relationship_type = 'member_of'
               AND r2.relationship_type = 'member_of'",
            $entity1_id,
            $entity2_id
        );

        $result = $wpdb->get_var($query);

        return (int)$result > 0 ? 1.0 : 0.0;
    }

    /**
     * Calculate network centrality
     *
     * How central are entities in the relationship graph?
     * More central entities are more likely to be connected
     *
     * @param int $entity1_id First entity ID
     * @param int $entity2_id Second entity ID
     * @param int $saga_id Saga ID
     * @return float Normalized value 0-1
     */
    public function calculateNetworkCentrality(int $entity1_id, int $entity2_id, int $saga_id): float
    {
        global $wpdb;

        // Count relationships for each entity
        $query = $wpdb->prepare(
            "SELECT
                (SELECT COUNT(*) FROM {$this->relationships_table}
                 WHERE source_entity_id = %d OR target_entity_id = %d) as count1,
                (SELECT COUNT(*) FROM {$this->relationships_table}
                 WHERE source_entity_id = %d OR target_entity_id = %d) as count2,
                (SELECT COUNT(*) FROM {$this->entities_table} WHERE saga_id = %d) as total_entities",
            $entity1_id,
            $entity1_id,
            $entity2_id,
            $entity2_id,
            $saga_id
        );

        $result = $wpdb->get_row($query, ARRAY_A);

        if (!$result || !$result['total_entities']) {
            return 0.0;
        }

        $degree1 = (int)$result['count1'];
        $degree2 = (int)$result['count2'];
        $total = (int)$result['total_entities'];

        // Average degree centrality
        $centrality1 = $this->normalizeFeature($degree1, 0, $total);
        $centrality2 = $this->normalizeFeature($degree2, 0, $total);

        return ($centrality1 + $centrality2) / 2;
    }

    /**
     * Calculate mention frequency
     *
     * How often are entities mentioned together?
     *
     * @param int $entity1_id First entity ID
     * @param int $entity2_id Second entity ID
     * @return float Normalized value 0-1
     */
    public function calculateMentionFrequency(int $entity1_id, int $entity2_id): float
    {
        global $wpdb;

        // Search for co-mentions in content fragments
        $query = $wpdb->prepare(
            "SELECT COUNT(*) as mentions
             FROM {$this->content_table} cf
             WHERE cf.entity_id IN (%d, %d)
             AND cf.fragment_text LIKE CONCAT('%%',
                (SELECT canonical_name FROM {$this->entities_table} WHERE id = %d), '%%')
             AND cf.fragment_text LIKE CONCAT('%%',
                (SELECT canonical_name FROM {$this->entities_table} WHERE id = %d), '%%')",
            $entity1_id,
            $entity2_id,
            $entity1_id === $entity1_id ? $entity2_id : $entity1_id,
            $entity1_id === $entity1_id ? $entity1_id : $entity2_id
        );

        $mentions = (int)$wpdb->get_var($query);

        return $this->normalizeFeature($mentions, 0, 20);
    }

    /**
     * Normalize feature value to 0-1 range
     *
     * @param float $value Raw value
     * @param float $min Minimum possible value
     * @param float $max Maximum possible value
     * @return float Normalized value 0-1
     */
    public function normalizeFeature(float $value, float $min, float $max): float
    {
        if ($max <= $min) {
            return 0.5; // Default if range invalid
        }

        $normalized = ($value - $min) / ($max - $min);

        // Clamp to 0-1
        return max(0.0, min(1.0, $normalized));
    }

    /**
     * Create SuggestionFeature objects from extracted features
     *
     * @param int $suggestion_id Suggestion ID
     * @param array $features Extracted features [type => value]
     * @param array $weights Feature weights [type => weight]
     * @return array Array of SuggestionFeature objects
     */
    public function createFeatureObjects(int $suggestion_id, array $features, array $weights): array
    {
        $feature_objects = [];

        foreach ($features as $type => $value) {
            try {
                $feature_type = FeatureType::from($type);
                $weight = $weights[$type] ?? $feature_type->getDefaultWeight();

                $feature_objects[] = new SuggestionFeature(
                    id: null,
                    suggestion_id: $suggestion_id,
                    feature_type: $feature_type,
                    feature_name: $feature_type->getDescription(),
                    feature_value: $value,
                    weight: $weight,
                    metadata: null,
                    created_at: time()
                );
            } catch (\ValueError $e) {
                error_log(sprintf(
                    '[SAGA][PREDICTIVE][ERROR] Invalid feature type: %s',
                    $type
                ));
            }
        }

        return $feature_objects;
    }

    /**
     * Get feature statistics for saga
     *
     * @param int $saga_id Saga ID
     * @return array Statistics array
     */
    public function getFeatureStatistics(int $saga_id): array
    {
        global $wpdb;

        $stats = [
            'total_entities' => 0,
            'total_relationships' => 0,
            'total_timeline_events' => 0,
            'avg_entity_degree' => 0
        ];

        $stats['total_entities'] = (int)$wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$this->entities_table} WHERE saga_id = %d",
            $saga_id
        ));

        $stats['total_relationships'] = (int)$wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$this->relationships_table} r
             INNER JOIN {$this->entities_table} e ON r.source_entity_id = e.id
             WHERE e.saga_id = %d",
            $saga_id
        ));

        $stats['total_timeline_events'] = (int)$wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$this->timeline_table} WHERE saga_id = %d",
            $saga_id
        ));

        if ($stats['total_entities'] > 0) {
            $stats['avg_entity_degree'] = round(
                ($stats['total_relationships'] * 2) / $stats['total_entities'],
                2
            );
        }

        return $stats;
    }
}
