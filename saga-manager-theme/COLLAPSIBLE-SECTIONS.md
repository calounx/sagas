# Collapsible Sections Feature

Accordion-style collapsible sections for entity pages with smooth animations, state persistence, accessibility, and deep linking support.

## Features

- **Smooth Animations**: GPU-accelerated CSS transitions (300ms)
- **State Persistence**: localStorage per page with debounced writes
- **Accessibility**: Full ARIA support, keyboard navigation, screen reader compatible
- **Deep Linking**: URL hash navigation with auto-expand and scroll
- **Mobile Optimized**: Touch-friendly, collapsed by default on mobile
- **Reduced Motion**: Respects `prefers-reduced-motion` media query
- **Multi-tab Sync**: Changes sync across browser tabs via storage events
- **Dark Mode**: Compatible with dark color schemes
- **High Contrast**: Enhanced borders and outlines for accessibility

## Installation

The feature is automatically loaded for `saga_entity` post types. No additional setup required.

### Files Created

```
saga-manager-theme/
├── inc/collapsible-helpers.php                    # Helper functions
├── template-parts/collapsible-section.php         # Template part
├── assets/css/collapsible-sections.css            # Styles
├── assets/js/collapsible-sections.js              # JavaScript
└── example-collapsible-usage.php                  # Usage examples
```

## Basic Usage

### Simple Section

```php
saga_collapsible_section([
    'id' => 'biography',
    'title' => __('Biography', 'saga-manager'),
    'content' => wp_kses_post($biography_html),
    'expanded' => true,
    'icon' => 'user',
]);
```

### With Controls

```php
<!-- Expand All / Collapse All buttons -->
<?php saga_collapsible_controls(['position' => 'top']); ?>

<!-- Sections -->
<?php saga_collapsible_section([...]); ?>
<?php saga_collapsible_section([...]); ?>
```

## Function Reference

### `saga_collapsible_section(array $args)`

Renders a collapsible section.

**Parameters:**

| Parameter | Type | Required | Default | Description |
|-----------|------|----------|---------|-------------|
| `id` | string | Yes | - | Unique section identifier |
| `title` | string | Yes | - | Section heading text |
| `content` | string | Yes | - | Section content HTML |
| `expanded` | bool | No | `true` | Default expanded state |
| `icon` | string | No | `''` | Icon identifier (see below) |
| `heading_level` | string | No | `'h3'` | Heading level (h2-h6) |
| `classes` | array | No | `[]` | Additional CSS classes |

**Available Icons:**

- `user` - User profile
- `list` - List items
- `users` - Multiple users
- `clock` - Timeline/history
- `quote` - Quotation marks
- `map-pin` - Location marker
- `globe` - World/geography
- `calendar` - Events
- `map` - Map/territories
- `file-text` - Document
- `arrow-right` - Forward/consequence
- `link` - Connection/relationship
- `crown` - Leadership
- `star` - Important/powers
- `book` - Knowledge/definition

### `saga_collapsible_controls(array $args)`

Renders Expand All / Collapse All buttons.

**Parameters:**

| Parameter | Type | Default | Description |
|-----------|------|---------|-------------|
| `position` | string | `'top'` | Position: `top`, `bottom`, or `both` |
| `show_expand` | bool | `true` | Show expand all button |
| `show_collapse` | bool | `true` | Show collapse all button |

### `saga_get_entity_sections(string $entity_type)`

Get predefined section configurations for an entity type.

**Returns:** Array of section configurations with `title`, `icon`, and `expanded` keys.

**Supported Entity Types:**

- `character` - Biography, Attributes, Relationships, Timeline, Quotes
- `location` - Description, Geography, Inhabitants, Events, Sub-locations
- `event` - Description, Participants, Location, Consequences, Related
- `faction` - Description, Leadership, Members, Territories, History
- `artifact` - Description, History, Powers, Owners
- `concept` - Definition, Significance, Examples, Related

## JavaScript API

The feature exposes a global API for programmatic control:

```javascript
// Expand a section
window.sagaCollapsibleAPI.expand('biography');

// Collapse a section
window.sagaCollapsibleAPI.collapse('timeline');

// Toggle a section
window.sagaCollapsibleAPI.toggle('relationships');

// Expand all sections
window.sagaCollapsibleAPI.expandAll();

// Collapse all sections
window.sagaCollapsibleAPI.collapseAll();

// Get current states
const states = window.sagaCollapsibleAPI.getStates();
// Returns: { biography: true, timeline: false, ... }

// Reset to default states
window.sagaCollapsibleAPI.reset();
```

### Custom Events

Listen for section toggle events:

```javascript
document.addEventListener('saga:section:toggle', function(event) {
    const { sectionId, expanded } = event.detail;
    console.log(`Section ${sectionId} is now ${expanded ? 'expanded' : 'collapsed'}`);
});
```

## Deep Linking

Sections can be linked directly via URL hash:

```html
<!-- Link to section by ID -->
<a href="/character/luke-skywalker/#biography">View Biography</a>

<!-- Link to section content ID -->
<a href="/character/luke-skywalker/#section-biography">View Biography</a>
```

The linked section will:
1. Auto-expand (if collapsed)
2. Scroll into view with smooth animation
3. Briefly highlight (2s animation)

## Accessibility

### Keyboard Navigation

| Key | Action |
|-----|--------|
| Tab | Navigate to toggle buttons |
| Space / Enter | Toggle section |
| Arrow Down | Move to next section |
| Arrow Up | Move to previous section |

### Screen Reader Support

- **ARIA Attributes**: `aria-expanded`, `aria-controls`, `aria-hidden`, `aria-labelledby`
- **Live Regions**: State changes announced ("Expanded" / "Collapsed")
- **Semantic HTML**: Proper heading hierarchy, button roles
- **Focus Management**: Focus remains on toggle button after activation

### Color Contrast

All colors meet WCAG AA standards:
- Text: 4.5:1 contrast ratio
- Interactive elements: 3:1 contrast ratio
- Focus indicators: Clearly visible 2px outline

## State Persistence

Section states are saved to `localStorage` per page:

```json
{
  "saga_sections_page_123": {
    "biography": true,
    "relationships": false,
    "timeline": true
  }
}
```

- **Debounced Writes**: 300ms delay to reduce localStorage writes
- **Multi-tab Sync**: Changes sync across browser tabs
- **Per-page Storage**: Each page has independent state
- **Graceful Degradation**: Falls back to default states if localStorage unavailable

## Mobile Optimization

### Touch Targets

- Minimum 44x44px tap targets (WCAG 2.5.5)
- Larger chevron icons (22x22px)
- Generous padding around buttons

### Default Behavior

Sections are **collapsed by default on mobile** (screen width < 640px) for better scrolling performance.

Override this behavior:

```php
add_filter('saga_mobile_collapsed_default', '__return_false');
```

### Sticky Controls

On mobile, Expand All / Collapse All buttons stick to the top of the screen for easy access.

## Performance

### Animations

- **GPU Accelerated**: Uses `transform` and `opacity` for smooth 60fps animations
- **Max Height Transition**: Smooth expand/collapse without JavaScript measurement
- **Debounced Storage**: Reduces localStorage writes to 1 per 300ms

### Reduced Motion

Respects user preferences:

```css
@media (prefers-reduced-motion: reduce) {
    .saga-section-content {
        transition: none;
    }
}
```

JavaScript automatically detects this and uses instant show/hide.

## Customization

### Add Custom Section Type

```php
add_filter('saga_entity_sections', function($sections, $entity_type) {
    if ($entity_type === 'character') {
        $sections['skills'] = [
            'title' => __('Skills & Abilities', 'saga-manager'),
            'icon' => 'star',
            'expanded' => false,
        ];
    }
    return $sections;
}, 10, 2);
```

### Custom Styling

Override CSS variables:

```css
.saga-collapsible-section {
    --color-primary: #3b82f6;
    --color-background-secondary: #f3f4f6;
    --border-radius: 12px;
}
```

### Custom Icons

Extend the icon list in `template-parts/collapsible-section.php`:

```php
function saga_render_section_icon(string $icon): void {
    $icons = [
        'custom-icon' => '<svg>...</svg>',
        // Add more icons
    ];
    // ...
}
```

## Browser Support

- **Modern Browsers**: Chrome 90+, Firefox 88+, Safari 14+, Edge 90+
- **localStorage**: Required (feature degrades gracefully if unavailable)
- **CSS Grid**: Used for layout (fallback to flexbox in older browsers)
- **Intersection Observer**: Not used (no lazy loading dependencies)

## Testing

### Manual Testing Checklist

- [ ] Sections expand/collapse smoothly
- [ ] State persists after page reload
- [ ] Deep links work (#section-id)
- [ ] Keyboard navigation functional
- [ ] Screen reader announces state changes
- [ ] Mobile: collapsed by default
- [ ] Multi-tab sync works
- [ ] Reduced motion respected
- [ ] Dark mode renders correctly
- [ ] Print: all sections visible

### Automated Testing

```javascript
// Cypress test example
describe('Collapsible Sections', () => {
    it('toggles section on click', () => {
        cy.visit('/character/luke-skywalker/');
        cy.get('[data-section-id="biography"] .saga-section-toggle').click();
        cy.get('#section-biography').should('have.attr', 'aria-hidden', 'true');
    });

    it('deep links to section', () => {
        cy.visit('/character/luke-skywalker/#timeline');
        cy.get('#section-timeline').should('have.attr', 'aria-hidden', 'false');
        cy.get('#section-timeline').should('be.visible');
    });
});
```

## Troubleshooting

### Sections Not Animating

**Cause**: `prefers-reduced-motion` is enabled or CSS not loaded

**Solution**: Check browser settings or ensure CSS is enqueued:

```php
wp_enqueue_style('saga-collapsible-sections');
```

### State Not Persisting

**Cause**: localStorage disabled or browser in private mode

**Solution**: Feature degrades gracefully. State resets on page load.

### JavaScript Errors

**Cause**: Script loaded before DOM ready or missing dependencies

**Solution**: Ensure script loads in footer with no dependencies:

```php
wp_enqueue_script('saga-collapsible-sections', $url, [], $ver, true);
```

### Sections Not Found via Hash

**Cause**: Section ID doesn't match hash or JavaScript not initialized

**Solution**: Check section ID matches hash:

```php
// Section with id="biography"
saga_collapsible_section(['id' => 'biography', ...]);

// Links to #biography or #section-biography
```

## License

Part of Saga Manager Theme. Licensed under GPL-2.0-or-later.

## Changelog

### 1.0.0 (2025-12-31)

- Initial release
- Accordion-style sections with smooth animations
- State persistence via localStorage
- Deep linking support
- Full accessibility (ARIA, keyboard navigation)
- Mobile optimization
- Dark mode support
- Multi-tab synchronization
