# Breadcrumb Navigation Documentation

## Overview

The Saga Manager theme includes a comprehensive breadcrumb navigation system with:

- Hierarchical breadcrumb trails for all page types
- Session-based navigation history with "Back" button
- Schema.org structured data for SEO
- Responsive mobile design (collapsible on small screens)
- Full accessibility support (ARIA, keyboard navigation)
- Dark mode and high contrast support

## Features

### 1. Hierarchical Breadcrumbs

Automatically generates breadcrumbs based on page type:

- **Entity Detail Pages**: `Home > Saga Name > Entity Type > Entity Name`
- **Archive Pages**: `Home > Entities`
- **Taxonomy Pages**: `Home > Parent Term > Child Term > Current Term`
- **Search Pages**: `Home > Search Results: "query"`
- **Regular Posts**: `Home > Category > Post Title`
- **Pages**: `Home > Parent Page > Current Page`

### 2. Session History "Back" Button

- Tracks last 5 pages in session (per browser tab)
- Shows "Back" button only when history exists
- Accessible via keyboard (Enter/Space)
- Updates aria-label with previous page title
- Uses sessionStorage (not persistent across tabs)

### 3. Schema.org Markup

All breadcrumbs include JSON-LD structured data:

```json
{
  "@context": "https://schema.org",
  "@type": "BreadcrumbList",
  "itemListElement": [
    {
      "@type": "ListItem",
      "position": 1,
      "name": "Home",
      "item": "https://example.com/"
    },
    {
      "@type": "ListItem",
      "position": 2,
      "name": "Characters",
      "item": "https://example.com/characters/"
    }
  ]
}
```

### 4. Mobile Responsive Design

- **Desktop**: Shows full breadcrumb trail
- **Mobile** (< 768px):
  - Shows only first + last 2 items
  - Displays ellipsis (...) for hidden items
  - Smaller back button (icon only on very small screens)
  - Reduced spacing

### 5. Accessibility Features

- `role="navigation"` with `aria-label="Breadcrumb"`
- Current page marked with `aria-current="page"` (via is_current flag)
- Keyboard navigable (Tab, Enter, Space)
- Focus visible styles (2px outline)
- Screen reader friendly separators (aria-hidden)
- High contrast mode support

## File Structure

```
saga-manager-theme/
├── inc/
│   └── breadcrumb-generator.php      # PHP class for breadcrumb logic
├── template-parts/
│   └── breadcrumbs.php                # HTML template
├── assets/
│   ├── css/
│   │   └── breadcrumbs.css            # Breadcrumb styles
│   └── js/
│       └── breadcrumb-history.js      # Session history tracking
└── functions.php                      # Integration hooks
```

## Usage

### Automatic Display

Breadcrumbs are automatically displayed after the header on all pages (except front page).

### Manual Display

To display breadcrumbs in a custom location:

```php
<?php saga_display_breadcrumbs(); ?>
```

### Customization

#### Change Home Label

```php
add_filter('saga_breadcrumb_home_label', function($label) {
    return 'Start';
});
```

#### Change Separator

```php
add_filter('saga_breadcrumb_separator', function($separator) {
    return ' → ';
});
```

#### Hide Breadcrumbs on Specific Pages

```php
add_filter('saga_show_breadcrumbs', function($show) {
    if (is_page('custom-page')) {
        return false;
    }
    return $show;
});
```

#### Change Hook Location

By default, breadcrumbs are hooked into `generate_after_header`. To change:

```php
// In child theme functions.php
remove_action('after_setup_theme', 'saga_hook_breadcrumbs');

add_action('generate_before_content', 'saga_display_breadcrumbs');
```

## Programmatic Usage

### Get Breadcrumb Data

```php
use SagaManagerTheme\Breadcrumb\BreadcrumbGenerator;

$generator = new BreadcrumbGenerator();
$generator->generate();

// Get items array
$items = $generator->get_items();
// Returns: [
//   ['name' => 'Home', 'url' => 'https://...', 'is_current' => false],
//   ['name' => 'Characters', 'url' => 'https://...', 'is_current' => false],
//   ['name' => 'Luke Skywalker', 'url' => '', 'is_current' => true]
// ]

// Get Schema.org data
$schema = $generator->get_schema();

// Get plain text
$text = $generator->get_text();
// Returns: "Home > Characters > Luke Skywalker"
```

### Check if Breadcrumbs Should Display

```php
$generator = new BreadcrumbGenerator();
$generator->generate();

if ($generator->should_display()) {
    // Display breadcrumbs
}
```

## JavaScript API

### Access History (Debug Mode)

Enable debug mode by setting localStorage:

```javascript
localStorage.setItem('saga_debug', 'true');
```

Then access via console:

```javascript
// Get current history
window.sagaBreadcrumbHistory.getHistory();

// Clear history
window.sagaBreadcrumbHistory.clearHistory();
```

### Session Storage Structure

```javascript
{
  "saga_breadcrumb_history": [
    {
      "url": "https://example.com/page1/",
      "title": "Page 1 Title",
      "timestamp": 1704067200000
    },
    {
      "url": "https://example.com/page2/",
      "title": "Page 2 Title",
      "timestamp": 1704067260000
    }
  ]
}
```

## Styling Customization

### CSS Variables

Override these CSS variables in your theme:

```css
:root {
    /* Colors */
    --saga-bg-secondary: #f8f9fa;
    --saga-border-color: #e0e0e0;
    --saga-text-primary: #333;
    --saga-text-secondary: #6c757d;
    --saga-text-muted: #adb5bd;
    --saga-link-color: #0066cc;
    --saga-link-hover-color: #0052a3;
    --saga-focus-color: #0066cc;

    /* Dark mode */
    --saga-bg-secondary-dark: #1a1a1a;
    --saga-border-color-dark: #333;
    --saga-text-primary-dark: #e0e0e0;
    --saga-link-color-dark: #66b3ff;

    /* Layout */
    --saga-content-width: 1200px;
}
```

### Custom Separator Icon

Replace the chevron icon in `template-parts/breadcrumbs.php`:

```php
<svg width="16" height="16" viewBox="0 0 16 16" fill="none">
    <!-- Your custom icon path -->
</svg>
```

### Disable Mobile Collapse

```css
@media (max-width: 767px) {
    .saga-breadcrumbs__item--mobile-hidden {
        display: inline-flex !important;
    }

    .saga-breadcrumbs__item--ellipsis {
        display: none !important;
    }
}
```

## Performance

- **CSS**: 8KB minified
- **JavaScript**: 4KB minified
- **Session Storage**: ~500 bytes per page (max 2.5KB for 5 pages)
- **No external dependencies**
- **No jQuery required**

## Browser Support

- Chrome/Edge 90+
- Firefox 88+
- Safari 14+
- Opera 76+
- iOS Safari 14+
- Android Chrome 90+

## Accessibility Testing

Tested with:
- NVDA (Windows)
- JAWS (Windows)
- VoiceOver (macOS/iOS)
- TalkBack (Android)
- Keyboard navigation
- WCAG 2.1 Level AA compliant

## SEO Benefits

1. **Rich Snippets**: Google displays breadcrumbs in search results
2. **Site Structure**: Helps search engines understand page hierarchy
3. **Internal Linking**: Provides additional navigation paths
4. **User Experience**: Reduces bounce rate with clear navigation

## Troubleshooting

### Breadcrumbs Not Showing

1. Check if front page (breadcrumbs hidden by default)
2. Verify `saga_show_breadcrumbs` filter
3. Ensure GeneratePress hook exists (`generate_after_header`)

### Back Button Not Appearing

1. Navigate to at least 2 pages in same tab
2. Check browser console for errors
3. Verify sessionStorage is enabled
4. Check if JavaScript is loaded (view page source)

### Schema.org Not Validating

1. Test with [Google Rich Results Test](https://search.google.com/test/rich-results)
2. Verify JSON-LD is in `<script type="application/ld+json">`
3. Check for PHP errors (may break JSON output)

### Mobile Layout Issues

1. Clear browser cache
2. Check for CSS conflicts with other plugins
3. Verify viewport meta tag exists
4. Test with browser dev tools device emulation

## Future Enhancements

- [ ] Breadcrumb history persistence (optional via cookie)
- [ ] Support for custom post types beyond saga_entity
- [ ] Breadcrumb caching for performance
- [ ] Admin settings panel for configuration
- [ ] RTL (right-to-left) language support
- [ ] Yoast SEO integration
- [ ] Rank Math integration

## License

Part of Saga Manager Theme - GPL v2 or later
