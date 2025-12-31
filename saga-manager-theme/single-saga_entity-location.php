<?php
/**
 * Template for Location Entity Type
 *
 * Displays location-specific sections: geography, inhabitants, sub-locations, events
 *
 * @package SagaManager
 * @since 1.0.0
 */

declare(strict_types=1);

use function SagaManager\Theme\saga_output_schema_markup;
use function SagaManager\Theme\saga_output_og_tags;
use function SagaManager\Theme\saga_get_related_entities;
use function SagaManager\Theme\saga_get_entity_timeline;
use function SagaManager\Theme\saga_get_entity_attributes;

get_header();

while (have_posts()) :
    the_post();
    $post_id = get_the_ID();

    // Output structured data
    saga_output_schema_markup($post_id, 'location');
    saga_output_og_tags($post_id, 'location');
    ?>

    <article id="post-<?php echo $post_id; ?>" <?php post_class('saga-entity saga-entity--location'); ?>>

        <!-- Hero Section -->
        <header class="saga-entity__hero saga-location__hero">
            <div class="container">
                <?php if (has_post_thumbnail()) : ?>
                    <div class="saga-location__featured-image">
                        <?php the_post_thumbnail('full', [
                            'class' => 'saga-location__image',
                            'alt' => get_the_title(),
                        ]); ?>
                    </div>
                <?php endif; ?>

                <div class="saga-entity__hero-content">
                    <div class="saga-entity__meta">
                        <span class="saga-entity__type"><?php esc_html_e('Location', 'saga-manager'); ?></span>

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

                    <?php if (has_excerpt()) : ?>
                        <div class="saga-entity__excerpt">
                            <?php the_excerpt(); ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </header>

        <!-- Main Content -->
        <div class="saga-entity__content">
            <div class="container">
                <div class="saga-entity__grid">

                    <!-- Primary Column -->
                    <div class="saga-entity__primary">

                        <!-- Description -->
                        <section class="saga-section saga-location__description">
                            <h2 class="saga-section__title"><?php esc_html_e('Description', 'saga-manager'); ?></h2>
                            <div class="saga-section__content">
                                <?php the_content(); ?>
                            </div>
                        </section>

                        <!-- Geography & Climate -->
                        <?php
                        $geography = get_post_meta($post_id, '_saga_location_geography', true);
                        $climate = get_post_meta($post_id, '_saga_location_climate', true);

                        if (!empty($geography) || !empty($climate)) :
                            ?>
                            <section class="saga-section saga-location__geography">
                                <h2 class="saga-section__title"><?php esc_html_e('Geography & Climate', 'saga-manager'); ?></h2>
                                <div class="saga-section__content">
                                    <?php if (!empty($geography)) : ?>
                                        <div class="saga-location__geography-detail">
                                            <h3><?php esc_html_e('Geography', 'saga-manager'); ?></h3>
                                            <?php echo wp_kses_post(wpautop($geography)); ?>
                                        </div>
                                    <?php endif; ?>

                                    <?php if (!empty($climate)) : ?>
                                        <div class="saga-location__climate-detail">
                                            <h3><?php esc_html_e('Climate', 'saga-manager'); ?></h3>
                                            <?php echo wp_kses_post(wpautop($climate)); ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </section>
                        <?php endif; ?>

                        <!-- Sub-Locations -->
                        <?php
                        $sublocation_args = [
                            'post_type' => 'saga_entity',
                            'posts_per_page' => 20,
                            'post_parent' => $post_id,
                            'orderby' => 'title',
                            'order' => 'ASC',
                        ];

                        $sublocations = new WP_Query($sublocation_args);

                        if ($sublocations->have_posts()) :
                            ?>
                            <section class="saga-section saga-location__sublocations">
                                <h2 class="saga-section__title"><?php esc_html_e('Sub-Locations', 'saga-manager'); ?></h2>
                                <div class="saga-location__sublocation-grid">
                                    <?php
                                    while ($sublocations->have_posts()) :
                                        $sublocations->the_post();
                                        ?>
                                        <div class="saga-location__sublocation-card">
                                            <a href="<?php the_permalink(); ?>" class="saga-location__sublocation-link">
                                                <?php if (has_post_thumbnail()) : ?>
                                                    <div class="saga-location__sublocation-image">
                                                        <?php the_post_thumbnail('medium'); ?>
                                                    </div>
                                                <?php endif; ?>
                                                <div class="saga-location__sublocation-content">
                                                    <h3 class="saga-location__sublocation-title"><?php the_title(); ?></h3>
                                                    <?php if (has_excerpt()) : ?>
                                                        <p class="saga-location__sublocation-excerpt"><?php echo esc_html(get_the_excerpt()); ?></p>
                                                    <?php endif; ?>
                                                </div>
                                            </a>
                                        </div>
                                    <?php endwhile; ?>
                                </div>
                            </section>
                            <?php
                            wp_reset_postdata();
                        endif;
                        ?>

                        <!-- Historical Events -->
                        <?php
                        $timeline = saga_get_entity_timeline($post_id);
                        if (!empty($timeline)) :
                            get_template_part('template-parts/entity/event-timeline', null, [
                                'events' => $timeline,
                                'title' => __('Historical Events', 'saga-manager'),
                            ]);
                        endif;
                        ?>

                    </div>

                    <!-- Sidebar Column -->
                    <aside class="saga-entity__sidebar">

                        <!-- Location Details -->
                        <?php
                        $location_type = get_post_meta($post_id, '_saga_location_type', true);
                        $population = get_post_meta($post_id, '_saga_location_population', true);
                        $government = get_post_meta($post_id, '_saga_location_government', true);
                        $established = get_post_meta($post_id, '_saga_location_established', true);

                        if (!empty($location_type) || !empty($population) || !empty($government) || !empty($established)) :
                            ?>
                            <section class="saga-section saga-location__details">
                                <h2 class="saga-section__title"><?php esc_html_e('Details', 'saga-manager'); ?></h2>
                                <dl class="saga-attributes">

                                    <?php if (!empty($location_type)) : ?>
                                        <div class="saga-attribute">
                                            <dt class="saga-attribute__label"><?php esc_html_e('Type', 'saga-manager'); ?></dt>
                                            <dd class="saga-attribute__value"><?php echo esc_html($location_type); ?></dd>
                                        </div>
                                    <?php endif; ?>

                                    <?php if (!empty($population)) : ?>
                                        <div class="saga-attribute">
                                            <dt class="saga-attribute__label"><?php esc_html_e('Population', 'saga-manager'); ?></dt>
                                            <dd class="saga-attribute__value"><?php echo esc_html($population); ?></dd>
                                        </div>
                                    <?php endif; ?>

                                    <?php if (!empty($government)) : ?>
                                        <div class="saga-attribute">
                                            <dt class="saga-attribute__label"><?php esc_html_e('Government', 'saga-manager'); ?></dt>
                                            <dd class="saga-attribute__value"><?php echo esc_html($government); ?></dd>
                                        </div>
                                    <?php endif; ?>

                                    <?php if (!empty($established)) : ?>
                                        <div class="saga-attribute">
                                            <dt class="saga-attribute__label"><?php esc_html_e('Established', 'saga-manager'); ?></dt>
                                            <dd class="saga-attribute__value"><?php echo esc_html($established); ?></dd>
                                        </div>
                                    <?php endif; ?>

                                </dl>
                            </section>
                        <?php endif; ?>

                        <!-- Map/Coordinates -->
                        <?php
                        $coordinates = get_post_meta($post_id, '_saga_location_coordinates', true);
                        if (!empty($coordinates)) :
                            get_template_part('template-parts/entity/location-map', null, [
                                'coordinates' => $coordinates,
                            ]);
                        endif;
                        ?>

                        <!-- Inhabitants -->
                        <?php
                        $inhabitants = saga_get_related_entities($post_id, 'inhabits');
                        if (!empty($inhabitants)) :
                            ?>
                            <section class="saga-section saga-location__inhabitants">
                                <h2 class="saga-section__title"><?php esc_html_e('Notable Inhabitants', 'saga-manager'); ?></h2>
                                <ul class="saga-related-list">
                                    <?php foreach ($inhabitants as $inhabitant) : ?>
                                        <li class="saga-related-list__item">
                                            <a href="<?php echo esc_url(get_permalink($inhabitant->ID)); ?>" class="saga-related-list__link">
                                                <?php if (has_post_thumbnail($inhabitant->ID)) : ?>
                                                    <?php echo get_the_post_thumbnail($inhabitant->ID, 'thumbnail', ['class' => 'saga-related-list__image']); ?>
                                                <?php endif; ?>
                                                <span class="saga-related-list__title"><?php echo esc_html($inhabitant->post_title); ?></span>
                                            </a>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            </section>
                        <?php endif; ?>

                        <!-- Factions/Organizations -->
                        <?php
                        $factions = saga_get_related_entities($post_id, 'controls');
                        if (!empty($factions)) :
                            ?>
                            <section class="saga-section saga-location__factions">
                                <h2 class="saga-section__title"><?php esc_html_e('Controlling Factions', 'saga-manager'); ?></h2>
                                <ul class="saga-related-list">
                                    <?php foreach ($factions as $faction) : ?>
                                        <li class="saga-related-list__item">
                                            <a href="<?php echo esc_url(get_permalink($faction->ID)); ?>" class="saga-related-list__link">
                                                <?php echo esc_html($faction->post_title); ?>
                                            </a>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            </section>
                        <?php endif; ?>

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
