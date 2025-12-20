<?php
/**
 * Relationships graph shortcode handler.
 *
 * @package SagaManagerDisplay
 */

declare(strict_types=1);

namespace SagaManagerDisplay\Shortcode;

use WP_Error;

/**
 * Shortcode: [saga_relationships entity="123"]
 *
 * Displays a relationship graph for an entity.
 */
class RelationshipsShortcode extends AbstractShortcode
{
    protected string $shortcodeTag = 'saga_relationships';

    /**
     * Get default attributes.
     *
     * @return array Default attributes.
     */
    protected function getDefaultAttributes(): array
    {
        return [
            'entity' => '',
            'layout' => 'graph', // graph, list, tree
            'depth' => '1', // how many levels of relationships to show
            'limit' => '20', // max relationships to display
            'types' => '', // filter by relationship types
            'direction' => 'both', // incoming, outgoing, both
            'show_strength' => 'true',
            'show_labels' => 'true',
            'interactive' => 'true',
            'height' => '400', // graph height in pixels
            'class' => '',
            'id' => '',
        ];
    }

    /**
     * Validate attributes.
     *
     * @param array $atts Attributes to validate.
     * @return true|WP_Error True if valid.
     */
    protected function validateAttributes(array $atts): true|WP_Error
    {
        if (empty($atts['entity'])) {
            return new WP_Error(
                'missing_entity',
                __('Entity ID is required.', 'saga-manager-display')
            );
        }

        $validLayouts = ['graph', 'list', 'tree'];
        if (!in_array($atts['layout'], $validLayouts, true)) {
            return new WP_Error(
                'invalid_layout',
                sprintf(
                    __('Invalid layout. Valid options: %s', 'saga-manager-display'),
                    implode(', ', $validLayouts)
                )
            );
        }

        $validDirections = ['incoming', 'outgoing', 'both'];
        if (!in_array($atts['direction'], $validDirections, true)) {
            return new WP_Error(
                'invalid_direction',
                sprintf(
                    __('Invalid direction. Valid options: %s', 'saga-manager-display'),
                    implode(', ', $validDirections)
                )
            );
        }

        $depth = $this->parseInt($atts['depth'], 1);
        if ($depth < 1 || $depth > 3) {
            return new WP_Error(
                'invalid_depth',
                __('Depth must be between 1 and 3.', 'saga-manager-display')
            );
        }

        return true;
    }

    /**
     * Render the shortcode.
     *
     * @param array $atts Parsed attributes.
     * @param string|null $content Shortcode content.
     * @return string Rendered output.
     */
    protected function doRender(array $atts, ?string $content): string
    {
        $entityId = $this->parseInt($atts['entity']);

        // Fetch the source entity
        $entity = $this->apiClient->getEntity($entityId);
        if (is_wp_error($entity)) {
            return $this->renderError($entity->get_error_message());
        }

        // Build API args
        $apiArgs = [
            'limit' => $this->parseInt($atts['limit'], 20),
            'direction' => $atts['direction'],
        ];

        if (!empty($atts['types'])) {
            $apiArgs['types'] = $atts['types'];
        }

        // Fetch relationships
        $relationships = $this->apiClient->getRelationships($entityId, $apiArgs);
        if (is_wp_error($relationships)) {
            return $this->renderError($relationships->get_error_message());
        }

        $relationshipData = $relationships['data'] ?? [];

        if (empty($relationshipData)) {
            return $this->renderWarning(
                __('No relationships found for this entity.', 'saga-manager-display')
            );
        }

        // Fetch additional depth levels if requested
        $depth = $this->parseInt($atts['depth'], 1);
        $allNodes = [$entity];
        $allEdges = [];

        // Process first level
        foreach ($relationshipData as $rel) {
            $allEdges[] = $this->formatEdge($rel, $entityId);

            // Add connected entity to nodes
            $connectedId = $rel['source_entity_id'] == $entityId
                ? $rel['target_entity_id']
                : $rel['source_entity_id'];

            if (isset($rel['connected_entity'])) {
                $allNodes[] = $rel['connected_entity'];
            }
        }

        // Fetch deeper levels if needed
        if ($depth > 1) {
            $processedIds = [$entityId];
            $currentLevel = array_map(function ($rel) use ($entityId) {
                return $rel['source_entity_id'] == $entityId
                    ? $rel['target_entity_id']
                    : $rel['source_entity_id'];
            }, $relationshipData);

            for ($level = 2; $level <= $depth; $level++) {
                $nextLevel = [];

                foreach ($currentLevel as $nodeId) {
                    if (in_array($nodeId, $processedIds, true)) {
                        continue;
                    }
                    $processedIds[] = $nodeId;

                    $subRels = $this->apiClient->getRelationships((int) $nodeId, [
                        'limit' => 5, // Limit sub-relationships
                        'direction' => $atts['direction'],
                    ]);

                    if (!is_wp_error($subRels) && !empty($subRels['data'])) {
                        foreach ($subRels['data'] as $rel) {
                            $allEdges[] = $this->formatEdge($rel, $nodeId);

                            $connectedId = $rel['source_entity_id'] == $nodeId
                                ? $rel['target_entity_id']
                                : $rel['source_entity_id'];

                            if (!in_array($connectedId, $processedIds, true)) {
                                $nextLevel[] = $connectedId;
                            }

                            if (isset($rel['connected_entity'])) {
                                $allNodes[] = $rel['connected_entity'];
                            }
                        }
                    }
                }

                $currentLevel = $nextLevel;
            }
        }

        // Deduplicate nodes
        $uniqueNodes = [];
        foreach ($allNodes as $node) {
            $nodeId = $node['id'] ?? 0;
            if ($nodeId && !isset($uniqueNodes[$nodeId])) {
                $uniqueNodes[$nodeId] = $node;
            }
        }

        // Prepare template data
        $templateData = [
            'source_entity' => $entity,
            'nodes' => array_values($uniqueNodes),
            'edges' => $allEdges,
            'options' => [
                'layout' => $atts['layout'],
                'show_strength' => $this->parseBool($atts['show_strength']),
                'show_labels' => $this->parseBool($atts['show_labels']),
                'interactive' => $this->parseBool($atts['interactive']),
                'height' => $this->parseInt($atts['height'], 400),
                'depth' => $depth,
            ],
        ];

        // Allow filtering template data
        $templateData = apply_filters('saga_display_relationships_data', $templateData, $atts);

        // Select template based on layout
        $template = match ($atts['layout']) {
            'list' => 'relationships/list',
            'tree' => 'relationships/tree',
            default => 'relationships/graph',
        };

        $output = $this->templateEngine->render($template, $templateData);

        // Add data attributes for JavaScript
        $dataAttrs = '';
        if ($this->parseBool($atts['interactive']) && $atts['layout'] === 'graph') {
            $dataAttrs = ' ' . $this->dataAttributes([
                'entity' => $entityId,
                'nodes' => array_values($uniqueNodes),
                'edges' => $allEdges,
                'height' => $atts['height'],
            ]);
        }

        $classes = [
            'saga-relationships',
            'saga-relationships--' . $atts['layout'],
            $this->parseBool($atts['interactive']) ? 'saga-relationships--interactive' : '',
        ];

        $style = $atts['layout'] === 'graph'
            ? sprintf(' style="height: %dpx;"', $this->parseInt($atts['height'], 400))
            : '';

        return sprintf(
            '<div class="%s"%s%s>%s</div>',
            esc_attr(implode(' ', array_filter($classes))),
            $dataAttrs,
            $style,
            $output
        );
    }

    /**
     * Format a relationship as an edge for the graph.
     *
     * @param array $relationship Relationship data.
     * @param int $sourceId Source entity ID.
     * @return array Formatted edge.
     */
    private function formatEdge(array $relationship, int $sourceId): array
    {
        $isOutgoing = $relationship['source_entity_id'] == $sourceId;

        return [
            'source' => $relationship['source_entity_id'],
            'target' => $relationship['target_entity_id'],
            'type' => $relationship['relationship_type'] ?? 'related',
            'strength' => $relationship['strength'] ?? 50,
            'label' => $relationship['relationship_type'] ?? '',
            'direction' => $isOutgoing ? 'outgoing' : 'incoming',
            'valid_from' => $relationship['valid_from'] ?? null,
            'valid_until' => $relationship['valid_until'] ?? null,
        ];
    }
}
