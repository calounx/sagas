# Entity Comparison Feature

## Overview

The Entity Comparison feature allows users to compare 2-4 saga entities side-by-side in a responsive, synchronized table view. Users can highlight differences, search for entities, export comparisons, and share via URL.

## Files Structure

```
saga-manager-theme/
├── page-templates/
│   └── compare-entities.php          # Main comparison page template
├── template-parts/
│   └── comparison-column.php          # Individual entity column template
├── inc/
│   └── comparison-helpers.php         # Helper functions and logic
├── assets/
│   ├── js/
│   │   └── entity-comparison.js      # JavaScript for interaction and sync
│   └── css/
│       └── entity-comparison.css     # Responsive styling
└── functions.php                      # AJAX handlers integration
```

## Setup Instructions

### 1. Create Comparison Page

In WordPress admin:

1. Go to **Pages > Add New**
2. Enter page title: "Compare Entities"
3. Set permalink slug: `compare`
4. Select template: **Entity Comparison**
5. Publish the page

### 2. URL Structure

The comparison page accepts entity identifiers via query parameters:

```
# By entity IDs
/compare/?entities=123,456,789

# By entity slugs
/compare/?entities=luke-skywalker,darth-vader,yoda

# Mixed (IDs and slugs)
/compare/?entities=123,darth-vader,789
```

## Features

### Entity Selection

- **Search Autocomplete**: Type 2+ characters to search entities
- **Max Entities**: Up to 4 entities can be compared simultaneously
- **Exclusion**: Already selected entities don't appear in search results
- **Keyboard Navigation**: Arrow keys to navigate, Enter to select

### Comparison Table

- **Sticky Headers**: Entity headers remain visible during scroll
- **Attribute Alignment**: Same attributes appear on the same row
- **Difference Highlighting**: Rows with different values are highlighted in yellow
- **Priority Sorting**: Most important attributes appear first
- **Value Formatting**: Arrays, dates, booleans formatted appropriately
- **N/A Display**: Missing attributes show as "N/A" in gray

### Responsive Design

- **Desktop (>1024px)**: Side-by-side columns with synchronized scrolling
- **Tablet (768px-1024px)**: 2 columns maximum
- **Mobile (<768px)**: Vertical stacking with accordion-style sections

### Actions

1. **Show Only Differences**: Toggle to hide identical attributes
2. **Share URL**: Copy shareable URL to clipboard
3. **Export**: Download comparison as JSON file
4. **Print**: Optimized print layout (A4)
5. **Remove Entity**: Click X to remove from comparison

## Usage Examples

### Comparing Characters

```
/compare/?entities=luke-skywalker,darth-vader,obi-wan-kenobi
```

Attributes aligned:
- Species
- Homeworld
- Birth Year
- Affiliation
- Height
- Mass

### Comparing Locations

```
/compare/?entities=tatooine,coruscant,hoth
```

Attributes aligned:
- Planet Type
- Terrain
- Climate
- Population
- Government

## Customization

### Attribute Priority

Edit `/inc/comparison-helpers.php` to customize attribute priority:

```php
function saga_get_attribute_priority(string $key, string $entity_type): int {
    // Core attributes (highest priority)
    $core_attributes = [
        'entity_type' => 100,
        'species' => 95,
        'your_custom_attribute' => 90,  // Add custom priorities
    ];

    // ...
}
```

### Attribute Labels

Customize display labels:

```php
function saga_format_attribute_label(string $key): string {
    $labels = [
        'birth_year' => 'Born',
        'your_key' => 'Your Custom Label',
    ];

    // ...
}
```

### Styling

Override CSS variables in your child theme:

```css
.comparison-page {
    --comparison-spacing: 2rem;
    --comparison-border-color: #e5e7eb;
    --comparison-diff-bg: #fef3c7;        /* Difference highlight color */
    --comparison-diff-border: #fbbf24;
    --comparison-na-color: #9ca3af;
}
```

### Max Entities

Change maximum entities in JavaScript:

```javascript
// In assets/js/entity-comparison.js
config: {
    maxEntities: 6,  // Change from 4 to 6
    // ...
}
```

## AJAX Endpoints

### Search Entities

```javascript
POST /wp-admin/admin-ajax.php
{
    action: 'saga_search_entities',
    nonce: sagaComparison.nonce,
    query: 'search term',
    exclude: '123,456'  // Comma-separated IDs to exclude
}
```

**Response:**
```json
{
    "success": true,
    "data": [
        {
            "id": 789,
            "title": "Entity Name",
            "slug": "entity-slug",
            "type": "Character",
            "thumbnail": "https://..."
        }
    ]
}
```

### Export Comparison

```javascript
POST /wp-admin/admin-ajax.php
{
    action: 'saga_export_comparison',
    nonce: sagaComparison.nonce,
    entities: '123,456,789'
}
```

**Response:**
```json
{
    "success": true,
    "data": {
        "timestamp": "2025-12-31 12:00:00",
        "entities": [
            {
                "id": 123,
                "title": "Entity Name",
                "type": "character",
                "permalink": "https://..."
            }
        ],
        "attributes": [
            {
                "attribute": "Species",
                "values": ["Human", "Wookiee", "Droid"]
            }
        ]
    }
}
```

## Accessibility

### Keyboard Navigation

- **Tab**: Navigate through controls and search results
- **Arrow Up/Down**: Navigate search results
- **Enter**: Select entity from search
- **Escape**: Close search results

### Screen Readers

- ARIA live regions announce entity additions
- Table semantics with proper headers
- Column and row scopes defined
- Descriptive button labels

### Color Contrast

- WCAG AA compliant color contrast
- High contrast mode support
- Focus indicators visible

## Performance Considerations

### Optimization

- **Search Debouncing**: 300ms delay prevents excessive AJAX calls
- **Scroll Throttling**: 16ms (60fps) for smooth scrolling
- **Max Entities**: Limited to 4 to prevent DOM bloat
- **Lazy Loading**: Thumbnails load on-demand

### Caching

Entity data is cached in `sessionStorage` to reduce server requests:

```javascript
// Automatic caching (handled by JavaScript)
sessionStorage.setItem('saga_entity_123', JSON.stringify(entityData));
```

## Browser Support

- Chrome/Edge 90+
- Firefox 88+
- Safari 14+
- iOS Safari 14+
- Android Chrome 90+

## Troubleshooting

### Search Not Working

1. Check WordPress REST API is accessible
2. Verify nonce is valid (check browser console)
3. Ensure entities have `post_status = 'publish'`

### Entities Not Loading

1. Check URL parameter format: `?entities=123,456`
2. Verify entity IDs/slugs are correct
3. Check PHP error logs for database issues

### Styling Issues

1. Clear browser cache
2. Check for CSS conflicts with parent theme
3. Ensure `entity-comparison.css` is enqueued

### Export Not Working

1. Check browser console for JavaScript errors
2. Verify AJAX endpoint is accessible
3. Check file download permissions

## Future Enhancements

Potential features for future versions:

- [ ] PDF export (requires external library)
- [ ] Image/screenshot export (html2canvas)
- [ ] Drag-to-reorder columns
- [ ] Save comparison as collection
- [ ] Email comparison to friend
- [ ] Embed comparison in posts (shortcode)
- [ ] Compare across multiple sagas
- [ ] Visual diff highlighting (color-coded)
- [ ] Timeline comparison for events
- [ ] Relationship graph comparison

## License

This feature is part of the Saga Manager Theme and follows the same license as the parent theme.

## Support

For issues or questions:
1. Check theme documentation
2. Review code comments
3. Search issue tracker
4. Contact theme support
