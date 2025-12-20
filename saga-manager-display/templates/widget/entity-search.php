<?php
/**
 * Template: Widget - Entity Search
 *
 * @package SagaManagerDisplay
 * @var string $placeholder Search placeholder
 * @var string $button_text Button text
 * @var string $results_page URL of results page (empty for AJAX)
 * @var array $entity_types Available entity types
 * @var array $options Display options
 */

defined('ABSPATH') || exit;

$form_action = $results_page ?: '';
$is_ajax = empty($results_page);
?>

<div class="saga-widget saga-widget-search">
    <form
        class="saga-widget-search__form"
        action="<?php echo esc_url($form_action); ?>"
        method="get"
        <?php echo $is_ajax ? 'data-ajax="true"' : ''; ?>
    >
        <div class="saga-widget-search__input-wrapper">
            <input
                type="search"
                name="saga_search"
                class="saga-widget-search__input saga-input"
                placeholder="<?php echo esc_attr($placeholder); ?>"
                aria-label="<?php echo esc_attr($placeholder); ?>"
            >
            <button type="submit" class="saga-widget-search__button saga-button">
                <?php echo esc_html($button_text); ?>
            </button>
        </div>

        <?php if ($options['show_type_filter'] && !empty($entity_types)): ?>
            <select
                name="saga_type"
                class="saga-widget-search__filter saga-select"
                aria-label="<?php esc_attr_e('Entity type', 'saga-manager-display'); ?>"
            >
                <option value=""><?php esc_html_e('All Types', 'saga-manager-display'); ?></option>
                <?php foreach ($entity_types as $type): ?>
                    <?php
                    $type_key = is_array($type) ? ($type['key'] ?? '') : $type;
                    $type_label = is_array($type) ? ($type['label'] ?? $type_key) : $type;
                    ?>
                    <option value="<?php echo esc_attr($type_key); ?>">
                        <?php echo esc_html(ucfirst($type_label)); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        <?php endif; ?>
    </form>

    <?php if ($is_ajax): ?>
        <div class="saga-widget-search__results" aria-live="polite">
            <!-- Results will be inserted by JavaScript -->
        </div>
    <?php endif; ?>
</div>
