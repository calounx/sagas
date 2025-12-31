<?php
/**
 * Template Name: Entity Comparison
 * Description: Compare multiple saga entities side-by-side
 *
 * @package Saga_Manager_Theme
 */

declare(strict_types=1);

get_header();

// Get entities from URL parameter
$entity_identifiers = isset($_GET['entities']) ? explode(',', sanitize_text_field($_GET['entities'])) : [];
$entities = saga_get_comparison_entities($entity_identifiers);
$aligned_data = saga_align_entity_attributes($entities);
?>

<main id="primary" class="site-main comparison-page">
    <div class="comparison-container">

        <!-- Page Header -->
        <header class="comparison-header">
            <h1 class="comparison-title">
                <?php esc_html_e('Compare Entities', 'saga-manager-theme'); ?>
            </h1>

            <!-- Entity Selection Bar -->
            <div class="comparison-controls">
                <div class="entity-selector">
                    <input
                        type="text"
                        id="entity-search"
                        class="entity-search-input"
                        placeholder="<?php esc_attr_e('Search entities to add...', 'saga-manager-theme'); ?>"
                        autocomplete="off"
                        aria-label="<?php esc_attr_e('Search entities', 'saga-manager-theme'); ?>"
                    />
                    <div class="entity-search-results" aria-live="polite"></div>
                </div>

                <div class="comparison-actions">
                    <label class="toggle-differences">
                        <input type="checkbox" id="show-only-differences" />
                        <span><?php esc_html_e('Only show differences', 'saga-manager-theme'); ?></span>
                    </label>

                    <button type="button" class="btn-share-url" aria-label="<?php esc_attr_e('Copy share URL', 'saga-manager-theme'); ?>">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"></path>
                            <path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"></path>
                        </svg>
                        <?php esc_html_e('Share', 'saga-manager-theme'); ?>
                    </button>

                    <button type="button" class="btn-export-comparison" aria-label="<?php esc_attr_e('Export comparison', 'saga-manager-theme'); ?>">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>
                            <polyline points="7 10 12 15 17 10"></polyline>
                            <line x1="12" y1="15" x2="12" y2="3"></line>
                        </svg>
                        <?php esc_html_e('Export', 'saga-manager-theme'); ?>
                    </button>

                    <button type="button" class="btn-print-comparison" onclick="window.print()" aria-label="<?php esc_attr_e('Print comparison', 'saga-manager-theme'); ?>">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <polyline points="6 9 6 2 18 2 18 9"></polyline>
                            <path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"></path>
                            <rect x="6" y="14" width="12" height="8"></rect>
                        </svg>
                        <?php esc_html_e('Print', 'saga-manager-theme'); ?>
                    </button>
                </div>
            </div>
        </header>

        <?php if (empty($entities)) : ?>

            <!-- Empty State -->
            <div class="comparison-empty-state">
                <svg width="120" height="120" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1" opacity="0.3">
                    <rect x="3" y="3" width="7" height="7"></rect>
                    <rect x="14" y="3" width="7" height="7"></rect>
                    <rect x="14" y="14" width="7" height="7"></rect>
                    <rect x="3" y="14" width="7" height="7"></rect>
                </svg>
                <h2><?php esc_html_e('Start comparing entities', 'saga-manager-theme'); ?></h2>
                <p><?php esc_html_e('Search for entities above to add them to the comparison. You can compare up to 4 entities at once.', 'saga-manager-theme'); ?></p>
            </div>

        <?php else : ?>

            <!-- Comparison Table -->
            <div class="comparison-wrapper">
                <div class="comparison-table-scroll">
                    <table class="comparison-table" role="table">
                        <thead class="comparison-thead" role="rowgroup">
                            <tr role="row">
                                <th class="comparison-attribute-header" scope="col" role="columnheader">
                                    <?php esc_html_e('Attribute', 'saga-manager-theme'); ?>
                                </th>

                                <?php foreach ($aligned_data['entities'] as $entity) : ?>
                                    <th class="comparison-entity-header" scope="col" role="columnheader" data-entity-id="<?php echo esc_attr($entity['id']); ?>">
                                        <div class="entity-header-content">
                                            <?php if ($entity['thumbnail']) : ?>
                                                <img
                                                    src="<?php echo esc_url($entity['thumbnail']); ?>"
                                                    alt="<?php echo esc_attr($entity['title']); ?>"
                                                    class="entity-thumbnail"
                                                    loading="lazy"
                                                />
                                            <?php endif; ?>

                                            <div class="entity-header-text">
                                                <h3 class="entity-name">
                                                    <a href="<?php echo esc_url($entity['permalink']); ?>">
                                                        <?php echo esc_html($entity['title']); ?>
                                                    </a>
                                                </h3>
                                                <?php if ($entity['type']) : ?>
                                                    <span class="entity-type-badge">
                                                        <?php echo esc_html(ucfirst($entity['type'])); ?>
                                                    </span>
                                                <?php endif; ?>
                                            </div>

                                            <button
                                                type="button"
                                                class="btn-remove-entity"
                                                data-entity-id="<?php echo esc_attr($entity['id']); ?>"
                                                aria-label="<?php echo esc_attr(sprintf(__('Remove %s', 'saga-manager-theme'), $entity['title'])); ?>"
                                            >
                                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                    <line x1="18" y1="6" x2="6" y2="18"></line>
                                                    <line x1="6" y1="6" x2="18" y2="18"></line>
                                                </svg>
                                            </button>
                                        </div>
                                    </th>
                                <?php endforeach; ?>
                            </tr>
                        </thead>

                        <tbody class="comparison-tbody" role="rowgroup">
                            <?php foreach ($aligned_data['attributes'] as $attribute) :
                                // Get values for this attribute across all entities
                                $values = [];
                                foreach ($aligned_data['entities'] as $entity) {
                                    $values[] = $entity['attributes'][$attribute['key']] ?? null;
                                }

                                $has_differences = saga_has_attribute_differences($values);
                                $row_class = $has_differences ? 'has-differences' : '';
                            ?>
                                <tr class="comparison-row <?php echo esc_attr($row_class); ?>" role="row" data-has-differences="<?php echo $has_differences ? 'true' : 'false'; ?>">
                                    <th class="comparison-attribute-cell" scope="row" role="rowheader">
                                        <?php echo esc_html($attribute['label']); ?>
                                    </th>

                                    <?php foreach ($aligned_data['entities'] as $entity) :
                                        $value = $entity['attributes'][$attribute['key']] ?? null;
                                    ?>
                                        <td class="comparison-value-cell" role="cell">
                                            <?php echo wp_kses_post(saga_format_attribute_value($value, $attribute['type'])); ?>
                                        </td>
                                    <?php endforeach; ?>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Mobile View (Accordion) -->
            <div class="comparison-mobile">
                <?php foreach ($aligned_data['entities'] as $index => $entity) : ?>
                    <div class="comparison-mobile-entity" data-entity-id="<?php echo esc_attr($entity['id']); ?>">
                        <div class="mobile-entity-header">
                            <?php if ($entity['thumbnail']) : ?>
                                <img
                                    src="<?php echo esc_url($entity['thumbnail']); ?>"
                                    alt="<?php echo esc_attr($entity['title']); ?>"
                                    class="mobile-entity-thumbnail"
                                    loading="lazy"
                                />
                            <?php endif; ?>

                            <div class="mobile-entity-info">
                                <h3><?php echo esc_html($entity['title']); ?></h3>
                                <?php if ($entity['type']) : ?>
                                    <span class="entity-type-badge">
                                        <?php echo esc_html(ucfirst($entity['type'])); ?>
                                    </span>
                                <?php endif; ?>
                            </div>

                            <button
                                type="button"
                                class="btn-remove-entity"
                                data-entity-id="<?php echo esc_attr($entity['id']); ?>"
                                aria-label="<?php echo esc_attr(sprintf(__('Remove %s', 'saga-manager-theme'), $entity['title'])); ?>"
                            >
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <line x1="18" y1="6" x2="6" y2="18"></line>
                                    <line x1="6" y1="6" x2="18" y2="18"></line>
                                </svg>
                            </button>
                        </div>

                        <dl class="mobile-entity-attributes">
                            <?php foreach ($aligned_data['attributes'] as $attribute) :
                                $value = $entity['attributes'][$attribute['key']] ?? null;
                            ?>
                                <div class="mobile-attribute-row">
                                    <dt><?php echo esc_html($attribute['label']); ?></dt>
                                    <dd><?php echo wp_kses_post(saga_format_attribute_value($value, $attribute['type'])); ?></dd>
                                </div>
                            <?php endforeach; ?>
                        </dl>
                    </div>
                <?php endforeach; ?>
            </div>

        <?php endif; ?>

    </div>
</main>

<?php
get_footer();
