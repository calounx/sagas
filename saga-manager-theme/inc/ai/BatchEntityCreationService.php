<?php
/**
 * Batch Entity Creation Service
 *
 * Creates permanent saga entities from approved extracted entities.
 * Handles batch operations with proper transaction support and error recovery.
 * Updates extraction job statistics and links created entities back to extraction records.
 *
 * @package SagaManager
 * @subpackage AI\EntityExtractor
 * @since 1.4.0
 */

declare(strict_types=1);

namespace SagaManager\AI\EntityExtractor;

use SagaManager\AI\EntityExtractor\Entities\ExtractedEntity;
use SagaManager\AI\EntityExtractor\Entities\ExtractedEntityStatus;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Batch Entity Creation Service
 *
 * Converts approved extracted entities into permanent saga entities.
 */
class BatchEntityCreationService
{
    private ExtractionRepository $repository;
    private string $entities_table;
    private string $attributes_table;
    private string $attr_definitions_table;

    /**
     * Constructor
     *
     * @param ExtractionRepository|null $repository Optional repository (auto-created if null)
     */
    public function __construct(?ExtractionRepository $repository = null)
    {
        global $wpdb;

        $this->repository = $repository ?? new ExtractionRepository();
        $this->entities_table = $wpdb->prefix . 'saga_entities';
        $this->attributes_table = $wpdb->prefix . 'saga_attribute_values';
        $this->attr_definitions_table = $wpdb->prefix . 'saga_attribute_definitions';
    }

    /**
     * Create saga entities from approved extracted entities
     *
     * @param array $extracted_entities Array of ExtractedEntity objects (must be APPROVED status)
     * @param int $saga_id Target saga ID
     * @return array Creation results ['success' => [...], 'failed' => [...]]
     */
    public function createEntities(array $extracted_entities, int $saga_id): array
    {
        global $wpdb;

        $results = [
            'success' => [],
            'failed' => [],
            'total' => 0,
            'created' => 0,
            'errors' => 0,
        ];

        foreach ($extracted_entities as $extracted) {
            if (!($extracted instanceof ExtractedEntity)) {
                error_log('[SAGA][EXTRACTOR][ERROR] Invalid entity object in batch');
                continue;
            }

            // Only create approved entities
            if ($extracted->status !== ExtractedEntityStatus::APPROVED) {
                error_log(sprintf(
                    '[SAGA][EXTRACTOR][ERROR] Entity "%s" not approved (status: %s)',
                    $extracted->canonical_name,
                    $extracted->status->value
                ));
                $results['failed'][] = [
                    'extracted_entity_id' => $extracted->id,
                    'name' => $extracted->canonical_name,
                    'error' => 'Entity not approved',
                ];
                $results['errors']++;
                continue;
            }

            $results['total']++;

            try {
                $entity_id = $this->createSingleEntity($extracted, $saga_id);

                $results['success'][] = [
                    'extracted_entity_id' => $extracted->id,
                    'created_entity_id' => $entity_id,
                    'name' => $extracted->canonical_name,
                    'type' => $extracted->entity_type->value,
                ];
                $results['created']++;

                error_log(sprintf(
                    '[SAGA][EXTRACTOR] Created entity #%d "%s" from extracted entity #%d',
                    $entity_id,
                    $extracted->canonical_name,
                    $extracted->id
                ));

            } catch (\Exception $e) {
                $results['failed'][] = [
                    'extracted_entity_id' => $extracted->id,
                    'name' => $extracted->canonical_name,
                    'error' => $e->getMessage(),
                ];
                $results['errors']++;

                error_log(sprintf(
                    '[SAGA][EXTRACTOR][ERROR] Failed to create entity "%s": %s',
                    $extracted->canonical_name,
                    $e->getMessage()
                ));
            }
        }

        // Update job statistics
        if (!empty($extracted_entities) && isset($extracted_entities[0])) {
            $job_id = $extracted_entities[0]->job_id;
            $this->repository->syncJobStatistics($job_id);
        }

        error_log(sprintf(
            '[SAGA][EXTRACTOR] Batch creation complete: %d created, %d failed',
            $results['created'],
            $results['errors']
        ));

        return $results;
    }

    /**
     * Create single saga entity with attributes
     *
     * @param ExtractedEntity $extracted Extracted entity
     * @param int $saga_id Saga ID
     * @return int Created entity ID
     * @throws \Exception If creation fails
     */
    private function createSingleEntity(ExtractedEntity $extracted, int $saga_id): int
    {
        global $wpdb;

        $wpdb->query('START TRANSACTION');

        try {
            // 1. Create core entity record
            $entity_data = [
                'saga_id' => $saga_id,
                'entity_type' => $extracted->entity_type->value,
                'canonical_name' => $extracted->canonical_name,
                'slug' => $this->generateSlug($extracted->canonical_name, $saga_id),
                'importance_score' => $this->calculateImportanceScore($extracted),
                'created_at' => current_time('mysql'),
                'updated_at' => current_time('mysql'),
            ];

            $result = $wpdb->insert($this->entities_table, $entity_data);

            if ($result === false) {
                throw new \Exception('Failed to insert entity: ' . $wpdb->last_error);
            }

            $entity_id = $wpdb->insert_id;

            // 2. Create attributes
            if (!empty($extracted->attributes)) {
                $this->createEntityAttributes($entity_id, $extracted);
            }

            // 3. Create description attribute if provided
            if ($extracted->description !== null) {
                $this->createDescriptionAttribute($entity_id, $extracted->entity_type->value, $extracted->description);
            }

            // 4. Create alternative names attribute
            if (!empty($extracted->alternative_names)) {
                $this->createAlternativeNamesAttribute(
                    $entity_id,
                    $extracted->entity_type->value,
                    $extracted->alternative_names
                );
            }

            // 5. Update extracted entity with created ID
            $updated_extracted = $extracted->markCreated($entity_id);
            $this->repository->updateEntity($updated_extracted);

            $wpdb->query('COMMIT');

            return $entity_id;

        } catch (\Exception $e) {
            $wpdb->query('ROLLBACK');
            error_log(sprintf(
                '[SAGA][EXTRACTOR][ERROR] Transaction rollback for "%s": %s',
                $extracted->canonical_name,
                $e->getMessage()
            ));
            throw $e;
        }
    }

    /**
     * Create entity attributes from extracted data
     *
     * @param int $entity_id Entity ID
     * @param ExtractedEntity $extracted Extracted entity
     * @return void
     * @throws \Exception If attribute creation fails
     */
    private function createEntityAttributes(int $entity_id, ExtractedEntity $extracted): void
    {
        global $wpdb;

        foreach ($extracted->attributes as $key => $value) {
            // Get or create attribute definition
            $attr_id = $this->getOrCreateAttributeDefinition(
                $extracted->entity_type->value,
                $key,
                $value
            );

            if ($attr_id === null) {
                error_log(sprintf(
                    '[SAGA][EXTRACTOR][ERROR] Failed to get attribute definition for "%s"',
                    $key
                ));
                continue;
            }

            // Determine value column based on type
            $value_data = $this->prepareAttributeValue($value);

            $attr_data = array_merge(
                [
                    'entity_id' => $entity_id,
                    'attribute_id' => $attr_id,
                    'updated_at' => current_time('mysql'),
                ],
                $value_data
            );

            $result = $wpdb->insert($this->attributes_table, $attr_data);

            if ($result === false) {
                throw new \Exception("Failed to insert attribute '{$key}': " . $wpdb->last_error);
            }
        }
    }

    /**
     * Create description attribute
     *
     * @param int $entity_id Entity ID
     * @param string $entity_type Entity type
     * @param string $description Description text
     * @return void
     */
    private function createDescriptionAttribute(int $entity_id, string $entity_type, string $description): void
    {
        global $wpdb;

        $attr_id = $this->getOrCreateAttributeDefinition(
            $entity_type,
            'description',
            $description
        );

        if ($attr_id === null) {
            return;
        }

        $wpdb->insert($this->attributes_table, [
            'entity_id' => $entity_id,
            'attribute_id' => $attr_id,
            'value_text' => $description,
            'updated_at' => current_time('mysql'),
        ]);
    }

    /**
     * Create alternative names attribute
     *
     * @param int $entity_id Entity ID
     * @param string $entity_type Entity type
     * @param array $names Alternative names
     * @return void
     */
    private function createAlternativeNamesAttribute(int $entity_id, string $entity_type, array $names): void
    {
        global $wpdb;

        $attr_id = $this->getOrCreateAttributeDefinition(
            $entity_type,
            'alternative_names',
            json_encode($names)
        );

        if ($attr_id === null) {
            return;
        }

        $wpdb->insert($this->attributes_table, [
            'entity_id' => $entity_id,
            'attribute_id' => $attr_id,
            'value_json' => json_encode($names),
            'updated_at' => current_time('mysql'),
        ]);
    }

    /**
     * Get existing or create new attribute definition
     *
     * @param string $entity_type Entity type
     * @param string $key Attribute key
     * @param mixed $value Sample value (for type inference)
     * @return int|null Attribute definition ID
     */
    private function getOrCreateAttributeDefinition(string $entity_type, string $key, mixed $value): ?int
    {
        global $wpdb;

        // Check cache
        $cache_key = "attr_def_{$entity_type}_{$key}";
        $cached_id = wp_cache_get($cache_key, 'saga');

        if ($cached_id !== false) {
            return $cached_id;
        }

        // Check if definition exists
        $existing_id = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$this->attr_definitions_table}
             WHERE entity_type = %s AND attribute_key = %s",
            $entity_type,
            $key
        ));

        if ($existing_id !== null) {
            wp_cache_set($cache_key, (int)$existing_id, 'saga', 300);
            return (int)$existing_id;
        }

        // Create new definition
        $data_type = $this->inferDataType($value);

        $result = $wpdb->insert($this->attr_definitions_table, [
            'entity_type' => $entity_type,
            'attribute_key' => $key,
            'display_name' => $this->humanizeKey($key),
            'data_type' => $data_type,
            'is_searchable' => $data_type === 'string' ? 1 : 0,
            'is_required' => 0,
            'created_at' => current_time('mysql'),
        ]);

        if ($result === false) {
            error_log(sprintf(
                '[SAGA][EXTRACTOR][ERROR] Failed to create attribute definition "%s": %s',
                $key,
                $wpdb->last_error
            ));
            return null;
        }

        $attr_id = $wpdb->insert_id;

        // Cache result
        wp_cache_set($cache_key, $attr_id, 'saga', 300);

        return $attr_id;
    }

    /**
     * Prepare attribute value for database insertion
     *
     * @param mixed $value Attribute value
     * @return array Column => value mapping
     */
    private function prepareAttributeValue(mixed $value): array
    {
        if (is_bool($value)) {
            return ['value_bool' => $value ? 1 : 0];
        }

        if (is_int($value)) {
            return ['value_int' => $value];
        }

        if (is_float($value)) {
            return ['value_float' => $value];
        }

        if (is_array($value)) {
            return ['value_json' => json_encode($value)];
        }

        // String value
        $str_value = (string)$value;

        if (mb_strlen($str_value) > 500) {
            return ['value_text' => $str_value];
        }

        return ['value_string' => $str_value];
    }

    /**
     * Infer data type from value
     *
     * @param mixed $value Sample value
     * @return string Data type (string, int, float, bool, text, json)
     */
    private function inferDataType(mixed $value): string
    {
        if (is_bool($value)) {
            return 'bool';
        }

        if (is_int($value)) {
            return 'int';
        }

        if (is_float($value)) {
            return 'float';
        }

        if (is_array($value)) {
            return 'json';
        }

        $str_value = (string)$value;

        if (mb_strlen($str_value) > 500) {
            return 'text';
        }

        return 'string';
    }

    /**
     * Generate unique slug for entity
     *
     * @param string $name Entity name
     * @param int $saga_id Saga ID
     * @return string Unique slug
     */
    private function generateSlug(string $name, int $saga_id): string
    {
        global $wpdb;

        // Sanitize name for slug
        $slug = sanitize_title($name);

        // Check if slug exists in this saga
        $existing = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$this->entities_table}
             WHERE saga_id = %d AND slug = %s",
            $saga_id,
            $slug
        ));

        // Add number suffix if duplicate
        if ($existing > 0) {
            $counter = 2;
            do {
                $new_slug = $slug . '-' . $counter;
                $exists = $wpdb->get_var($wpdb->prepare(
                    "SELECT COUNT(*) FROM {$this->entities_table}
                     WHERE saga_id = %d AND slug = %s",
                    $saga_id,
                    $new_slug
                ));
                $counter++;
            } while ($exists > 0);

            $slug = $new_slug;
        }

        return $slug;
    }

    /**
     * Calculate importance score from extracted entity
     *
     * @param ExtractedEntity $extracted Extracted entity
     * @return int Importance score 0-100
     */
    private function calculateImportanceScore(ExtractedEntity $extracted): int
    {
        $score = 50; // Base score

        // Boost for high confidence
        if ($extracted->confidence_score >= 90) {
            $score += 15;
        } elseif ($extracted->confidence_score >= 70) {
            $score += 10;
        }

        // Boost for quality
        $quality = $extracted->getQualityScore();
        if ($quality >= 80) {
            $score += 10;
        } elseif ($quality >= 60) {
            $score += 5;
        }

        // Boost for completeness
        if ($extracted->description !== null) {
            $score += 5;
        }
        if (!empty($extracted->alternative_names)) {
            $score += 5;
        }
        if (!empty($extracted->attributes)) {
            $score += 5;
        }

        // Clamp to 0-100 range
        return max(0, min(100, $score));
    }

    /**
     * Convert attribute key to human-readable label
     *
     * @param string $key Attribute key (e.g., "age_years", "home_planet")
     * @return string Display name (e.g., "Age Years", "Home Planet")
     */
    private function humanizeKey(string $key): string
    {
        // Replace underscores and hyphens with spaces
        $label = str_replace(['_', '-'], ' ', $key);

        // Capitalize words
        $label = ucwords($label);

        return $label;
    }

    /**
     * Get creation summary for job
     *
     * @param int $job_id Job ID
     * @return array Summary statistics
     */
    public function getCreationSummary(int $job_id): array
    {
        $entities = $this->repository->findEntitiesByJob($job_id);

        $created = 0;
        $pending = 0;
        $approved = 0;

        foreach ($entities as $entity) {
            match($entity->status) {
                ExtractedEntityStatus::CREATED => $created++,
                ExtractedEntityStatus::APPROVED => $approved++,
                ExtractedEntityStatus::PENDING => $pending++,
                default => null
            };
        }

        return [
            'total_entities' => count($entities),
            'created' => $created,
            'approved_pending_creation' => $approved,
            'pending_review' => $pending,
            'ready_to_create' => $approved,
        ];
    }

    /**
     * Validate entities before batch creation
     *
     * @param array $entities Array of ExtractedEntity objects
     * @return array Validation results ['valid' => [...], 'invalid' => [...]]
     */
    public function validateEntities(array $entities): array
    {
        $valid = [];
        $invalid = [];

        foreach ($entities as $entity) {
            if (!($entity instanceof ExtractedEntity)) {
                $invalid[] = [
                    'entity' => null,
                    'errors' => ['Not an ExtractedEntity object'],
                ];
                continue;
            }

            $errors = [];

            // Check status
            if ($entity->status !== ExtractedEntityStatus::APPROVED) {
                $errors[] = "Status must be APPROVED (current: {$entity->status->value})";
            }

            // Check required fields
            if (empty(trim($entity->canonical_name))) {
                $errors[] = 'Canonical name is required';
            }

            // Check if already created
            if ($entity->created_entity_id !== null) {
                $errors[] = "Entity already created (ID: {$entity->created_entity_id})";
            }

            if (empty($errors)) {
                $valid[] = $entity;
            } else {
                $invalid[] = [
                    'entity' => $entity,
                    'errors' => $errors,
                ];
            }
        }

        return [
            'valid' => $valid,
            'invalid' => $invalid,
            'total' => count($entities),
            'valid_count' => count($valid),
            'invalid_count' => count($invalid),
        ];
    }
}
