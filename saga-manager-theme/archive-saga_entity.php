<?php
/**
 * Archive Template for Saga Entities with Masonry Layout
 *
 * Displays entity archive with Pinterest-style masonry grid
 * and infinite scroll functionality
 *
 * @package SagaTheme
 * @version 1.0.0
 */

declare(strict_types=1);

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

get_header();
?>

<div class="saga-archive-wrapper">
    <div class="saga-archive-header">
        <div class="container">
            <h1 class="saga-archive-title">
                <?php
                if (is_tax('saga_type')) {
                    single_term_title();
                } else {
                    esc_html_e('Saga Entities', 'saga-manager-theme');
                }
                ?>
            </h1>

            <?php
            $term_description = term_description();
            if (!empty($term_description)) :
            ?>
                <div class="saga-archive-description">
                    <?php echo wp_kses_post($term_description); ?>
                </div>
            <?php endif; ?>

            <!-- Archive Filters -->
            <div class="saga-archive-filters">
                <form class="saga-filter-form" method="get">
                    <div class="saga-filter-form__controls">
                        <!-- Search -->
                        <div class="saga-filter-form__field">
                            <label for="saga-search" class="sr-only">
                                <?php esc_html_e('Search entities', 'saga-manager-theme'); ?>
                            </label>
                            <input
                                type="search"
                                id="saga-search"
                                name="s"
                                class="saga-filter-form__search"
                                placeholder="<?php esc_attr_e('Search entities...', 'saga-manager-theme'); ?>"
                                value="<?php echo esc_attr(get_search_query()); ?>"
                            >
                        </div>

                        <!-- Sort Order -->
                        <div class="saga-filter-form__field">
                            <label for="saga-orderby" class="sr-only">
                                <?php esc_html_e('Sort by', 'saga-manager-theme'); ?>
                            </label>
                            <select id="saga-orderby" name="orderby" class="saga-filter-form__select">
                                <option value="importance" <?php selected(isset($_GET['orderby']) && $_GET['orderby'] === 'importance'); ?>>
                                    <?php esc_html_e('Importance', 'saga-manager-theme'); ?>
                                </option>
                                <option value="title" <?php selected(isset($_GET['orderby']) && $_GET['orderby'] === 'title'); ?>>
                                    <?php esc_html_e('Title (A-Z)', 'saga-manager-theme'); ?>
                                </option>
                                <option value="date" <?php selected(isset($_GET['orderby']) && $_GET['orderby'] === 'date'); ?>>
                                    <?php esc_html_e('Recently Added', 'saga-manager-theme'); ?>
                                </option>
                            </select>
                        </div>

                        <!-- Submit Button -->
                        <button type="submit" class="saga-filter-form__submit">
                            <?php esc_html_e('Filter', 'saga-manager-theme'); ?>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="saga-archive-content">
        <div class="container">
            <?php if (have_posts()) : ?>

                <!-- Masonry Grid with Infinite Scroll -->
                <div
                    class="saga-masonry-grid"
                    data-infinite-scroll="true"
                    data-per-page="12"
                    data-threshold="300"
                    <?php if (is_tax('saga_type')) : ?>
                        data-entity-type="<?php echo esc_attr(get_queried_object()->slug); ?>"
                    <?php endif; ?>
                    <?php if (isset($_GET['orderby'])) : ?>
                        data-orderby="<?php echo esc_attr(sanitize_text_field($_GET['orderby'])); ?>"
                    <?php endif; ?>
                >
                    <!-- Grid Sizer for Masonry Column Width -->
                    <div class="saga-masonry-grid__sizer"></div>

                    <?php
                    while (have_posts()) :
                        the_post();
                    ?>
                        <div class="saga-masonry-grid__item">
                            <?php
                            get_template_part('template-parts/entity-card-masonry', null, [
                                'entity_id' => get_the_ID(),
                            ]);
                            ?>
                        </div>
                    <?php endwhile; ?>
                </div>

                <!-- Fallback Pagination (if JavaScript disabled) -->
                <noscript>
                    <div class="saga-pagination">
                        <?php
                        the_posts_pagination([
                            'mid_size' => 2,
                            'prev_text' => __('&larr; Previous', 'saga-manager-theme'),
                            'next_text' => __('Next &rarr;', 'saga-manager-theme'),
                        ]);
                        ?>
                    </div>
                </noscript>

            <?php else : ?>

                <!-- Empty State -->
                <div class="saga-empty-state">
                    <div class="saga-empty-state__icon">🔍</div>
                    <h2 class="saga-empty-state__title">
                        <?php esc_html_e('No entities found', 'saga-manager-theme'); ?>
                    </h2>
                    <p class="saga-empty-state__description">
                        <?php esc_html_e('Try adjusting your filters or search query.', 'saga-manager-theme'); ?>
                    </p>
                    <?php if (get_search_query()) : ?>
                        <a href="<?php echo esc_url(get_post_type_archive_link('saga_entity')); ?>" class="saga-empty-state__button">
                            <?php esc_html_e('Clear Search', 'saga-manager-theme'); ?>
                        </a>
                    <?php endif; ?>
                </div>

            <?php endif; ?>
        </div>
    </div>
</div>

<?php get_footer();
