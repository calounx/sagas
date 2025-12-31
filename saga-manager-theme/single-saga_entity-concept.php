<?php
/**
 * Template for Concept Entity Type
 *
 * Displays concept-specific sections: definition, significance, examples, related entities
 *
 * @package SagaManager
 * @since 1.0.0
 */

declare(strict_types=1);

use function SagaManager\Theme\saga_output_schema_markup;
use function SagaManager\Theme\saga_output_og_tags;
use function SagaManager\Theme\saga_get_related_entities;
use function SagaManager\Theme\saga_get_entity_timeline;

get_header();

while (have_posts()) :
    the_post();
    $post_id = get_the_ID();

    // Output structured data
    saga_output_schema_markup($post_id, 'concept');
    saga_output_og_tags($post_id, 'concept');
    ?>

    <article id="post-<?php echo $post_id; ?>" <?php post_class('saga-entity saga-entity--concept'); ?>>

        <!-- Hero Section -->
        <header class="saga-entity__hero saga-concept__hero">
            <div class="container">
                <div class="saga-entity__hero-content">

                    <?php if (has_post_thumbnail()) : ?>
                        <div class="saga-concept__icon">
                            <?php the_post_thumbnail('medium', [
                                'class' => 'saga-concept__icon-image',
                                'alt' => get_the_title(),
                            ]); ?>
                        </div>
                    <?php endif; ?>

                    <div class="saga-entity__hero-text">
                        <div class="saga-entity__meta">
                            <span class="saga-entity__type"><?php esc_html_e('Concept', 'saga-manager'); ?></span>

                            <?php
                            $sagas = get_the_terms($post_id, 'saga');
                            if ($sagas && !is_wp_error($sagas)) :
                                ?>
                                <span class="saga-entity__saga">
                                    <?php echo esc_html($sagas[0]->name); ?>
                                </span>
                            <?php endif; ?>
                        </div>

                        <h1 class="saga-entity__title"><?php the_title(); ?></h1>

                        <?php
                        $terminology = get_post_meta($post_id, '_saga_concept_terminology', true);
                        if (!empty($terminology)) :
                            ?>
                            <p class="saga-concept__terminology">
                                <strong><?php esc_html_e('Also known as:', 'saga-manager'); ?></strong>
                                <?php echo esc_html($terminology); ?>
                            </p>
                        <?php endif; ?>

                        <?php if (has_excerpt()) : ?>
                            <div class="saga-entity__excerpt">
                                <?php the_excerpt(); ?>
                            </div>
                        <?php endif; ?>
                    </div>

                </div>
            </div>
        </header>

        <!-- Main Content -->
        <div class="saga-entity__content">
            <div class="container">
                <div class="saga-entity__grid">

                    <!-- Primary Column -->
                    <div class="saga-entity__primary">

                        <!-- Definition & Explanation -->
                        <section class="saga-section saga-concept__definition">
                            <h2 class="saga-section__title"><?php esc_html_e('Definition', 'saga-manager'); ?></h2>
                            <div class="saga-section__content">
                                <?php the_content(); ?>
                            </div>
                        </section>

                        <!-- In-Universe Significance -->
                        <?php
                        $significance = get_post_meta($post_id, '_saga_concept_significance', true);
                        if (!empty($significance)) :
                            ?>
                            <section class="saga-section saga-concept__significance">
                                <h2 class="saga-section__title"><?php esc_html_e('Significance in Universe', 'saga-manager'); ?></h2>
                                <div class="saga-section__content">
                                    <?php echo wp_kses_post(wpautop($significance)); ?>
                                </div>
                            </section>
                        <?php endif; ?>

                        <!-- Historical Context -->
                        <?php
                        $historical_context = get_post_meta($post_id, '_saga_concept_historical_context', true);
                        if (!empty($historical_context)) :
                            ?>
                            <section class="saga-section saga-concept__historical-context">
                                <h2 class="saga-section__title"><?php esc_html_e('Historical Context', 'saga-manager'); ?></h2>
                                <div class="saga-section__content">
                                    <?php echo wp_kses_post(wpautop($historical_context)); ?>
                                </div>
                            </section>
                        <?php endif; ?>

                        <!-- Examples & Instances -->
                        <?php
                        $examples = get_post_meta($post_id, '_saga_concept_examples', true);
                        if (!empty($examples)) :
                            ?>
                            <section class="saga-section saga-concept__examples">
                                <h2 class="saga-section__title"><?php esc_html_e('Examples & Instances', 'saga-manager'); ?></h2>
                                <div class="saga-section__content">
                                    <?php echo wp_kses_post(wpautop($examples)); ?>
                                </div>
                            </section>
                        <?php endif; ?>

                        <!-- Related Events -->
                        <?php
                        $timeline = saga_get_entity_timeline($post_id);
                        if (!empty($timeline)) :
                            get_template_part('template-parts/entity/event-timeline', null, [
                                'events' => $timeline,
                                'title' => __('Related Events', 'saga-manager'),
                            ]);
                        endif;
                        ?>

                    </div>

                    <!-- Sidebar Column -->
                    <aside class="saga-entity__sidebar">

                        <!-- Concept Details -->
                        <section class="saga-section saga-concept__details">
                            <h2 class="saga-section__title"><?php esc_html_e('Details', 'saga-manager'); ?></h2>
                            <dl class="saga-attributes">

                                <?php
                                $concept_type = get_post_meta($post_id, '_saga_concept_type', true);
                                if (!empty($concept_type)) :
                                    ?>
                                    <div class="saga-attribute">
                                        <dt class="saga-attribute__label"><?php esc_html_e('Type', 'saga-manager'); ?></dt>
                                        <dd class="saga-attribute__value"><?php echo esc_html($concept_type); ?></dd>
                                    </div>
                                <?php endif; ?>

                                <?php
                                $origin = get_post_meta($post_id, '_saga_concept_origin', true);
                                if (!empty($origin)) :
                                    ?>
                                    <div class="saga-attribute">
                                        <dt class="saga-attribute__label"><?php esc_html_e('Origin', 'saga-manager'); ?></dt>
                                        <dd class="saga-attribute__value"><?php echo esc_html($origin); ?></dd>
                                    </div>
                                <?php endif; ?>

                                <?php
                                $first_mentioned = get_post_meta($post_id, '_saga_concept_first_mentioned', true);
                                if (!empty($first_mentioned)) :
                                    ?>
                                    <div class="saga-attribute">
                                        <dt class="saga-attribute__label"><?php esc_html_e('First Mentioned', 'saga-manager'); ?></dt>
                                        <dd class="saga-attribute__value"><?php echo esc_html($first_mentioned); ?></dd>
                                    </div>
                                <?php endif; ?>

                                <?php
                                $scope = get_post_meta($post_id, '_saga_concept_scope', true);
                                if (!empty($scope)) :
                                    ?>
                                    <div class="saga-attribute">
                                        <dt class="saga-attribute__label"><?php esc_html_e('Scope', 'saga-manager'); ?></dt>
                                        <dd class="saga-attribute__value"><?php echo esc_html($scope); ?></dd>
                                    </div>
                                <?php endif; ?>

                            </dl>
                        </section>

                        <!-- Related Characters -->
                        <?php
                        $related_characters = saga_get_related_entities($post_id, 'practices');
                        if (!empty($related_characters)) :
                            ?>
                            <section class="saga-section saga-concept__related-characters">
                                <h2 class="saga-section__title"><?php esc_html_e('Associated Characters', 'saga-manager'); ?></h2>
                                <ul class="saga-related-list">
                                    <?php foreach ($related_characters as $character) : ?>
                                        <li class="saga-related-list__item">
                                            <a href="<?php echo esc_url(get_permalink($character->ID)); ?>" class="saga-related-list__link">
                                                <?php if (has_post_thumbnail($character->ID)) : ?>
                                                    <?php echo get_the_post_thumbnail($character->ID, 'thumbnail', ['class' => 'saga-related-list__image']); ?>
                                                <?php endif; ?>
                                                <span class="saga-related-list__title"><?php echo esc_html($character->post_title); ?></span>
                                            </a>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            </section>
                        <?php endif; ?>

                        <!-- Related Factions -->
                        <?php
                        $related_factions = saga_get_related_entities($post_id, 'follows');
                        if (!empty($related_factions)) :
                            ?>
                            <section class="saga-section saga-concept__related-factions">
                                <h2 class="saga-section__title"><?php esc_html_e('Associated Factions', 'saga-manager'); ?></h2>
                                <ul class="saga-related-list">
                                    <?php foreach ($related_factions as $faction) : ?>
                                        <li class="saga-related-list__item">
                                            <a href="<?php echo esc_url(get_permalink($faction->ID)); ?>" class="saga-related-list__link">
                                                <?php if (has_post_thumbnail($faction->ID)) : ?>
                                                    <?php echo get_the_post_thumbnail($faction->ID, 'thumbnail', ['class' => 'saga-related-list__image']); ?>
                                                <?php endif; ?>
                                                <span class="saga-related-list__title"><?php echo esc_html($faction->post_title); ?></span>
                                            </a>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            </section>
                        <?php endif; ?>

                        <!-- Related Locations -->
                        <?php
                        $related_locations = saga_get_related_entities($post_id, 'practiced_in');
                        if (!empty($related_locations)) :
                            ?>
                            <section class="saga-section saga-concept__related-locations">
                                <h2 class="saga-section__title"><?php esc_html_e('Locations', 'saga-manager'); ?></h2>
                                <ul class="saga-related-list">
                                    <?php foreach ($related_locations as $location) : ?>
                                        <li class="saga-related-list__item">
                                            <a href="<?php echo esc_url(get_permalink($location->ID)); ?>" class="saga-related-list__link">
                                                <?php echo esc_html($location->post_title); ?>
                                            </a>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            </section>
                        <?php endif; ?>

                        <!-- Related Concepts -->
                        <?php
                        $related_concepts = new WP_Query([
                            'post_type' => 'saga_entity',
                            'posts_per_page' => 5,
                            'post__not_in' => [$post_id],
                            'tax_query' => [
                                [
                                    'taxonomy' => 'saga_type',
                                    'field' => 'slug',
                                    'terms' => 'concept',
                                ],
                                [
                                    'taxonomy' => 'saga',
                                    'terms' => wp_get_post_terms($post_id, 'saga', ['fields' => 'ids']),
                                ],
                            ],
                        ]);

                        if ($related_concepts->have_posts()) :
                            ?>
                            <section class="saga-section saga-concept__related">
                                <h2 class="saga-section__title"><?php esc_html_e('Related Concepts', 'saga-manager'); ?></h2>
                                <ul class="saga-related-list">
                                    <?php
                                    while ($related_concepts->have_posts()) :
                                        $related_concepts->the_post();
                                        ?>
                                        <li class="saga-related-list__item">
                                            <a href="<?php the_permalink(); ?>" class="saga-related-list__link">
                                                <?php the_title(); ?>
                                            </a>
                                        </li>
                                    <?php endwhile; ?>
                                </ul>
                            </section>
                            <?php
                            wp_reset_postdata();
                        endif;
                        ?>

                    </aside>

                </div>
            </div>
        </div>

        <!-- User Annotations -->
        <?php if (is_user_logged_in()) : ?>
            <div class="saga-entity__annotations">
                <div class="container">
                    <?php
                    // Display existing annotations
                    get_template_part('template-parts/annotation-display');

                    // Display annotation form
                    get_template_part('template-parts/annotation-form');
                    ?>
                </div>
            </div>
        <?php endif; ?>

        <!-- Footer Meta -->
        <footer class="saga-entity__footer">
            <div class="container">
                <div class="saga-entity__footer-meta">
                    <span class="saga-entity__date">
                        <?php
                        printf(
                            /* translators: %s: post date */
                            esc_html__('Last updated: %s', 'saga-manager'),
                            esc_html(get_the_modified_date())
                        );
                        ?>
                    </span>
                </div>
            </div>
        </footer>

    </article>

    <?php
endwhile;

get_footer();
