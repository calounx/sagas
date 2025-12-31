# Enhanced Relationship Graph v2

Advanced D3 v7 relationship graph visualization for Saga Manager Theme (v1.3.0)

## Overview

The Enhanced Relationship Graph v2 is a complete overhaul of the original graph visualization, featuring cutting-edge D3 v7 capabilities, multiple layout algorithms, advanced analytics, and professional-grade interactions.

## Key Features

### 1. Multiple Layout Algorithms

Six different layout algorithms to visualize your data:

- **Force-Directed** (default): Physics-based simulation with attractive/repulsive forces
- **Hierarchical**: Tree-like structure showing entity relationships
- **Circular**: Entities arranged in a circle
- **Radial**: Concentric circles based on distance from root entity
- **Grid**: Organized grid layout
- **Clustered**: Groups entities by type or custom criteria

### 2. D3 v7 Enhancements

- Curved edge paths with proper arrow markers
- Smooth animations and transitions
- Advanced zoom and pan with constraints
- Particle effects on edges (optional)
- Level of Detail (LOD) rendering

### 3. Performance Optimizations

- **Web Worker**: Offloads force simulation to background thread
- **Canvas Rendering**: Hybrid SVG/Canvas for 1000+ nodes
- **Virtual Rendering**: Only renders visible elements
- **Quadtree Collision Detection**: Efficient spatial indexing
- **Efficient Data Updates**: Minimal DOM manipulation

### 4. Advanced Interactions

- **Drag-to-Rearrange**: Nodes can be repositioned
- **Click to Expand/Collapse**: Show/hide node neighborhoods
- **Double-Click to Focus**: Zoom and center on entity
- **Right-Click Context Menu**: Quick actions per node
- **Multi-Select**: Shift+Click to select multiple nodes
- **Lasso Selection**: Draw selection area (optional)

### 5. Graph Analytics

- **Betweenness Centrality**: Identify key connector nodes
- **Community Detection**: Find clusters and groups
- **Shortest Path**: Find paths between entities
- **Influence Visualization**: Heatmap overlay
- **Export Metrics**: CSV export of analytics

### 6. Temporal Playback

- Timeline slider to show graph evolution
- Play/pause controls
- Speed adjustment
- Configurable time steps

## Installation

### 1. Files Included

```
assets/
├── js/
│   ├── graph-layouts.js          # Layout algorithms
│   ├── graph-worker.js            # Web Worker for simulation
│   ├── relationship-graph-v2.js   # Main graph class
│   └── relationship-graph.js      # Original (v1) - still available
├── css/
│   ├── relationship-graph-v2.css  # Enhanced styles
│   └── relationship-graph.css     # Original (v1) - still available
shortcode/
└── graph-v2-shortcode.php         # Shortcode implementation
template-parts/
└── graph-controls-v2.php          # Advanced controls UI
```

### 2. Dependencies

- D3.js v7.8.5+ (automatically loaded from CDN)
- WordPress 6.0+
- Modern browser with Web Worker support

### 3. Enable the Shortcode

Add to your theme's `functions.php`:

```php
require_once get_template_directory() . '/shortcode/graph-v2-shortcode.php';
```

## Usage

### Basic Shortcode

```
[saga_graph_v2 entity_id="123"]
```

### Advanced Shortcode Examples

#### Hierarchical Layout

```
[saga_graph_v2 entity_id="123" layout="hierarchical" hierarchical_orientation="horizontal"]
```

#### Circular Layout with Analytics

```
[saga_graph_v2 entity_type="character" layout="circular" show_analytics="true"]
```

#### High-Performance Mode (1000+ nodes)

```
[saga_graph_v2 limit="1000" use_worker="true" use_canvas="true"]
```

#### Clustered by Faction

```
[saga_graph_v2 layout="clustered" cluster_by="faction"]
```

### Shortcode Parameters

#### Basic Parameters (Backward Compatible)

| Parameter | Type | Default | Description |
|-----------|------|---------|-------------|
| `entity_id` | int | 0 | Focus on specific entity |
| `depth` | int | 2 | Relationship depth (1-3) |
| `entity_type` | string | '' | Filter by type |
| `relationship_type` | string | '' | Filter by relationship |
| `limit` | int | 100 | Max entities to display |
| `height` | int | 600 | Container height in pixels |

#### Layout Parameters

| Parameter | Type | Default | Description |
|-----------|------|---------|-------------|
| `layout` | string | force | Layout algorithm (force, hierarchical, circular, radial, grid, clustered) |
| `hierarchical_orientation` | string | vertical | Tree orientation (vertical, horizontal) |
| `radius_step` | int | 100 | Radial layout step size |
| `grid_columns` | int | 0 | Grid columns (0 = auto) |
| `cluster_by` | string | type | Cluster property (type, faction, etc.) |

#### Performance Parameters

| Parameter | Type | Default | Description |
|-----------|------|---------|-------------|
| `use_worker` | bool | true | Enable Web Worker |
| `use_canvas` | string | auto | Canvas rendering (auto, true, false) |
| `animation_duration` | int | 750 | Animation duration (ms) |
| `link_distance` | int | 100 | Force layout link distance |
| `charge_strength` | int | -300 | Force layout charge strength |
| `collision_radius` | int | 30 | Node collision radius |

#### Feature Toggles

| Parameter | Type | Default | Description |
|-----------|------|---------|-------------|
| `show_analytics` | bool | true | Display analytics panel |
| `show_temporal` | bool | false | Enable temporal playback |
| `show_minimap` | bool | true | Show navigation minimap |
| `show_legend` | bool | true | Show entity type legend |
| `show_controls` | bool | true | Show control panel |
| `enable_multi_select` | bool | true | Enable Shift+Click multi-select |
| `enable_lasso` | bool | true | Enable lasso selection |

## Keyboard Shortcuts

| Key | Action |
|-----|--------|
| `F` | Switch to Force layout |
| `H` | Switch to Hierarchical layout |
| `C` | Switch to Circular layout |
| `R` | Switch to Radial layout |
| `G` | Switch to Grid layout |
| `K` | Switch to Clustered layout |
| `S` | Save current layout |
| `Esc` | Clear selection |
| `Shift+Click` | Multi-select nodes |
| `Double-Click` | Release node position / Reset zoom |

## JavaScript API

### Accessing Graph Instance

```javascript
// Graph instances are stored globally
const graph = window.sagaGraphInstances['saga-graph-v2-1234'];
```

### Methods

#### Layout Methods

```javascript
// Switch layout
graph.switchLayout('hierarchical');

// Apply custom layout configuration
graph.applyLayout('radial', { radiusStep: 150 });
```

#### View Methods

```javascript
// Zoom controls
graph.zoomIn();
graph.zoomOut();
graph.resetZoom();

// Focus on node
graph.focusNode(nodeData);

// Fit graph to view
graph.fitToView();
```

#### Selection Methods

```javascript
// Select node
graph.selectNode(nodeData);

// Multi-select
graph.toggleNodeSelection(nodeData);

// Clear selection
graph.clearSelection();
```

#### Analytics Methods

```javascript
// Calculate centrality
graph.calculateAnalytics();

// Find shortest path
graph.findAndHighlightPath(sourceId, targetId);

// Get analytics data
const analytics = graph.analytics;
console.log(analytics.centrality);
console.log(analytics.communities);
```

#### Filter Methods

```javascript
// Filter by entity type
graph.filterByEntityType('character');

// Filter by relationship type
graph.filterByRelationshipType('ally');

// Filter by strength
graph.filterByStrength(50);

// Filter by importance
graph.filterByImportance(70);

// Clear all filters
graph.clearFilters();
```

#### Export Methods

```javascript
// Export as PNG
graph.exportPNG();

// Export as SVG
graph.exportSVG();

// Save layout to localStorage
graph.saveLayout();
```

## Performance Guidelines

### Recommended Limits

- **SVG Mode**: Up to 500 nodes for optimal performance
- **Canvas Mode**: 500-1000+ nodes
- **Web Worker**: Always enable for 100+ nodes

### Optimization Tips

1. **Use Canvas for Large Graphs**: Set `use_canvas="true"` for 500+ nodes
2. **Enable Web Worker**: Prevents UI blocking during simulation
3. **Reduce Animation**: Lower `animation_duration` for faster interactions
4. **Limit Depth**: Keep `depth="2"` or less for large datasets
5. **Static Layouts**: Use hierarchical/grid/circular for fixed positions

## Browser Compatibility

| Browser | Version | Support |
|---------|---------|---------|
| Chrome | 90+ | Full |
| Firefox | 88+ | Full |
| Safari | 14+ | Full |
| Edge | 90+ | Full |
| Opera | 76+ | Full |

**Required Features:**
- ES6 (arrow functions, classes)
- Web Workers
- Canvas API
- SVG
- localStorage

## Accessibility

### Features

- **ARIA Labels**: All interactive elements labeled
- **Keyboard Navigation**: Tab through nodes, Enter to activate
- **Screen Reader Support**: Live region announcements
- **Focus Indicators**: Clear visual focus states
- **High Contrast Mode**: Automatic adjustments
- **Reduced Motion**: Respects `prefers-reduced-motion`

### WCAG 2.1 Compliance

- Level AA compliant
- Color-blind friendly palette
- Minimum contrast ratios met
- Keyboard-only operation supported

## Troubleshooting

### Graph Not Rendering

1. **Check Console**: Look for JavaScript errors
2. **Verify D3 Loaded**: `typeof d3` should be `'object'`
3. **Check Data**: Ensure API endpoint returns valid data
4. **Container Height**: Set explicit height on container

### Performance Issues

1. **Enable Web Worker**: Set `use_worker="true"`
2. **Use Canvas**: Set `use_canvas="true"`
3. **Reduce Limit**: Lower `limit` value
4. **Simplify Layout**: Use grid or circular instead of force

### Web Worker Errors

1. **Check Path**: Verify `graph-worker.js` is accessible
2. **CORS Issues**: Ensure same-origin policy
3. **Fallback**: Worker failures automatically fallback to local simulation

### Layout Issues

1. **Reset Layout**: Press `S` to save, then reload
2. **Clear Cache**: Clear localStorage for graph
3. **Check Root Node**: Ensure root node exists for hierarchical/radial

## Migration from v1

### Backward Compatibility

The original `[saga_relationship_graph]` shortcode still works. v2 is a separate implementation with `[saga_graph_v2]`.

### Key Differences

| Feature | v1 | v2 |
|---------|----|----|
| Layouts | Force only | 6 layouts |
| Performance | SVG only | SVG + Canvas |
| Max Nodes | ~200 | 1000+ |
| Analytics | None | Centrality, communities, paths |
| Multi-Select | No | Yes |
| Web Worker | No | Yes |
| Context Menu | No | Yes |
| Temporal | No | Yes |

### Migration Steps

1. **Test with v2**: Add `[saga_graph_v2]` to test page
2. **Compare Performance**: Check with your data size
3. **Customize Layout**: Choose best layout for your use case
4. **Update Templates**: Replace v1 shortcode if satisfied

## Advanced Customization

### Custom Layout Algorithms

Add to `graph-layouts.js`:

```javascript
window.SagaGraphLayouts.myCustomLayout = function(nodes, edges, width, height, options) {
    // Your layout logic
    nodes.forEach((node, i) => {
        node.x = /* calculate X */;
        node.y = /* calculate Y */;
        node.fx = node.x;
        node.fy = node.y;
    });

    return null; // or simulation object
};
```

Use in shortcode:

```
[saga_graph_v2 layout="myCustomLayout"]
```

### Custom Colors

Override in your theme CSS:

```css
:root {
    --saga-character-color: #0173B2;
    --saga-location-color: #029E73;
    /* etc. */
}
```

### Custom Analytics

Extend analytics in `relationship-graph-v2.js`:

```javascript
graph.customAnalytics = function() {
    // Your analytics logic
    return results;
};
```

## Version History

### v2.0.0 (1.3.0) - 2024

- Initial release of enhanced graph
- D3 v7 upgrade
- 6 layout algorithms
- Web Worker support
- Canvas rendering
- Advanced analytics
- Multi-select and lasso
- Context menus
- Temporal playback
- Comprehensive documentation

## Support

- **Theme Documentation**: See `CLAUDE.md` for architecture
- **Issue Tracker**: Report bugs in theme repository
- **Performance**: Target <50ms query time, sub-100ms render

## License

Part of Saga Manager Theme - see main theme license

## Credits

- **D3.js v7**: Mike Bostock and contributors
- **Layout Algorithms**: Adapted from D3 examples
- **Color Palette**: Color-blind friendly palette by Bang Wong
- **Icons**: Unicode emoji symbols

## Future Enhancements (Planned)

- [ ] 3D graph visualization
- [ ] VR/AR support
- [ ] Real-time collaborative editing
- [ ] AI-suggested relationships
- [ ] Graph diff visualization
- [ ] Custom node shapes
- [ ] Edge bundling
- [ ] Fisheye distortion
- [ ] Time-series animation
- [ ] Export to Gephi/Cytoscape

---

For questions or contributions, see theme documentation.
