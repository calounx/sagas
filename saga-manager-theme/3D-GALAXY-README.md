# 3D Semantic Galaxy Visualization

**Version:** 1.3.0
**Status:** Phase 1 - Next-Gen Features
**Framework:** Three.js

## Overview

The 3D Semantic Galaxy visualization provides an immersive, interactive way to explore entity relationships in saga universes. Entities are rendered as spheres in 3D space with force-directed positioning, creating a galaxy-like structure where connections are visually represented.

## Features

### Core Visualization
- **3D Force-Directed Graph:** Entities positioned using physics-based simulation
- **Entity Nodes:** Rendered as colored spheres with size based on importance
- **Relationship Edges:** Lines connecting related entities with varying opacity
- **Starfield Background:** Immersive particle system for depth
- **Dynamic Labels:** Billboarded text sprites that face the camera

### Interactions
- **Orbit Controls:** Rotate, zoom, and pan with mouse/touch
- **Node Selection:** Click entities to view detailed information
- **Hover Effects:** Visual feedback with scaling and glow
- **Search/Filter:** Real-time entity search and type filtering
- **Keyboard Shortcuts:** Power-user navigation and controls

### Performance
- **Optimized Rendering:** Handles 1000+ entities smoothly
- **Object Caching:** WordPress transient cache for data
- **Efficient Force Simulation:** Pre-calculated with configurable iterations
- **Responsive Canvas:** Adapts to container size

### Accessibility
- **Keyboard Navigation:** Full keyboard support
- **ARIA Labels:** Screen reader compatible
- **Reduced Motion:** Respects prefers-reduced-motion
- **High Contrast:** Dark/light mode support

## Installation

The 3D Galaxy feature is automatically available once the theme is activated. No additional setup required.

### File Structure

```
saga-manager-theme/
├── assets/
│   ├── js/
│   │   └── 3d-galaxy.js          # Main Three.js visualization
│   └── css/
│       └── 3d-galaxy.css         # Styling and themes
├── inc/
│   ├── shortcodes/
│   │   └── galaxy-shortcode.php  # Shortcode handler
│   └── ajax/
│       └── galaxy-data-handler.php # AJAX endpoint
└── template-parts/
    └── galaxy-controls.php       # UI controls template
```

## Usage

### Shortcode

Basic usage:

```php
[saga_galaxy saga_id="1"]
```

Full parameters:

```php
[saga_galaxy
    saga_id="1"
    height="600"
    auto_rotate="false"
    show_controls="true"
    show_minimap="true"
    theme="auto"
    particle_count="1000"
    node_min_size="2"
    node_max_size="15"
    link_opacity="0.4"
    force_strength="0.02"
]
```

### Parameters

| Parameter | Type | Default | Description |
|-----------|------|---------|-------------|
| `saga_id` | int | 1 | Saga post ID to visualize |
| `height` | int | 600 | Canvas height in pixels |
| `auto_rotate` | bool | false | Enable automatic rotation |
| `show_controls` | bool | true | Display control panel |
| `show_minimap` | bool | true | Display navigation minimap |
| `theme` | string | auto | Theme mode: auto, dark, light |
| `particle_count` | int | 1000 | Number of background stars |
| `node_min_size` | float | 2 | Minimum node radius |
| `node_max_size` | float | 15 | Maximum node radius |
| `link_opacity` | float | 0.4 | Relationship line opacity |
| `force_strength` | float | 0.02 | Force simulation strength |

### PHP Template

```php
<?php
// In your theme template
if (function_exists('saga_galaxy_shortcode_handler')) {
    echo do_shortcode('[saga_galaxy saga_id="' . get_the_ID() . '"]');
}
?>
```

### JavaScript API

```javascript
// Get galaxy instance
const container = document.querySelector('.saga-galaxy-container');
const galaxy = container.dataset.galaxy;

// Listen for events
container.addEventListener('galaxy:nodeSelect', function(e) {
    const node = e.detail.node;
    console.log('Selected:', node.name);
});

container.addEventListener('galaxy:searchComplete', function(e) {
    console.log('Found:', e.detail.matchCount, 'matches');
});

// Programmatic control
galaxy.searchEntities('Luke');
galaxy.filterByType(['character', 'location']);
galaxy.resetView();
galaxy.clearSearch();
```

## Entity Type Colors

The visualization uses color coding for different entity types:

- **Character:** Blue (#4488ff)
- **Location:** Green (#44ff88)
- **Event:** Orange (#ff8844)
- **Faction:** Pink (#ff4488)
- **Artifact:** Gold (#ffaa44)
- **Concept:** Purple (#8844ff)

## Keyboard Shortcuts

| Key | Action |
|-----|--------|
| `R` | Reset camera view |
| `A` | Toggle auto-rotation |
| `Esc` | Deselect current node |
| `?` | Show keyboard shortcuts |

## Mouse/Touch Controls

| Action | Mouse | Touch |
|--------|-------|-------|
| Rotate | Left-click + drag | One-finger drag |
| Zoom | Scroll wheel | Pinch |
| Pan | Right-click + drag | Two-finger drag |
| Select | Click on node | Tap on node |

## Data Requirements

### Entity Data Structure

The AJAX endpoint returns entity nodes with the following structure:

```json
{
  "nodes": [
    {
      "id": 123,
      "name": "Luke Skywalker",
      "type": "character",
      "importance": 95,
      "slug": "luke-skywalker",
      "description": "Jedi Knight and hero...",
      "url": "https://example.com/entity/luke-skywalker",
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

### Database Integration

The handler supports two data sources:

1. **Custom Tables** (Plugin architecture):
   - `wp_saga_entities`
   - `wp_saga_entity_relationships`

2. **WordPress Posts** (Fallback):
   - Post type: `saga_entity`
   - Meta fields: `saga_id`, `entity_type`, `importance_score`
   - Relationships via post meta

## Caching

Data is cached using WordPress transients for 5 minutes:

```php
// Cache keys
saga_galaxy_nodes_{saga_id}
saga_galaxy_links_{saga_id}

// Clear cache manually
saga_clear_galaxy_cache($saga_id);

// Automatic cache clearing on entity save
add_action('save_post', 'saga_clear_galaxy_cache_on_save');
```

## Customization

### Custom Styles

Override default styles in your child theme:

```css
/* Custom entity colors */
.saga-galaxy-wrapper {
    --character-color: #your-color;
    --location-color: #your-color;
}

/* Custom panel styling */
.saga-galaxy-controls {
    background: rgba(your-bg);
    border-radius: your-radius;
}
```

### Custom Force Simulation

Modify the physics parameters:

```javascript
const galaxy = new SemanticGalaxy(container, {
    forceStrength: 0.05,  // Stronger repulsion
    nodeMinSize: 5,        // Larger nodes
    linkOpacity: 0.8       // More visible links
});
```

### Custom Node Rendering

Extend the `SemanticGalaxy` class:

```javascript
class CustomGalaxy extends SemanticGalaxy {
    createNodes() {
        // Custom node geometry
        const geometry = new THREE.DodecahedronGeometry(size, 0);
        // ... custom implementation
        super.createNodes();
    }
}
```

## Performance Optimization

### Recommended Limits

- **Optimal:** Up to 500 entities
- **Good:** 500-1000 entities
- **Degraded:** 1000+ entities

### Performance Tips

1. **Reduce Particle Count:**
   ```php
   [saga_galaxy particle_count="500"]
   ```

2. **Limit Data Query:**
   ```php
   // In galaxy-data-handler.php
   LIMIT 500  // Reduce from 1000
   ```

3. **Disable Auto-Rotate:**
   ```php
   [saga_galaxy auto_rotate="false"]
   ```

4. **Enable Performance Monitor:**
   - Click "Performance" button in controls
   - Monitor FPS and render time

### Browser Compatibility

- **Chrome/Edge:** Excellent (90+ FPS)
- **Firefox:** Good (60+ FPS)
- **Safari:** Good (60+ FPS)
- **Mobile:** Moderate (30+ FPS)

**Requirements:**
- WebGL support (97% of browsers)
- ES6 JavaScript support
- Minimum 2GB RAM

## Troubleshooting

### Common Issues

**1. Galaxy not loading**
- Check browser console for errors
- Verify Three.js CDN is accessible
- Ensure saga_id exists and has entities

**2. Poor performance**
- Reduce particle_count
- Limit entity query in AJAX handler
- Disable auto_rotate
- Close other browser tabs

**3. Entities not appearing**
- Check AJAX response in Network tab
- Verify entity data structure
- Clear WordPress cache
- Check database table existence

**4. Controls not working**
- Ensure jQuery is loaded
- Check for JavaScript conflicts
- Verify nonce is valid

### Debug Mode

Enable performance monitor:

```javascript
// Show FPS and stats
document.querySelector('[data-action="toggle-perf"]').click();
```

Check AJAX response:

```javascript
// In browser console
fetch(sagaGalaxy.ajaxUrl, {
    method: 'POST',
    body: new URLSearchParams({
        action: 'saga_galaxy_data',
        saga_id: 1,
        nonce: sagaGalaxy.nonce
    })
})
.then(r => r.json())
.then(console.log);
```

## Security

### Nonce Verification

All AJAX requests require valid nonces:

```php
check_ajax_referer('saga_galaxy_nonce', 'nonce', false);
```

### Data Sanitization

All inputs are sanitized:

```php
$saga_id = absint($_POST['saga_id']);
$query = sanitize_text_field($_POST['query']);
```

### SQL Injection Prevention

Using `$wpdb->prepare()` for all queries:

```php
$query = $wpdb->prepare(
    "SELECT * FROM {$table} WHERE saga_id = %d",
    $saga_id
);
```

## Future Enhancements (Phase 2+)

- [ ] VR/AR support for immersive exploration
- [ ] Real-time collaboration (multi-user)
- [ ] Advanced physics (gravity, collision)
- [ ] Custom node shapes per entity type
- [ ] Animated relationship formation
- [ ] Sound effects and ambient audio
- [ ] Timeline integration (4D visualization)
- [ ] Export to glTF/USD formats
- [ ] Machine learning for auto-layout
- [ ] Cluster analysis visualization

## Credits

### Libraries
- **Three.js:** https://threejs.org/ (MIT License)
- **OrbitControls:** Three.js examples

### Inspiration
- Star Wars galaxy maps
- Marvel Cinematic Universe relationship graphs
- Scientific data visualization tools

## Support

For issues, feature requests, or questions:

1. Check this documentation
2. Review browser console for errors
3. Test with default parameters
4. Check compatibility requirements
5. Open GitHub issue with details

## Changelog

### Version 1.3.0 (2025-01-01)
- Initial release of 3D Galaxy visualization
- Three.js-based force-directed graph
- Interactive controls and filtering
- Dark/light mode support
- Accessibility features
- Performance optimization
- WordPress integration

## License

This feature is part of the Saga Manager Theme and follows the same license terms.

---

**Note:** This is a Phase 1 next-generation feature. Future versions will add VR/AR support, real-time collaboration, and advanced physics simulations.
