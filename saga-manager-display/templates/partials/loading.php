<?php
/**
 * Template Partial: Loading Placeholder
 *
 * @package SagaManagerDisplay
 * @var string $type Placeholder type (card, list, text)
 */

defined('ABSPATH') || exit;

$type = $type ?? 'card';
?>

<?php if ($type === 'card'): ?>
    <div class="saga-skeleton saga-skeleton--card" aria-hidden="true">
        <div class="saga-skeleton__image"></div>
        <div class="saga-skeleton__content">
            <div class="saga-skeleton saga-skeleton--text" style="width: 30%;"></div>
            <div class="saga-skeleton saga-skeleton--text" style="width: 80%;"></div>
            <div class="saga-skeleton saga-skeleton--text" style="width: 60%;"></div>
        </div>
    </div>
<?php elseif ($type === 'list'): ?>
    <div class="saga-skeleton saga-skeleton--list" aria-hidden="true">
        <?php for ($i = 0; $i < 5; $i++): ?>
            <div class="saga-skeleton__item">
                <div class="saga-skeleton saga-skeleton--avatar"></div>
                <div class="saga-skeleton saga-skeleton--text" style="width: 60%;"></div>
            </div>
        <?php endfor; ?>
    </div>
<?php else: ?>
    <div class="saga-skeleton saga-skeleton--text-block" aria-hidden="true">
        <div class="saga-skeleton saga-skeleton--text" style="width: 100%;"></div>
        <div class="saga-skeleton saga-skeleton--text" style="width: 90%;"></div>
        <div class="saga-skeleton saga-skeleton--text" style="width: 75%;"></div>
    </div>
<?php endif; ?>

<span class="saga-sr-only">
    <?php esc_html_e('Loading...', 'saga-manager-display'); ?>
</span>
