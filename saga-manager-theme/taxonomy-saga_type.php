<?php
/**
 * Taxonomy Saga Type Template
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

<div id="primary" class="content-area saga-taxonomy-content">
    <main id="main" class="site-main">

        <?php if (have_posts()) : ?>

            <div class="saga-entities-grid" id="saga-entities-container">

                <?php
                while (have_posts()) :
                    the_post();
                    get_template_part('template-parts/saga/entity', 'card');
                endwhile;
                ?>

            </div>

            <?php
            // Pagination
            the_posts_pagination([
                'mid_size' => 2,
                'prev_text' => __('&laquo; Previous', 'saga-manager-theme'),
                'next_text' => __('Next &raquo;', 'saga-manager-theme'),
                'screen_reader_text' => __('Entities navigation', 'saga-manager-theme'),
            ]);
            ?>

        <?php else : ?>

            <div class="saga-empty-state">
                <div class="saga-empty-state__icon">&#128269;</div>
                <h3 class="saga-empty-state__title"><?php esc_html_e('No entities found', 'saga-manager-theme'); ?></h3>
                <p class="saga-empty-state__description">
                    <?php
                    $term = get_queried_object();
                    printf(
                        esc_html__('No %s entities are available yet.', 'saga-manager-theme'),
                        esc_html($term->name)
                    );
                    ?>
                </p>
            </div>

        <?php endif; ?>

    </main>
</div>

<?php
get_footer();
