<?php
declare(strict_types=1);

namespace SagaManager\Infrastructure\WordPress;

/**
 * Saga Type Taxonomy
 *
 * Registers a non-hierarchical taxonomy for categorizing saga entities
 */
class SagaTypeTaxonomy
{
    private const TAXONOMY = 'saga_type';
    private const POST_TYPE = 'saga_entity';

    /**
     * Register the taxonomy
     */
    public function register(): void
    {
        register_taxonomy(self::TAXONOMY, [self::POST_TYPE], [
            'labels' => [
                'name' => __('Saga Types', 'saga-manager'),
                'singular_name' => __('Saga Type', 'saga-manager'),
                'search_items' => __('Search Saga Types', 'saga-manager'),
                'popular_items' => __('Popular Saga Types', 'saga-manager'),
                'all_items' => __('All Saga Types', 'saga-manager'),
                'edit_item' => __('Edit Saga Type', 'saga-manager'),
                'update_item' => __('Update Saga Type', 'saga-manager'),
                'add_new_item' => __('Add New Saga Type', 'saga-manager'),
                'new_item_name' => __('New Saga Type Name', 'saga-manager'),
                'separate_items_with_commas' => __('Separate saga types with commas', 'saga-manager'),
                'add_or_remove_items' => __('Add or remove saga types', 'saga-manager'),
                'choose_from_most_used' => __('Choose from most used saga types', 'saga-manager'),
                'not_found' => __('No saga types found', 'saga-manager'),
                'menu_name' => __('Saga Types', 'saga-manager'),
            ],
            'hierarchical' => false, // Like tags, not categories
            'public' => true,
            'show_ui' => true,
            'show_admin_column' => true,
            'show_in_nav_menus' => true,
            'show_tagcloud' => true,
            'show_in_rest' => true, // Enable in Gutenberg
            'rest_base' => 'saga-types',
            'rewrite' => [
                'slug' => 'saga-type',
                'with_front' => false,
            ],
            'capabilities' => [
                'manage_terms' => 'manage_categories',
                'edit_terms' => 'manage_categories',
                'delete_terms' => 'manage_categories',
                'assign_terms' => 'edit_posts',
            ],
        ]);
    }
}
