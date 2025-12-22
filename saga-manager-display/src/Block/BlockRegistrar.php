<?php
/**
 * Gutenberg block registrar.
 *
 * @package SagaManagerDisplay
 */

declare(strict_types=1);

namespace SagaManagerDisplay\Block;

use SagaManagerDisplay\API\SagaApiClient;
use SagaManagerDisplay\Template\TemplateEngine;

/**
 * Registers all Gutenberg blocks for the plugin.
 */
class BlockRegistrar
{
    private SagaApiClient $apiClient;
    private TemplateEngine $templateEngine;
    private string $blocksDir;

    /**
     * Constructor.
     *
     * @param SagaApiClient $apiClient API client instance.
     * @param TemplateEngine $templateEngine Template engine instance.
     */
    public function __construct(SagaApiClient $apiClient, TemplateEngine $templateEngine)
    {
        $this->apiClient = $apiClient;
        $this->templateEngine = $templateEngine;
        // Use build directory for compiled blocks (production)
        // Falls back to source directory if build doesn't exist (development with hot reload)
        $buildDir = SAGA_DISPLAY_PLUGIN_DIR . 'build/blocks/';
        $this->blocksDir = is_dir($buildDir) ? $buildDir : SAGA_DISPLAY_PLUGIN_DIR . 'blocks/';
    }

    /**
     * Register all blocks.
     */
    public function register(): void
    {
        // Register block category
        add_filter('block_categories_all', [$this, 'registerCategory'], 10, 2);

        // Register individual blocks
        $this->registerEntityDisplayBlock();
        $this->registerTimelineBlock();
        $this->registerSearchBlock();

        // Enqueue editor assets
        add_action('enqueue_block_editor_assets', [$this, 'enqueueEditorAssets']);
    }

    /**
     * Register the Saga Manager block category.
     *
     * @param array $categories Existing categories.
     * @param \WP_Block_Editor_Context $context Editor context.
     * @return array Updated categories.
     */
    public function registerCategory(array $categories, $context): array
    {
        return array_merge([
            [
                'slug' => 'saga-manager',
                'title' => __('Saga Manager', 'saga-manager-display'),
                'icon' => 'book-alt',
            ],
        ], $categories);
    }

    /**
     * Register the Entity Display block.
     */
    private function registerEntityDisplayBlock(): void
    {
        register_block_type($this->blocksDir . 'entity-display', [
            'render_callback' => [$this, 'renderEntityDisplay'],
        ]);
    }

    /**
     * Register the Timeline block.
     */
    private function registerTimelineBlock(): void
    {
        register_block_type($this->blocksDir . 'timeline', [
            'render_callback' => [$this, 'renderTimeline'],
        ]);
    }

    /**
     * Register the Search block.
     */
    private function registerSearchBlock(): void
    {
        register_block_type($this->blocksDir . 'search', [
            'render_callback' => [$this, 'renderSearch'],
        ]);
    }

    /**
     * Render the Entity Display block.
     *
     * @param array $attributes Block attributes.
     * @return string Rendered output.
     */
    public function renderEntityDisplay(array $attributes): string
    {
        $entityId = (int) ($attributes['entityId'] ?? 0);

        if ($entityId <= 0) {
            return $this->renderPlaceholder(
                __('Select an entity to display', 'saga-manager-display'),
                'entity-display'
            );
        }

        $entity = $this->apiClient->getEntity($entityId);

        if (is_wp_error($entity)) {
            return $this->renderError($entity->get_error_message());
        }

        $layout = $attributes['layout'] ?? 'card';
        $showImage = $attributes['showImage'] ?? true;
        $showType = $attributes['showType'] ?? true;
        $showAttributes = $attributes['showAttributes'] ?? true;

        $templateData = [
            'entity' => $entity,
            'attributes' => $entity['attributes'] ?? [],
            'relationships' => [],
            'options' => [
                'layout' => $layout,
                'show_image' => $showImage,
                'show_type' => $showType,
                'show_importance' => false,
                'show_relationships' => false,
                'show_attributes' => $showAttributes,
                'link' => true,
            ],
        ];

        $template = match ($layout) {
            'full' => 'entity/full',
            'compact' => 'entity/compact',
            'inline' => 'entity/inline',
            default => 'entity/card',
        };

        $output = $this->templateEngine->render($template, $templateData);

        $classes = [
            'wp-block-saga-manager-entity-display',
            'saga-entity',
            'saga-entity--' . $layout,
        ];

        if (!empty($attributes['className'])) {
            $classes[] = $attributes['className'];
        }

        if (!empty($attributes['align'])) {
            $classes[] = 'align' . $attributes['align'];
        }

        return sprintf(
            '<div class="%s">%s</div>',
            esc_attr(implode(' ', $classes)),
            $output
        );
    }

    /**
     * Render the Timeline block.
     *
     * @param array $attributes Block attributes.
     * @return string Rendered output.
     */
    public function renderTimeline(array $attributes): string
    {
        $sagaSlug = $attributes['sagaSlug'] ?? '';

        if (empty($sagaSlug)) {
            return $this->renderPlaceholder(
                __('Select a saga to display its timeline', 'saga-manager-display'),
                'timeline'
            );
        }

        $apiArgs = [
            'limit' => $attributes['limit'] ?? 20,
            'order' => $attributes['order'] ?? 'asc',
        ];

        $timeline = $this->apiClient->getTimeline($sagaSlug, $apiArgs);

        if (is_wp_error($timeline)) {
            return $this->renderError($timeline->get_error_message());
        }

        $events = $timeline['data'] ?? [];

        if (empty($events)) {
            return $this->renderMessage(
                __('No timeline events found for this saga.', 'saga-manager-display'),
                'warning'
            );
        }

        $layout = $attributes['layout'] ?? 'vertical';

        $templateData = [
            'saga_slug' => $sagaSlug,
            'events' => $events,
            'meta' => $timeline['meta'] ?? [],
            'is_grouped' => false,
            'group_by' => '',
            'options' => [
                'layout' => $layout,
                'show_participants' => $attributes['showParticipants'] ?? true,
                'show_locations' => $attributes['showLocations'] ?? true,
                'show_descriptions' => $attributes['showDescriptions'] ?? true,
                'interactive' => $attributes['interactive'] ?? true,
                'order' => $attributes['order'] ?? 'asc',
            ],
        ];

        $template = match ($layout) {
            'horizontal' => 'timeline/horizontal',
            'compact' => 'timeline/compact',
            default => 'timeline/vertical',
        };

        $output = $this->templateEngine->render($template, $templateData);

        $classes = [
            'wp-block-saga-manager-timeline',
            'saga-timeline',
            'saga-timeline--' . $layout,
        ];

        if (!empty($attributes['className'])) {
            $classes[] = $attributes['className'];
        }

        return sprintf(
            '<div class="%s">%s</div>',
            esc_attr(implode(' ', $classes)),
            $output
        );
    }

    /**
     * Render the Search block.
     *
     * @param array $attributes Block attributes.
     * @return string Rendered output.
     */
    public function renderSearch(array $attributes): string
    {
        // Get available sagas
        $sagas = [];
        $showSagaFilter = $attributes['showSagaFilter'] ?? true;
        $fixedSaga = $attributes['sagaSlug'] ?? '';

        if ($showSagaFilter && empty($fixedSaga)) {
            $sagaData = $this->apiClient->getSagas();
            if (!is_wp_error($sagaData)) {
                $sagas = $sagaData['data'] ?? [];
            }
        }

        // Get entity types
        $entityTypes = [];
        $showTypeFilter = $attributes['showTypeFilter'] ?? true;

        if ($showTypeFilter) {
            $typeData = $this->apiClient->getEntityTypes();
            if (!is_wp_error($typeData)) {
                $entityTypes = $typeData['data'] ?? [];
            }
        }

        $layout = $attributes['resultsLayout'] ?? 'grid';

        $templateData = [
            'sagas' => $sagas,
            'entity_types' => $entityTypes,
            'initial_results' => [],
            'placeholder' => $attributes['placeholder'] ?? __('Search entities...', 'saga-manager-display'),
            'fixed_saga' => $fixedSaga,
            'fixed_types' => [],
            'options' => [
                'show_filters' => $attributes['showFilters'] ?? true,
                'show_type_filter' => $showTypeFilter,
                'show_saga_filter' => $showSagaFilter,
                'results_layout' => $layout,
                'results_per_page' => $attributes['resultsPerPage'] ?? 12,
                'show_pagination' => $attributes['showPagination'] ?? true,
                'semantic' => $attributes['semantic'] ?? false,
                'live_search' => $attributes['liveSearch'] ?? true,
                'min_chars' => $attributes['minChars'] ?? 3,
                'debounce' => $attributes['debounce'] ?? 300,
            ],
        ];

        $output = $this->templateEngine->render('search/form', $templateData);

        $classes = [
            'wp-block-saga-manager-search',
            'saga-search',
            'saga-search--' . $layout,
        ];

        if (!empty($attributes['className'])) {
            $classes[] = $attributes['className'];
        }

        $dataAttrs = sprintf(
            'data-saga="%s" data-layout="%s" data-per-page="%d" data-live-search="%s"',
            esc_attr($fixedSaga),
            esc_attr($layout),
            $attributes['resultsPerPage'] ?? 12,
            ($attributes['liveSearch'] ?? true) ? 'true' : 'false'
        );

        return sprintf(
            '<div class="%s" %s>%s</div>',
            esc_attr(implode(' ', $classes)),
            $dataAttrs,
            $output
        );
    }

    /**
     * Enqueue block editor assets.
     */
    public function enqueueEditorAssets(): void
    {
        // Get sagas for entity selector
        $sagas = [];
        $sagaData = $this->apiClient->getSagas();
        if (!is_wp_error($sagaData)) {
            $sagas = $sagaData['data'] ?? [];
        }

        // Get entity types
        $entityTypes = [];
        $typeData = $this->apiClient->getEntityTypes();
        if (!is_wp_error($typeData)) {
            $entityTypes = $typeData['data'] ?? [];
        }

        wp_localize_script('saga-manager-entity-display-editor-script', 'sagaManagerBlocks', [
            'apiUrl' => rest_url(SAGA_DISPLAY_API_NAMESPACE),
            'nonce' => wp_create_nonce('wp_rest'),
            'sagas' => $sagas,
            'entityTypes' => $entityTypes,
            'i18n' => [
                'selectEntity' => __('Select Entity', 'saga-manager-display'),
                'selectSaga' => __('Select Saga', 'saga-manager-display'),
                'searchEntities' => __('Search entities...', 'saga-manager-display'),
                'noResults' => __('No results found', 'saga-manager-display'),
                'loading' => __('Loading...', 'saga-manager-display'),
            ],
        ]);
    }

    /**
     * Render a placeholder for empty blocks.
     *
     * @param string $message Placeholder message.
     * @param string $blockType Block type identifier.
     * @return string Rendered placeholder.
     */
    private function renderPlaceholder(string $message, string $blockType): string
    {
        return sprintf(
            '<div class="saga-block-placeholder saga-block-placeholder--%s">
                <div class="saga-block-placeholder__icon">
                    <span class="dashicons dashicons-book-alt"></span>
                </div>
                <p class="saga-block-placeholder__message">%s</p>
            </div>',
            esc_attr($blockType),
            esc_html($message)
        );
    }

    /**
     * Render an error message.
     *
     * @param string $message Error message.
     * @return string Rendered error.
     */
    private function renderError(string $message): string
    {
        return $this->templateEngine->error($message, 'error');
    }

    /**
     * Render a message.
     *
     * @param string $message Message text.
     * @param string $type Message type.
     * @return string Rendered message.
     */
    private function renderMessage(string $message, string $type = 'info'): string
    {
        return $this->templateEngine->error($message, $type);
    }
}
