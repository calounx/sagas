<?php

declare(strict_types=1);

namespace SagaManagerDisplay\Shortcode;

use SagaManagerDisplay\ApiClient\SagaApiClient;
use SagaManagerDisplay\Template\TemplateLoader;

/**
 * [saga_entity] shortcode - Display a single entity
 *
 * Usage:
 *   [saga_entity id="123"]
 *   [saga_entity id="123" include="attributes,relationships"]
 *   [saga_entity id="123" template="card"]
 */
final class EntityShortcode extends AbstractShortcode
{
    protected string $tag = 'saga_entity';

    protected array $defaults = [
        'id' => 0,
        'include' => '',
        'template' => 'single',
        'class' => '',
    ];

    public function render(array $atts, ?string $content = null, string $tag = ''): string
    {
        $atts = $this->parseAttributes($atts);

        $entityId = absint($atts['id']);
        if ($entityId === 0) {
            return $this->renderError(__('Entity ID is required.', 'saga-manager-display'));
        }

        // Parse include parameter
        $include = array_filter(array_map('trim', explode(',', $atts['include'])));

        try {
            $entity = $this->apiClient->entities->get($entityId, $include);

            if ($entity === null) {
                return $this->renderError(__('Entity not found.', 'saga-manager-display'));
            }

            return $this->renderTemplate('entity-' . $atts['template'], [
                'entity' => $entity,
                'include' => $include,
                'class' => $atts['class'],
            ]);

        } catch (\Exception $e) {
            return $this->renderError(__('Failed to load entity.', 'saga-manager-display'), $e);
        }
    }
}
