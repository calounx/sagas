<?php
declare(strict_types=1);

namespace SagaTheme;

/**
 * Hook Manager for GeneratePress Integration
 *
 * Manages all WordPress and GeneratePress hooks for the Saga Manager theme
 * Customizes layout, adds entity metadata, and enhances post display
 *
 * @package SagaTheme
 */
class SagaHooks
{
    private SagaHelpers $helpers;
    private SagaQueries $queries;

    /**
     * Constructor
     *
     * @param SagaHelpers $helpers Helper service instance
     * @param SagaQueries $queries Query service instance
     */
    public function __construct(SagaHelpers $helpers, SagaQueries $queries)
    {
        $this->helpers = $helpers;
        $this->queries = $queries;
    }

    /**
     * Register all hooks
     *
     * @return void
     */
    public function registerHooks(): void
    {
        // GeneratePress layout customization
        add_filter('generate_sidebar_layout', [$this, 'customizeSidebar']);
        add_filter('generate_content_width', [$this, 'customizeContentWidth']);

        // Entity meta display
        add_action('generate_after_entry_title', [$this, 'addEntityMeta']);
        add_action('generate_after_entry_content', [$this, 'addEntityRelationships']);
        add_action('generate_after_entry_content', [$this, 'addEntityAttributes']);

        // Archive customization
        add_action('generate_before_main_content', [$this, 'addArchiveHeader']);
        add_filter('excerpt_length', [$this, 'customizeExcerptLength'], 999);

        // Body classes
        add_filter('body_class', [$this, 'addBodyClasses']);

        // Cache invalidation on post update
        add_action('save_post', [$this, 'invalidateCacheOnSave'], 10, 2);
    }

    /**
     * Customize sidebar layout for saga entities
     *
     * @param string $layout Current sidebar layout
     * @return string Modified sidebar layout
     */
    public function customizeSidebar(string $layout): string
    {
        // Check if we're on a saga entity post
        if (!$this->isSagaEntity()) {
            return $layout;
        }

        // Use right sidebar for entity posts
        return 'right-sidebar';
    }

    /**
     * Customize content width for saga entities
     *
     * @param string $width Current content width
     * @return string Modified content width
     */
    public function customizeContentWidth(string $width): string
    {
        if (!$this->isSagaEntity()) {
            return $width;
        }

        // Use contained width for better readability
        return 'contained';
    }

    /**
     * Add entity metadata after entry title
     *
     * @return void
     */
    public function addEntityMeta(): void
    {
        if (!is_singular() || !$this->isSagaEntity()) {
            return;
        }

        $entity = $this->helpers->getEntityByPostId(get_the_ID());

        if (!$entity) {
            return;
        }

        ?>
        <div class="saga-entity-meta">
            <div class="saga-entity-meta__grid">
                <div class="saga-entity-meta__item">
                    <span class="saga-entity-meta__label"><?php esc_html_e('Type', 'saga-manager-theme'); ?></span>
                    <div class="saga-entity-meta__value">
                        <?php echo $this->helpers->getEntityTypeBadge($entity->entity_type); ?>
                    </div>
                </div>

                <div class="saga-entity-meta__item">
                    <span class="saga-entity-meta__label"><?php esc_html_e('Importance', 'saga-manager-theme'); ?></span>
                    <div class="saga-entity-meta__value">
                        <?php echo $this->helpers->getImportanceScoreBar((int) $entity->importance_score); ?>
                    </div>
                </div>

                <?php if (!empty($entity->slug)): ?>
                <div class="saga-entity-meta__item">
                    <span class="saga-entity-meta__label"><?php esc_html_e('Slug', 'saga-manager-theme'); ?></span>
                    <span class="saga-entity-meta__value">
                        <?php echo esc_html($entity->slug); ?>
                    </span>
                </div>
                <?php endif; ?>
            </div>
        </div>
        <?php
    }

    /**
     * Add entity relationships after content
     *
     * @return void
     */
    public function addEntityRelationships(): void
    {
        if (!is_singular() || !$this->isSagaEntity()) {
            return;
        }

        $entity = $this->helpers->getEntityByPostId(get_the_ID());

        if (!$entity) {
            return;
        }

        $relationships = $this->helpers->getEntityRelationships((int) $entity->id);

        if (empty($relationships)) {
            return;
        }

        $grouped = $this->helpers->groupRelationshipsByType($relationships);

        ?>
        <div class="saga-relationships">
            <h2 class="saga-relationships__title">
                <?php esc_html_e('Relationships', 'saga-manager-theme'); ?>
            </h2>

            <?php foreach ($grouped as $type => $rels): ?>
                <div class="saga-relationships__group">
                    <h3 class="saga-relationships__group-title">
                        <?php echo esc_html($this->helpers->formatRelationshipType($type)); ?>
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

                                <?php echo $this->helpers->getRelationshipStrengthBadge((int) $rel->strength); ?>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endforeach; ?>
        </div>
        <?php
    }

    /**
     * Add entity attributes after content
     *
     * @return void
     */
    public function addEntityAttributes(): void
    {
        if (!is_singular() || !$this->isSagaEntity()) {
            return;
        }

        $entity = $this->helpers->getEntityByPostId(get_the_ID());

        if (!$entity) {
            return;
        }

        $attributes = $this->queries->getAttributeValues((int) $entity->id);

        if (empty($attributes)) {
            return;
        }

        ?>
        <div class="saga-entity-attributes">
            <h2 class="saga-entity-attributes__title">
                <?php esc_html_e('Attributes', 'saga-manager-theme'); ?>
            </h2>

            <div class="saga-entity-meta__grid">
                <?php foreach ($attributes as $attr): ?>
                    <div class="saga-entity-meta__item">
                        <span class="saga-entity-meta__label">
                            <?php echo esc_html($attr->display_name); ?>
                        </span>
                        <span class="saga-entity-meta__value">
                            <?php echo esc_html($this->helpers->formatAttributeValue($attr)); ?>
                        </span>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php
    }

    /**
     * Add archive header with entity counts
     *
     * @return void
     */
    public function addArchiveHeader(): void
    {
        if (!is_archive() && !is_home()) {
            return;
        }

        // Get current saga ID from query or default
        // TODO: Implement saga selection logic
        $sagaId = 1; // Default saga

        $counts = $this->queries->getEntityCountsByType($sagaId);
        $totalCount = array_sum($counts);

        if ($totalCount === 0) {
            return;
        }

        ?>
        <div class="saga-archive-header">
            <h1 class="saga-archive-header__title">
                <?php
                if (is_post_type_archive()) {
                    post_type_archive_title();
                } else {
                    esc_html_e('Saga Entities', 'saga-manager-theme');
                }
                ?>
            </h1>

            <div class="saga-archive-header__meta">
                <span class="saga-archive-header__count">
                    <?php
                    printf(
                        esc_html(_n('%d entity', '%d entities', $totalCount, 'saga-manager-theme')),
                        $totalCount
                    );
                    ?>
                </span>

                <div class="saga-archive-header__badges">
                    <?php foreach ($counts as $type => $count): ?>
                        <?php if ($count > 0): ?>
                            <?php echo $this->helpers->getEntityTypeBadge($type); ?>
                            <span><?php echo esc_html($count); ?></span>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * Customize excerpt length for saga entities
     *
     * @param int $length Current excerpt length
     * @return int Modified excerpt length
     */
    public function customizeExcerptLength(int $length): int
    {
        if ($this->isSagaEntity()) {
            return 40; // Longer excerpts for entity archives
        }

        return $length;
    }

    /**
     * Add custom body classes
     *
     * @param array $classes Current body classes
     * @return array Modified body classes
     */
    public function addBodyClasses(array $classes): array
    {
        if ($this->isSagaEntity()) {
            $classes[] = 'saga-entity-page';

            $entity = $this->helpers->getEntityByPostId(get_the_ID());

            if ($entity) {
                $classes[] = 'saga-entity-type-' . sanitize_html_class($entity->entity_type);
            }
        }

        return $classes;
    }

    /**
     * Invalidate cache when post is saved
     *
     * @param int $postId Post ID
     * @param \WP_Post $post Post object
     * @return void
     */
    public function invalidateCacheOnSave(int $postId, \WP_Post $post): void
    {
        // Skip autosave and revisions
        if (wp_is_post_autosave($postId) || wp_is_post_revision($postId)) {
            return;
        }

        $entity = $this->helpers->getEntityByPostId($postId);

        if ($entity) {
            // Invalidate all caches for this entity
            $cache = new SagaCache();
            $cache->invalidateAll((int) $entity->id, $postId);
        }
    }

    /**
     * Check if current post is a saga entity
     *
     * @return bool True if current post is linked to a saga entity
     */
    private function isSagaEntity(): bool
    {
        if (!is_singular() && !is_archive()) {
            return false;
        }

        if (is_singular()) {
            $entity = $this->helpers->getEntityByPostId(get_the_ID());
            return $entity !== null;
        }

        return false;
    }
}
