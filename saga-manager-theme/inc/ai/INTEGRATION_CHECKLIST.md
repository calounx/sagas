# AI Consistency Guardian - Integration Checklist

Complete checklist for integrating the AI Consistency Guardian into your Saga Manager theme.

## Pre-Integration Checklist

- [ ] PHP 8.2+ installed
- [ ] WordPress 6.0+ installed
- [ ] MariaDB 11.4+ or MySQL 8.0+
- [ ] Saga Manager base theme active
- [ ] Backend AI infrastructure exists (ConsistencyAnalyzer, Repository, etc.)

## Installation Steps

### Step 1: Include Loader in functions.php

Add to `/functions.php`:

```php
/**
 * Load AI Consistency Guardian
 */
require_once get_template_directory() . '/inc/consistency-guardian-loader.php';
```

**Verify:**
- [ ] No PHP errors on site load
- [ ] Database table `wp_saga_consistency_issues` created
- [ ] Admin menu "AI Guardian" appears

### Step 2: Verify File Structure

All files should exist:

```
saga-manager-theme/
├── inc/
│   ├── ai/
│   │   ├── entities/
│   │   │   └── ConsistencyIssue.php ✓
│   │   ├── AIClient.php ✓
│   │   ├── ConsistencyAnalyzer.php ✓
│   │   ├── ConsistencyRepository.php ✓
│   │   ├── ConsistencyRuleEngine.php ✓
│   │   └── database-migrator.php ✓
│   ├── admin/
│   │   ├── consistency-admin-init.php ✓ (NEW)
│   │   └── consistency-dashboard-widget.php ✓ (NEW)
│   ├── ajax/
│   │   └── consistency-ajax.php ✓ (NEW)
│   └── consistency-guardian-loader.php ✓ (NEW)
├── page-templates/
│   └── admin-consistency-page.php ✓ (NEW)
├── assets/
│   ├── js/
│   │   └── consistency-dashboard.js ✓ (NEW)
│   └── css/
│       └── consistency-dashboard.css ✓ (NEW)
```

**Verify:**
- [ ] All 7 new files exist
- [ ] Backend files from previous implementation exist
- [ ] No file permission issues (644 for files)

### Step 3: Database Verification

Check database table creation:

```sql
SHOW TABLES LIKE '%saga_consistency_issues';
DESCRIBE wp_saga_consistency_issues;
```

**Expected columns:**
- [ ] id (BIGINT UNSIGNED, PRIMARY KEY)
- [ ] saga_id (INT UNSIGNED)
- [ ] issue_type (VARCHAR)
- [ ] severity (ENUM)
- [ ] entity_id (BIGINT UNSIGNED, NULL)
- [ ] related_entity_id (BIGINT UNSIGNED, NULL)
- [ ] description (TEXT)
- [ ] context (JSON)
- [ ] suggested_fix (TEXT)
- [ ] status (ENUM)
- [ ] detected_at (DATETIME)
- [ ] resolved_at (DATETIME, NULL)
- [ ] resolved_by (BIGINT UNSIGNED, NULL)
- [ ] ai_confidence (DECIMAL)

**Verify indexes:**
```sql
SHOW INDEXES FROM wp_saga_consistency_issues;
```

Expected indexes:
- [ ] PRIMARY (id)
- [ ] idx_saga_status (saga_id, status)
- [ ] idx_severity (severity)
- [ ] idx_issue_type (issue_type)
- [ ] idx_entity (entity_id)
- [ ] idx_detected (detected_at)

### Step 4: Admin Interface Verification

**Main Admin Page:**
- [ ] Navigate to **AI Guardian** in admin menu
- [ ] Page loads without errors
- [ ] Saga selector dropdown appears
- [ ] Statistics boxes display (may show 0)
- [ ] Charts load (empty if no data)
- [ ] Scan section visible
- [ ] Filters toolbar present
- [ ] Issues table loads (may be empty)

**Dashboard Widget:**
- [ ] Navigate to WordPress Dashboard
- [ ] "AI Consistency Guardian" widget visible
- [ ] Stats show correctly
- [ ] "Run Quick Scan" button works
- [ ] "View All Issues" link navigates to main page

**Settings Page:**
- [ ] Navigate to **AI Guardian → Settings**
- [ ] AI settings section visible
- [ ] API key field present
- [ ] Model selector works
- [ ] Scan schedule options available
- [ ] Save settings works

**Admin Toolbar:**
- [ ] Shield icon appears in admin toolbar (top)
- [ ] Shows issue count badge if issues exist
- [ ] Click navigates to main page

### Step 5: Functionality Testing

**Run a Test Scan:**
1. [ ] Go to AI Guardian main page
2. [ ] Select a saga from dropdown
3. [ ] Uncheck "Use AI" (for faster test)
4. [ ] Click "Run Full Scan"
5. [ ] Progress indicator shows
6. [ ] Scan completes successfully
7. [ ] Issues appear in table (if any found)

**Issue Management:**
1. [ ] Click on an issue description
2. [ ] Modal opens with full details
3. [ ] "Resolve" button works
4. [ ] "Dismiss" button works
5. [ ] "Mark False Positive" button works
6. [ ] Issue status updates in table

**Bulk Actions:**
1. [ ] Select multiple issues with checkboxes
2. [ ] "Select all" checkbox works
3. [ ] Choose bulk action (e.g., "Resolve")
4. [ ] Click "Apply"
5. [ ] Confirmation prompt appears
6. [ ] Action completes successfully
7. [ ] Table refreshes

**Filters:**
1. [ ] Change status filter
2. [ ] Change severity filter
3. [ ] Change type filter
4. [ ] Click "Apply Filters"
5. [ ] Table updates with filtered results
6. [ ] Pagination works correctly

**Export:**
1. [ ] Apply some filters (optional)
2. [ ] Click "Export CSV"
3. [ ] CSV file downloads
4. [ ] File contains correct data
5. [ ] Headers are properly formatted

### Step 6: AJAX Verification

Open browser DevTools Network tab and verify:

**Run Scan:**
- [ ] Request to `admin-ajax.php` with action `saga_run_consistency_scan`
- [ ] Response includes `success: true`
- [ ] Nonce verified (no 403 errors)

**Load Issues:**
- [ ] Request to `admin-ajax.php` with action `saga_load_issues`
- [ ] Response includes issues array
- [ ] Pagination data correct

**Resolve Issue:**
- [ ] Request with action `saga_resolve_issue`
- [ ] Issue ID sent correctly
- [ ] Success response received

**No Console Errors:**
- [ ] No JavaScript errors in console
- [ ] No 404 errors for assets
- [ ] No AJAX errors (check response codes)

### Step 7: Performance Testing

**Page Load Time:**
- [ ] Admin page loads < 2 seconds
- [ ] Dashboard widget loads < 1 second
- [ ] No significant slowdown on dashboard

**Query Performance:**
- [ ] Enable Query Monitor plugin
- [ ] Check queries on admin page
- [ ] Verify < 50ms query time
- [ ] No N+1 query issues

**Caching:**
- [ ] Statistics cached (check with Redis/Memcached)
- [ ] Object cache hits visible
- [ ] Transients set correctly

### Step 8: Security Verification

**Nonce Protection:**
- [ ] All AJAX requests include nonce
- [ ] Invalid nonce returns 403
- [ ] Nonce changes after use

**Capability Checks:**
- [ ] Non-admin users cannot access settings
- [ ] Editor users can view/resolve issues
- [ ] Subscriber users have no access

**SQL Injection:**
- [ ] All queries use `$wpdb->prepare()`
- [ ] No raw SQL with user input
- [ ] Test with malicious input (e.g., `1' OR '1'='1`)

**XSS Prevention:**
- [ ] All output uses `esc_html()`, `esc_attr()`
- [ ] No unescaped user content
- [ ] Test with `<script>alert('XSS')</script>` input

### Step 9: Cross-Browser Testing

Test in multiple browsers:

- [ ] Chrome (latest)
- [ ] Firefox (latest)
- [ ] Safari (latest)
- [ ] Edge (latest)

Verify:
- [ ] Charts render correctly
- [ ] Modal displays properly
- [ ] Buttons all clickable
- [ ] No CSS issues

### Step 10: Responsive Testing

Test at different screen sizes:

- [ ] Desktop (1920x1080)
- [ ] Laptop (1366x768)
- [ ] Tablet (768x1024)
- [ ] Mobile (375x667)

Verify:
- [ ] Charts stack on mobile
- [ ] Tables scroll horizontally if needed
- [ ] Filters collapse properly
- [ ] Modal fits screen

### Step 11: Error Handling

**Test Error Scenarios:**

1. **Network Error:**
   - [ ] Disable network in DevTools
   - [ ] Try to run scan
   - [ ] Error message displays
   - [ ] UI doesn't break

2. **Invalid Data:**
   - [ ] Send invalid saga_id (0)
   - [ ] Proper error message
   - [ ] No PHP fatal errors

3. **Missing Entity:**
   - [ ] Request issue with deleted entity
   - [ ] Graceful handling (shows "—" or empty)

4. **API Failure (if using AI):**
   - [ ] Provide invalid API key
   - [ ] Scan fails gracefully
   - [ ] Error logged, not displayed to user

### Step 12: Logging Verification

Check WordPress debug log:

```bash
tail -f /wp-content/debug.log
```

**Expected log entries:**
- [ ] `[SAGA][AI] Consistency Guardian activated` (on first load)
- [ ] `[SAGA][AI] Rule engine found X issues`
- [ ] `[SAGA][AI] Total analysis: X issues found`
- [ ] No PHP warnings or notices
- [ ] No fatal errors

### Step 13: REST API Testing

Test REST endpoints with curl or Postman:

**Scan:**
```bash
curl -X POST https://yoursite.com/wp-json/saga/v1/consistency/scan \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -d '{"saga_id":1}'
```
- [ ] Returns 200 OK
- [ ] Response includes issue count

**Get Issues:**
```bash
curl https://yoursite.com/wp-json/saga/v1/consistency/issues?saga_id=1
```
- [ ] Returns issues array
- [ ] Proper authentication required

**Get Single Issue:**
```bash
curl https://yoursite.com/wp-json/saga/v1/consistency/issues/123
```
- [ ] Returns issue details
- [ ] 404 for non-existent issue

## Post-Integration Checklist

### Documentation
- [ ] README file reviewed
- [ ] Integration checklist completed
- [ ] Team trained on new features

### Monitoring
- [ ] Error monitoring setup (e.g., Sentry)
- [ ] Performance tracking enabled
- [ ] User analytics configured

### Maintenance
- [ ] Backup schedule includes new table
- [ ] Update scripts include new files
- [ ] Version control includes all files

### Optional Enhancements
- [ ] Configure automatic scans
- [ ] Set up email notifications for critical issues
- [ ] Integrate with external monitoring tools
- [ ] Add custom issue types
- [ ] Implement advanced filtering

## Common Issues and Solutions

### Issue: "AI Guardian" menu not appearing
**Solution:**
- Clear WordPress cache
- Deactivate/reactivate theme
- Check functions.php for syntax errors

### Issue: Database table not created
**Solution:**
```php
// Run manually in wp-config.php temporarily
define('WP_DEBUG', true);
require_once get_template_directory() . '/inc/consistency-guardian-loader.php';
saga_consistency_create_tables();
```

### Issue: Charts not loading
**Solution:**
- Check browser console for errors
- Verify Chart.js CDN accessible
- Clear browser cache

### Issue: AJAX requests failing
**Solution:**
- Verify nonce in HTML source
- Check admin-ajax.php URL correct
- Enable WordPress debug mode
- Check .htaccess for redirect issues

### Issue: Slow performance
**Solution:**
- Enable Redis object cache
- Add database indexes (already included)
- Reduce scan frequency
- Disable AI for large sagas

## Rollback Procedure

If issues occur, rollback steps:

1. Remove loader from functions.php
2. Deactivate theme temporarily
3. Drop table (optional):
```sql
DROP TABLE wp_saga_consistency_issues;
```
4. Remove options:
```php
delete_option('saga_ai_consistency_enabled');
delete_option('saga_consistency_db_version');
```

## Success Criteria

Integration is successful when:
- [ ] All 13 verification steps passed
- [ ] No PHP errors in debug log
- [ ] No JavaScript console errors
- [ ] All AJAX requests return success
- [ ] Charts display correctly
- [ ] Scans complete without timeout
- [ ] Performance within acceptable limits (<2s page load)
- [ ] Security tests passed
- [ ] Cross-browser compatibility verified

## Final Sign-off

**Tested by:** _________________
**Date:** _________________
**Environment:** ☐ Development ☐ Staging ☐ Production
**Version:** 1.4.0
**WordPress Version:** _________________
**PHP Version:** _________________

**Notes:**
_________________________________________________________________
_________________________________________________________________
_________________________________________________________________

**Approved for production:** ☐ Yes ☐ No

**Signature:** _________________
