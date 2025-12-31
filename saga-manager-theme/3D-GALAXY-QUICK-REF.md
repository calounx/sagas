# 3D Galaxy - Quick Reference Card

**Version:** 1.3.0 | **Phase:** 1 - Next-Gen

## Quick Start

```php
// Basic usage
[saga_galaxy saga_id="1"]

// Custom height
[saga_galaxy saga_id="1" height="800"]

// Light theme
[saga_galaxy saga_id="1" theme="light"]

// Auto-rotate
[saga_galaxy saga_id="1" auto_rotate="true"]
```

## File Locations

```
assets/js/3d-galaxy.js              Main Three.js code
assets/css/3d-galaxy.css            Styling
inc/shortcodes/galaxy-shortcode.php Shortcode handler
inc/ajax/galaxy-data-handler.php    AJAX endpoint
template-parts/galaxy-controls.php  UI controls
```

## Shortcode Parameters

| Param | Type | Default | Range |
|-------|------|---------|-------|
| saga_id | int | 1 | Any valid ID |
| height | int | 600 | 400-1200 |
| auto_rotate | bool | false | true/false |
| show_controls | bool | true | true/false |
| show_minimap | bool | true | true/false |
| theme | string | auto | auto/dark/light |
| particle_count | int | 1000 | 100-3000 |
| node_min_size | float | 2 | 1-10 |
| node_max_size | float | 15 | 10-30 |
| link_opacity | float | 0.4 | 0.1-1.0 |
| force_strength | float | 0.02 | 0.01-0.1 |

## Entity Colors

```css
Character: #4488ff (blue)
Location:  #44ff88 (green)
Event:     #ff8844 (orange)
Faction:   #ff4488 (pink)
Artifact:  #ffaa44 (gold)
Concept:   #8844ff (purple)
```

## Keyboard Shortcuts

```
R     - Reset view
A     - Auto-rotate
Esc   - Deselect
?     - Help
```

## JavaScript API

```javascript
// Get galaxy instance
const container = document.querySelector('.saga-galaxy-container');

// Events
container.addEventListener('galaxy:nodeSelect', (e) => {
    console.log(e.detail.node);
});

container.addEventListener('galaxy:searchComplete', (e) => {
    console.log(e.detail.matchCount);
});

// Methods (if you have direct access)
galaxy.searchEntities('query');
galaxy.filterByType(['character', 'location']);
galaxy.resetView();
galaxy.clearSearch();
galaxy.deselectNode();
galaxy.getStats();
galaxy.dispose();
```

## Common Patterns

### PHP Template
```php
<?php if (have_posts()) : while (have_posts()) : the_post(); ?>
    <h1><?php the_title(); ?></h1>
    <?php echo do_shortcode('[saga_galaxy saga_id="' . get_the_ID() . '"]'); ?>
<?php endwhile; endif; ?>
```

### Conditional Display
```php
<?php if (is_user_logged_in()) : ?>
    [saga_galaxy saga_id="1"]
<?php endif; ?>
```

### Dynamic Configuration
```php
<?php
$height = wp_is_mobile() ? 400 : 800;
echo do_shortcode('[saga_galaxy saga_id="1" height="' . $height . '"]');
?>
```

## AJAX Endpoint

```javascript
// Fetch galaxy data
fetch(sagaGalaxy.ajaxUrl, {
    method: 'POST',
    body: new URLSearchParams({
        action: 'saga_galaxy_data',
        saga_id: 1,
        nonce: sagaGalaxy.nonce
    })
})
.then(r => r.json())
.then(data => console.log(data));
```

## Data Structure

```json
{
  "nodes": [
    {
      "id": 123,
      "name": "Entity Name",
      "type": "character",
      "importance": 95,
      "slug": "entity-name",
      "description": "...",
      "url": "https://...",
      "connections": 42
    }
  ],
  "links": [
    {
      "id": 1,
      "source": 123,
      "target": 456,
      "type": "family",
      "strength": 90
    }
  ]
}
```

## Cache Functions

```php
// Clear cache
saga_clear_galaxy_cache($saga_id);

// Get cached data
$nodes = saga_get_galaxy_nodes($saga_id);
$links = saga_get_galaxy_links($saga_id);

// Export data
saga_export_galaxy_data($saga_id, $download = false);
```

## Performance Tips

```php
// Small datasets (< 100 entities)
[saga_galaxy particle_count="2000" node_max_size="20"]

// Medium datasets (100-500 entities)
[saga_galaxy particle_count="1000" node_max_size="15"]

// Large datasets (500-1000 entities)
[saga_galaxy particle_count="500" node_max_size="10"]

// Huge datasets (1000+ entities)
[saga_galaxy particle_count="300" node_min_size="1" node_max_size="8"]
```

## Custom Styling

```css
/* Override wrapper background */
.saga-galaxy-wrapper {
    background: linear-gradient(135deg, #your-color 0%, #your-color 100%);
}

/* Custom controls panel */
.saga-galaxy-controls {
    background: rgba(your-bg);
    border: 1px solid your-color;
}

/* Custom entity colors */
.saga-galaxy-filter-btn[data-type="character"] {
    --entity-color: #your-color;
}
```

## Debugging

```javascript
// Enable performance monitor
document.querySelector('[data-action="toggle-perf"]').click();

// Check stats
const container = document.querySelector('.saga-galaxy-container');
container.addEventListener('galaxy:graphCreated', (e) => {
    console.log('Stats:', e.detail.galaxy.getStats());
});

// Monitor events
['dataLoaded', 'nodeSelect', 'searchComplete', 'viewReset']
    .forEach(event => {
        container.addEventListener(`galaxy:${event}`, (e) => {
            console.log(`Event: ${event}`, e.detail);
        });
    });
```

## Troubleshooting

| Issue | Solution |
|-------|----------|
| Not loading | Check browser console, verify Three.js CDN |
| Poor performance | Reduce particle_count, limit entities |
| Empty galaxy | Check saga_id, verify entities exist |
| Controls broken | Check for JS conflicts, verify jQuery loaded |
| Cache issues | Call saga_clear_galaxy_cache($saga_id) |

## Browser Console Quick Checks

```javascript
// Check if Three.js loaded
typeof THREE !== 'undefined'

// Check if galaxy class available
typeof SemanticGalaxy !== 'undefined'

// Get galaxy localized data
console.log(sagaGalaxy)

// Test AJAX endpoint
fetch(sagaGalaxy.ajaxUrl, {
    method: 'POST',
    body: new URLSearchParams({
        action: 'saga_galaxy_data',
        saga_id: 1,
        nonce: sagaGalaxy.nonce
    })
}).then(r => r.json()).then(console.log)
```

## Security Checklist

- [x] Nonce verification on AJAX
- [x] Input sanitization (absint, sanitize_text_field)
- [x] SQL injection prevention ($wpdb->prepare)
- [x] Capability checks for exports
- [x] XSS protection (esc_html, esc_attr)

## Performance Targets

| Metric | Desktop | Mobile |
|--------|---------|--------|
| FPS | 60+ | 30+ |
| Render Time | < 20ms | < 35ms |
| Memory | < 150 MB | < 100 MB |
| Max Entities | 1000 | 500 |

## Files & Line Counts

```
3d-galaxy.js          806 lines
3d-galaxy.css         616 lines
galaxy-shortcode.php  492 lines
galaxy-data-handler   503 lines
galaxy-controls.php   329 lines
------------------------
Total:               2746 lines
```

## Version History

### 1.3.0 (2025-01-01)
- Initial release
- Three.js-based 3D visualization
- Force-directed layout
- Interactive controls
- Accessibility features
- Performance optimization

## Support

- Documentation: 3D-GALAXY-README.md
- Examples: example-galaxy-usage.php
- Showcase: page-templates/galaxy-showcase.php

---

**Quick Tip:** Start with default settings, then customize based on your specific needs. Monitor performance and adjust particle_count and entity limits accordingly.
