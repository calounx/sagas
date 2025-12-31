<?php
/**
 * Template for Event Entity Type
 *
 * Displays event-specific sections: date, participants, location, consequences
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
    saga_output_schema_markup($post_id, 'event');
    saga_output_og_tags($post_id, 'event');
    ?>

    <article id="post-<?php echo $post_id; ?>" <?php post_class('saga-entity saga-entity--event'); ?>>

        <!-- Hero Section -->
        <header class="saga-entity__hero saga-event__hero">
            <div class="container">
                <div class="saga-entity__hero-content">

                    <div class="saga-entity__meta">
                        <span class="saga-entity__type"><?php esc_html_e('Event', 'saga-manager'); ?></span>

                        <?php
                        $sagas = get_the_terms($post_id, 'saga');
                        if ($sagas && !is_wp_error($sagas)) :
                            ?>
                            <span class="saga-entity__saga">
                                <?php echo esc_html($sagas[0]->name); ?>
                            </span>
                        <?php endif; ?>

                        <?php
                        // Display event date
                        $event_date = get_post_meta($post_id, '_saga_event_date', true);
                        if (!empty($event_date)) :
                            ?>
                            <time class="saga-event__date" datetime="<?php echo esc_attr($event_date); ?>">
                                <?php echo esc_html($event_date); ?>
                            </time>
                        <?php endif; ?>
                    </div>

                    <h1 class="saga-entity__title"><?php the_title(); ?></h1>

                    <?php if (has_excerpt()) : ?>
                        <div class="saga-entity__excerpt">
                            <?php the_excerpt(); ?>
                        </div>
                    <?php endif; ?>

                </div>

                <?php if (has_post_thumbnail()) : ?>
                    <div class="saga-event__featured-image">
                        <?php the_post_thumbnail('large', [
                            'class' => 'saga-event__image',
                            'alt' => get_the_title(),
                        ]); ?>
                    </div>
                <?php endif; ?>
            </div>
        </header>

        <!-- Main Content -->
        <div class="saga-entity__content">
            <div class="container">
                <div class="saga-entity__grid">

                    <!-- Primary Column -->
                    <div class="saga-entity__primary">

                        <!-- Event Description -->
                        <section class="saga-section saga-event__description">
                            <h2 class="saga-section__title"><?php esc_html_e('Event Details', 'saga-manager'); ?></h2>
                            <div class="saga-section__content">
                                <?php the_content(); ?>
                            </div>
                        </section>

                        <!-- Participants -->
                        <?php
                        $participants = saga_get_related_entities($post_id, 'participated_in');
                        if (!empty($participants)) :
                            ?>
                            <section class="saga-section saga-event__participants">
                                <h2 class="saga-section__title"><?php esc_html_e('Participants', 'saga-manager'); ?></h2>
                                <div class="saga-participants">
                                    <?php foreach ($participants as $participant) : ?>
                                        <div class="saga-participant">
                                            <a href="<?php echo esc_url(get_permalink($participant->ID)); ?>" class="saga-participant__link">
                                                <?php if (has_post_thumbnail($participant->ID)) : ?>
                                                    <div class="saga-participant__image">
                                                        <?php echo get_the_post_thumbnail($participant->ID, 'thumbnail'); ?>
                                                    </div>
                                                <?php endif; ?>
                                                <div class="saga-participant__content">
                                                    <h3 class="saga-participant__name"><?php echo esc_html($participant->post_title); ?></h3>
                                                    <?php if (!empty($participant->relationship_type)) : ?>
                                                        <span class="saga-participant__role">
                                                            <?php echo esc_html(ucfirst($participant->relationship_type)); ?>
                                                        </span>
                                                    <?php endif; ?>
                                                </div>
                                            </a>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </section>
                        <?php endif; ?>

                        <!-- Consequences/Outcomes -->
                        <?php
                        $consequences = get_post_meta($post_id, '_saga_event_consequences', true);
                        if (!empty($consequences)) :
                            ?>
                            <section class="saga-section saga-event__consequences">
                                <h2 class="saga-section__title"><?php esc_html_e('Consequences & Outcomes', 'saga-manager'); ?></h2>
                                <div class="saga-section__content">
                                    <?php echo wp_kses_post(wpautop($consequences)); ?>
                                </div>
                            </section>
                        <?php endif; ?>

                        <!-- Related Events -->
                        <?php
                        $related_events_before = saga_get_related_entities($post_id, 'precedes');
                        $related_events_after = saga_get_related_entities($post_id, 'follows');

                        if (!empty($related_events_before) || !empty($related_events_after)) :
                            ?>
                            <section class="saga-section saga-event__related">
                                <h2 class="saga-section__title"><?php esc_html_e('Related Events', 'saga-manager'); ?></h2>

                                <?php if (!empty($related_events_before)) : ?>
                                    <div class="saga-event__timeline-section">
                                        <h3><?php esc_html_e('Events Before', 'saga-manager'); ?></h3>
                                        <ul class="saga-related-list">
                                            <?php foreach ($related_events_before as $event) : ?>
                                                <li class="saga-related-list__item">
                                                    <a href="<?php echo esc_url(get_permalink($event->ID)); ?>" class="saga-related-list__link">
                                                        <?php echo esc_html($event->post_title); ?>
                                                    </a>
                                                </li>
                                            <?php endforeach; ?>
                                        </ul>
                                    </div>
                                <?php endif; ?>

                                <?php if (!empty($related_events_after)) : ?>
                                    <div class="saga-event__timeline-section">
                                        <h3><?php esc_html_e('Events After', 'saga-manager'); ?></h3>
                                        <ul class="saga-related-list">
                                            <?php foreach ($related_events_after as $event) : ?>
                                                <li class="saga-related-list__item">
                                                    <a href="<?php echo esc_url(get_permalink($event->ID)); ?>" class="saga-related-list__link">
                                                        <?php echo esc_html($event->post_title); ?>
                                                    </a>
                                                </li>
                                            <?php endforeach; ?>
                                        </ul>
                                    </div>
                                <?php endif; ?>
                            </section>
                        <?php endif; ?>

                    </div>

                    <!-- Sidebar Column -->
                    <aside class="saga-entity__sidebar">

                        <!-- Event Meta -->
                        <section class="saga-section saga-event__meta">
                            <h2 class="saga-section__title"><?php esc_html_e('Event Information', 'saga-manager'); ?></h2>
                            <dl class="saga-attributes">

                                <?php
                                $event_date = get_post_meta($post_id, '_saga_event_date', true);
                                if (!empty($event_date)) :
                                    ?>
                                    <div class="saga-attribute">
                                        <dt class="saga-attribute__label"><?php esc_html_e('Date', 'saga-manager'); ?></dt>
                                        <dd class="saga-attribute__value">
                                            <time datetime="<?php echo esc_attr($event_date); ?>">
                                                <?php echo esc_html($event_date); ?>
                                            </time>
                                        </dd>
                                    </div>
                                <?php endif; ?>

                                <?php
                                $duration = get_post_meta($post_id, '_saga_event_duration', true);
                                if (!empty($duration)) :
                                    ?>
                                    <div class="saga-attribute">
                                        <dt class="saga-attribute__label"><?php esc_html_e('Duration', 'saga-manager'); ?></dt>
                                        <dd class="saga-attribute__value"><?php echo esc_html($duration); ?></dd>
                                    </div>
                                <?php endif; ?>

                                <?php
                                $event_type = get_post_meta($post_id, '_saga_event_type', true);
                                if (!empty($event_type)) :
                                    ?>
                                    <div class="saga-attribute">
                                        <dt class="saga-attribute__label"><?php esc_html_e('Type', 'saga-manager'); ?></dt>
                                        <dd class="saga-attribute__value"><?php echo esc_html($event_type); ?></dd>
                                    </div>
                                <?php endif; ?>

                                <?php
                                $significance = get_post_meta($post_id, '_saga_event_significance', true);
                                if (!empty($significance)) :
                                    ?>
                                    <div class="saga-attribute">
                                        <dt class="saga-attribute__label"><?php esc_html_e('Significance', 'saga-manager'); ?></dt>
                                        <dd class="saga-attribute__value"><?php echo esc_html($significance); ?></dd>
                                    </div>
                                <?php endif; ?>

                            </dl>
                        </section>

                        <!-- Location -->
                        <?php
                        $location_id = get_post_meta($post_id, '_saga_event_location', true);
                        if (!empty($location_id)) :
                            $location = get_post($location_id);
                            if ($location && $location->post_status === 'publish') :
                                ?>
                                <section class="saga-section saga-event__location">
                                    <h2 class="saga-section__title"><?php esc_html_e('Location', 'saga-manager'); ?></h2>
                                    <div class="saga-location-card">
                                        <a href="<?php echo esc_url(get_permalink($location)); ?>" class="saga-location-card__link">
                                            <?php if (has_post_thumbnail($location)) : ?>
                                                <div class="saga-location-card__image">
                                                    <?php echo get_the_post_thumbnail($location, 'medium'); ?>
                                                </div>
                                            <?php endif; ?>
                                            <div class="saga-location-card__content">
                                                <h3 class="saga-location-card__title"><?php echo esc_html($location->post_title); ?></h3>
                                                <?php if ($location->post_excerpt) : ?>
                                                    <p class="saga-location-card__excerpt"><?php echo esc_html($location->post_excerpt); ?></p>
                                                <?php endif; ?>
                                            </div>
                                        </a>
                                    </div>
                                </section>
                            <?php
                            endif;
                        endif;
                        ?>

                        <!-- Timeline Position -->
                        <?php
                        get_template_part('template-parts/entity/event-timeline', null, [
                            'events' => saga_get_entity_timeline($post_id, 10),
                            'title' => __('Timeline Position', 'saga-manager'),
                            'current_event_id' => $post_id,
                        ]);
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
