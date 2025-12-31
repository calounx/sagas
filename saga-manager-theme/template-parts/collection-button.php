<?php
/**
 * Template Part: Collection Bookmark Button
 *
 * Displays a bookmark button for adding/removing entities to collections.
 * Supports both logged-in users (server-side) and guests (localStorage).
 *
 * @package Saga_Manager_Theme
 *
 * @param int    $entity_id        Entity post ID (required)
 * @param string $collection       Collection slug (default: 'favorites')
 * @param string $variant          Button variant: 'default', 'icon-only' (default: 'default')
 * @param string $button_text      Custom button text (default: 'Bookmark')
 * @param bool   $show_text        Show text label (default: true)
 * @param string $class            Additional CSS classes
 */

if (!defined('ABSPATH')) {
    exit;
}

// Get parameters
$entity_id = isset($args['entity_id']) ? absint($args['entity_id']) : get_the_ID();
$collection = isset($args['collection']) ? sanitize_key($args['collection']) : 'favorites';
$variant = isset($args['variant']) ? sanitize_key($args['variant']) : 'default';
$button_text = isset($args['button_text']) ? sanitize_text_field($args['button_text']) : __('Bookmark', 'saga-manager');
$show_text = isset($args['show_text']) ? (bool) $args['show_text'] : true;
$additional_class = isset($args['class']) ? sanitize_html_class($args['class']) : '';

// Validate entity ID
if (!$entity_id) {
    return;
}

// Check if entity is in collection
$is_bookmarked = false;

if (is_user_logged_in()) {
    $collections_manager = new Saga_Collections();
    $user_id = get_current_user_id();
    $is_bookmarked = $collections_manager->is_in_collection($user_id, $collection, $entity_id);
}
// For guests, JavaScript will handle state from localStorage

// Build CSS classes
$button_classes = ['saga-bookmark-btn'];

if ($is_bookmarked) {
    $button_classes[] = 'is-bookmarked';
}

if ($variant === 'icon-only' || !$show_text) {
    $button_classes[] = 'icon-only';
}

if ($additional_class) {
    $button_classes[] = $additional_class;
}

// Aria labels
$label_add = sprintf(__('Add to %s', 'saga-manager'), ucfirst($collection));
$label_remove = sprintf(__('Remove from %s', 'saga-manager'), ucfirst($collection));

// Heart icon (filled if bookmarked, empty otherwise)
$heart_icon = $is_bookmarked ? '&#9829;' : '&#9825;';
?>

<button
    type="button"
    class="<?php echo esc_attr(implode(' ', $button_classes)); ?>"
    data-entity-id="<?php echo esc_attr($entity_id); ?>"
    data-collection="<?php echo esc_attr($collection); ?>"
    data-label-add="<?php echo esc_attr($label_add); ?>"
    data-label-remove="<?php echo esc_attr($label_remove); ?>"
    aria-label="<?php echo esc_attr($is_bookmarked ? $label_remove : $label_add); ?>"
    title="<?php echo esc_attr($is_bookmarked ? $label_remove : $label_add); ?>"
>
    <span class="saga-bookmark-icon" aria-hidden="true"><?php echo $heart_icon; ?></span>
    <?php if ($show_text && $variant !== 'icon-only') : ?>
        <span class="saga-bookmark-text"><?php echo esc_html($button_text); ?></span>
    <?php endif; ?>
</button>
