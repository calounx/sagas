<?php
/**
 * Example: Using Masonry Layout in Custom Templates
 *
 * This file demonstrates how to implement the masonry layout
 * with infinite scroll in custom page templates or shortcodes
 *
 * @package SagaTheme
 * @version 1.0.0
 */

declare(strict_types=1);

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Example 1: Basic Masonry Grid
 *
 * Minimal implementation with default settings
 */
function example_basic_masonry_grid() {
    ?>
    <div class="saga-masonry-grid" data-infinite-scroll="true">
        <div class="saga-masonry-grid__sizer"></div>

        <?php
        $query = new WP_Query([
            'post_type' => 'saga_entity',
            'posts_per_page' => 12,
        ]);

        while ($query->have_posts()) :
            $query->the_post();
        ?>
            <div class="saga-masonry-grid__item">
                <?php get_template_part('template-parts/entity-card-masonry', null, [
                    'entity_id' => get_the_ID()
                ]); ?>
            </div>
        <?php endwhile; wp_reset_postdata(); ?>
    </div>
    <?php
}

/**
 * Example 2: Masonry Grid with Custom Query
 *
 * Filter entities by specific criteria
 */
function example_filtered_masonry_grid() {
    ?>
    <div
        class="saga-masonry-grid"
        data-infinite-scroll="true"
        data-per-page="16"
        data-threshold="500"
        data-entity-type="character"
    >
        <div class="saga-masonry-grid__sizer"></div>

        <?php
        $query = new WP_Query([
            'post_type' => 'saga_entity',
            'posts_per_page' => 16,
            'tax_query' => [
                [
                    'taxonomy' => 'saga_type',
                    'field' => 'slug',
                    'terms' => 'character',
                ]
            ],
            'meta_query' => [
                [
                    'key' => '_saga_importance_score',
                    'value' => 50,
                    'compare' => '>=',
                    'type' => 'NUMERIC',
                ]
            ],
        ]);

        while ($query->have_posts()) :
            $query->the_post();
        ?>
            <div class="saga-masonry-grid__item">
                <?php get_template_part('template-parts/entity-card-masonry', null, [
                    'entity_id' => get_the_ID()
                ]); ?>
            </div>
        <?php endwhile; wp_reset_postdata(); ?>
    </div>
    <?php
}

/**
 * Example 3: Masonry Shortcode
 *
 * Usage: [saga_masonry type="character" per_page="12"]
 */
function saga_masonry_shortcode($atts) {
    $atts = shortcode_atts([
        'type' => '',
        'saga_id' => 0,
        'per_page' => 12,
        'orderby' => 'importance',
        'infinite_scroll' => 'true',
    ], $atts, 'saga_masonry');

    $query_args = [
        'post_type' => 'saga_entity',
        'posts_per_page' => absint($atts['per_page']),
    ];

    // Add taxonomy filter
    if (!empty($atts['type'])) {
        $query_args['tax_query'] = [
            [
                'taxonomy' => 'saga_type',
                'field' => 'slug',
                'terms' => sanitize_text_field($atts['type']),
            ]
        ];
    }

    // Add saga filter
    if ($atts['saga_id'] > 0) {
        $query_args['meta_query'] = [
            [
                'key' => '_saga_id',
                'value' => absint($atts['saga_id']),
            ]
        ];
    }

    // Add ordering
    switch ($atts['orderby']) {
        case 'importance':
            $query_args['meta_key'] = '_saga_importance_score';
            $query_args['orderby'] = 'meta_value_num';
            $query_args['order'] = 'DESC';
            break;
        case 'title':
            $query_args['orderby'] = 'title';
            $query_args['order'] = 'ASC';
            break;
        case 'date':
            $query_args['orderby'] = 'date';
            $query_args['order'] = 'DESC';
            break;
    }

    $query = new WP_Query($query_args);

    if (!$query->have_posts()) {
        return '<p>No entities found.</p>';
    }

    ob_start();
    ?>
    <div
        class="saga-masonry-grid"
        data-infinite-scroll="<?php echo esc_attr($atts['infinite_scroll']); ?>"
        data-per-page="<?php echo esc_attr($atts['per_page']); ?>"
        <?php if (!empty($atts['type'])): ?>
            data-entity-type="<?php echo esc_attr($atts['type']); ?>"
        <?php endif; ?>
    >
        <div class="saga-masonry-grid__sizer"></div>

        <?php
        while ($query->have_posts()) :
            $query->the_post();
        ?>
            <div class="saga-masonry-grid__item">
                <?php get_template_part('template-parts/entity-card-masonry', null, [
                    'entity_id' => get_the_ID()
                ]); ?>
            </div>
        <?php endwhile; wp_reset_postdata(); ?>
    </div>
    <?php
    return ob_get_clean();
}
add_shortcode('saga_masonry', 'saga_masonry_shortcode');

/**
 * Example 4: Gutenberg Block (PHP render callback)
 *
 * Register a custom block for masonry grid
 */
function register_saga_masonry_block() {
    if (!function_exists('register_block_type')) {
        return;
    }

    register_block_type('saga/masonry-grid', [
        'render_callback' => 'render_saga_masonry_block',
        'attributes' => [
            'entityType' => [
                'type' => 'string',
                'default' => '',
            ],
            'perPage' => [
                'type' => 'number',
                'default' => 12,
            ],
            'infiniteScroll' => [
                'type' => 'boolean',
                'default' => true,
            ],
        ],
    ]);
}
add_action('init', 'register_saga_masonry_block');

function render_saga_masonry_block($attributes) {
    return saga_masonry_shortcode([
        'type' => $attributes['entityType'] ?? '',
        'per_page' => $attributes['perPage'] ?? 12,
        'infinite_scroll' => ($attributes['infiniteScroll'] ?? true) ? 'true' : 'false',
    ]);
}

/**
 * Example 5: Custom Page Template with Masonry
 *
 * Template Name: Saga Masonry Gallery
 */
function example_masonry_page_template() {
    get_header();
    ?>

    <div class="saga-masonry-page">
        <header class="saga-masonry-page__header">
            <h1><?php the_title(); ?></h1>
            <?php the_content(); ?>
        </header>

        <div class="saga-masonry-page__grid">
            <?php echo do_shortcode('[saga_masonry type="character" per_page="20"]'); ?>
        </div>
    </div>

    <?php
    get_footer();
}

/**
 * Example 6: AJAX Filter Integration
 *
 * Add filtering controls that work with masonry
 */
function example_masonry_with_filters() {
    ?>
    <div class="saga-masonry-filters">
        <select id="saga-type-filter" class="saga-filter-select">
            <option value="">All Types</option>
            <option value="character">Characters</option>
            <option value="location">Locations</option>
            <option value="event">Events</option>
            <option value="faction">Factions</option>
            <option value="artifact">Artifacts</option>
            <option value="concept">Concepts</option>
        </select>

        <select id="saga-sort-filter" class="saga-filter-select">
            <option value="importance">Importance</option>
            <option value="title">Title (A-Z)</option>
            <option value="date">Recently Added</option>
        </select>
    </div>

    <div
        class="saga-masonry-grid"
        id="filterable-masonry"
        data-infinite-scroll="true"
    >
        <!-- Grid items here -->
    </div>

    <script>
    (function() {
        const typeFilter = document.getElementById('saga-type-filter');
        const sortFilter = document.getElementById('saga-sort-filter');
        const grid = document.getElementById('filterable-masonry');

        function updateGrid() {
            const type = typeFilter.value;
            const sort = sortFilter.value;

            // Update data attributes
            grid.dataset.entityType = type;
            grid.dataset.orderby = sort;

            // Clear current items
            grid.innerHTML = '<div class="saga-masonry-grid__sizer"></div>';

            // Reload with new filters
            if (grid.sagaInfiniteScroll) {
                grid.sagaInfiniteScroll.currentPage = 1;
                grid.sagaInfiniteScroll.hasMore = true;
                grid.sagaInfiniteScroll.loadMore();
            }
        }

        typeFilter.addEventListener('change', updateGrid);
        sortFilter.addEventListener('change', updateGrid);
    })();
    </script>
    <?php
}

/**
 * Example 7: Manually Initialize Masonry
 *
 * For custom JavaScript implementations
 */
function example_manual_masonry_init() {
    ?>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const container = document.querySelector('.my-custom-masonry');

        // Wait for masonry class to be available
        if (typeof SagaMasonryLayout !== 'undefined') {
            const masonry = new SagaMasonryLayout(container, {
                itemSelector: '.my-custom-item',
                gutter: 20,
                percentPosition: true
            });

            // Add items dynamically
            const newItems = [...]; // Array of DOM elements
            masonry.addItems(newItems);
        }
    });
    </script>
    <?php
}

/**
 * Example 8: Customize Card Height Distribution
 *
 * Control which cards are tall vs short
 */
function example_custom_card_heights() {
    ?>
    <div class="saga-masonry-grid" data-infinite-scroll="true">
        <div class="saga-masonry-grid__sizer"></div>

        <?php
        $query = new WP_Query(['post_type' => 'saga_entity', 'posts_per_page' => 12]);
        $index = 0;

        while ($query->have_posts()) :
            $query->the_post();
            $index++;

            // Make every 3rd card tall
            $height_class = ($index % 3 === 0) ? 'tall' : 'medium';
        ?>
            <div class="saga-masonry-grid__item">
                <article class="saga-entity-card-masonry saga-entity-card-masonry--<?php echo $height_class; ?>">
                    <!-- Card content -->
                </article>
            </div>
        <?php endwhile; wp_reset_postdata(); ?>
    </div>
    <?php
}

/**
 * Example 9: Preload Next Page (Performance Optimization)
 *
 * Start loading next page before user scrolls to bottom
 */
function example_masonry_preload() {
    ?>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const container = document.querySelector('.saga-masonry-grid');

        // Listen for items loaded
        container.addEventListener('sagaInfiniteScroll:itemsLoaded', function(e) {
            // Preload next page if we're not at the end
            if (e.detail.hasMore) {
                setTimeout(() => {
                    // Silently fetch next page to cache
                    const nextPage = e.detail.page + 1;
                    console.log('Preloading page:', nextPage);
                }, 1000);
            }
        });
    });
    </script>
    <?php
}

/**
 * Example 10: Add Analytics Tracking
 *
 * Track infinite scroll events
 */
function example_masonry_analytics() {
    ?>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const container = document.querySelector('.saga-masonry-grid');

        container.addEventListener('sagaInfiniteScroll:itemsLoaded', function(e) {
            // Google Analytics 4
            if (typeof gtag !== 'undefined') {
                gtag('event', 'infinite_scroll', {
                    page: e.detail.page,
                    items_count: e.detail.items.length
                });
            }

            // Facebook Pixel
            if (typeof fbq !== 'undefined') {
                fbq('track', 'ViewContent', {
                    content_type: 'entity_grid',
                    page: e.detail.page
                });
            }
        });
    });
    </script>
    <?php
}
