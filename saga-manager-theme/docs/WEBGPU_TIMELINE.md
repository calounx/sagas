# WebGPU Infinite Zoom Timeline

## Overview

The WebGPU Infinite Zoom Timeline is a next-generation visualization feature for the Saga Manager theme (v1.3.0). It provides a high-performance, GPU-accelerated timeline interface that can handle thousands of events with smooth infinite zoom capabilities, from cosmic timescales (millennia) down to hour-by-hour events.

## Features

### Core Capabilities

- **Infinite Zoom**: Smoothly zoom from years → months → days → hours
- **WebGPU Acceleration**: GPU-powered rendering for 60 FPS performance
- **Canvas 2D Fallback**: Automatic fallback for browsers without WebGPU support
- **10,000+ Events**: Efficient rendering with quadtree spatial indexing
- **Custom Calendars**: Support for saga-specific date systems (BBY, AG, Third Age, etc.)
- **Multi-Track Layout**: Parallel timeline tracks for different storylines

### Interactive Features

- **Pan & Zoom**: Drag to pan, scroll/pinch to zoom with inertia
- **Event Markers**: Beautiful color-coded event markers with icons
- **Hover Previews**: Tooltip previews on event hover
- **Search**: Full-text search across timeline events
- **Bookmarks**: Save and navigate to important moments
- **Minimap**: Bird's-eye view with viewport indicator
- **Keyboard Navigation**: Full keyboard shortcut support
- **Export**: Export timeline as PNG image

### Visual Design

- **Dark/Light Mode**: Automatic theme detection with manual override
- **Gradient Backgrounds**: Beautiful gradients and animations
- **Entity Type Colors**: Distinct colors for characters, locations, events, etc.
- **Relationship Connections**: Visual connections between related events
- **Era Bands**: Background bands for different ages/eras
- **Responsive Design**: Mobile-optimized with touch gestures

## Installation

The timeline feature is automatically loaded with the Saga Manager theme v1.3.0+.

### Requirements

- WordPress 6.0+
- PHP 8.2+
- Saga Manager Theme v1.3.0+
- Modern browser (Chrome 113+, Edge 113+, Firefox with WebGPU flag)

### File Structure

```
saga-manager-theme/
├── assets/
│   ├── js/
│   │   ├── webgpu-timeline.js           # Main WebGPU engine
│   │   └── timeline-controls.js         # UI controls
│   └── css/
│       └── webgpu-timeline.css          # Timeline styles
├── inc/
│   ├── shortcodes/
│   │   └── timeline-shortcode.php       # Shortcode handler
│   ├── ajax/
│   │   └── timeline-data-handler.php    # AJAX endpoints
│   └── helpers/
│       └── calendar-converter.php       # Date conversion
└── template-parts/
    └── timeline-controls.php            # Accessibility controls
```

## Usage

### Basic Shortcode

```php
[saga_timeline saga_id="1"]
```

### Full Options

```php
[saga_timeline
    saga_id="1"
    width="100%"
    height="600px"
    theme="dark"
    show_controls="true"
    show_minimap="true"
    initial_zoom="1"
    min_zoom="0.0001"
    max_zoom="1000"
]
```

### Shortcode Parameters

| Parameter | Type | Default | Description |
|-----------|------|---------|-------------|
| `saga_id` | int | required | Saga database ID |
| `width` | string | `100%` | Timeline width (CSS units) |
| `height` | string | `600px` | Timeline height (CSS units) |
| `theme` | string | `dark` | Theme: `dark` or `light` |
| `show_controls` | bool | `true` | Show control panel |
| `show_minimap` | bool | `true` | Show minimap overview |
| `initial_zoom` | float | `1` | Initial zoom level |
| `min_zoom` | float | `0.0001` | Minimum zoom (years to millennia) |
| `max_zoom` | float | `1000` | Maximum zoom (down to hours) |

### Programmatic Usage

```javascript
// Get timeline instance
const container = document.getElementById('saga-timeline-123');
const timeline = container.timelineInstance;

// Navigate to specific timestamp
timeline.goToTimestamp(1234567890, true); // animated

// Zoom in/out
timeline.zoomIn();
timeline.zoomOut();

// Fit all events in view
timeline.fitToEvents();

// Destroy timeline
timeline.destroy();
```

## Custom Calendar Systems

### Configuration

Calendar systems are defined in the `saga_sagas` table's `calendar_config` JSON field.

### Supported Calendar Types

#### 1. Absolute (Gregorian)

Standard Gregorian calendar dates.

```json
{
  "calendar_type": "absolute"
}
```

Usage: `"2024-03-15 14:30:00"`

#### 2. Epoch Relative

Dates relative to a specific epoch (e.g., Star Wars BBY/ABY).

```json
{
  "calendar_type": "epoch_relative",
  "calendar_config": {
    "epoch": "BBY",
    "epoch_timestamp": 0,
    "age_offsets": {
      "BBY": 0,
      "ABY": 0
    },
    "format": "{sign}{year} {epoch}"
  }
}
```

Supported formats:
- `"32 BBY"` (Before Battle of Yavin)
- `"4 ABY"` (After Battle of Yavin)
- `"10,191 AG"` (Dune: After Guild)
- `"TA 3019"` (LOTR: Third Age)
- `"2019-03-25 TA"` (Full date with age)

#### 3. Age Based

Dates based on named ages/eras (e.g., LOTR).

```json
{
  "calendar_type": "age_based",
  "calendar_config": {
    "ages": [
      {
        "name": "First Age",
        "start_timestamp": -946684800000,
        "end_timestamp": -631152000000
      },
      {
        "name": "Second Age",
        "start_timestamp": -631152000000,
        "end_timestamp": -315619200000
      },
      {
        "name": "Third Age",
        "start_timestamp": -315619200000,
        "end_timestamp": 0
      }
    ]
  }
}
```

Usage: `"Third Age, Year 3019"`

### Adding Custom Calendar Systems

1. **Define Calendar in Database**:

```sql
UPDATE wp_saga_sagas
SET calendar_type = 'epoch_relative',
    calendar_config = '{
      "epoch": "AG",
      "epoch_timestamp": 0,
      "format": "{year} {epoch}"
    }'
WHERE id = 1;
```

2. **Insert Events with Custom Dates**:

```sql
INSERT INTO wp_saga_timeline_events (
    saga_id,
    canon_date,
    normalized_timestamp,
    title,
    description
) VALUES (
    1,
    '10,191 AG',
    321892800,  -- Normalized Unix timestamp
    'Paul Atreides arrives on Arrakis',
    'The ducal family arrives on Dune'
);
```

3. **Calendar Converter** handles normalization automatically via `CalendarConverter::toTimestamp()`.

## Performance Optimization

### Quadtree Spatial Indexing

The timeline uses a quadtree data structure for efficient culling of off-screen events:

```javascript
class TimelineQuadtree {
    // Subdivides events spatially
    // Only renders events in visible viewport
    // Handles 10,000+ events at 60 FPS
}
```

### WebGPU Rendering Pipeline

```
CPU (JavaScript)
  ↓
Create vertex buffers with event positions
  ↓
GPU (WebGPU Shader)
  ↓
Transform vertices based on zoom/pan
  ↓
Rasterize and render at 60 FPS
```

### Canvas 2D Fallback

If WebGPU is unavailable:
- Automatic detection and fallback
- Virtual scrolling/culling
- Optimized drawing with requestAnimationFrame
- Maintains 60 FPS with <5000 events

## Keyboard Shortcuts

| Shortcut | Action |
|----------|--------|
| `←` `→` | Pan left/right |
| `+` `-` | Zoom in/out |
| `Home` | Return to start |
| `Ctrl+F` | Search timeline |
| `Ctrl+B` | Add bookmark |
| `Space` | Pause/resume auto-play |
| `Esc` | Close panels |

## Accessibility

### ARIA Support

- Proper ARIA labels on all controls
- Live regions for screen reader announcements
- Keyboard navigation support
- Focus management for panels

### WCAG 2.1 AA Compliance

- Color contrast ratios > 4.5:1
- Focus indicators on all interactive elements
- Screen reader friendly announcements
- Reduced motion support

```css
@media (prefers-reduced-motion: reduce) {
    /* Disable animations */
}
```

## Browser Support

| Browser | WebGPU | Canvas Fallback |
|---------|--------|-----------------|
| Chrome 113+ | ✅ Yes | ✅ Yes |
| Edge 113+ | ✅ Yes | ✅ Yes |
| Firefox 121+ | 🚧 Flag required | ✅ Yes |
| Safari | ❌ Not yet | ✅ Yes |
| Mobile Chrome | ✅ Yes (Android) | ✅ Yes |
| Mobile Safari | ❌ Not yet | ✅ Yes |

### WebGPU Detection

```javascript
if (!navigator.gpu) {
    console.warn('WebGPU not available, using Canvas 2D');
    // Automatic fallback
}
```

## API Reference

### JavaScript API

#### WebGPUTimeline Class

```javascript
const timeline = new WebGPUTimeline(container, options);
```

**Options:**
- `sagaId` (int): Saga database ID
- `width` (int): Canvas width in pixels
- `height` (int): Canvas height in pixels
- `minZoom` (float): Minimum zoom level
- `maxZoom` (float): Maximum zoom level
- `initialZoom` (float): Starting zoom level
- `initialCenter` (float): Starting center timestamp
- `backgroundColor` (string): Background color hex
- `gridColor` (string): Grid line color hex
- `eventColor` (string): Default event color hex
- `accentColor` (string): Accent color hex

**Methods:**
- `zoomIn()`: Zoom in by 1.5x
- `zoomOut()`: Zoom out by 0.67x
- `goToTimestamp(timestamp, animate)`: Navigate to timestamp
- `fitToEvents()`: Fit all events in view
- `destroy()`: Clean up resources

**Events:**
- `saga-timeline-hover`: Fired when hovering over event
  ```javascript
  container.addEventListener('saga-timeline-hover', (e) => {
      console.log(e.detail.event); // Event data
  });
  ```

#### TimelineControls Class

```javascript
const controls = new TimelineControls(timeline, container);
```

**Methods:**
- `showSearchPanel()`: Open search panel
- `hideSearchPanel()`: Close search panel
- `showBookmarkPanel()`: Open bookmark panel
- `hideBookmarkPanel()`: Close bookmark panel
- `addBookmark()`: Add bookmark at current position
- `exportImage()`: Export timeline as PNG

### PHP API

#### Shortcode Handler

```php
TimelineShortcode::register();
```

#### AJAX Endpoints

```php
// Get timeline events
GET /wp-admin/admin-ajax.php?action=get_timeline_events&saga_id=1&nonce=xxx

// Get event details
GET /wp-admin/admin-ajax.php?action=get_timeline_event_details&event_id=123&nonce=xxx
```

#### Calendar Converter

```php
use SagaManager\Helpers\CalendarConverter;

// Convert saga date to Unix timestamp
$timestamp = CalendarConverter::toTimestamp(
    '32 BBY',
    'epoch_relative',
    $calendar_config
);

// Convert timestamp to saga date
$canonDate = CalendarConverter::toCanonDate(
    $timestamp,
    'epoch_relative',
    $calendar_config
);
```

## Troubleshooting

### Timeline Not Loading

1. **Check browser console** for errors
2. **Verify saga exists** in database
3. **Check AJAX endpoint** returns data:
   ```
   /wp-admin/admin-ajax.php?action=get_timeline_events&saga_id=1&nonce=xxx
   ```
4. **Verify nonce** is valid

### WebGPU Not Working

1. **Check browser support**: Chrome/Edge 113+
2. **Enable WebGPU flag** in Firefox: `about:config` → `dom.webgpu.enabled`
3. **Canvas fallback** will activate automatically

### Performance Issues

1. **Reduce event count** per saga (<10,000 recommended)
2. **Use event importance** to filter low-priority events
3. **Enable browser hardware acceleration**
4. **Close other GPU-intensive tabs**

### Calendar Dates Not Converting

1. **Check calendar_type** matches format
2. **Verify calendar_config** JSON is valid
3. **Check epoch_timestamp** is set correctly
4. **Use CalendarConverter::validateConfig()** to test

## Examples

### Star Wars Timeline

```php
// Create saga with BBY/ABY calendar
INSERT INTO wp_saga_sagas (name, universe, calendar_type, calendar_config)
VALUES (
    'Star Wars',
    'Star Wars',
    'epoch_relative',
    '{
        "epoch": "BBY",
        "epoch_timestamp": 0,
        "format": "{sign}{year} {epoch}"
    }'
);

// Add Battle of Yavin (epoch event)
INSERT INTO wp_saga_timeline_events (saga_id, canon_date, normalized_timestamp, title)
VALUES (1, '0 BBY', 0, 'Battle of Yavin');

// Add Phantom Menace
INSERT INTO wp_saga_timeline_events (saga_id, canon_date, normalized_timestamp, title)
VALUES (1, '32 BBY', -1009843200, 'Invasion of Naboo');

// Display timeline
[saga_timeline saga_id="1" height="800px"]
```

### LOTR Timeline

```php
// Create saga with age-based calendar
INSERT INTO wp_saga_sagas (name, universe, calendar_type, calendar_config)
VALUES (
    'Lord of the Rings',
    'Middle-earth',
    'age_based',
    '{
        "ages": [
            {
                "name": "Third Age",
                "start_timestamp": -94608000000,
                "end_timestamp": 0
            }
        ]
    }'
);

// Add event
INSERT INTO wp_saga_timeline_events (saga_id, canon_date, normalized_timestamp, title)
VALUES (1, 'Third Age, Year 3019', -62208000, 'Destruction of the One Ring');

[saga_timeline saga_id="1" theme="light"]
```

### Dune Timeline

```php
// Create saga with AG calendar
INSERT INTO wp_saga_sagas (name, universe, calendar_type, calendar_config)
VALUES (
    'Dune',
    'Dune',
    'epoch_relative',
    '{
        "epoch": "AG",
        "epoch_timestamp": 0
    }'
);

// Add event
INSERT INTO wp_saga_timeline_events (saga_id, canon_date, normalized_timestamp, title)
VALUES (1, '10,191 AG', 321892800000, 'Paul Atreides arrives on Arrakis');

[saga_timeline saga_id="1" initial_zoom="0.1"]
```

## Future Enhancements

### Phase 2 (v1.4.0)
- [ ] Multi-saga timeline comparison
- [ ] Advanced filtering (by entity type, importance, etc.)
- [ ] Timeline annotations
- [ ] Event clustering at high zoom levels
- [ ] Auto-play mode with narration

### Phase 3 (v1.5.0)
- [ ] Collaborative timeline editing
- [ ] Version history/undo
- [ ] PDF export
- [ ] Timeline templates
- [ ] AI-powered event suggestions

## Contributing

See main [CONTRIBUTING.md](../CONTRIBUTING.md) for guidelines.

### Testing Checklist

- [ ] WebGPU rendering works in Chrome/Edge
- [ ] Canvas fallback works in Firefox/Safari
- [ ] Mobile touch gestures work correctly
- [ ] Keyboard shortcuts function properly
- [ ] Screen reader announces events
- [ ] Custom calendars convert correctly
- [ ] 10,000+ events render at 60 FPS
- [ ] Dark/light themes work
- [ ] Export generates valid PNG
- [ ] Bookmarks persist across sessions

## License

Part of Saga Manager Theme - Same license as parent theme.

## Credits

- **WebGPU API**: W3C WebGPU Working Group
- **Inspiration**: Histography, Timeline JS, Vis.js Timeline
- **Architecture**: Based on Saga Manager hexagonal architecture patterns
