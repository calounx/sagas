<?php
/**
 * Search Form Template
 *
 * Reusable template for semantic search form with advanced filters.
 *
 * @package SagaManager
 * @since 1.3.0
 */

declare(strict_types=1);

// Default options
$defaults = [
    'placeholder' => __('Search saga entities...', 'saga-manager'),
    'show_filters' => true,
    'show_voice' => true,
    'show_results' => true,
    'show_saved_searches' => true,
    'compact' => false,
    'max_results' => 50,
    'saga_id' => 0,
    'form_action' => home_url('/'),
];

$options = wp_parse_args($args ?? [], $defaults);

$form_class = 'saga-search-form saga-main-search';
if ($options['compact']) {
    $form_class .= ' saga-search-compact';
}

$form_id = 'saga-search-' . uniqid();
?>

<div class="saga-search-container saga-template-search">
    <form class="<?php echo esc_attr($form_class); ?>"
          id="<?php echo esc_attr($form_id); ?>"
          role="search"
          method="get"
          action="<?php echo esc_url($options['form_action']); ?>"
          data-max-results="<?php echo esc_attr($options['max_results']); ?>"
          data-saga-id="<?php echo esc_attr($options['saga_id']); ?>">

        <!-- Search Input -->
        <div class="saga-search-input-wrapper">
            <label for="<?php echo esc_attr($form_id); ?>-input" class="screen-reader-text">
                <?php esc_html_e('Search for saga entities', 'saga-manager'); ?>
            </label>

            <i class="saga-search-icon" aria-hidden="true">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <circle cx="11" cy="11" r="8" stroke="currentColor" stroke-width="2"/>
                    <path d="M21 21l-4.35-4.35" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                </svg>
            </i>

            <input type="search"
                   id="<?php echo esc_attr($form_id); ?>-input"
                   name="s"
                   class="saga-search-input"
                   placeholder="<?php echo esc_attr($options['placeholder']); ?>"
                   value="<?php echo esc_attr(get_search_query()); ?>"
                   autocomplete="off"
                   aria-expanded="false"
                   aria-autocomplete="list"
                   aria-controls="<?php echo esc_attr($form_id); ?>-results"
                   aria-describedby="<?php echo esc_attr($form_id); ?>-description">

            <?php if ($options['show_voice']): ?>
                <button type="button"
                        class="saga-voice-search-btn"
                        aria-label="<?php esc_attr_e('Voice search', 'saga-manager'); ?>"
                        title="<?php esc_attr_e('Voice search', 'saga-manager'); ?>">
                    <svg width="20" height="20" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <rect x="7" y="2" width="6" height="10" rx="3" stroke="currentColor" stroke-width="2"/>
                        <path d="M3 10a9 9 0 0 0 14 0M10 16v4" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                    </svg>
                </button>
            <?php endif; ?>

            <button type="button"
                    class="saga-search-clear"
                    aria-label="<?php esc_attr_e('Clear search', 'saga-manager'); ?>"
                    style="display: none;">
                <svg width="20" height="20" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <path d="M15 5L5 15M5 5l10 10" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                </svg>
            </button>
        </div>

        <p id="<?php echo esc_attr($form_id); ?>-description" class="screen-reader-text">
            <?php esc_html_e('Use quotes for exact phrases, minus sign to exclude terms, AND/OR for boolean operators', 'saga-manager'); ?>
        </p>

        <!-- Advanced Filters -->
        <?php if ($options['show_filters']): ?>
            <div class="saga-search-filters">
                <button type="button"
                        class="saga-search-filters-toggle"
                        aria-expanded="false"
                        aria-controls="<?php echo esc_attr($form_id); ?>-filters">
                    <svg width="20" height="20" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path d="M4 6h12M6 10h8M8 14h4" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                    </svg>
                    <span><?php esc_html_e('Advanced Filters', 'saga-manager'); ?></span>
                    <svg class="saga-chevron" width="16" height="16" viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path d="M4 6l4 4 4-4" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                </button>

                <div class="saga-search-filters-content"
                     id="<?php echo esc_attr($form_id); ?>-filters"
                     style="display: none;">

                    <!-- Entity Type Filter -->
                    <div class="saga-filter-group">
                        <label class="saga-filter-label">
                            <svg width="16" height="16" viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <path d="M8 2v12M2 8h12" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                            </svg>
                            <?php esc_html_e('Entity Type', 'saga-manager'); ?>
                        </label>
                        <div class="saga-filter-types">
                            <?php
                            $entity_types = [
                                'character' => [
                                    'label' => __('Characters', 'saga-manager'),
                                    'icon' => 'user',
                                ],
                                'location' => [
                                    'label' => __('Locations', 'saga-manager'),
                                    'icon' => 'map-pin',
                                ],
                                'event' => [
                                    'label' => __('Events', 'saga-manager'),
                                    'icon' => 'calendar',
                                ],
                                'faction' => [
                                    'label' => __('Factions', 'saga-manager'),
                                    'icon' => 'users',
                                ],
                                'artifact' => [
                                    'label' => __('Artifacts', 'saga-manager'),
                                    'icon' => 'package',
                                ],
                                'concept' => [
                                    'label' => __('Concepts', 'saga-manager'),
                                    'icon' => 'book',
                                ],
                            ];

                            $filter_id = uniqid();
                            foreach ($entity_types as $type => $data):
                            ?>
                                <div class="saga-filter-type-option">
                                    <input type="checkbox"
                                           id="saga-filter-type-<?php echo esc_attr($type . '-' . $filter_id); ?>"
                                           class="saga-filter-type"
                                           name="types[]"
                                           value="<?php echo esc_attr($type); ?>">
                                    <label for="saga-filter-type-<?php echo esc_attr($type . '-' . $filter_id); ?>">
                                        <span class="saga-type-icon saga-icon-<?php echo esc_attr($data['icon']); ?>"></span>
                                        <?php echo esc_html($data['label']); ?>
                                    </label>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <!-- Importance Score Filter -->
                    <div class="saga-filter-group">
                        <label class="saga-filter-label">
                            <svg width="16" height="16" viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <path d="M8 2l2 6h6l-5 4 2 6-5-4-5 4 2-6-5-4h6l2-6z" stroke="currentColor" stroke-width="1.5"/>
                            </svg>
                            <?php esc_html_e('Importance Score', 'saga-manager'); ?>
                        </label>
                        <div class="saga-filter-importance-range">
                            <div class="saga-filter-importance-inputs">
                                <input type="number"
                                       class="saga-filter-importance-min"
                                       name="importance_min"
                                       min="0"
                                       max="100"
                                       step="10"
                                       placeholder="<?php esc_attr_e('Min', 'saga-manager'); ?>"
                                       aria-label="<?php esc_attr_e('Minimum importance score', 'saga-manager'); ?>">
                                <span class="saga-filter-separator">-</span>
                                <input type="number"
                                       class="saga-filter-importance-max"
                                       name="importance_max"
                                       min="0"
                                       max="100"
                                       step="10"
                                       placeholder="<?php esc_attr_e('Max', 'saga-manager'); ?>"
                                       aria-label="<?php esc_attr_e('Maximum importance score', 'saga-manager'); ?>">
                            </div>
                            <div class="saga-importance-presets">
                                <button type="button" class="saga-preset-btn" data-min="80" data-max="100">
                                    <?php esc_html_e('Major', 'saga-manager'); ?>
                                </button>
                                <button type="button" class="saga-preset-btn" data-min="50" data-max="79">
                                    <?php esc_html_e('Important', 'saga-manager'); ?>
                                </button>
                                <button type="button" class="saga-preset-btn" data-min="0" data-max="49">
                                    <?php esc_html_e('Minor', 'saga-manager'); ?>
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Saga Filter -->
                    <?php if (!$options['saga_id']): ?>
                        <div class="saga-filter-group">
                            <label class="saga-filter-label" for="<?php echo esc_attr($form_id); ?>-saga">
                                <svg width="16" height="16" viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg">
                                    <rect x="2" y="2" width="12" height="12" rx="2" stroke="currentColor" stroke-width="2"/>
                                    <path d="M6 6h4M6 10h4" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                                </svg>
                                <?php esc_html_e('Saga', 'saga-manager'); ?>
                            </label>
                            <select class="saga-filter-saga"
                                    id="<?php echo esc_attr($form_id); ?>-saga"
                                    name="saga_id">
                                <option value=""><?php esc_html_e('All Sagas', 'saga-manager'); ?></option>
                                <?php
                                global $wpdb;
                                $sagas_table = $wpdb->prefix . 'saga_sagas';
                                $sagas = $wpdb->get_results("SELECT id, name FROM {$sagas_table} ORDER BY name ASC", ARRAY_A);

                                foreach ($sagas as $saga):
                                ?>
                                    <option value="<?php echo esc_attr($saga['id']); ?>">
                                        <?php echo esc_html($saga['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    <?php endif; ?>

                    <!-- Sort Options -->
                    <div class="saga-filter-group">
                        <label class="saga-filter-label" for="<?php echo esc_attr($form_id); ?>-sort">
                            <svg width="16" height="16" viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <path d="M4 2v12M4 2l-2 2M4 2l2 2M12 14V2M12 14l2-2M12 14l-2-2" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                            </svg>
                            <?php esc_html_e('Sort By', 'saga-manager'); ?>
                        </label>
                        <select class="saga-search-sort"
                                id="<?php echo esc_attr($form_id); ?>-sort"
                                name="sort">
                            <option value="relevance"><?php esc_html_e('Relevance', 'saga-manager'); ?></option>
                            <option value="name"><?php esc_html_e('Name (A-Z)', 'saga-manager'); ?></option>
                            <option value="date"><?php esc_html_e('Recently Updated', 'saga-manager'); ?></option>
                            <option value="importance"><?php esc_html_e('Importance', 'saga-manager'); ?></option>
                        </select>
                    </div>

                    <!-- Filter Actions -->
                    <div class="saga-filter-actions">
                        <button type="button" class="saga-save-search-btn">
                            <svg width="16" height="16" viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <path d="M12 2H4a2 2 0 0 0-2 2v10l4-3 4 3 4-3V4a2 2 0 0 0-2-2z" stroke="currentColor" stroke-width="2"/>
                            </svg>
                            <?php esc_html_e('Save Search', 'saga-manager'); ?>
                        </button>
                        <button type="button" class="saga-reset-filters-btn">
                            <svg width="16" height="16" viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <path d="M14 8A6 6 0 1 1 8 2" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                                <path d="M8 2l2 2-2 2" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                            </svg>
                            <?php esc_html_e('Reset', 'saga-manager'); ?>
                        </button>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <!-- Saved Searches -->
        <?php if ($options['show_saved_searches']): ?>
            <div class="saga-saved-searches-panel" style="display: none;">
                <h4><?php esc_html_e('Saved Searches', 'saga-manager'); ?></h4>
                <div class="saga-saved-searches-list"></div>
            </div>
        <?php endif; ?>

        <!-- Search Results Container -->
        <?php if ($options['show_results']): ?>
            <div class="saga-search-results"
                 id="<?php echo esc_attr($form_id); ?>-results"
                 role="region"
                 aria-live="polite"
                 aria-atomic="false"
                 aria-label="<?php esc_attr_e('Search results', 'saga-manager'); ?>">
            </div>
        <?php endif; ?>

        <!-- Hidden Fields -->
        <input type="hidden" name="post_type" value="saga_entity">
    </form>

    <!-- Screen Reader Live Region -->
    <div class="saga-search-live-region" role="status" aria-live="polite" aria-atomic="true"></div>
</div>

<script>
jQuery(document).ready(function($) {
    // Toggle filters
    $('.saga-search-filters-toggle').on('click', function() {
        const $content = $(this).siblings('.saga-search-filters-content');
        const expanded = $(this).attr('aria-expanded') === 'true';

        $(this).attr('aria-expanded', !expanded);
        $content.slideToggle(300);
        $(this).find('.saga-chevron').toggleClass('rotated');
    });

    // Importance presets
    $('.saga-preset-btn').on('click', function() {
        const min = $(this).data('min');
        const max = $(this).data('max');
        const $form = $(this).closest('.saga-search-form');

        $form.find('.saga-filter-importance-min').val(min);
        $form.find('.saga-filter-importance-max').val(max);

        // Trigger search
        $form.find('.saga-search-input').trigger('input');
    });

    // Reset filters
    $('.saga-reset-filters-btn').on('click', function() {
        const $form = $(this).closest('.saga-search-form');

        $form.find('.saga-filter-type').prop('checked', false);
        $form.find('.saga-filter-importance-min, .saga-filter-importance-max').val('');
        $form.find('.saga-filter-saga').val('');
        $form.find('.saga-search-sort').val('relevance');

        // Trigger search refresh
        $form.find('.saga-search-input').trigger('input');
    });

    // Show clear button when input has value
    $('.saga-search-input').on('input', function() {
        const $clear = $(this).siblings('.saga-search-clear');
        if ($(this).val().length > 0) {
            $clear.show();
        } else {
            $clear.hide();
        }
    });
});
</script>
