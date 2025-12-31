<?php
/**
 * Single Saga Entity Template
 *
 * @package SagaManagerTheme
 * @since 1.0.0
 */

declare(strict_types=1);

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

get_header();
?>

<div id="primary" class="content-area saga-entity-single-content">
    <main id="main" class="site-main">

        <?php
        while (have_posts()) :
            the_post();
            ?>

            <article id="post-<?php the_ID(); ?>" <?php post_class('saga-entity-article'); ?>>

                <header class="entry-header">
                    <?php
                    // Display entity type badge
                    $entity_type = saga_get_entity_type(get_the_ID());
                    if ($entity_type && saga_get_option('saga_show_type_badge', true)) {
                        echo saga_get_entity_type_badge($entity_type);
                    }
                    ?>

                    <?php the_title('<h1 class="entry-title">', '</h1>'); ?>

                    <?php
                    // Display reading mode button
                    echo '<div class="saga-entity-actions" style="margin-top: 1rem; display: flex; gap: 0.75rem; flex-wrap: wrap;">';
                    saga_reading_mode_button();
                    echo '</div>';
                    ?>

                    <?php
                    // Display importance score
                    if (saga_get_option('saga_show_importance', true)) {
                        $importance = saga_get_importance_score(get_the_ID());
                        echo '<div class="saga-entity-importance">';
                        echo '<span class="saga-entity-importance__label">' . 
                             esc_html__('Importance:', 'saga-manager-theme') . 
                             '</span>';
                        echo saga_format_importance_score($importance);
                        echo '</div>';
                    }
                    ?>
                </header>

                <?php if (has_post_thumbnail()) : ?>
                    <div class="saga-entity-featured-image">
                        <?php the_post_thumbnail('large'); ?>
                    </div>
                <?php endif; ?>

                <div class="entry-content">
                    <?php
                    the_content();

                    wp_link_pages([
                        'before' => '<div class="page-links">' . esc_html__('Pages:', 'saga-manager-theme'),
                        'after'  => '</div>',
                    ]);
                    ?>
                </div>

                <footer class="entry-footer">
                    <?php
                    // Display taxonomy terms
                    $terms = get_the_terms(get_the_ID(), 'saga_type');
                    if ($terms && !is_wp_error($terms)) {
                        echo '<div class="saga-entity-terms">';
                        echo '<span class="saga-entity-terms__label">' . 
                             esc_html__('Type:', 'saga-manager-theme') . 
                             '</span>';
                        foreach ($terms as $term) {
                            echo '<a href="' . esc_url(get_term_link($term)) . '" class="saga-entity-term">';
                            echo esc_html($term->name);
                            echo '</a>';
                        }
                        echo '</div>';
                    }
                    ?>
                </footer>

            </article>

            <?php
            // Display annotations for logged-in users
            if (is_user_logged_in()) :
                ?>
                <div class="saga-entity__annotations">
                    <?php
                    // Display existing annotations
                    get_template_part('template-parts/annotation-display');

                    // Display annotation form
                    get_template_part('template-parts/annotation-form');
                    ?>
                </div>
            <?php
            endif;
            ?>

            <?php
        endwhile;
        ?>

    </main>
</div>

<?php
get_sidebar();
get_footer();
