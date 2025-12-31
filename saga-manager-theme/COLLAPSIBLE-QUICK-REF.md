# Collapsible Sections - Quick Reference

## Basic Usage

### Simple Section
```php
saga_collapsible_section([
    'id' => 'biography',
    'title' => __('Biography', 'saga-manager'),
    'content' => $html_content,
    'expanded' => true,
    'icon' => 'user',
]);
```

### With Controls
```php
saga_collapsible_controls();

saga_collapsible_section([...]);
saga_collapsible_section([...]);
```

## Parameters

| Parameter | Type | Required | Default | Description |
|-----------|------|----------|---------|-------------|
| `id` | string | Yes | - | Unique identifier |
| `title` | string | Yes | - | Section heading |
| `content` | string | Yes | - | HTML content |
| `expanded` | bool | No | true | Initial state |
| `icon` | string | No | '' | Icon name |
| `heading_level` | string | No | 'h3' | h2-h6 |
| `classes` | array | No | [] | Extra classes |

## Icons

`user` `list` `users` `clock` `quote` `map-pin` `globe` `calendar` `map` `file-text` `arrow-right` `link` `crown` `star` `book`

## JavaScript API

```javascript
// Expand/collapse
window.sagaCollapsibleAPI.expand('section-id');
window.sagaCollapsibleAPI.collapse('section-id');
window.sagaCollapsibleAPI.toggle('section-id');

// All sections
window.sagaCollapsibleAPI.expandAll();
window.sagaCollapsibleAPI.collapseAll();

// Get states
const states = window.sagaCollapsibleAPI.getStates();
// { biography: true, timeline: false, ... }

// Reset
window.sagaCollapsibleAPI.reset();

// Listen for events
document.addEventListener('saga:section:toggle', (e) => {
    console.log(e.detail); // { sectionId, expanded }
});
```

## Deep Linking

```html
<!-- Auto-expands and scrolls to section -->
<a href="/character/luke/#biography">View Biography</a>
```

## Keyboard Shortcuts

| Key | Action |
|-----|--------|
| Tab | Navigate |
| Space/Enter | Toggle |
| Arrow Down | Next section |
| Arrow Up | Previous section |

## Entity Sections

### Get Configs
```php
$sections = saga_get_entity_sections('character');
// Returns: ['biography' => [...], 'attributes' => [...], ...]
```

### Entity Types
- **character**: biography, attributes, relationships, timeline, quotes
- **location**: description, geography, inhabitants, events, sublocations
- **event**: description, participants, location, consequences, related
- **faction**: description, leadership, members, territories, history
- **artifact**: description, history, powers, owners
- **concept**: definition, significance, examples, related

## Customization

### Filter Sections
```php
add_filter('saga_entity_sections', function($sections, $type) {
    $sections['new_section'] = [
        'title' => 'New Section',
        'icon' => 'star',
        'expanded' => false,
    ];
    return $sections;
}, 10, 2);
```

### Mobile Collapsed
```php
add_filter('saga_mobile_collapsed_default', '__return_false');
```

### CSS Variables
```css
.saga-collapsible-section {
    --color-primary: #3b82f6;
    --color-background-secondary: #f3f4f6;
    --border-radius: 12px;
}
```

## Storage

**Key Format**: `saga_sections_page_{pageId}`

**Structure**:
```json
{
  "biography": true,
  "relationships": false,
  "timeline": true
}
```

**Clear Storage**:
```javascript
window.sagaCollapsibleAPI.reset();
```

## Accessibility

- Full ARIA support
- Keyboard navigation
- Screen reader compatible
- WCAG AA contrast
- 44x44px touch targets
- Reduced motion support

## Browser Support

Chrome 90+ | Firefox 88+ | Safari 14+ | Edge 90+

## Files

```
inc/collapsible-helpers.php           # PHP helpers
template-parts/collapsible-section.php # Template
assets/css/collapsible-sections.css    # Styles
assets/js/collapsible-sections.js      # JavaScript
```

## Common Tasks

### Add Section to Template
```php
$content = get_post_meta($post_id, '_meta_key', true);
if (!empty($content)) {
    saga_collapsible_section([
        'id' => 'my-section',
        'title' => __('My Section', 'saga-manager'),
        'content' => wp_kses_post($content),
        'expanded' => false,
        'icon' => 'list',
    ]);
}
```

### Programmatic Toggle
```javascript
document.querySelector('.my-button').addEventListener('click', () => {
    window.sagaCollapsibleAPI.toggle('biography');
});
```

### Check Section State
```javascript
const states = window.sagaCollapsibleAPI.getStates();
if (states.biography) {
    console.log('Biography is expanded');
}
```

### Link to Specific Section
```php
<a href="<?php echo esc_url(get_permalink($id) . '#timeline'); ?>">
    View Timeline
</a>
```

## Troubleshooting

**Not animating?**
- Check `prefers-reduced-motion` browser setting
- Verify CSS is loaded

**State not saving?**
- Check if localStorage is enabled
- Try private/incognito mode

**Hash not working?**
- Verify section `id` matches hash
- Check JavaScript console for errors

**Styles broken?**
- Ensure CSS is enqueued
- Check for theme conflicts

## Performance

- GPU-accelerated animations
- Debounced storage writes (300ms)
- No forced reflows
- Efficient event delegation

## Security

- All input sanitized
- All output escaped
- WordPress Coding Standards
- No XSS vulnerabilities

---

**Full docs**: See `COLLAPSIBLE-SECTIONS.md`

**Examples**: See `example-collapsible-usage.php`
