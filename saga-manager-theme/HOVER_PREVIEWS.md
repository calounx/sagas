# Entity Hover Previews

## Overview

The Hover Previews feature displays rich preview cards when users hover over entity links. This provides quick context without navigating away from the current page.

## Features

- **300ms hover delay** - Prevents accidental triggers
- **Intelligent positioning** - Automatically avoids viewport edges
- **Performance optimized** - Caches preview data, limits concurrent requests
- **Desktop-only** - Automatically disabled on touch devices
- **Accessibility** - Respects `prefers-reduced-motion`
- **Dark mode support** - Adapts to theme preference
- **Smooth animations** - GPU-accelerated transforms

## Usage

### Marking Entity Links

Add the `saga-entity-link` class or `data-entity-id` attribute to any link:

```html
<!-- Using CSS class -->
<a href="/entity/123/" class="saga-entity-link">Luke Skywalker</a>

<!-- Using data attribute -->
<a href="/entity/123/" data-entity-id="123">Luke Skywalker</a>
```

### Preview Content

Preview cards display:
- Entity thumbnail (150x150px)
- Entity title
- Entity type badge (colored by type)
- Short excerpt (100 chars max)
- Top 3 attributes (based on entity type)
- "View full details" link

### Automatic Initialization

The feature automatically initializes on page load for:
- Single entity pages (`is_singular('saga_entity')`)
- Entity archives (`is_post_type_archive('saga_entity')`)
- Entity taxonomy pages (`is_tax('saga_type')`)
- Saga pages (`is_singular('saga')`)

## Files

### PHP

- **`/inc/ajax-preview.php`** - REST API endpoint for preview data
  - `GET /wp-json/saga/v1/entities/{id}/preview`
  - Returns JSON with entity data
  - Caches responses for 1 hour

### JavaScript

- **`/assets/js/hover-preview.js`** - Preview logic and positioning
  - Event handlers for hover/leave
  - Positioning algorithm
  - Cache management
  - AJAX requests

### CSS

- **`/assets/css/hover-preview.css`** - Preview card styling
  - Card layout and typography
  - Type badge colors
  - Animations
  - Dark mode support
  - Arrow pointer positioning

### Templates

- **`/template-parts/preview-card-template.php`** - HTML template
  - `<template>` element cloned by JavaScript
  - Loading state
  - Loaded state with all content

## Configuration

### JavaScript Constants (in hover-preview.js)

```javascript
const CONFIG = {
    hoverDelay: 300,        // ms before showing preview
    hideDelay: 100,         // ms before hiding preview
    maxPreviewWidth: 360,   // px
    viewportPadding: 10,    // px from viewport edge
    maxConcurrentRequests: 5,
    cacheExpiry: 3600000,   // 1 hour in ms
};
```

### Priority Attributes by Entity Type

Defined in `get_entity_preview_attributes()` in `/inc/ajax-preview.php`:

- **Character**: species, affiliation, homeworld, birth_date
- **Location**: type, planet, region, population
- **Event**: date, location, participants, outcome
- **Faction**: type, leader, founded, allegiance
- **Artifact**: type, creator, power, location
- **Concept**: category, origin, significance

## API Endpoint

### Request

```
GET /wp-json/saga/v1/entities/{id}/preview
```

### Response

```json
{
    "id": 123,
    "title": "Luke Skywalker",
    "type": "character",
    "excerpt": "A young farm boy who becomes a Jedi Knight...",
    "thumbnail": "https://example.com/wp-content/uploads/luke.jpg",
    "url": "https://example.com/entity/123/",
    "importance": 95,
    "attributes": [
        {
            "label": "Species",
            "value": "Human"
        },
        {
            "label": "Affiliation",
            "value": "Rebel Alliance"
        }
    ]
}
```

## Performance

### Caching Strategy

1. **Server-side cache** (WordPress object cache)
   - Key: `saga_preview_{entity_id}`
   - Group: `saga_previews`
   - TTL: 1 hour

2. **Client-side cache** (JavaScript Map)
   - Stores up to 100 previews
   - LRU eviction when limit exceeded
   - Expires after 1 hour

### Request Throttling

- Maximum 5 concurrent AJAX requests
- Additional requests show error state
- Prevents server overload

### Optimization Techniques

- CSS transforms for animation (GPU accelerated)
- Debounced positioning calculations
- Lazy loading for preview images
- Reduced DOM manipulation

## Browser Support

- Modern browsers (Chrome, Firefox, Safari, Edge)
- Requires ES6+ support
- Graceful degradation (no JS = regular links)

## Accessibility

- `role="tooltip"` on preview cards
- Hidden from screen readers (`aria-hidden="true"`)
- Respects `prefers-reduced-motion`
- No keyboard focus (intentionally)
- Disabled for touch devices

## Dark Mode

The feature automatically supports dark mode through:

1. **System preference**: `@media (prefers-color-scheme: dark)`
2. **Class-based**: `.dark` class on body
3. **Data attribute**: `[data-theme="dark"]`

All type badge colors and UI elements adapt accordingly.

## Troubleshooting

### Preview not showing

1. Check browser console for errors
2. Verify link has `saga-entity-link` class or `data-entity-id`
3. Ensure template is included in footer
4. Check if touch device (feature disabled)

### Wrong positioning

1. Ensure viewport has enough space
2. Check for CSS conflicts
3. Verify card element is appended to body

### API errors

1. Check REST API is enabled
2. Verify entity ID is valid
3. Check database for entity existence
4. Review server error logs

## Customization

### Changing Hover Delay

Edit `CONFIG.hoverDelay` in `/assets/js/hover-preview.js`:

```javascript
const CONFIG = {
    hoverDelay: 500, // 500ms delay
    // ...
};
```

### Custom Styling

Override CSS variables in your theme:

```css
.saga-preview-card {
    --preview-bg: #ffffff;
    --preview-border: #e5e7eb;
    --preview-text: #111827;
    /* ... */
}
```

### Custom Attributes

Modify priority arrays in `get_entity_preview_attributes()`:

```php
$priority_attrs = [
    'character' => ['custom_attr', 'species', 'affiliation'],
    // ...
];
```

## Testing

### Manual Testing Checklist

- [ ] Hover shows preview after 300ms
- [ ] Preview hides when mouse leaves
- [ ] Positioning works in all corners
- [ ] Arrow points to link
- [ ] Dark mode colors correct
- [ ] Touch devices don't show preview
- [ ] Multiple hovers cache properly
- [ ] Loading spinner shows briefly
- [ ] All entity types display correctly

### Performance Testing

```javascript
// Check cache hit rate
console.log(state.previewCache.size); // Should grow to 100 max

// Check concurrent requests
console.log(state.activeRequests.size); // Should not exceed 5
```

## Future Enhancements

Potential improvements for future versions:

- [ ] Prefetch visible links on page load
- [ ] Keyboard navigation support
- [ ] Mobile long-press support
- [ ] Preview image placeholder
- [ ] Relationship preview in attributes
- [ ] Timeline event preview
- [ ] Customizable preview template per entity type
- [ ] Analytics tracking for hover interactions

## Credits

- Developed for Saga Manager Theme
- Uses WordPress REST API
- Follows CLAUDE.md architectural guidelines
- Implements hexagonal architecture principles
