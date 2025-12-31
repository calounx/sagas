<?php
/**
 * Breadcrumb Navigation Template
 *
 * Displays breadcrumbs with session history "Back" button,
 * Schema.org markup, and full accessibility support.
 *
 * @package SagaManagerTheme
 * @since 1.0.0
 */

declare(strict_types=1);

use SagaManagerTheme\Breadcrumb\BreadcrumbGenerator;

// Generate breadcrumbs
$generator = new BreadcrumbGenerator();
$generator->generate();

// Don't display if empty or on front page
if (!$generator->should_display()) {
    return;
}

$items = $generator->get_items();
$schema = $generator->get_schema();

// Minimum items check (at least home + current)
if (count($items) < 2) {
    return;
}

?>

<nav class="saga-breadcrumbs" role="navigation" aria-label="<?php esc_attr_e('Breadcrumb', 'saga-manager-theme'); ?>">
    <div class="saga-breadcrumbs__container">

        <?php
        /**
         * Back button - populated by JavaScript if history exists
         */
        ?>
        <button
            type="button"
            class="saga-breadcrumbs__back"
            id="saga-breadcrumb-back"
            aria-label="<?php esc_attr_e('Go back to previous page', 'saga-manager-theme'); ?>"
            style="display: none;"
        >
            <svg class="saga-breadcrumbs__back-icon" width="16" height="16" viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                <path d="M10 12L6 8L10 4" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
            <span class="saga-breadcrumbs__back-text"><?php esc_html_e('Back', 'saga-manager-theme'); ?></span>
        </button>

        <span class="saga-breadcrumbs__separator saga-breadcrumbs__separator--back" aria-hidden="true" style="display: none;">|</span>

        <?php
        /**
         * Breadcrumb list
         */
        ?>
        <ol class="saga-breadcrumbs__list" itemscope itemtype="https://schema.org/BreadcrumbList">
            <?php
            $total_items = count($items);

            foreach ($items as $index => $item):
                $position = $index + 1;
                $is_last = ($position === $total_items);
                $is_mobile_hidden = ($position < $total_items - 1) && ($total_items > 3);

                // CSS classes
                $item_classes = ['saga-breadcrumbs__item'];
                if ($is_last) {
                    $item_classes[] = 'saga-breadcrumbs__item--current';
                }
                if ($is_mobile_hidden) {
                    $item_classes[] = 'saga-breadcrumbs__item--mobile-hidden';
                }
                ?>

                <li
                    class="<?php echo esc_attr(implode(' ', $item_classes)); ?>"
                    itemprop="itemListElement"
                    itemscope
                    itemtype="https://schema.org/ListItem"
                >
                    <?php if (!empty($item['url']) && !$is_last): ?>
                        <a
                            href="<?php echo esc_url($item['url']); ?>"
                            class="saga-breadcrumbs__link"
                            itemprop="item"
                        >
                            <span itemprop="name"><?php echo esc_html($item['name']); ?></span>
                        </a>
                    <?php else: ?>
                        <span class="saga-breadcrumbs__text" itemprop="name">
                            <?php echo esc_html($item['name']); ?>
                        </span>
                    <?php endif; ?>

                    <meta itemprop="position" content="<?php echo esc_attr((string) $position); ?>" />

                    <?php if (!$is_last): ?>
                        <span class="saga-breadcrumbs__separator" aria-hidden="true">
                            <svg width="16" height="16" viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <path d="M6 12L10 8L6 4" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                            </svg>
                        </span>
                    <?php endif; ?>
                </li>

                <?php
                // Show ellipsis on mobile after first item (if there are more than 3 items)
                if ($position === 1 && $total_items > 3):
                ?>
                    <li class="saga-breadcrumbs__item saga-breadcrumbs__item--ellipsis" aria-hidden="true">
                        <span class="saga-breadcrumbs__text">...</span>
                        <span class="saga-breadcrumbs__separator">
                            <svg width="16" height="16" viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <path d="M6 12L10 8L6 4" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                            </svg>
                        </span>
                    </li>
                <?php endif; ?>

            <?php endforeach; ?>
        </ol>
    </div>
</nav>

<?php
/**
 * Output Schema.org JSON-LD
 */
if (!empty($schema)):
?>
<script type="application/ld+json">
<?php echo wp_json_encode($schema, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT); ?>
</script>
<?php endif; ?>
