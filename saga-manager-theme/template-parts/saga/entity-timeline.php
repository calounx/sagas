<?php
/**
 * Entity Timeline Template Part
 *
 * Display timeline events for an entity
 *
 * @package SagaManagerTheme
 * @since 1.0.0
 */

declare(strict_types=1);

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

if (!saga_get_option('saga_show_timeline', true)) {
    return;
}

$events = saga_get_entity_timeline_events(get_the_ID(), 15);

if (empty($events)) {
    return;
}
?>

<div class="saga-timeline">
    <h2 class="saga-timeline__title"><?php esc_html_e('Timeline Events', 'saga-manager-theme'); ?></h2>

    <div class="saga-timeline__events">
        <?php foreach ($events as $event) : ?>
            <div class="saga-timeline__event">
                <div class="saga-timeline__event-date">
                    <?php echo saga_format_canon_date($event->canon_date); ?>
                </div>

                <h3 class="saga-timeline__event-title">
                    <?php if ($event->event_entity_id) : ?>
                        <?php
                        $event_url = saga_get_entity_url_by_id((int) $event->event_entity_id);
                        if ($event_url) :
                        ?>
                            <a href="<?php echo esc_url($event_url); ?>">
                                <?php echo esc_html($event->title); ?>
                            </a>
                        <?php else : ?>
                            <?php echo esc_html($event->title); ?>
                        <?php endif; ?>
                    <?php else : ?>
                        <?php echo esc_html($event->title); ?>
                    <?php endif; ?>
                </h3>

                <?php if ($event->description) : ?>
                    <div class="saga-timeline__event-description">
                        <?php echo wp_kses_post($event->description); ?>
                    </div>
                <?php endif; ?>

                <?php
                // Display participants and locations
                $participants = json_decode($event->participants ?? '[]', true);
                $locations = json_decode($event->locations ?? '[]', true);

                if (!empty($participants) || !empty($locations)) :
                ?>
                    <div class="saga-timeline__event-meta">
                        <?php if (!empty($participants)) : ?>
                            <div class="saga-timeline__event-participants">
                                <strong><?php esc_html_e('Participants:', 'saga-manager-theme'); ?></strong>
                                <?php
                                $participant_links = [];
                                foreach ($participants as $participant_id) {
                                    $url = saga_get_entity_url_by_id((int) $participant_id);
                                    if ($url) {
                                        $participant_links[] = '<a href="' . esc_url($url) . '">Entity #' . esc_html($participant_id) . '</a>';
                                    }
                                }
                                echo implode(', ', $participant_links);
                                ?>
                            </div>
                        <?php endif; ?>

                        <?php if (!empty($locations)) : ?>
                            <div class="saga-timeline__event-locations">
                                <strong><?php esc_html_e('Locations:', 'saga-manager-theme'); ?></strong>
                                <?php
                                $location_links = [];
                                foreach ($locations as $location_id) {
                                    $url = saga_get_entity_url_by_id((int) $location_id);
                                    if ($url) {
                                        $location_links[] = '<a href="' . esc_url($url) . '">Entity #' . esc_html($location_id) . '</a>';
                                    }
                                }
                                echo implode(', ', $location_links);
                                ?>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    </div>
</div>
