<?php
/**
 * Template Partial: Timeline Event
 *
 * @package SagaManagerDisplay
 * @var array $event Event data
 * @var array $options Display options
 */

defined('ABSPATH') || exit;

$event_id = $event['id'] ?? '';
$canon_date = $event['canon_date'] ?? '';
$title = $event['title'] ?? '';
$description = $event['description'] ?? '';
$participants = $event['participants'] ?? [];
$locations = $event['locations'] ?? [];
$is_major = ($event['importance'] ?? 50) > 75;
?>

<div
    class="saga-timeline__event <?php echo $is_major ? 'saga-timeline__event--major' : ''; ?>"
    data-event-id="<?php echo esc_attr($event_id); ?>"
>
    <div class="saga-timeline__marker"></div>

    <div class="saga-timeline__content">
        <time class="saga-timeline__date">
            <?php echo esc_html($canon_date); ?>
        </time>

        <h4 class="saga-timeline__title">
            <?php echo esc_html($title); ?>
        </h4>

        <?php if ($options['show_descriptions'] && $description): ?>
            <p class="saga-timeline__description">
                <?php echo esc_html($description); ?>
            </p>
        <?php endif; ?>

        <?php if ($options['show_participants'] && !empty($participants)): ?>
            <div class="saga-timeline__participants">
                <span class="saga-timeline__participants-label">
                    <?php esc_html_e('Participants:', 'saga-manager-display'); ?>
                </span>
                <?php foreach ($participants as $participant): ?>
                    <?php
                    $name = is_array($participant) ? ($participant['canonical_name'] ?? '') : $participant;
                    $type = is_array($participant) ? ($participant['entity_type'] ?? 'character') : 'character';
                    ?>
                    <span class="saga-badge saga-badge--<?php echo esc_attr($type); ?>">
                        <?php echo esc_html($name); ?>
                    </span>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <?php if ($options['show_locations'] && !empty($locations)): ?>
            <div class="saga-timeline__locations">
                <span class="saga-timeline__locations-label">
                    <?php esc_html_e('Locations:', 'saga-manager-display'); ?>
                </span>
                <?php foreach ($locations as $location): ?>
                    <?php
                    $name = is_array($location) ? ($location['canonical_name'] ?? '') : $location;
                    ?>
                    <span class="saga-badge saga-badge--location">
                        <?php echo esc_html($name); ?>
                    </span>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>
