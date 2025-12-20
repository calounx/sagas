<?php
/**
 * Recent Entities widget.
 *
 * @package SagaManagerDisplay
 */

declare(strict_types=1);

namespace SagaManagerDisplay\Widget;

use SagaManagerDisplay\API\SagaApiClient;
use SagaManagerDisplay\Template\TemplateEngine;
use WP_Widget;

/**
 * Widget displaying recent saga entities.
 */
class RecentEntitiesWidget extends WP_Widget
{
    private SagaApiClient $apiClient;
    private TemplateEngine $templateEngine;

    /**
     * Constructor.
     */
    public function __construct()
    {
        parent::__construct(
            'saga_recent_entities',
            __('Saga: Recent Entities', 'saga-manager-display'),
            [
                'description' => __('Displays recently created saga entities.', 'saga-manager-display'),
                'classname' => 'widget-saga-recent-entities',
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
        $count = (int) ($instance['count'] ?? 5);
        $entityType = $instance['entity_type'] ?? '';
        $showType = (bool) ($instance['show_type'] ?? true);
        $showImage = (bool) ($instance['show_image'] ?? true);

        echo $args['before_widget'];

        if (!empty($title)) {
            echo $args['before_title'] . esc_html($title) . $args['after_title'];
        }

        // Fetch recent entities
        $entities = $this->apiClient->getRecentEntities(
            $count,
            $entityType ?: null
        );

        if (is_wp_error($entities) || empty($entities['data'])) {
            echo '<p class="saga-widget-empty">';
            esc_html_e('No entities found.', 'saga-manager-display');
            echo '</p>';
        } else {
            echo $this->templateEngine->render('widget/recent-entities', [
                'entities' => $entities['data'],
                'options' => [
                    'show_type' => $showType,
                    'show_image' => $showImage,
                ],
            ]);
        }

        echo $args['after_widget'];
    }

    /**
     * Back-end widget form.
     *
     * @param array $instance Previously saved values from database.
     */
    public function form($instance): void
    {
        $title = $instance['title'] ?? __('Recent Entities', 'saga-manager-display');
        $count = (int) ($instance['count'] ?? 5);
        $entityType = $instance['entity_type'] ?? '';
        $showType = (bool) ($instance['show_type'] ?? true);
        $showImage = (bool) ($instance['show_image'] ?? true);

        // Get entity types for dropdown
        $entityTypes = $this->apiClient->getEntityTypes();
        $typeOptions = is_wp_error($entityTypes) ? [] : ($entityTypes['data'] ?? []);
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
            <label for="<?php echo esc_attr($this->get_field_id('count')); ?>">
                <?php esc_html_e('Number of entities:', 'saga-manager-display'); ?>
            </label>
            <input
                type="number"
                class="tiny-text"
                id="<?php echo esc_attr($this->get_field_id('count')); ?>"
                name="<?php echo esc_attr($this->get_field_name('count')); ?>"
                value="<?php echo esc_attr((string) $count); ?>"
                min="1"
                max="20"
            />
        </p>
        <p>
            <label for="<?php echo esc_attr($this->get_field_id('entity_type')); ?>">
                <?php esc_html_e('Entity Type:', 'saga-manager-display'); ?>
            </label>
            <select
                class="widefat"
                id="<?php echo esc_attr($this->get_field_id('entity_type')); ?>"
                name="<?php echo esc_attr($this->get_field_name('entity_type')); ?>"
            >
                <option value=""><?php esc_html_e('All Types', 'saga-manager-display'); ?></option>
                <?php foreach ($typeOptions as $type): ?>
                    <?php
                    $typeKey = is_array($type) ? ($type['key'] ?? '') : $type;
                    $typeLabel = is_array($type) ? ($type['label'] ?? $typeKey) : $type;
                    ?>
                    <option
                        value="<?php echo esc_attr($typeKey); ?>"
                        <?php selected($entityType, $typeKey); ?>
                    >
                        <?php echo esc_html($typeLabel); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </p>
        <p>
            <input
                type="checkbox"
                class="checkbox"
                id="<?php echo esc_attr($this->get_field_id('show_type')); ?>"
                name="<?php echo esc_attr($this->get_field_name('show_type')); ?>"
                <?php checked($showType); ?>
            />
            <label for="<?php echo esc_attr($this->get_field_id('show_type')); ?>">
                <?php esc_html_e('Show entity type', 'saga-manager-display'); ?>
            </label>
        </p>
        <p>
            <input
                type="checkbox"
                class="checkbox"
                id="<?php echo esc_attr($this->get_field_id('show_image')); ?>"
                name="<?php echo esc_attr($this->get_field_name('show_image')); ?>"
                <?php checked($showImage); ?>
            />
            <label for="<?php echo esc_attr($this->get_field_id('show_image')); ?>">
                <?php esc_html_e('Show entity image', 'saga-manager-display'); ?>
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
        $instance['count'] = min(20, max(1, (int) ($new_instance['count'] ?? 5)));
        $instance['entity_type'] = sanitize_key($new_instance['entity_type'] ?? '');
        $instance['show_type'] = isset($new_instance['show_type']);
        $instance['show_image'] = isset($new_instance['show_image']);

        return $instance;
    }
}
