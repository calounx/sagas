<?php
/**
 * API Endpoint Contract
 *
 * This file defines the API contract between backend and frontend plugins.
 * It should be included in BOTH plugins to ensure consistency.
 *
 * @package SagaManager\Contract
 */

declare(strict_types=1);

namespace SagaManager\Contract;

/**
 * Defines all REST API endpoints and their expected behavior
 */
final class ApiEndpoints
{
    public const NAMESPACE = 'saga/v1';
    public const VERSION = '1.2.0';

    // Saga endpoints
    public const SAGAS = '/sagas';
    public const SAGA_SINGLE = '/sagas/(?P<id>\d+)';

    // Entity endpoints
    public const ENTITIES = '/entities';
    public const ENTITY_SINGLE = '/entities/(?P<id>\d+)';
    public const ENTITY_ATTRIBUTES = '/entities/(?P<id>\d+)/attributes';
    public const ENTITY_RELATIONSHIPS = '/entities/(?P<id>\d+)/relationships';

    // Relationship endpoints
    public const RELATIONSHIPS = '/relationships';
    public const RELATIONSHIP_SINGLE = '/relationships/(?P<id>\d+)';

    // Timeline endpoints
    public const TIMELINE = '/timeline';
    public const TIMELINE_EVENT = '/timeline/(?P<id>\d+)';

    // Search endpoints
    public const SEARCH = '/search';
    public const SEARCH_SEMANTIC = '/search/semantic';

    // Attribute definition endpoints
    public const ATTRIBUTE_DEFINITIONS = '/attribute-definitions';

    // Quality/Health endpoints
    public const HEALTH = '/health';
    public const QUALITY = '/entities/(?P<id>\d+)/quality';

    /**
     * Build full endpoint URL
     */
    public static function url(string $endpoint, array $params = []): string
    {
        $path = self::NAMESPACE . $endpoint;

        // Replace path parameters
        foreach ($params as $key => $value) {
            $path = str_replace("(?P<{$key}>\d+)", (string)$value, $path);
        }

        return rest_url($path);
    }
}

/**
 * Entity type utilities for API contracts
 *
 * Uses the canonical EntityType enum from the Domain layer as the single source of truth.
 * Provides static helper methods for contract validation.
 *
 * @see \SagaManager\Domain\Entity\EntityType The canonical enum definition
 */
final class EntityTypes
{
    /**
     * Get all valid entity type values
     *
     * @return string[]
     */
    public static function all(): array
    {
        return array_map(
            fn(\SagaManager\Domain\Entity\EntityType $type) => $type->value,
            \SagaManager\Domain\Entity\EntityType::cases()
        );
    }

    /**
     * Check if a type string is valid
     */
    public static function isValid(string $type): bool
    {
        return in_array($type, self::all(), true);
    }

    /**
     * Get human-readable label for a type
     */
    public static function getLabel(string $type): string
    {
        try {
            $enumType = \SagaManager\Domain\Entity\EntityType::from($type);
            return $enumType->label();
        } catch (\ValueError) {
            return ucfirst($type);
        }
    }

    /**
     * Convert string to EntityType enum
     *
     * @throws \ValueError If type is invalid
     */
    public static function toEnum(string $type): \SagaManager\Domain\Entity\EntityType
    {
        return \SagaManager\Domain\Entity\EntityType::from($type);
    }

    /**
     * Try to convert string to EntityType enum, return null on failure
     */
    public static function tryToEnum(string $type): ?\SagaManager\Domain\Entity\EntityType
    {
        return \SagaManager\Domain\Entity\EntityType::tryFrom($type);
    }
}

/**
 * Relationship type definitions
 */
final class RelationshipTypes
{
    // Character relationships
    public const PARENT_OF = 'parent_of';
    public const CHILD_OF = 'child_of';
    public const SIBLING_OF = 'sibling_of';
    public const SPOUSE_OF = 'spouse_of';
    public const MENTOR_OF = 'mentor_of';
    public const STUDENT_OF = 'student_of';
    public const ALLY_OF = 'ally_of';
    public const ENEMY_OF = 'enemy_of';

    // Faction relationships
    public const MEMBER_OF = 'member_of';
    public const LEADER_OF = 'leader_of';
    public const ALLIED_WITH = 'allied_with';
    public const AT_WAR_WITH = 'at_war_with';

    // Location relationships
    public const LOCATED_IN = 'located_in';
    public const CONTAINS = 'contains';
    public const NEAR = 'near';

    // Event relationships
    public const PARTICIPATED_IN = 'participated_in';
    public const CAUSED = 'caused';
    public const RESULTED_FROM = 'resulted_from';

    // Artifact relationships
    public const OWNS = 'owns';
    public const OWNED_BY = 'owned_by';
    public const CREATED = 'created';
    public const CREATED_BY = 'created_by';

    /**
     * Get inverse relationship type
     */
    public static function getInverse(string $type): ?string
    {
        return match ($type) {
            self::PARENT_OF => self::CHILD_OF,
            self::CHILD_OF => self::PARENT_OF,
            self::MENTOR_OF => self::STUDENT_OF,
            self::STUDENT_OF => self::MENTOR_OF,
            self::OWNS => self::OWNED_BY,
            self::OWNED_BY => self::OWNS,
            self::CREATED => self::CREATED_BY,
            self::CREATED_BY => self::CREATED,
            self::CAUSED => self::RESULTED_FROM,
            self::RESULTED_FROM => self::CAUSED,
            self::CONTAINS => self::LOCATED_IN,
            self::LOCATED_IN => self::CONTAINS,
            // Symmetric relationships
            self::SIBLING_OF, self::SPOUSE_OF, self::ALLY_OF, self::ENEMY_OF,
            self::ALLIED_WITH, self::AT_WAR_WITH, self::NEAR => $type,
            default => null,
        };
    }
}

/**
 * Standard API response format
 */
final class ResponseFormat
{
    /**
     * Success response structure
     *
     * @param mixed $data Response data
     * @param array $meta Optional metadata (pagination, etc.)
     */
    public static function success(mixed $data, array $meta = []): array
    {
        $response = [
            'success' => true,
            'data' => $data,
        ];

        if (!empty($meta)) {
            $response['meta'] = $meta;
        }

        return $response;
    }

    /**
     * Error response structure
     */
    public static function error(
        string $code,
        string $message,
        int $status = 400,
        array $details = []
    ): \WP_Error {
        return new \WP_Error($code, $message, [
            'status' => $status,
            'details' => $details,
        ]);
    }

    /**
     * Paginated response structure
     */
    public static function paginated(
        array $items,
        int $total,
        int $page,
        int $perPage
    ): array {
        return self::success($items, [
            'pagination' => [
                'total' => $total,
                'total_pages' => (int) ceil($total / $perPage),
                'current_page' => $page,
                'per_page' => $perPage,
                'has_next' => ($page * $perPage) < $total,
                'has_previous' => $page > 1,
            ],
        ]);
    }
}

/**
 * Common query parameters
 */
final class QueryParams
{
    public const PAGE = 'page';
    public const PER_PAGE = 'per_page';
    public const SEARCH = 'search';
    public const SAGA_ID = 'saga_id';
    public const ENTITY_TYPE = 'entity_type';
    public const ORDER_BY = 'orderby';
    public const ORDER = 'order';
    public const INCLUDE = 'include';
    public const EXCLUDE = 'exclude';

    public const DEFAULT_PER_PAGE = 20;
    public const MAX_PER_PAGE = 100;

    /**
     * Sanitize and validate common query params
     */
    public static function sanitize(\WP_REST_Request $request): array
    {
        return [
            'page' => max(1, (int) $request->get_param(self::PAGE) ?: 1),
            'per_page' => min(
                self::MAX_PER_PAGE,
                max(1, (int) $request->get_param(self::PER_PAGE) ?: self::DEFAULT_PER_PAGE)
            ),
            'search' => sanitize_text_field($request->get_param(self::SEARCH) ?? ''),
            'saga_id' => absint($request->get_param(self::SAGA_ID) ?? 0),
            'entity_type' => sanitize_key($request->get_param(self::ENTITY_TYPE) ?? ''),
            'orderby' => sanitize_key($request->get_param(self::ORDER_BY) ?? 'id'),
            'order' => strtoupper($request->get_param(self::ORDER) ?? 'ASC') === 'DESC' ? 'DESC' : 'ASC',
        ];
    }
}
