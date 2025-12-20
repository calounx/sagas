<?php
/**
 * Template Partial: Pagination
 *
 * @package SagaManagerDisplay
 * @var int $current_page Current page number
 * @var int $total_pages Total number of pages
 * @var string $base_url Base URL for pagination links
 */

defined('ABSPATH') || exit;

if ($total_pages <= 1) {
    return;
}

$range = 2;
$pages = [];

// Always show first page
$pages[] = 1;

// Add ellipsis if needed
if ($current_page > $range + 2) {
    $pages[] = '...';
}

// Pages around current
for ($i = max(2, $current_page - $range); $i <= min($total_pages - 1, $current_page + $range); $i++) {
    $pages[] = $i;
}

// Add ellipsis if needed
if ($current_page < $total_pages - $range - 1) {
    $pages[] = '...';
}

// Always show last page
if ($total_pages > 1) {
    $pages[] = $total_pages;
}
?>

<nav class="saga-pagination" aria-label="<?php esc_attr_e('Pagination', 'saga-manager-display'); ?>">
    <?php
    // Previous button
    $prev_url = add_query_arg('paged', max(1, $current_page - 1), $base_url);
    $prev_disabled = $current_page <= 1;
    ?>
    <a
        href="<?php echo esc_url($prev_url); ?>"
        class="saga-pagination__item saga-pagination__item--prev <?php echo $prev_disabled ? 'saga-pagination__item--disabled' : ''; ?>"
        <?php echo $prev_disabled ? 'aria-disabled="true" tabindex="-1"' : ''; ?>
    >
        <span aria-hidden="true">&laquo;</span>
        <span class="saga-sr-only"><?php esc_html_e('Previous page', 'saga-manager-display'); ?></span>
    </a>

    <?php foreach ($pages as $page): ?>
        <?php if ($page === '...'): ?>
            <span class="saga-pagination__ellipsis" aria-hidden="true">&hellip;</span>
        <?php else: ?>
            <?php
            $page_url = add_query_arg('paged', $page, $base_url);
            $is_current = $page === $current_page;
            ?>
            <a
                href="<?php echo esc_url($page_url); ?>"
                class="saga-pagination__item <?php echo $is_current ? 'saga-pagination__item--current' : ''; ?>"
                <?php echo $is_current ? 'aria-current="page"' : ''; ?>
            >
                <?php echo esc_html($page); ?>
            </a>
        <?php endif; ?>
    <?php endforeach; ?>

    <?php
    // Next button
    $next_url = add_query_arg('paged', min($total_pages, $current_page + 1), $base_url);
    $next_disabled = $current_page >= $total_pages;
    ?>
    <a
        href="<?php echo esc_url($next_url); ?>"
        class="saga-pagination__item saga-pagination__item--next <?php echo $next_disabled ? 'saga-pagination__item--disabled' : ''; ?>"
        <?php echo $next_disabled ? 'aria-disabled="true" tabindex="-1"' : ''; ?>
    >
        <span aria-hidden="true">&raquo;</span>
        <span class="saga-sr-only"><?php esc_html_e('Next page', 'saga-manager-display'); ?></span>
    </a>
</nav>
