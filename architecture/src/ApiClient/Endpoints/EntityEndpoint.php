<?php

declare(strict_types=1);

namespace SagaManagerDisplay\ApiClient\Endpoints;

use SagaManager\Contract\ApiEndpoints;
use SagaManagerDisplay\ApiClient\SagaApiClient;
use SagaManagerDisplay\ApiClient\ApiResponse;
use SagaManagerDisplay\DTO\EntityDTO;

/**
 * Entity API endpoint wrapper
 */
final class EntityEndpoint
{
    public function __construct(
        private readonly SagaApiClient $client
    ) {}

    /**
     * Get entity by ID
     *
     * @param int $id Entity ID
     * @param array $include Optional relations to include: ['attributes', 'relationships']
     * @return EntityDTO|null
     */
    public function get(int $id, array $include = []): ?EntityDTO
    {
        $endpoint = str_replace('(?P<id>\d+)', (string) $id, ApiEndpoints::ENTITY_SINGLE);
        $params = [];

        if (!empty($include)) {
            $params['include'] = implode(',', $include);
        }

        $response = $this->client->get($endpoint, $params);

        if (!$response->isSuccess()) {
            return null;
        }

        return EntityDTO::fromArray($response->getData());
    }

    /**
     * List entities with filtering
     *
     * @return array{items: EntityDTO[], pagination: array}
     */
    public function list(
        ?int $sagaId = null,
        ?string $type = null,
        ?string $search = null,
        int $page = 1,
        int $perPage = 20,
        string $orderBy = 'canonical_name',
        string $order = 'ASC'
    ): array {
        $params = array_filter([
            'saga_id' => $sagaId,
            'entity_type' => $type,
            'search' => $search,
            'page' => $page,
            'per_page' => $perPage,
            'orderby' => $orderBy,
            'order' => $order,
        ], fn($v) => $v !== null && $v !== '');

        $response = $this->client->get(ApiEndpoints::ENTITIES, $params);

        if (!$response->isSuccess()) {
            return ['items' => [], 'pagination' => []];
        }

        $items = array_map(
            fn(array $data) => EntityDTO::fromArray($data),
            $response->getData()
        );

        return [
            'items' => $items,
            'pagination' => $response->getPagination(),
        ];
    }

    /**
     * Get entities by IDs (batch)
     *
     * @param int[] $ids
     * @return EntityDTO[]
     */
    public function getByIds(array $ids): array
    {
        if (empty($ids)) {
            return [];
        }

        $response = $this->client->get(ApiEndpoints::ENTITIES, [
            'include' => implode(',', $ids),
            'per_page' => count($ids),
        ]);

        if (!$response->isSuccess()) {
            return [];
        }

        return array_map(
            fn(array $data) => EntityDTO::fromArray($data),
            $response->getData()
        );
    }

    /**
     * Get entity attributes
     */
    public function getAttributes(int $id): array
    {
        $endpoint = str_replace('(?P<id>\d+)', (string) $id, ApiEndpoints::ENTITY_ATTRIBUTES);
        $response = $this->client->get($endpoint);

        if (!$response->isSuccess()) {
            return [];
        }

        return $response->getData();
    }

    /**
     * Get entity relationships
     */
    public function getRelationships(
        int $id,
        string $direction = 'both',
        ?string $type = null
    ): array {
        $endpoint = str_replace('(?P<id>\d+)', (string) $id, ApiEndpoints::ENTITY_RELATIONSHIPS);

        $params = ['direction' => $direction];
        if ($type) {
            $params['type'] = $type;
        }

        $response = $this->client->get($endpoint, $params);

        if (!$response->isSuccess()) {
            return [];
        }

        return $response->getData();
    }

    /**
     * Get entities by type for a saga
     */
    public function getByType(int $sagaId, string $type, int $limit = 100): array
    {
        $result = $this->list(
            sagaId: $sagaId,
            type: $type,
            perPage: $limit,
            orderBy: 'importance_score',
            order: 'DESC'
        );

        return $result['items'];
    }

    /**
     * Get most important entities for a saga
     */
    public function getTopEntities(int $sagaId, int $limit = 10): array
    {
        $result = $this->list(
            sagaId: $sagaId,
            perPage: $limit,
            orderBy: 'importance_score',
            order: 'DESC'
        );

        return $result['items'];
    }
}
