<?php
/**
 * Template part for displaying annotations
 *
 * @package Saga_Manager_Theme
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!is_user_logged_in()) {
    return;
}

$entity_id = get_the_ID();
$annotations = Saga_Annotations::get_entity_annotations($entity_id);

if (empty($annotations)) {
    return;
}
?>

<div class="saga-annotations" id="saga-annotations-display">
    <h3 class="saga-annotations__title">
        <svg class="saga-annotations__title-icon" width="24" height="24" viewBox="0 0 24 24" fill="none" aria-hidden="true">
            <path d="M9 5H7C5.89543 5 5 5.89543 5 7V19C5 20.1046 5.89543 21 7 21H17C18.1046 21 19 20.1046 19 19V7C19 5.89543 18.1046 5 17 5H15" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
            <rect x="9" y="3" width="6" height="4" rx="1" stroke="currentColor" stroke-width="2"/>
            <path d="M9 13H15M9 17H13" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
        </svg>
        <?php
        /* translators: %d: number of annotations */
        printf(esc_html(_n('Your Note (%d)', 'Your Notes (%d)', count($annotations), 'saga-manager-theme')), count($annotations));
        ?>
    </h3>

    <div class="saga-annotations__list" role="list">
        <?php foreach ($annotations as $annotation): ?>
            <?php
            $annotation_id = esc_attr($annotation['id']);
            $created_date = !empty($annotation['created_at']) ? mysql2date('F j, Y', $annotation['created_at']) : '';
            $updated_date = !empty($annotation['updated_at']) ? mysql2date('F j, Y g:i a', $annotation['updated_at']) : '';
            $is_public = ($annotation['visibility'] ?? 'private') === 'public';
            ?>

            <article class="saga-annotation" id="annotation-<?php echo $annotation_id; ?>" data-annotation-id="<?php echo $annotation_id; ?>" role="listitem">
                <div class="saga-annotation__header">
                    <div class="saga-annotation__meta">
                        <?php if ($is_public): ?>
                            <span class="saga-annotation__visibility saga-annotation__visibility--public" title="<?php esc_attr_e('Public annotation', 'saga-manager-theme'); ?>">
                                <svg width="16" height="16" viewBox="0 0 16 16" fill="none" aria-hidden="true">
                                    <path d="M8 3C4.5 3 1.73 5.11 1 8C1.73 10.89 4.5 13 8 13C11.5 13 14.27 10.89 15 8C14.27 5.11 11.5 3 8 3Z" stroke="currentColor" stroke-width="1.5"/>
                                    <circle cx="8" cy="8" r="2" stroke="currentColor" stroke-width="1.5"/>
                                </svg>
                                <span class="screen-reader-text"><?php esc_html_e('Public', 'saga-manager-theme'); ?></span>
                            </span>
                        <?php else: ?>
                            <span class="saga-annotation__visibility saga-annotation__visibility--private" title="<?php esc_attr_e('Private annotation', 'saga-manager-theme'); ?>">
                                <svg width="16" height="16" viewBox="0 0 16 16" fill="none" aria-hidden="true">
                                    <rect x="3" y="7" width="10" height="7" rx="1" stroke="currentColor" stroke-width="1.5"/>
                                    <path d="M5 7V5C5 3.34315 6.34315 2 8 2V2C9.65685 2 11 3.34315 11 5V7" stroke="currentColor" stroke-width="1.5"/>
                                </svg>
                                <span class="screen-reader-text"><?php esc_html_e('Private', 'saga-manager-theme'); ?></span>
                            </span>
                        <?php endif; ?>

                        <time class="saga-annotation__date" datetime="<?php echo esc_attr($annotation['updated_at'] ?? ''); ?>">
                            <?php
                            if ($created_date === $updated_date) {
                                /* translators: %s: date */
                                printf(esc_html__('Created %s', 'saga-manager-theme'), esc_html($created_date));
                            } else {
                                /* translators: %s: date and time */
                                printf(esc_html__('Updated %s', 'saga-manager-theme'), esc_html($updated_date));
                            }
                            ?>
                        </time>
                    </div>

                    <div class="saga-annotation__actions">
                        <button
                            type="button"
                            class="saga-annotation__action saga-annotation__edit"
                            data-annotation-id="<?php echo $annotation_id; ?>"
                            aria-label="<?php esc_attr_e('Edit annotation', 'saga-manager-theme'); ?>"
                            title="<?php esc_attr_e('Edit', 'saga-manager-theme'); ?>"
                        >
                            <svg width="16" height="16" viewBox="0 0 16 16" fill="none" aria-hidden="true">
                                <path d="M11.5 2L14 4.5L5 13.5H2.5V11L11.5 2Z" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                            </svg>
                            <span class="screen-reader-text"><?php esc_html_e('Edit', 'saga-manager-theme'); ?></span>
                        </button>

                        <button
                            type="button"
                            class="saga-annotation__action saga-annotation__delete"
                            data-annotation-id="<?php echo $annotation_id; ?>"
                            aria-label="<?php esc_attr_e('Delete annotation', 'saga-manager-theme'); ?>"
                            title="<?php esc_attr_e('Delete', 'saga-manager-theme'); ?>"
                        >
                            <svg width="16" height="16" viewBox="0 0 16 16" fill="none" aria-hidden="true">
                                <path d="M3 4H13M12 4V13C12 13.5523 11.5523 14 11 14H5C4.44772 14 4 13.5523 4 13V4M6 4V3C6 2.44772 6.44772 2 7 2H9C9.55228 2 10 2.44772 10 3V4M6.5 7V11M9.5 7V11" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
                            </svg>
                            <span class="screen-reader-text"><?php esc_html_e('Delete', 'saga-manager-theme'); ?></span>
                        </button>

                        <button
                            type="button"
                            class="saga-annotation__action saga-annotation__toggle"
                            aria-expanded="true"
                            aria-controls="annotation-content-<?php echo $annotation_id; ?>"
                            aria-label="<?php esc_attr_e('Toggle annotation', 'saga-manager-theme'); ?>"
                        >
                            <svg width="16" height="16" viewBox="0 0 16 16" fill="none" aria-hidden="true" class="saga-annotation__toggle-icon">
                                <path d="M4 6L8 10L12 6" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                            </svg>
                            <span class="screen-reader-text"><?php esc_html_e('Toggle', 'saga-manager-theme'); ?></span>
                        </button>
                    </div>
                </div>

                <div class="saga-annotation__body" id="annotation-content-<?php echo $annotation_id; ?>">
                    <?php if (!empty($annotation['quote'])): ?>
                        <blockquote class="saga-annotation__quote">
                            <?php echo wp_kses_post($annotation['quote']); ?>
                        </blockquote>
                    <?php endif; ?>

                    <div class="saga-annotation__content">
                        <?php echo wp_kses_post($annotation['content']); ?>
                    </div>

                    <?php if (!empty($annotation['tags'])): ?>
                        <div class="saga-annotation__tags">
                            <?php foreach ($annotation['tags'] as $tag): ?>
                                <span class="saga-annotation__tag">
                                    <svg width="12" height="12" viewBox="0 0 12 12" fill="none" aria-hidden="true">
                                        <path d="M2 2L6 1L10 2L11 6L10 10L6 11L2 10L1 6L2 2Z" stroke="currentColor" stroke-width="1" stroke-linecap="round" stroke-linejoin="round"/>
                                    </svg>
                                    <?php echo esc_html($tag); ?>
                                </span>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </article>
        <?php endforeach; ?>
    </div>
</div>
