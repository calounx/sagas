<?php
/**
 * Breadcrumb Generator for Saga Manager Theme
 *
 * Generates hierarchical breadcrumbs with Schema.org markup
 * for entity pages, archives, taxonomies, and search results.
 *
 * @package SagaManagerTheme
 * @since 1.0.0
 */

declare(strict_types=1);

namespace SagaManagerTheme\Breadcrumb;

/**
 * Class BreadcrumbGenerator
 *
 * Handles breadcrumb generation with support for:
 * - Hierarchical taxonomy breadcrumbs
 * - Entity type → Entity detail trails
 * - Search results breadcrumbs
 * - Schema.org BreadcrumbList markup
 */
class BreadcrumbGenerator
{
    /**
     * Breadcrumb items array
     *
     * @var array<int, array{name: string, url: string}>
     */
    private array $items = [];

    /**
     * Schema.org structured data
     *
     * @var array<string, mixed>
     */
    private array $schema = [];

    /**
     * Home page label
     *
     * @var string
     */
    private string $home_label = 'Home';

    /**
     * Separator for plain text output
     *
     * @var string
     */
    private string $separator = ' > ';

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->home_label = apply_filters('saga_breadcrumb_home_label', __('Home', 'saga-manager-theme'));
        $this->separator = apply_filters('saga_breadcrumb_separator', ' > ');
    }

    /**
     * Generate breadcrumbs for current page
     *
     * @return self
     */
    public function generate(): self
    {
        // Always start with home
        $this->add_home();

        // Handle different page types
        if (is_front_page()) {
            // Front page - only home crumb
            return $this;
        }

        if (is_search()) {
            $this->add_search();
        } elseif (is_404()) {
            $this->add_404();
        } elseif (is_singular('saga_entity')) {
            $this->add_entity_breadcrumbs();
        } elseif (is_post_type_archive('saga_entity')) {
            $this->add_entity_archive();
        } elseif (is_tax()) {
            $this->add_taxonomy_breadcrumbs();
        } elseif (is_singular()) {
            $this->add_single_post();
        } elseif (is_page()) {
            $this->add_page_breadcrumbs();
        } elseif (is_category() || is_tag() || is_tax()) {
            $this->add_term_breadcrumbs();
        } elseif (is_post_type_archive()) {
            $this->add_post_type_archive();
        } elseif (is_author()) {
            $this->add_author();
        } elseif (is_date()) {
            $this->add_date_archive();
        }

        $this->build_schema();

        return $this;
    }

    /**
     * Add home breadcrumb
     */
    private function add_home(): void
    {
        $this->add_item($this->home_label, home_url('/'));
    }

    /**
     * Add search breadcrumb
     */
    private function add_search(): void
    {
        $search_query = get_search_query();
        $label = sprintf(
            __('Search Results: "%s"', 'saga-manager-theme'),
            esc_html($search_query)
        );

        $this->add_item($label, '', true);
    }

    /**
     * Add 404 breadcrumb
     */
    private function add_404(): void
    {
        $this->add_item(__('404 - Page Not Found', 'saga-manager-theme'), '', true);
    }

    /**
     * Add entity detail breadcrumbs
     * Structure: Home > Saga Name > Entity Type > Entity Name
     */
    private function add_entity_breadcrumbs(): void
    {
        global $post;

        // Get entity type taxonomy
        $entity_types = get_the_terms($post->ID, 'saga_entity_type');

        // Get saga taxonomy
        $sagas = get_the_terms($post->ID, 'saga');

        // Add saga breadcrumb if exists
        if ($sagas && !is_wp_error($sagas)) {
            $saga = array_shift($sagas);
            $this->add_item(
                $saga->name,
                get_term_link($saga)
            );
        }

        // Add entity type breadcrumb if exists
        if ($entity_types && !is_wp_error($entity_types)) {
            $entity_type = array_shift($entity_types);

            // Build archive URL with saga filter if applicable
            $archive_url = get_post_type_archive_link('saga_entity');
            if ($saga ?? null) {
                $archive_url = add_query_arg([
                    'saga' => $saga->slug,
                    'entity_type' => $entity_type->slug,
                ], $archive_url);
            }

            $this->add_item(
                $entity_type->name,
                $archive_url
            );
        }

        // Add current entity (no link)
        $this->add_item(get_the_title(), '', true);
    }

    /**
     * Add entity archive breadcrumb
     */
    private function add_entity_archive(): void
    {
        $post_type_obj = get_post_type_object('saga_entity');

        if ($post_type_obj) {
            $this->add_item(
                $post_type_obj->labels->name ?? __('Entities', 'saga-manager-theme'),
                '',
                true
            );
        }
    }

    /**
     * Add taxonomy breadcrumbs with hierarchy
     */
    private function add_taxonomy_breadcrumbs(): void
    {
        $term = get_queried_object();

        if (!$term instanceof \WP_Term) {
            return;
        }

        // Add parent terms if hierarchical
        if (is_taxonomy_hierarchical($term->taxonomy)) {
            $ancestors = get_ancestors($term->term_id, $term->taxonomy, 'taxonomy');
            $ancestors = array_reverse($ancestors);

            foreach ($ancestors as $ancestor_id) {
                $ancestor = get_term($ancestor_id, $term->taxonomy);
                if ($ancestor && !is_wp_error($ancestor)) {
                    $this->add_item(
                        $ancestor->name,
                        get_term_link($ancestor)
                    );
                }
            }
        }

        // Add current term (no link)
        $this->add_item($term->name, '', true);
    }

    /**
     * Add single post breadcrumbs
     */
    private function add_single_post(): void
    {
        $post_type = get_post_type();
        $post_type_obj = get_post_type_object($post_type);

        // Add post type archive if available
        if ($post_type_obj && $post_type_obj->has_archive) {
            $this->add_item(
                $post_type_obj->labels->name ?? $post_type,
                get_post_type_archive_link($post_type)
            );
        }

        // Add categories for posts
        if ($post_type === 'post') {
            $categories = get_the_category();
            if ($categories) {
                $category = array_shift($categories);
                $this->add_term_hierarchy($category);
            }
        }

        // Add current post (no link)
        $this->add_item(get_the_title(), '', true);
    }

    /**
     * Add page breadcrumbs with hierarchy
     */
    private function add_page_breadcrumbs(): void
    {
        global $post;

        // Add parent pages
        if ($post->post_parent) {
            $parent_ids = array_reverse(get_post_ancestors($post));

            foreach ($parent_ids as $parent_id) {
                $this->add_item(
                    get_the_title($parent_id),
                    get_permalink($parent_id)
                );
            }
        }

        // Add current page (no link)
        $this->add_item(get_the_title(), '', true);
    }

    /**
     * Add term breadcrumbs
     */
    private function add_term_breadcrumbs(): void
    {
        $term = get_queried_object();

        if ($term instanceof \WP_Term) {
            $this->add_term_hierarchy($term);
        }
    }

    /**
     * Add term hierarchy recursively
     *
     * @param \WP_Term $term Term object
     * @param bool $is_current Whether this is the current term
     */
    private function add_term_hierarchy(\WP_Term $term, bool $is_current = true): void
    {
        // Add parent terms first
        if ($term->parent) {
            $parent = get_term($term->parent, $term->taxonomy);
            if ($parent && !is_wp_error($parent)) {
                $this->add_term_hierarchy($parent, false);
            }
        }

        // Add current term
        $this->add_item(
            $term->name,
            $is_current ? '' : get_term_link($term),
            $is_current
        );
    }

    /**
     * Add post type archive breadcrumb
     */
    private function add_post_type_archive(): void
    {
        $post_type_obj = get_post_type_object(get_post_type());

        if ($post_type_obj) {
            $this->add_item(
                $post_type_obj->labels->name ?? get_post_type(),
                '',
                true
            );
        }
    }

    /**
     * Add author breadcrumb
     */
    private function add_author(): void
    {
        $author = get_queried_object();

        if ($author instanceof \WP_User) {
            $label = sprintf(
                __('Author: %s', 'saga-manager-theme'),
                $author->display_name
            );
            $this->add_item($label, '', true);
        }
    }

    /**
     * Add date archive breadcrumbs
     */
    private function add_date_archive(): void
    {
        if (is_year()) {
            $this->add_item(get_the_date('Y'), '', true);
        } elseif (is_month()) {
            $this->add_item(get_the_date('Y'), get_year_link(get_the_date('Y')));
            $this->add_item(get_the_date('F'), '', true);
        } elseif (is_day()) {
            $this->add_item(get_the_date('Y'), get_year_link(get_the_date('Y')));
            $this->add_item(get_the_date('F'), get_month_link(get_the_date('Y'), get_the_date('m')));
            $this->add_item(get_the_date('d'), '', true);
        }
    }

    /**
     * Add breadcrumb item
     *
     * @param string $name Item name
     * @param string $url Item URL (empty for current page)
     * @param bool $is_current Whether this is the current page
     */
    private function add_item(string $name, string $url = '', bool $is_current = false): void
    {
        $this->items[] = [
            'name' => $name,
            'url' => $url,
            'is_current' => $is_current,
        ];
    }

    /**
     * Build Schema.org structured data
     */
    private function build_schema(): void
    {
        $list_items = [];

        foreach ($this->items as $position => $item) {
            $list_item = [
                '@type' => 'ListItem',
                'position' => $position + 1,
                'name' => $item['name'],
            ];

            if (!empty($item['url'])) {
                $list_item['item'] = $item['url'];
            }

            $list_items[] = $list_item;
        }

        $this->schema = [
            '@context' => 'https://schema.org',
            '@type' => 'BreadcrumbList',
            'itemListElement' => $list_items,
        ];
    }

    /**
     * Get breadcrumb items
     *
     * @return array<int, array{name: string, url: string, is_current: bool}>
     */
    public function get_items(): array
    {
        return $this->items;
    }

    /**
     * Get Schema.org structured data
     *
     * @return array<string, mixed>
     */
    public function get_schema(): array
    {
        return $this->schema;
    }

    /**
     * Get breadcrumbs as plain text
     *
     * @return string
     */
    public function get_text(): string
    {
        $names = array_column($this->items, 'name');
        return implode($this->separator, $names);
    }

    /**
     * Check if breadcrumbs should be displayed
     *
     * @return bool
     */
    public function should_display(): bool
    {
        // Don't show on front page (only home crumb)
        if (is_front_page()) {
            return false;
        }

        // Allow filtering
        return apply_filters('saga_show_breadcrumbs', true);
    }
}
