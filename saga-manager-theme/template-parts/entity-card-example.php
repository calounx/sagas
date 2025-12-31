<?php
/**
 * Template Part: Entity Card (Example with Bookmark Button)
 *
 * Example entity card template showing integration with collections/bookmarks.
 * Copy this pattern to your actual entity card template.
 *
 * @package Saga_Manager_Theme
 */

if (!defined('ABSPATH')) {
    exit;
}

$entity_id = get_the_ID();
$entity = saga_get_entity($entity_id);

if (!$entity) {
    return;
}

$entity_type = $entity->entity_type ?? '';
$importance_score = (int) ($entity->importance_score ?? 50);
?>

<article id="post-<?php echo esc_attr($entity_id); ?>" <?php post_class('saga-entity-card'); ?>>

    <!-- Featured Image -->
    <?php if (has_post_thumbnail()) : ?>
        <div class="saga-entity-card__thumbnail">
            <a href="<?php the_permalink(); ?>">
                <?php the_post_thumbnail('saga-entity-card', ['alt' => get_the_title()]); ?>
            </a>

            <!-- Bookmark button overlay (icon-only) -->
            <div class="saga-entity-card__bookmark-overlay">
                <?php
                saga_bookmark_button($entity_id, 'favorites', [
                    'variant' => 'icon-only',
                    'show_text' => false,
                    'class' => 'saga-bookmark-overlay-btn',
                ]);
                ?>
            </div>
        </div>
    <?php endif; ?>

    <!-- Card Header -->
    <header class="saga-entity-card__header">
        <div class="saga-entity-card__meta">
            <?php if ($entity_type) : ?>
                <span class="saga-entity-type-badge saga-entity-type-<?php echo esc_attr($entity_type); ?>">
                    <?php echo esc_html(ucfirst($entity_type)); ?>
                </span>
            <?php endif; ?>

            <?php if ($importance_score > 0) : ?>
                <span class="saga-entity-importance" title="<?php echo esc_attr(sprintf(__('Importance: %d/100', 'saga-manager'), $importance_score)); ?>">
                    <?php
                    $stars = round($importance_score / 20);
                    echo str_repeat('⭐', $stars);
                    ?>
                </span>
            <?php endif; ?>
        </div>

        <h3 class="saga-entity-card__title">
            <a href="<?php the_permalink(); ?>">
                <?php the_title(); ?>
            </a>
        </h3>
    </header>

    <!-- Card Content -->
    <div class="saga-entity-card__content">
        <?php if (has_excerpt()) : ?>
            <div class="saga-entity-card__excerpt">
                <?php the_excerpt(); ?>
            </div>
        <?php endif; ?>

        <!-- Entity relationships preview -->
        <?php
        $relationships = saga_get_relationships($entity->id ?? 0, 'outgoing');
        if (!empty($relationships)) :
            $relationship_count = count($relationships);
        ?>
            <div class="saga-entity-card__relationships">
                <span class="saga-relationships-count">
                    <?php
                    printf(
                        /* translators: %d: number of relationships */
                        _n('%d connection', '%d connections', $relationship_count, 'saga-manager'),
                        $relationship_count
                    );
                    ?>
                </span>
            </div>
        <?php endif; ?>
    </div>

    <!-- Card Footer -->
    <footer class="saga-entity-card__footer">
        <div class="saga-entity-card__actions">
            <a href="<?php the_permalink(); ?>" class="saga-btn saga-btn-primary">
                <?php esc_html_e('View Details', 'saga-manager'); ?>
            </a>

            <!-- Bookmark button (with text) -->
            <?php
            saga_bookmark_button($entity_id, 'favorites', [
                'variant' => 'default',
                'button_text' => __('Bookmark', 'saga-manager'),
                'show_text' => true,
            ]);
            ?>
        </div>

        <!-- Bookmark status indicator -->
        <?php if (saga_is_bookmarked($entity_id)) : ?>
            <div class="saga-entity-card__bookmark-indicator">
                <span class="saga-bookmarked-label">
                    <?php esc_html_e('In your collection', 'saga-manager'); ?>
                </span>
            </div>
        <?php endif; ?>
    </footer>

</article>

<?php
/**
 * Example CSS for entity card (add to your theme stylesheet)
 *
 * .saga-entity-card {
 *     position: relative;
 *     background: #fff;
 *     border: 1px solid rgba(0, 0, 0, 0.1);
 *     border-radius: 0.5rem;
 *     overflow: hidden;
 *     transition: all 0.2s ease-in-out;
 * }
 *
 * .saga-entity-card:hover {
 *     box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
 *     transform: translateY(-2px);
 * }
 *
 * .saga-entity-card__thumbnail {
 *     position: relative;
 *     overflow: hidden;
 * }
 *
 * .saga-entity-card__thumbnail img {
 *     width: 100%;
 *     height: auto;
 *     transition: transform 0.3s ease-in-out;
 * }
 *
 * .saga-entity-card:hover .saga-entity-card__thumbnail img {
 *     transform: scale(1.05);
 * }
 *
 * .saga-entity-card__bookmark-overlay {
 *     position: absolute;
 *     top: 0.5rem;
 *     right: 0.5rem;
 *     opacity: 0;
 *     transition: opacity 0.2s ease-in-out;
 * }
 *
 * .saga-entity-card:hover .saga-entity-card__bookmark-overlay {
 *     opacity: 1;
 * }
 *
 * .saga-bookmark-overlay-btn {
 *     background: rgba(255, 255, 255, 0.9);
 *     backdrop-filter: blur(4px);
 *     box-shadow: 0 2px 8px rgba(0, 0, 0, 0.2);
 * }
 *
 * .saga-entity-card__header {
 *     padding: 1rem;
 * }
 *
 * .saga-entity-card__meta {
 *     display: flex;
 *     align-items: center;
 *     gap: 0.5rem;
 *     margin-bottom: 0.5rem;
 * }
 *
 * .saga-entity-type-badge {
 *     display: inline-block;
 *     padding: 0.25rem 0.75rem;
 *     background: rgba(0, 124, 186, 0.1);
 *     border-radius: 0.25rem;
 *     color: #007cba;
 *     font-size: 0.75rem;
 *     font-weight: 500;
 *     text-transform: uppercase;
 * }
 *
 * .saga-entity-card__title {
 *     margin: 0;
 *     font-size: 1.25rem;
 *     font-weight: 600;
 * }
 *
 * .saga-entity-card__title a {
 *     color: #333;
 *     text-decoration: none;
 *     transition: color 0.2s ease-in-out;
 * }
 *
 * .saga-entity-card__title a:hover {
 *     color: #007cba;
 * }
 *
 * .saga-entity-card__content {
 *     padding: 0 1rem 1rem;
 * }
 *
 * .saga-entity-card__excerpt {
 *     color: #666;
 *     font-size: 0.875rem;
 *     line-height: 1.6;
 * }
 *
 * .saga-entity-card__relationships {
 *     margin-top: 0.75rem;
 *     padding-top: 0.75rem;
 *     border-top: 1px solid rgba(0, 0, 0, 0.1);
 * }
 *
 * .saga-relationships-count {
 *     color: #666;
 *     font-size: 0.75rem;
 * }
 *
 * .saga-entity-card__footer {
 *     padding: 1rem;
 *     background: #f8f9fa;
 *     border-top: 1px solid rgba(0, 0, 0, 0.1);
 * }
 *
 * .saga-entity-card__actions {
 *     display: flex;
 *     gap: 0.5rem;
 *     align-items: center;
 * }
 *
 * .saga-btn {
 *     display: inline-flex;
 *     align-items: center;
 *     justify-content: center;
 *     padding: 0.5rem 1rem;
 *     border: none;
 *     border-radius: 0.25rem;
 *     font-size: 0.875rem;
 *     font-weight: 500;
 *     text-decoration: none;
 *     cursor: pointer;
 *     transition: all 0.2s ease-in-out;
 * }
 *
 * .saga-btn-primary {
 *     background: #007cba;
 *     color: #fff;
 * }
 *
 * .saga-btn-primary:hover {
 *     background: #005a87;
 * }
 *
 * .saga-entity-card__bookmark-indicator {
 *     margin-top: 0.5rem;
 *     padding: 0.5rem;
 *     background: rgba(0, 124, 186, 0.1);
 *     border-radius: 0.25rem;
 *     text-align: center;
 * }
 *
 * .saga-bookmarked-label {
 *     color: #007cba;
 *     font-size: 0.75rem;
 *     font-weight: 500;
 * }
 */
?>
