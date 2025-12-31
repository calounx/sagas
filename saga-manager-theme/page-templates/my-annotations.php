<?php
/**
 * Template Name: My Annotations
 *
 * User annotations dashboard page template
 *
 * @package Saga_Manager_Theme
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!is_user_logged_in()) {
    wp_safe_redirect(wp_login_url(get_permalink()));
    exit;
}

get_header();

$annotations = Saga_Annotations::get_user_annotations();
$user_tags = Saga_Annotations::get_user_tags();
?>

<div class="saga-annotations-dashboard">
    <div class="saga-annotations-dashboard__header">
        <h1 class="saga-annotations-dashboard__title">
            <?php esc_html_e('My Annotations', 'saga-manager-theme'); ?>
        </h1>

        <div class="saga-annotations-dashboard__stats">
            <div class="saga-stat">
                <span class="saga-stat__value"><?php echo count($annotations); ?></span>
                <span class="saga-stat__label"><?php esc_html_e('Total Notes', 'saga-manager-theme'); ?></span>
            </div>
            <div class="saga-stat">
                <span class="saga-stat__value"><?php echo count($user_tags); ?></span>
                <span class="saga-stat__label"><?php esc_html_e('Tags', 'saga-manager-theme'); ?></span>
            </div>
        </div>
    </div>

    <?php if (empty($annotations)): ?>
        <div class="saga-annotations-dashboard__empty">
            <svg class="saga-annotations-dashboard__empty-icon" width="64" height="64" viewBox="0 0 64 64" fill="none" aria-hidden="true">
                <path d="M20 12H44C46.2091 12 48 13.7909 48 16V48C48 50.2091 46.2091 52 44 52H20C17.7909 52 16 50.2091 16 48V16C16 13.7909 17.7909 12 20 12Z" stroke="currentColor" stroke-width="3" stroke-linecap="round"/>
                <path d="M24 24H40M24 32H40M24 40H32" stroke="currentColor" stroke-width="3" stroke-linecap="round"/>
            </svg>
            <h2 class="saga-annotations-dashboard__empty-title">
                <?php esc_html_e('No annotations yet', 'saga-manager-theme'); ?>
            </h2>
            <p class="saga-annotations-dashboard__empty-text">
                <?php esc_html_e('Start adding notes to entities to keep track of important information and insights.', 'saga-manager-theme'); ?>
            </p>
            <a href="<?php echo esc_url(home_url('/')); ?>" class="button button-primary">
                <?php esc_html_e('Browse Entities', 'saga-manager-theme'); ?>
            </a>
        </div>
    <?php else: ?>
        <div class="saga-annotations-dashboard__controls">
            <div class="saga-annotations-dashboard__search">
                <label for="annotation-search" class="screen-reader-text">
                    <?php esc_html_e('Search annotations', 'saga-manager-theme'); ?>
                </label>
                <input
                    type="search"
                    id="annotation-search"
                    class="saga-annotations-dashboard__search-input"
                    placeholder="<?php esc_attr_e('Search your notes...', 'saga-manager-theme'); ?>"
                    aria-label="<?php esc_attr_e('Search annotations', 'saga-manager-theme'); ?>"
                >
                <button type="button" class="saga-annotations-dashboard__search-button" aria-label="<?php esc_attr_e('Search', 'saga-manager-theme'); ?>">
                    <svg width="20" height="20" viewBox="0 0 20 20" fill="none" aria-hidden="true">
                        <circle cx="8" cy="8" r="5" stroke="currentColor" stroke-width="2"/>
                        <path d="M12 12L17 17" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                    </svg>
                </button>
            </div>

            <div class="saga-annotations-dashboard__filters">
                <label for="filter-tag" class="saga-annotations-dashboard__filter-label">
                    <?php esc_html_e('Filter by tag:', 'saga-manager-theme'); ?>
                </label>
                <select id="filter-tag" class="saga-annotations-dashboard__filter-select">
                    <option value=""><?php esc_html_e('All tags', 'saga-manager-theme'); ?></option>
                    <?php foreach ($user_tags as $tag): ?>
                        <option value="<?php echo esc_attr($tag); ?>"><?php echo esc_html($tag); ?></option>
                    <?php endforeach; ?>
                </select>

                <label for="filter-visibility" class="saga-annotations-dashboard__filter-label">
                    <?php esc_html_e('Visibility:', 'saga-manager-theme'); ?>
                </label>
                <select id="filter-visibility" class="saga-annotations-dashboard__filter-select">
                    <option value=""><?php esc_html_e('All', 'saga-manager-theme'); ?></option>
                    <option value="private"><?php esc_html_e('Private', 'saga-manager-theme'); ?></option>
                    <option value="public"><?php esc_html_e('Public', 'saga-manager-theme'); ?></option>
                </select>

                <label for="sort-by" class="saga-annotations-dashboard__filter-label">
                    <?php esc_html_e('Sort by:', 'saga-manager-theme'); ?>
                </label>
                <select id="sort-by" class="saga-annotations-dashboard__filter-select">
                    <option value="updated-desc"><?php esc_html_e('Recently Updated', 'saga-manager-theme'); ?></option>
                    <option value="updated-asc"><?php esc_html_e('Oldest Updated', 'saga-manager-theme'); ?></option>
                    <option value="created-desc"><?php esc_html_e('Recently Created', 'saga-manager-theme'); ?></option>
                    <option value="created-asc"><?php esc_html_e('Oldest Created', 'saga-manager-theme'); ?></option>
                </select>
            </div>

            <div class="saga-annotations-dashboard__actions">
                <button
                    type="button"
                    id="export-annotations"
                    class="button"
                    aria-label="<?php esc_attr_e('Export annotations', 'saga-manager-theme'); ?>"
                >
                    <svg width="16" height="16" viewBox="0 0 16 16" fill="none" aria-hidden="true">
                        <path d="M8 1V10M8 10L5 7M8 10L11 7" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                        <path d="M1 11V13C1 14.1046 1.89543 15 3 15H13C14.1046 15 15 14.1046 15 13V11" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
                    </svg>
                    <?php esc_html_e('Export', 'saga-manager-theme'); ?>
                </button>

                <div id="export-menu" class="saga-export-menu" style="display: none;">
                    <button type="button" class="saga-export-menu__option" data-format="markdown">
                        <?php esc_html_e('Export as Markdown', 'saga-manager-theme'); ?>
                    </button>
                    <button type="button" class="saga-export-menu__option" data-format="json">
                        <?php esc_html_e('Export as JSON', 'saga-manager-theme'); ?>
                    </button>
                </div>
            </div>
        </div>

        <div id="annotations-list" class="saga-annotations-dashboard__list">
            <?php
            // Sort annotations by updated date (most recent first)
            usort($annotations, function($a, $b) {
                return strtotime($b['updated_at'] ?? '0') - strtotime($a['updated_at'] ?? '0');
            });

            foreach ($annotations as $annotation):
                $annotation_id = esc_attr($annotation['id']);
                $entity_id = $annotation['entity_id'] ?? 0;
                $entity_title = get_the_title($entity_id);
                $entity_url = get_permalink($entity_id);
                $created_date = !empty($annotation['created_at']) ? mysql2date('F j, Y', $annotation['created_at']) : '';
                $updated_date = !empty($annotation['updated_at']) ? mysql2date('F j, Y g:i a', $annotation['updated_at']) : '';
                $is_public = ($annotation['visibility'] ?? 'private') === 'public';
                ?>

                <article
                    class="saga-annotation-card"
                    data-annotation-id="<?php echo $annotation_id; ?>"
                    data-entity-id="<?php echo esc_attr($entity_id); ?>"
                    data-tags="<?php echo esc_attr(json_encode($annotation['tags'] ?? [])); ?>"
                    data-visibility="<?php echo esc_attr($annotation['visibility'] ?? 'private'); ?>"
                    data-created="<?php echo esc_attr($annotation['created_at'] ?? ''); ?>"
                    data-updated="<?php echo esc_attr($annotation['updated_at'] ?? ''); ?>"
                >
                    <div class="saga-annotation-card__header">
                        <h3 class="saga-annotation-card__entity">
                            <a href="<?php echo esc_url($entity_url); ?>" class="saga-annotation-card__entity-link">
                                <?php echo esc_html($entity_title); ?>
                            </a>
                        </h3>

                        <div class="saga-annotation-card__meta">
                            <?php if ($is_public): ?>
                                <span class="saga-annotation-card__badge saga-annotation-card__badge--public">
                                    <?php esc_html_e('Public', 'saga-manager-theme'); ?>
                                </span>
                            <?php else: ?>
                                <span class="saga-annotation-card__badge saga-annotation-card__badge--private">
                                    <?php esc_html_e('Private', 'saga-manager-theme'); ?>
                                </span>
                            <?php endif; ?>

                            <time class="saga-annotation-card__date" datetime="<?php echo esc_attr($annotation['updated_at'] ?? ''); ?>">
                                <?php echo esc_html($updated_date); ?>
                            </time>
                        </div>
                    </div>

                    <?php if (!empty($annotation['quote'])): ?>
                        <blockquote class="saga-annotation-card__quote">
                            <?php echo wp_kses_post($annotation['quote']); ?>
                        </blockquote>
                    <?php endif; ?>

                    <div class="saga-annotation-card__content">
                        <?php
                        $content = wp_strip_all_tags($annotation['content']);
                        echo esc_html(wp_trim_words($content, 50, '...'));
                        ?>
                    </div>

                    <?php if (!empty($annotation['tags'])): ?>
                        <div class="saga-annotation-card__tags">
                            <?php foreach ($annotation['tags'] as $tag): ?>
                                <span class="saga-annotation-card__tag">
                                    <?php echo esc_html($tag); ?>
                                </span>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>

                    <div class="saga-annotation-card__actions">
                        <a href="<?php echo esc_url($entity_url . '#annotation-' . $annotation_id); ?>" class="button button-small">
                            <?php esc_html_e('View', 'saga-manager-theme'); ?>
                        </a>
                        <button
                            type="button"
                            class="saga-annotation-card__delete button button-small"
                            data-annotation-id="<?php echo $annotation_id; ?>"
                        >
                            <?php esc_html_e('Delete', 'saga-manager-theme'); ?>
                        </button>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>

        <div id="no-results" class="saga-annotations-dashboard__no-results" style="display: none;">
            <p><?php esc_html_e('No annotations found matching your filters.', 'saga-manager-theme'); ?></p>
        </div>
    <?php endif; ?>
</div>

<?php get_footer(); ?>
