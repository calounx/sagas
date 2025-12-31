<?php
/**
 * Semantic Search Widget
 *
 * WordPress widget for semantic search functionality
 * in sidebars and widget areas.
 *
 * @package SagaManager
 * @since 1.3.0
 */

declare(strict_types=1);

namespace SagaManager\Widgets;

class SearchWidget extends \WP_Widget {

    /**
     * Widget constructor
     */
    public function __construct() {
        parent::__construct(
            'saga_search_widget',
            __('Saga Search', 'saga-manager'),
            [
                'description' => __('Semantic search for saga entities', 'saga-manager'),
                'classname' => 'saga-search-widget',
            ]
        );
    }

    /**
     * Front-end display of widget
     *
     * @param array $args     Widget arguments
     * @param array $instance Saved values from database
     */
    public function widget($args, $instance): void {
        $title = apply_filters('widget_title', $instance['title'] ?? '');
        $placeholder = $instance['placeholder'] ?? __('Search saga entities...', 'saga-manager');
        $show_filters = $instance['show_filters'] ?? false;
        $show_voice = $instance['show_voice'] ?? true;
        $compact_mode = $instance['compact_mode'] ?? false;

        echo $args['before_widget'];

        if (!empty($title)) {
            echo $args['before_title'] . esc_html($title) . $args['after_title'];
        }

        $this->render_search_form([
            'placeholder' => $placeholder,
            'show_filters' => $show_filters,
            'show_voice' => $show_voice,
            'compact' => $compact_mode,
        ]);

        echo $args['after_widget'];
    }

    /**
     * Render search form
     */
    private function render_search_form(array $options): void {
        $form_class = 'saga-search-form saga-widget-search';
        if ($options['compact']) {
            $form_class .= ' saga-search-compact';
        }
        ?>
        <div class="saga-search-container saga-widget-container">
            <form class="<?php echo esc_attr($form_class); ?>" role="search" method="get" action="<?php echo esc_url(home_url('/')); ?>">
                <div class="saga-search-input-wrapper">
                    <i class="saga-search-icon" aria-hidden="true">
                        <svg width="20" height="20" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path d="M9 17A8 8 0 1 0 9 1a8 8 0 0 0 0 16zM19 19l-4.35-4.35" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                    </i>

                    <input type="search"
                           name="s"
                           class="saga-search-input"
                           placeholder="<?php echo esc_attr($options['placeholder']); ?>"
                           autocomplete="off"
                           aria-label="<?php esc_attr_e('Search', 'saga-manager'); ?>"
                           aria-expanded="false"
                           aria-autocomplete="list"
                           aria-controls="saga-search-results">

                    <?php if ($options['show_voice']): ?>
                        <button type="button"
                                class="saga-voice-search-btn"
                                aria-label="<?php esc_attr_e('Voice search', 'saga-manager'); ?>"
                                title="<?php esc_attr_e('Voice search', 'saga-manager'); ?>">
                            <svg width="20" height="20" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <path d="M10 1a3 3 0 0 0-3 3v6a3 3 0 0 0 6 0V4a3 3 0 0 0-3-3z" stroke="currentColor" stroke-width="2"/>
                                <path d="M4 10a6 6 0 0 0 12 0M10 16v3" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                            </svg>
                        </button>
                    <?php endif; ?>

                    <button type="button"
                            class="saga-search-clear"
                            aria-label="<?php esc_attr_e('Clear search', 'saga-manager'); ?>"
                            style="display: none;">
                        <svg width="20" height="20" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path d="M15 5L5 15M5 5l10 10" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                        </svg>
                    </button>
                </div>

                <?php if ($options['show_filters']): ?>
                    <div class="saga-search-filters">
                        <button type="button" class="saga-search-filters-toggle">
                            <span><?php esc_html_e('Filters', 'saga-manager'); ?></span>
                            <i class="saga-icon-chevron-down"></i>
                        </button>

                        <div class="saga-search-filters-content" style="display: none;">
                            <?php $this->render_filters(); ?>
                        </div>
                    </div>
                <?php endif; ?>

                <div class="saga-search-results" id="saga-search-results" role="region" aria-live="polite"></div>
            </form>

            <div class="saga-search-live-region" role="status" aria-live="polite" aria-atomic="true"></div>
        </div>
        <?php
    }

    /**
     * Render search filters
     */
    private function render_filters(): void {
        ?>
        <div class="saga-search-filters-content">
            <div class="saga-filter-group">
                <label class="saga-filter-label"><?php esc_html_e('Entity Type', 'saga-manager'); ?></label>
                <div class="saga-filter-types">
                    <?php
                    $types = [
                        'character' => __('Characters', 'saga-manager'),
                        'location' => __('Locations', 'saga-manager'),
                        'event' => __('Events', 'saga-manager'),
                        'faction' => __('Factions', 'saga-manager'),
                        'artifact' => __('Artifacts', 'saga-manager'),
                        'concept' => __('Concepts', 'saga-manager'),
                    ];

                    foreach ($types as $type => $label):
                    ?>
                        <div class="saga-filter-type-option">
                            <input type="checkbox"
                                   id="saga-filter-type-<?php echo esc_attr($type); ?>"
                                   class="saga-filter-type"
                                   name="types[]"
                                   value="<?php echo esc_attr($type); ?>">
                            <label for="saga-filter-type-<?php echo esc_attr($type); ?>">
                                <?php echo esc_html($label); ?>
                            </label>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="saga-filter-group">
                <label class="saga-filter-label"><?php esc_html_e('Importance', 'saga-manager'); ?></label>
                <div class="saga-filter-importance-range">
                    <div class="saga-filter-importance-inputs">
                        <input type="number"
                               class="saga-filter-importance-min"
                               name="importance_min"
                               min="0"
                               max="100"
                               placeholder="<?php esc_attr_e('Min', 'saga-manager'); ?>">
                        <span>-</span>
                        <input type="number"
                               class="saga-filter-importance-max"
                               name="importance_max"
                               min="0"
                               max="100"
                               placeholder="<?php esc_attr_e('Max', 'saga-manager'); ?>">
                    </div>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * Back-end widget form
     *
     * @param array $instance Previously saved values from database
     */
    public function form($instance): void {
        $title = $instance['title'] ?? __('Search Entities', 'saga-manager');
        $placeholder = $instance['placeholder'] ?? __('Search saga entities...', 'saga-manager');
        $show_filters = $instance['show_filters'] ?? false;
        $show_voice = $instance['show_voice'] ?? true;
        $compact_mode = $instance['compact_mode'] ?? false;
        ?>
        <p>
            <label for="<?php echo esc_attr($this->get_field_id('title')); ?>">
                <?php esc_html_e('Title:', 'saga-manager'); ?>
            </label>
            <input class="widefat"
                   id="<?php echo esc_attr($this->get_field_id('title')); ?>"
                   name="<?php echo esc_attr($this->get_field_name('title')); ?>"
                   type="text"
                   value="<?php echo esc_attr($title); ?>">
        </p>

        <p>
            <label for="<?php echo esc_attr($this->get_field_id('placeholder')); ?>">
                <?php esc_html_e('Placeholder Text:', 'saga-manager'); ?>
            </label>
            <input class="widefat"
                   id="<?php echo esc_attr($this->get_field_id('placeholder')); ?>"
                   name="<?php echo esc_attr($this->get_field_name('placeholder')); ?>"
                   type="text"
                   value="<?php echo esc_attr($placeholder); ?>">
        </p>

        <p>
            <input class="checkbox"
                   type="checkbox"
                   id="<?php echo esc_attr($this->get_field_id('show_filters')); ?>"
                   name="<?php echo esc_attr($this->get_field_name('show_filters')); ?>"
                   value="1"
                   <?php checked($show_filters, true); ?>>
            <label for="<?php echo esc_attr($this->get_field_id('show_filters')); ?>">
                <?php esc_html_e('Show filters', 'saga-manager'); ?>
            </label>
        </p>

        <p>
            <input class="checkbox"
                   type="checkbox"
                   id="<?php echo esc_attr($this->get_field_id('show_voice')); ?>"
                   name="<?php echo esc_attr($this->get_field_name('show_voice')); ?>"
                   value="1"
                   <?php checked($show_voice, true); ?>>
            <label for="<?php echo esc_attr($this->get_field_id('show_voice')); ?>">
                <?php esc_html_e('Show voice search button', 'saga-manager'); ?>
            </label>
        </p>

        <p>
            <input class="checkbox"
                   type="checkbox"
                   id="<?php echo esc_attr($this->get_field_id('compact_mode')); ?>"
                   name="<?php echo esc_attr($this->get_field_name('compact_mode')); ?>"
                   value="1"
                   <?php checked($compact_mode, true); ?>>
            <label for="<?php echo esc_attr($this->get_field_id('compact_mode')); ?>">
                <?php esc_html_e('Compact mode', 'saga-manager'); ?>
            </label>
        </p>
        <?php
    }

    /**
     * Sanitize widget form values as they are saved
     *
     * @param array $new_instance Values just sent to be saved
     * @param array $old_instance Previously saved values from database
     * @return array Updated safe values to be saved
     */
    public function update($new_instance, $old_instance): array {
        $instance = [];

        $instance['title'] = !empty($new_instance['title'])
            ? sanitize_text_field($new_instance['title'])
            : '';

        $instance['placeholder'] = !empty($new_instance['placeholder'])
            ? sanitize_text_field($new_instance['placeholder'])
            : __('Search saga entities...', 'saga-manager');

        $instance['show_filters'] = !empty($new_instance['show_filters']);
        $instance['show_voice'] = !empty($new_instance['show_voice']);
        $instance['compact_mode'] = !empty($new_instance['compact_mode']);

        return $instance;
    }
}

/**
 * Register widget
 */
function register_search_widget(): void {
    register_widget(SearchWidget::class);
}

add_action('widgets_init', __NAMESPACE__ . '\\register_search_widget');
