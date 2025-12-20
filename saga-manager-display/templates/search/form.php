<?php
/**
 * Template: Search Form
 *
 * @package SagaManagerDisplay
 * @var array $sagas Available sagas
 * @var array $entity_types Available entity types
 * @var array $initial_results Initial results to display
 * @var string $placeholder Search placeholder text
 * @var string $fixed_saga Fixed saga filter
 * @var array $fixed_types Fixed type filters
 * @var array $options Display options
 */

defined('ABSPATH') || exit;
?>

<form class="saga-search__form" role="search">
    <div class="saga-search__input-wrapper">
        <span class="dashicons dashicons-search saga-search__icon" aria-hidden="true"></span>
        <input
            type="search"
            class="saga-search__input"
            placeholder="<?php echo esc_attr($placeholder); ?>"
            aria-label="<?php echo esc_attr($placeholder); ?>"
        >
    </div>

    <?php if ($options['show_filters']): ?>
        <div class="saga-search__filters">
            <?php if ($options['show_type_filter'] && !empty($entity_types)): ?>
                <select class="saga-search__filter saga-search__filter--type" aria-label="<?php esc_attr_e('Filter by type', 'saga-manager-display'); ?>">
                    <option value=""><?php esc_html_e('All Types', 'saga-manager-display'); ?></option>
                    <?php foreach ($entity_types as $type): ?>
                        <?php
                        $type_key = is_array($type) ? ($type['key'] ?? '') : $type;
                        $type_label = is_array($type) ? ($type['label'] ?? $type_key) : $type;
                        $selected = in_array($type_key, $fixed_types, true);
                        ?>
                        <option value="<?php echo esc_attr($type_key); ?>" <?php selected($selected); ?>>
                            <?php echo esc_html(ucfirst($type_label)); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            <?php endif; ?>

            <?php if ($options['show_saga_filter'] && !$fixed_saga && !empty($sagas)): ?>
                <select class="saga-search__filter saga-search__filter--saga" aria-label="<?php esc_attr_e('Filter by saga', 'saga-manager-display'); ?>">
                    <option value=""><?php esc_html_e('All Sagas', 'saga-manager-display'); ?></option>
                    <?php foreach ($sagas as $saga): ?>
                        <?php
                        $saga_slug = $saga['slug'] ?? sanitize_title($saga['name'] ?? '');
                        $saga_name = $saga['name'] ?? '';
                        ?>
                        <option value="<?php echo esc_attr($saga_slug); ?>">
                            <?php echo esc_html($saga_name); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            <?php endif; ?>

            <button type="submit" class="saga-button">
                <?php esc_html_e('Search', 'saga-manager-display'); ?>
            </button>
        </div>
    <?php endif; ?>
</form>

<div class="saga-search__results">
    <div class="saga-search__results-header">
        <span class="saga-search__results-count" aria-live="polite"></span>
    </div>

    <div class="saga-search__results-grid">
        <?php if (!empty($initial_results)): ?>
            <?php foreach ($initial_results as $entity): ?>
                <?php
                $entity_type = $entity['entity_type'] ?? 'entity';
                $entity_url = $entity['url'] ?? '';
                $entity_image = $entity['image'] ?? '';
                $entity_name = $entity['canonical_name'] ?? '';
                $entity_description = $entity['description'] ?? '';
                ?>
                <article class="saga-entity saga-entity--card" data-entity-id="<?php echo esc_attr($entity['id'] ?? ''); ?>">
                    <?php if ($entity_image): ?>
                        <div class="saga-entity__image">
                            <img
                                src="<?php echo esc_url($entity_image); ?>"
                                alt="<?php echo esc_attr($entity_name); ?>"
                                loading="lazy"
                            >
                        </div>
                    <?php endif; ?>

                    <div class="saga-entity__content">
                        <span class="saga-badge saga-badge--<?php echo esc_attr($entity_type); ?>">
                            <?php echo esc_html(ucfirst($entity_type)); ?>
                        </span>

                        <h3 class="saga-entity__name">
                            <?php if ($entity_url): ?>
                                <a href="<?php echo esc_url($entity_url); ?>">
                                    <?php echo esc_html($entity_name); ?>
                                </a>
                            <?php else: ?>
                                <?php echo esc_html($entity_name); ?>
                            <?php endif; ?>
                        </h3>

                        <?php if ($entity_description): ?>
                            <p class="saga-entity__description saga-line-clamp-2">
                                <?php echo esc_html($entity_description); ?>
                            </p>
                        <?php endif; ?>
                    </div>
                </article>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <?php if ($options['show_pagination']): ?>
        <nav class="saga-search__pagination saga-pagination" aria-label="<?php esc_attr_e('Search results pagination', 'saga-manager-display'); ?>">
            <!-- Pagination will be inserted by JavaScript -->
        </nav>
    <?php endif; ?>
</div>
