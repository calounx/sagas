<?php
/**
 * Template part for displaying the annotation form
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
$annotation_id = isset($args['annotation_id']) ? $args['annotation_id'] : '';
$annotation = null;

if ($annotation_id) {
    $annotation = Saga_Annotations::get_annotation($annotation_id);
}

$content = $annotation['content'] ?? '';
$quote = $annotation['quote'] ?? '';
$section = $annotation['section'] ?? '';
$tags = $annotation['tags'] ?? [];
$visibility = $annotation['visibility'] ?? 'private';
?>

<div id="saga-annotation-modal" class="saga-annotation-modal" role="dialog" aria-labelledby="annotation-modal-title" aria-modal="true" style="display: none;">
    <div class="saga-annotation-modal__overlay" aria-hidden="true"></div>

    <div class="saga-annotation-modal__content">
        <div class="saga-annotation-modal__header">
            <h2 id="annotation-modal-title" class="saga-annotation-modal__title">
                <?php echo $annotation_id ? esc_html__('Edit Annotation', 'saga-manager-theme') : esc_html__('Add Annotation', 'saga-manager-theme'); ?>
            </h2>
            <button type="button" class="saga-annotation-modal__close" aria-label="<?php esc_attr_e('Close', 'saga-manager-theme'); ?>">
                <span aria-hidden="true">&times;</span>
            </button>
        </div>

        <form id="saga-annotation-form" class="saga-annotation-form">
            <input type="hidden" name="entity_id" id="annotation-entity-id" value="<?php echo esc_attr($entity_id); ?>">
            <input type="hidden" name="annotation_id" id="annotation-id" value="<?php echo esc_attr($annotation_id); ?>">
            <input type="hidden" name="section" id="annotation-section" value="<?php echo esc_attr($section); ?>">

            <?php if (!empty($quote)): ?>
            <div class="saga-annotation-form__quote-container">
                <label for="annotation-quote" class="saga-annotation-form__label">
                    <?php esc_html_e('Quoted Text:', 'saga-manager-theme'); ?>
                </label>
                <div id="annotation-quote-display" class="saga-annotation-form__quote">
                    <?php echo wp_kses_post($quote); ?>
                </div>
                <input type="hidden" name="quote" id="annotation-quote" value="<?php echo esc_attr($quote); ?>">
                <button type="button" class="saga-annotation-form__remove-quote button button-small" aria-label="<?php esc_attr_e('Remove quote', 'saga-manager-theme'); ?>">
                    <?php esc_html_e('Remove Quote', 'saga-manager-theme'); ?>
                </button>
            </div>
            <?php else: ?>
            <input type="hidden" name="quote" id="annotation-quote" value="">
            <?php endif; ?>

            <div class="saga-annotation-form__field">
                <label for="annotation-content" class="saga-annotation-form__label">
                    <?php esc_html_e('Your Note:', 'saga-manager-theme'); ?>
                    <span class="required" aria-label="<?php esc_attr_e('required', 'saga-manager-theme'); ?>">*</span>
                </label>
                <?php
                wp_editor($content, 'annotation-content', [
                    'textarea_name' => 'content',
                    'media_buttons' => false,
                    'teeny' => true,
                    'quicktags' => true,
                    'textarea_rows' => 8,
                    'tinymce' => [
                        'toolbar1' => 'bold,italic,underline,strikethrough,bullist,numlist,link,unlink,blockquote,undo,redo',
                        'toolbar2' => '',
                        'height' => 200,
                        'content_css' => get_template_directory_uri() . '/assets/css/editor-style.css',
                    ],
                ]);
                ?>
                <p class="saga-annotation-form__helper">
                    <?php esc_html_e('Maximum 5,000 characters', 'saga-manager-theme'); ?>
                </p>
            </div>

            <div class="saga-annotation-form__field">
                <label for="annotation-tags" class="saga-annotation-form__label">
                    <?php esc_html_e('Tags:', 'saga-manager-theme'); ?>
                </label>
                <input
                    type="text"
                    name="tags"
                    id="annotation-tags"
                    class="saga-annotation-form__input"
                    value="<?php echo esc_attr(implode(', ', $tags)); ?>"
                    placeholder="<?php esc_attr_e('Add tags (comma separated, max 5)', 'saga-manager-theme'); ?>"
                    aria-describedby="tags-description"
                >
                <p id="tags-description" class="saga-annotation-form__helper">
                    <?php esc_html_e('Separate tags with commas. Maximum 5 tags.', 'saga-manager-theme'); ?>
                </p>
                <div id="annotation-tags-suggestions" class="saga-annotation-form__tag-suggestions" role="listbox"></div>
            </div>

            <div class="saga-annotation-form__field">
                <fieldset>
                    <legend class="saga-annotation-form__label">
                        <?php esc_html_e('Visibility:', 'saga-manager-theme'); ?>
                    </legend>
                    <div class="saga-annotation-form__radio-group">
                        <label class="saga-annotation-form__radio-label">
                            <input
                                type="radio"
                                name="visibility"
                                value="private"
                                <?php checked($visibility, 'private'); ?>
                                class="saga-annotation-form__radio"
                            >
                            <span><?php esc_html_e('Private (only you can see)', 'saga-manager-theme'); ?></span>
                        </label>
                        <label class="saga-annotation-form__radio-label">
                            <input
                                type="radio"
                                name="visibility"
                                value="public"
                                <?php checked($visibility, 'public'); ?>
                                class="saga-annotation-form__radio"
                            >
                            <span><?php esc_html_e('Public (visible to all users)', 'saga-manager-theme'); ?></span>
                        </label>
                    </div>
                </fieldset>
            </div>

            <div class="saga-annotation-form__actions">
                <button type="button" class="saga-annotation-form__cancel button">
                    <?php esc_html_e('Cancel', 'saga-manager-theme'); ?>
                </button>
                <button type="submit" class="saga-annotation-form__submit button button-primary">
                    <span class="saga-annotation-form__submit-text">
                        <?php echo $annotation_id ? esc_html__('Update Annotation', 'saga-manager-theme') : esc_html__('Save Annotation', 'saga-manager-theme'); ?>
                    </span>
                    <span class="saga-annotation-form__submit-spinner spinner" style="display: none;"></span>
                </button>
            </div>

            <div class="saga-annotation-form__message" role="alert" aria-live="polite"></div>
        </form>
    </div>
</div>

<button
    type="button"
    id="saga-add-annotation-button"
    class="saga-add-annotation-button button"
    aria-label="<?php esc_attr_e('Add new annotation', 'saga-manager-theme'); ?>"
>
    <svg class="saga-add-annotation-button__icon" width="20" height="20" viewBox="0 0 20 20" fill="none" aria-hidden="true">
        <path d="M10 5V15M5 10H15" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
    </svg>
    <span><?php esc_html_e('Add Note', 'saga-manager-theme'); ?></span>
</button>
