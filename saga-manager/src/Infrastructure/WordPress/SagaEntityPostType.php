<?php
declare(strict_types=1);

namespace SagaManager\Infrastructure\WordPress;

use SagaManager\Domain\Entity\EntityId;
use SagaManager\Domain\Entity\EntityType;
use SagaManager\Domain\Entity\ImportanceScore;
use SagaManager\Domain\Entity\SagaEntity;
use SagaManager\Domain\Entity\SagaId;
use SagaManager\Domain\Repository\EntityRepositoryInterface;
use SagaManager\Domain\Exception\EntityNotFoundException;
use SagaManager\Domain\Exception\ValidationException;

/**
 * Saga Entity Custom Post Type
 *
 * Manages bidirectional sync between wp_posts and saga_entities table.
 * Implements conflict detection using timestamp comparison.
 */
class SagaEntityPostType extends WordPressTablePrefixAware
{
    private const POST_TYPE = 'saga_entity';
    private const SYNC_FLAG_META = '_saga_sync_in_progress';
    private const CONFLICT_META = '_saga_sync_conflict';

    private EntityRepositoryInterface $entityRepository;

    public function __construct(EntityRepositoryInterface $entityRepository)
    {
        parent::__construct();
        $this->entityRepository = $entityRepository;
    }

    /**
     * Register the custom post type
     */
    public function register(): void
    {
        register_post_type(self::POST_TYPE, [
            'labels' => [
                'name' => __('Saga Entities', 'saga-manager'),
                'singular_name' => __('Saga Entity', 'saga-manager'),
                'add_new' => __('Add New Entity', 'saga-manager'),
                'add_new_item' => __('Add New Saga Entity', 'saga-manager'),
                'edit_item' => __('Edit Saga Entity', 'saga-manager'),
                'new_item' => __('New Saga Entity', 'saga-manager'),
                'view_item' => __('View Saga Entity', 'saga-manager'),
                'search_items' => __('Search Saga Entities', 'saga-manager'),
                'not_found' => __('No saga entities found', 'saga-manager'),
                'not_found_in_trash' => __('No saga entities found in Trash', 'saga-manager'),
            ],
            'public' => true,
            'publicly_queryable' => true,
            'show_ui' => true,
            'show_in_menu' => true,
            'show_in_rest' => true, // Enable Gutenberg
            'rest_base' => 'saga-entities',
            'has_archive' => true,
            'rewrite' => [
                'slug' => 'saga-entity',
                'with_front' => false,
            ],
            'capability_type' => 'post',
            'map_meta_cap' => true,
            'supports' => [
                'title',
                'editor',
                'custom-fields',
                'thumbnail',
                'excerpt',
                'revisions',
            ],
            'taxonomies' => ['saga_type'],
            'menu_icon' => 'dashicons-book-alt',
            'menu_position' => 20,
        ]);
    }

    /**
     * Sync WordPress post to saga_entities table
     * Called on save_post hook
     *
     * @param int $post_id WordPress post ID
     */
    public function syncToDatabase(int $post_id): void
    {
        // Check if sync is already in progress (prevent infinite loops)
        if (get_post_meta($post_id, self::SYNC_FLAG_META, true)) {
            return;
        }

        $post = get_post($post_id);

        // Verify post type
        if (!$post || $post->post_type !== self::POST_TYPE) {
            return;
        }

        // Skip autosaves and revisions
        if (wp_is_post_autosave($post_id) || wp_is_post_revision($post_id)) {
            return;
        }

        // Check permissions
        if (!current_user_can('edit_post', $post_id)) {
            return;
        }

        // Set sync flag
        update_post_meta($post_id, self::SYNC_FLAG_META, '1');

        try {
            // Get metadata
            $saga_id = (int) get_post_meta($post_id, '_saga_id', true);
            $entity_type = get_post_meta($post_id, '_entity_type', true);
            $importance_score = (int) get_post_meta($post_id, '_importance_score', true);

            // Validate required metadata
            if (!$saga_id) {
                error_log("[SAGA][SYNC] Cannot sync post {$post_id}: missing saga_id");
                delete_post_meta($post_id, self::SYNC_FLAG_META);
                return;
            }

            if (!$entity_type || !$this->isValidEntityType($entity_type)) {
                error_log("[SAGA][SYNC] Cannot sync post {$post_id}: invalid entity_type");
                delete_post_meta($post_id, self::SYNC_FLAG_META);
                return;
            }

            // Find existing entity linked to this post
            $entity = $this->entityRepository->findByWpPostId($post_id);

            if ($entity) {
                // Entity exists - check for conflicts
                $this->handleUpdate($entity, $post, $saga_id, $entity_type, $importance_score);
            } else {
                // New entity - create it
                $this->handleCreate($post, $saga_id, $entity_type, $importance_score);
            }

        } catch (\Exception $e) {
            error_log('[SAGA][ERROR] Failed to sync post to database: ' . $e->getMessage());
            update_post_meta($post_id, self::CONFLICT_META, [
                'error' => $e->getMessage(),
                'timestamp' => current_time('mysql'),
            ]);
        } finally {
            // Clear sync flag
            delete_post_meta($post_id, self::SYNC_FLAG_META);
        }
    }

    /**
     * Sync saga entity to WordPress post
     * Called on saga_entity_saved action
     *
     * @param SagaEntity $entity The saved entity
     */
    public function syncFromEntity(SagaEntity $entity): void
    {
        $wpPostId = $entity->getWpPostId();

        // Check if sync is already in progress
        if ($wpPostId && get_post_meta($wpPostId, self::SYNC_FLAG_META, true)) {
            return;
        }

        try {
            if ($wpPostId) {
                // Update existing post
                $this->updatePost($entity, $wpPostId);
            } else {
                // Create new post
                $this->createPost($entity);
            }
        } catch (\Exception $e) {
            error_log('[SAGA][ERROR] Failed to sync entity to post: ' . $e->getMessage());
        }
    }

    /**
     * Handle entity update with conflict detection
     */
    private function handleUpdate(
        SagaEntity $entity,
        \WP_Post $post,
        int $saga_id,
        string $entity_type,
        int $importance_score
    ): void {
        $post_modified_timestamp = get_post_modified_time('U', false, $post->ID);
        $entity_updated_timestamp = $entity->getUpdatedAt()->getTimestamp();

        // Conflict detection: compare timestamps
        if ($entity_updated_timestamp > $post_modified_timestamp) {
            error_log(sprintf(
                "[SAGA][SYNC] Conflict detected for post %d: entity newer (entity: %s, post: %s)",
                $post->ID,
                $entity->getUpdatedAt()->format('Y-m-d H:i:s'),
                get_post_modified_time('Y-m-d H:i:s', false, $post->ID)
            ));

            update_post_meta($post->ID, self::CONFLICT_META, [
                'entity_updated' => $entity->getUpdatedAt()->format('Y-m-d H:i:s'),
                'post_modified' => get_post_modified_time('Y-m-d H:i:s', false, $post->ID),
                'resolution' => 'post_wins',
                'timestamp' => current_time('mysql'),
            ]);

            // Last write wins: post is newer, update entity
        }

        // Update entity with post data
        $entity->updateCanonicalName(sanitize_text_field($post->post_title));
        $entity->updateSlug(sanitize_title($post->post_name));

        if ($importance_score > 0 && $importance_score <= 100) {
            $entity->setImportanceScore(new ImportanceScore($importance_score));
        }

        $this->entityRepository->save($entity);

        error_log(sprintf(
            "[SAGA][SYNC] Updated entity %d from post %d",
            $entity->getId()->value(),
            $post->ID
        ));
    }

    /**
     * Handle entity creation from post
     */
    private function handleCreate(
        \WP_Post $post,
        int $saga_id,
        string $entity_type,
        int $importance_score
    ): void {
        // Create new entity
        $entity = new SagaEntity(
            sagaId: new SagaId($saga_id),
            type: EntityType::from($entity_type),
            canonicalName: sanitize_text_field($post->post_title),
            slug: sanitize_title($post->post_name),
            importanceScore: new ImportanceScore(
                $importance_score > 0 && $importance_score <= 100 ? $importance_score : 50
            )
        );

        $entity->linkToWpPost($post->ID);
        $this->entityRepository->save($entity);

        error_log(sprintf(
            "[SAGA][SYNC] Created entity %d from post %d",
            $entity->getId()->value(),
            $post->ID
        ));
    }

    /**
     * Update WordPress post from entity
     */
    private function updatePost(SagaEntity $entity, int $postId): void
    {
        $post = get_post($postId);
        if (!$post) {
            return;
        }

        // Set sync flag
        update_post_meta($postId, self::SYNC_FLAG_META, '1');

        $post_modified_timestamp = get_post_modified_time('U', false, $postId);
        $entity_updated_timestamp = $entity->getUpdatedAt()->getTimestamp();

        // Conflict detection
        if ($post_modified_timestamp > $entity_updated_timestamp) {
            error_log(sprintf(
                "[SAGA][SYNC] Conflict detected for entity %d: post newer (entity: %s, post: %s)",
                $entity->getId()->value(),
                $entity->getUpdatedAt()->format('Y-m-d H:i:s'),
                get_post_modified_time('Y-m-d H:i:s', false, $postId)
            ));

            update_post_meta($postId, self::CONFLICT_META, [
                'entity_updated' => $entity->getUpdatedAt()->format('Y-m-d H:i:s'),
                'post_modified' => get_post_modified_time('Y-m-d H:i:s', false, $postId),
                'resolution' => 'entity_wins',
                'timestamp' => current_time('mysql'),
            ]);

            // Last write wins: entity is newer, update post
        }

        wp_update_post([
            'ID' => $postId,
            'post_title' => $entity->getCanonicalName(),
            'post_name' => $entity->getSlug(),
        ]);

        // Update metadata
        update_post_meta($postId, '_saga_id', $entity->getSagaId()->value());
        update_post_meta($postId, '_entity_type', $entity->getType()->value);
        update_post_meta($postId, '_importance_score', $entity->getImportanceScore()->value());

        // Clear sync flag
        delete_post_meta($postId, self::SYNC_FLAG_META);

        error_log(sprintf(
            "[SAGA][SYNC] Updated post %d from entity %d",
            $postId,
            $entity->getId()->value()
        ));
    }

    /**
     * Create WordPress post from entity
     */
    private function createPost(SagaEntity $entity): void
    {
        $post_id = wp_insert_post([
            'post_type' => self::POST_TYPE,
            'post_title' => $entity->getCanonicalName(),
            'post_name' => $entity->getSlug(),
            'post_status' => 'publish',
            'post_author' => get_current_user_id(),
        ]);

        if (is_wp_error($post_id)) {
            throw new \RuntimeException('Failed to create post: ' . $post_id->get_error_message());
        }

        // Set sync flag
        update_post_meta($post_id, self::SYNC_FLAG_META, '1');

        // Update metadata
        update_post_meta($post_id, '_saga_id', $entity->getSagaId()->value());
        update_post_meta($post_id, '_entity_type', $entity->getType()->value);
        update_post_meta($post_id, '_importance_score', $entity->getImportanceScore()->value());

        // Link entity to post
        $entity->linkToWpPost($post_id);
        $this->entityRepository->save($entity);

        // Clear sync flag
        delete_post_meta($post_id, self::SYNC_FLAG_META);

        error_log(sprintf(
            "[SAGA][SYNC] Created post %d from entity %d",
            $post_id,
            $entity->getId()->value()
        ));
    }

    /**
     * Validate entity type string
     */
    private function isValidEntityType(string $type): bool
    {
        try {
            EntityType::from($type);
            return true;
        } catch (\ValueError $e) {
            return false;
        }
    }
}
