<?php
declare(strict_types=1);

namespace SagaManager\Presentation\API;

use SagaManager\Domain\Entity\AttributeValue;
use SagaManager\Domain\Entity\EntityId;
use SagaManager\Domain\Exception\EntityNotFoundException;
use SagaManager\Domain\Exception\ValidationException;
use SagaManager\Infrastructure\Repository\MariaDBAttributeDefinitionRepository;
use SagaManager\Infrastructure\Repository\MariaDBAttributeValueRepository;
use SagaManager\Infrastructure\Repository\MariaDBEntityRepository;

/**
 * REST API Controller for Entity Attribute Values
 *
 * Handles getting and setting EAV attribute values for entities.
 */
class EntityAttributeController
{
    private const NAMESPACE = 'saga/v1';

    public function __construct(
        private MariaDBEntityRepository $entityRepository,
        private MariaDBAttributeDefinitionRepository $definitionRepository,
        private MariaDBAttributeValueRepository $valueRepository
    ) {}

    /**
     * Register all REST API routes
     */
    public function registerRoutes(): void
    {
        // Get/Set all attributes for an entity
        register_rest_route(self::NAMESPACE, '/entities/(?P<id>\d+)/attributes', [
            [
                'methods' => \WP_REST_Server::READABLE,
                'callback' => [$this, 'index'],
                'permission_callback' => [$this, 'checkReadPermission'],
                'args' => [
                    'id' => [
                        'required' => true,
                        'validate_callback' => fn($param) => is_numeric($param) && $param > 0,
                    ],
                ],
            ],
            [
                'methods' => \WP_REST_Server::EDITABLE,
                'callback' => [$this, 'bulkUpdate'],
                'permission_callback' => [$this, 'checkWritePermission'],
                'args' => [
                    'id' => [
                        'required' => true,
                        'validate_callback' => fn($param) => is_numeric($param) && $param > 0,
                    ],
                    'attributes' => [
                        'required' => true,
                        'type' => 'object',
                    ],
                ],
            ],
        ]);

        // Get/Set/Delete single attribute
        register_rest_route(self::NAMESPACE, '/entities/(?P<id>\d+)/attributes/(?P<key>[a-z][a-z0-9_]*)', [
            [
                'methods' => \WP_REST_Server::READABLE,
                'callback' => [$this, 'show'],
                'permission_callback' => [$this, 'checkReadPermission'],
            ],
            [
                'methods' => \WP_REST_Server::EDITABLE,
                'callback' => [$this, 'update'],
                'permission_callback' => [$this, 'checkWritePermission'],
                'args' => [
                    'value' => [
                        'required' => true,
                    ],
                ],
            ],
            [
                'methods' => \WP_REST_Server::DELETABLE,
                'callback' => [$this, 'delete'],
                'permission_callback' => [$this, 'checkWritePermission'],
            ],
        ]);
    }

    /**
     * GET /wp-json/saga/v1/entities/{id}/attributes
     */
    public function index(\WP_REST_Request $request): \WP_REST_Response
    {
        try {
            $entityId = new EntityId((int) $request->get_param('id'));

            // Verify entity exists
            $entity = $this->entityRepository->findById($entityId);

            // Get all attribute values
            $values = $this->valueRepository->findByEntity($entityId);

            // Get all definitions for this entity type to include metadata
            $definitions = $this->definitionRepository->findByEntityType($entity->getType());
            $definitionsByKey = [];
            foreach ($definitions as $def) {
                $definitionsByKey[$def->getAttributeKey()] = $def;
            }

            $data = [];
            foreach ($values as $key => $value) {
                $def = $definitionsByKey[$key] ?? null;
                $data[$key] = $this->formatAttributeValue($value, $def);
            }

            // Include empty attributes with defaults
            foreach ($definitions as $def) {
                $key = $def->getAttributeKey();
                if (!isset($data[$key])) {
                    $data[$key] = [
                        'key' => $key,
                        'value' => $def->getTypedDefaultValue(),
                        'display_name' => $def->getDisplayName(),
                        'data_type' => $def->getDataType()->value,
                        'is_required' => $def->isRequired(),
                        'is_set' => false,
                    ];
                }
            }

            return new \WP_REST_Response($data, 200);

        } catch (EntityNotFoundException $e) {
            return new \WP_REST_Response(
                ['error' => 'Entity not found', 'message' => $e->getMessage()],
                404
            );
        } catch (\Exception $e) {
            return $this->handleException($e);
        }
    }

    /**
     * GET /wp-json/saga/v1/entities/{id}/attributes/{key}
     */
    public function show(\WP_REST_Request $request): \WP_REST_Response
    {
        try {
            $entityId = new EntityId((int) $request->get_param('id'));
            $attributeKey = $request->get_param('key');

            // Verify entity exists
            $entity = $this->entityRepository->findById($entityId);

            // Get the attribute value
            $value = $this->valueRepository->findByEntityAndKey($entityId, $attributeKey);

            // Get definition for metadata
            $definition = $this->definitionRepository->findByTypeAndKey($entity->getType(), $attributeKey);

            if ($definition === null) {
                throw new EntityNotFoundException(
                    sprintf('Attribute "%s" not defined for entity type "%s"',
                        $attributeKey, $entity->getType()->value)
                );
            }

            if ($value === null) {
                return new \WP_REST_Response([
                    'key' => $attributeKey,
                    'value' => $definition->getTypedDefaultValue(),
                    'display_name' => $definition->getDisplayName(),
                    'data_type' => $definition->getDataType()->value,
                    'is_required' => $definition->isRequired(),
                    'is_set' => false,
                ], 200);
            }

            return new \WP_REST_Response($this->formatAttributeValue($value, $definition), 200);

        } catch (EntityNotFoundException $e) {
            return new \WP_REST_Response(
                ['error' => 'Not found', 'message' => $e->getMessage()],
                404
            );
        } catch (\Exception $e) {
            return $this->handleException($e);
        }
    }

    /**
     * PUT /wp-json/saga/v1/entities/{id}/attributes
     */
    public function bulkUpdate(\WP_REST_Request $request): \WP_REST_Response
    {
        try {
            $entityId = new EntityId((int) $request->get_param('id'));
            $attributes = $request->get_param('attributes');

            if (!is_array($attributes)) {
                throw new ValidationException('Attributes must be an object with key-value pairs');
            }

            // Verify entity exists
            $entity = $this->entityRepository->findById($entityId);

            // Get all definitions for this type
            $definitions = $this->definitionRepository->findByEntityType($entity->getType());
            $definitionsByKey = [];
            foreach ($definitions as $def) {
                $definitionsByKey[$def->getAttributeKey()] = $def;
            }

            // Validate all values
            $valuesToSave = [];
            $errors = [];

            foreach ($attributes as $key => $value) {
                if (!isset($definitionsByKey[$key])) {
                    $errors[] = sprintf('Attribute "%s" not defined for entity type "%s"',
                        $key, $entity->getType()->value);
                    continue;
                }

                $definition = $definitionsByKey[$key];

                if (!$definition->validateValue($value)) {
                    $error = $definition->getValidationError($value);
                    $errors[] = $error ?? sprintf('Value for attribute "%s" failed validation', $key);
                    continue;
                }

                $valuesToSave[] = $definition->createValue($entityId, $value);
            }

            if (!empty($errors)) {
                return new \WP_REST_Response(
                    ['error' => 'Validation failed', 'messages' => $errors],
                    400
                );
            }

            // Save all values
            if (!empty($valuesToSave)) {
                $this->valueRepository->saveMany($valuesToSave);
            }

            // Return updated attributes
            $values = $this->valueRepository->findByEntity($entityId);
            $data = [];
            foreach ($values as $key => $value) {
                $def = $definitionsByKey[$key] ?? null;
                $data[$key] = $this->formatAttributeValue($value, $def);
            }

            return new \WP_REST_Response($data, 200);

        } catch (EntityNotFoundException $e) {
            return new \WP_REST_Response(
                ['error' => 'Entity not found', 'message' => $e->getMessage()],
                404
            );
        } catch (ValidationException $e) {
            return new \WP_REST_Response(
                ['error' => 'Validation failed', 'message' => $e->getMessage()],
                400
            );
        } catch (\Exception $e) {
            return $this->handleException($e);
        }
    }

    /**
     * PUT /wp-json/saga/v1/entities/{id}/attributes/{key}
     */
    public function update(\WP_REST_Request $request): \WP_REST_Response
    {
        try {
            $entityId = new EntityId((int) $request->get_param('id'));
            $attributeKey = $request->get_param('key');
            $value = $request->get_param('value');

            // Verify entity exists
            $entity = $this->entityRepository->findById($entityId);

            // Get definition
            $definition = $this->definitionRepository->findByTypeAndKey($entity->getType(), $attributeKey);

            if ($definition === null) {
                throw new EntityNotFoundException(
                    sprintf('Attribute "%s" not defined for entity type "%s"',
                        $attributeKey, $entity->getType()->value)
                );
            }

            // Validate
            if (!$definition->validateValue($value)) {
                $error = $definition->getValidationError($value);
                throw new ValidationException(
                    $error ?? sprintf('Value for attribute "%s" failed validation', $attributeKey)
                );
            }

            // Save
            $attributeValue = $definition->createValue($entityId, $value);
            $this->valueRepository->save($attributeValue);

            return new \WP_REST_Response($this->formatAttributeValue($attributeValue, $definition), 200);

        } catch (EntityNotFoundException $e) {
            return new \WP_REST_Response(
                ['error' => 'Not found', 'message' => $e->getMessage()],
                404
            );
        } catch (ValidationException $e) {
            return new \WP_REST_Response(
                ['error' => 'Validation failed', 'message' => $e->getMessage()],
                400
            );
        } catch (\Exception $e) {
            return $this->handleException($e);
        }
    }

    /**
     * DELETE /wp-json/saga/v1/entities/{id}/attributes/{key}
     */
    public function delete(\WP_REST_Request $request): \WP_REST_Response
    {
        try {
            $entityId = new EntityId((int) $request->get_param('id'));
            $attributeKey = $request->get_param('key');

            // Verify entity exists
            $entity = $this->entityRepository->findById($entityId);

            // Verify attribute is defined
            $definition = $this->definitionRepository->findByTypeAndKey($entity->getType(), $attributeKey);

            if ($definition === null) {
                throw new EntityNotFoundException(
                    sprintf('Attribute "%s" not defined for entity type "%s"',
                        $attributeKey, $entity->getType()->value)
                );
            }

            // Check if attribute is required
            if ($definition->isRequired()) {
                throw new ValidationException(
                    sprintf('Cannot delete required attribute "%s"', $attributeKey)
                );
            }

            $this->valueRepository->delete($entityId, $attributeKey);

            return new \WP_REST_Response(null, 204);

        } catch (EntityNotFoundException $e) {
            return new \WP_REST_Response(
                ['error' => 'Not found', 'message' => $e->getMessage()],
                404
            );
        } catch (ValidationException $e) {
            return new \WP_REST_Response(
                ['error' => 'Validation failed', 'message' => $e->getMessage()],
                400
            );
        } catch (\Exception $e) {
            return $this->handleException($e);
        }
    }

    /**
     * Permission check for read operations
     */
    public function checkReadPermission(): bool
    {
        return current_user_can('read');
    }

    /**
     * Permission check for write operations
     */
    public function checkWritePermission(): bool
    {
        return current_user_can('edit_posts');
    }

    /**
     * Format attribute value for API response
     */
    private function formatAttributeValue(
        AttributeValue $value,
        ?\SagaManager\Domain\Entity\AttributeDefinition $definition = null
    ): array {
        $data = [
            'key' => $value->getAttributeKey(),
            'value' => $value->getValue(),
            'data_type' => $value->getDataType()->value,
            'updated_at' => $value->getUpdatedAt()->format('c'),
            'is_set' => true,
        ];

        if ($definition !== null) {
            $data['display_name'] = $definition->getDisplayName();
            $data['is_required'] = $definition->isRequired();
        }

        return $data;
    }

    /**
     * Handle exceptions and return appropriate response
     */
    private function handleException(\Exception $e): \WP_REST_Response
    {
        error_log('[SAGA][API] Error: ' . $e->getMessage());

        return new \WP_REST_Response(
            ['error' => 'Internal server error', 'message' => $e->getMessage()],
            500
        );
    }
}
