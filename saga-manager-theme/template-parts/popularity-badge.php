<?php
declare(strict_types=1);

/**
 * Template Part: Popularity Badge
 *
 * @package Saga_Manager_Theme
 * @var int $entity_id Entity post ID
 */

if (!isset($entity_id)) {
    $entity_id = get_the_ID();
}

$badge_type = Saga_Popularity::get_badge_type($entity_id);
$formatted_views = Saga_Popularity::get_formatted_views($entity_id);
$stats = Saga_Analytics::get_entity_stats($entity_id);

if (!$badge_type && (!$stats || (int) $stats['total_views'] === 0)) {
    return; // Don't show anything if no stats
}
?>

<div class="saga-popularity-indicators" data-entity-id="<?php echo esc_attr($entity_id); ?>">
    <?php if ($badge_type) : ?>
        <span class="popularity-badge popularity-badge--<?php echo esc_attr($badge_type); ?>">
            <?php if ($badge_type === 'trending') : ?>
                <svg class="badge-icon" xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M8.5 14.5A2.5 2.5 0 0 0 11 12c0-1.38-.5-2-1-3-1.072-2.143-.224-4.054 2-6 .5 2.5 2 4.9 4 6.5 2 1.6 3 3.5 3 5.5a7 7 0 1 1-14 0c0-1.153.433-2.294 1-3a2.5 2.5 0 0 0 2.5 2.5z"/>
                </svg>
                <span class="badge-label">Trending</span>
            <?php elseif ($badge_type === 'popular') : ?>
                <svg class="badge-icon" xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/>
                </svg>
                <span class="badge-label">Popular</span>
            <?php elseif ($badge_type === 'rising') : ?>
                <svg class="badge-icon" xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <polyline points="23 6 13.5 15.5 8.5 10.5 1 18"/>
                    <polyline points="17 6 23 6 23 12"/>
                </svg>
                <span class="badge-label">Rising</span>
            <?php endif; ?>
        </span>
    <?php endif; ?>

    <?php if ($stats && (int) $stats['total_views'] > 0) : ?>
        <span class="popularity-indicator popularity-indicator--views">
            <svg class="indicator-icon" xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/>
                <circle cx="12" cy="12" r="3"/>
            </svg>
            <span class="indicator-value"><?php echo esc_html($formatted_views); ?></span>
            <span class="indicator-label">views</span>
        </span>
    <?php endif; ?>

    <?php if ($stats && (int) $stats['bookmark_count'] > 0) : ?>
        <span class="popularity-indicator popularity-indicator--bookmarks">
            <svg class="indicator-icon" xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="m19 21-7-5-7 5V5a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2v16z"/>
            </svg>
            <span class="indicator-value"><?php echo esc_html($stats['bookmark_count']); ?></span>
        </span>
    <?php endif; ?>

    <?php if ($stats && (int) $stats['annotation_count'] > 0) : ?>
        <span class="popularity-indicator popularity-indicator--annotations">
            <svg class="indicator-icon" xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/>
            </svg>
            <span class="indicator-value"><?php echo esc_html($stats['annotation_count']); ?></span>
        </span>
    <?php endif; ?>
</div>
