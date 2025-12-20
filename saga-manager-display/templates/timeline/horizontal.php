<?php
/**
 * Template: Timeline Horizontal
 *
 * @package SagaManagerDisplay
 * @var string $saga_slug Saga slug
 * @var array $events Timeline events
 * @var array $meta Response metadata
 * @var array $options Display options
 */

defined('ABSPATH') || exit;
?>

<div class="saga-timeline__track">
    <?php foreach ($events as $event): ?>
        <?php
        $event_id = $event['id'] ?? '';
        $canon_date = $event['canon_date'] ?? '';
        $title = $event['title'] ?? '';
        $description = $event['description'] ?? '';
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

                <?php if ($options['show_descriptions'] && $description): ?>
                    <p class="saga-timeline__description saga-line-clamp-3">
                        <?php echo esc_html($description); ?>
                    </p>
                <?php endif; ?>
            </div>
        </div>
    <?php endforeach; ?>
</div>
