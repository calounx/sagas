<?php
/**
 * Template Name: My Collections
 *
 * Page template for managing user collections and bookmarks.
 * Displays all collections with entity lists and management options.
 *
 * @package Saga_Manager_Theme
 */

if (!defined('ABSPATH')) {
    exit;
}

get_header();

$collections_manager = new Saga_Collections();
$user_id = get_current_user_id();
$is_logged_in = $user_id > 0;

// Get user collections (empty array for guests)
$collections = $is_logged_in ? $collections_manager->get_user_collections($user_id) : [];
?>

<main id="primary" class="site-main saga-collections-page">
    <div class="container">

        <!-- Page Header -->
        <header class="saga-collections-header">
            <h1><?php echo esc_html__('My Collections', 'saga-manager'); ?></h1>
            <p><?php echo esc_html__('Organize and manage your favorite saga entities', 'saga-manager'); ?></p>
        </header>

        <?php if (!$is_logged_in) : ?>
            <!-- Guest User Notice -->
            <div class="saga-guest-notice">
                <p>
                    <?php
                    printf(
                        /* translators: %s: login URL */
                        esc_html__('You are viewing collections in guest mode. Your bookmarks are stored locally in your browser. %s to sync your collections across devices.', 'saga-manager'),
                        '<a href="' . esc_url(wp_login_url(get_permalink())) . '">' . esc_html__('Log in', 'saga-manager') . '</a>'
                    );
                    ?>
                </p>
            </div>
        <?php endif; ?>

        <?php if ($is_logged_in) : ?>
            <!-- Create New Collection -->
            <div class="saga-create-collection-card">
                <h2><?php echo esc_html__('Create New Collection', 'saga-manager'); ?></h2>
                <form id="saga-create-collection-form">
                    <div class="form-group">
                        <label for="collection_name">
                            <?php echo esc_html__('Collection Name', 'saga-manager'); ?>
                        </label>
                        <input
                            type="text"
                            id="collection_name"
                            name="collection_name"
                            placeholder="<?php echo esc_attr__('e.g., To Read, Important Characters', 'saga-manager'); ?>"
                            maxlength="100"
                            required
                        >
                    </div>
                    <button type="submit">
                        <?php echo esc_html__('Create Collection', 'saga-manager'); ?>
                    </button>
                </form>
            </div>
        <?php endif; ?>

        <!-- Collections Grid -->
        <?php if (empty($collections)) : ?>
            <div class="saga-collection-empty">
                <div class="saga-collection-empty-icon">&#128218;</div>
                <p><?php echo esc_html__('No collections yet. Start bookmarking entities to build your collection!', 'saga-manager'); ?></p>
            </div>
        <?php else : ?>
            <div class="saga-collections-grid">
                <?php foreach ($collections as $collection_slug => $collection_data) :
                    $entity_ids = $collection_data['entity_ids'] ?? [];
                    $entity_count = count($entity_ids);
                    $collection_name = $collection_data['name'] ?? ucfirst($collection_slug);
                    $created_at = $collection_data['created_at'] ?? '';
                    $updated_at = $collection_data['updated_at'] ?? '';
                    $is_protected = $collection_slug === 'favorites';
                ?>
                    <div class="saga-collection-item" data-collection="<?php echo esc_attr($collection_slug); ?>">

                        <!-- Collection Header -->
                        <div class="saga-collection-header">
                            <h3 class="saga-collection-name">
                                <?php echo esc_html($collection_name); ?>
                                <?php if ($is_protected) : ?>
                                    <span class="saga-collection-badge" title="<?php echo esc_attr__('Default collection', 'saga-manager'); ?>">⭐</span>
                                <?php endif; ?>
                            </h3>
                            <span class="saga-collection-count">
                                <?php
                                printf(
                                    /* translators: %d: number of entities */
                                    esc_html(_n('%d item', '%d items', $entity_count, 'saga-manager')),
                                    $entity_count
                                );
                                ?>
                            </span>
                        </div>

                        <!-- Collection Meta -->
                        <?php if ($updated_at) : ?>
                            <div class="saga-collection-meta">
                                <?php
                                printf(
                                    /* translators: %s: formatted date */
                                    esc_html__('Last updated: %s', 'saga-manager'),
                                    esc_html(mysql2date(get_option('date_format'), $updated_at))
                                );
                                ?>
                            </div>
                        <?php endif; ?>

                        <!-- Collection Entities List -->
                        <?php if ($entity_count > 0) : ?>
                            <div class="saga-collection-entities">
                                <?php
                                // Limit display to first 5 entities
                                $display_entities = array_slice($entity_ids, 0, 5);

                                foreach ($display_entities as $entity_id) :
                                    $entity = get_post($entity_id);

                                    if (!$entity || $entity->post_status !== 'publish') {
                                        continue;
                                    }

                                    $entity_type = get_post_meta($entity_id, 'entity_type', true);
                                ?>
                                    <div class="saga-collection-entity-item">
                                        <?php if ($entity_type) : ?>
                                            <span class="saga-collection-entity-type">
                                                <?php echo esc_html($entity_type); ?>
                                            </span>
                                        <?php endif; ?>
                                        <a href="<?php echo esc_url(get_permalink($entity)); ?>">
                                            <?php echo esc_html(get_the_title($entity)); ?>
                                        </a>
                                    </div>
                                <?php endforeach; ?>

                                <?php if ($entity_count > 5) : ?>
                                    <div class="saga-collection-entity-item">
                                        <em>
                                            <?php
                                            printf(
                                                /* translators: %d: number of remaining entities */
                                                esc_html__('+ %d more', 'saga-manager'),
                                                $entity_count - 5
                                            );
                                            ?>
                                        </em>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>

                        <!-- Collection Actions -->
                        <div class="saga-collection-actions">
                            <?php if ($entity_count > 0) : ?>
                                <a href="<?php echo esc_url(add_query_arg('collection', $collection_slug, get_permalink())); ?>" class="saga-view-collection">
                                    <?php echo esc_html__('View All', 'saga-manager'); ?>
                                </a>
                            <?php endif; ?>

                            <button
                                type="button"
                                class="saga-export-collection"
                                data-collection="<?php echo esc_attr($collection_slug); ?>"
                            >
                                <?php echo esc_html__('Export', 'saga-manager'); ?>
                            </button>

                            <?php if ($is_logged_in && !$is_protected) : ?>
                                <button
                                    type="button"
                                    class="saga-rename-collection-toggle"
                                    data-collection="<?php echo esc_attr($collection_slug); ?>"
                                    onclick="this.nextElementSibling.style.display = this.nextElementSibling.style.display === 'none' ? 'flex' : 'none';"
                                >
                                    <?php echo esc_html__('Rename', 'saga-manager'); ?>
                                </button>

                                <button
                                    type="button"
                                    class="saga-delete-collection"
                                    data-collection="<?php echo esc_attr($collection_slug); ?>"
                                >
                                    <?php echo esc_html__('Delete', 'saga-manager'); ?>
                                </button>
                            <?php endif; ?>
                        </div>

                        <!-- Rename Form (Hidden by default) -->
                        <?php if ($is_logged_in && !$is_protected) : ?>
                            <form
                                class="saga-rename-collection-form"
                                data-collection="<?php echo esc_attr($collection_slug); ?>"
                                style="display: none;"
                            >
                                <input
                                    type="text"
                                    name="new_name"
                                    value="<?php echo esc_attr($collection_name); ?>"
                                    placeholder="<?php echo esc_attr__('New collection name', 'saga-manager'); ?>"
                                    maxlength="100"
                                    required
                                >
                                <button type="submit">
                                    <?php echo esc_html__('Save', 'saga-manager'); ?>
                                </button>
                            </form>
                        <?php endif; ?>

                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <!-- Guest Collections Message -->
        <?php if (!$is_logged_in) : ?>
            <div class="saga-guest-collections-info" style="margin-top: 2rem; padding: 1.5rem; background: #f8f9fa; border-radius: 0.5rem;">
                <h3><?php echo esc_html__('About Guest Collections', 'saga-manager'); ?></h3>
                <p><?php echo esc_html__('Your bookmarks are stored in your browser\'s local storage. This means:', 'saga-manager'); ?></p>
                <ul>
                    <li><?php echo esc_html__('Collections are device-specific and won\'t sync across browsers', 'saga-manager'); ?></li>
                    <li><?php echo esc_html__('Clearing browser data will remove your collections', 'saga-manager'); ?></li>
                    <li><?php echo esc_html__('Create an account to backup and sync your collections', 'saga-manager'); ?></li>
                </ul>
            </div>
        <?php endif; ?>

    </div>
</main>

<?php
get_footer();
