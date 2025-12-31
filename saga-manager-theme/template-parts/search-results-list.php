<?php
/**
 * Search Results List
 *
 * Renders individual search result items with rich previews.
 *
 * @package SagaManager
 * @since 1.3.0
 */

declare(strict_types=1);

$results = $args['results'] ?? [];
$query = $args['query'] ?? '';

if (empty($results)) {
    return;
}

/**
 * Helper function to highlight search terms
 */
function saga_highlight_text($text, $query) {
    if (empty($query)) {
        return esc_html($text);
    }

    $terms = array_filter(explode(' ', $query));
    $highlighted = esc_html($text);

    foreach ($terms as $term) {
        $pattern = '/(' . preg_quote($term, '/') . ')/i';
        $highlighted = preg_replace($pattern, '<mark>$1</mark>', $highlighted);
    }

    return $highlighted;
}

/**
 * Render importance stars
 */
function saga_render_importance_stars($score) {
    $stars = round($score / 20); // 0-100 to 0-5 stars
    $output = '<span class="saga-importance-indicator" aria-label="Importance: ' . esc_attr($score) . '/100">';

    for ($i = 0; $i < 5; $i++) {
        $class = $i < $stars ? 'filled' : 'empty';
        $output .= '<i class="saga-star ' . $class . '"></i>';
    }

    $output .= '</span>';
    return $output;
}
?>

<div class="saga-search-results-list">
    <?php foreach ($results as $result): ?>
        <?php
        $entity_id = $result['id'] ?? 0;
        $entity_type = $result['entity_type'] ?? 'concept';
        $title = $result['title'] ?? $result['canonical_name'] ?? '';
        $url = $result['url'] ?? '#';
        $snippet = $result['snippet'] ?? '';
        $saga_name = $result['saga_name'] ?? '';
        $importance = $result['importance_score'] ?? 50;
        $relevance = $result['relevance_score'] ?? 0;
        $thumbnail = $result['thumbnail'] ?? '';
        ?>

        <article class="saga-search-result-item saga-entity-type-<?php echo esc_attr($entity_type); ?>"
                 data-entity-id="<?php echo esc_attr($entity_id); ?>"
                 data-type="<?php echo esc_attr($entity_type); ?>"
                 data-score="<?php echo esc_attr($relevance); ?>"
                 tabindex="0">

            <!-- Entity Icon -->
            <div class="saga-result-icon">
                <?php
                $icons = [
                    'character' => '<svg width="24" height="24" viewBox="0 0 24 24" fill="none"><circle cx="12" cy="8" r="4" stroke="currentColor" stroke-width="2"/><path d="M6 21v-2a4 4 0 0 1 4-4h4a4 4 0 0 1 4 4v2" stroke="currentColor" stroke-width="2"/></svg>',
                    'location' => '<svg width="24" height="24" viewBox="0 0 24 24" fill="none"><path d="M12 21s-8-7-8-13a8 8 0 0 1 16 0c0 6-8 13-8 13z" stroke="currentColor" stroke-width="2"/><circle cx="12" cy="8" r="3" stroke="currentColor" stroke-width="2"/></svg>',
                    'event' => '<svg width="24" height="24" viewBox="0 0 24 24" fill="none"><rect x="3" y="4" width="18" height="18" rx="2" stroke="currentColor" stroke-width="2"/><path d="M3 10h18M8 2v4M16 2v4" stroke="currentColor" stroke-width="2"/></svg>',
                    'faction' => '<svg width="24" height="24" viewBox="0 0 24 24" fill="none"><circle cx="9" cy="7" r="4" stroke="currentColor" stroke-width="2"/><circle cx="16" cy="15" r="4" stroke="currentColor" stroke-width="2"/><path d="M13 11l-4 4" stroke="currentColor" stroke-width="2"/></svg>',
                    'artifact' => '<svg width="24" height="24" viewBox="0 0 24 24" fill="none"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z" stroke="currentColor" stroke-width="2"/><path d="M9 22V12h6v10" stroke="currentColor" stroke-width="2"/></svg>',
                    'concept' => '<svg width="24" height="24" viewBox="0 0 24 24" fill="none"><path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20" stroke="currentColor" stroke-width="2"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z" stroke="currentColor" stroke-width="2"/></svg>',
                ];

                echo $icons[$entity_type] ?? $icons['concept'];
                ?>
            </div>

            <!-- Thumbnail (if available) -->
            <?php if ($thumbnail): ?>
                <div class="saga-result-thumbnail">
                    <img src="<?php echo esc_url($thumbnail); ?>"
                         alt="<?php echo esc_attr($title); ?>"
                         loading="lazy">
                </div>
            <?php endif; ?>

            <!-- Content -->
            <div class="saga-result-content">
                <!-- Title -->
                <h4 class="saga-result-title">
                    <a href="<?php echo esc_url($url); ?>">
                        <?php echo saga_highlight_text($title, $query); ?>
                    </a>
                </h4>

                <!-- Meta Information -->
                <div class="saga-result-meta">
                    <span class="saga-result-type">
                        <svg width="14" height="14" viewBox="0 0 14 14" fill="none">
                            <circle cx="7" cy="7" r="6" stroke="currentColor"/>
                        </svg>
                        <?php echo esc_html(ucfirst($entity_type)); ?>
                    </span>

                    <?php if ($saga_name): ?>
                        <span class="saga-result-saga">
                            <svg width="14" height="14" viewBox="0 0 14 14" fill="none">
                                <rect x="2" y="2" width="10" height="10" rx="1" stroke="currentColor"/>
                            </svg>
                            <?php echo esc_html($saga_name); ?>
                        </span>
                    <?php endif; ?>

                    <span class="saga-result-importance">
                        <?php echo saga_render_importance_stars($importance); ?>
                    </span>

                    <?php if (defined('WP_DEBUG') && WP_DEBUG && $relevance > 0): ?>
                        <span class="saga-result-score">
                            <svg width="14" height="14" viewBox="0 0 14 14" fill="none">
                                <path d="M7 1l2 5h5l-4 3 2 5-5-3-5 3 2-5-4-3h5z" stroke="currentColor"/>
                            </svg>
                            <?php echo number_format($relevance, 2); ?>
                        </span>
                    <?php endif; ?>
                </div>

                <!-- Snippet -->
                <?php if ($snippet): ?>
                    <p class="saga-result-snippet">
                        <?php echo saga_highlight_text($snippet, $query); ?>
                    </p>
                <?php endif; ?>

                <!-- Actions -->
                <div class="saga-result-actions">
                    <a href="<?php echo esc_url($url); ?>"
                       class="saga-result-link">
                        <?php esc_html_e('View Details', 'saga-manager'); ?>
                        <svg width="12" height="12" viewBox="0 0 12 12" fill="none">
                            <path d="M2 6h8M10 6l-3-3M10 6l-3 3" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                        </svg>
                    </a>

                    <?php if (current_user_can('edit_posts')): ?>
                        <a href="<?php echo esc_url(get_edit_post_link($result['wp_post_id'] ?? 0)); ?>"
                           class="saga-result-edit">
                            <svg width="12" height="12" viewBox="0 0 12 12" fill="none">
                                <path d="M1 11h10M6 2l4 4-5 5H1V7z" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
                            </svg>
                            <?php esc_html_e('Edit', 'saga-manager'); ?>
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </article>

    <?php endforeach; ?>
</div>
