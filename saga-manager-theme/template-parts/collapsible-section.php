<?php
/**
 * Template Part: Collapsible Section
 *
 * Renders an accordion-style collapsible section with accessibility support.
 *
 * @package SagaManager
 * @since 1.0.0
 */

declare(strict_types=1);

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Extract template args
$section_id = $args['section_id'] ?? '';
$title = $args['title'] ?? '';
$content = $args['content'] ?? '';
$expanded = $args['expanded'] ?? true;
$icon = $args['icon'] ?? '';
$heading_level = $args['heading_level'] ?? 'h3';
$classes = $args['classes'] ?? 'saga-collapsible-section';

// Validate required data
if (empty($section_id) || empty($title) || empty($content)) {
    return;
}

$aria_expanded = $expanded ? 'true' : 'false';
$aria_hidden = $expanded ? 'false' : 'true';
$content_id = 'section-' . esc_attr($section_id);
?>

<div class="<?php echo esc_attr($classes); ?>" data-section-id="<?php echo esc_attr($section_id); ?>">
    <button
        type="button"
        class="saga-section-toggle"
        aria-expanded="<?php echo esc_attr($aria_expanded); ?>"
        aria-controls="<?php echo esc_attr($content_id); ?>"
        data-section="<?php echo esc_attr($section_id); ?>">

        <span class="toggle-icon" aria-hidden="true">
            <svg class="chevron" width="20" height="20" viewBox="0 0 20 20" fill="none">
                <path d="M5 7.5L10 12.5L15 7.5" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
        </span>

        <<?php echo esc_attr($heading_level); ?> class="section-title">
            <?php if (!empty($icon)) : ?>
                <span class="section-icon" aria-hidden="true">
                    <?php saga_render_section_icon($icon); ?>
                </span>
            <?php endif; ?>
            <?php echo wp_kses_post($title); ?>
        </<?php echo esc_attr($heading_level); ?>>

        <span class="sr-only toggle-state">
            <?php echo $expanded ? esc_html__('Expanded', 'saga-manager') : esc_html__('Collapsed', 'saga-manager'); ?>
        </span>
    </button>

    <div
        class="saga-section-content"
        id="<?php echo esc_attr($content_id); ?>"
        aria-hidden="<?php echo esc_attr($aria_hidden); ?>"
        role="region"
        aria-labelledby="<?php echo esc_attr($content_id); ?>-label">

        <div class="section-content-inner">
            <?php echo wp_kses_post($content); ?>
        </div>
    </div>
</div>

<?php
/**
 * Render section icon
 *
 * @param string $icon Icon identifier
 * @return void
 */
function saga_render_section_icon(string $icon): void {
    $icons = [
        'user' => '<svg width="16" height="16" viewBox="0 0 16 16" fill="currentColor"><path d="M8 8a3 3 0 100-6 3 3 0 000 6zm2 1.5c2.5 0 4.5 1.5 4.5 3.5v1H1.5v-1c0-2 2-3.5 4.5-3.5h4z"/></svg>',
        'list' => '<svg width="16" height="16" viewBox="0 0 16 16" fill="currentColor"><path d="M2 4h12M2 8h12M2 12h12" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>',
        'users' => '<svg width="16" height="16" viewBox="0 0 16 16" fill="currentColor"><path d="M5.5 6a2 2 0 100-4 2 2 0 000 4zm0 1.5c-2 0-3.5 1-3.5 2.5v1h7v-1c0-1.5-1.5-2.5-3.5-2.5zm5-5a2 2 0 100-4 2 2 0 000 4zm0 1.5c-1.5 0-2.5.5-3 1 1 .5 2 1.5 2 3v1h4v-1c0-1.5-1.5-2.5-3-2.5z"/></svg>',
        'clock' => '<svg width="16" height="16" viewBox="0 0 16 16" fill="currentColor"><circle cx="8" cy="8" r="6" stroke="currentColor" stroke-width="1.5" fill="none"/><path d="M8 4v4l3 2" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg>',
        'quote' => '<svg width="16" height="16" viewBox="0 0 16 16" fill="currentColor"><path d="M3 6c0-1.5 1-2.5 2.5-2.5.5 0 1 .5 1 1s-.5 1-1 1c-.5 0-.5.5-.5 1v1h1.5v3.5H3V6zm5 0c0-1.5 1-2.5 2.5-2.5.5 0 1 .5 1 1s-.5 1-1 1c-.5 0-.5.5-.5 1v1H11v3.5H8V6z"/></svg>',
        'map-pin' => '<svg width="16" height="16" viewBox="0 0 16 16" fill="currentColor"><path d="M8 1.5C5.5 1.5 3.5 3.5 3.5 6c0 3.5 4.5 8.5 4.5 8.5s4.5-5 4.5-8.5c0-2.5-2-4.5-4.5-4.5zm0 6a1.5 1.5 0 110-3 1.5 1.5 0 010 3z"/></svg>',
        'globe' => '<svg width="16" height="16" viewBox="0 0 16 16" fill="currentColor"><circle cx="8" cy="8" r="6" stroke="currentColor" stroke-width="1.5" fill="none"/><path d="M2 8h12M8 2c1.5 0 2.5 2.5 2.5 6s-1 6-2.5 6-2.5-2.5-2.5-6 1-6 2.5-6z" stroke="currentColor" stroke-width="1.5" fill="none"/></svg>',
        'calendar' => '<svg width="16" height="16" viewBox="0 0 16 16" fill="currentColor"><rect x="2" y="3" width="12" height="11" rx="1" stroke="currentColor" stroke-width="1.5" fill="none"/><path d="M2 6h12M5 1.5v3M11 1.5v3"/></svg>',
        'map' => '<svg width="16" height="16" viewBox="0 0 16 16" fill="currentColor"><path d="M2 3l4-1.5v11L2 14V3zm4-1.5l4 1.5v11l-4-1.5v-11zm4 1.5l4-1.5v11L10 14V3z" stroke="currentColor" stroke-width="1.5" fill="none"/></svg>',
        'file-text' => '<svg width="16" height="16" viewBox="0 0 16 16" fill="currentColor"><path d="M4 2h5l3 3v8a1 1 0 01-1 1H4a1 1 0 01-1-1V3a1 1 0 011-1z" stroke="currentColor" stroke-width="1.5" fill="none"/><path d="M9 2v3h3M5 8h6M5 11h6"/></svg>',
        'arrow-right' => '<svg width="16" height="16" viewBox="0 0 16 16" fill="currentColor"><path d="M3 8h10M9 4l4 4-4 4" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>',
        'link' => '<svg width="16" height="16" viewBox="0 0 16 16" fill="currentColor"><path d="M6.5 10.5l3-3m-1 4.5l1.5 1.5a3 3 0 004-4L12.5 8m-5-1.5L6 5a3 3 0 014-4L11.5 2.5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg>',
        'crown' => '<svg width="16" height="16" viewBox="0 0 16 16" fill="currentColor"><path d="M2 11l2-5 4 3 4-3 2 5v2H2v-2zm2-7a1 1 0 100-2 1 1 0 000 2zm4 0a1 1 0 100-2 1 1 0 000 2zm4 0a1 1 0 100-2 1 1 0 000 2z"/></svg>',
        'star' => '<svg width="16" height="16" viewBox="0 0 16 16" fill="currentColor"><path d="M8 2l1.5 4.5h4.5l-3.5 2.5 1.5 4.5L8 11l-3.5 2.5 1.5-4.5L2 6.5h4.5L8 2z"/></svg>',
        'book' => '<svg width="16" height="16" viewBox="0 0 16 16" fill="currentColor"><path d="M2 2h12v11a1 1 0 01-1 1H3a1 1 0 01-1-1V2zm6 0v12" stroke="currentColor" stroke-width="1.5" fill="none"/></svg>',
    ];

    if (isset($icons[$icon])) {
        echo wp_kses($icons[$icon], [
            'svg' => ['width' => [], 'height' => [], 'viewBox' => [], 'fill' => []],
            'path' => ['d' => [], 'stroke' => [], 'stroke-width' => [], 'stroke-linecap' => [], 'stroke-linejoin' => [], 'fill' => []],
            'circle' => ['cx' => [], 'cy' => [], 'r' => [], 'stroke' => [], 'stroke-width' => [], 'fill' => []],
            'rect' => ['x' => [], 'y' => [], 'width' => [], 'height' => [], 'rx' => [], 'stroke' => [], 'stroke-width' => [], 'fill' => []],
        ]);
    }
}
