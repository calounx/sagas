# Smart Autocomplete Search - Documentation

## Overview

The Smart Autocomplete Search feature provides real-time search suggestions for saga entities with a focus on performance, accessibility, and user experience.

## Features

- **Real-time Search**: 300ms debouncing for optimal performance
- **Grouped Results**: Results organized by entity type (characters, locations, events, etc.)
- **Keyboard Navigation**: Full support for arrow keys, Enter, and Escape
- **Recent Searches**: Stores last 10 searches in localStorage
- **Highlighting**: Matching text highlighted in results
- **Mobile Responsive**: Optimized layouts for all screen sizes
- **Accessibility**: Full ARIA support and screen reader compatibility
- **Dark Mode**: Automatic dark mode support based on user preference

## Files

### Backend (PHP)

1. **`/inc/class-sagaajaxhandler.php`**
   - `autocompleteSearch()` - Main AJAX handler
   - `groupResultsByType()` - Groups results by entity type
   - `formatAutocompleteResult()` - Formats single result
   - `highlightMatch()` - Highlights search terms
   - `getEntityTypeIcon()` - Returns emoji icons for types

### Frontend (JavaScript)

2. **`/assets/js/autocomplete-search.js`**
   - `SagaAutocomplete` class - Main autocomplete implementation
   - Features:
     - Debounced search (300ms)
     - Keyboard navigation
     - Recent searches management
     - Cache management
     - Event handling

### Styling (CSS)

3. **`/assets/css/autocomplete-search.css`**
   - Dropdown styling
   - Group headers
   - Result items
   - Loading/empty/error states
   - Responsive breakpoints
   - Dark mode styles
   - Accessibility enhancements

4. **`/assets/css/searchform.css`**
   - Search form container
   - Input field styling
   - Submit button
   - Responsive variants

### Templates

5. **`/searchform.php`**
   - WordPress search form template
   - ARIA attributes
   - Accessibility labels

## Usage

### Basic Implementation

The autocomplete is automatically initialized on all search inputs with class `saga-search-input`:

```html
<input type="search" class="saga-search-input" name="s" />
```

### Custom Initialization

```javascript
const autocomplete = new SagaAutocomplete(inputElement, {
    debounceDelay: 300,        // Debounce delay in ms
    minChars: 2,               // Minimum characters to trigger search
    maxResults: 10,            // Maximum results per group
    maxRecentSearches: 10,     // Maximum recent searches to store
    sagaId: null,              // Optional: filter by saga ID
    ajaxUrl: '/wp-admin/admin-ajax.php',
    nonce: 'your-nonce-here'
});
```

### WordPress Template

Include the search form in your template:

```php
<?php get_search_form(); ?>
```

Or use directly:

```php
<?php get_template_part('searchform'); ?>
```

## AJAX Endpoint

### Request

```javascript
POST /wp-admin/admin-ajax.php
{
    action: 'saga_autocomplete_search',
    saga_autocomplete_nonce: 'nonce-value',
    q: 'search query',
    limit: 10,
    saga_id: 1  // Optional
}
```

### Response

```json
{
    "success": true,
    "data": {
        "results": [
            {
                "id": 123,
                "title": "Luke Skywalker",
                "title_highlighted": "Luke <mark>Sky</mark>walker",
                "type": "character",
                "type_label": "Character",
                "type_icon": "👤",
                "excerpt": "Jedi Knight from Tatooine",
                "excerpt_highlighted": "Jedi Knight from <mark>Tat</mark>ooine",
                "url": "https://example.com/entity/luke-skywalker",
                "importance_score": 95
            }
        ],
        "grouped": {
            "character": [...],
            "location": [...],
            "event": [...]
        },
        "total": 15,
        "query": "sky"
    }
}
```

## Keyboard Navigation

| Key | Action |
|-----|--------|
| `Arrow Down` | Move to next result |
| `Arrow Up` | Move to previous result |
| `Enter` | Select current result or submit form |
| `Escape` | Close dropdown |
| `Tab` | Close dropdown and continue |

## Recent Searches

Recent searches are stored in `localStorage` under the key `saga_recent_searches`:

```javascript
// Format
[
    { title: "Luke Skywalker", url: "/entity/luke" },
    { title: "Tatooine", url: "/entity/tatooine" }
]
```

Maximum: 10 searches (configurable)

## Customization

### Styling

Override CSS variables or classes:

```css
/* Custom dropdown max height */
.saga-autocomplete__dropdown {
    max-height: 500px;
}

/* Custom highlight color */
.saga-autocomplete__item-title mark {
    background-color: #your-color;
}

/* Custom loading spinner color */
.saga-autocomplete__spinner {
    border-top-color: #your-color;
}
```

### Entity Type Icons

Modify icons in `/inc/class-sagaajaxhandler.php`:

```php
private function getEntityTypeIcon(string $type): string
{
    $icons = [
        'character' => '👤',
        'location' => '📍',
        'event' => '⚡',
        'faction' => '🛡️',
        'artifact' => '⚔️',
        'concept' => '💡',
        'custom_type' => '🔮', // Add your custom type
    ];

    return $icons[$type] ?? '📄';
}
```

### Debounce Delay

Change debounce delay in WordPress localization:

```php
wp_localize_script('saga-autocomplete', 'sagaAutocomplete', [
    'debounceDelay' => 500, // 500ms instead of default 300ms
    // ... other options
]);
```

## Performance

### Caching

- Results are cached in memory for the current session
- Cache key: search query string
- No expiration (cleared on page reload)

### Optimization Tips

1. **Limit Results**: Keep `maxResults` between 5-10 per group
2. **Database Indexes**: Ensure `canonical_name` is indexed
3. **Debounce Delay**: Balance between responsiveness and server load
4. **Minify Assets**: Use minified CSS/JS in production

### Performance Targets

- Search response: < 200ms
- First paint: < 100ms
- Interaction ready: < 300ms
- Smooth 60fps scrolling in dropdown

## Accessibility

### ARIA Attributes

```html
<!-- Container -->
<div role="combobox" aria-expanded="false" aria-haspopup="listbox">
    <!-- Input -->
    <input role="searchbox"
           aria-autocomplete="list"
           aria-controls="saga-autocomplete-dropdown" />

    <!-- Dropdown -->
    <div id="saga-autocomplete-dropdown" role="listbox">
        <!-- Group -->
        <div role="group" aria-labelledby="group-id">
            <!-- Item -->
            <div role="option" aria-selected="false">...</div>
        </div>
    </div>
</div>
```

### Screen Reader Support

- Announced search suggestions
- Announced selected item
- Announced empty/error states
- Described keyboard shortcuts

### Keyboard-Only Navigation

Fully navigable without mouse:
- Tab to focus input
- Arrow keys to navigate results
- Enter to select
- Escape to close

## Browser Support

- Chrome/Edge: 90+
- Firefox: 88+
- Safari: 14+
- Mobile Safari: 14+
- Chrome Android: 90+

### Required Features

- ES6+ JavaScript
- Fetch API
- LocalStorage
- CSS Grid/Flexbox
- CSS Custom Properties

## Security

### Nonce Verification

All AJAX requests require valid nonce:

```php
wp_verify_nonce($_POST['saga_autocomplete_nonce'], 'saga_autocomplete')
```

### Input Sanitization

- `sanitize_text_field()` for search query
- `absint()` for numeric values
- HTML escaping in output

### XSS Prevention

- All user input escaped in JavaScript
- Server-side HTML generation uses `esc_html()`
- `<mark>` tags are the only allowed HTML in results

## Troubleshooting

### No Results Showing

1. Check browser console for JavaScript errors
2. Verify AJAX endpoint is accessible
3. Check nonce is being passed correctly
4. Verify database has entities with matching names

### Autocomplete Not Initializing

1. Ensure JavaScript file is enqueued
2. Check input has class `saga-search-input`
3. Verify `sagaAutocomplete` global is defined
4. Check browser console for errors

### Slow Performance

1. Check database indexes on `canonical_name`
2. Reduce `maxResults` value
3. Increase `debounceDelay`
4. Enable WordPress object cache (Redis/Memcached)

### Styling Issues

1. Check CSS file is enqueued
2. Verify no theme conflicts with class names
3. Check z-index conflicts
4. Inspect element for computed styles

## Future Enhancements

- [ ] Fuzzy matching for typos
- [ ] Search history analytics
- [ ] Custom result templates
- [ ] Image thumbnails in results
- [ ] Multi-language support
- [ ] Voice search integration
- [ ] Search filters in dropdown
- [ ] Advanced search mode toggle

## Credits

Built for Saga Manager Theme
Following WordPress and WCAG 2.1 AA standards
Optimized for performance and accessibility
