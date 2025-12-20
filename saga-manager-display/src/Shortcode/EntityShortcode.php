<?php
/**
 * Entity display shortcode handler.
 *
 * @package SagaManagerDisplay
 */

declare(strict_types=1);

namespace SagaManagerDisplay\Shortcode;

use WP_Error;

/**
 * Shortcode: [saga_entity id="123"]
 *
 * Displays a single entity with customizable layout.
 */
class EntityShortcode extends AbstractShortcode
{
    protected string $shortcodeTag = 'saga_entity';

    /**
     * Get default attributes.
     *
     * @return array Default attributes.
     */
    protected function getDefaultAttributes(): array
    {
        return [
            'id' => '',
            'slug' => '',
            'layout' => 'card', // card, full, compact, inline
            'show_image' => 'true',
            'show_type' => 'true',
            'show_importance' => 'false',
            'show_relationships' => 'false',
            'relationship_limit' => '5',
            'show_attributes' => 'true',
            'attributes' => '', // comma-separated list of attribute keys
            'link' => 'true', // link to entity page
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
        if (empty($atts['id']) && empty($atts['slug'])) {
            return new WP_Error(
                'missing_identifier',
                __('Entity ID or slug is required.', 'saga-manager-display')
            );
        }

        $validLayouts = ['card', 'full', 'compact', 'inline'];
        if (!in_array($atts['layout'], $validLayouts, true)) {
            return new WP_Error(
                'invalid_layout',
                sprintf(
                    __('Invalid layout. Valid options: %s', 'saga-manager-display'),
                    implode(', ', $validLayouts)
                )
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
        // Fetch entity data
        $entityId = $this->parseInt($atts['id']);

        if ($entityId > 0) {
            $entity = $this->apiClient->getEntity($entityId);
        } else {
            // Search by slug
            $searchResult = $this->apiClient->searchEntities('', ['slug' => $atts['slug']]);
            if (is_wp_error($searchResult) || empty($searchResult['data'])) {
                return $this->renderError(__('Entity not found.', 'saga-manager-display'));
            }
            $entity = $searchResult['data'][0];
        }

        if (is_wp_error($entity)) {
            return $this->renderError($entity->get_error_message());
        }

        // Fetch relationships if needed
        $relationships = [];
        if ($this->parseBool($atts['show_relationships'])) {
            $relationshipData = $this->apiClient->getRelationships(
                (int) $entity['id'],
                ['limit' => $this->parseInt($atts['relationship_limit'], 5)]
            );

            if (!is_wp_error($relationshipData)) {
                $relationships = $relationshipData['data'] ?? [];
            }
        }

        // Filter attributes if specified
        $visibleAttributes = [];
        if ($this->parseBool($atts['show_attributes']) && !empty($entity['attributes'])) {
            $allowedKeys = $this->parseList($atts['attributes']);

            if (empty($allowedKeys)) {
                $visibleAttributes = $entity['attributes'];
            } else {
                foreach ($entity['attributes'] as $key => $value) {
                    if (in_array($key, $allowedKeys, true)) {
                        $visibleAttributes[$key] = $value;
                    }
                }
            }
        }

        // Prepare template data
        $templateData = [
            'entity' => $entity,
            'relationships' => $relationships,
            'attributes' => $visibleAttributes,
            'options' => [
                'layout' => $atts['layout'],
                'show_image' => $this->parseBool($atts['show_image']),
                'show_type' => $this->parseBool($atts['show_type']),
                'show_importance' => $this->parseBool($atts['show_importance']),
                'show_relationships' => $this->parseBool($atts['show_relationships']),
                'show_attributes' => $this->parseBool($atts['show_attributes']),
                'link' => $this->parseBool($atts['link']),
            ],
        ];

        // Allow filtering template data
        $templateData = apply_filters('saga_display_entity_data', $templateData, $atts);

        // Select template based on layout
        $template = match ($atts['layout']) {
            'full' => 'entity/full',
            'compact' => 'entity/compact',
            'inline' => 'entity/inline',
            default => 'entity/card',
        };

        $output = $this->templateEngine->render($template, $templateData);

        return $this->wrapOutput($output, $atts, 'saga-entity saga-entity--' . $atts['layout']);
    }
}
