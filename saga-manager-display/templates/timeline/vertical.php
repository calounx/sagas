<?php
/**
 * Template: Timeline Vertical
 *
 * @package SagaManagerDisplay
 * @var string $saga_slug Saga slug
 * @var array $events Timeline events
 * @var array $meta Response metadata
 * @var bool $is_grouped Whether events are grouped
 * @var string $group_by Group by field
 * @var array $options Display options
 */

defined('ABSPATH') || exit;
?>

<div class="saga-timeline__track">
    <?php if ($is_grouped): ?>
        <?php foreach ($events as $group_key => $group): ?>
            <div class="saga-timeline__group">
                <div class="saga-timeline__group-label">
                    <?php echo esc_html($group['label']); ?>
                </div>
                <?php foreach ($group['events'] as $event): ?>
                    <?php include __DIR__ . '/partials/event.php'; ?>
                <?php endforeach; ?>
            </div>
        <?php endforeach; ?>
    <?php else: ?>
        <?php foreach ($events as $event): ?>
            <?php include __DIR__ . '/partials/event.php'; ?>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<?php if (!empty($meta['has_more'])): ?>
    <div class="saga-timeline__load-more-container">
        <button type="button" class="saga-button saga-timeline__load-more">
            <?php esc_html_e('Load more events', 'saga-manager-display'); ?>
        </button>
    </div>
<?php endif; ?>
