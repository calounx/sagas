# Masonry Layout with Infinite Scroll

Premium Pinterest-style masonry grid layout for Saga Manager entity archives with elegant design and infinite scroll functionality.

## Features

### Masonry Grid Layout
- **Pinterest-style layout** with variable height cards
- **Responsive columns**: 4 desktop → 3 tablet → 2 mobile → 1 small mobile
- **Smooth animations** when items are added
- **Gap spacing**: 24px desktop, 16px mobile
- **Natural flow** for different content heights

### Infinite Scroll
- **Automatic loading** when scrolling near bottom (300px threshold)
- **AJAX pagination** for seamless experience
- **Loading indicators**: elegant spinner with skeleton cards
- **End-of-content message** when no more items
- **URL updates** with page number for bookmarking
- **Keyboard accessible** Load More button fallback
- **Browser back button** support with history API

### Elegant Design
- **Premium card design** with subtle multi-layer shadows
- **Smooth hover effects** with scale transforms
- **Gradient overlays** on images
- **Typography**: Playfair Display (headings) + Inter (body)
- **Color palette**: sophisticated neutrals with accent colors
- **Animations**: fade-in, slide-up for new items
- **Frosted glass effects** (where supported)
- **High-quality image loading** with lazy loading
- **Rounded corners**: 12-16px for modern look
- **Dark mode support** with elegant transitions

## File Structure

```
saga-manager-theme/
├── assets/
│   ├── css/
│   │   ├── masonry-layout.css      # Grid layout styles
│   │   ├── elegant-cards.css       # Premium card design
│   │   └── archive-header.css      # Archive header & filters
│   └── js/
│       ├── masonry-layout.js       # Masonry initialization
│       └── infinite-scroll.js      # Infinite scroll logic
├── inc/
│   └── ajax-load-more.php          # AJAX endpoint
├── template-parts/
│   └── entity-card-masonry.php     # Masonry card template
└── archive-saga_entity.php         # Archive template
```

## Installation

### 1. Files Already Included

All necessary files are already created in the theme. The masonry layout will automatically activate on entity archive pages.

### 2. Required Libraries

The theme automatically loads these libraries from CDN:
- **Masonry.js** v4.2.2 (layout engine)
- **imagesLoaded** v5.0.0 (image loading detection)

### 3. WordPress Integration

The feature automatically integrates with:
- Entity archives (`/saga-entities/`)
- Entity type taxonomies (`/saga-type/character/`)
- Search results for entities

## Usage

### Basic Archive Display

The masonry layout automatically activates on entity archives. No additional configuration needed.

```php
// Just visit: /saga-entities/
// Or any taxonomy: /saga-type/character/
```

### Customize Per Page Items

Modify the number of items loaded per page:

```php
// In functions.php
add_filter('saga_masonry_per_page', function($per_page) {
    return 16; // Default: 12
});
```

### Customize Scroll Threshold

Adjust when infinite scroll triggers:

```php
// In functions.php
add_filter('saga_masonry_threshold', function($threshold) {
    return 500; // Default: 300px from bottom
});
```

### Disable Infinite Scroll

Keep masonry layout but disable infinite scroll:

```php
// In archive-saga_entity.php, change data attribute:
<div class="saga-masonry-grid" data-infinite-scroll="false">
```

### Custom Card Heights

Modify the `entity-card-masonry.php` template to control height variations:

```php
// Remove random height variation:
// Delete or modify this line:
$height_variation = $height_variations[array_rand($height_variations)];

// Set fixed height:
$height_variation = 'medium'; // Options: short, medium, tall
```

## Customization

### Card Styling

Edit `/assets/css/elegant-cards.css`:

```css
/* Change card background */
.saga-entity-card-masonry {
    background: your-gradient-here;
}

/* Modify hover effect */
.saga-entity-card-masonry:hover {
    transform: translateY(-10px) scale(1.03);
}

/* Customize entity type colors */
.saga-entity-card-masonry__type-badge--character {
    background: linear-gradient(135deg, #your-color-1, #your-color-2);
    color: #your-text-color;
}
```

### Typography

Change fonts in `/assets/css/elegant-cards.css`:

```css
:root {
    --font-heading: 'Your Serif Font', Georgia, serif;
    --font-body: 'Your Sans Font', -apple-system, sans-serif;
}
```

Or use Google Fonts:

```css
@import url('https://fonts.googleapis.com/css2?family=Crimson+Pro:wght@400;600;700&family=Work+Sans:wght@300;400;600&display=swap');

:root {
    --font-heading: 'Crimson Pro', Georgia, serif;
    --font-body: 'Work Sans', sans-serif;
}
```

### Grid Columns

Modify responsive breakpoints in `/assets/css/masonry-layout.css`:

```css
/* 5 columns for large screens */
@media (min-width: 1600px) {
    .saga-masonry-grid {
        grid-template-columns: repeat(5, 1fr);
    }
}

/* 2 columns for smaller tablets */
@media (min-width: 600px) and (max-width: 900px) {
    .saga-masonry-grid {
        grid-template-columns: repeat(2, 1fr);
    }
}
```

### Loading Spinner

Customize in `/assets/css/masonry-layout.css`:

```css
.saga-masonry-loading__spinner {
    width: 60px;
    height: 60px;
    border: 5px solid rgba(0, 0, 0, 0.1);
    border-left-color: #your-accent-color;
}
```

### Color Palette

Update CSS custom properties:

```css
:root {
    --saga-accent-color: #6366f1;        /* Primary accent */
    --saga-accent-dark: #4f46e5;         /* Darker accent */
    --saga-text-primary: #111827;        /* Main text */
    --saga-text-secondary: #4b5563;      /* Secondary text */
    --saga-text-muted: #6b7280;          /* Muted text */
}
```

## JavaScript Events

### Listen to Masonry Events

```javascript
// Masonry loaded
document.querySelector('.saga-masonry-grid').addEventListener('sagaMasonry:masonryLoaded', (e) => {
    console.log('Masonry initialized');
});

// Items added
document.querySelector('.saga-masonry-grid').addEventListener('sagaMasonry:itemsAdded', (e) => {
    console.log('New items added:', e.detail.items);
});
```

### Listen to Infinite Scroll Events

```javascript
// Items loaded via infinite scroll
document.querySelector('.saga-masonry-grid').addEventListener('sagaInfiniteScroll:itemsLoaded', (e) => {
    console.log('Page:', e.detail.page);
    console.log('Has more:', e.detail.hasMore);
    console.log('Items:', e.detail.items);
});

// Load error
document.querySelector('.saga-masonry-grid').addEventListener('sagaInfiniteScroll:loadError', (e) => {
    console.error('Load error:', e.detail.message);
});
```

### Manual Control

```javascript
// Get masonry instance
const container = document.querySelector('.saga-masonry-grid');
const masonryInstance = container.sagaMasonry;

// Trigger layout
masonryInstance.layout();

// Add items manually
const newElements = [...]; // Array of DOM elements
masonryInstance.addItems(newElements);

// Get infinite scroll instance
const infiniteScrollInstance = container.sagaInfiniteScroll;

// Manually trigger load more
infiniteScrollInstance.loadMore();

// Destroy infinite scroll
infiniteScrollInstance.destroy();
```

## Accessibility

### Keyboard Navigation

- **Tab** through cards and buttons
- **Enter/Space** to activate Load More button
- **Focus visible** indicators on all interactive elements

### Screen Reader Support

- **ARIA labels** on all controls
- **Live regions** announce loaded content
- **Semantic HTML** for proper document structure
- **Alt text** on all images

### Reduced Motion

Users with `prefers-reduced-motion` enabled will see:
- No animations
- Instant state changes
- Simplified visual effects

## Performance

### Optimization Features

- **Lazy loading** images with `loading="lazy"`
- **Debounced scroll** events (100ms)
- **Throttled resize** events (250ms)
- **CSS containment** for better paint performance
- **Intersection Observer** for efficient scroll detection
- **Progressive rendering** with staggered animations

### Performance Targets

- **Initial load**: < 2s (12 items)
- **AJAX pagination**: < 500ms
- **Layout recalculation**: < 50ms
- **Smooth scrolling**: 60 FPS

### Tips for Better Performance

1. **Optimize images**: Use WebP format, responsive sizes
2. **Limit cards per page**: 12-16 items optimal
3. **Use CDN**: Images served from CDN
4. **Enable caching**: WordPress object cache
5. **Minify assets**: Use build tools in production

## Browser Support

### Fully Supported
- Chrome 90+
- Firefox 88+
- Safari 14+
- Edge 90+

### Graceful Degradation
- **No JavaScript**: Fallback to standard pagination
- **Old browsers**: CSS Grid fallback to flexbox
- **No backdrop-filter**: Regular backgrounds

## Troubleshooting

### Masonry Not Initializing

**Check console for errors:**
```javascript
// Open browser console (F12)
// Look for: [Saga Masonry] errors
```

**Ensure libraries loaded:**
```javascript
console.log(typeof Masonry);     // Should be "function"
console.log(typeof imagesLoaded); // Should be "function"
```

### Infinite Scroll Not Working

**Verify AJAX endpoint:**
```javascript
console.log(sagaInfiniteScrollData.ajaxUrl);
console.log(sagaInfiniteScrollData.nonce);
```

**Check network tab:**
- Look for `admin-ajax.php` requests
- Status should be 200
- Response should be JSON

### Cards Overlapping

**Trigger layout recalculation:**
```javascript
const container = document.querySelector('.saga-masonry-grid');
if (container.sagaMasonry) {
    container.sagaMasonry.layout();
}
```

**Check image loading:**
```javascript
// Ensure all images have loaded
imagesLoaded(container).on('done', () => {
    container.sagaMasonry.layout();
});
```

### Styling Issues

**Clear browser cache**: Hard refresh (Ctrl+Shift+R / Cmd+Shift+R)

**Check CSS enqueue priority**:
```php
// In functions.php
add_action('wp_enqueue_scripts', 'saga_enqueue_masonry_assets', 15);
```

**Verify CSS loaded**:
```javascript
// Check in browser console
document.styleSheets; // Look for masonry CSS files
```

## Advanced Customization

### Add Filters to Cards

Implement client-side filtering:

```javascript
// Filter by entity type
function filterByType(type) {
    const items = document.querySelectorAll('.saga-masonry-grid__item');

    items.forEach(item => {
        const card = item.querySelector('.saga-entity-card-masonry');
        const cardType = card.dataset.entityType;

        if (type === 'all' || cardType === type) {
            item.style.display = 'block';
        } else {
            item.style.display = 'none';
        }
    });

    // Re-layout masonry
    document.querySelector('.saga-masonry-grid').sagaMasonry.layout();
}
```

### Skeleton Loading

The theme includes skeleton cards by default. Customize in `/assets/css/masonry-layout.css`:

```css
.saga-masonry-skeleton {
    /* Your custom skeleton styles */
}
```

### Virtual Scrolling

For 1000+ items, implement virtual scrolling:

```javascript
// Use a library like react-window or vue-virtual-scroller
// Or implement custom virtual scrolling logic
```

## Integration with Other Features

### Collections (Bookmarks)

Cards automatically include bookmark buttons if collections are enabled:

```php
// In entity-card-masonry.php
<?php if (function_exists('saga_bookmark_button')): ?>
    <button class="saga-entity-card-masonry__bookmark">
        <!-- Bookmark icon -->
    </button>
<?php endif; ?>
```

### Entity Previews

Hover previews work seamlessly with masonry cards. Just ensure the hover preview script is loaded.

### Search Integration

Search functionality automatically filters masonry grid:

```php
// Archive template includes search form
<input type="search" name="s" class="saga-filter-form__search">
```

## Credits

- **Masonry.js**: https://masonry.desandro.com/
- **imagesLoaded**: https://imagesloaded.desandro.com/
- **Fonts**: Google Fonts (Playfair Display, Inter)

## License

Part of Saga Manager Theme - Licensed under GPL v2 or later.

## Support

For issues or questions:
- Check troubleshooting section above
- Review browser console for errors
- Verify all files are properly enqueued
- Test with default WordPress theme to isolate issue
