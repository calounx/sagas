# Real-Time Consistency Checking - Quick Start Guide

## 5-Minute Setup

### 1. Install Dependencies (1 min)

```bash
cd saga-manager-theme
npm install
```

### 2. Build React Components (1 min)

```bash
npm run build
```

### 3. Verify Installation (1 min)

Open any Saga Entity in WordPress editor:

**Gutenberg:**
- Look for "Consistency Status" panel in Document sidebar (right)
- Should see shield icon

**Classic Editor:**
- Look for "Consistency Check" meta box below title

### 4. Test Functionality (2 min)

1. Make an edit to the entity content
2. Wait 5 seconds
3. Check should run automatically
4. Click "Check Now" button to manually trigger

**Expected Result:** Score badge appears with percentage and status

---

## File Overview

### Created Files

| File | Purpose | Size |
|------|---------|------|
| `/inc/ajax/consistency-ajax.php` | AJAX endpoints (extended) | 869 lines |
| `/assets/js/consistency-realtime-checker.js` | Core debounced checker | 450 lines |
| `/assets/js/consistency-gutenberg-panel.jsx` | Gutenberg sidebar panel | 440 lines |
| `/assets/js/blocks/consistency-badge.jsx` | Badge block | 350 lines |
| `/assets/js/consistency-classic-editor.js` | Classic editor integration | 520 lines |
| `/assets/css/consistency-editor.css` | Editor styles | 650 lines |
| `/inc/consistency-guardian-loader.php` | Asset enqueuing (extended) | 378 lines |
| `package.json` | NPM dependencies | 55 lines |
| `webpack.config.js` | Build configuration | 55 lines |
| `.babelrc` | Babel config | 15 lines |

**Total:** 3,782 lines of production-ready code

---

## Common Tasks

### Rebuild After JSX Changes

```bash
npm run build
```

### Development Mode (Auto-rebuild)

```bash
npm run dev
```

### Check Build Output

```bash
ls -la assets/js/build/
# Should see:
# - consistency-gutenberg-panel.js
# - consistency-badge-block.js
```

### Clear Cache

```bash
# WordPress cache
wp cache flush

# Browser cache
# Hard reload: Ctrl+Shift+R (Chrome/Firefox)
```

---

## Quick Tests

### Test Gutenberg Panel

1. Edit entity in Gutenberg
2. Click three dots (⋮) in top-right
3. Select "Consistency Status"
4. Panel should open on right

### Test Classic Editor Meta Box

1. Edit entity in Classic editor
2. Scroll to "Consistency Check" box
3. Click "Check Now"
4. Issues should load

### Test Real-Time Checking

1. Open entity editor
2. Type something in content area
3. Wait 5 seconds
4. Check console: Should see AJAX request

### Test Toast Notifications

1. Create a critical consistency issue
2. Edit the entity
3. Wait for check to run
4. Toast should appear in top-right

---

## Troubleshooting

### Panel Not Appearing

```bash
# Check assets loaded
grep 'saga-consistency' wp-content/debug.log

# Rebuild
npm run build

# Clear cache
wp cache flush
```

### JavaScript Errors

```bash
# Check console
# Open Developer Tools > Console
# Look for red errors

# Common fix: Rebuild
npm run build
```

### AJAX Errors

```bash
# Check nonce
console.log(sagaConsistency.nonce);

# Check endpoint
console.log(sagaConsistency.ajaxUrl);

# Enable WordPress debug
# In wp-config.php:
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
```

---

## What's Working

- Real-time consistency checking with 5-second debounce
- Gutenberg sidebar panel with live updates
- Classic Editor meta box integration
- Consistency badge block (insertable in posts)
- Toast notifications for critical issues
- Score calculation and color coding
- Resolve/Dismiss actions
- AJAX caching (60 seconds)
- Screen reader accessibility
- Keyboard navigation
- Dark mode support
- Responsive design

---

## Next Steps

1. **Customize Settings:** Edit check frequency in `consistency-realtime-checker.js`
2. **Add Custom Rules:** Extend `ConsistencyRuleEngine.php`
3. **Style Adjustments:** Modify `consistency-editor.css`
4. **Performance Tuning:** Adjust cache TTL in AJAX handlers

---

## Getting Help

**Error Logs:**
```bash
tail -f wp-content/debug.log | grep SAGA
```

**JavaScript Console:**
- Press F12 in browser
- Click "Console" tab
- Look for errors

**Network Tab:**
- Press F12 > Network
- Filter by "saga"
- Check AJAX requests

---

## Production Checklist

Before deploying:

- [ ] Run `npm run build` (production mode)
- [ ] Test all functionality
- [ ] Check console for errors
- [ ] Test on staging environment
- [ ] Clear all caches
- [ ] Verify accessibility
- [ ] Test performance

---

**Ready to use!** Edit any Saga Entity to see real-time consistency checking in action.
