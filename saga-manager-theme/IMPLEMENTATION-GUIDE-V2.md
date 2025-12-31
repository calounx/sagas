# Enhanced Relationship Graph v2 - Implementation Guide

Quick start guide for implementing the v2 graph in your Saga Manager Theme.

## Quick Start (5 Minutes)

### 1. Enable the Shortcode

Add to `functions.php`:

```php
// Enable enhanced graph v2
require_once get_template_directory() . '/shortcode/graph-v2-shortcode.php';
```

### 2. Add to Any Page/Post

```
[saga_graph_v2 entity_id="123"]
```

Done! The graph will automatically load with default settings.

## File Structure

```
saga-manager-theme/
├── assets/
│   ├── js/
│   │   ├── graph-layouts.js          ← Layout algorithms (14KB)
│   │   ├── graph-worker.js            ← Web Worker (11KB)
│   │   ├── relationship-graph-v2.js   ← Main graph class (45KB)
│   │   └── relationship-graph.js      ← Original v1 (still works)
│   └── css/
│       ├── relationship-graph-v2.css  ← Enhanced styles (20KB)
│       └── relationship-graph.css     ← Original v1 (still works)
├── shortcode/
│   ├── graph-v2-shortcode.php         ← v2 shortcode handler
│   └── relationship-graph-shortcode.php ← v1 shortcode
├── template-parts/
│   ├── graph-controls-v2.php          ← Advanced controls UI
│   └── relationship-graph.php         ← v1 template
├── GRAPH-V2-README.md                 ← Full documentation
└── IMPLEMENTATION-GUIDE-V2.md         ← This file
```

## Common Use Cases

### 1. Character Relationship Map

```
[saga_graph_v2
    entity_id="42"
    layout="radial"
    depth="2"
    show_analytics="true"]
```

**Result**: Radial layout centered on character 42, showing 2 levels of relationships with analytics panel.

### 2. All Characters in Force Layout

```
[saga_graph_v2
    entity_type="character"
    layout="force"
    limit="200"
    use_worker="true"]
```

**Result**: Force-directed graph of up to 200 characters with Web Worker for performance.

### 3. Hierarchical Faction Structure

```
[saga_graph_v2
    entity_id="5"
    layout="hierarchical"
    hierarchical_orientation="horizontal"
    relationship_type="member"]
```

**Result**: Horizontal tree showing faction hierarchy with "member" relationships.

### 4. High-Performance Mode (1000+ Entities)

```
[saga_graph_v2
    limit="1000"
    layout="clustered"
    cluster_by="type"
    use_worker="true"
    use_canvas="true"
    show_controls="true"]
```

**Result**: Clustered layout with Canvas rendering and Web Worker, handles 1000+ entities smoothly.

### 5. Minimal View (No Controls)

```
[saga_graph_v2
    entity_id="10"
    layout="circular"
    show_controls="false"
    show_legend="false"
    show_analytics="false"]
```

**Result**: Clean circular graph without UI clutter.

## Layout Comparison

| Layout | Best For | Performance | Complexity |
|--------|----------|-------------|------------|
| **Force** | General exploration, discovering patterns | Medium | Medium |
| **Hierarchical** | Parent-child relationships, org charts | Fast | Low |
| **Circular** | Equal importance entities, aesthetics | Fast | Low |
| **Radial** | Distance from central entity | Fast | Low |
| **Grid** | Alphabetical/sorted display | Fastest | Lowest |
| **Clustered** | Grouped entities, categories | Medium | Medium |

## Performance Tuning

### Small Graphs (<100 nodes)

```
[saga_graph_v2
    entity_id="1"
    use_worker="false"
    use_canvas="false"
    animation_duration="500"]
```

- Disable worker for simplicity
- SVG rendering is fine
- Faster animations

### Medium Graphs (100-500 nodes)

```
[saga_graph_v2
    limit="300"
    use_worker="true"
    use_canvas="auto"
    link_distance="80"
    charge_strength="-250"]
```

- Enable worker to prevent UI blocking
- Auto canvas (switches at 500)
- Tighter spacing for readability

### Large Graphs (500-1000+ nodes)

```
[saga_graph_v2
    limit="1000"
    use_worker="true"
    use_canvas="true"
    layout="clustered"
    collision_radius="25"
    link_distance="60"]
```

- Force canvas rendering
- Use clustered/grid layout
- Reduce spacing to fit more nodes
- Consider static layouts (hierarchical, grid)

## Integration Examples

### In Page Template

```php
<?php
// page-entity-graph.php

get_header();

$entity_id = get_query_var('entity_id', 0);

if ($entity_id > 0) {
    echo do_shortcode('[saga_graph_v2 entity_id="' . absint($entity_id) . '" layout="force" height="800"]');
} else {
    echo do_shortcode('[saga_graph_v2 entity_type="character" limit="200"]');
}

get_footer();
```

### In Widget

```php
// Custom widget
class Saga_Graph_Widget extends WP_Widget {
    public function widget($args, $instance) {
        echo $args['before_widget'];

        $shortcode = sprintf(
            '[saga_graph_v2 entity_id="%d" layout="%s" height="400" show_controls="false"]',
            absint($instance['entity_id']),
            sanitize_key($instance['layout'])
        );

        echo do_shortcode($shortcode);

        echo $args['after_widget'];
    }
}
```

### In Gutenberg Block

```javascript
// Custom Gutenberg block
registerBlockType('saga/graph-v2-block', {
    title: 'Enhanced Relationship Graph',
    category: 'widgets',
    attributes: {
        entityId: { type: 'number', default: 0 },
        layout: { type: 'string', default: 'force' }
    },
    edit: function(props) {
        return (
            <ServerSideRender
                block="saga/relationship-graph-v2"
                attributes={props.attributes}
            />
        );
    },
    save: function() {
        return null; // Server-side render
    }
});
```

### Programmatic Initialization

```javascript
// Direct JavaScript initialization
const graph = new SagaRelationshipGraphV2('my-container', {
    entityId: 42,
    layout: 'force',
    depth: 2,
    useWebWorker: true,
    enableAnalytics: true,
    onLoad: function(data) {
        console.log('Graph loaded:', data);
    }
});
```

## Styling Customization

### Custom Colors

```css
/* In your theme CSS */
:root {
    /* Override entity colors */
    --saga-character-color: #FF6B6B;
    --saga-location-color: #4ECDC4;
    --saga-event-color: #FFE66D;
}

/* Custom node styling */
.saga-graph-node-circle-v2 {
    stroke-width: 3px !important;
}

/* Custom edge styling */
.saga-graph-edge-v2 {
    stroke-opacity: 0.8 !important;
}
```

### Dark Theme

```css
/* Force dark theme */
.saga-graph-v2-container {
    background: linear-gradient(135deg, #1a1a1a 0%, #2d2d2d 100%);
}

.saga-graph-v2-svg {
    background: #2d2d2d;
}

.saga-graph-label-v2 {
    fill: #e0e0e0;
}
```

## JavaScript Events & Hooks

### Listen to Graph Events

```javascript
// Get graph instance
const graph = window.sagaGraphInstances['saga-graph-v2-1234'];

// Override tick handler
const originalTick = graph.tick.bind(graph);
graph.tick = function() {
    originalTick();
    // Your custom logic
    console.log('Graph updated');
};

// Override node click
const originalClick = graph.handleNodeClick.bind(graph);
graph.handleNodeClick = function(event, node) {
    console.log('Node clicked:', node);
    originalClick(event, node);
};
```

### Custom Analytics

```javascript
// Add custom analytics
graph.calculateDegreeDistribution = function() {
    const degrees = new Map();
    this.data.nodes.forEach(n => {
        const degree = this.data.edges.filter(e =>
            e.source.id === n.id || e.target.id === n.id
        ).length;
        degrees.set(n.id, degree);
    });
    return degrees;
};

// Use it
const degrees = graph.calculateDegreeDistribution();
console.log('Degree distribution:', degrees);
```

## Troubleshooting Checklist

### Graph Not Appearing

- [ ] Shortcode enabled in `functions.php`?
- [ ] Container has height set?
- [ ] Check browser console for errors
- [ ] D3.js loaded? (check Network tab)
- [ ] Data endpoint returning results?

### Performance Issues

- [ ] Enable Web Worker (`use_worker="true"`)
- [ ] Enable Canvas (`use_canvas="true"`)
- [ ] Reduce limit (`limit="500"`)
- [ ] Use static layout (grid, circular)
- [ ] Disable particles (`enable_particles="false"`)

### Layout Problems

- [ ] Check root node exists (hierarchical/radial)
- [ ] Clear localStorage cache
- [ ] Try different layout
- [ ] Increase container size
- [ ] Reduce node count

### Browser Issues

- [ ] Update browser to latest version
- [ ] Check Web Worker support
- [ ] Disable browser extensions
- [ ] Clear browser cache
- [ ] Try different browser

## Testing Procedure

### 1. Unit Test (Manual)

```javascript
// In browser console
const graph = window.sagaGraphInstances['saga-graph-v2-1234'];

// Test layout switch
graph.switchLayout('circular');
setTimeout(() => graph.switchLayout('force'), 2000);

// Test filters
graph.filterByEntityType('character');
graph.clearFilters();

// Test analytics
graph.calculateAnalytics();
console.log(graph.analytics);
```

### 2. Performance Test

```javascript
// Measure render time
console.time('graph-render');
const graph = new SagaRelationshipGraphV2('test-container', {
    limit: 1000,
    useWebWorker: true
});
graph.on('renderComplete', () => {
    console.timeEnd('graph-render');
});
```

### 3. Accessibility Test

- [ ] Tab navigation works
- [ ] Screen reader announces changes
- [ ] Keyboard shortcuts functional
- [ ] Focus indicators visible
- [ ] High contrast mode works

## Migration from v1 Checklist

- [ ] Test v2 on development site
- [ ] Compare performance with v1
- [ ] Verify all features work
- [ ] Test with production data size
- [ ] Update documentation/training
- [ ] Replace v1 shortcodes with v2
- [ ] Monitor for issues post-launch

## Support Resources

- **Full Documentation**: `GRAPH-V2-README.md`
- **Architecture**: `CLAUDE.md` (project standards)
- **Original Graph**: `assets/js/relationship-graph.js`
- **Theme Functions**: `functions.php`

## Quick Reference

### Shortcode Syntax

```
[saga_graph_v2 PARAM="VALUE" ...]
```

### Essential Parameters

| Parameter | Values | Default |
|-----------|--------|---------|
| `layout` | force, hierarchical, circular, radial, grid, clustered | force |
| `entity_id` | int | 0 |
| `limit` | int (1-1000) | 100 |
| `use_worker` | true/false | true |
| `use_canvas` | true/false/auto | auto |

### Keyboard Shortcuts

- `F` = Force layout
- `H` = Hierarchical
- `C` = Circular
- `R` = Radial
- `G` = Grid
- `K` = Clustered
- `S` = Save layout
- `Esc` = Clear selection

---

**Ready to use!** Start with basic examples and gradually add features as needed.
