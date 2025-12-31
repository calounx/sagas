<?php
/**
 * Template Part: Timeline Viewer
 *
 * Displays interactive timeline visualization for saga events.
 *
 * @package SagaManagerTheme
 * @since 1.0.0
 *
 * @param array $args {
 *     Timeline configuration arguments.
 *
 *     @type int    $saga_id        Saga ID (required)
 *     @type int    $entity_id      Entity ID to filter by (optional)
 *     @type string $type           Timeline type: linear, grouped, stacked (default: linear)
 *     @type int    $height         Timeline height in pixels (default: 600)
 *     @type string $calendar_type  Calendar type: standard, bby, age_based (optional)
 *     @type array  $date_range     Date range filter (optional)
 *     @type bool   $show_controls  Show timeline controls (default: true)
 *     @type bool   $show_filters   Show filter panel (default: true)
 * }
 */

// Security check
if (!defined('ABSPATH')) {
    exit;
}

// Extract arguments with defaults
$saga_id = isset($args['saga_id']) ? absint($args['saga_id']) : 0;
$entity_id = isset($args['entity_id']) ? absint($args['entity_id']) : null;
$timeline_type = isset($args['type']) ? sanitize_key($args['type']) : 'linear';
$height = isset($args['height']) ? absint($args['height']) : 600;
$calendar_type = isset($args['calendar_type']) ? sanitize_key($args['calendar_type']) : 'standard';
$date_range = isset($args['date_range']) ? $args['date_range'] : null;
$show_controls = isset($args['show_controls']) ? (bool) $args['show_controls'] : true;
$show_filters = isset($args['show_filters']) ? (bool) $args['show_filters'] : true;

// Validate required parameters
if (!$saga_id) {
    echo '<div class="timeline-error active">Error: Saga ID is required.</div>';
    return;
}

// Get saga data
global $wpdb;
$saga = $wpdb->get_row($wpdb->prepare(
    "SELECT * FROM {$wpdb->prefix}saga_sagas WHERE id = %d",
    $saga_id
));

if (!$saga) {
    echo '<div class="timeline-error active">Error: Saga not found.</div>';
    return;
}

// Parse calendar config
$calendar_config = json_decode($saga->calendar_config, true) ?: [];

// Available entity types for filters
$entity_types = [
    'character' => __('Characters', 'saga-manager'),
    'location' => __('Locations', 'saga-manager'),
    'event' => __('Events', 'saga-manager'),
    'faction' => __('Factions', 'saga-manager'),
    'artifact' => __('Artifacts', 'saga-manager'),
    'concept' => __('Concepts', 'saga-manager'),
];

// Get event count
$event_count = $wpdb->get_var($wpdb->prepare(
    "SELECT COUNT(*) FROM {$wpdb->prefix}saga_timeline_events WHERE saga_id = %d",
    $saga_id
));

?>

<div class="saga-timeline-wrapper"
     data-saga-id="<?php echo esc_attr($saga_id); ?>"
     data-entity-id="<?php echo esc_attr($entity_id); ?>"
     data-timeline-type="<?php echo esc_attr($timeline_type); ?>"
     data-height="<?php echo esc_attr($height); ?>"
     data-calendar-type="<?php echo esc_attr($calendar_type); ?>"
     data-calendar-config="<?php echo esc_attr(json_encode($calendar_config)); ?>">

    <!-- Screen reader announcer -->
    <div class="sr-announcer" role="status" aria-live="polite" aria-atomic="true"></div>

    <!-- Timeline Header -->
    <div class="timeline-header">
        <h2><?php echo esc_html($saga->name); ?> <?php _e('Timeline', 'saga-manager'); ?></h2>
        <div class="timeline-meta">
            <div class="timeline-meta-item">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                </svg>
                <span><?php printf(__('%s Events', 'saga-manager'), number_format($event_count)); ?></span>
            </div>
            <div class="timeline-meta-item">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3.055 11H5a2 2 0 012 2v1a2 2 0 002 2 2 2 0 012 2v2.945M8 3.935V5.5A2.5 2.5 0 0010.5 8h.5a2 2 0 012 2 2 2 0 104 0 2 2 0 012-2h1.064M15 20.488V18a2 2 0 012-2h3.064M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                <span><?php echo esc_html($saga->universe); ?></span>
            </div>
            <div class="timeline-meta-item">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                <span><?php echo esc_html(ucfirst(str_replace('_', ' ', $saga->calendar_type))); ?></span>
            </div>
        </div>
    </div>

    <?php if ($show_controls) : ?>
    <!-- Timeline Controls -->
    <div class="timeline-controls">
        <div class="timeline-controls-group">
            <button class="zoom-in" aria-label="<?php esc_attr_e('Zoom in', 'saga-manager'); ?>" title="<?php esc_attr_e('Zoom in (+)', 'saga-manager'); ?>">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0zM10 7v6m3-3H7" />
                </svg>
                <span class="sr-only"><?php _e('Zoom in', 'saga-manager'); ?></span>
            </button>
            <button class="zoom-out" aria-label="<?php esc_attr_e('Zoom out', 'saga-manager'); ?>" title="<?php esc_attr_e('Zoom out (-)', 'saga-manager'); ?>">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0zM13 10H7" />
                </svg>
                <span class="sr-only"><?php _e('Zoom out', 'saga-manager'); ?></span>
            </button>
            <button class="fit-window" aria-label="<?php esc_attr_e('Fit to window', 'saga-manager'); ?>" title="<?php esc_attr_e('Fit to window (Home)', 'saga-manager'); ?>">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 8V4m0 0h4M4 4l5 5m11-1V4m0 0h-4m4 0l-5 5M4 16v4m0 0h4m-4 0l5-5m11 5l-5-5m5 5v-4m0 4h-4" />
                </svg>
                <span class="sr-only"><?php _e('Fit to window', 'saga-manager'); ?></span>
            </button>
        </div>

        <div class="timeline-controls-group">
            <label for="timeline-type-select" class="sr-only"><?php _e('Timeline Type', 'saga-manager'); ?></label>
            <select class="timeline-type" id="timeline-type-select">
                <option value="linear" <?php selected($timeline_type, 'linear'); ?>><?php _e('Linear View', 'saga-manager'); ?></option>
                <option value="grouped" <?php selected($timeline_type, 'grouped'); ?>><?php _e('Grouped by Type', 'saga-manager'); ?></option>
                <option value="stacked" <?php selected($timeline_type, 'stacked'); ?>><?php _e('Stacked View', 'saga-manager'); ?></option>
            </select>
        </div>

        <div class="timeline-controls-group export-dropdown">
            <button class="export-toggle" aria-label="<?php esc_attr_e('Export timeline', 'saga-manager'); ?>" aria-haspopup="true" aria-expanded="false">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                </svg>
                <span><?php _e('Export', 'saga-manager'); ?></span>
            </button>
            <div class="export-menu" role="menu">
                <button class="export-json" role="menuitem"><?php _e('Export JSON', 'saga-manager'); ?></button>
                <button class="export-csv" role="menuitem"><?php _e('Export CSV', 'saga-manager'); ?></button>
                <button class="export-image" role="menuitem"><?php _e('Export Image', 'saga-manager'); ?></button>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <?php if ($show_filters) : ?>
    <!-- Filter Toggle -->
    <button class="filter-toggle" aria-expanded="false" aria-controls="timeline-filters-panel">
        <span><?php _e('Filters', 'saga-manager'); ?></span>
        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
        </svg>
    </button>

    <!-- Timeline Filters -->
    <div class="timeline-filters collapsed" id="timeline-filters-panel">
        <!-- Entity Type Filter -->
        <div class="filter-section">
            <h4><?php _e('Entity Types', 'saga-manager'); ?></h4>
            <div class="filter-checkboxes">
                <?php foreach ($entity_types as $type => $label) : ?>
                <label class="filter-checkbox-label">
                    <input type="checkbox" class="filter-entity-type" value="<?php echo esc_attr($type); ?>">
                    <span><?php echo esc_html($label); ?></span>
                </label>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Importance Filter -->
        <div class="filter-section">
            <h4><?php _e('Minimum Importance', 'saga-manager'); ?></h4>
            <div class="filter-range">
                <input type="range" class="filter-importance" min="0" max="100" value="0" step="5" aria-label="<?php esc_attr_e('Minimum importance threshold', 'saga-manager'); ?>">
                <div class="filter-range-label">
                    <span><?php _e('All Events', 'saga-manager'); ?></span>
                    <span class="importance-value">0</span>
                </div>
            </div>
        </div>

        <!-- Date Range Filter -->
        <div class="filter-section">
            <h4><?php _e('Date Range', 'saga-manager'); ?></h4>
            <div class="filter-date-range">
                <div class="filter-date-input">
                    <label for="filter-date-from"><?php _e('From', 'saga-manager'); ?></label>
                    <input type="date" id="filter-date-from" class="filter-date-from">
                </div>
                <div class="filter-date-input">
                    <label for="filter-date-to"><?php _e('To', 'saga-manager'); ?></label>
                    <input type="date" id="filter-date-to" class="filter-date-to">
                </div>
            </div>
        </div>

        <!-- Filter Actions -->
        <div class="filter-actions">
            <button class="apply-filters primary-button"><?php _e('Apply Filters', 'saga-manager'); ?></button>
            <button class="reset-filters"><?php _e('Reset', 'saga-manager'); ?></button>
        </div>
    </div>
    <?php endif; ?>

    <!-- Timeline Container -->
    <div class="timeline-container" role="application" aria-label="<?php esc_attr_e('Interactive timeline visualization', 'saga-manager'); ?>">
        <!-- Timeline will be rendered here by JavaScript -->
    </div>

    <!-- Loading Indicator -->
    <div class="timeline-loader">
        <div class="loader-spinner" role="status">
            <span class="sr-only"><?php _e('Loading timeline...', 'saga-manager'); ?></span>
        </div>
    </div>

    <!-- Error Container -->
    <div class="timeline-error" role="alert"></div>

    <!-- Event Details Modal -->
    <div class="event-details-modal" role="dialog" aria-modal="true" aria-labelledby="event-modal-title">
        <div class="modal-content">
            <!-- Content populated by JavaScript -->
        </div>
    </div>
</div>

<script type="text/javascript">
/**
 * Additional timeline interactions
 */
(function($) {
    'use strict';

    $(document).ready(function() {
        // Filter toggle
        $('.filter-toggle').on('click', function() {
            const $this = $(this);
            const $filters = $('#timeline-filters-panel');
            const isExpanded = $this.attr('aria-expanded') === 'true';

            $this.attr('aria-expanded', !isExpanded);
            $this.toggleClass('active');
            $filters.toggleClass('collapsed');
        });

        // Export dropdown toggle
        $('.export-toggle').on('click', function(e) {
            e.stopPropagation();
            const $dropdown = $(this).closest('.export-dropdown');
            const isExpanded = $(this).attr('aria-expanded') === 'true';

            $dropdown.toggleClass('active');
            $(this).attr('aria-expanded', !isExpanded);
        });

        // Close export dropdown on outside click
        $(document).on('click', function(e) {
            if (!$(e.target).closest('.export-dropdown').length) {
                $('.export-dropdown').removeClass('active');
                $('.export-toggle').attr('aria-expanded', 'false');
            }
        });

        // Close modal on outside click
        $('.event-details-modal').on('click', function(e) {
            if ($(e.target).hasClass('event-details-modal')) {
                $(this).removeClass('active');
            }
        });

        // Close modal on Escape key
        $(document).on('keydown', function(e) {
            if (e.key === 'Escape') {
                $('.event-details-modal').removeClass('active');
            }
        });
    });
})(jQuery);
</script>

<?php
// Alternative table view for accessibility (hidden by default)
?>
<div class="timeline-table-view sr-only" role="region" aria-label="<?php esc_attr_e('Timeline data in table format', 'saga-manager'); ?>">
    <table>
        <caption><?php printf(__('%s Timeline Events', 'saga-manager'), esc_html($saga->name)); ?></caption>
        <thead>
            <tr>
                <th scope="col"><?php _e('Date', 'saga-manager'); ?></th>
                <th scope="col"><?php _e('Title', 'saga-manager'); ?></th>
                <th scope="col"><?php _e('Type', 'saga-manager'); ?></th>
                <th scope="col"><?php _e('Importance', 'saga-manager'); ?></th>
            </tr>
        </thead>
        <tbody>
            <?php
            // Get events for table view
            $events = $wpdb->get_results($wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}saga_timeline_events
                WHERE saga_id = %d
                ORDER BY normalized_timestamp ASC
                LIMIT 100",
                $saga_id
            ));

            foreach ($events as $event) :
                $participants = json_decode($event->participants, true) ?: [];
            ?>
            <tr>
                <td><?php echo esc_html($event->canon_date); ?></td>
                <td><?php echo esc_html($event->title); ?></td>
                <td><?php echo esc_html(ucfirst($event->entity_type ?? 'event')); ?></td>
                <td><?php echo esc_html($event->importance ?? '50'); ?>/100</td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
