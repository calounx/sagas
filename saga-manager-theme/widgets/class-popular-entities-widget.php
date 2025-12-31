<?php
declare(strict_types=1);

/**
 * Popular Entities Widget
 *
 * @package Saga_Manager_Theme
 */

class Saga_Popular_Entities_Widget extends WP_Widget {

    /**
     * Constructor
     */
    public function __construct() {
        parent::__construct(
            'saga_popular_entities',
            __('Popular Saga Entities', 'saga-manager'),
            [
                'description' => __('Display trending or popular saga entities', 'saga-manager'),
                'classname' => 'saga-popular-entities-widget',
            ]
        );
    }

    /**
     * Widget output
     *
     * @param array $args Widget arguments
     * @param array $instance Widget instance settings
     */
    public function widget($args, $instance): void {
        $title = !empty($instance['title']) ? $instance['title'] : __('Trending Entities', 'saga-manager');
        $title = apply_filters('widget_title', $title, $instance, $this->id_base);

        $count = !empty($instance['count']) ? absint($instance['count']) : 5;
        $display_type = !empty($instance['display_type']) ? $instance['display_type'] : 'trending';
        $show_views = !empty($instance['show_views']);
        $show_badges = !empty($instance['show_badges']);

        echo $args['before_widget'];

        if ($title) {
            echo $args['before_title'] . esc_html($title) . $args['after_title'];
        }

        $entities = $this->get_entities($display_type, $count);

        if (empty($entities)) {
            echo '<p class="no-entities">' . esc_html__('No entities to display yet.', 'saga-manager') . '</p>';
            echo $args['after_widget'];
            return;
        }

        echo '<ul class="popular-entities-list popular-entities-list--' . esc_attr($display_type) . '">';

        foreach ($entities as $entity) {
            $this->render_entity_item($entity, $show_views, $show_badges);
        }

        echo '</ul>';

        echo $args['after_widget'];
    }

    /**
     * Render individual entity item
     *
     * @param array $entity Entity data
     * @param bool  $show_views Show view count
     * @param bool  $show_badges Show popularity badges
     */
    private function render_entity_item(array $entity, bool $show_views, bool $show_badges): void {
        $post = get_post($entity['entity_id']);

        if (!$post) {
            return;
        }

        $permalink = get_permalink($post);
        $title = get_the_title($post);
        $entity_type = get_post_meta($post->ID, '_saga_entity_type', true);

        echo '<li class="popular-entity-item">';
        echo '<a href="' . esc_url($permalink) . '" class="entity-link">';

        // Entity type icon (if available)
        if ($entity_type) {
            echo '<span class="entity-type entity-type--' . esc_attr($entity_type) . '">';
            echo $this->get_entity_type_icon($entity_type);
            echo '</span>';
        }

        echo '<span class="entity-title">' . esc_html($title) . '</span>';
        echo '</a>';

        // Stats container
        echo '<div class="entity-stats">';

        if ($show_badges) {
            $badge_type = Saga_Popularity::get_badge_type($entity['entity_id']);
            if ($badge_type) {
                echo '<span class="mini-badge mini-badge--' . esc_attr($badge_type) . '">';
                echo $this->get_badge_icon($badge_type);
                echo '</span>';
            }
        }

        if ($show_views) {
            $formatted_views = Saga_Popularity::get_formatted_views($entity['entity_id']);
            echo '<span class="view-count">';
            echo '<svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>';
            echo '<span>' . esc_html($formatted_views) . '</span>';
            echo '</span>';
        }

        echo '</div>'; // .entity-stats
        echo '</li>';
    }

    /**
     * Get entities based on display type
     *
     * @param string $display_type Type of entities to display
     * @param int    $count Number of entities
     * @return array Entities
     */
    private function get_entities(string $display_type, int $count): array {
        switch ($display_type) {
            case 'trending':
                return Saga_Popularity::get_trending($count, 'weekly');

            case 'popular':
                return Saga_Popularity::get_popular($count);

            case 'recent':
                return $this->get_recently_viewed($count);

            default:
                return Saga_Popularity::get_trending($count, 'weekly');
        }
    }

    /**
     * Get recently viewed entities
     *
     * @param int $count Number of entities
     * @return array Entities
     */
    private function get_recently_viewed(int $count): array {
        global $wpdb;

        $stats_table = $wpdb->prefix . 'saga_entity_stats';

        $results = $wpdb->get_results($wpdb->prepare(
            "SELECT entity_id, popularity_score as trend_score
            FROM {$stats_table}
            WHERE last_viewed IS NOT NULL
            ORDER BY last_viewed DESC
            LIMIT %d",
            $count
        ), ARRAY_A);

        return $results ?: [];
    }

    /**
     * Get entity type icon
     *
     * @param string $type Entity type
     * @return string SVG icon
     */
    private function get_entity_type_icon(string $type): string {
        $icons = [
            'character' => '<svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>',
            'location' => '<svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>',
            'event' => '<svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>',
            'faction' => '<svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>',
            'artifact' => '<svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><circle cx="12" cy="12" r="6"/><circle cx="12" cy="12" r="2"/></svg>',
            'concept' => '<svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>',
        ];

        return $icons[$type] ?? '';
    }

    /**
     * Get badge icon
     *
     * @param string $badge_type Badge type
     * @return string SVG icon
     */
    private function get_badge_icon(string $badge_type): string {
        $icons = [
            'trending' => '<svg xmlns="http://www.w3.org/2000/svg" width="10" height="10" viewBox="0 0 24 24" fill="currentColor"><path d="M8.5 14.5A2.5 2.5 0 0 0 11 12c0-1.38-.5-2-1-3-1.072-2.143-.224-4.054 2-6 .5 2.5 2 4.9 4 6.5 2 1.6 3 3.5 3 5.5a7 7 0 1 1-14 0c0-1.153.433-2.294 1-3a2.5 2.5 0 0 0 2.5 2.5z"/></svg>',
            'popular' => '<svg xmlns="http://www.w3.org/2000/svg" width="10" height="10" viewBox="0 0 24 24" fill="currentColor"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>',
            'rising' => '<svg xmlns="http://www.w3.org/2000/svg" width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="23 6 13.5 15.5 8.5 10.5 1 18"/><polyline points="17 6 23 6 23 12"/></svg>',
        ];

        return $icons[$badge_type] ?? '';
    }

    /**
     * Widget settings form
     *
     * @param array $instance Current instance settings
     */
    public function form($instance): void {
        $title = !empty($instance['title']) ? $instance['title'] : __('Trending Entities', 'saga-manager');
        $count = !empty($instance['count']) ? absint($instance['count']) : 5;
        $display_type = !empty($instance['display_type']) ? $instance['display_type'] : 'trending';
        $show_views = !empty($instance['show_views']);
        $show_badges = !empty($instance['show_badges']);
        ?>
        <p>
            <label for="<?php echo esc_attr($this->get_field_id('title')); ?>">
                <?php esc_html_e('Title:', 'saga-manager'); ?>
            </label>
            <input class="widefat" id="<?php echo esc_attr($this->get_field_id('title')); ?>"
                   name="<?php echo esc_attr($this->get_field_name('title')); ?>" type="text"
                   value="<?php echo esc_attr($title); ?>">
        </p>

        <p>
            <label for="<?php echo esc_attr($this->get_field_id('display_type')); ?>">
                <?php esc_html_e('Display Type:', 'saga-manager'); ?>
            </label>
            <select class="widefat" id="<?php echo esc_attr($this->get_field_id('display_type')); ?>"
                    name="<?php echo esc_attr($this->get_field_name('display_type')); ?>">
                <option value="trending" <?php selected($display_type, 'trending'); ?>>
                    <?php esc_html_e('Trending', 'saga-manager'); ?>
                </option>
                <option value="popular" <?php selected($display_type, 'popular'); ?>>
                    <?php esc_html_e('Popular (All-time)', 'saga-manager'); ?>
                </option>
                <option value="recent" <?php selected($display_type, 'recent'); ?>>
                    <?php esc_html_e('Recently Viewed', 'saga-manager'); ?>
                </option>
            </select>
        </p>

        <p>
            <label for="<?php echo esc_attr($this->get_field_id('count')); ?>">
                <?php esc_html_e('Number of entities:', 'saga-manager'); ?>
            </label>
            <input class="tiny-text" id="<?php echo esc_attr($this->get_field_id('count')); ?>"
                   name="<?php echo esc_attr($this->get_field_name('count')); ?>" type="number"
                   min="1" max="20" value="<?php echo esc_attr($count); ?>">
        </p>

        <p>
            <input class="checkbox" type="checkbox" id="<?php echo esc_attr($this->get_field_id('show_views')); ?>"
                   name="<?php echo esc_attr($this->get_field_name('show_views')); ?>"
                   <?php checked($show_views); ?>>
            <label for="<?php echo esc_attr($this->get_field_id('show_views')); ?>">
                <?php esc_html_e('Show view counts', 'saga-manager'); ?>
            </label>
        </p>

        <p>
            <input class="checkbox" type="checkbox" id="<?php echo esc_attr($this->get_field_id('show_badges')); ?>"
                   name="<?php echo esc_attr($this->get_field_name('show_badges')); ?>"
                   <?php checked($show_badges); ?>>
            <label for="<?php echo esc_attr($this->get_field_id('show_badges')); ?>">
                <?php esc_html_e('Show popularity badges', 'saga-manager'); ?>
            </label>
        </p>
        <?php
    }

    /**
     * Update widget settings
     *
     * @param array $new_instance New instance settings
     * @param array $old_instance Old instance settings
     * @return array Updated settings
     */
    public function update($new_instance, $old_instance): array {
        $instance = [];
        $instance['title'] = !empty($new_instance['title']) ? sanitize_text_field($new_instance['title']) : '';
        $instance['count'] = !empty($new_instance['count']) ? absint($new_instance['count']) : 5;
        $instance['display_type'] = !empty($new_instance['display_type']) ? sanitize_text_field($new_instance['display_type']) : 'trending';
        $instance['show_views'] = !empty($new_instance['show_views']);
        $instance['show_badges'] = !empty($new_instance['show_badges']);

        // Clear widget cache
        wp_cache_delete('saga_popular_widget_' . $this->id, 'saga_analytics');

        return $instance;
    }
}

/**
 * Register widget
 */
function saga_register_popular_entities_widget(): void {
    register_widget('Saga_Popular_Entities_Widget');
}
add_action('widgets_init', 'saga_register_popular_entities_widget');
