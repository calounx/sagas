<?php
declare(strict_types=1);

namespace SagaManager\Tests\Fixtures;

/**
 * Test Fixtures for Saga Manager
 *
 * Provides sample saga data for integration tests.
 * Uses WordPress global $wpdb for database operations.
 */
class SagaFixtures
{
    private static ?int $sagaId = null;
    private static array $entityIds = [];

    /**
     * Load Star Wars test saga with entities and relationships
     *
     * Creates:
     * - 1 saga (Star Wars)
     * - 5 entities (2 characters, 1 location, 1 event, 1 faction)
     * - 3 relationships
     *
     * @return int Saga ID
     */
    public static function loadStarWarsSaga(): int
    {
        global $wpdb;

        // Insert saga
        $wpdb->insert(
            $wpdb->prefix . 'saga_sagas',
            [
                'name' => 'Star Wars Test',
                'universe' => 'Star Wars',
                'calendar_type' => 'epoch_relative',
                'calendar_config' => json_encode([
                    'epoch' => 'BBY',
                    'epoch_year' => 0,
                ]),
                'created_at' => current_time('mysql'),
                'updated_at' => current_time('mysql'),
            ],
            ['%s', '%s', '%s', '%s', '%s', '%s']
        );

        self::$sagaId = $wpdb->insert_id;

        // Insert entities
        self::insertEntities();

        // Insert relationships
        self::insertRelationships();

        return self::$sagaId;
    }

    /**
     * Get the loaded saga ID
     */
    public static function getSagaId(): ?int
    {
        return self::$sagaId;
    }

    /**
     * Get entity IDs by type
     */
    public static function getEntityId(string $key): ?int
    {
        return self::$entityIds[$key] ?? null;
    }

    /**
     * Get all entity IDs
     */
    public static function getAllEntityIds(): array
    {
        return self::$entityIds;
    }

    /**
     * Clean up all test data
     */
    public static function cleanup(): void
    {
        global $wpdb;

        if (self::$sagaId !== null) {
            // Foreign key constraints will cascade delete
            $wpdb->delete(
                $wpdb->prefix . 'saga_sagas',
                ['id' => self::$sagaId],
                ['%d']
            );
        }

        self::$sagaId = null;
        self::$entityIds = [];
    }

    /**
     * Insert test entities
     */
    private static function insertEntities(): void
    {
        global $wpdb;

        $entities = [
            [
                'key' => 'luke',
                'type' => 'character',
                'name' => 'Luke Skywalker',
                'slug' => 'luke-skywalker',
                'importance' => 95,
            ],
            [
                'key' => 'vader',
                'type' => 'character',
                'name' => 'Darth Vader',
                'slug' => 'darth-vader',
                'importance' => 100,
            ],
            [
                'key' => 'tatooine',
                'type' => 'location',
                'name' => 'Tatooine',
                'slug' => 'tatooine',
                'importance' => 70,
            ],
            [
                'key' => 'battle_yavin',
                'type' => 'event',
                'name' => 'Battle of Yavin',
                'slug' => 'battle-of-yavin',
                'importance' => 90,
            ],
            [
                'key' => 'rebel_alliance',
                'type' => 'faction',
                'name' => 'Rebel Alliance',
                'slug' => 'rebel-alliance',
                'importance' => 85,
            ],
        ];

        foreach ($entities as $entity) {
            $wpdb->insert(
                $wpdb->prefix . 'saga_entities',
                [
                    'saga_id' => self::$sagaId,
                    'entity_type' => $entity['type'],
                    'canonical_name' => $entity['name'],
                    'slug' => $entity['slug'],
                    'importance_score' => $entity['importance'],
                    'created_at' => current_time('mysql'),
                    'updated_at' => current_time('mysql'),
                ],
                ['%d', '%s', '%s', '%s', '%d', '%s', '%s']
            );

            self::$entityIds[$entity['key']] = $wpdb->insert_id;
        }
    }

    /**
     * Insert test relationships
     */
    private static function insertRelationships(): void
    {
        global $wpdb;

        $relationships = [
            [
                'source' => self::$entityIds['vader'],
                'target' => self::$entityIds['luke'],
                'type' => 'parent_of',
                'strength' => 100,
            ],
            [
                'source' => self::$entityIds['luke'],
                'target' => self::$entityIds['rebel_alliance'],
                'type' => 'member_of',
                'strength' => 90,
            ],
            [
                'source' => self::$entityIds['luke'],
                'target' => self::$entityIds['tatooine'],
                'type' => 'born_on',
                'strength' => 80,
            ],
        ];

        foreach ($relationships as $rel) {
            $wpdb->insert(
                $wpdb->prefix . 'saga_entity_relationships',
                [
                    'source_entity_id' => $rel['source'],
                    'target_entity_id' => $rel['target'],
                    'relationship_type' => $rel['type'],
                    'strength' => $rel['strength'],
                    'created_at' => current_time('mysql'),
                    'updated_at' => current_time('mysql'),
                ],
                ['%d', '%d', '%s', '%d', '%s', '%s']
            );
        }
    }

    /**
     * Create attribute definitions for testing
     */
    public static function createAttributeDefinitions(): void
    {
        global $wpdb;

        $definitions = [
            [
                'entity_type' => 'character',
                'key' => 'species',
                'name' => 'Species',
                'type' => 'string',
                'searchable' => true,
            ],
            [
                'entity_type' => 'character',
                'key' => 'birth_year',
                'name' => 'Birth Year',
                'type' => 'string',
                'searchable' => false,
            ],
            [
                'entity_type' => 'location',
                'key' => 'climate',
                'name' => 'Climate',
                'type' => 'string',
                'searchable' => true,
            ],
            [
                'entity_type' => 'event',
                'key' => 'date',
                'name' => 'Date',
                'type' => 'date',
                'searchable' => false,
            ],
        ];

        foreach ($definitions as $def) {
            $wpdb->insert(
                $wpdb->prefix . 'saga_attribute_definitions',
                [
                    'entity_type' => $def['entity_type'],
                    'attribute_key' => $def['key'],
                    'display_name' => $def['name'],
                    'data_type' => $def['type'],
                    'is_searchable' => $def['searchable'],
                    'is_required' => false,
                ],
                ['%s', '%s', '%s', '%s', '%d', '%d']
            );
        }
    }

    /**
     * Set attribute values for test entities
     */
    public static function setAttributeValues(): void
    {
        global $wpdb;

        // Get attribute definition IDs
        $speciesAttr = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$wpdb->prefix}saga_attribute_definitions
             WHERE attribute_key = %s AND entity_type = %s",
            'species',
            'character'
        ));

        $birthYearAttr = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$wpdb->prefix}saga_attribute_definitions
             WHERE attribute_key = %s AND entity_type = %s",
            'birth_year',
            'character'
        ));

        $climateAttr = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$wpdb->prefix}saga_attribute_definitions
             WHERE attribute_key = %s AND entity_type = %s",
            'climate',
            'location'
        ));

        // Set Luke's attributes
        if ($speciesAttr) {
            $wpdb->insert(
                $wpdb->prefix . 'saga_attribute_values',
                [
                    'entity_id' => self::$entityIds['luke'],
                    'attribute_id' => $speciesAttr,
                    'value_string' => 'Human',
                ],
                ['%d', '%d', '%s']
            );
        }

        if ($birthYearAttr) {
            $wpdb->insert(
                $wpdb->prefix . 'saga_attribute_values',
                [
                    'entity_id' => self::$entityIds['luke'],
                    'attribute_id' => $birthYearAttr,
                    'value_string' => '19 BBY',
                ],
                ['%d', '%d', '%s']
            );
        }

        // Set Tatooine's attributes
        if ($climateAttr) {
            $wpdb->insert(
                $wpdb->prefix . 'saga_attribute_values',
                [
                    'entity_id' => self::$entityIds['tatooine'],
                    'attribute_id' => $climateAttr,
                    'value_string' => 'Arid',
                ],
                ['%d', '%d', '%s']
            );
        }
    }
}
