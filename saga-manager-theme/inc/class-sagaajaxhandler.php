<?php
declare(strict_types=1);

namespace SagaTheme;

/**
 * AJAX Handler for Saga Manager Theme
 *
 * Handles AJAX requests with proper security (nonce verification)
 * Provides endpoints for filtering and searching entities
 *
 * @package SagaTheme
 */
class SagaAjaxHandler
{
    private SagaQueries $queries;
    private SagaHelpers $helpers;

    /**
     * Constructor
     *
     * @param SagaQueries $queries Query service instance
     * @param SagaHelpers $helpers Helper service instance
     */
    public function __construct(SagaQueries $queries, SagaHelpers $helpers)
    {
        $this->queries = $queries;
        $this->helpers = $helpers;
    }

    /**
     * Register AJAX endpoints
     *
     * @return void
     */
    public function registerEndpoints(): void
    {
        // Filter entities (public and authenticated)
        add_action('wp_ajax_saga_filter_entities', [$this, 'filterEntities']);
        add_action('wp_ajax_nopriv_saga_filter_entities', [$this, 'filterEntities']);

        // Search entities (public and authenticated)
        add_action('wp_ajax_saga_search_entities', [$this, 'searchEntities']);
        add_action('wp_ajax_nopriv_saga_search_entities', [$this, 'searchEntities']);

        // Autocomplete search (public and authenticated)
        add_action('wp_ajax_saga_autocomplete_search', [$this, 'autocompleteSearch']);
        add_action('wp_ajax_nopriv_saga_autocomplete_search', [$this, 'autocompleteSearch']);

        // Get relationships (public and authenticated)
        add_action('wp_ajax_saga_get_relationships', [$this, 'getRelationships']);
        add_action('wp_ajax_nopriv_saga_get_relationships', [$this, 'getRelationships']);
    }

    /**
     * Filter entities by type and saga
     *
     * @return void
     */
    public function filterEntities(): void
    {
        // Verify nonce
        if (!$this->verifyNonce('saga_filter_nonce', 'saga_filter')) {
            $this->sendError('Security check failed', 403);
            return;
        }

        // Sanitize and validate input
        $entityType = isset($_POST['entity_type']) ? sanitize_key($_POST['entity_type']) : null;
        $sagaId = isset($_POST['saga_id']) ? absint($_POST['saga_id']) : null;
        $limit = isset($_POST['limit']) ? absint($_POST['limit']) : 20;
        $offset = isset($_POST['offset']) ? absint($_POST['offset']) : 0;

        // Validate entity type
        $validTypes = ['character', 'location', 'event', 'faction', 'artifact', 'concept'];
        if ($entityType !== null && !in_array($entityType, $validTypes, true)) {
            $this->sendError('Invalid entity type', 400);
            return;
        }

        // Limit boundaries
        $limit = max(1, min(50, $limit));

        try {
            // Query entities
            if ($entityType !== null) {
                $entities = $this->queries->getEntitiesByType($entityType, $sagaId, $limit);
            } elseif ($sagaId !== null) {
                $entities = $this->queries->getEntitiesBySaga($sagaId, $limit, $offset);
            } else {
                $entities = $this->queries->getRecentEntities($limit);
            }

            // Format response
            $formatted = array_map(function ($entity) {
                return $this->formatEntityForResponse($entity);
            }, $entities);

            $this->sendSuccess([
                'entities' => $formatted,
                'count' => count($formatted),
                'offset' => $offset,
            ]);

        } catch (\Exception $e) {
            error_log('[SAGA-THEME][ERROR] Filter entities failed: ' . $e->getMessage());
            $this->sendError('Failed to retrieve entities', 500);
        }
    }

    /**
     * Search entities by name
     *
     * @return void
     */
    public function searchEntities(): void
    {
        // Verify nonce
        if (!$this->verifyNonce('saga_search_nonce', 'saga_search')) {
            $this->sendError('Security check failed', 403);
            return;
        }

        // Sanitize input
        $searchTerm = isset($_POST['search']) ? sanitize_text_field($_POST['search']) : '';
        $sagaId = isset($_POST['saga_id']) ? absint($_POST['saga_id']) : null;
        $limit = isset($_POST['limit']) ? absint($_POST['limit']) : 20;

        // Validate search term
        if (strlen($searchTerm) < 2) {
            $this->sendError('Search term must be at least 2 characters', 400);
            return;
        }

        // Limit boundaries
        $limit = max(1, min(50, $limit));

        try {
            $entities = $this->queries->searchEntities($searchTerm, $sagaId, $limit);

            $formatted = array_map(function ($entity) {
                return $this->formatEntityForResponse($entity);
            }, $entities);

            $this->sendSuccess([
                'entities' => $formatted,
                'count' => count($formatted),
                'search_term' => $searchTerm,
            ]);

        } catch (\Exception $e) {
            error_log('[SAGA-THEME][ERROR] Search entities failed: ' . $e->getMessage());
            $this->sendError('Search failed', 500);
        }
    }

    /**
     * Autocomplete search for real-time suggestions
     *
     * @return void
     */
    public function autocompleteSearch(): void
    {
        // Verify nonce
        if (!$this->verifyNonce('saga_autocomplete_nonce', 'saga_autocomplete')) {
            $this->sendError('Security check failed', 403);
            return;
        }

        // Sanitize input
        $query = isset($_POST['q']) ? sanitize_text_field($_POST['q']) : '';
        $sagaId = isset($_POST['saga_id']) ? absint($_POST['saga_id']) : null;
        $limit = isset($_POST['limit']) ? absint($_POST['limit']) : 10;

        // Validate query length
        if (strlen($query) < 2) {
            $this->sendSuccess([
                'results' => [],
                'grouped' => [],
                'total' => 0,
            ]);
            return;
        }

        // Limit boundaries
        $limit = max(1, min(20, $limit));

        try {
            // Search entities
            $entities = $this->queries->searchEntities($query, $sagaId, $limit * 2);

            // Group results by entity type
            $grouped = $this->groupResultsByType($entities, $limit);

            // Format all results
            $allResults = [];
            foreach ($grouped as $type => $items) {
                foreach ($items as $entity) {
                    $allResults[] = $this->formatAutocompleteResult($entity, $query);
                }
            }

            $this->sendSuccess([
                'results' => $allResults,
                'grouped' => array_map(function ($items) use ($query) {
                    return array_map(function ($entity) use ($query) {
                        return $this->formatAutocompleteResult($entity, $query);
                    }, $items);
                }, $grouped),
                'total' => count($allResults),
                'query' => $query,
            ]);

        } catch (\Exception $e) {
            error_log('[SAGA-THEME][ERROR] Autocomplete search failed: ' . $e->getMessage());
            $this->sendError('Autocomplete search failed', 500);
        }
    }

    /**
     * Get entity relationships
     *
     * @return void
     */
    public function getRelationships(): void
    {
        // Verify nonce
        if (!$this->verifyNonce('saga_relationships_nonce', 'saga_relationships')) {
            $this->sendError('Security check failed', 403);
            return;
        }

        // Sanitize input
        $entityId = isset($_POST['entity_id']) ? absint($_POST['entity_id']) : 0;
        $direction = isset($_POST['direction']) ? sanitize_key($_POST['direction']) : 'both';
        $limit = isset($_POST['limit']) ? absint($_POST['limit']) : 10;

        // Validate entity ID
        if ($entityId === 0) {
            $this->sendError('Entity ID is required', 400);
            return;
        }

        // Validate direction
        $validDirections = ['outgoing', 'incoming', 'both'];
        if (!in_array($direction, $validDirections, true)) {
            $direction = 'both';
        }

        // Limit boundaries
        $limit = max(1, min(50, $limit));

        try {
            $relationships = $this->queries->getRelatedEntities($entityId, $direction, $limit);

            $formatted = array_map(function ($rel) {
                return [
                    'relationship_id' => (int) $rel->relationship_id,
                    'relationship_type' => $rel->relationship_type,
                    'relationship_type_formatted' => $this->helpers->formatRelationshipType($rel->relationship_type),
                    'strength' => (int) $rel->strength,
                    'strength_badge' => $this->helpers->getRelationshipStrengthBadge((int) $rel->strength),
                    'direction' => $rel->direction,
                    'entity' => $this->formatEntityForResponse($rel),
                ];
            }, $relationships);

            $this->sendSuccess([
                'relationships' => $formatted,
                'count' => count($formatted),
                'entity_id' => $entityId,
                'direction' => $direction,
            ]);

        } catch (\Exception $e) {
            error_log('[SAGA-THEME][ERROR] Get relationships failed: ' . $e->getMessage());
            $this->sendError('Failed to retrieve relationships', 500);
        }
    }

    /**
     * Group search results by entity type
     *
     * @param array $entities Array of entity objects
     * @param int $limitPerType Maximum items per type
     * @return array Grouped entities by type
     */
    private function groupResultsByType(array $entities, int $limitPerType = 10): array
    {
        $grouped = [
            'character' => [],
            'location' => [],
            'event' => [],
            'faction' => [],
            'artifact' => [],
            'concept' => [],
        ];

        foreach ($entities as $entity) {
            $type = $entity->entity_type ?? '';

            if (isset($grouped[$type]) && count($grouped[$type]) < $limitPerType) {
                $grouped[$type][] = $entity;
            }
        }

        // Remove empty groups
        return array_filter($grouped, function ($items) {
            return !empty($items);
        });
    }

    /**
     * Format entity for autocomplete result
     *
     * @param object $entity Entity object
     * @param string $query Search query for highlighting
     * @return array Formatted autocomplete result
     */
    private function formatAutocompleteResult(object $entity, string $query): array
    {
        $name = $entity->canonical_name ?? '';
        $highlightedName = $this->highlightMatch($name, $query);

        $excerpt = $this->helpers->getEntityExcerpt($entity, 15);
        $highlightedExcerpt = $this->highlightMatch($excerpt, $query);

        return [
            'id' => (int) ($entity->id ?? $entity->entity_id ?? 0),
            'title' => $name,
            'title_highlighted' => $highlightedName,
            'type' => $entity->entity_type ?? '',
            'type_label' => $this->helpers->formatEntityType($entity->entity_type ?? ''),
            'type_icon' => $this->getEntityTypeIcon($entity->entity_type ?? ''),
            'excerpt' => $excerpt,
            'excerpt_highlighted' => $highlightedExcerpt,
            'url' => $this->helpers->getEntityPermalink($entity),
            'importance_score' => (int) ($entity->importance_score ?? 0),
        ];
    }

    /**
     * Highlight search term matches in text
     *
     * @param string $text Text to highlight
     * @param string $query Search query
     * @return string Text with <mark> tags around matches
     */
    private function highlightMatch(string $text, string $query): string
    {
        if (empty($query) || empty($text)) {
            return $text;
        }

        // Escape special regex characters in query
        $query = preg_quote($query, '/');

        // Case-insensitive highlighting
        return preg_replace(
            '/(' . $query . ')/ui',
            '<mark>$1</mark>',
            $text
        ) ?? $text;
    }

    /**
     * Get icon class for entity type
     *
     * @param string $type Entity type
     * @return string Icon class/emoji
     */
    private function getEntityTypeIcon(string $type): string
    {
        $icons = [
            'character' => '👤',
            'location' => '📍',
            'event' => '⚡',
            'faction' => '🛡️',
            'artifact' => '⚔️',
            'concept' => '💡',
        ];

        return $icons[$type] ?? '📄';
    }

    /**
     * Format entity object for JSON response
     *
     * @param object $entity Entity object
     * @return array Formatted entity data
     */
    private function formatEntityForResponse(object $entity): array
    {
        return [
            'id' => (int) ($entity->id ?? $entity->entity_id ?? 0),
            'canonical_name' => $entity->canonical_name ?? '',
            'slug' => $entity->slug ?? '',
            'entity_type' => $entity->entity_type ?? '',
            'entity_type_badge' => $this->helpers->getEntityTypeBadge($entity->entity_type ?? ''),
            'importance_score' => (int) ($entity->importance_score ?? 0),
            'wp_post_id' => isset($entity->wp_post_id) ? (int) $entity->wp_post_id : null,
            'permalink' => $this->helpers->getEntityPermalink($entity),
            'excerpt' => $this->helpers->getEntityExcerpt($entity, 20),
        ];
    }

    /**
     * Verify nonce for security
     *
     * @param string $nonceField Nonce field name in $_POST
     * @param string $nonceAction Nonce action
     * @return bool True if nonce is valid
     */
    private function verifyNonce(string $nonceField, string $nonceAction): bool
    {
        if (!isset($_POST[$nonceField])) {
            return false;
        }

        return wp_verify_nonce($_POST[$nonceField], $nonceAction) !== false;
    }

    /**
     * Send JSON success response
     *
     * @param array $data Response data
     * @return void
     */
    private function sendSuccess(array $data): void
    {
        wp_send_json_success($data);
    }

    /**
     * Send JSON error response
     *
     * @param string $message Error message
     * @param int $statusCode HTTP status code
     * @return void
     */
    private function sendError(string $message, int $statusCode = 400): void
    {
        status_header($statusCode);
        wp_send_json_error(['message' => $message]);
    }
}
