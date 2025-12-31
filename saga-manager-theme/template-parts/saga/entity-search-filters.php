<?php
/**
 * Entity Search Filters Template Part
 *
 * AJAX-powered filtering for entity archives
 *
 * @package SagaManagerTheme
 * @since 1.0.0
 */

declare(strict_types=1);

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

$sagas = saga_get_all_sagas();
$entity_types = get_terms([
    'taxonomy' => 'saga_type',
    'hide_empty' => true,
]);

$current_saga = isset($_GET['saga']) ? absint($_GET['saga']) : 0;
$current_type = isset($_GET['type']) ? sanitize_text_field($_GET['type']) : '';
$current_search = isset($_GET['s']) ? sanitize_text_field($_GET['s']) : '';
?>

<div class="saga-filters" id="saga-filters">
    <form method="get" action="<?php echo esc_url(get_post_type_archive_link('saga_entity')); ?>" 
          id="saga-filter-form">
        
        <div class="saga-filters__grid">
            
            <!-- Saga Filter -->
            <?php if (!empty($sagas)) : ?>
                <div class="saga-filters__field">
                    <label for="saga-filter-saga" class="saga-filters__label">
                        <?php esc_html_e('Saga', 'saga-manager-theme'); ?>
                    </label>
                    <select name="saga" id="saga-filter-saga" class="saga-filters__select">
                        <option value=""><?php esc_html_e('All Sagas', 'saga-manager-theme'); ?></option>
                        <?php foreach ($sagas as $saga) : ?>
                            <option value="<?php echo esc_attr($saga->id); ?>" 
                                    <?php selected($current_saga, $saga->id); ?>>
                                <?php echo esc_html($saga->name); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            <?php endif; ?>

            <!-- Entity Type Filter -->
            <?php if (!empty($entity_types) && !is_wp_error($entity_types)) : ?>
                <div class="saga-filters__field">
                    <label for="saga-filter-type" class="saga-filters__label">
                        <?php esc_html_e('Entity Type', 'saga-manager-theme'); ?>
                    </label>
                    <select name="type" id="saga-filter-type" class="saga-filters__select">
                        <option value=""><?php esc_html_e('All Types', 'saga-manager-theme'); ?></option>
                        <?php foreach ($entity_types as $term) : ?>
                            <option value="<?php echo esc_attr($term->slug); ?>" 
                                    <?php selected($current_type, $term->slug); ?>>
                                <?php echo esc_html($term->name); ?> (<?php echo esc_html($term->count); ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            <?php endif; ?>

            <!-- Search Field -->
            <div class="saga-filters__field">
                <label for="saga-filter-search" class="saga-filters__label">
                    <?php esc_html_e('Search', 'saga-manager-theme'); ?>
                </label>
                <input type="text" 
                       name="s" 
                       id="saga-filter-search" 
                       class="saga-filters__input"
                       placeholder="<?php esc_attr_e('Search entities...', 'saga-manager-theme'); ?>"
                       value="<?php echo esc_attr($current_search); ?>">
            </div>

            <!-- Importance Range -->
            <div class="saga-filters__field">
                <label for="saga-filter-importance" class="saga-filters__label">
                    <?php esc_html_e('Importance', 'saga-manager-theme'); ?>
                </label>
                <div class="saga-filters__range">
                    <div class="saga-filters__range-value">
                        <span id="importance-min-value">0</span> - 
                        <span id="importance-max-value">100</span>
                    </div>
                    <input type="range" 
                           name="importance_min" 
                           id="saga-filter-importance-min"
                           min="0" 
                           max="100" 
                           value="0"
                           class="saga-filters__range-input">
                    <input type="range" 
                           name="importance_max" 
                           id="saga-filter-importance-max"
                           min="0" 
                           max="100" 
                           value="100"
                           class="saga-filters__range-input">
                </div>
            </div>

            <!-- Submit Button -->
            <button type="submit" class="saga-filters__submit">
                <?php esc_html_e('Apply Filters', 'saga-manager-theme'); ?>
            </button>

        </div>
    </form>
</div>

<script>
(function($) {
    'use strict';

    $(document).ready(function() {
        // Update range value display
        $('#saga-filter-importance-min').on('input', function() {
            $('#importance-min-value').text($(this).val());
        });

        $('#saga-filter-importance-max').on('input', function() {
            $('#importance-max-value').text($(this).val());
        });

        // AJAX filtering (if needed)
        $('#saga-filter-form').on('submit', function(e) {
            if (typeof sagaAjax === 'undefined') {
                return; // Fallback to normal form submission
            }

            e.preventDefault();

            var formData = {
                action: 'saga_filter_entities',
                nonce: sagaAjax.nonce,
                saga_id: $('#saga-filter-saga').val(),
                entity_type: $('#saga-filter-type').val(),
                search: $('#saga-filter-search').val(),
                importance_min: $('#saga-filter-importance-min').val(),
                importance_max: $('#saga-filter-importance-max').val()
            };

            $.ajax({
                url: sagaAjax.ajaxUrl,
                type: 'POST',
                data: formData,
                beforeSend: function() {
                    $('#saga-entities-container').addClass('loading');
                },
                success: function(response) {
                    if (response.success) {
                        $('#saga-entities-container').html(response.data.html);
                    }
                },
                complete: function() {
                    $('#saga-entities-container').removeClass('loading');
                }
            });
        });
    });
})(jQuery);
</script>
