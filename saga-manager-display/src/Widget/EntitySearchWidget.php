<?php
/**
 * Entity Search widget.
 *
 * @package SagaManagerDisplay
 */

declare(strict_types=1);

namespace SagaManagerDisplay\Widget;

use SagaManagerDisplay\API\SagaApiClient;
use SagaManagerDisplay\Template\TemplateEngine;
use WP_Widget;

/**
 * Widget providing entity search functionality.
 */
class EntitySearchWidget extends WP_Widget
{
    private SagaApiClient $apiClient;
    private TemplateEngine $templateEngine;

    /**
     * Constructor.
     */
    public function __construct()
    {
        parent::__construct(
            'saga_entity_search',
            __('Saga: Entity Search', 'saga-manager-display'),
            [
                'description' => __('A search widget for saga entities.', 'saga-manager-display'),
                'classname' => 'widget-saga-entity-search',
            ]
        );

        $this->apiClient = new SagaApiClient();
        $this->templateEngine = new TemplateEngine();
    }

    /**
     * Front-end display of widget.
     *
     * @param array $args Widget arguments.
     * @param array $instance Saved values from database.
     */
    public function widget($args, $instance): void
    {
        $title = apply_filters('widget_title', $instance['title'] ?? '');
        $placeholder = $instance['placeholder'] ?? __('Search entities...', 'saga-manager-display');
        $showTypeFilter = (bool) ($instance['show_type_filter'] ?? false);
        $buttonText = $instance['button_text'] ?? __('Search', 'saga-manager-display');
        $resultsPage = $instance['results_page'] ?? '';

        echo $args['before_widget'];

        if (!empty($title)) {
            echo $args['before_title'] . esc_html($title) . $args['after_title'];
        }

        // Get entity types if needed
        $entityTypes = [];
        if ($showTypeFilter) {
            $typeData = $this->apiClient->getEntityTypes();
            if (!is_wp_error($typeData)) {
                $entityTypes = $typeData['data'] ?? [];
            }
        }

        echo $this->templateEngine->render('widget/entity-search', [
            'placeholder' => $placeholder,
            'button_text' => $buttonText,
            'results_page' => $resultsPage,
            'entity_types' => $entityTypes,
            'options' => [
                'show_type_filter' => $showTypeFilter,
            ],
        ]);

        echo $args['after_widget'];
    }

    /**
     * Back-end widget form.
     *
     * @param array $instance Previously saved values from database.
     */
    public function form($instance): void
    {
        $title = $instance['title'] ?? __('Search Entities', 'saga-manager-display');
        $placeholder = $instance['placeholder'] ?? __('Search entities...', 'saga-manager-display');
        $showTypeFilter = (bool) ($instance['show_type_filter'] ?? false);
        $buttonText = $instance['button_text'] ?? __('Search', 'saga-manager-display');
        $resultsPage = $instance['results_page'] ?? '';

        // Get pages for results dropdown
        $pages = get_pages(['post_status' => 'publish']);
        ?>
        <p>
            <label for="<?php echo esc_attr($this->get_field_id('title')); ?>">
                <?php esc_html_e('Title:', 'saga-manager-display'); ?>
            </label>
            <input
                type="text"
                class="widefat"
                id="<?php echo esc_attr($this->get_field_id('title')); ?>"
                name="<?php echo esc_attr($this->get_field_name('title')); ?>"
                value="<?php echo esc_attr($title); ?>"
            />
        </p>
        <p>
            <label for="<?php echo esc_attr($this->get_field_id('placeholder')); ?>">
                <?php esc_html_e('Placeholder:', 'saga-manager-display'); ?>
            </label>
            <input
                type="text"
                class="widefat"
                id="<?php echo esc_attr($this->get_field_id('placeholder')); ?>"
                name="<?php echo esc_attr($this->get_field_name('placeholder')); ?>"
                value="<?php echo esc_attr($placeholder); ?>"
            />
        </p>
        <p>
            <label for="<?php echo esc_attr($this->get_field_id('button_text')); ?>">
                <?php esc_html_e('Button Text:', 'saga-manager-display'); ?>
            </label>
            <input
                type="text"
                class="widefat"
                id="<?php echo esc_attr($this->get_field_id('button_text')); ?>"
                name="<?php echo esc_attr($this->get_field_name('button_text')); ?>"
                value="<?php echo esc_attr($buttonText); ?>"
            />
        </p>
        <p>
            <label for="<?php echo esc_attr($this->get_field_id('results_page')); ?>">
                <?php esc_html_e('Results Page:', 'saga-manager-display'); ?>
            </label>
            <select
                class="widefat"
                id="<?php echo esc_attr($this->get_field_id('results_page')); ?>"
                name="<?php echo esc_attr($this->get_field_name('results_page')); ?>"
            >
                <option value=""><?php esc_html_e('Same page (AJAX)', 'saga-manager-display'); ?></option>
                <?php foreach ($pages as $page): ?>
                    <option
                        value="<?php echo esc_attr(get_permalink($page->ID)); ?>"
                        <?php selected($resultsPage, get_permalink($page->ID)); ?>
                    >
                        <?php echo esc_html($page->post_title); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </p>
        <p>
            <input
                type="checkbox"
                class="checkbox"
                id="<?php echo esc_attr($this->get_field_id('show_type_filter')); ?>"
                name="<?php echo esc_attr($this->get_field_name('show_type_filter')); ?>"
                <?php checked($showTypeFilter); ?>
            />
            <label for="<?php echo esc_attr($this->get_field_id('show_type_filter')); ?>">
                <?php esc_html_e('Show entity type filter', 'saga-manager-display'); ?>
            </label>
        </p>
        <?php
    }

    /**
     * Sanitize widget form values as they are saved.
     *
     * @param array $new_instance Values just sent to be saved.
     * @param array $old_instance Previously saved values from database.
     * @return array Updated safe values.
     */
    public function update($new_instance, $old_instance): array
    {
        $instance = [];
        $instance['title'] = sanitize_text_field($new_instance['title'] ?? '');
        $instance['placeholder'] = sanitize_text_field($new_instance['placeholder'] ?? '');
        $instance['button_text'] = sanitize_text_field($new_instance['button_text'] ?? '');
        $instance['results_page'] = esc_url_raw($new_instance['results_page'] ?? '');
        $instance['show_type_filter'] = isset($new_instance['show_type_filter']);

        return $instance;
    }
}
