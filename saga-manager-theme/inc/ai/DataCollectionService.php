<?php
/**
 * Data Collection Service
 *
 * Collects VERIFIED DATA ONLY from the database for summary generation.
 * Queries WordPress database for real saga data (entities, events, relationships).
 * All data includes source IDs for reference tracking and verification.
 *
 * CRITICAL: NO fictional content or placeholders - only database-verified data.
 *
 * @package SagaManager
 * @subpackage AI\SummaryGenerator
 * @since 1.5.0
 */

declare(strict_types=1);

namespace SagaManager\AI\SummaryGenerator;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Data Collection Service
 *
 * Gathers verified saga data for AI summary generation.
 */
class DataCollectionService
{
    private string $entities_table;
    private string $relationships_table;
    private string $timeline_table;
    private string $attributes_table;
    private string $attribute_values_table;
    private string $sagas_table;

    /**
     * Constructor
     */
    public function __construct()
    {
        global $wpdb;
        $this->entities_table = $wpdb->prefix . 'saga_entities';
        $this->relationships_table = $wpdb->prefix . 'saga_entity_relationships';
        $this->timeline_table = $wpdb->prefix . 'saga_timeline_events';
        $this->attributes_table = $wpdb->prefix . 'saga_attribute_definitions';
        $this->attribute_values_table = $wpdb->prefix . 'saga_attribute_values';
        $this->sagas_table = $wpdb->prefix . 'saga_sagas';
    }

    /**
     * Collect character data from database
     *
     * @param int $entity_id Character entity ID
     * @return array Character data with source references
     * @throws \Exception If character not found
     *
     * @example
     * $data = $service->collectCharacterData(123);
     * // Returns: [
     * //   'entity' => ['id' => 123, 'name' => 'Paul Atreides', ...],
     * //   'attributes' => ['age' => '15', 'role' => 'protagonist'],
     * //   'relationships' => [['target' => 'Duke Leto', 'type' => 'son_of']],
     * //   'events' => [['title' => 'Arrival on Arrakis', ...]],
     * //   'content' => 'Description from wp_posts',
     * //   'source_ids' => ['entity_id' => 123, 'post_id' => 456, ...]
     * // ]
     */
    public function collectCharacterData(int $entity_id): array
    {
        global $wpdb;

        // Get entity
        $entity = $wpdb->get_row($wpdb->prepare(
            "SELECT e.*, s.name as saga_name
             FROM {$this->entities_table} e
             JOIN {$this->sagas_table} s ON e.saga_id = s.id
             WHERE e.id = %d AND e.entity_type = 'character'",
            $entity_id
        ), ARRAY_A);

        if (!$entity) {
            throw new \Exception("Character entity #{$entity_id} not found");
        }

        // Get attributes
        $attributes = $this->collectEntityAttributes($entity_id);

        // Get relationships
        $relationships = $this->collectEntityRelationships($entity_id);

        // Get events
        $events = $this->collectEntityEvents($entity_id);

        // Get wp_posts content if linked
        $content = $this->collectPostContent((int)$entity['wp_post_id']);

        return [
            'entity' => $entity,
            'attributes' => $attributes,
            'relationships' => $relationships,
            'events' => $events,
            'content' => $content,
            'source_ids' => [
                'entity_id' => $entity_id,
                'post_id' => (int)$entity['wp_post_id'],
                'saga_id' => (int)$entity['saga_id'],
            ],
        ];
    }

    /**
     * Collect timeline data from database
     *
     * @param int $saga_id Saga ID
     * @param array $scope_params Scope parameters (date range, chapter, etc.)
     * @return array Timeline events with source references
     *
     * @example
     * $data = $service->collectTimelineData(1, ['date_range' => ['start' => '10190', 'end' => '10193']]);
     * // Returns: [
     * //   'events' => [['id' => 1, 'title' => 'Battle of Arrakeen', ...]],
     * //   'entities' => [['id' => 123, 'name' => 'Paul Atreides']],
     * //   'saga' => ['id' => 1, 'name' => 'Dune'],
     * //   'source_ids' => ['event_ids' => [1, 2, 3], 'entity_ids' => [123, 124]]
     * // ]
     */
    public function collectTimelineData(int $saga_id, array $scope_params = []): array
    {
        global $wpdb;

        // Get saga
        $saga = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->sagas_table} WHERE id = %d",
            $saga_id
        ), ARRAY_A);

        if (!$saga) {
            throw new \Exception("Saga #{$saga_id} not found");
        }

        // Build events query based on scope
        $events_query = "SELECT * FROM {$this->timeline_table} WHERE saga_id = %d";
        $query_args = [$saga_id];

        // Apply date range filter
        if (!empty($scope_params['date_range'])) {
            $events_query .= " AND normalized_timestamp BETWEEN %d AND %d";
            $query_args[] = (int)$scope_params['date_range']['start'];
            $query_args[] = (int)$scope_params['date_range']['end'];
        }

        // Apply chapter filter
        if (!empty($scope_params['chapter_ids'])) {
            $placeholders = implode(',', array_fill(0, count($scope_params['chapter_ids']), '%d'));
            $events_query .= " AND JSON_CONTAINS(metadata, JSON_QUOTE(%s), '$.chapter_id')";
            foreach ($scope_params['chapter_ids'] as $chapter_id) {
                $query_args[] = (string)$chapter_id;
            }
        }

        $events_query .= " ORDER BY normalized_timestamp ASC LIMIT 100";

        $events = $wpdb->get_results(
            $wpdb->prepare($events_query, ...$query_args),
            ARRAY_A
        );

        // Get participating entities
        $entity_ids = [];
        foreach ($events as $event) {
            if (!empty($event['participants'])) {
                $participants = json_decode($event['participants'], true);
                if (is_array($participants)) {
                    $entity_ids = array_merge($entity_ids, $participants);
                }
            }
        }

        $entities = [];
        if (!empty($entity_ids)) {
            $entity_ids = array_unique($entity_ids);
            $placeholders = implode(',', array_fill(0, count($entity_ids), '%d'));
            $entities = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT * FROM {$this->entities_table} WHERE id IN ({$placeholders})",
                    ...$entity_ids
                ),
                ARRAY_A
            );
        }

        return [
            'saga' => $saga,
            'events' => $events,
            'entities' => $entities,
            'source_ids' => [
                'saga_id' => $saga_id,
                'event_ids' => array_column($events, 'id'),
                'entity_ids' => array_column($entities, 'id'),
            ],
        ];
    }

    /**
     * Collect relationship data from database
     *
     * @param int $saga_id Saga ID
     * @param int|null $entity_id Optional: focus on specific entity
     * @return array Relationship data with source references
     *
     * @example
     * $data = $service->collectRelationshipData(1, 123);
     * // Returns: [
     * //   'relationships' => [['source' => 'Paul', 'target' => 'Jessica', 'type' => 'son_of']],
     * //   'entities' => [['id' => 123, 'name' => 'Paul']],
     * //   'source_ids' => ['relationship_ids' => [1, 2], 'entity_ids' => [123, 124]]
     * // ]
     */
    public function collectRelationshipData(int $saga_id, ?int $entity_id = null): array
    {
        global $wpdb;

        // Build relationships query
        $rel_query = "
            SELECT r.*,
                   se.canonical_name as source_name,
                   te.canonical_name as target_name
            FROM {$this->relationships_table} r
            JOIN {$this->entities_table} se ON r.source_entity_id = se.id
            JOIN {$this->entities_table} te ON r.target_entity_id = te.id
            WHERE se.saga_id = %d
        ";
        $query_args = [$saga_id];

        // Filter by specific entity
        if ($entity_id !== null) {
            $rel_query .= " AND (r.source_entity_id = %d OR r.target_entity_id = %d)";
            $query_args[] = $entity_id;
            $query_args[] = $entity_id;
        }

        $rel_query .= " ORDER BY r.strength DESC LIMIT 50";

        $relationships = $wpdb->get_results(
            $wpdb->prepare($rel_query, ...$query_args),
            ARRAY_A
        );

        // Get all involved entities
        $entity_ids = [];
        foreach ($relationships as $rel) {
            $entity_ids[] = (int)$rel['source_entity_id'];
            $entity_ids[] = (int)$rel['target_entity_id'];
        }

        $entities = [];
        if (!empty($entity_ids)) {
            $entity_ids = array_unique($entity_ids);
            $placeholders = implode(',', array_fill(0, count($entity_ids), '%d'));
            $entities = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT * FROM {$this->entities_table} WHERE id IN ({$placeholders})",
                    ...$entity_ids
                ),
                ARRAY_A
            );
        }

        return [
            'relationships' => $relationships,
            'entities' => $entities,
            'source_ids' => [
                'saga_id' => $saga_id,
                'relationship_ids' => array_column($relationships, 'id'),
                'entity_ids' => array_column($entities, 'id'),
            ],
        ];
    }

    /**
     * Collect faction data from database
     *
     * @param int $entity_id Faction entity ID
     * @return array Faction data with source references
     * @throws \Exception If faction not found
     *
     * @example
     * $data = $service->collectFactionData(456);
     * // Returns: [
     * //   'entity' => ['id' => 456, 'name' => 'House Atreides'],
     * //   'members' => [['id' => 123, 'name' => 'Paul Atreides']],
     * //   'activities' => [['id' => 1, 'title' => 'Conquest of Arrakis']],
     * //   'source_ids' => ['entity_id' => 456, 'member_ids' => [123, 124]]
     * // ]
     */
    public function collectFactionData(int $entity_id): array
    {
        global $wpdb;

        // Get faction entity
        $entity = $wpdb->get_row($wpdb->prepare(
            "SELECT e.*, s.name as saga_name
             FROM {$this->entities_table} e
             JOIN {$this->sagas_table} s ON e.saga_id = s.id
             WHERE e.id = %d AND e.entity_type = 'faction'",
            $entity_id
        ), ARRAY_A);

        if (!$entity) {
            throw new \Exception("Faction entity #{$entity_id} not found");
        }

        // Get faction attributes
        $attributes = $this->collectEntityAttributes($entity_id);

        // Get members (characters with affiliation relationship)
        $members = $wpdb->get_results($wpdb->prepare(
            "SELECT e.*
             FROM {$this->relationships_table} r
             JOIN {$this->entities_table} e ON r.source_entity_id = e.id
             WHERE r.target_entity_id = %d
             AND r.relationship_type IN ('member_of', 'belongs_to', 'affiliation')
             AND e.entity_type = 'character'
             LIMIT 50",
            $entity_id
        ), ARRAY_A);

        // Get activities (events involving faction)
        $activities = $wpdb->get_results($wpdb->prepare(
            "SELECT t.*
             FROM {$this->timeline_table} t
             WHERE JSON_CONTAINS(participants, %s)
             ORDER BY normalized_timestamp DESC
             LIMIT 30",
            json_encode([$entity_id])
        ), ARRAY_A);

        // Get wp_posts content
        $content = $this->collectPostContent((int)$entity['wp_post_id']);

        return [
            'entity' => $entity,
            'attributes' => $attributes,
            'members' => $members,
            'activities' => $activities,
            'content' => $content,
            'source_ids' => [
                'entity_id' => $entity_id,
                'post_id' => (int)$entity['wp_post_id'],
                'saga_id' => (int)$entity['saga_id'],
                'member_ids' => array_column($members, 'id'),
                'activity_ids' => array_column($activities, 'id'),
            ],
        ];
    }

    /**
     * Collect location data from database
     *
     * @param int $entity_id Location entity ID
     * @return array Location data with source references
     * @throws \Exception If location not found
     *
     * @example
     * $data = $service->collectLocationData(789);
     * // Returns: [
     * //   'entity' => ['id' => 789, 'name' => 'Arrakeen'],
     * //   'events' => [['id' => 1, 'title' => 'Battle of Arrakeen']],
     * //   'residents' => [['id' => 123, 'name' => 'Paul Atreides']],
     * //   'source_ids' => ['entity_id' => 789, 'event_ids' => [1, 2]]
     * // ]
     */
    public function collectLocationData(int $entity_id): array
    {
        global $wpdb;

        // Get location entity
        $entity = $wpdb->get_row($wpdb->prepare(
            "SELECT e.*, s.name as saga_name
             FROM {$this->entities_table} e
             JOIN {$this->sagas_table} s ON e.saga_id = s.id
             WHERE e.id = %d AND e.entity_type = 'location'",
            $entity_id
        ), ARRAY_A);

        if (!$entity) {
            throw new \Exception("Location entity #{$entity_id} not found");
        }

        // Get location attributes
        $attributes = $this->collectEntityAttributes($entity_id);

        // Get events at this location
        $events = $wpdb->get_results($wpdb->prepare(
            "SELECT t.*
             FROM {$this->timeline_table} t
             WHERE JSON_CONTAINS(locations, %s)
             ORDER BY normalized_timestamp DESC
             LIMIT 30",
            json_encode([$entity_id])
        ), ARRAY_A);

        // Get associated entities (characters, factions at location)
        $residents = $wpdb->get_results($wpdb->prepare(
            "SELECT e.*
             FROM {$this->relationships_table} r
             JOIN {$this->entities_table} e ON r.source_entity_id = e.id
             WHERE r.target_entity_id = %d
             AND r.relationship_type IN ('located_at', 'resides_in', 'based_in')
             LIMIT 30",
            $entity_id
        ), ARRAY_A);

        // Get wp_posts content
        $content = $this->collectPostContent((int)$entity['wp_post_id']);

        return [
            'entity' => $entity,
            'attributes' => $attributes,
            'events' => $events,
            'residents' => $residents,
            'content' => $content,
            'source_ids' => [
                'entity_id' => $entity_id,
                'post_id' => (int)$entity['wp_post_id'],
                'saga_id' => (int)$entity['saga_id'],
                'event_ids' => array_column($events, 'id'),
                'resident_ids' => array_column($residents, 'id'),
            ],
        ];
    }

    /**
     * Collect entity attributes from database
     *
     * @param int $entity_id Entity ID
     * @return array Attributes as key-value pairs
     */
    private function collectEntityAttributes(int $entity_id): array
    {
        global $wpdb;

        $query = "
            SELECT ad.attribute_key, ad.data_type,
                   av.value_string, av.value_int, av.value_float,
                   av.value_bool, av.value_date, av.value_text, av.value_json
            FROM {$this->attribute_values_table} av
            JOIN {$this->attributes_table} ad ON av.attribute_id = ad.id
            WHERE av.entity_id = %d
        ";

        $rows = $wpdb->get_results($wpdb->prepare($query, $entity_id), ARRAY_A);

        $attributes = [];
        foreach ($rows as $row) {
            $key = $row['attribute_key'];
            $value = match($row['data_type']) {
                'string' => $row['value_string'],
                'int' => $row['value_int'],
                'float' => $row['value_float'],
                'bool' => (bool)$row['value_bool'],
                'date' => $row['value_date'],
                'text' => $row['value_text'],
                'json' => json_decode($row['value_json'], true),
                default => null,
            };

            $attributes[$key] = $value;
        }

        return $attributes;
    }

    /**
     * Collect entity relationships
     *
     * @param int $entity_id Entity ID
     * @return array Relationships array
     */
    private function collectEntityRelationships(int $entity_id): array
    {
        global $wpdb;

        // Get relationships where entity is source or target
        $query = "
            SELECT r.*,
                   se.canonical_name as source_name,
                   te.canonical_name as target_name
            FROM {$this->relationships_table} r
            JOIN {$this->entities_table} se ON r.source_entity_id = se.id
            JOIN {$this->entities_table} te ON r.target_entity_id = te.id
            WHERE r.source_entity_id = %d OR r.target_entity_id = %d
            ORDER BY r.strength DESC
            LIMIT 30
        ";

        return $wpdb->get_results(
            $wpdb->prepare($query, $entity_id, $entity_id),
            ARRAY_A
        );
    }

    /**
     * Collect events involving entity
     *
     * @param int $entity_id Entity ID
     * @return array Timeline events
     */
    private function collectEntityEvents(int $entity_id): array
    {
        global $wpdb;

        // Find events where entity is participant
        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$this->timeline_table}
             WHERE JSON_CONTAINS(participants, %s)
             ORDER BY normalized_timestamp ASC
             LIMIT 30",
            json_encode([$entity_id])
        ), ARRAY_A);
    }

    /**
     * Collect wp_posts content if linked
     *
     * @param int|null $post_id WordPress post ID
     * @return string|null Post content or null
     */
    private function collectPostContent(?int $post_id): ?string
    {
        if (!$post_id) {
            return null;
        }

        $post = get_post($post_id);
        if (!$post) {
            return null;
        }

        return $post->post_content;
    }

    /**
     * Format collected data for human-friendly AI consumption
     *
     * Converts database arrays into readable text format for AI prompts.
     *
     * @param array $data Collected data from collect* methods
     * @param string $type Data type (character, timeline, relationship, faction, location)
     * @return string Formatted human-readable text
     */
    public function formatForAI(array $data, string $type): string
    {
        $output = '';

        switch ($type) {
            case 'character':
                $output .= "Character: {$data['entity']['canonical_name']}\n";
                $output .= "Saga: {$data['entity']['saga_name']}\n\n";

                if (!empty($data['attributes'])) {
                    $output .= "Attributes:\n";
                    foreach ($data['attributes'] as $key => $value) {
                        $output .= "  - {$key}: {$value}\n";
                    }
                    $output .= "\n";
                }

                if (!empty($data['relationships'])) {
                    $output .= "Relationships:\n";
                    foreach ($data['relationships'] as $rel) {
                        $direction = $rel['source_entity_id'] == $data['entity']['id'] ? 'to' : 'from';
                        $other = $direction === 'to' ? $rel['target_name'] : $rel['source_name'];
                        $output .= "  - {$rel['relationship_type']} {$direction} {$other}\n";
                    }
                    $output .= "\n";
                }

                if (!empty($data['events'])) {
                    $output .= "Events:\n";
                    foreach ($data['events'] as $event) {
                        $output .= "  - {$event['canon_date']}: {$event['title']}\n";
                        if (!empty($event['description'])) {
                            $output .= "    {$event['description']}\n";
                        }
                    }
                    $output .= "\n";
                }

                if (!empty($data['content'])) {
                    $output .= "Additional Content:\n{$data['content']}\n";
                }
                break;

            case 'timeline':
                $output .= "Timeline: {$data['saga']['name']}\n\n";
                $output .= "Events:\n";
                foreach ($data['events'] as $event) {
                    $output .= "- {$event['canon_date']}: {$event['title']}\n";
                    if (!empty($event['description'])) {
                        $output .= "  {$event['description']}\n";
                    }
                }
                break;

            case 'relationship':
                $output .= "Relationship Network\n\n";
                foreach ($data['relationships'] as $rel) {
                    $output .= "- {$rel['source_name']} → {$rel['relationship_type']} → {$rel['target_name']} (strength: {$rel['strength']})\n";
                }
                break;

            case 'faction':
                $output .= "Faction: {$data['entity']['canonical_name']}\n\n";
                if (!empty($data['members'])) {
                    $output .= "Members:\n";
                    foreach ($data['members'] as $member) {
                        $output .= "  - {$member['canonical_name']}\n";
                    }
                    $output .= "\n";
                }
                if (!empty($data['activities'])) {
                    $output .= "Activities:\n";
                    foreach ($data['activities'] as $activity) {
                        $output .= "  - {$activity['canon_date']}: {$activity['title']}\n";
                    }
                }
                break;

            case 'location':
                $output .= "Location: {$data['entity']['canonical_name']}\n\n";
                if (!empty($data['events'])) {
                    $output .= "Events at this location:\n";
                    foreach ($data['events'] as $event) {
                        $output .= "  - {$event['canon_date']}: {$event['title']}\n";
                    }
                }
                break;
        }

        return $output;
    }
}
