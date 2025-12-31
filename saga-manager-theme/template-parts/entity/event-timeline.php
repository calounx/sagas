<?php
/**
 * Template Part: Event Timeline
 *
 * Displays a timeline of events for an entity
 *
 * @package SagaManager
 * @since 1.0.0
 *
 * @var array $args {
 *     Template arguments
 *     @type array  $events           Array of timeline event objects
 *     @type string $title            Section title (default: 'Timeline')
 *     @type int    $current_event_id Optional current event ID to highlight
 * }
 */

declare(strict_types=1);

// Extract arguments
$events = $args['events'] ?? [];
$title = $args['title'] ?? __('Timeline', 'saga-manager');
$current_event_id = $args['current_event_id'] ?? 0;

// Exit if no events
if (empty($events)) {
    return;
}
?>

<section class="saga-section saga-timeline">
    <h2 class="saga-section__title"><?php echo esc_html($title); ?></h2>

    <div class="saga-timeline__container">
        <ol class="saga-timeline__list">
            <?php foreach ($events as $event) : ?>
                <li class="saga-timeline__item <?php echo ($current_event_id === $event->id) ? 'saga-timeline__item--current' : ''; ?>">
                    <div class="saga-timeline__marker"></div>

                    <div class="saga-timeline__content">
                        <time class="saga-timeline__date" datetime="<?php echo esc_attr($event->canon_date); ?>">
                            <?php echo esc_html($event->canon_date); ?>
                        </time>

                        <h3 class="saga-timeline__title">
                            <?php if (!empty($event->event_entity_id)) : ?>
                                <a href="<?php echo esc_url(get_permalink($event->event_entity_id)); ?>" class="saga-timeline__link">
                                    <?php echo esc_html($event->title); ?>
                                </a>
                            <?php else : ?>
                                <?php echo esc_html($event->title); ?>
                            <?php endif; ?>
                        </h3>

                        <?php if (!empty($event->description)) : ?>
                            <p class="saga-timeline__description">
                                <?php echo esc_html(wp_trim_words($event->description, 25)); ?>
                            </p>
                        <?php endif; ?>

                        <?php
                        // Display participants if available
                        if (!empty($event->participants)) :
                            $participant_ids = json_decode($event->participants, true);

                            if (is_array($participant_ids) && !empty($participant_ids)) :
                                ?>
                                <div class="saga-timeline__participants">
                                    <span class="saga-timeline__participants-label">
                                        <?php esc_html_e('Participants:', 'saga-manager'); ?>
                                    </span>
                                    <?php
                                    $participant_links = [];

                                    foreach (array_slice($participant_ids, 0, 5) as $participant_id) :
                                        $participant = get_post($participant_id);

                                        if ($participant && $participant->post_status === 'publish') :
                                            $participant_links[] = sprintf(
                                                '<a href="%s" class="saga-timeline__participant-link">%s</a>',
                                                esc_url(get_permalink($participant)),
                                                esc_html($participant->post_title)
                                            );
                                        endif;
                                    endforeach;

                                    if (!empty($participant_links)) :
                                        echo implode(', ', $participant_links);

                                        if (count($participant_ids) > 5) :
                                            printf(
                                                ' ' . esc_html__('and %d more', 'saga-manager'),
                                                count($participant_ids) - 5
                                            );
                                        endif;
                                    endif;
                                    ?>
                                </div>
                            <?php
                            endif;
                        endif;
                        ?>

                        <?php
                        // Display locations if available
                        if (!empty($event->locations)) :
                            $location_ids = json_decode($event->locations, true);

                            if (is_array($location_ids) && !empty($location_ids)) :
                                ?>
                                <div class="saga-timeline__locations">
                                    <span class="saga-timeline__locations-label">
                                        <?php esc_html_e('Location:', 'saga-manager'); ?>
                                    </span>
                                    <?php
                                    $location_links = [];

                                    foreach (array_slice($location_ids, 0, 3) as $location_id) :
                                        $location = get_post($location_id);

                                        if ($location && $location->post_status === 'publish') :
                                            $location_links[] = sprintf(
                                                '<a href="%s" class="saga-timeline__location-link">%s</a>',
                                                esc_url(get_permalink($location)),
                                                esc_html($location->post_title)
                                            );
                                        endif;
                                    endforeach;

                                    if (!empty($location_links)) :
                                        echo implode(', ', $location_links);
                                    endif;
                                    ?>
                                </div>
                            <?php
                            endif;
                        endif;
                        ?>
                    </div>
                </li>
            <?php endforeach; ?>
        </ol>
    </div>
</section>
