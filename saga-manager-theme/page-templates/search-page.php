<?php
/**
 * Template Name: Saga Search Page
 *
 * Full-featured search page with semantic search, filters, and analytics.
 *
 * @package SagaManager
 * @since 1.3.0
 */

declare(strict_types=1);

get_header();

// Enqueue search assets
wp_enqueue_style('saga-semantic-search');
wp_enqueue_script('saga-semantic-search');
wp_enqueue_script('saga-search-autocomplete');

// Get search query
$search_query = get_search_query();
$search_active = !empty($search_query);

?>

<main id="primary" class="site-main saga-search-page">
    <div class="saga-page-container">

        <!-- Page Header -->
        <header class="saga-page-header">
            <h1 class="saga-page-title">
                <?php
                if ($search_active) {
                    esc_html_e('Search Results', 'saga-manager');
                } else {
                    esc_html_e('Search Saga Entities', 'saga-manager');
                }
                ?>
            </h1>

            <?php if ($search_active): ?>
                <p class="saga-page-description">
                    <?php
                    printf(
                        /* translators: %s: search query */
                        esc_html__('Results for: %s', 'saga-manager'),
                        '<strong>' . esc_html($search_query) . '</strong>'
                    );
                    ?>
                </p>
            <?php else: ?>
                <p class="saga-page-description">
                    <?php esc_html_e('Discover characters, locations, events, and more from your favorite sagas using natural language search.', 'saga-manager'); ?>
                </p>
            <?php endif; ?>
        </header>

        <!-- Search Tips (show when no query) -->
        <?php if (!$search_active): ?>
            <div class="saga-search-tips-panel">
                <h2><?php esc_html_e('Search Tips', 'saga-manager'); ?></h2>
                <div class="saga-tips-grid">
                    <div class="saga-tip-card">
                        <div class="saga-tip-icon">
                            <svg width="32" height="32" viewBox="0 0 32 32" fill="none">
                                <path d="M8 16h16M16 8v16" stroke="currentColor" stroke-width="3" stroke-linecap="round"/>
                            </svg>
                        </div>
                        <h3><?php esc_html_e('Natural Language', 'saga-manager'); ?></h3>
                        <p><?php esc_html_e('Ask questions like "Who fought in the Clone Wars?" or search by concept.', 'saga-manager'); ?></p>
                    </div>

                    <div class="saga-tip-card">
                        <div class="saga-tip-icon">
                            <svg width="32" height="32" viewBox="0 0 32 32" fill="none">
                                <path d="M4 8h24M4 16h16M4 24h20" stroke="currentColor" stroke-width="3" stroke-linecap="round"/>
                            </svg>
                        </div>
                        <h3><?php esc_html_e('Boolean Operators', 'saga-manager'); ?></h3>
                        <p><?php esc_html_e('Use AND, OR, NOT to combine terms. Use quotes for exact phrases.', 'saga-manager'); ?></p>
                    </div>

                    <div class="saga-tip-card">
                        <div class="saga-tip-icon">
                            <svg width="32" height="32" viewBox="0 0 32 32" fill="none">
                                <path d="M28 16A12 12 0 1 1 16 4" stroke="currentColor" stroke-width="3" stroke-linecap="round"/>
                                <path d="M16 4l4 4-4 4" stroke="currentColor" stroke-width="3" stroke-linecap="round"/>
                            </svg>
                        </div>
                        <h3><?php esc_html_e('Filters', 'saga-manager'); ?></h3>
                        <p><?php esc_html_e('Narrow results by entity type, importance score, or specific saga.', 'saga-manager'); ?></p>
                    </div>

                    <div class="saga-tip-card">
                        <div class="saga-tip-icon">
                            <svg width="32" height="32" viewBox="0 0 32 32" fill="none">
                                <rect x="8" y="4" width="16" height="24" rx="2" stroke="currentColor" stroke-width="3"/>
                                <path d="M12 12h8M12 18h8" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                            </svg>
                        </div>
                        <h3><?php esc_html_e('Voice Search', 'saga-manager'); ?></h3>
                        <p><?php esc_html_e('Use the microphone button to search using your voice.', 'saga-manager'); ?></p>
                    </div>
                </div>

                <!-- Example Searches -->
                <div class="saga-example-searches">
                    <h3><?php esc_html_e('Try these searches:', 'saga-manager'); ?></h3>
                    <div class="saga-example-buttons">
                        <a href="<?php echo esc_url(add_query_arg('s', 'jedi temple')); ?>"
                           class="saga-example-btn">
                            "Jedi Temple"
                        </a>
                        <a href="<?php echo esc_url(add_query_arg('s', 'battle AND clone')); ?>"
                           class="saga-example-btn">
                            battle AND clone
                        </a>
                        <a href="<?php echo esc_url(add_query_arg('s', 'ancient artifacts')); ?>"
                           class="saga-example-btn">
                            ancient artifacts
                        </a>
                        <a href="<?php echo esc_url(add_query_arg('s', 'dark side -sith')); ?>"
                           class="saga-example-btn">
                            dark side -sith
                        </a>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <!-- Search Form -->
        <div class="saga-search-section">
            <?php
            get_template_part('template-parts/search-form', null, [
                'placeholder' => __('Search saga entities...', 'saga-manager'),
                'show_filters' => true,
                'show_voice' => true,
                'show_results' => true,
                'show_saved_searches' => true,
                'max_results' => 50,
            ]);
            ?>
        </div>

        <!-- Search Results (PHP-rendered for SEO) -->
        <?php if ($search_active): ?>
            <?php
            // Perform server-side search for SEO
            global $wpdb;
            $table = $wpdb->prefix . 'saga_entities';

            $search_term = '%' . $wpdb->esc_like($search_query) . '%';
            $results = $wpdb->get_results($wpdb->prepare(
                "SELECT e.*, s.name as saga_name
                 FROM {$table} e
                 LEFT JOIN {$wpdb->prefix}saga_sagas s ON e.saga_id = s.id
                 WHERE e.canonical_name LIKE %s
                 ORDER BY e.importance_score DESC
                 LIMIT 50",
                $search_term
            ), ARRAY_A);

            // Enrich results
            foreach ($results as &$result) {
                if ($result['wp_post_id']) {
                    $post = get_post($result['wp_post_id']);
                    if ($post) {
                        $result['title'] = $post->post_title;
                        $result['url'] = get_permalink($post);
                        $result['snippet'] = wp_trim_words($post->post_content, 30);
                        $result['thumbnail'] = get_the_post_thumbnail_url($post, 'thumbnail');
                    }
                }
            }

            get_template_part('template-parts/search-results', null, [
                'results' => $results,
                'total' => count($results),
                'query_time' => 0,
                'grouped' => false,
                'suggestions' => [],
            ]);
            ?>
        <?php endif; ?>

        <!-- Popular Searches -->
        <?php if (!$search_active): ?>
            <?php
            $popular_searches = \SagaManager\Ajax\SearchHandler::get_popular_searches(10);
            if (!empty($popular_searches)):
            ?>
                <div class="saga-popular-searches">
                    <h2><?php esc_html_e('Popular Searches', 'saga-manager'); ?></h2>
                    <div class="saga-popular-list">
                        <?php foreach ($popular_searches as $popular): ?>
                            <a href="<?php echo esc_url(add_query_arg('s', $popular)); ?>"
                               class="saga-popular-item">
                                <svg width="16" height="16" viewBox="0 0 16 16" fill="none">
                                    <path d="M8 2v12M2 8h12" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                                </svg>
                                <?php echo esc_html($popular); ?>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>
        <?php endif; ?>

        <!-- Search Keyboard Shortcuts -->
        <div class="saga-keyboard-shortcuts" style="display: none;">
            <h3><?php esc_html_e('Keyboard Shortcuts', 'saga-manager'); ?></h3>
            <dl class="saga-shortcuts-list">
                <div class="saga-shortcut-item">
                    <dt><kbd>Ctrl</kbd> + <kbd>K</kbd></dt>
                    <dd><?php esc_html_e('Focus search', 'saga-manager'); ?></dd>
                </div>
                <div class="saga-shortcut-item">
                    <dt><kbd>/</kbd></dt>
                    <dd><?php esc_html_e('Focus search', 'saga-manager'); ?></dd>
                </div>
                <div class="saga-shortcut-item">
                    <dt><kbd>Esc</kbd></dt>
                    <dd><?php esc_html_e('Clear search', 'saga-manager'); ?></dd>
                </div>
                <div class="saga-shortcut-item">
                    <dt><kbd>↑</kbd> <kbd>↓</kbd></dt>
                    <dd><?php esc_html_e('Navigate results', 'saga-manager'); ?></dd>
                </div>
                <div class="saga-shortcut-item">
                    <dt><kbd>Enter</kbd></dt>
                    <dd><?php esc_html_e('Open result', 'saga-manager'); ?></dd>
                </div>
            </dl>
        </div>

        <!-- Help Button -->
        <button type="button"
                class="saga-search-help-btn"
                aria-label="<?php esc_attr_e('Show keyboard shortcuts', 'saga-manager'); ?>">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none">
                <circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="2"/>
                <path d="M9 9a3 3 0 0 1 6 0c0 2-3 3-3 3M12 17h.01" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
            </svg>
        </button>

    </div>
</main>

<style>
.saga-search-page {
    padding: 3rem 0;
}

.saga-page-container {
    max-width: 1200px;
    margin: 0 auto;
    padding: 0 2rem;
}

.saga-page-header {
    text-align: center;
    margin-bottom: 3rem;
}

.saga-page-title {
    font-size: 2.5rem;
    font-weight: 700;
    margin: 0 0 1rem;
    color: var(--color-text);
}

.saga-page-description {
    font-size: 1.125rem;
    color: var(--color-text-muted);
    max-width: 600px;
    margin: 0 auto;
}

.saga-search-tips-panel {
    background: var(--color-bg-secondary);
    border: 1px solid var(--color-border);
    border-radius: var(--border-radius-lg);
    padding: 2rem;
    margin-bottom: 3rem;
}

.saga-search-tips-panel h2 {
    font-size: 1.5rem;
    margin: 0 0 1.5rem;
    text-align: center;
}

.saga-tips-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 1.5rem;
    margin-bottom: 2rem;
}

.saga-tip-card {
    background: var(--color-bg-primary);
    border: 1px solid var(--color-border);
    border-radius: var(--border-radius-md);
    padding: 1.5rem;
    text-align: center;
}

.saga-tip-icon {
    width: 64px;
    height: 64px;
    margin: 0 auto 1rem;
    display: flex;
    align-items: center;
    justify-content: center;
    background: var(--color-primary-alpha);
    border-radius: 50%;
    color: var(--color-primary);
}

.saga-tip-card h3 {
    font-size: 1.125rem;
    margin: 0 0 0.5rem;
}

.saga-tip-card p {
    font-size: 0.95rem;
    color: var(--color-text-muted);
    margin: 0;
}

.saga-example-searches {
    text-align: center;
    padding-top: 2rem;
    border-top: 1px solid var(--color-border);
}

.saga-example-searches h3 {
    font-size: 1.125rem;
    margin: 0 0 1rem;
}

.saga-example-buttons {
    display: flex;
    flex-wrap: wrap;
    gap: 0.75rem;
    justify-content: center;
}

.saga-example-btn {
    padding: 0.5rem 1rem;
    background: var(--color-bg-primary);
    border: 1px solid var(--color-border);
    border-radius: var(--border-radius-sm);
    color: var(--color-text);
    text-decoration: none;
    font-family: 'Courier New', monospace;
    font-size: 0.9rem;
    transition: all 0.2s ease;
}

.saga-example-btn:hover {
    background: var(--color-primary);
    color: white;
    border-color: var(--color-primary);
}

.saga-search-section {
    margin-bottom: 3rem;
}

.saga-popular-searches {
    margin: 3rem 0;
}

.saga-popular-searches h2 {
    font-size: 1.5rem;
    margin: 0 0 1.5rem;
}

.saga-popular-list {
    display: flex;
    flex-wrap: wrap;
    gap: 0.75rem;
}

.saga-popular-item {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.5rem 1rem;
    background: var(--color-bg-secondary);
    border: 1px solid var(--color-border);
    border-radius: var(--border-radius-sm);
    color: var(--color-text);
    text-decoration: none;
    transition: all 0.2s ease;
}

.saga-popular-item:hover {
    background: var(--color-primary);
    color: white;
    border-color: var(--color-primary);
}

.saga-search-help-btn {
    position: fixed;
    bottom: 2rem;
    right: 2rem;
    width: 3rem;
    height: 3rem;
    background: var(--color-primary);
    color: white;
    border: none;
    border-radius: 50%;
    cursor: pointer;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
    transition: all 0.2s ease;
    z-index: 100;
}

.saga-search-help-btn:hover {
    transform: scale(1.1);
    box-shadow: 0 6px 16px rgba(0, 0, 0, 0.2);
}

.saga-keyboard-shortcuts {
    background: white;
    border: 1px solid var(--color-border);
    border-radius: var(--border-radius-md);
    padding: 1.5rem;
    box-shadow: 0 8px 24px rgba(0, 0, 0, 0.15);
}

.saga-shortcuts-list {
    display: grid;
    gap: 0.75rem;
    margin: 0;
}

.saga-shortcut-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.saga-shortcut-item dt {
    font-weight: 600;
}

.saga-shortcut-item kbd {
    display: inline-block;
    padding: 0.25rem 0.5rem;
    background: var(--color-bg-secondary);
    border: 1px solid var(--color-border);
    border-radius: var(--border-radius-sm);
    font-family: monospace;
    font-size: 0.875rem;
}

@media (max-width: 768px) {
    .saga-page-title {
        font-size: 2rem;
    }

    .saga-tips-grid {
        grid-template-columns: 1fr;
    }

    .saga-example-buttons {
        flex-direction: column;
    }

    .saga-search-help-btn {
        bottom: 1rem;
        right: 1rem;
    }
}
</style>

<script>
jQuery(document).ready(function($) {
    // Help button toggle
    $('.saga-search-help-btn').on('click', function() {
        const $shortcuts = $('.saga-keyboard-shortcuts');
        $shortcuts.fadeToggle(200);

        // Position near button
        const btnOffset = $(this).offset();
        $shortcuts.css({
            position: 'fixed',
            bottom: '5rem',
            right: '2rem',
            'z-index': 101
        });
    });

    // Close shortcuts on outside click
    $(document).on('click', function(e) {
        if (!$(e.target).closest('.saga-search-help-btn, .saga-keyboard-shortcuts').length) {
            $('.saga-keyboard-shortcuts').fadeOut(200);
        }
    });
});
</script>

<?php
get_footer();
