<?php
/**
 * Template for Character Entity Type
 *
 * Displays character-specific sections: biography, attributes, relationships, timeline
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
    saga_output_schema_markup($post_id, 'character');
    saga_output_og_tags($post_id, 'character');
    ?>

    <article id="post-<?php echo $post_id; ?>" <?php post_class('saga-entity saga-entity--character'); ?>>

        <!-- Hero Section -->
        <header class="saga-entity__hero saga-character__hero">
            <div class="container">
                <div class="saga-entity__hero-content">

                    <?php if (has_post_thumbnail()) : ?>
                        <div class="saga-character__portrait">
                            <?php the_post_thumbnail('large', [
                                'class' => 'saga-character__portrait-image',
                                'alt' => get_the_title(),
                            ]); ?>
                        </div>
                    <?php endif; ?>

                    <div class="saga-entity__hero-text">
                        <div class="saga-entity__meta">
                            <span class="saga-entity__type"><?php esc_html_e('Character', 'saga-manager'); ?></span>

                            <?php
                            // Display saga taxonomy
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
                        // Display aliases
                        $aliases = get_post_meta($post_id, '_saga_character_aliases', true);
                        if (!empty($aliases)) :
                            ?>
                            <p class="saga-character__aliases">
                                <strong><?php esc_html_e('Also known as:', 'saga-manager'); ?></strong>
                                <?php echo esc_html($aliases); ?>
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

                        <!-- Biography -->
                        <section class="saga-section saga-character__biography">
                            <h2 class="saga-section__title"><?php esc_html_e('Biography', 'saga-manager'); ?></h2>
                            <div class="saga-section__content">
                                <?php the_content(); ?>
                            </div>
                        </section>

                        <!-- Timeline -->
                        <?php
                        $timeline = saga_get_entity_timeline($post_id);
                        if (!empty($timeline)) :
                            get_template_part('template-parts/entity/event-timeline', null, [
                                'events' => $timeline,
                                'title' => __('Character Timeline', 'saga-manager'),
                            ]);
                        endif;
                        ?>

                        <!-- Quotes/Dialogue -->
                        <?php
                        $quotes = get_post_meta($post_id, '_saga_character_quotes', true);
                        if (!empty($quotes)) :
                            ?>
                            <section class="saga-section saga-character__quotes">
                                <h2 class="saga-section__title"><?php esc_html_e('Notable Quotes', 'saga-manager'); ?></h2>
                                <div class="saga-section__content">
                                    <?php echo wp_kses_post(wpautop($quotes)); ?>
                                </div>
                            </section>
                        <?php endif; ?>

                    </div>

                    <!-- Sidebar Column -->
                    <aside class="saga-entity__sidebar">

                        <!-- Character Attributes -->
                        <?php
                        $attributes = saga_get_entity_attributes($post_id);

                        // Also get meta fields
                        $meta_attrs = [
                            'age' => __('Age', 'saga-manager'),
                            'species' => __('Species', 'saga-manager'),
                            'gender' => __('Gender', 'saga-manager'),
                            'homeworld' => __('Homeworld', 'saga-manager'),
                            'occupation' => __('Occupation', 'saga-manager'),
                            'affiliation' => __('Affiliation', 'saga-manager'),
                        ];

                        $has_attrs = !empty($attributes);
                        foreach ($meta_attrs as $key => $label) {
                            if (get_post_meta($post_id, "_saga_character_{$key}", true)) {
                                $has_attrs = true;
                                break;
                            }
                        }

                        if ($has_attrs) :
                            ?>
                            <section class="saga-section saga-character__attributes">
                                <h2 class="saga-section__title"><?php esc_html_e('Attributes', 'saga-manager'); ?></h2>
                                <dl class="saga-attributes">

                                    <?php
                                    // Display meta attributes
                                    foreach ($meta_attrs as $key => $label) :
                                        $value = get_post_meta($post_id, "_saga_character_{$key}", true);
                                        if (!empty($value)) :
                                            ?>
                                            <div class="saga-attribute">
                                                <dt class="saga-attribute__label"><?php echo esc_html($label); ?></dt>
                                                <dd class="saga-attribute__value"><?php echo esc_html($value); ?></dd>
                                            </div>
                                        <?php
                                        endif;
                                    endforeach;

                                    // Display EAV attributes
                                    foreach ($attributes as $key => $attr) :
                                        if (!empty($attr['value'])) :
                                            ?>
                                            <div class="saga-attribute">
                                                <dt class="saga-attribute__label"><?php echo esc_html($attr['label']); ?></dt>
                                                <dd class="saga-attribute__value">
                                                    <?php
                                                    if (is_array($attr['value'])) {
                                                        echo esc_html(implode(', ', $attr['value']));
                                                    } else {
                                                        echo esc_html($attr['value']);
                                                    }
                                                    ?>
                                                </dd>
                                            </div>
                                        <?php
                                        endif;
                                    endforeach;
                                    ?>

                                </dl>
                            </section>
                        <?php endif; ?>

                        <!-- Relationships -->
                        <?php
                        $relationships = saga_get_related_entities($post_id);
                        if (!empty($relationships)) :
                            ?>
                            <section class="saga-section saga-character__relationships">
                                <h2 class="saga-section__title"><?php esc_html_e('Relationships', 'saga-manager'); ?></h2>
                                <div class="saga-relationships">
                                    <?php foreach ($relationships as $related) : ?>
                                        <div class="saga-relationship">
                                            <a href="<?php echo esc_url(get_permalink($related->ID)); ?>" class="saga-relationship__link">
                                                <?php if (has_post_thumbnail($related->ID)) : ?>
                                                    <div class="saga-relationship__image">
                                                        <?php echo get_the_post_thumbnail($related->ID, 'thumbnail'); ?>
                                                    </div>
                                                <?php endif; ?>
                                                <div class="saga-relationship__content">
                                                    <h3 class="saga-relationship__title"><?php echo esc_html($related->post_title); ?></h3>
                                                    <?php if (!empty($related->relationship_type)) : ?>
                                                        <span class="saga-relationship__type">
                                                            <?php echo esc_html(ucfirst($related->relationship_type)); ?>
                                                        </span>
                                                    <?php endif; ?>
                                                </div>
                                            </a>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </section>
                        <?php endif; ?>

                        <!-- Related Characters -->
                        <?php
                        // Get related posts via WordPress relationships
                        $related_args = [
                            'post_type' => 'saga_entity',
                            'posts_per_page' => 5,
                            'post__not_in' => [$post_id],
                            'tax_query' => [
                                [
                                    'taxonomy' => 'saga',
                                    'terms' => wp_get_post_terms($post_id, 'saga', ['fields' => 'ids']),
                                ],
                            ],
                        ];

                        $related_query = new WP_Query($related_args);

                        if ($related_query->have_posts()) :
                            ?>
                            <section class="saga-section saga-character__related">
                                <h2 class="saga-section__title"><?php esc_html_e('Related Characters', 'saga-manager'); ?></h2>
                                <ul class="saga-related-list">
                                    <?php
                                    while ($related_query->have_posts()) :
                                        $related_query->the_post();
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
