<?php
/**
 * Template for Artifact Entity Type
 *
 * Displays artifact-specific sections: properties, history, powers, current owner
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
    saga_output_schema_markup($post_id, 'artifact');
    saga_output_og_tags($post_id, 'artifact');
    ?>

    <article id="post-<?php echo $post_id; ?>" <?php post_class('saga-entity saga-entity--artifact'); ?>>

        <!-- Hero Section -->
        <header class="saga-entity__hero saga-artifact__hero">
            <div class="container">
                <div class="saga-entity__hero-content">

                    <?php if (has_post_thumbnail()) : ?>
                        <div class="saga-artifact__image-container">
                            <?php the_post_thumbnail('large', [
                                'class' => 'saga-artifact__image',
                                'alt' => get_the_title(),
                            ]); ?>
                        </div>
                    <?php endif; ?>

                    <div class="saga-entity__hero-text">
                        <div class="saga-entity__meta">
                            <span class="saga-entity__type"><?php esc_html_e('Artifact', 'saga-manager'); ?></span>

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
                        $alternative_names = get_post_meta($post_id, '_saga_artifact_alternative_names', true);
                        if (!empty($alternative_names)) :
                            ?>
                            <p class="saga-artifact__alternative-names">
                                <strong><?php esc_html_e('Also known as:', 'saga-manager'); ?></strong>
                                <?php echo esc_html($alternative_names); ?>
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

                        <!-- Description -->
                        <section class="saga-section saga-artifact__description">
                            <h2 class="saga-section__title"><?php esc_html_e('Description', 'saga-manager'); ?></h2>
                            <div class="saga-section__content">
                                <?php the_content(); ?>
                            </div>
                        </section>

                        <!-- Powers & Abilities -->
                        <?php
                        $powers = get_post_meta($post_id, '_saga_artifact_powers', true);
                        if (!empty($powers)) :
                            ?>
                            <section class="saga-section saga-artifact__powers">
                                <h2 class="saga-section__title"><?php esc_html_e('Powers & Abilities', 'saga-manager'); ?></h2>
                                <div class="saga-section__content">
                                    <?php echo wp_kses_post(wpautop($powers)); ?>
                                </div>
                            </section>
                        <?php endif; ?>

                        <!-- History & Origin -->
                        <?php
                        $history = get_post_meta($post_id, '_saga_artifact_history', true);
                        if (!empty($history)) :
                            ?>
                            <section class="saga-section saga-artifact__history">
                                <h2 class="saga-section__title"><?php esc_html_e('History & Origin', 'saga-manager'); ?></h2>
                                <div class="saga-section__content">
                                    <?php echo wp_kses_post(wpautop($history)); ?>
                                </div>
                            </section>
                        <?php endif; ?>

                        <!-- Related Lore -->
                        <?php
                        $lore = get_post_meta($post_id, '_saga_artifact_lore', true);
                        if (!empty($lore)) :
                            ?>
                            <section class="saga-section saga-artifact__lore">
                                <h2 class="saga-section__title"><?php esc_html_e('Lore & Legends', 'saga-manager'); ?></h2>
                                <div class="saga-section__content">
                                    <?php echo wp_kses_post(wpautop($lore)); ?>
                                </div>
                            </section>
                        <?php endif; ?>

                        <!-- Timeline -->
                        <?php
                        $timeline = saga_get_entity_timeline($post_id);
                        if (!empty($timeline)) :
                            get_template_part('template-parts/entity/event-timeline', null, [
                                'events' => $timeline,
                                'title' => __('Artifact Timeline', 'saga-manager'),
                            ]);
                        endif;
                        ?>

                    </div>

                    <!-- Sidebar Column -->
                    <aside class="saga-entity__sidebar">

                        <!-- Artifact Properties -->
                        <section class="saga-section saga-artifact__properties">
                            <h2 class="saga-section__title"><?php esc_html_e('Properties', 'saga-manager'); ?></h2>
                            <dl class="saga-attributes">

                                <?php
                                $artifact_type = get_post_meta($post_id, '_saga_artifact_type', true);
                                if (!empty($artifact_type)) :
                                    ?>
                                    <div class="saga-attribute">
                                        <dt class="saga-attribute__label"><?php esc_html_e('Type', 'saga-manager'); ?></dt>
                                        <dd class="saga-attribute__value"><?php echo esc_html($artifact_type); ?></dd>
                                    </div>
                                <?php endif; ?>

                                <?php
                                $material = get_post_meta($post_id, '_saga_artifact_material', true);
                                if (!empty($material)) :
                                    ?>
                                    <div class="saga-attribute">
                                        <dt class="saga-attribute__label"><?php esc_html_e('Material', 'saga-manager'); ?></dt>
                                        <dd class="saga-attribute__value"><?php echo esc_html($material); ?></dd>
                                    </div>
                                <?php endif; ?>

                                <?php
                                $creator = get_post_meta($post_id, '_saga_artifact_creator', true);
                                if (!empty($creator)) :
                                    ?>
                                    <div class="saga-attribute">
                                        <dt class="saga-attribute__label"><?php esc_html_e('Creator', 'saga-manager'); ?></dt>
                                        <dd class="saga-attribute__value"><?php echo esc_html($creator); ?></dd>
                                    </div>
                                <?php endif; ?>

                                <?php
                                $created_date = get_post_meta($post_id, '_saga_artifact_created_date', true);
                                if (!empty($created_date)) :
                                    ?>
                                    <div class="saga-attribute">
                                        <dt class="saga-attribute__label"><?php esc_html_e('Created', 'saga-manager'); ?></dt>
                                        <dd class="saga-attribute__value"><?php echo esc_html($created_date); ?></dd>
                                    </div>
                                <?php endif; ?>

                                <?php
                                $rarity = get_post_meta($post_id, '_saga_artifact_rarity', true);
                                if (!empty($rarity)) :
                                    ?>
                                    <div class="saga-attribute">
                                        <dt class="saga-attribute__label"><?php esc_html_e('Rarity', 'saga-manager'); ?></dt>
                                        <dd class="saga-attribute__value"><?php echo esc_html($rarity); ?></dd>
                                    </div>
                                <?php endif; ?>

                                <?php
                                $status = get_post_meta($post_id, '_saga_artifact_status', true);
                                if (!empty($status)) :
                                    ?>
                                    <div class="saga-attribute">
                                        <dt class="saga-attribute__label"><?php esc_html_e('Status', 'saga-manager'); ?></dt>
                                        <dd class="saga-attribute__value"><?php echo esc_html($status); ?></dd>
                                    </div>
                                <?php endif; ?>

                            </dl>
                        </section>

                        <!-- Current Owner/Location -->
                        <?php
                        $owner_id = get_post_meta($post_id, '_saga_artifact_owner', true);
                        $location_id = get_post_meta($post_id, '_saga_artifact_location', true);

                        if (!empty($owner_id) || !empty($location_id)) :
                            ?>
                            <section class="saga-section saga-artifact__current-status">
                                <h2 class="saga-section__title"><?php esc_html_e('Current Status', 'saga-manager'); ?></h2>

                                <?php
                                if (!empty($owner_id)) :
                                    $owner = get_post($owner_id);
                                    if ($owner && $owner->post_status === 'publish') :
                                        ?>
                                        <div class="saga-artifact__owner">
                                            <h3><?php esc_html_e('Owner', 'saga-manager'); ?></h3>
                                            <a href="<?php echo esc_url(get_permalink($owner)); ?>" class="saga-artifact__owner-link">
                                                <?php if (has_post_thumbnail($owner)) : ?>
                                                    <div class="saga-artifact__owner-image">
                                                        <?php echo get_the_post_thumbnail($owner, 'thumbnail'); ?>
                                                    </div>
                                                <?php endif; ?>
                                                <span class="saga-artifact__owner-name"><?php echo esc_html($owner->post_title); ?></span>
                                            </a>
                                        </div>
                                    <?php
                                    endif;
                                endif;

                                if (!empty($location_id)) :
                                    $location = get_post($location_id);
                                    if ($location && $location->post_status === 'publish') :
                                        ?>
                                        <div class="saga-artifact__location">
                                            <h3><?php esc_html_e('Location', 'saga-manager'); ?></h3>
                                            <a href="<?php echo esc_url(get_permalink($location)); ?>" class="saga-artifact__location-link">
                                                <?php if (has_post_thumbnail($location)) : ?>
                                                    <div class="saga-artifact__location-image">
                                                        <?php echo get_the_post_thumbnail($location, 'thumbnail'); ?>
                                                    </div>
                                                <?php endif; ?>
                                                <span class="saga-artifact__location-name"><?php echo esc_html($location->post_title); ?></span>
                                            </a>
                                        </div>
                                    <?php
                                    endif;
                                endif;
                                ?>
                            </section>
                        <?php endif; ?>

                        <!-- Previous Owners -->
                        <?php
                        $previous_owners = saga_get_related_entities($post_id, 'owned');
                        if (!empty($previous_owners)) :
                            ?>
                            <section class="saga-section saga-artifact__previous-owners">
                                <h2 class="saga-section__title"><?php esc_html_e('Previous Owners', 'saga-manager'); ?></h2>
                                <ul class="saga-related-list">
                                    <?php foreach ($previous_owners as $prev_owner) : ?>
                                        <li class="saga-related-list__item">
                                            <a href="<?php echo esc_url(get_permalink($prev_owner->ID)); ?>" class="saga-related-list__link">
                                                <?php if (has_post_thumbnail($prev_owner->ID)) : ?>
                                                    <?php echo get_the_post_thumbnail($prev_owner->ID, 'thumbnail', ['class' => 'saga-related-list__image']); ?>
                                                <?php endif; ?>
                                                <span class="saga-related-list__title"><?php echo esc_html($prev_owner->post_title); ?></span>
                                            </a>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            </section>
                        <?php endif; ?>

                        <!-- Related Artifacts -->
                        <?php
                        $related_artifacts = new WP_Query([
                            'post_type' => 'saga_entity',
                            'posts_per_page' => 5,
                            'post__not_in' => [$post_id],
                            'tax_query' => [
                                [
                                    'taxonomy' => 'saga_type',
                                    'field' => 'slug',
                                    'terms' => 'artifact',
                                ],
                                [
                                    'taxonomy' => 'saga',
                                    'terms' => wp_get_post_terms($post_id, 'saga', ['fields' => 'ids']),
                                ],
                            ],
                        ]);

                        if ($related_artifacts->have_posts()) :
                            ?>
                            <section class="saga-section saga-artifact__related">
                                <h2 class="saga-section__title"><?php esc_html_e('Related Artifacts', 'saga-manager'); ?></h2>
                                <ul class="saga-related-list">
                                    <?php
                                    while ($related_artifacts->have_posts()) :
                                        $related_artifacts->the_post();
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
