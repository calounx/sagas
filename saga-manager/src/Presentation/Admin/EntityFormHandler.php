<?php
declare(strict_types=1);

namespace SagaManager\Presentation\Admin;

use SagaManager\Domain\Entity\EntityId;
use SagaManager\Domain\Entity\EntityType;
use SagaManager\Domain\Entity\ImportanceScore;
use SagaManager\Domain\Entity\SagaEntity;
use SagaManager\Domain\Entity\SagaId;
use SagaManager\Domain\Exception\ValidationException;
use SagaManager\Infrastructure\Repository\MariaDBEntityRepository;
use WP_Error;

/**
 * Entity Form Handler
 *
 * Handles validation and processing of entity form submissions
 */
class EntityFormHandler
{
    private MariaDBEntityRepository $repository;

    public function __construct(MariaDBEntityRepository $repository)
    {
        $this->repository = $repository;
    }

    /**
     * Create a new entity from form data
     *
     * @param array<string, mixed> $data Form data
     * @return EntityId|WP_Error
     */
    public function create(array $data): EntityId|WP_Error
    {
        $validated = $this->validateFormData($data);

        if (is_wp_error($validated)) {
            return $validated;
        }

        try {
            $entity = new SagaEntity(
                sagaId: new SagaId($validated['saga_id']),
                type: EntityType::from($validated['entity_type']),
                canonicalName: $validated['canonical_name'],
                slug: $validated['slug'],
                importanceScore: new ImportanceScore($validated['importance_score'])
            );

            $this->repository->save($entity);

            // Save EAV attributes if provided
            if (!empty($validated['attributes'])) {
                $this->saveAttributes($entity->getId(), $validated['attributes']);
            }

            return $entity->getId();
        } catch (ValidationException $e) {
            return new WP_Error('validation_error', $e->getMessage());
        } catch (\Exception $e) {
            error_log('[SAGA][ERROR] Entity creation failed: ' . $e->getMessage());
            return new WP_Error('creation_error', __('Failed to create entity.', 'saga-manager'));
        }
    }

    /**
     * Update an existing entity
     *
     * @param int $entityId Entity ID
     * @param array<string, mixed> $data Form data
     * @return bool|WP_Error
     */
    public function update(int $entityId, array $data): bool|WP_Error
    {
        $validated = $this->validateFormData($data, $entityId);

        if (is_wp_error($validated)) {
            return $validated;
        }

        try {
            $entity = $this->repository->findByIdOrNull(new EntityId($entityId));

            if (!$entity) {
                return new WP_Error('not_found', __('Entity not found.', 'saga-manager'));
            }

            $entity->updateCanonicalName($validated['canonical_name']);
            $entity->updateSlug($validated['slug']);
            $entity->setImportanceScore(new ImportanceScore($validated['importance_score']));

            $this->repository->save($entity);

            // Update EAV attributes if provided
            if (!empty($validated['attributes'])) {
                $this->saveAttributes($entity->getId(), $validated['attributes']);
            }

            return true;
        } catch (ValidationException $e) {
            return new WP_Error('validation_error', $e->getMessage());
        } catch (\Exception $e) {
            error_log('[SAGA][ERROR] Entity update failed: ' . $e->getMessage());
            return new WP_Error('update_error', __('Failed to update entity.', 'saga-manager'));
        }
    }

    /**
     * Delete an entity
     *
     * @param int $entityId Entity ID
     * @return bool|WP_Error
     */
    public function delete(int $entityId): bool|WP_Error
    {
        try {
            $entity = $this->repository->findByIdOrNull(new EntityId($entityId));

            if (!$entity) {
                return new WP_Error('not_found', __('Entity not found.', 'saga-manager'));
            }

            $this->repository->delete(new EntityId($entityId));

            return true;
        } catch (\Exception $e) {
            error_log('[SAGA][ERROR] Entity deletion failed: ' . $e->getMessage());
            return new WP_Error('deletion_error', __('Failed to delete entity.', 'saga-manager'));
        }
    }

    /**
     * Bulk delete entities
     *
     * @param int[] $entityIds Array of entity IDs
     * @return int|WP_Error Number of deleted entities
     */
    public function bulkDelete(array $entityIds): int|WP_Error
    {
        if (empty($entityIds)) {
            return new WP_Error('no_items', __('No items selected.', 'saga-manager'));
        }

        global $wpdb;
        $table = $wpdb->prefix . 'saga_entities';

        $wpdb->query('START TRANSACTION');

        try {
            $placeholders = implode(',', array_fill(0, count($entityIds), '%d'));
            $sql = $wpdb->prepare(
                "DELETE FROM {$table} WHERE id IN ({$placeholders})",
                ...$entityIds
            );

            $result = $wpdb->query($sql);

            if ($result === false) {
                throw new \RuntimeException('Bulk delete query failed: ' . $wpdb->last_error);
            }

            $wpdb->query('COMMIT');

            // Invalidate cache for deleted entities
            foreach ($entityIds as $id) {
                wp_cache_delete("saga_entity_{$id}", 'saga');
            }

            return (int) $result;
        } catch (\Exception $e) {
            $wpdb->query('ROLLBACK');
            error_log('[SAGA][ERROR] Bulk deletion failed: ' . $e->getMessage());
            return new WP_Error('bulk_deletion_error', __('Failed to delete entities.', 'saga-manager'));
        }
    }

    /**
     * Bulk update importance scores
     *
     * @param int[] $entityIds Array of entity IDs
     * @param int $delta Amount to change (positive or negative)
     * @return int|WP_Error Number of updated entities
     */
    public function bulkUpdateImportance(array $entityIds, int $delta): int|WP_Error
    {
        if (empty($entityIds)) {
            return new WP_Error('no_items', __('No items selected.', 'saga-manager'));
        }

        global $wpdb;
        $table = $wpdb->prefix . 'saga_entities';

        $wpdb->query('START TRANSACTION');

        try {
            $placeholders = implode(',', array_fill(0, count($entityIds), '%d'));

            // Clamp values between 0 and 100
            $sql = $wpdb->prepare(
                "UPDATE {$table}
                 SET importance_score = LEAST(100, GREATEST(0, importance_score + %d)),
                     updated_at = NOW()
                 WHERE id IN ({$placeholders})",
                $delta,
                ...$entityIds
            );

            $result = $wpdb->query($sql);

            if ($result === false) {
                throw new \RuntimeException('Bulk update query failed: ' . $wpdb->last_error);
            }

            $wpdb->query('COMMIT');

            // Invalidate cache for updated entities
            foreach ($entityIds as $id) {
                wp_cache_delete("saga_entity_{$id}", 'saga');
            }

            return (int) $result;
        } catch (\Exception $e) {
            $wpdb->query('ROLLBACK');
            error_log('[SAGA][ERROR] Bulk importance update failed: ' . $e->getMessage());
            return new WP_Error('bulk_update_error', __('Failed to update entities.', 'saga-manager'));
        }
    }

    /**
     * Validate form data
     *
     * @param array<string, mixed> $data Raw form data
     * @param int|null $entityId Entity ID for updates (null for creates)
     * @return array<string, mixed>|WP_Error Validated data or error
     */
    private function validateFormData(array $data, ?int $entityId = null): array|WP_Error
    {
        $errors = [];

        // Saga ID
        $sagaId = isset($data['saga_id']) ? absint($data['saga_id']) : 0;
        if (!$sagaId) {
            $errors[] = __('Saga is required.', 'saga-manager');
        } else {
            // Verify saga exists
            global $wpdb;
            $sagaExists = $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM {$wpdb->prefix}saga_sagas WHERE id = %d",
                $sagaId
            ));
            if (!$sagaExists) {
                $errors[] = __('Selected saga does not exist.', 'saga-manager');
            }
        }

        // Entity type
        $entityType = isset($data['entity_type']) ? sanitize_key($data['entity_type']) : '';
        if (!$entityType) {
            $errors[] = __('Entity type is required.', 'saga-manager');
        } else {
            try {
                EntityType::from($entityType);
            } catch (\ValueError $e) {
                $errors[] = __('Invalid entity type.', 'saga-manager');
            }
        }

        // Canonical name
        $canonicalName = isset($data['canonical_name']) ? sanitize_text_field($data['canonical_name']) : '';
        if (empty($canonicalName)) {
            $errors[] = __('Name is required.', 'saga-manager');
        } elseif (strlen($canonicalName) > 255) {
            $errors[] = __('Name cannot exceed 255 characters.', 'saga-manager');
        } else {
            // Check for duplicates (within same saga)
            global $wpdb;
            $duplicate = $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM {$wpdb->prefix}saga_entities WHERE saga_id = %d AND canonical_name = %s AND id != %d",
                $sagaId,
                $canonicalName,
                $entityId ?? 0
            ));
            if ($duplicate) {
                $errors[] = __('An entity with this name already exists in this saga.', 'saga-manager');
            }
        }

        // Slug
        $slug = isset($data['slug']) ? sanitize_title($data['slug']) : '';
        if (empty($slug)) {
            // Auto-generate slug from name
            $slug = sanitize_title($canonicalName);
        }
        if (strlen($slug) > 255) {
            $errors[] = __('Slug cannot exceed 255 characters.', 'saga-manager');
        }

        // Importance score
        $importanceScore = isset($data['importance_score']) ? absint($data['importance_score']) : 50;
        if ($importanceScore > 100) {
            $importanceScore = 100;
        }

        // Return errors if any
        if (!empty($errors)) {
            return new WP_Error('validation_failed', implode(' ', $errors));
        }

        // Parse dynamic attributes
        $attributes = [];
        if (isset($data['attributes']) && is_array($data['attributes'])) {
            foreach ($data['attributes'] as $attrId => $value) {
                $attributes[absint($attrId)] = $this->sanitizeAttributeValue($value);
            }
        }

        return [
            'saga_id' => $sagaId,
            'entity_type' => $entityType,
            'canonical_name' => $canonicalName,
            'slug' => $slug,
            'importance_score' => $importanceScore,
            'attributes' => $attributes,
        ];
    }

    /**
     * Sanitize attribute value based on type
     */
    private function sanitizeAttributeValue(mixed $value): mixed
    {
        if (is_array($value)) {
            return array_map([$this, 'sanitizeAttributeValue'], $value);
        }

        if (is_numeric($value)) {
            return strpos((string) $value, '.') !== false ? (float) $value : (int) $value;
        }

        if (is_bool($value)) {
            return $value;
        }

        return sanitize_text_field((string) $value);
    }

    /**
     * Save EAV attributes for an entity
     *
     * @param EntityId $entityId Entity ID
     * @param array<int, mixed> $attributes Attribute ID => value pairs
     */
    private function saveAttributes(EntityId $entityId, array $attributes): void
    {
        global $wpdb;

        $attributeValuesTable = $wpdb->prefix . 'saga_attribute_values';
        $attributeDefsTable = $wpdb->prefix . 'saga_attribute_definitions';

        foreach ($attributes as $attributeId => $value) {
            // Get attribute definition
            $attrDef = $wpdb->get_row($wpdb->prepare(
                "SELECT data_type FROM {$attributeDefsTable} WHERE id = %d",
                $attributeId
            ), ARRAY_A);

            if (!$attrDef) {
                continue;
            }

            // Prepare data based on attribute type
            $data = [
                'entity_id' => $entityId->value(),
                'attribute_id' => $attributeId,
                'value_string' => null,
                'value_int' => null,
                'value_float' => null,
                'value_bool' => null,
                'value_date' => null,
                'value_text' => null,
                'value_json' => null,
            ];

            switch ($attrDef['data_type']) {
                case 'string':
                    $data['value_string'] = (string) $value;
                    break;
                case 'int':
                    $data['value_int'] = (int) $value;
                    break;
                case 'float':
                    $data['value_float'] = (float) $value;
                    break;
                case 'bool':
                    $data['value_bool'] = (bool) $value;
                    break;
                case 'date':
                    $data['value_date'] = sanitize_text_field($value);
                    break;
                case 'text':
                    $data['value_text'] = wp_kses_post($value);
                    break;
                case 'json':
                    $data['value_json'] = wp_json_encode($value);
                    break;
            }

            // Upsert (REPLACE INTO)
            $wpdb->replace($attributeValuesTable, $data);
        }
    }

    /**
     * Get attribute definitions for a specific entity type
     *
     * @param string $entityType Entity type value
     * @return array<int, array<string, mixed>>
     */
    public function getAttributeDefinitions(string $entityType): array
    {
        global $wpdb;

        $table = $wpdb->prefix . 'saga_attribute_definitions';

        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$table} WHERE entity_type = %s ORDER BY display_name ASC",
            $entityType
        ), ARRAY_A) ?: [];
    }

    /**
     * Get attribute values for an entity
     *
     * @param int $entityId Entity ID
     * @return array<int, mixed> Attribute ID => value pairs
     */
    public function getAttributeValues(int $entityId): array
    {
        global $wpdb;

        $valuesTable = $wpdb->prefix . 'saga_attribute_values';
        $defsTable = $wpdb->prefix . 'saga_attribute_definitions';

        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT av.*, ad.data_type
             FROM {$valuesTable} av
             JOIN {$defsTable} ad ON av.attribute_id = ad.id
             WHERE av.entity_id = %d",
            $entityId
        ), ARRAY_A);

        $values = [];
        foreach ($rows as $row) {
            $attrId = (int) $row['attribute_id'];
            $values[$attrId] = match ($row['data_type']) {
                'string' => $row['value_string'],
                'int' => (int) $row['value_int'],
                'float' => (float) $row['value_float'],
                'bool' => (bool) $row['value_bool'],
                'date' => $row['value_date'],
                'text' => $row['value_text'],
                'json' => json_decode($row['value_json'] ?? '{}', true),
                default => $row['value_string'],
            };
        }

        return $values;
    }
}
