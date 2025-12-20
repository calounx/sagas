# Saga Manager Display

Frontend display plugin for the Saga Manager system. Provides shortcodes, Gutenberg blocks, and widgets for displaying saga entities, timelines, and relationships.

## Requirements

- WordPress 6.0+
- PHP 8.2+
- Saga Manager Core plugin (for REST API)

## Installation

1. Upload the `saga-manager-display` folder to `/wp-content/plugins/`
2. Activate through the WordPress admin
3. Ensure Saga Manager Core is installed and configured

## Features

### Shortcodes

#### Entity Display
```
[saga_entity id="123"]
[saga_entity slug="luke-skywalker" layout="full"]
```

**Attributes:**
- `id` - Entity ID
- `slug` - Entity slug (alternative to ID)
- `layout` - Display layout: `card`, `full`, `compact`, `inline`
- `show_image` - Show entity image (true/false)
- `show_type` - Show entity type badge (true/false)
- `show_attributes` - Show entity attributes (true/false)
- `show_relationships` - Show relationships (true/false)
- `link` - Link to entity page (true/false)

#### Timeline
```
[saga_timeline saga="star-wars"]
[saga_timeline saga="dune" layout="horizontal" limit="10"]
```

**Attributes:**
- `saga` - Saga slug (required)
- `layout` - Display layout: `vertical`, `horizontal`, `compact`
- `limit` - Number of events to show
- `order` - Sort order: `asc`, `desc`
- `show_participants` - Show event participants (true/false)
- `show_locations` - Show event locations (true/false)
- `show_descriptions` - Show event descriptions (true/false)
- `group_by` - Group events by: `age`, `year`, `decade`

#### Search
```
[saga_search]
[saga_search saga="star-wars" results_layout="list"]
```

**Attributes:**
- `saga` - Limit to specific saga
- `types` - Comma-separated entity types
- `placeholder` - Search input placeholder
- `show_filters` - Show filter controls (true/false)
- `results_layout` - Results layout: `grid`, `list`
- `semantic` - Enable semantic search (true/false)
- `live_search` - Enable live search (true/false)

#### Relationships
```
[saga_relationships entity="123"]
[saga_relationships entity="123" layout="list" depth="2"]
```

**Attributes:**
- `entity` - Entity ID (required)
- `layout` - Display layout: `graph`, `list`, `tree`
- `depth` - Relationship depth (1-3)
- `types` - Filter by relationship types
- `show_strength` - Show relationship strength (true/false)

### Gutenberg Blocks

- **Entity Display Block** - Display a saga entity
- **Timeline Block** - Display saga timeline
- **Search Block** - Entity search interface

Blocks are found in the "Saga Manager" block category.

### Widgets

- **Recent Entities** - Display recently created entities
- **Entity Search** - Search widget for sidebar

## Template Customization

Templates can be overridden in your theme by placing files in:
```
{your-theme}/saga-manager/
```

Available templates:
- `entity/card.php`
- `entity/full.php`
- `entity/compact.php`
- `entity/inline.php`
- `entity/list.php`
- `timeline/vertical.php`
- `timeline/horizontal.php`
- `timeline/compact.php`
- `search/form.php`
- `relationships/graph.php`
- `relationships/list.php`
- `relationships/tree.php`
- `widget/recent-entities.php`
- `widget/entity-search.php`
- `partials/message.php`
- `partials/loading.php`
- `partials/pagination.php`

## CSS Customization

The plugin uses CSS custom properties for easy theming:

```css
:root {
    --saga-color-primary: #2563eb;
    --saga-color-text: #1e293b;
    --saga-color-bg: #ffffff;
    --saga-color-border: #e2e8f0;
    /* ... see assets/css/main.css for all variables */
}
```

Dark mode is automatically supported via `prefers-color-scheme`.

## JavaScript API

The plugin exposes a global `SagaDisplay` object:

```javascript
// API client
SagaDisplay.api.searchEntities('luke', { type: 'character' });
SagaDisplay.api.getEntity(123);
SagaDisplay.api.getTimeline('star-wars');

// Utilities
SagaDisplay.utils.debounce(fn, 300);
SagaDisplay.utils.throttle(fn, 100);

// Renderers
SagaDisplay.entityRenderer.card(entity);
SagaDisplay.paginationRenderer.render(1, 10);
```

## Development

### Building Blocks

```bash
# Install dependencies
npm install

# Development build with watch
npm start

# Production build
npm run build
```

### Filters

```php
// Modify template data before rendering
add_filter('saga_display_entity_data', function($data, $atts) {
    // Customize data
    return $data;
}, 10, 2);

// Disable caching
add_filter('saga_display_cache_enabled', '__return_false');

// Custom template location
add_filter('saga_display_template_path', function($path, $template) {
    if ($template === 'entity/card.php') {
        return '/custom/path/to/template.php';
    }
    return $path;
}, 10, 2);
```

### Actions

```php
// After plugin initialization
add_action('saga_display_init', function($plugin) {
    // Register custom shortcodes
});

// Register custom shortcodes
add_action('saga_display_register_shortcodes', function($api_client, $template_engine) {
    add_shortcode('my_saga_shortcode', function($atts) use ($api_client) {
        // Custom shortcode logic
    });
}, 10, 2);
```

## Changelog

### 1.0.0
- Initial release
- Entity display shortcode and block
- Timeline shortcode and block
- Search shortcode and block
- Relationships shortcode
- Recent Entities widget
- Entity Search widget
- Template override system
- Dark mode support
- Responsive design

## License

GPL v2 or later
