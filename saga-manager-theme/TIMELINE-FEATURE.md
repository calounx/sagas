# Interactive Timeline Visualization

Production-ready timeline visualization for saga events using vis-timeline library.

## Features

### Timeline Types
- **Linear**: All events on a single track
- **Grouped**: Events grouped by entity type
- **Stacked**: Multiple parallel timelines

### Interactive Controls
- Zoom in/out with mouse wheel or buttons
- Pan left/right with drag or arrow keys
- Fit to window to see all events
- Timeline type switcher
- Export to JSON, CSV, or PNG image

### Advanced Filtering
- Filter by entity type (characters, locations, events, etc.)
- Minimum importance threshold (0-100 scale)
- Date range filter
- Reset filters button

### Visual Design
- Color-coded events by type (battle, birth, death, etc.)
- Event importance shown by marker size
- Event icons (emoji) for quick recognition
- Smooth zoom/pan animations
- Responsive mobile view (vertical timeline)
- Dark mode support

### Custom Calendar Support
- Standard dates
- BBY/ABY (Star Wars style)
- Age-based (LOTR style: First Age, Second Age)
- Custom epoch dates

### Performance
- Virtual rendering for 1000+ events
- AJAX lazy loading
- Redis caching (5 min TTL)
- Debounced zoom/pan (60fps)
- Rate limiting (10-30 req/min)

### Accessibility
- ARIA labels on all controls
- Keyboard navigation (arrows, +/-, Home)
- Screen reader announcements
- Alternative table view
- Focus indicators

## Usage

### Shortcode

```php
// Basic usage
[saga_timeline saga_id="1"]

// With options
[saga_timeline saga_id="1" type="grouped" height="800"]

// Filter by entity
[saga_timeline saga_id="1" entity_id="123"]

// Date range
[saga_timeline saga_id="1" date_from="100 BBY" date_to="100 ABY"]

// Without controls/filters
[saga_timeline saga_id="1" show_controls="false" show_filters="false"]
```

### Template Part

```php
<?php
get_template_part('template-parts/timeline-viewer', null, [
    'saga_id' => 1,
    'type' => 'linear',
    'height' => 600,
    'show_controls' => true,
    'show_filters' => true
]);
?>
```

### JavaScript API

```javascript
// Initialize timeline programmatically
const timeline = new SagaTimeline(document.querySelector('.timeline-wrapper'), {
    sagaId: 1,
    type: 'linear',
    height: 600,
    calendarType: 'bby'
});

// Access vis-timeline instance
timeline.timeline.fit();
timeline.timeline.zoomIn(0.5);

// Destroy timeline
timeline.destroy();
```

## File Structure

```
saga-manager-theme/
├── assets/
│   ├── css/
│   │   └── timeline-visualization.css     # Timeline styles
│   └── js/
│       └── timeline-visualization.js      # Timeline JavaScript
├── inc/
│   └── ajax-timeline-data.php             # AJAX endpoints
├── shortcode/
│   └── timeline-shortcode.php             # Shortcode handler
└── template-parts/
    └── timeline-viewer.php                 # Timeline template
```

## AJAX Endpoints

### Get Timeline Data

**Endpoint:** `wp-ajax-saga_get_timeline_data`

**Parameters:**
- `saga_id` (required): Saga ID
- `entity_id` (optional): Filter by entity
- `date_range` (optional): `{start: 'date', end: 'date'}`
- `nonce` (required): Security nonce

**Response:**
```json
{
    "success": true,
    "data": {
        "events": [...],
        "calendar_type": "bby",
        "calendar_config": {...},
        "saga_name": "Star Wars",
        "total_events": 150,
        "date_range": {"min": 1234567890, "max": 1234567890}
    }
}
```

### Get Timeline Stats

**Endpoint:** `wp-ajax-saga_get_timeline_stats`

**Parameters:**
- `saga_id` (required): Saga ID
- `nonce` (required): Security nonce

**Response:**
```json
{
    "success": true,
    "data": {
        "total_events": 150,
        "earliest_event": 1234567890,
        "latest_event": 1234567890,
        "avg_description_length": 245.5,
        "type_distribution": [...],
        "time_span_years": 1000
    }
}
```

## Event Data Structure

```json
{
    "id": 123,
    "title": "Battle of Yavin",
    "canon_date": "0 BBY",
    "normalized_timestamp": 1234567890,
    "description": "Rebel Alliance destroys Death Star",
    "type": "battle",
    "entity_type": "event",
    "importance": 95,
    "participants": ["Luke Skywalker", "Darth Vader"],
    "location": "Yavin 4",
    "metadata": {...}
}
```

## Event Types & Colors

| Type | Color | Icon |
|------|-------|------|
| Battle | Red (#dc2626) | ⚔️ |
| Birth | Green (#16a34a) | 🎂 |
| Death | Dark Gray (#1f2937) | 💀 |
| Founding | Purple (#9333ea) | 🏛️ |
| Discovery | Cyan (#0891b2) | 🔍 |
| Treaty | Emerald (#059669) | 📜 |
| Coronation | Amber (#d97706) | 👑 |
| Destruction | Rose (#be123c) | 💥 |
| Meeting | Indigo (#4f46e5) | 🤝 |
| Journey | Violet (#7c3aed) | 🗺️ |

## Custom Calendar Examples

### BBY/ABY (Star Wars)

```php
$calendar_config = [
    'epoch_year' => 1977,
    'format' => 'bby'
];
```

Timeline labels: "100 BBY", "0 BY", "25 ABY"

### Age-Based (Lord of the Rings)

```php
$calendar_config = [
    'ages' => [
        ['name' => 'First Age', 'start' => 0, 'end' => 31536000000],
        ['name' => 'Second Age', 'start' => 31536000001, 'end' => 63072000000],
        ['name' => 'Third Age', 'start' => 63072000001, 'end' => 94608000000]
    ]
];
```

Timeline labels: "First Age 500", "Second Age 1200"

## Performance Optimization

### Caching Strategy

```php
// Cache key format
saga_timeline_{saga_id}_{entity_id}_{date_range_hash}

// TTL: 5 minutes
// Invalidation: On event save/update
```

### Query Optimization

```sql
-- Optimized query with indexed columns
SELECT te.*, e.entity_type, e.importance_score
FROM wp_saga_timeline_events te
LEFT JOIN wp_saga_entities e ON te.event_entity_id = e.id
WHERE te.saga_id = 1
AND te.normalized_timestamp BETWEEN 1234567890 AND 1234567890
ORDER BY te.normalized_timestamp ASC
LIMIT 10000
```

### Rate Limiting

- Logged-in users: 30 requests/minute
- Guest users: 10 requests/minute
- HTTP 429 response on limit exceeded

## Security

### Input Sanitization

```php
$saga_id = absint($_POST['saga_id']);
$date_range = array_map('sanitize_text_field', $_POST['date_range']);
```

### Nonce Verification

```php
wp_verify_nonce($_POST['nonce'], 'saga_timeline_nonce');
```

### Capability Checks

All endpoints accessible to public (read permission).
No write operations exposed.

## Keyboard Shortcuts

| Key | Action |
|-----|--------|
| ← → | Pan left/right |
| + = | Zoom in |
| - | Zoom out |
| Home | Fit to window |
| Esc | Close modal |

## Browser Support

- Chrome/Edge 90+
- Firefox 88+
- Safari 14+
- Mobile: iOS 14+, Android 10+

## Dependencies

- vis-timeline 7.7.3 (CDN)
- html2canvas 1.4.1 (CDN, optional for image export)
- jQuery (WordPress core)

## Customization

### Custom Event Type

```javascript
// Add custom event type in timeline-visualization.js
this.eventTypes = {
    // ... existing types
    custom_type: { color: '#ff6600', icon: '🔥' }
};
```

### Custom Date Format

```javascript
// Override formatTimelineDate method
formatTimelineDate(date, type) {
    // Custom logic here
    return 'Custom Date Format';
}
```

### Custom Styling

```css
/* Override in your theme's style.css */
.saga-timeline-wrapper {
    --timeline-primary-color: #your-color;
}

.timeline-event-marker.custom-class {
    /* Custom styles */
}
```

## Troubleshooting

### Timeline Not Loading

1. Check console for JavaScript errors
2. Verify saga_id exists in database
3. Check AJAX endpoint response (Network tab)
4. Verify nonce is valid

### Slow Performance

1. Check event count (`>1000` may be slow)
2. Enable caching (Redis recommended)
3. Optimize database indexes
4. Reduce date range filter

### Events Not Displaying

1. Check `normalized_timestamp` field populated
2. Verify event importance > 0
3. Check filter settings
4. Verify entity_type mapping

## Future Enhancements

- [ ] Timeline templates (battles, character arcs, etc.)
- [ ] Event clustering by proximity
- [ ] Multi-saga comparison timeline
- [ ] Timeline animation/playback
- [ ] Integration with relationship graph
- [ ] PDF export
- [ ] Timeline sharing (permalink with filters)
- [ ] Real-time updates (WebSocket)

## Credits

- vis-timeline library: https://visjs.org/
- Icons: Unicode emoji
- Design inspiration: Modern timeline libraries

## License

GPL v2 or later (same as WordPress)

## Support

For issues or questions:
1. Check documentation above
2. Enable WP_DEBUG for error logs
3. Check browser console
4. Contact theme support
