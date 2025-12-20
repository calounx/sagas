<?php
/**
 * Search interface shortcode handler.
 *
 * @package SagaManagerDisplay
 */

declare(strict_types=1);

namespace SagaManagerDisplay\Shortcode;

use WP_Error;

/**
 * Shortcode: [saga_search]
 *
 * Displays a search interface for entities.
 */
class SearchShortcode extends AbstractShortcode
{
    protected string $shortcodeTag = 'saga_search';

    /**
     * Get default attributes.
     *
     * @return array Default attributes.
     */
    protected function getDefaultAttributes(): array
    {
        return [
            'saga' => '', // limit to specific saga
            'types' => '', // comma-separated entity types
            'placeholder' => '', // search input placeholder
            'show_filters' => 'true',
            'show_type_filter' => 'true',
            'show_saga_filter' => 'true',
            'results_layout' => 'grid', // grid, list
            'results_per_page' => '12',
            'show_pagination' => 'true',
            'semantic' => 'false', // enable semantic search
            'live_search' => 'true', // enable live search
            'min_chars' => '3', // minimum characters before search
            'debounce' => '300', // debounce delay in ms
            'initial_results' => 'false', // show results on load
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
        $validLayouts = ['grid', 'list'];
        if (!in_array($atts['results_layout'], $validLayouts, true)) {
            return new WP_Error(
                'invalid_layout',
                sprintf(
                    __('Invalid results layout. Valid options: %s', 'saga-manager-display'),
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
        // Get available sagas for filter
        $sagas = [];
        if ($this->parseBool($atts['show_saga_filter']) && empty($atts['saga'])) {
            $sagaData = $this->apiClient->getSagas();
            if (!is_wp_error($sagaData)) {
                $sagas = $sagaData['data'] ?? [];
            }
        }

        // Get entity types for filter
        $entityTypes = [];
        if ($this->parseBool($atts['show_type_filter'])) {
            $typeData = $this->apiClient->getEntityTypes();
            if (!is_wp_error($typeData)) {
                $entityTypes = $typeData['data'] ?? [];
            }
        }

        // Filter types if specified
        if (!empty($atts['types'])) {
            $allowedTypes = $this->parseList($atts['types']);
            $entityTypes = array_filter($entityTypes, function ($type) use ($allowedTypes) {
                return in_array($type['key'] ?? $type, $allowedTypes, true);
            });
        }

        // Get initial results if requested
        $initialResults = [];
        if ($this->parseBool($atts['initial_results'])) {
            $filters = [];
            if (!empty($atts['saga'])) {
                $filters['saga'] = $atts['saga'];
            }
            if (!empty($atts['types'])) {
                $filters['types'] = $atts['types'];
            }
            $filters['limit'] = $this->parseInt($atts['results_per_page'], 12);

            $results = $this->apiClient->getRecentEntities(
                $filters['limit'],
                !empty($atts['types']) ? $this->parseList($atts['types'])[0] ?? null : null
            );

            if (!is_wp_error($results)) {
                $initialResults = $results['data'] ?? [];
            }
        }

        // Build placeholder text
        $placeholder = $atts['placeholder'];
        if (empty($placeholder)) {
            $placeholder = __('Search entities...', 'saga-manager-display');
        }

        // Prepare template data
        $templateData = [
            'sagas' => $sagas,
            'entity_types' => $entityTypes,
            'initial_results' => $initialResults,
            'placeholder' => $placeholder,
            'fixed_saga' => $atts['saga'],
            'fixed_types' => $this->parseList($atts['types']),
            'options' => [
                'show_filters' => $this->parseBool($atts['show_filters']),
                'show_type_filter' => $this->parseBool($atts['show_type_filter']),
                'show_saga_filter' => $this->parseBool($atts['show_saga_filter']),
                'results_layout' => $atts['results_layout'],
                'results_per_page' => $this->parseInt($atts['results_per_page'], 12),
                'show_pagination' => $this->parseBool($atts['show_pagination']),
                'semantic' => $this->parseBool($atts['semantic']),
                'live_search' => $this->parseBool($atts['live_search']),
                'min_chars' => $this->parseInt($atts['min_chars'], 3),
                'debounce' => $this->parseInt($atts['debounce'], 300),
            ],
        ];

        // Allow filtering template data
        $templateData = apply_filters('saga_display_search_data', $templateData, $atts);

        $output = $this->templateEngine->render('search/form', $templateData);

        // Add data attributes for JavaScript
        $dataAttrs = $this->dataAttributes([
            'saga' => $atts['saga'] ?: null,
            'types' => $atts['types'] ?: null,
            'layout' => $atts['results_layout'],
            'per_page' => $atts['results_per_page'],
            'semantic' => $this->parseBool($atts['semantic']) ? 'true' : 'false',
            'live_search' => $this->parseBool($atts['live_search']) ? 'true' : 'false',
            'min_chars' => $atts['min_chars'],
            'debounce' => $atts['debounce'],
        ]);

        $classes = [
            'saga-search',
            'saga-search--' . $atts['results_layout'],
            $this->parseBool($atts['live_search']) ? 'saga-search--live' : '',
        ];

        return sprintf(
            '<div class="%s" %s>%s</div>',
            esc_attr(implode(' ', array_filter($classes))),
            $dataAttrs,
            $output
        );
    }
}
