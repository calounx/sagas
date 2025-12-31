# Saga Manager - GeneratePress Child Theme

A specialized child theme for GeneratePress, designed to work seamlessly with the Saga Manager WordPress plugin. This theme provides custom templates, advanced filtering, and a polished UI for managing complex fictional universe entities.

## Overview

**Theme Name:** Saga Manager - GeneratePress Child  
**Parent Theme:** GeneratePress  
**Version:** 1.0.0  
**Requires:** WordPress 6.0+, PHP 8.2+  
**License:** GPL v2 or later

## Features

### Entity Display
- **Custom Post Type Templates:** Specialized templates for `saga_entity` posts
- **Entity Cards:** Beautiful, responsive card layouts for archives
- **Entity Metadata:** Display saga name, type, importance score, and quality metrics
- **Type Badges:** Color-coded badges for different entity types (character, location, event, faction, artifact, concept)
- **Importance Indicators:** Visual importance score with progress bars

### Relationships & Timeline
- **Entity Relationships:** Display related entities grouped by relationship type
- **Relationship Strength:** Visual indicators for relationship strength (high, medium, low)
- **Timeline Events:** Chronological display of events associated with entities
- **Canon Dates:** Properly formatted saga-specific dates

### Advanced Filtering
- **AJAX-Powered Filters:** Real-time filtering without page reloads
- **Multi-Criteria Search:** Filter by saga, entity type, importance range, and keywords
- **URL State Management:** Filters are reflected in URL for bookmarking
- **Sort Options:** Sort by importance, name, or date added

### Navigation
- **Prev/Next Navigation:** Navigate between entities within the same saga
- **Thumbnail Previews:** Visual previews in navigation links
- **Breadcrumbs:** Custom breadcrumbs showing entity hierarchy

### WordPress Customizer Integration
- **Layout Options:** Choose between grid, list, or masonry layouts
- **Display Toggles:** Show/hide importance scores, type badges, relationships, timeline
- **Pagination Control:** Customize entities per page
- **Real-time Preview:** See changes instantly in the customizer

## File Structure

```
saga-manager-theme/
├── style.css                          # Theme stylesheet with custom CSS
├── functions.php                      # Main theme functions
├── screenshot.png                     # Theme screenshot
├── README.md                          # This file
│
├── inc/                               # Helper files
│   ├── saga-helpers.php              # Utility functions for entity data
│   ├── saga-queries.php              # Custom WP_Query helpers
│   ├── saga-hooks.php                # GeneratePress hook integrations
│   └── saga-customizer.php           # WordPress Customizer settings
│
├── template-parts/                    # Reusable template parts
│   └── saga/
│       ├── entity-card.php           # Entity card for archives
│       ├── entity-meta.php           # Entity metadata display
│       ├── entity-relationships.php  # Relationships display
│       ├── entity-timeline.php       # Timeline events display
│       ├── entity-search-filters.php # Archive filter form
│       └── entity-navigation.php     # Prev/Next navigation
│
├── archive-saga_entity.php            # Archive template
├── single-saga_entity.php             # Single entity template
├── taxonomy-saga_type.php             # Taxonomy archive template
│
└── assets/                            # Theme assets
    ├── css/                           # Additional stylesheets
    ├── js/
    │   └── saga-filters.js           # AJAX filtering JavaScript
    └── images/                        # Theme images
```

## Installation

1. **Install Parent Theme:**
   ```bash
   # Install GeneratePress from WordPress.org
   wp theme install generatepress --activate
   ```

2. **Install Child Theme:**
   ```bash
   # Copy theme to WordPress themes directory
   cp -r saga-manager-theme /path/to/wordpress/wp-content/themes/
   
   # Activate child theme
   wp theme activate saga-manager-theme
   ```

3. **Install Saga Manager Plugin:**
   ```bash
   # Ensure the Saga Manager plugin is installed and activated
   wp plugin activate saga-manager
   ```

## Template Hierarchy

### Single Entity Page
```
single-saga_entity.php
└── template-parts/saga/
    ├── entity-meta.php         (displays metadata)
    ├── entity-relationships.php (after content)
    ├── entity-timeline.php     (after content)
    └── entity-navigation.php   (prev/next links)
```

### Entity Archive
```
archive-saga_entity.php
└── template-parts/saga/
    ├── entity-search-filters.php (filter form)
    └── entity-card.php          (for each entity)
```

### Taxonomy Archive
```
taxonomy-saga_type.php
└── template-parts/saga/
    ├── entity-search-filters.php
    └── entity-card.php
```

## Helper Functions

### Entity Data
```php
// Get entity by post ID
$entity = saga_get_entity_by_post_id($post_id);

// Get entity type
$type = saga_get_entity_type($post_id);

// Get importance score
$score = saga_get_importance_score($post_id);

// Get relationships
$relationships = saga_get_entity_relationships($post_id);

// Get timeline events
$events = saga_get_entity_timeline_events($post_id, 10);
```

### Formatting
```php
// Format importance score with visual bar
echo saga_format_importance_score($score, true);

// Get entity type badge HTML
echo saga_get_entity_type_badge('character');

// Format canon date
echo saga_format_canon_date('10191 AG');
```

### Queries
```php
// Query entities by saga
$query = saga_query_entities_by_saga($saga_id);

// Query entities by type
$query = saga_query_entities_by_type('character');

// Query related entities
$query = saga_query_related_entities($post_id);

// Query top entities by importance
$query = saga_query_top_entities(10);
```

## GeneratePress Integration

This theme leverages GeneratePress hooks for seamless integration:

### Layout Customization
```php
// Customize sidebar layout
add_filter('generate_sidebar_layout', 'saga_customize_sidebar_layout');

// Custom sidebar for entity pages
add_filter('generate_sidebar', 'saga_custom_sidebar');
```

### Content Hooks
```php
// Add content before entity content
add_action('generate_before_content', 'saga_before_entity_content');

// Add content after entity content
add_action('generate_after_entry_content', 'saga_after_entity_content');

// Customize archive header
add_action('generate_before_main_content', 'saga_customize_archive_header');
```

## Customization

### WordPress Customizer
Navigate to **Appearance → Customize → Saga Manager Settings** to configure:

- Entity card layout (grid/list/masonry)
- Show/hide importance scores
- Show/hide entity type badges
- Entities per page
- Show/hide relationships
- Show/hide timeline events

### CSS Customization
Custom styles can be added via:

1. **WordPress Customizer:** Additional CSS section
2. **Child Theme:** Add to `style.css`
3. **Custom CSS File:** Create `assets/css/custom.css` and enqueue in `functions.php`

### PHP Customization
All theme functions can be overridden in a child-child theme or via plugin.

## AJAX Filtering

The theme includes real-time AJAX filtering powered by `saga-filters.js`:

**Features:**
- Filter by saga
- Filter by entity type
- Search by keyword
- Filter by importance range (0-100)
- URL state management
- Debounced search input
- Loading states

**JavaScript Events:**
```javascript
// Custom event fired after filtering
$(document).on('saga:filtered', function(event, data) {
    console.log('Filtered results:', data.found_posts);
});
```

## Performance

### Caching Strategy
- Entity data cached using WordPress object cache
- Cache duration: 5 minutes (300 seconds)
- Cache groups: `saga`

### Database Queries
- Optimized queries using `$wpdb->prepare()`
- Indexed database tables
- Minimal JOINs in EAV queries

### Frontend Performance
- Conditional asset loading
- Minification ready
- Lazy loading compatible

## Security

### Data Sanitization
- All user input sanitized using WordPress functions
- `sanitize_text_field()`, `absint()`, `esc_html()`, `esc_url()`

### SQL Injection Prevention
- All database queries use `$wpdb->prepare()`
- No raw SQL with user input

### Nonce Verification
- AJAX requests verify nonces
- Form submissions check capabilities

### XSS Prevention
- All output escaped using WordPress functions
- `esc_html()`, `esc_attr()`, `esc_url()`, `wp_kses_post()`

## Accessibility

- Semantic HTML5 elements
- ARIA labels where appropriate
- Keyboard navigation support
- Screen reader friendly
- WCAG 2.1 AA compliant

## Browser Support

- Chrome (latest)
- Firefox (latest)
- Safari (latest)
- Edge (latest)
- Mobile browsers (iOS Safari, Chrome Mobile)

## WordPress Standards

This theme follows:
- WordPress Coding Standards (WPCS)
- PHP 8.2+ strict types
- Proper escaping and sanitization
- WordPress template hierarchy
- GeneratePress hook system

## Troubleshooting

### Theme Not Displaying Entities

1. **Check Plugin Activation:**
   ```bash
   wp plugin list
   ```
   Ensure `saga-manager` is active.

2. **Verify Post Type Registration:**
   ```bash
   wp post-type list
   ```
   `saga_entity` should appear.

3. **Check Database Tables:**
   ```bash
   wp db query "SHOW TABLES LIKE 'wp_saga_%';"
   ```

### Filters Not Working

1. **Check JavaScript Console:** Look for errors in browser console
2. **Verify AJAX URL:** Check `sagaAjax` object is defined
3. **Test Nonce:** Ensure nonce verification passes

### Styling Issues

1. **Clear Cache:** Clear any caching plugins
2. **Check Parent Theme:** Ensure GeneratePress is activated
3. **Inspect CSS:** Use browser dev tools to check CSS loading

## Development

### Requirements
- PHP 8.2+
- WordPress 6.0+
- GeneratePress parent theme
- Saga Manager plugin
- MySQL/MariaDB with saga tables

### Coding Standards
```bash
# Install PHP_CodeSniffer with WordPress standards
composer global require squizlabs/php_codesniffer
composer global require wp-coding-standards/wpcs

# Check coding standards
phpcs --standard=WordPress saga-manager-theme/
```

### Testing
```bash
# Test theme activation
wp theme activate saga-manager-theme

# Test with sample data
wp post create --post_type=saga_entity --post_title="Test Entity" --post_content="Test content"
```

## Support

- **GitHub Issues:** https://github.com/calounx/saga-manager-theme/issues
- **Documentation:** https://github.com/calounx/saga-manager-theme/wiki
- **Saga Manager Plugin:** https://github.com/calounx/saga-manager

## Changelog

### 1.0.0 - 2024-12-31
- Initial release
- Custom templates for saga entities
- AJAX filtering and search
- Relationship and timeline display
- WordPress Customizer integration
- GeneratePress hook system
- Complete documentation

## Credits

- **Theme Author:** Saga Manager Team
- **Parent Theme:** GeneratePress by Tom Usborne
- **Built for:** Saga Manager Plugin

## License

This theme is licensed under the GNU General Public License v2 or later.

```
Copyright (C) 2024 Saga Manager Team

This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation; either version 2 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
GNU General Public License for more details.
```
