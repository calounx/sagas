<?php
declare(strict_types=1);

namespace SagaManager\Presentation\API;

use SagaManager\Domain\Entity\AttributeDefinition;
use SagaManager\Domain\Entity\AttributeDefinitionId;
use SagaManager\Domain\Entity\DataType;
use SagaManager\Domain\Entity\EntityType;
use SagaManager\Domain\Exception\DuplicateEntityException;
use SagaManager\Domain\Exception\EntityNotFoundException;
use SagaManager\Domain\Exception\ValidationException;
use SagaManager\Infrastructure\Repository\MariaDBAttributeDefinitionRepository;

/**
 * REST API Controller for Attribute Definitions
 *
 * Handles CRUD operations for EAV attribute schema definitions.
 */
class AttributeDefinitionController
{
    private const NAMESPACE = 'saga/v1';

    public function __construct(
        private MariaDBAttributeDefinitionRepository $repository
    ) {}

    /**
     * Register all REST API routes
     */
    public function registerRoutes(): void
    {
        register_rest_route(self::NAMESPACE, '/attribute-definitions', [
            [
                'methods' => \WP_REST_Server::READABLE,
                'callback' => [$this, 'index'],
                'permission_callback' => [$this, 'checkReadPermission'],
                'args' => $this->getIndexArgs(),
            ],
            [
                'methods' => \WP_REST_Server::CREATABLE,
                'callback' => [$this, 'create'],
                'permission_callback' => [$this, 'checkAdminPermission'],
                'args' => $this->getCreateArgs(),
            ],
        ]);

        register_rest_route(self::NAMESPACE, '/attribute-definitions/(?P<id>\d+)', [
            [
                'methods' => \WP_REST_Server::READABLE,
                'callback' => [$this, 'show'],
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
                'callback' => [$this, 'update'],
                'permission_callback' => [$this, 'checkAdminPermission'],
                'args' => $this->getUpdateArgs(),
            ],
            [
                'methods' => \WP_REST_Server::DELETABLE,
                'callback' => [$this, 'delete'],
                'permission_callback' => [$this, 'checkAdminPermission'],
                'args' => [
                    'id' => [
                        'required' => true,
                        'validate_callback' => fn($param) => is_numeric($param) && $param > 0,
                    ],
                ],
            ],
        ]);
    }

    /**
     * GET /wp-json/saga/v1/attribute-definitions
     */
    public function index(\WP_REST_Request $request): \WP_REST_Response
    {
        try {
            $entityType = $request->get_param('entity_type');

            if ($entityType !== null) {
                $type = EntityType::from($entityType);
                $definitions = $this->repository->findByEntityType($type);
            } else {
                // Get all definitions across all types
                $definitions = [];
                foreach (EntityType::cases() as $type) {
                    $definitions = array_merge(
                        $definitions,
                        $this->repository->findByEntityType($type)
                    );
                }
            }

            $data = array_map([$this, 'formatDefinition'], $definitions);

            return new \WP_REST_Response($data, 200);

        } catch (\ValueError $e) {
            return new \WP_REST_Response(
                ['error' => 'Invalid entity type', 'message' => $e->getMessage()],
                400
            );
        } catch (\Exception $e) {
            return $this->handleException($e);
        }
    }

    /**
     * GET /wp-json/saga/v1/attribute-definitions/{id}
     */
    public function show(\WP_REST_Request $request): \WP_REST_Response
    {
        try {
            $id = new AttributeDefinitionId((int) $request->get_param('id'));
            $definition = $this->repository->findById($id);

            return new \WP_REST_Response($this->formatDefinition($definition), 200);

        } catch (EntityNotFoundException $e) {
            return new \WP_REST_Response(
                ['error' => 'Attribute definition not found', 'message' => $e->getMessage()],
                404
            );
        } catch (\Exception $e) {
            return $this->handleException($e);
        }
    }

    /**
     * POST /wp-json/saga/v1/attribute-definitions
     */
    public function create(\WP_REST_Request $request): \WP_REST_Response
    {
        try {
            $entityType = EntityType::from($request->get_param('entity_type'));
            $dataType = DataType::from($request->get_param('data_type'));

            // Check for duplicate
            $attributeKey = sanitize_key($request->get_param('attribute_key'));
            if ($this->repository->keyExists($entityType, $attributeKey)) {
                throw new DuplicateEntityException(
                    sprintf('Attribute key "%s" already exists for entity type "%s"',
                        $attributeKey, $entityType->value)
                );
            }

            $validationRule = $request->get_param('validation_rule');
            $validationRuleObj = null;
            if (!empty($validationRule)) {
                $validationRuleObj = \SagaManager\Domain\Entity\ValidationRule::fromArray($validationRule);
            }

            $definition = new AttributeDefinition(
                entityType: $entityType,
                attributeKey: $attributeKey,
                displayName: sanitize_text_field($request->get_param('display_name')),
                dataType: $dataType,
                isSearchable: (bool) $request->get_param('is_searchable'),
                isRequired: (bool) $request->get_param('is_required'),
                validationRule: $validationRuleObj,
                defaultValue: $request->get_param('default_value')
            );

            $this->repository->save($definition);

            return new \WP_REST_Response($this->formatDefinition($definition), 201);

        } catch (DuplicateEntityException $e) {
            return new \WP_REST_Response(
                ['error' => 'Duplicate attribute key', 'message' => $e->getMessage()],
                409
            );
        } catch (ValidationException $e) {
            return new \WP_REST_Response(
                ['error' => 'Validation failed', 'message' => $e->getMessage()],
                400
            );
        } catch (\ValueError $e) {
            return new \WP_REST_Response(
                ['error' => 'Invalid parameter', 'message' => $e->getMessage()],
                400
            );
        } catch (\Exception $e) {
            return $this->handleException($e);
        }
    }

    /**
     * PUT /wp-json/saga/v1/attribute-definitions/{id}
     */
    public function update(\WP_REST_Request $request): \WP_REST_Response
    {
        try {
            $id = new AttributeDefinitionId((int) $request->get_param('id'));
            $definition = $this->repository->findById($id);

            // Apply updates (only mutable fields)
            if ($request->has_param('display_name')) {
                $definition->updateDisplayName(sanitize_text_field($request->get_param('display_name')));
            }

            if ($request->has_param('is_searchable')) {
                $definition->setSearchable((bool) $request->get_param('is_searchable'));
            }

            if ($request->has_param('is_required')) {
                $definition->setRequired((bool) $request->get_param('is_required'));
            }

            if ($request->has_param('validation_rule')) {
                $validationRule = $request->get_param('validation_rule');
                $definition->updateValidationRule(
                    empty($validationRule)
                        ? null
                        : \SagaManager\Domain\Entity\ValidationRule::fromArray($validationRule)
                );
            }

            if ($request->has_param('default_value')) {
                $defaultValue = $request->get_param('default_value');
                $definition->updateDefaultValue($defaultValue === '' ? null : $defaultValue);
            }

            $this->repository->save($definition);

            return new \WP_REST_Response($this->formatDefinition($definition), 200);

        } catch (EntityNotFoundException $e) {
            return new \WP_REST_Response(
                ['error' => 'Attribute definition not found', 'message' => $e->getMessage()],
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
     * DELETE /wp-json/saga/v1/attribute-definitions/{id}
     */
    public function delete(\WP_REST_Request $request): \WP_REST_Response
    {
        try {
            $id = new AttributeDefinitionId((int) $request->get_param('id'));

            // Verify exists
            $this->repository->findById($id);

            $this->repository->delete($id);

            return new \WP_REST_Response(null, 204);

        } catch (EntityNotFoundException $e) {
            return new \WP_REST_Response(
                ['error' => 'Attribute definition not found', 'message' => $e->getMessage()],
                404
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
     * Permission check for admin operations (create, update, delete)
     */
    public function checkAdminPermission(): bool
    {
        return current_user_can('manage_options');
    }

    /**
     * Format definition for API response
     */
    private function formatDefinition(AttributeDefinition $definition): array
    {
        return [
            'id' => $definition->getId()?->value(),
            'entity_type' => $definition->getEntityType()->value,
            'attribute_key' => $definition->getAttributeKey(),
            'display_name' => $definition->getDisplayName(),
            'data_type' => $definition->getDataType()->value,
            'data_type_label' => $definition->getDataType()->label(),
            'is_searchable' => $definition->isSearchable(),
            'is_required' => $definition->isRequired(),
            'validation_rule' => $definition->getValidationRule()?->toArray(),
            'default_value' => $definition->getDefaultValue(),
            'created_at' => $definition->getCreatedAt()->format('c'),
        ];
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

    /**
     * Get arguments for index endpoint
     */
    private function getIndexArgs(): array
    {
        return [
            'entity_type' => [
                'required' => false,
                'type' => 'string',
                'enum' => array_map(fn($t) => $t->value, EntityType::cases()),
                'description' => 'Filter by entity type',
            ],
        ];
    }

    /**
     * Get arguments for create endpoint
     */
    private function getCreateArgs(): array
    {
        return [
            'entity_type' => [
                'required' => true,
                'type' => 'string',
                'enum' => array_map(fn($t) => $t->value, EntityType::cases()),
            ],
            'attribute_key' => [
                'required' => true,
                'type' => 'string',
                'pattern' => '^[a-z][a-z0-9_]*$',
                'maxLength' => 100,
            ],
            'display_name' => [
                'required' => true,
                'type' => 'string',
                'maxLength' => 150,
            ],
            'data_type' => [
                'required' => true,
                'type' => 'string',
                'enum' => array_map(fn($t) => $t->value, DataType::cases()),
            ],
            'is_searchable' => [
                'type' => 'boolean',
                'default' => false,
            ],
            'is_required' => [
                'type' => 'boolean',
                'default' => false,
            ],
            'validation_rule' => [
                'type' => 'object',
            ],
            'default_value' => [
                'type' => 'string',
            ],
        ];
    }

    /**
     * Get arguments for update endpoint
     */
    private function getUpdateArgs(): array
    {
        return [
            'id' => [
                'required' => true,
                'validate_callback' => fn($param) => is_numeric($param) && $param > 0,
            ],
            'display_name' => [
                'type' => 'string',
                'maxLength' => 150,
            ],
            'is_searchable' => [
                'type' => 'boolean',
            ],
            'is_required' => [
                'type' => 'boolean',
            ],
            'validation_rule' => [
                'type' => 'object',
            ],
            'default_value' => [
                'type' => 'string',
            ],
        ];
    }
}
