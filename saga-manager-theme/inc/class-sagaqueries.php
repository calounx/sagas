<?php
declare(strict_types=1);

namespace SagaTheme;

use wpdb;

/**
 * Query Builder for Saga Manager Theme
 *
 * Object-oriented interface for database queries
 * All queries use wpdb->prepare() for SQL injection prevention
 * Follows WordPress table prefix conventions
 *
 * @package SagaTheme
 */
class SagaQueries
{
    private wpdb $wpdb;
    private SagaCache $cache;
    private string $entitiesTable;
    private string $attributeValuesTable;
    private string $relationshipsTable;

    /**
     * Constructor
     *
     * @param SagaCache $cache Cache layer instance
     */
    public function __construct(SagaCache $cache)
    {
        global $wpdb;
        $this->wpdb = $wpdb;
        $this->cache = $cache;

        // Initialize table names with proper WordPress prefix
        $this->entitiesTable = $wpdb->prefix . 'saga_entities';
        $this->attributeValuesTable = $wpdb->prefix . 'saga_attribute_values';
        $this->relationshipsTable = $wpdb->prefix . 'saga_entity_relationships';
    }

    /**
     * Get entity by WordPress post ID
     *
     * @param int $postId WordPress post ID
     * @return object|null Entity object or null if not found
     */
    public function getEntityByPostId(int $postId): ?object
    {
        // Check cache first
        $cached = $this->cache->getEntity($postId);
        if ($cached !== null) {
            return $cached;
        }

        $query = $this->wpdb->prepare(
            "SELECT * FROM {$this->entitiesTable} WHERE wp_post_id = %d LIMIT 1",
            $postId
        );

        $entity = $this->wpdb->get_row($query);

        if ($entity !== null) {
            $this->cache->setEntity($postId, $entity);
        }

        return $entity;
    }

    /**
     * Get entities by saga ID
     *
     * @param int $sagaId Saga ID
     * @param int $limit Maximum number of entities to return
     * @param int $offset Offset for pagination
     * @return array Array of entity objects
     */
    public function getEntitiesBySaga(int $sagaId, int $limit = 20, int $offset = 0): array
    {
        $query = $this->wpdb->prepare(
            "SELECT * FROM {$this->entitiesTable}
             WHERE saga_id = %d
             ORDER BY importance_score DESC, canonical_name ASC
             LIMIT %d OFFSET %d",
            $sagaId,
            $limit,
            $offset
        );

        $entities = $this->wpdb->get_results($query);

        return $entities ?: [];
    }

    /**
     * Get entities by type
     *
     * @param string $type Entity type (character, location, event, faction, artifact, concept)
     * @param int $sagaId Optional saga ID to filter by
     * @param int $limit Maximum number of entities to return
     * @return array Array of entity objects
     */
    public function getEntitiesByType(string $type, ?int $sagaId = null, int $limit = 20): array
    {
        if ($sagaId !== null) {
            $query = $this->wpdb->prepare(
                "SELECT * FROM {$this->entitiesTable}
                 WHERE entity_type = %s AND saga_id = %d
                 ORDER BY importance_score DESC, canonical_name ASC
                 LIMIT %d",
                $type,
                $sagaId,
                $limit
            );
        } else {
            $query = $this->wpdb->prepare(
                "SELECT * FROM {$this->entitiesTable}
                 WHERE entity_type = %s
                 ORDER BY importance_score DESC, canonical_name ASC
                 LIMIT %d",
                $type,
                $limit
            );
        }

        $entities = $this->wpdb->get_results($query);

        return $entities ?: [];
    }

    /**
     * Get related entities for a given entity
     *
     * @param int $entityId Entity ID
     * @param string $direction Relationship direction: 'outgoing', 'incoming', 'both'
     * @param int $limit Maximum number of related entities to return
     * @return array Array of related entity objects with relationship data
     */
    public function getRelatedEntities(int $entityId, string $direction = 'both', int $limit = 10): array
    {
        // Check cache first
        $cached = $this->cache->getRelationships($entityId, $direction);
        if ($cached !== null) {
            return $cached;
        }

        $conditions = [];
        $params = [];

        if ($direction === 'outgoing' || $direction === 'both') {
            $conditions[] = 'r.source_entity_id = %d';
            $params[] = $entityId;
        }

        if ($direction === 'incoming' || $direction === 'both') {
            $conditions[] = 'r.target_entity_id = %d';
            $params[] = $entityId;
        }

        $whereClause = '(' . implode(' OR ', $conditions) . ')';
        $params[] = $limit;

        $query = $this->wpdb->prepare(
            "SELECT
                r.id as relationship_id,
                r.relationship_type,
                r.strength,
                r.valid_from,
                r.valid_until,
                r.metadata,
                e.id as entity_id,
                e.canonical_name,
                e.entity_type,
                e.slug,
                e.importance_score,
                e.wp_post_id,
                CASE
                    WHEN r.source_entity_id = %d THEN 'outgoing'
                    ELSE 'incoming'
                END as direction
             FROM {$this->relationshipsTable} r
             INNER JOIN {$this->entitiesTable} e ON (
                CASE
                    WHEN r.source_entity_id = %d THEN r.target_entity_id = e.id
                    ELSE r.source_entity_id = e.id
                END
             )
             WHERE {$whereClause}
             ORDER BY r.strength DESC, e.importance_score DESC
             LIMIT %d",
            $entityId,
            $entityId,
            ...$params
        );

        $results = $this->wpdb->get_results($query);

        // Decode JSON metadata
        foreach ($results as $result) {
            if ($result->metadata !== null) {
                $result->metadata = json_decode($result->metadata);
            }
        }

        $related = $results ?: [];

        // Cache the results
        $this->cache->setRelationships($entityId, $related, $direction);

        return $related;
    }

    /**
     * Get recent entities across all sagas
     *
     * @param int $limit Maximum number of entities to return
     * @return array Array of entity objects
     */
    public function getRecentEntities(int $limit = 5): array
    {
        $query = $this->wpdb->prepare(
            "SELECT * FROM {$this->entitiesTable}
             ORDER BY created_at DESC
             LIMIT %d",
            $limit
        );

        $entities = $this->wpdb->get_results($query);

        return $entities ?: [];
    }

    /**
     * Get attribute values for an entity
     *
     * @param int $entityId Entity ID
     * @return array Array of attribute value objects
     */
    public function getAttributeValues(int $entityId): array
    {
        // Check cache first
        $cached = $this->cache->getAttributes($entityId);
        if ($cached !== null) {
            return $cached;
        }

        $query = $this->wpdb->prepare(
            "SELECT
                av.*,
                ad.attribute_key,
                ad.display_name,
                ad.data_type
             FROM {$this->attributeValuesTable} av
             INNER JOIN {$this->wpdb->prefix}saga_attribute_definitions ad
                ON av.attribute_id = ad.id
             WHERE av.entity_id = %d
             ORDER BY ad.display_name ASC",
            $entityId
        );

        $attributes = $this->wpdb->get_results($query);

        // Normalize attribute values based on data_type
        foreach ($attributes as $attr) {
            $attr->value = $this->normalizeAttributeValue($attr);
        }

        $result = $attributes ?: [];

        // Cache the results
        $this->cache->setAttributes($entityId, $result);

        return $result;
    }

    /**
     * Search entities by name
     *
     * @param string $searchTerm Search term (minimum 2 characters)
     * @param int|null $sagaId Optional saga ID to filter by
     * @param int $limit Maximum number of results
     * @return array Array of entity objects
     */
    public function searchEntities(string $searchTerm, ?int $sagaId = null, int $limit = 20): array
    {
        // Sanitize and validate search term
        $searchTerm = sanitize_text_field($searchTerm);
        if (strlen($searchTerm) < 2) {
            return [];
        }

        $searchPattern = '%' . $this->wpdb->esc_like($searchTerm) . '%';

        if ($sagaId !== null) {
            $query = $this->wpdb->prepare(
                "SELECT * FROM {$this->entitiesTable}
                 WHERE canonical_name LIKE %s AND saga_id = %d
                 ORDER BY importance_score DESC, canonical_name ASC
                 LIMIT %d",
                $searchPattern,
                $sagaId,
                $limit
            );
        } else {
            $query = $this->wpdb->prepare(
                "SELECT * FROM {$this->entitiesTable}
                 WHERE canonical_name LIKE %s
                 ORDER BY importance_score DESC, canonical_name ASC
                 LIMIT %d",
                $searchPattern,
                $limit
            );
        }

        $entities = $this->wpdb->get_results($query);

        return $entities ?: [];
    }

    /**
     * Normalize attribute value based on data type
     *
     * @param object $attribute Attribute object with data_type and value fields
     * @return mixed Normalized value
     */
    private function normalizeAttributeValue(object $attribute): mixed
    {
        return match ($attribute->data_type) {
            'string' => $attribute->value_string,
            'int' => (int) $attribute->value_int,
            'float' => (float) $attribute->value_float,
            'bool' => (bool) $attribute->value_bool,
            'date' => $attribute->value_date,
            'text' => $attribute->value_text,
            'json' => json_decode($attribute->value_json ?? '{}'),
            default => null,
        };
    }

    /**
     * Get entity count by type for a saga
     *
     * @param int $sagaId Saga ID
     * @return array Associative array of entity_type => count
     */
    public function getEntityCountsByType(int $sagaId): array
    {
        $query = $this->wpdb->prepare(
            "SELECT entity_type, COUNT(*) as count
             FROM {$this->entitiesTable}
             WHERE saga_id = %d
             GROUP BY entity_type",
            $sagaId
        );

        $results = $this->wpdb->get_results($query);

        $counts = [];
        foreach ($results as $row) {
            $counts[$row->entity_type] = (int) $row->count;
        }

        return $counts;
    }
}
