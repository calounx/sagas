<?php
/**
 * Search Form Template with Smart Autocomplete
 *
 * Enhanced search form with real-time autocomplete suggestions
 * Accessible and mobile-responsive
 *
 * @package SagaTheme
 */

declare(strict_types=1);

// Get unique form ID for accessibility
$unique_id = wp_unique_id('saga-search-form-');
$input_id = wp_unique_id('saga-search-input-');

// Get current saga ID if on a saga page
$saga_id = null;
if (is_singular('saga_entity')) {
    $entity = saga_get_entity(get_the_ID());
    if ($entity && isset($entity->saga_id)) {
        $saga_id = (int) $entity->saga_id;
    }
}

?>
<form role="search"
      method="get"
      class="saga-search-form"
      id="<?php echo esc_attr($unique_id); ?>"
      action="<?php echo esc_url(home_url('/')); ?>"
      aria-label="<?php esc_attr_e('Search entities', 'saga-manager-theme'); ?>">

    <div class="saga-search-form__wrapper">
        <label for="<?php echo esc_attr($input_id); ?>" class="saga-search-form__label">
            <?php esc_html_e('Search Entities', 'saga-manager-theme'); ?>
        </label>

        <div class="saga-search-form__input-wrapper">
            <input
                type="search"
                id="<?php echo esc_attr($input_id); ?>"
                class="saga-search-form__input saga-search-input"
                name="s"
                value="<?php echo get_search_query(); ?>"
                placeholder="<?php esc_attr_e('Search characters, locations, events...', 'saga-manager-theme'); ?>"
                <?php if ($saga_id): ?>
                data-saga-id="<?php echo esc_attr((string) $saga_id); ?>"
                <?php endif; ?>
                required
                aria-describedby="<?php echo esc_attr($input_id . '-desc'); ?>"
            />

            <button
                type="submit"
                class="saga-search-form__submit"
                aria-label="<?php esc_attr_e('Submit search', 'saga-manager-theme'); ?>">
                <svg class="saga-search-form__icon"
                     xmlns="http://www.w3.org/2000/svg"
                     width="20"
                     height="20"
                     viewBox="0 0 24 24"
                     fill="none"
                     stroke="currentColor"
                     stroke-width="2"
                     stroke-linecap="round"
                     stroke-linejoin="round"
                     aria-hidden="true">
                    <circle cx="11" cy="11" r="8"></circle>
                    <path d="m21 21-4.35-4.35"></path>
                </svg>
                <span class="saga-search-form__submit-text">
                    <?php esc_html_e('Search', 'saga-manager-theme'); ?>
                </span>
            </button>
        </div>

        <span id="<?php echo esc_attr($input_id . '-desc'); ?>" class="saga-search-form__description">
            <?php esc_html_e('Start typing to see suggestions', 'saga-manager-theme'); ?>
        </span>
    </div>

    <?php if (is_search()): ?>
        <!-- Hidden input to maintain post type filtering -->
        <input type="hidden" name="post_type" value="saga_entity" />
    <?php endif; ?>
</form>
