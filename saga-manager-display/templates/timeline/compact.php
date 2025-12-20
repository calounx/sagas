<?php
/**
 * Template: Timeline Compact
 *
 * @package SagaManagerDisplay
 * @var string $saga_slug Saga slug
 * @var array $events Timeline events
 * @var array $meta Response metadata
 * @var array $options Display options
 */

defined('ABSPATH') || exit;
?>

<div class="saga-timeline__list">
    <?php foreach ($events as $event): ?>
        <?php
        $event_id = $event['id'] ?? '';
        $canon_date = $event['canon_date'] ?? '';
        $title = $event['title'] ?? '';
        ?>
        <div
            class="saga-timeline__event"
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
            </div>
        </div>
    <?php endforeach; ?>
</div>
