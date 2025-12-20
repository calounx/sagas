<?php

declare(strict_types=1);

namespace SagaManagerDisplay\Shortcode;

use SagaManager\Contract\EntityTypes;

/**
 * [saga_entities] shortcode - Display a list of entities
 *
 * Usage:
 *   [saga_entities saga="1"]
 *   [saga_entities saga="1" type="character"]
 *   [saga_entities saga="1" type="character" limit="10" orderby="importance_score" order="desc"]
 *   [saga_entities saga="1" template="grid" columns="3"]
 */
final class EntityListShortcode extends AbstractShortcode
{
    protected string $tag = 'saga_entities';

    protected array $defaults = [
        'saga' => 0,
        'type' => '',
        'limit' => 20,
        'page' => 1,
        'orderby' => 'canonical_name',
        'order' => 'asc',
        'template' => 'list',
        'columns' => 3,
        'show_pagination' => 'true',
        'class' => '',
    ];

    public function render(array $atts, ?string $content = null, string $tag = ''): string
    {
        $atts = $this->parseAttributes($atts);

        $sagaId = absint($atts['saga']);
        if ($sagaId === 0) {
            return $this->renderError(__('Saga ID is required.', 'saga-manager-display'));
        }

        // Validate entity type
        $entityType = $atts['type'];
        if ($entityType && !EntityTypes::isValid($entityType)) {
            return $this->renderError(
                sprintf(__('Invalid entity type: %s', 'saga-manager-display'), $entityType)
            );
        }

        try {
            $result = $this->apiClient->entities->list(
                sagaId: $sagaId,
                type: $entityType ?: null,
                page: max(1, absint($atts['page'])),
                perPage: min(100, max(1, absint($atts['limit']))),
                orderBy: $this->sanitizeOrderBy($atts['orderby']),
                order: strtoupper($atts['order']) === 'DESC' ? 'DESC' : 'ASC'
            );

            if (empty($result['items'])) {
                return $this->renderTemplate('entity-list-empty', [
                    'saga_id' => $sagaId,
                    'type' => $entityType,
                ]);
            }

            return $this->renderTemplate('entity-' . $atts['template'], [
                'entities' => $result['items'],
                'pagination' => $result['pagination'],
                'saga_id' => $sagaId,
                'type' => $entityType,
                'columns' => absint($atts['columns']),
                'show_pagination' => $atts['show_pagination'] === 'true',
                'class' => $atts['class'],
            ]);

        } catch (\Exception $e) {
            return $this->renderError(__('Failed to load entities.', 'saga-manager-display'), $e);
        }
    }

    private function sanitizeOrderBy(string $orderBy): string
    {
        $allowed = ['id', 'canonical_name', 'importance_score', 'created_at', 'updated_at'];
        return in_array($orderBy, $allowed, true) ? $orderBy : 'canonical_name';
    }
}
