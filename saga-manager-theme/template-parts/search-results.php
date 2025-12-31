<?php
/**
 * Search Results Template
 *
 * Displays search results with rich previews, filtering, and sorting.
 *
 * @package SagaManager
 * @since 1.3.0
 */

declare(strict_types=1);

// Get search query
$search_query = get_search_query();
$search_results = $args['results'] ?? [];
$total_results = $args['total'] ?? 0;
$query_time = $args['query_time'] ?? 0;
$grouped = $args['grouped'] ?? false;
$suggestions = $args['suggestions'] ?? [];

?>

<div class="saga-search-results-container">
    <?php if (!empty($suggestions)): ?>
        <!-- Search Suggestions -->
        <div class="saga-search-suggestions">
            <p><?php esc_html_e('Did you mean:', 'saga-manager'); ?></p>
            <?php foreach ($suggestions as $suggestion): ?>
                <a href="<?php echo esc_url(add_query_arg('s', $suggestion)); ?>"
                   class="saga-suggestion-link">
                    <?php echo esc_html($suggestion); ?>
                </a>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <?php if (empty($search_results)): ?>
        <!-- Empty State -->
        <div class="saga-search-empty">
            <svg class="saga-icon-search-empty" width="120" height="120" viewBox="0 0 120 120" fill="none" xmlns="http://www.w3.org/2000/svg">
                <circle cx="50" cy="50" r="30" stroke="currentColor" stroke-width="4" opacity="0.3"/>
                <path d="M72 72l18 18" stroke="currentColor" stroke-width="4" stroke-linecap="round" opacity="0.3"/>
                <path d="M40 50h20M50 40v20" stroke="currentColor" stroke-width="3" stroke-linecap="round" opacity="0.2"/>
            </svg>

            <h3><?php esc_html_e('No results found', 'saga-manager'); ?></h3>

            <?php if ($search_query): ?>
                <p>
                    <?php
                    printf(
                        /* translators: %s: search query */
                        esc_html__('Your search for %s didn\'t match any entities.', 'saga-manager'),
                        '<strong>' . esc_html($search_query) . '</strong>'
                    );
                    ?>
                </p>
            <?php endif; ?>

            <ul class="saga-search-tips">
                <li><?php esc_html_e('Try different keywords', 'saga-manager'); ?></li>
                <li><?php esc_html_e('Use more general terms', 'saga-manager'); ?></li>
                <li><?php esc_html_e('Check your spelling', 'saga-manager'); ?></li>
                <li><?php esc_html_e('Remove filters to expand results', 'saga-manager'); ?></li>
            </ul>
        </div>

    <?php else: ?>
        <!-- Results Header -->
        <div class="saga-search-results-header">
            <div class="saga-results-info">
                <h2 class="saga-results-count">
                    <?php
                    printf(
                        /* translators: 1: number of results, 2: search query */
                        _n(
                            '%1$s result for "%2$s"',
                            '%1$s results for "%2$s"',
                            $total_results,
                            'saga-manager'
                        ),
                        '<span class="saga-count">' . number_format_i18n($total_results) . '</span>',
                        '<span class="saga-query">' . esc_html($search_query) . '</span>'
                    );
                    ?>
                </h2>

                <?php if ($query_time > 0): ?>
                    <span class="saga-query-time">
                        <?php
                        printf(
                            /* translators: %s: query time in milliseconds */
                            esc_html__('(%s ms)', 'saga-manager'),
                            number_format($query_time, 2)
                        );
                        ?>
                    </span>
                <?php endif; ?>
            </div>

            <div class="saga-results-actions">
                <button type="button" class="saga-toggle-view-btn" data-view="grid" aria-label="<?php esc_attr_e('Toggle grid view', 'saga-manager'); ?>">
                    <svg width="20" height="20" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <rect x="2" y="2" width="7" height="7" stroke="currentColor" stroke-width="2"/>
                        <rect x="11" y="2" width="7" height="7" stroke="currentColor" stroke-width="2"/>
                        <rect x="2" y="11" width="7" height="7" stroke="currentColor" stroke-width="2"/>
                        <rect x="11" y="11" width="7" height="7" stroke="currentColor" stroke-width="2"/>
                    </svg>
                </button>

                <button type="button" class="saga-export-results-btn" aria-label="<?php esc_attr_e('Export results', 'saga-manager'); ?>">
                    <svg width="20" height="20" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path d="M10 2v10M10 2l-4 4M10 2l4 4" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                        <path d="M3 12v4a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2v-4" stroke="currentColor" stroke-width="2"/>
                    </svg>
                </button>
            </div>
        </div>

        <!-- Results List -->
        <?php if ($grouped): ?>
            <?php foreach ($search_results as $type => $items): ?>
                <div class="saga-results-group saga-results-group-<?php echo esc_attr($type); ?>">
                    <h3 class="saga-results-group-title">
                        <?php echo esc_html(ucfirst($type)); ?>
                        <span class="saga-group-count">(<?php echo count($items); ?>)</span>
                    </h3>
                    <?php get_template_part('template-parts/search-results-list', null, ['results' => $items, 'query' => $search_query]); ?>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <?php get_template_part('template-parts/search-results-list', null, ['results' => $search_results, 'query' => $search_query]); ?>
        <?php endif; ?>

        <!-- Load More Button -->
        <?php if ($total_results > count($search_results)): ?>
            <div class="saga-load-more-container">
                <button type="button" class="saga-load-more-btn">
                    <span><?php esc_html_e('Load More Results', 'saga-manager'); ?></span>
                    <svg width="16" height="16" viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path d="M8 2v12M8 14l-4-4M8 14l4-4" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                    </svg>
                </button>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</div>

<?php if (!empty($search_results)): ?>
<script>
jQuery(document).ready(function($) {
    // Toggle view (grid/list)
    $('.saga-toggle-view-btn').on('click', function() {
        const $list = $('.saga-search-results-list');
        const currentView = $list.hasClass('saga-grid-view') ? 'grid' : 'list';
        const newView = currentView === 'grid' ? 'list' : 'grid';

        $list.toggleClass('saga-grid-view');
        $(this).data('view', newView);

        // Save preference
        localStorage.setItem('saga_search_view', newView);
    });

    // Restore view preference
    const savedView = localStorage.getItem('saga_search_view');
    if (savedView === 'grid') {
        $('.saga-search-results-list').addClass('saga-grid-view');
    }

    // Export results
    $('.saga-export-results-btn').on('click', function() {
        const results = [];
        $('.saga-search-result-item').each(function() {
            results.push({
                title: $(this).find('.saga-result-title').text().trim(),
                type: $(this).data('type'),
                url: $(this).find('.saga-result-title a').attr('href'),
                snippet: $(this).find('.saga-result-snippet').text().trim()
            });
        });

        // Create CSV
        let csv = 'Title,Type,URL,Snippet\n';
        results.forEach(function(result) {
            csv += `"${result.title}","${result.type}","${result.url}","${result.snippet}"\n`;
        });

        // Download
        const blob = new Blob([csv], { type: 'text/csv' });
        const url = window.URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = 'saga-search-results.csv';
        a.click();
        window.URL.revokeObjectURL(url);
    });
});
</script>
<?php endif; ?>
