# AI Consistency Guardian - Admin Dashboard

Production-ready admin interface for managing saga consistency issues detected by AI and rule-based analysis.

## Overview

The AI Consistency Guardian admin dashboard provides a comprehensive interface for:
- Viewing and managing consistency issues
- Running manual consistency scans
- Filtering and sorting issues by severity, type, and status
- Resolving, dismissing, or marking issues as false positives
- Viewing statistics and charts
- Exporting issues to CSV

## Files Created

### Backend (Already Exists)
- `inc/ai/ConsistencyAnalyzer.php` - Main analyzer orchestrator
- `inc/ai/ConsistencyRepository.php` - Database operations
- `inc/ai/ConsistencyRuleEngine.php` - Rule-based checks
- `inc/ai/AIClient.php` - AI API integration
- `inc/ai/entities/ConsistencyIssue.php` - Issue value object

### Admin Dashboard (New)
1. **AJAX Handlers** - `inc/ajax/consistency-ajax.php`
   - All AJAX endpoints for dashboard operations
   - Proper nonce verification and capability checks
   - Error handling and logging

2. **Dashboard Widget** - `inc/admin/consistency-dashboard-widget.php`
   - WordPress dashboard widget showing recent issues
   - Quick stats by severity
   - Quick scan button

3. **Admin Page** - `page-templates/admin-consistency-page.php`
   - Full admin interface with tables, filters, charts
   - Issue management with bulk actions
   - Pagination support

4. **JavaScript** - `assets/js/consistency-dashboard.js`
   - All frontend interactions
   - AJAX handling with debouncing
   - Modal management
   - Chart initialization

5. **CSS** - `assets/css/consistency-dashboard.css`
   - Complete styling for dashboard
   - Responsive design
   - Print styles
   - Severity color coding

6. **Admin Init** - `inc/admin/consistency-admin-init.php`
   - Menu registration
   - Settings page
   - Asset enqueueing
   - Admin notices

7. **Loader** - `inc/consistency-guardian-loader.php`
   - Main initialization file
   - Database table creation
   - REST API endpoints
   - Admin bar integration

## Installation

### 1. Add to functions.php

Add this line to your theme's `functions.php`:

```php
require_once get_template_directory() . '/inc/consistency-guardian-loader.php';
```

### 2. Database Tables

Tables are created automatically on first load. The system creates:

```sql
wp_saga_consistency_issues (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    saga_id INT UNSIGNED NOT NULL,
    issue_type VARCHAR(50) NOT NULL,
    severity ENUM('critical', 'high', 'medium', 'low', 'info'),
    entity_id BIGINT UNSIGNED NULL,
    related_entity_id BIGINT UNSIGNED NULL,
    description TEXT NOT NULL,
    context JSON NULL,
    suggested_fix TEXT NULL,
    status ENUM('open', 'resolved', 'dismissed', 'false_positive'),
    detected_at DATETIME NOT NULL,
    resolved_at DATETIME NULL,
    resolved_by BIGINT UNSIGNED NULL,
    ai_confidence DECIMAL(3,2) NULL,
    -- Indexes for performance
)
```

### 3. Configure Settings

1. Navigate to **AI Guardian → Settings** in WordPress admin
2. Enable AI Analysis (optional)
3. Add OpenAI API key (if using AI)
4. Select AI model (GPT-4 recommended)
5. Configure automatic scan schedule

## Usage

### Accessing the Dashboard

**Main Interface:**
- Navigate to **AI Guardian** in the WordPress admin menu
- Or click the shield icon in the admin toolbar

**Dashboard Widget:**
- View on the WordPress dashboard
- Shows recent issues and quick stats

### Running a Scan

**Manual Scan:**
1. Go to AI Guardian main page
2. Select saga from dropdown
3. Check/uncheck "Use AI-powered analysis"
4. Click "Run Full Scan"
5. Wait for completion (progress shown)

**Automatic Scans:**
- Configure in Settings → Scan Schedule
- Options: Daily, Weekly, Monthly, Manual only

### Managing Issues

**View Details:**
- Click on any issue description to open detail modal
- Shows full context, suggested fix, confidence score
- Action buttons: Resolve, Dismiss, Mark False Positive

**Bulk Actions:**
1. Select multiple issues using checkboxes
2. Choose action from "Bulk Actions" dropdown
3. Click "Apply"
4. Confirm action

**Filters:**
- Status: Open, Resolved, Dismissed, False Positive
- Severity: Critical, High, Medium, Low, Info
- Type: Timeline, Character, Location, Relationship, Logical

### Export to CSV

1. Apply desired filters
2. Click "Export CSV" button
3. File downloads automatically with timestamp

## AJAX Endpoints

All endpoints require nonce verification and proper capabilities.

### `saga_run_consistency_scan`
- **Method:** POST
- **Params:** `saga_id`, `use_ai` (bool)
- **Capability:** `manage_options`
- **Returns:** Issue count, statistics

### `saga_get_scan_progress`
- **Method:** GET
- **Params:** `saga_id`
- **Returns:** Progress percentage, current step

### `saga_load_issues`
- **Method:** GET
- **Params:** `saga_id`, `status`, `severity`, `issue_type`, `page`
- **Returns:** Issues array, pagination data

### `saga_get_issue_details`
- **Method:** GET
- **Params:** `issue_id`
- **Returns:** Full issue details with entity names

### `saga_resolve_issue`
- **Method:** POST
- **Params:** `issue_id`
- **Capability:** `edit_posts`
- **Returns:** Success/error message

### `saga_dismiss_issue`
- **Method:** POST
- **Params:** `issue_id`, `false_positive` (bool)
- **Capability:** `edit_posts`
- **Returns:** Success/error message

### `saga_bulk_action`
- **Method:** POST
- **Params:** `action_type`, `issue_ids[]`
- **Capability:** `edit_posts`
- **Returns:** Success/fail counts

### `saga_export_issues`
- **Method:** GET
- **Params:** `saga_id`, `status`
- **Capability:** `manage_options`
- **Returns:** CSV file download

## REST API Endpoints

### Scan Saga
```
POST /wp-json/saga/v1/consistency/scan
Body: { "saga_id": 1 }
```

### Get Issues
```
GET /wp-json/saga/v1/consistency/issues?saga_id=1&status=open
```

### Get Single Issue
```
GET /wp-json/saga/v1/consistency/issues/123
```

## UI Components

### Severity Badges
- **Critical:** Red (#dc2626) - Immediate attention required
- **High:** Orange (#ea580c) - Important issues
- **Medium:** Yellow (#ca8a04) - Moderate priority
- **Low:** Blue (#2563eb) - Minor issues
- **Info:** Gray (#6b7280) - Informational

### Charts
Uses Chart.js 4.4.0 (loaded via CDN):
- **Severity Chart:** Pie chart showing distribution
- **Type Chart:** Bar chart of issues by type

### Modal
- Click issue to open details modal
- Backdrop closes modal
- Action buttons: Resolve, Dismiss, False Positive
- Keyboard accessible

## Performance

### Caching Strategy
- Statistics cached for 5 minutes (wp_cache)
- Scan results cached in transients
- Query results use WordPress object cache

### Database Optimization
- Indexed columns: saga_id, status, severity, issue_type
- Pagination limits queries to 25 items
- Debounced search with 500ms delay

### Best Practices
- Use filters to reduce result sets
- Export large datasets instead of viewing in browser
- Schedule scans during off-peak hours
- Monitor AI API usage and costs

## Security

### Nonce Verification
All AJAX requests verified with `wp_create_nonce('saga_consistency_nonce')`

### Capability Checks
- `manage_options` - Settings, export, admin page
- `edit_posts` - Resolve, dismiss issues
- `read` - View issues (REST API)

### Input Sanitization
- `absint()` for IDs
- `sanitize_key()` for enum values
- `sanitize_text_field()` for user input
- `esc_html()`, `esc_attr()` for output

### SQL Injection Prevention
All queries use `$wpdb->prepare()` with proper placeholders

## Customization

### Colors
Edit `assets/css/consistency-dashboard.css`:
- Severity colors: Lines 145-175
- Status badge colors: Lines 199-218

### Issue Types
Add new types in:
1. Database ENUM in `consistency-guardian-loader.php`
2. Filter dropdown in `admin-consistency-page.php`
3. Icon mapping in `consistency-dashboard.js` (getTypeIcon)

### Filters
Extend filters in JavaScript:
```javascript
this.filters = {
    status: '',
    severity: '',
    issueType: '',
    // Add custom filters here
};
```

## Troubleshooting

### Issues Not Loading
1. Check browser console for JavaScript errors
2. Verify nonce in network tab (should match server)
3. Check WordPress debug log for PHP errors
4. Clear object cache: `wp cache flush`

### Scan Fails
1. Verify database tables exist
2. Check AI API key in settings (if using AI)
3. Review error log: `/wp-content/debug.log`
4. Test with AI disabled first

### Slow Performance
1. Reduce AI usage (disable or use faster model)
2. Add database indexes if custom queries added
3. Implement Redis object cache
4. Limit scan frequency

### Export Fails
1. Check PHP memory limit (increase to 256M)
2. Reduce export size with filters
3. Check file permissions on wp-content
4. Review server error logs

## Development

### Adding New Issue Types

1. **Update Database Schema:**
```php
// In consistency-guardian-loader.php
ALTER TABLE wp_saga_consistency_issues
MODIFY issue_type ENUM('timeline', 'character', 'location', 'relationship', 'logical', 'new_type');
```

2. **Add Rule in Rule Engine:**
```php
// In ConsistencyRuleEngine.php
private function checkNewType(int $sagaId): array {
    // Implementation
}
```

3. **Update UI:**
```javascript
// In consistency-dashboard.js
getTypeIcon: function(type) {
    const icons = {
        // ...existing types
        'new_type': 'dashicons-admin-generic',
    };
}
```

### Extending AJAX Handlers

```php
// In consistency-ajax.php
add_action('wp_ajax_saga_custom_action', [$this, 'handleCustomAction']);

public function handleCustomAction(): void {
    check_ajax_referer('saga_consistency_nonce', 'nonce');
    // Implementation
}
```

### Custom Chart Types

```javascript
// In consistency-dashboard.js - initCharts()
new Chart(ctx, {
    type: 'line', // or 'doughnut', 'radar', etc.
    data: { /* ... */ },
    options: { /* ... */ }
});
```

## Credits

- **Backend Architecture:** Hexagonal architecture with DDD principles
- **Charts:** Chart.js 4.4.0
- **Icons:** WordPress Dashicons
- **Framework:** WordPress 6.0+, PHP 8.2+

## Support

For issues or questions:
1. Check WordPress debug log
2. Review browser console
3. Verify all files are loaded correctly
4. Check database table structure
5. Test with WordPress default theme

## Changelog

### Version 1.4.0 (2025-01-01)
- Initial release of admin dashboard
- Complete AJAX integration
- Chart.js visualization
- Bulk actions support
- CSV export functionality
- REST API endpoints
- Dashboard widget
- Settings page
- Scan history tracking

## License

Part of Saga Manager Theme - All rights reserved
