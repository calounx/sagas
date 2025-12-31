<?php
/**
 * Template for Faction Entity Type
 *
 * Displays faction-specific sections: ideology, leadership, members, territories
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
    saga_output_schema_markup($post_id, 'faction');
    saga_output_og_tags($post_id, 'faction');
    ?>

    <article id="post-<?php echo $post_id; ?>" <?php post_class('saga-entity saga-entity--faction'); ?>>

        <!-- Hero Section -->
        <header class="saga-entity__hero saga-faction__hero">
            <div class="container">
                <div class="saga-entity__hero-content">

                    <?php if (has_post_thumbnail()) : ?>
                        <div class="saga-faction__emblem">
                            <?php the_post_thumbnail('medium', [
                                'class' => 'saga-faction__emblem-image',
                                'alt' => get_the_title(),
                            ]); ?>
                        </div>
                    <?php endif; ?>

                    <div class="saga-entity__hero-text">
                        <div class="saga-entity__meta">
                            <span class="saga-entity__type"><?php esc_html_e('Faction', 'saga-manager'); ?></span>

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
                        $motto = get_post_meta($post_id, '_saga_faction_motto', true);
                        if (!empty($motto)) :
                            ?>
                            <p class="saga-faction__motto">
                                "<?php echo esc_html($motto); ?>"
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

                        <!-- Description & Ideology -->
                        <section class="saga-section saga-faction__description">
                            <h2 class="saga-section__title"><?php esc_html_e('Overview', 'saga-manager'); ?></h2>
                            <div class="saga-section__content">
                                <?php the_content(); ?>
                            </div>
                        </section>

                        <!-- Leadership Hierarchy -->
                        <?php
                        $leaders = saga_get_related_entities($post_id, 'leads');
                        if (!empty($leaders)) :
                            ?>
                            <section class="saga-section saga-faction__leadership">
                                <h2 class="saga-section__title"><?php esc_html_e('Leadership', 'saga-manager'); ?></h2>
                                <div class="saga-leadership">
                                    <?php foreach ($leaders as $leader) : ?>
                                        <div class="saga-leader">
                                            <a href="<?php echo esc_url(get_permalink($leader->ID)); ?>" class="saga-leader__link">
                                                <?php if (has_post_thumbnail($leader->ID)) : ?>
                                                    <div class="saga-leader__image">
                                                        <?php echo get_the_post_thumbnail($leader->ID, 'medium'); ?>
                                                    </div>
                                                <?php endif; ?>
                                                <div class="saga-leader__content">
                                                    <h3 class="saga-leader__name"><?php echo esc_html($leader->post_title); ?></h3>
                                                    <?php
                                                    $position = get_post_meta($leader->ID, '_saga_character_position', true);
                                                    if (!empty($position)) :
                                                        ?>
                                                        <span class="saga-leader__position"><?php echo esc_html($position); ?></span>
                                                    <?php endif; ?>
                                                    <?php if ($leader->post_excerpt) : ?>
                                                        <p class="saga-leader__bio"><?php echo esc_html($leader->post_excerpt); ?></p>
                                                    <?php endif; ?>
                                                </div>
                                            </a>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </section>
                        <?php endif; ?>

                        <!-- Members -->
                        <?php
                        $members = saga_get_related_entities($post_id, 'member_of');
                        if (!empty($members)) :
                            ?>
                            <section class="saga-section saga-faction__members">
                                <h2 class="saga-section__title"><?php esc_html_e('Notable Members', 'saga-manager'); ?></h2>
                                <div class="saga-members-grid">
                                    <?php foreach ($members as $member) : ?>
                                        <div class="saga-member-card">
                                            <a href="<?php echo esc_url(get_permalink($member->ID)); ?>" class="saga-member-card__link">
                                                <?php if (has_post_thumbnail($member->ID)) : ?>
                                                    <div class="saga-member-card__image">
                                                        <?php echo get_the_post_thumbnail($member->ID, 'thumbnail'); ?>
                                                    </div>
                                                <?php endif; ?>
                                                <h3 class="saga-member-card__name"><?php echo esc_html($member->post_title); ?></h3>
                                            </a>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </section>
                        <?php endif; ?>

                        <!-- Controlled Territories -->
                        <?php
                        $territories = saga_get_related_entities($post_id, 'controls');
                        if (!empty($territories)) :
                            ?>
                            <section class="saga-section saga-faction__territories">
                                <h2 class="saga-section__title"><?php esc_html_e('Controlled Territories', 'saga-manager'); ?></h2>
                                <div class="saga-territories">
                                    <?php foreach ($territories as $territory) : ?>
                                        <div class="saga-territory-card">
                                            <a href="<?php echo esc_url(get_permalink($territory->ID)); ?>" class="saga-territory-card__link">
                                                <?php if (has_post_thumbnail($territory->ID)) : ?>
                                                    <div class="saga-territory-card__image">
                                                        <?php echo get_the_post_thumbnail($territory->ID, 'medium'); ?>
                                                    </div>
                                                <?php endif; ?>
                                                <div class="saga-territory-card__content">
                                                    <h3 class="saga-territory-card__name"><?php echo esc_html($territory->post_title); ?></h3>
                                                    <?php if ($territory->post_excerpt) : ?>
                                                        <p class="saga-territory-card__excerpt"><?php echo esc_html($territory->post_excerpt); ?></p>
                                                    <?php endif; ?>
                                                </div>
                                            </a>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </section>
                        <?php endif; ?>

                        <!-- Historical Timeline -->
                        <?php
                        $timeline = saga_get_entity_timeline($post_id);
                        if (!empty($timeline)) :
                            get_template_part('template-parts/entity/event-timeline', null, [
                                'events' => $timeline,
                                'title' => __('Historical Timeline', 'saga-manager'),
                            ]);
                        endif;
                        ?>

                    </div>

                    <!-- Sidebar Column -->
                    <aside class="saga-entity__sidebar">

                        <!-- Faction Details -->
                        <section class="saga-section saga-faction__details">
                            <h2 class="saga-section__title"><?php esc_html_e('Faction Information', 'saga-manager'); ?></h2>
                            <dl class="saga-attributes">

                                <?php
                                $faction_type = get_post_meta($post_id, '_saga_faction_type', true);
                                if (!empty($faction_type)) :
                                    ?>
                                    <div class="saga-attribute">
                                        <dt class="saga-attribute__label"><?php esc_html_e('Type', 'saga-manager'); ?></dt>
                                        <dd class="saga-attribute__value"><?php echo esc_html($faction_type); ?></dd>
                                    </div>
                                <?php endif; ?>

                                <?php
                                $founded = get_post_meta($post_id, '_saga_faction_founded', true);
                                if (!empty($founded)) :
                                    ?>
                                    <div class="saga-attribute">
                                        <dt class="saga-attribute__label"><?php esc_html_e('Founded', 'saga-manager'); ?></dt>
                                        <dd class="saga-attribute__value"><?php echo esc_html($founded); ?></dd>
                                    </div>
                                <?php endif; ?>

                                <?php
                                $leader_title = get_post_meta($post_id, '_saga_faction_leader_title', true);
                                if (!empty($leader_title)) :
                                    ?>
                                    <div class="saga-attribute">
                                        <dt class="saga-attribute__label"><?php esc_html_e('Leader Title', 'saga-manager'); ?></dt>
                                        <dd class="saga-attribute__value"><?php echo esc_html($leader_title); ?></dd>
                                    </div>
                                <?php endif; ?>

                                <?php
                                $headquarters = get_post_meta($post_id, '_saga_faction_headquarters', true);
                                if (!empty($headquarters)) :
                                    ?>
                                    <div class="saga-attribute">
                                        <dt class="saga-attribute__label"><?php esc_html_e('Headquarters', 'saga-manager'); ?></dt>
                                        <dd class="saga-attribute__value"><?php echo esc_html($headquarters); ?></dd>
                                    </div>
                                <?php endif; ?>

                                <?php
                                $status = get_post_meta($post_id, '_saga_faction_status', true);
                                if (!empty($status)) :
                                    ?>
                                    <div class="saga-attribute">
                                        <dt class="saga-attribute__label"><?php esc_html_e('Status', 'saga-manager'); ?></dt>
                                        <dd class="saga-attribute__value"><?php echo esc_html($status); ?></dd>
                                    </div>
                                <?php endif; ?>

                            </dl>
                        </section>

                        <!-- Allies -->
                        <?php
                        $allies = saga_get_related_entities($post_id, 'allied_with');
                        if (!empty($allies)) :
                            ?>
                            <section class="saga-section saga-faction__allies">
                                <h2 class="saga-section__title"><?php esc_html_e('Allies', 'saga-manager'); ?></h2>
                                <ul class="saga-related-list">
                                    <?php foreach ($allies as $ally) : ?>
                                        <li class="saga-related-list__item">
                                            <a href="<?php echo esc_url(get_permalink($ally->ID)); ?>" class="saga-related-list__link">
                                                <?php if (has_post_thumbnail($ally->ID)) : ?>
                                                    <?php echo get_the_post_thumbnail($ally->ID, 'thumbnail', ['class' => 'saga-related-list__image']); ?>
                                                <?php endif; ?>
                                                <span class="saga-related-list__title"><?php echo esc_html($ally->post_title); ?></span>
                                            </a>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            </section>
                        <?php endif; ?>

                        <!-- Enemies -->
                        <?php
                        $enemies = saga_get_related_entities($post_id, 'enemy_of');
                        if (!empty($enemies)) :
                            ?>
                            <section class="saga-section saga-faction__enemies">
                                <h2 class="saga-section__title"><?php esc_html_e('Enemies', 'saga-manager'); ?></h2>
                                <ul class="saga-related-list">
                                    <?php foreach ($enemies as $enemy) : ?>
                                        <li class="saga-related-list__item">
                                            <a href="<?php echo esc_url(get_permalink($enemy->ID)); ?>" class="saga-related-list__link">
                                                <?php if (has_post_thumbnail($enemy->ID)) : ?>
                                                    <?php echo get_the_post_thumbnail($enemy->ID, 'thumbnail', ['class' => 'saga-related-list__image']); ?>
                                                <?php endif; ?>
                                                <span class="saga-related-list__title"><?php echo esc_html($enemy->post_title); ?></span>
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
