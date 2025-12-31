<?php
/**
 * Saga Manager - GeneratePress Child Theme
 *
 * Enterprise-grade PHP architecture with SOLID principles
 * Integrates with Saga Manager plugin for fictional universe management
 *
 * @package SagaTheme
 * @version 1.0.0
 */

declare(strict_types=1);

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Theme constants
 */
define('SAGA_THEME_VERSION', '1.0.0');
define('SAGA_THEME_DIR', get_stylesheet_directory());
define('SAGA_THEME_URI', get_stylesheet_directory_uri());

/**
 * PSR-4 autoloader
 */
require_once SAGA_THEME_DIR . '/inc/autoload.php';

/**
 * Load breadcrumb generator
 */
require_once SAGA_THEME_DIR . '/inc/breadcrumb-generator.php';

/**
 * Load collections manager
 */
require_once SAGA_THEME_DIR . '/inc/class-saga-collections.php';

/**
 * Load annotations manager
 */
require_once SAGA_THEME_DIR . '/inc/class-saga-annotations.php';

/**
 * Load template loader for entity type-specific templates
 */
require_once SAGA_THEME_DIR . '/inc/template-loader.php';

/**
 * Load AJAX preview endpoint
 */
require_once SAGA_THEME_DIR . '/inc/ajax-preview.php';

/**
 * Load collapsible sections helpers
 */
require_once SAGA_THEME_DIR . '/inc/collapsible-helpers.php';

/**
 * Load comparison helpers
 */
require_once SAGA_THEME_DIR . '/inc/comparison-helpers.php';

/**
 * Load AJAX load more endpoint for infinite scroll
 */
require_once SAGA_THEME_DIR . '/inc/ajax-load-more.php';

/**
 * Load reading mode helpers
 */
require_once SAGA_THEME_DIR . '/inc/reading-mode-helpers.php';

/**
 * Load command registry for keyboard shortcuts
 */
require_once SAGA_THEME_DIR . '/inc/command-registry.php';

/**
 * Load PWA headers and integration
 */
require_once SAGA_THEME_DIR . '/inc/pwa-headers.php';

/**
 * Bootstrap the Saga Manager theme
 *
 * Uses dependency injection for all services
 * Follows hexagonal architecture principles
 */
function saga_theme_bootstrap(): void
{
    // Check PHP version
    if (version_compare(PHP_VERSION, '8.2.0', '<')) {
        add_action('admin_notices', function () {
            echo '<div class="notice notice-error"><p>';
            echo '<strong>' . esc_html__('Saga Manager Theme:', 'saga-manager-theme') . '</strong> ';
            echo esc_html__('This theme requires PHP 8.2 or higher.', 'saga-manager-theme');
            echo '</p></div>';
        });
        return;
    }

    // Check if GeneratePress parent theme is active
    if (!function_exists('generate_get_defaults')) {
        add_action('admin_notices', function () {
            echo '<div class="notice notice-error"><p>';
            echo '<strong>' . esc_html__('Saga Manager Theme:', 'saga-manager-theme') . '</strong> ';
            echo esc_html__('This theme requires GeneratePress to be installed and activated.', 'saga-manager-theme');
            echo '</p></div>';
        });
        return;
    }

    // Initialize dependency injection container
    $cache = new \SagaTheme\SagaCache();
    $queries = new \SagaTheme\SagaQueries($cache);
    $helpers = new \SagaTheme\SagaHelpers($queries);
    $hooks = new \SagaTheme\SagaHooks($helpers, $queries);
    $ajaxHandler = new \SagaTheme\SagaAjaxHandler($queries, $helpers);

    // Initialize main theme class
    $sagaTheme = new \SagaTheme\SagaTheme(
        $helpers,
        $queries,
        $hooks,
        $ajaxHandler,
        $cache
    );

    // Initialize theme
    $sagaTheme->init();

    // Make theme instance globally accessible (if needed)
    $GLOBALS['saga_theme'] = $sagaTheme;
}

// Bootstrap on WordPress init
add_action('after_setup_theme', 'saga_theme_bootstrap');

/**
 * Helper function to get theme instance
 *
 * @return \SagaTheme\SagaTheme|null Theme instance or null if not initialized
 */
function saga_theme(): ?\SagaTheme\SagaTheme
{
    return $GLOBALS['saga_theme'] ?? null;
}

/**
 * Helper function to get saga entity by post ID
 *
 * @param int $postId WordPress post ID
 * @return object|null Entity object or null
 */
function saga_get_entity(int $postId): ?object
{
    $theme = saga_theme();

    if ($theme === null) {
        return null;
    }

    return $theme->getHelpers()->getEntityByPostId($postId);
}

/**
 * Helper function to get entity relationships
 *
 * @param int $entityId Entity ID
 * @param string $direction Relationship direction: 'outgoing', 'incoming', 'both'
 * @return array Array of relationship objects
 */
function saga_get_relationships(int $entityId, string $direction = 'both'): array
{
    $theme = saga_theme();

    if ($theme === null) {
        return [];
    }

    return $theme->getHelpers()->getEntityRelationships($entityId, $direction);
}

/**
 * Template function to display entity meta
 *
 * @param int|null $postId Post ID (default: current post)
 * @return void
 */
function saga_display_entity_meta(?int $postId = null): void
{
    if ($postId === null) {
        $postId = get_the_ID();
    }

    $entity = saga_get_entity($postId);

    if (!$entity) {
        return;
    }

    $theme = saga_theme();
    if ($theme === null) {
        return;
    }

    $helpers = $theme->getHelpers();

    ?>
    <div class="saga-entity-meta">
        <div class="saga-entity-meta__grid">
            <div class="saga-entity-meta__item">
                <span class="saga-entity-meta__label"><?php esc_html_e('Type', 'saga-manager-theme'); ?></span>
                <div class="saga-entity-meta__value">
                    <?php echo $helpers->getEntityTypeBadge($entity->entity_type); ?>
                </div>
            </div>

            <div class="saga-entity-meta__item">
                <span class="saga-entity-meta__label"><?php esc_html_e('Importance', 'saga-manager-theme'); ?></span>
                <div class="saga-entity-meta__value">
                    <?php echo $helpers->getImportanceScoreBar((int) $entity->importance_score); ?>
                </div>
            </div>
        </div>
    </div>
    <?php
}

/**
 * Template function to display entity relationships
 *
 * @param int $entityId Entity ID
 * @param string $direction Relationship direction
 * @return void
 */
function saga_display_relationships(int $entityId, string $direction = 'both'): void
{
    $relationships = saga_get_relationships($entityId, $direction);

    if (empty($relationships)) {
        return;
    }

    $theme = saga_theme();
    if ($theme === null) {
        return;
    }

    $helpers = $theme->getHelpers();
    $grouped = $helpers->groupRelationshipsByType($relationships);

    ?>
    <div class="saga-relationships">
        <h2 class="saga-relationships__title">
            <?php esc_html_e('Relationships', 'saga-manager-theme'); ?>
        </h2>

        <?php foreach ($grouped as $type => $rels): ?>
            <div class="saga-relationships__group">
                <h3 class="saga-relationships__group-title">
                    <?php echo esc_html($helpers->formatRelationshipType($type)); ?>
                </h3>

                <ul class="saga-relationships__list">
                    <?php foreach ($rels as $rel): ?>
                        <li class="saga-relationships__item">
                            <?php
                            $permalink = get_permalink((int) $rel->wp_post_id);
                            if ($permalink !== false):
                            ?>
                                <a href="<?php echo esc_url($permalink); ?>" class="saga-relationships__item-link">
                                    <?php echo esc_html($rel->canonical_name); ?>
                                </a>
                            <?php else: ?>
                                <span class="saga-relationships__item-link">
                                    <?php echo esc_html($rel->canonical_name); ?>
                                </span>
                            <?php endif; ?>

                            <?php echo $helpers->getRelationshipStrengthBadge((int) $rel->strength); ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endforeach; ?>
    </div>
    <?php
}

/**
 * Load textdomain for translations
 */
add_action('after_setup_theme', function () {
    load_theme_textdomain('saga-manager-theme', SAGA_THEME_DIR . '/languages');
});

/**
 * Enqueue dark mode assets
 *
 * Loads dark mode CSS and JavaScript with proper dependencies
 * and versioning for cache busting
 *
 * @return void
 */
function saga_enqueue_dark_mode_assets(): void
{
    // Enqueue dark mode CSS
    wp_enqueue_style(
        'saga-dark-mode',
        SAGA_THEME_URI . '/assets/css/dark-mode.css',
        [], // No dependencies - loads first for FOUC prevention
        SAGA_THEME_VERSION,
        'all'
    );

    // Enqueue dark mode JavaScript
    wp_enqueue_script(
        'saga-dark-mode',
        SAGA_THEME_URI . '/assets/js/dark-mode.js',
        [], // No dependencies - vanilla JS
        SAGA_THEME_VERSION,
        false // Load in header to prevent FOUC
    );

    // Add inline script to set debug flag if WP_DEBUG is enabled
    if (defined('WP_DEBUG') && WP_DEBUG) {
        wp_add_inline_script(
            'saga-dark-mode',
            'window.sagaDebug = true;',
            'before'
        );
    }

    // Enqueue print styles for entity templates
    wp_enqueue_style(
        'saga-print',
        SAGA_THEME_URI . '/assets/css/print.css',
        [],
        SAGA_THEME_VERSION,
        'print'
    );
}
add_action('wp_enqueue_scripts', 'saga_enqueue_dark_mode_assets', 5); // Priority 5 to load early

/**
 * Add dark mode toggle to GeneratePress navigation
 *
 * Integrates the toggle button into the main navigation area
 * Can be positioned via GeneratePress hooks
 *
 * @return void
 */
function saga_add_dark_mode_toggle_to_header(): void
{
    get_template_part('template-parts/header-dark-mode-toggle');
}

/**
 * Hook dark mode toggle into GeneratePress navigation
 *
 * Available positions:
 * - generate_inside_navigation (inside nav container)
 * - generate_after_header (after header)
 * - generate_before_header (before header)
 *
 * Default: generate_inside_navigation for better UX
 */
add_action('generate_inside_navigation', 'saga_add_dark_mode_toggle_to_header', 10);

/**
 * Add inline styles for dark mode toggle positioning
 *
 * Ensures proper alignment within GeneratePress navigation
 *
 * @return void
 */
function saga_dark_mode_toggle_inline_styles(): void
{
    $custom_css = "
        /* Dark mode toggle integration with GeneratePress */
        .inside-navigation {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .saga-dark-mode-toggle {
            margin-left: auto;
        }

        @media (max-width: 768px) {
            .saga-dark-mode-toggle {
                position: fixed;
                bottom: 1rem;
                right: 1rem;
                z-index: 1000;
                box-shadow: var(--shadow-xl);
            }
        }
    ";
    wp_add_inline_style('saga-dark-mode', $custom_css);
}
add_action('wp_enqueue_scripts', 'saga_dark_mode_toggle_inline_styles', 20);

/**
 * Enqueue masonry layout and infinite scroll assets
 *
 * @return void
 */
function saga_enqueue_masonry_assets(): void
{
    // Only load on entity archives and masonry-enabled pages
    if (!is_post_type_archive('saga_entity') && !is_tax('saga_type')) {
        return;
    }

    // Enqueue Masonry.js library from CDN
    wp_enqueue_script(
        'masonry-js',
        'https://unpkg.com/masonry-layout@4.2.2/dist/masonry.pkgd.min.js',
        [],
        '4.2.2',
        true
    );

    // Enqueue imagesLoaded library from CDN
    wp_enqueue_script(
        'imagesloaded',
        'https://unpkg.com/imagesloaded@5.0.0/imagesloaded.pkgd.min.js',
        [],
        '5.0.0',
        true
    );

    // Enqueue archive header CSS
    wp_enqueue_style(
        'saga-archive-header',
        SAGA_THEME_URI . '/assets/css/archive-header.css',
        [],
        SAGA_THEME_VERSION
    );

    // Enqueue masonry layout CSS
    wp_enqueue_style(
        'saga-masonry-layout',
        SAGA_THEME_URI . '/assets/css/masonry-layout.css',
        ['saga-archive-header'],
        SAGA_THEME_VERSION
    );

    // Enqueue elegant cards CSS
    wp_enqueue_style(
        'saga-elegant-cards',
        SAGA_THEME_URI . '/assets/css/elegant-cards.css',
        ['saga-masonry-layout'],
        SAGA_THEME_VERSION
    );

    // Enqueue masonry layout JavaScript
    wp_enqueue_script(
        'saga-masonry-layout',
        SAGA_THEME_URI . '/assets/js/masonry-layout.js',
        ['masonry-js', 'imagesloaded'],
        SAGA_THEME_VERSION,
        true
    );

    // Enqueue infinite scroll JavaScript
    wp_enqueue_script(
        'saga-infinite-scroll',
        SAGA_THEME_URI . '/assets/js/infinite-scroll.js',
        ['saga-masonry-layout'],
        SAGA_THEME_VERSION,
        true
    );

    // Localize script with AJAX data
    wp_localize_script(
        'saga-infinite-scroll',
        'sagaInfiniteScrollData',
        [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('saga_load_more_nonce'),
            'perPage' => 12,
            'threshold' => 300,
        ]
    );
}
add_action('wp_enqueue_scripts', 'saga_enqueue_masonry_assets', 15);

/**
 * Legacy compatibility - Keep old helper files for backward compatibility
 * These will delegate to the new OOP classes
 *
 * @deprecated Will be removed in v2.0.0
 * Theme setup
 * @return void
 */
function saga_theme_setup(): void
{
    // Add theme support for post thumbnails
    add_theme_support('post-thumbnails');

    // Set custom thumbnail sizes for entity cards
    add_image_size('saga-entity-card', 400, 300, true);
    add_image_size('saga-entity-thumbnail', 80, 80, true);

    // Load theme text domain for translations
    load_child_theme_textdomain(
        'saga-manager-theme',
        SAGA_THEME_DIR . '/languages'
    );
}
add_action('after_setup_theme', 'saga_theme_setup');

/**
 * Register widget areas for saga entities
 *
 * @return void
 */
function saga_theme_widgets_init(): void
{
    register_sidebar([
        'name' => __('Entity Sidebar', 'saga-manager-theme'),
        'id' => 'saga-entity-sidebar',
        'description' => __('Sidebar for single saga entity pages', 'saga-manager-theme'),
        'before_widget' => '<div id="%1$s" class="widget %2$s">',
        'after_widget' => '</div>',
        'before_title' => '<h3 class="widget-title">',
        'after_title' => '</h3>',
    ]);

    register_sidebar([
        'name' => __('Entity Archive Sidebar', 'saga-manager-theme'),
        'id' => 'saga-archive-sidebar',
        'description' => __('Sidebar for entity archive pages', 'saga-manager-theme'),
        'before_widget' => '<div id="%1$s" class="widget %2$s">',
        'after_widget' => '</div>',
        'before_title' => '<h3 class="widget-title">',
        'after_title' => '</h3>',
    ]);
}
add_action('widgets_init', 'saga_theme_widgets_init');

/**
 * AJAX handler for entity filtering
 *
 * @return void
 */
function saga_ajax_filter_entities(): void
{
    // Verify nonce
    check_ajax_referer('saga_filter_nonce', 'nonce');

    $saga_id = isset($_POST['saga_id']) ? absint($_POST['saga_id']) : 0;
    $entity_type = isset($_POST['entity_type']) ? sanitize_text_field($_POST['entity_type']) : '';
    $importance_min = isset($_POST['importance_min']) ? absint($_POST['importance_min']) : 0;
    $importance_max = isset($_POST['importance_max']) ? absint($_POST['importance_max']) : 100;
    $search = isset($_POST['search']) ? sanitize_text_field($_POST['search']) : '';

    // Build query args
    $args = [
        'post_type' => 'saga_entity',
        'posts_per_page' => 12,
        'paged' => isset($_POST['paged']) ? absint($_POST['paged']) : 1,
    ];

    // Add meta query for saga and importance
    $meta_query = ['relation' => 'AND'];

    if ($saga_id > 0) {
        $meta_query[] = [
            'key' => '_saga_id',
            'value' => $saga_id,
            'compare' => '=',
        ];
    }

    if ($importance_min > 0 || $importance_max < 100) {
        $meta_query[] = [
            'key' => '_saga_importance_score',
            'value' => [$importance_min, $importance_max],
            'type' => 'NUMERIC',
            'compare' => 'BETWEEN',
        ];
    }

    if (!empty($meta_query) && count($meta_query) > 1) {
        $args['meta_query'] = $meta_query;
    }

    // Add taxonomy filter
    if (!empty($entity_type)) {
        $args['tax_query'] = [
            [
                'taxonomy' => 'saga_type',
                'field' => 'slug',
                'terms' => $entity_type,
            ],
        ];
    }

    // Add search
    if (!empty($search)) {
        $args['s'] = $search;
    }

    $query = new WP_Query($args);

    if ($query->have_posts()) {
        ob_start();
        while ($query->have_posts()) {
            $query->the_post();
            get_template_part('template-parts/saga/entity', 'card');
        }
        $html = ob_get_clean();

        wp_send_json_success([
            'html' => $html,
            'found_posts' => $query->found_posts,
            'max_pages' => $query->max_num_pages,
        ]);
    } else {
        wp_send_json_success([
            'html' => '<div class="saga-empty-state">
                <div class="saga-empty-state__icon">&#128269;</div>
                <h3 class="saga-empty-state__title">' . esc_html__('No entities found', 'saga-manager-theme') . '</h3>
                <p class="saga-empty-state__description">' . esc_html__('Try adjusting your filters or search query.', 'saga-manager-theme') . '</p>
            </div>',
            'found_posts' => 0,
            'max_pages' => 0,
        ]);
    }

    wp_reset_postdata();
}
add_action('wp_ajax_saga_filter_entities', 'saga_ajax_filter_entities');
add_action('wp_ajax_nopriv_saga_filter_entities', 'saga_ajax_filter_entities');

/**
 * Add body classes for saga entity pages
 *
 * @param array $classes Existing body classes
 * @return array Modified body classes
 */
function saga_body_classes(array $classes): array
{
    if (is_singular('saga_entity')) {
        $classes[] = 'saga-entity-single';

        // Add entity type class
        $entity_type = saga_get_entity_type(get_the_ID());
        if ($entity_type) {
            $classes[] = 'saga-entity-type-' . sanitize_html_class($entity_type);
        }
    }

    if (is_post_type_archive('saga_entity')) {
        $classes[] = 'saga-entity-archive';
    }

    if (is_tax('saga_type')) {
        $classes[] = 'saga-entity-taxonomy';
    }

    return $classes;
}
add_filter('body_class', 'saga_body_classes');

/**
 * Modify main query for entity archives
 *
 * @param WP_Query $query The WordPress query object
 * @return void
 */
function saga_archive_query(WP_Query $query): void
{
    if (!is_admin() && $query->is_main_query() && is_post_type_archive('saga_entity')) {
        $query->set('posts_per_page', 12);

        // Sort by importance score by default
        $orderby = isset($_GET['orderby']) ? sanitize_text_field($_GET['orderby']) : 'importance';

        switch ($orderby) {
            case 'importance':
                $query->set('meta_key', '_saga_importance_score');
                $query->set('orderby', 'meta_value_num');
                $query->set('order', 'DESC');
                break;
            case 'title':
                $query->set('orderby', 'title');
                $query->set('order', 'ASC');
                break;
            case 'date':
                $query->set('orderby', 'date');
                $query->set('order', 'DESC');
                break;
        }
    }
}
add_action('pre_get_posts', 'saga_archive_query');

/**
 * Add custom excerpt length for entity cards
 *
 * @param int $length Default excerpt length
 * @return int Modified excerpt length
 */
function saga_excerpt_length(int $length): int
{
    if (is_post_type_archive('saga_entity') || is_tax('saga_type')) {
        return 20;
    }
    return $length;
}
add_filter('excerpt_length', 'saga_excerpt_length');

/**
 * Add custom excerpt more text
 *
 * @param string $more Default more text
 * @return string Modified more text
 */
function saga_excerpt_more(string $more): string
{
    if (is_post_type_archive('saga_entity') || is_tax('saga_type')) {
        return '&hellip;';
    }
    return $more;
}
add_filter('excerpt_more', 'saga_excerpt_more');

/**
 * Check if Saga Manager plugin is active
 *
 * @return bool
 */
function saga_plugin_is_active(): bool
{
    return function_exists('saga_manager_init') || class_exists('SagaManager\SagaManagerPlugin');
}

/**
 * Display admin notice if Saga Manager plugin is not active
 *
 * @return void
 */
function saga_admin_notice(): void
{
    if (!saga_plugin_is_active()) {
        echo '<div class="notice notice-warning is-dismissible">';
        echo '<p><strong>' . esc_html__('Saga Manager Theme:', 'saga-manager-theme') . '</strong> ';
        echo esc_html__('This theme requires the Saga Manager plugin to be installed and activated for full functionality.', 'saga-manager-theme');
        echo '</p></div>';
    }
}
add_action('admin_notices', 'saga_admin_notice');

/**
 * =============================================================================
 * Breadcrumb Navigation
 * =============================================================================
 */

/**
 * Enqueue breadcrumb assets
 *
 * @return void
 */
function saga_enqueue_breadcrumb_assets(): void
{
    // Enqueue breadcrumb CSS
    wp_enqueue_style(
        'saga-breadcrumbs',
        SAGA_THEME_URI . '/assets/css/breadcrumbs.css',
        [],
        SAGA_THEME_VERSION
    );

    // Enqueue breadcrumb history JavaScript
    wp_enqueue_script(
        'saga-breadcrumb-history',
        SAGA_THEME_URI . '/assets/js/breadcrumb-history.js',
        [],
        SAGA_THEME_VERSION,
        true
    );
}
add_action('wp_enqueue_scripts', 'saga_enqueue_breadcrumb_assets');

/**
 * Display breadcrumbs
 *
 * @return void
 */
function saga_display_breadcrumbs(): void
{
    get_template_part('template-parts/breadcrumbs');
}

/**
 * Hook breadcrumbs into GeneratePress theme
 *
 * Hook into generate_before_content or generate_after_header
 * depending on preference.
 *
 * @return void
 */
function saga_hook_breadcrumbs(): void
{
    // Hook breadcrumbs after header (before main content)
    add_action('generate_after_header', 'saga_display_breadcrumbs', 10);
}
add_action('after_setup_theme', 'saga_hook_breadcrumbs');

/**
 * Helper function to get entity type from post ID
 * Used in breadcrumb generation
 *
 * @param int $postId Post ID
 * @return string|null Entity type or null
 */
function saga_get_entity_type(int $postId): ?string
{
    $terms = get_the_terms($postId, 'saga_entity_type');

    if (!$terms || is_wp_error($terms)) {
        return null;
    }

    $term = array_shift($terms);
    return $term->slug ?? null;
}

/**
 * =============================================================================
 * Personal Collections / Bookmarks
 * =============================================================================
 */

/**
 * Initialize collections manager
 *
 * @return void
 */
function saga_init_collections(): void
{
    $collections = new Saga_Collections();
    $collections->init();
}
add_action('init', 'saga_init_collections');

/**
 * Enqueue collections assets
 *
 * @return void
 */
function saga_enqueue_collections_assets(): void
{
    // Enqueue collections CSS
    wp_enqueue_style(
        'saga-collections',
        SAGA_THEME_URI . '/assets/css/collections.css',
        [],
        SAGA_THEME_VERSION
    );

    // Enqueue collections JavaScript with jQuery dependency
    wp_enqueue_script(
        'saga-collections',
        SAGA_THEME_URI . '/assets/js/collections.js',
        ['jquery'],
        SAGA_THEME_VERSION,
        true
    );

    // Localize script with AJAX data
    wp_localize_script(
        'saga-collections',
        'sagaCollectionsData',
        [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('saga_collections'),
            'isLoggedIn' => is_user_logged_in(),
            'reloadOnCreate' => apply_filters('saga_collections_reload_on_create', false),
        ]
    );
}
add_action('wp_enqueue_scripts', 'saga_enqueue_collections_assets');

/**
 * AJAX handler for exporting collection
 * (Missing from class - added here for completeness)
 *
 * @return void
 */
function saga_ajax_export_collection(): void
{
    check_ajax_referer('saga_collections', 'nonce');

    $user_id = get_current_user_id();

    if ($user_id === 0) {
        wp_send_json_error([
            'message' => __('You must be logged in to export collections', 'saga-manager'),
        ], 401);
    }

    $collection_slug = sanitize_key($_POST['collection'] ?? '');

    if (empty($collection_slug)) {
        wp_send_json_error([
            'message' => __('Invalid collection', 'saga-manager'),
        ], 400);
    }

    $collections_manager = new Saga_Collections();
    $result = $collections_manager->export_collection($user_id, $collection_slug);

    if (is_wp_error($result)) {
        wp_send_json_error([
            'message' => $result->get_error_message(),
            'code' => $result->get_error_code(),
        ], 400);
    }

    wp_send_json_success($result);
}
add_action('wp_ajax_saga_export_collection', 'saga_ajax_export_collection');

/**
 * Helper function to display bookmark button
 *
 * @param int|null $entity_id Entity post ID (default: current post)
 * @param string   $collection Collection slug (default: 'favorites')
 * @param array    $args Additional arguments
 * @return void
 */
function saga_bookmark_button(?int $entity_id = null, string $collection = 'favorites', array $args = []): void
{
    if ($entity_id === null) {
        $entity_id = get_the_ID();
    }

    if (!$entity_id) {
        return;
    }

    $args['entity_id'] = $entity_id;
    $args['collection'] = $collection;

    get_template_part('template-parts/collection-button', null, $args);
}

/**
 * Helper function to check if entity is bookmarked
 *
 * @param int    $entity_id Entity post ID
 * @param string $collection Collection slug (default: 'favorites')
 * @return bool True if bookmarked
 */
function saga_is_bookmarked(int $entity_id, string $collection = 'favorites'): bool
{
    $user_id = get_current_user_id();

    if ($user_id === 0) {
        return false;
    }

    $collections_manager = new Saga_Collections();
    return $collections_manager->is_in_collection($user_id, $collection, $entity_id);
}

/**
 * Helper function to get user collections
 *
 * @param int $user_id User ID (0 for current user)
 * @return array Collections array
 */
function saga_get_user_collections(int $user_id = 0): array
{
    $collections_manager = new Saga_Collections();
    return $collections_manager->get_user_collections($user_id);
}

/**
 * =============================================================================
 * Entity Hover Previews
 * =============================================================================
 */

/**
 * Enqueue hover preview assets
 *
 * @return void
 */
function saga_enqueue_hover_preview_assets(): void
{
    // Only load on frontend, not admin
    if (is_admin()) {
        return;
    }

    // Enqueue hover preview CSS
    wp_enqueue_style(
        'saga-hover-preview',
        SAGA_THEME_URI . '/assets/css/hover-preview.css',
        [],
        SAGA_THEME_VERSION
    );

    // Enqueue hover preview JavaScript
    wp_enqueue_script(
        'saga-hover-preview',
        SAGA_THEME_URI . '/assets/js/hover-preview.js',
        [],
        SAGA_THEME_VERSION,
        true
    );
}
add_action('wp_enqueue_scripts', 'saga_enqueue_hover_preview_assets');

/**
 * Include preview card template in footer
 *
 * @return void
 */
function saga_include_preview_template(): void
{
    // Only include on frontend
    if (is_admin()) {
        return;
    }

    // Only include if there are entity links on the page
    if (is_singular('saga_entity') || is_post_type_archive('saga_entity') || is_tax('saga_type') || is_singular('saga')) {
        get_template_part('template-parts/preview-card-template');
    }
}
add_action('wp_footer', 'saga_include_preview_template', 100);

/**
 * =============================================================================
 * Entity Comparison
 * =============================================================================
 */

/**
 * AJAX handler for entity search (used in comparison view)
 *
 * @return void
 */
function saga_ajax_search_entities(): void
{
    // Verify nonce
    check_ajax_referer('saga_comparison', 'nonce');

    $query = isset($_POST['query']) ? sanitize_text_field($_POST['query']) : '';
    $exclude = isset($_POST['exclude']) ? sanitize_text_field($_POST['exclude']) : '';

    if (strlen($query) < 2) {
        wp_send_json_error(['message' => __('Query too short', 'saga-manager-theme')]);
        return;
    }

    // Parse exclude IDs
    $exclude_ids = [];
    if (!empty($exclude)) {
        $exclude_ids = array_map('intval', explode(',', $exclude));
    }

    // Build query arguments
    $args = [
        'post_type' => 'saga_entity',
        'post_status' => 'publish',
        's' => $query,
        'posts_per_page' => 10,
        'fields' => 'ids',
    ];

    if (!empty($exclude_ids)) {
        $args['post__not_in'] = $exclude_ids;
    }

    $query_results = new WP_Query($args);

    if (!$query_results->have_posts()) {
        wp_send_json_success([]);
        return;
    }

    $results = [];

    foreach ($query_results->posts as $post_id) {
        $entity_type = get_post_meta($post_id, 'entity_type', true);

        $results[] = [
            'id' => $post_id,
            'title' => get_the_title($post_id),
            'slug' => get_post_field('post_name', $post_id),
            'type' => $entity_type ? ucfirst($entity_type) : '',
            'thumbnail' => get_the_post_thumbnail_url($post_id, 'thumbnail'),
        ];
    }

    wp_send_json_success($results);
}
add_action('wp_ajax_saga_search_entities', 'saga_ajax_search_entities');
add_action('wp_ajax_nopriv_saga_search_entities', 'saga_ajax_search_entities');

/**
 * AJAX handler for exporting comparison data
 *
 * @return void
 */
function saga_ajax_export_comparison(): void
{
    // Verify nonce
    check_ajax_referer('saga_comparison', 'nonce');

    $entities_param = isset($_POST['entities']) ? sanitize_text_field($_POST['entities']) : '';

    if (empty($entities_param)) {
        wp_send_json_error(['message' => __('No entities selected', 'saga-manager-theme')]);
        return;
    }

    // Parse entity IDs
    $entity_identifiers = explode(',', $entities_param);
    $entities = saga_get_comparison_entities($entity_identifiers);

    if (empty($entities)) {
        wp_send_json_error(['message' => __('No valid entities found', 'saga-manager-theme')]);
        return;
    }

    // Get export data
    $export_data = saga_get_comparison_export_data($entities);

    wp_send_json_success($export_data);
}
add_action('wp_ajax_saga_export_comparison', 'saga_ajax_export_comparison');
add_action('wp_ajax_nopriv_saga_export_comparison', 'saga_ajax_export_comparison');

/**
 * =============================================================================
 * Relationship Graph
 * =============================================================================
 */

/**
 * Load relationship graph AJAX handlers
 */
require_once SAGA_THEME_DIR . '/inc/ajax-graph-data.php';

/**
 * Load relationship graph REST API endpoints
 */
require_once SAGA_THEME_DIR . '/inc/rest-api-graph.php';

/**
 * Load relationship graph shortcode
 */
require_once SAGA_THEME_DIR . '/shortcode/relationship-graph-shortcode.php';

/**
 * Load timeline AJAX handlers
 */
require_once SAGA_THEME_DIR . '/inc/ajax-timeline-data.php';

/**
 * Load timeline shortcode
 */
require_once SAGA_THEME_DIR . '/shortcode/timeline-shortcode.php';

/**
 * =============================================================================
 * Timeline Visualization
 * =============================================================================
 */

/**
 * Enqueue timeline visualization assets
 *
 * @return void
 */
function saga_enqueue_timeline_assets(): void
{
    // Register vis-timeline library (CDN)
    wp_register_script(
        'vis-timeline',
        'https://unpkg.com/vis-timeline@7.7.3/standalone/umd/vis-timeline-graph2d.min.js',
        [],
        '7.7.3',
        true
    );

    wp_register_style(
        'vis-timeline',
        'https://unpkg.com/vis-timeline@7.7.3/styles/vis-timeline-graph2d.min.css',
        [],
        '7.7.3'
    );

    // Register html2canvas for image export (optional)
    wp_register_script(
        'html2canvas',
        'https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js',
        [],
        '1.4.1',
        true
    );

    // Enqueue timeline visualization CSS
    wp_enqueue_style(
        'saga-timeline-visualization',
        SAGA_THEME_URI . '/assets/css/timeline-visualization.css',
        [],
        SAGA_THEME_VERSION
    );

    // Enqueue timeline visualization JavaScript
    wp_enqueue_script(
        'saga-timeline-visualization',
        SAGA_THEME_URI . '/assets/js/timeline-visualization.js',
        ['jquery', 'vis-timeline'],
        SAGA_THEME_VERSION,
        true
    );

    // Localize script with AJAX data
    wp_localize_script('saga-timeline-visualization', 'sagaTimelineData', [
        'ajaxurl' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('saga_timeline_nonce'),
        'i18n' => [
            'loading' => __('Loading timeline...', 'saga-manager'),
            'error' => __('Failed to load timeline data.', 'saga-manager'),
            'no_events' => __('No events found.', 'saga-manager'),
            'filter_applied' => __('Filters applied.', 'saga-manager'),
            'zoom_in' => __('Zoom in', 'saga-manager'),
            'zoom_out' => __('Zoom out', 'saga-manager'),
            'fit' => __('Fit to window', 'saga-manager'),
            'export_success' => __('Export successful.', 'saga-manager'),
            'export_error' => __('Export failed.', 'saga-manager'),
        ]
    ]);
}
add_action('wp_enqueue_scripts', 'saga_enqueue_timeline_assets');

/**
 * =============================================================================
 * 3D Semantic Galaxy Visualization (Phase 1 - Next-Gen Features)
 * =============================================================================
 */

/**
 * Load galaxy shortcode handler
 */
require_once SAGA_THEME_DIR . '/inc/shortcodes/galaxy-shortcode.php';

/**
 * Load galaxy AJAX data handler
 */
require_once SAGA_THEME_DIR . '/inc/ajax/galaxy-data-handler.php';

/**
 * =============================================================================
 * WebGPU Infinite Zoom Timeline (Phase 1 - Next-Gen Features v1.3.0)
 * =============================================================================
 */

/**
 * Load timeline shortcode handler
 */
require_once SAGA_THEME_DIR . '/inc/shortcodes/timeline-shortcode.php';

/**
 * Load timeline AJAX data handler
 */
require_once SAGA_THEME_DIR . '/inc/ajax/timeline-data-handler.php';

/**
 * Load calendar converter helper
 */
require_once SAGA_THEME_DIR . '/inc/helpers/calendar-converter.php';

/**
 * =============================================================================
 * Entity Quick Create (Phase 1 - Next-Gen Features v1.3.0)
 * =============================================================================
 */

/**
 * Initialize quick create system
 *
 * Provides rapid entity creation through admin bar shortcut and modal interface
 * Includes keyboard shortcuts (Ctrl+Shift+E), AJAX submission, and localStorage autosave
 *
 * @return void
 */
function saga_init_quick_create(): void
{
    // Load required files
    require_once SAGA_THEME_DIR . '/inc/admin/quick-create.php';
    require_once SAGA_THEME_DIR . '/inc/ajax/quick-create-handler.php';
    require_once SAGA_THEME_DIR . '/inc/admin/entity-templates.php';

    // Initialize quick create
    $quick_create = new \SagaManager\Admin\QuickCreate();
    $quick_create->init();

    // Initialize AJAX handler
    $ajax_handler = new \SagaManager\Ajax\QuickCreateHandler();
    $ajax_handler->register();
}
add_action('init', 'saga_init_quick_create');

/**
 * =============================================================================
 * AI Consistency Guardian (Phase 2 - v1.4.0)
 * =============================================================================
 */

/**
 * Initialize AI Consistency Guardian
 *
 * Provides AI-powered consistency checking for saga entities
 * Detects plot holes, timeline issues, character contradictions
 *
 * @return void
 */
function saga_init_ai_consistency_guardian(): void
{
    // Load database migrator
    require_once SAGA_THEME_DIR . '/inc/ai/database-migrator.php';

    // Load entity classes
    require_once SAGA_THEME_DIR . '/inc/ai/entities/ConsistencyIssue.php';

    // Load core AI classes
    require_once SAGA_THEME_DIR . '/inc/ai/ConsistencyRuleEngine.php';
    require_once SAGA_THEME_DIR . '/inc/ai/AIClient.php';
    require_once SAGA_THEME_DIR . '/inc/ai/ConsistencyRepository.php';
    require_once SAGA_THEME_DIR . '/inc/ai/ConsistencyAnalyzer.php';

    // Load admin settings page
    if (is_admin()) {
        require_once SAGA_THEME_DIR . '/inc/admin/ai-settings.php';
    }
}
add_action('after_setup_theme', 'saga_init_ai_consistency_guardian');

/**
 * Get AI Consistency Analyzer instance
 *
 * Factory function for dependency injection
 *
 * @return \SagaManager\AI\ConsistencyAnalyzer
 */
function saga_get_consistency_analyzer(): \SagaManager\AI\ConsistencyAnalyzer
{
    static $analyzer = null;

    if ($analyzer === null) {
        $ruleEngine = new \SagaManager\AI\ConsistencyRuleEngine();
        $aiClient = new \SagaManager\AI\AIClient();
        $repository = new \SagaManager\AI\ConsistencyRepository();

        $analyzer = new \SagaManager\AI\ConsistencyAnalyzer(
            $ruleEngine,
            $aiClient,
            $repository
        );
    }

    return $analyzer;
}

/**
 * Run consistency analysis on a saga
 *
 * Template function for themes/plugins
 *
 * @param int   $sagaId     Saga ID
 * @param bool  $useAI      Whether to use AI analysis
 * @param array $ruleTypes  Rule types to check
 * @return array Array of ConsistencyIssue objects
 */
function saga_analyze_consistency(int $sagaId, bool $useAI = true, array $ruleTypes = []): array
{
    $analyzer = saga_get_consistency_analyzer();
    return $analyzer->analyze($sagaId, [], $useAI, $ruleTypes);
}

/**
 * Get consistency issues for a saga
 *
 * @param int    $sagaId Saga ID
 * @param string $status Status filter (open, resolved, dismissed, false_positive)
 * @return array Array of ConsistencyIssue objects
 */
function saga_get_consistency_issues(int $sagaId, string $status = 'open'): array
{
    $analyzer = saga_get_consistency_analyzer();
    return $analyzer->getIssues($sagaId, $status);
}

/**
 * Get consistency statistics for a saga
 *
 * @param int $sagaId Saga ID
 * @return array Statistics array
 */
function saga_get_consistency_stats(int $sagaId): array
{
    $analyzer = saga_get_consistency_analyzer();
    return $analyzer->getStatistics($sagaId);
}
