# Phase 1 Features - Testing Guide

**Theme Version:** 1.3.0
**Testing Date:** 2025-01-01
**Status:** Ready for Testing

---

## 🎯 Overview

This guide provides comprehensive testing procedures for all 5 Phase 1 next-generation features. Follow these steps to verify functionality, performance, accessibility, and security.

---

## 📋 Pre-Testing Checklist

### Environment Setup
- [ ] WordPress 6.0+ installed
- [ ] PHP 8.2+ enabled
- [ ] MariaDB 11.4.8+ running
- [ ] GeneratePress parent theme installed and activated
- [ ] Saga Manager theme activated
- [ ] Browser console open (F12)
- [ ] WordPress debug mode enabled (`WP_DEBUG = true`)
- [ ] Query Monitor plugin installed (optional but recommended)

### Browser Testing Matrix
Test in the following browsers:
- [ ] Chrome 90+ (desktop)
- [ ] Firefox 88+ (desktop)
- [ ] Safari 14+ (desktop/mobile)
- [ ] Edge 90+ (desktop)
- [ ] Chrome Mobile (Android 10+)
- [ ] Safari Mobile (iOS 14+)

---

## 1. 3D Semantic Galaxy Visualization

### Basic Functionality Tests

#### Test 1.1: Shortcode Rendering
**Steps:**
1. Create a new post/page
2. Add shortcode: `[saga_galaxy saga_id="1"]`
3. Preview/publish the page

**Expected:**
- ✅ 3D canvas appears without errors
- ✅ Entity nodes render as colored spheres
- ✅ Starfield background visible (1000+ particles)
- ✅ Control panel appears on right side
- ✅ Minimap shows in bottom-left corner
- ✅ No console errors

**Actual:** _______________

#### Test 1.2: Navigation Controls
**Steps:**
1. Click and drag on canvas to rotate
2. Scroll to zoom in/out
3. Right-click drag to pan
4. Click "Reset View" button

**Expected:**
- ✅ Orbit rotation smooth (60 FPS)
- ✅ Zoom responds to mouse wheel
- ✅ Pan moves camera position
- ✅ Reset returns to initial view
- ✅ FPS counter shows 60+ (desktop)

**Actual:** _______________

#### Test 1.3: Entity Interaction
**Steps:**
1. Click on an entity node
2. Observe info panel
3. Use search box to find "character"
4. Click "Clear Search" button

**Expected:**
- ✅ Entity glows on hover
- ✅ Info panel shows entity details on click
- ✅ Search highlights matching entities
- ✅ Non-matching entities dim
- ✅ Clear restores all entities

**Actual:** _______________

### Performance Tests

#### Test 1.4: Large Dataset Performance
**Steps:**
1. Test with 100 entities
2. Test with 500 entities
3. Test with 1000 entities
4. Monitor FPS counter

**Expected:**
- ✅ 100 entities: 90+ FPS
- ✅ 500 entities: 70+ FPS
- ✅ 1000 entities: 60+ FPS
- ✅ No memory leaks over 5 minutes

**Actual:** _______________

### Accessibility Tests

#### Test 1.5: Keyboard Navigation
**Steps:**
1. Press `R` key (reset view)
2. Press `A` key (auto-rotate)
3. Press `Esc` key (deselect)
4. Tab through controls

**Expected:**
- ✅ R: view resets
- ✅ A: rotation starts/stops
- ✅ Esc: deselects entity
- ✅ Tab: focuses next control
- ✅ Screen reader announces actions

**Actual:** _______________

### Mobile Tests

#### Test 1.6: Touch Gestures
**Steps:**
1. One-finger drag (rotate)
2. Pinch to zoom
3. Two-finger drag (pan)

**Expected:**
- ✅ Touch rotation smooth
- ✅ Pinch zoom responsive
- ✅ Pan works correctly
- ✅ 30+ FPS on mobile

**Actual:** _______________

---

## 2. WebGPU Infinite Zoom Timeline

### Basic Functionality Tests

#### Test 2.1: Shortcode Rendering
**Steps:**
1. Create a new post/page
2. Add shortcode: `[saga_timeline saga_id="1"]`
3. Preview/publish the page

**Expected:**
- ✅ Timeline canvas renders
- ✅ Event markers visible
- ✅ Grid with time labels shows
- ✅ Minimap appears bottom-right
- ✅ Controls panel visible
- ✅ No console errors

**Actual:** _______________

#### Test 2.2: Zoom and Pan
**Steps:**
1. Scroll to zoom in (years → months → days)
2. Continue zooming to hour level
3. Drag to pan left/right
4. Click "Fit All" button

**Expected:**
- ✅ Smooth zoom transitions
- ✅ Grid intervals adapt (years/months/days/hours)
- ✅ Event detail increases with zoom
- ✅ Pan momentum feels natural
- ✅ Fit All shows entire timeline
- ✅ 60 FPS during zoom/pan

**Actual:** _______________

#### Test 2.3: Event Interaction
**Steps:**
1. Click on an event marker
2. Hover over events
3. Use search to find events
4. Add a bookmark (Ctrl+B)

**Expected:**
- ✅ Event details popup appears
- ✅ Tooltip shows on hover
- ✅ Search highlights matching events
- ✅ Bookmark icon appears on timeline
- ✅ Bookmarks saved to localStorage

**Actual:** _______________

### Performance Tests

#### Test 2.4: Large Timeline Performance
**Steps:**
1. Load timeline with 1000 events
2. Load timeline with 5000 events
3. Load timeline with 10000 events
4. Monitor FPS during zoom/pan

**Expected:**
- ✅ 1000 events: 60 FPS
- ✅ 5000 events: 60 FPS
- ✅ 10000 events: 60 FPS
- ✅ Quadtree culling works (off-screen events not rendered)

**Actual:** _______________

### Calendar System Tests

#### Test 2.5: Custom Calendar Conversion
**Steps:**
1. Create events with "BBY" dates (Star Wars)
2. Create events with "AG" dates (Dune)
3. Create events with "Third Age" (LOTR)
4. Verify chronological ordering

**Expected:**
- ✅ All calendar systems display correctly
- ✅ Events sort chronologically
- ✅ Grid labels match calendar type
- ✅ Date conversions accurate

**Actual:** _______________

### Keyboard Tests

#### Test 2.6: Keyboard Shortcuts
**Steps:**
1. Press `←` `→` (pan left/right)
2. Press `+` `-` (zoom in/out)
3. Press `Home` (fit all events)
4. Press `Ctrl+F` (search)
5. Press `Ctrl+B` (bookmark)

**Expected:**
- ✅ Arrow keys pan timeline
- ✅ +/- zoom timeline
- ✅ Home fits all events
- ✅ Ctrl+F opens search
- ✅ Ctrl+B adds bookmark

**Actual:** _______________

---

## 3. Semantic Search UI

### Basic Functionality Tests

#### Test 3.1: Shortcode Rendering
**Steps:**
1. Create a new post/page
2. Add shortcode: `[saga_search]`
3. Preview/publish the page

**Expected:**
- ✅ Search box appears
- ✅ Advanced filters panel visible
- ✅ No console errors
- ✅ Placeholder text shows

**Actual:** _______________

#### Test 3.2: Natural Language Search
**Steps:**
1. Search: "jedi temple"
2. Search: "battle"
3. Search: "ancient"
4. Observe results

**Expected:**
- ✅ Results appear <500ms
- ✅ Synonyms matched ("battle" → "combat", "war")
- ✅ Relevance sorting works
- ✅ Result count accurate
- ✅ Snippets highlighted

**Actual:** _______________

#### Test 3.3: Advanced Search Syntax
**Steps:**
1. Search: `"Clone Wars"` (exact phrase)
2. Search: `dark side -sith` (exclusion)
3. Search: `battle AND clone` (Boolean AND)
4. Search: `ancient OR primordial` (Boolean OR)

**Expected:**
- ✅ Exact phrases work
- ✅ Exclusions filter correctly
- ✅ AND requires both terms
- ✅ OR matches either term

**Actual:** _______________

### Autocomplete Tests

#### Test 3.4: Autocomplete Suggestions
**Steps:**
1. Type "ja" in search box
2. Wait 300ms
3. Observe suggestions dropdown
4. Use arrow keys to navigate
5. Press Enter to select

**Expected:**
- ✅ Suggestions appear after 300ms
- ✅ Entity previews show thumbnails
- ✅ Recent searches appear
- ✅ Arrow keys navigate
- ✅ Enter selects suggestion

**Actual:** _______________

### Voice Search Tests

#### Test 3.5: Voice Input (Chrome/Edge only)
**Steps:**
1. Click microphone icon
2. Speak: "jedi temple"
3. Wait for transcription
4. Observe search execution

**Expected:**
- ✅ Microphone icon activates
- ✅ Real-time transcription shows
- ✅ Search auto-submits
- ✅ Results match spoken query
- ⚠️ Only works in Chrome/Edge

**Actual:** _______________

### Filter Tests

#### Test 3.6: Advanced Filters
**Steps:**
1. Select entity type: "Character"
2. Set importance: 80-100
3. Apply filters
4. Clear filters

**Expected:**
- ✅ Results filter to characters only
- ✅ Only high-importance entities show
- ✅ Filter combinations work (AND logic)
- ✅ Clear resets all filters

**Actual:** _______________

### Performance Tests

#### Test 3.7: Search Performance
**Steps:**
1. Search with 10 results
2. Search with 100 results
3. Search with 1000+ results
4. Monitor query time

**Expected:**
- ✅ Query time <50ms (database)
- ✅ Total response <300ms (autocomplete)
- ✅ Results cached (5 min TTL)
- ✅ Second search instant (from cache)

**Actual:** _______________

---

## 4. Enhanced Relationship Graph v2

### Basic Functionality Tests

#### Test 4.1: Shortcode Rendering
**Steps:**
1. Create a new post/page
2. Add shortcode: `[saga_graph_v2 entity_id="123"]`
3. Preview/publish the page

**Expected:**
- ✅ Graph canvas renders
- ✅ Nodes and edges visible
- ✅ Layout controls appear
- ✅ No console errors

**Actual:** _______________

#### Test 4.2: Layout Switching
**Steps:**
1. Click "Force" layout button (F)
2. Click "Hierarchical" button (H)
3. Click "Circular" button (C)
4. Click "Radial" button (R)
5. Click "Grid" button (G)
6. Click "Clustered" button (K)

**Expected:**
- ✅ Smooth animated transitions
- ✅ Each layout distinct
- ✅ Force: physics simulation
- ✅ Hierarchical: tree structure
- ✅ Circular: entities in circle
- ✅ Radial: concentric rings
- ✅ Grid: organized matrix
- ✅ Clustered: grouped by type

**Actual:** _______________

#### Test 4.3: Node Interaction
**Steps:**
1. Click a node
2. Drag a node
3. Shift+Click multiple nodes
4. Right-click a node

**Expected:**
- ✅ Click selects node
- ✅ Drag repositions node
- ✅ Multi-select works
- ✅ Context menu appears
- ✅ Edges highlight on selection

**Actual:** _______________

### Analytics Tests

#### Test 4.4: Graph Analytics
**Steps:**
1. Click "Show Analytics" button
2. Observe centrality scores
3. Click "Find Shortest Path"
4. Select two nodes

**Expected:**
- ✅ Analytics panel appears
- ✅ Betweenness centrality calculated
- ✅ Community detection works
- ✅ Shortest path highlighted

**Actual:** _______________

### Performance Tests

#### Test 4.5: Large Graph Performance
**Steps:**
1. Load graph with 100 nodes
2. Load graph with 500 nodes
3. Load graph with 1000 nodes
4. Enable Web Worker

**Expected:**
- ✅ 100 nodes: 90+ FPS
- ✅ 500 nodes: 70+ FPS
- ✅ 1000 nodes: 60+ FPS
- ✅ Web Worker prevents UI blocking

**Actual:** _______________

### Export Tests

#### Test 4.6: Graph Export
**Steps:**
1. Click "Export PNG"
2. Click "Export SVG"
3. Click "Export CSV"
4. Verify downloads

**Expected:**
- ✅ PNG downloads with current view
- ✅ SVG downloads vector graphics
- ✅ CSV contains node/edge data
- ✅ Files open correctly

**Actual:** _______________

---

## 5. Entity Quick Create

### Basic Functionality Tests

#### Test 5.1: Admin Bar Integration
**Steps:**
1. Log in as editor/admin
2. Observe WordPress admin bar
3. Click "+ New Entity" menu
4. Observe dropdown

**Expected:**
- ✅ "+ New Entity" appears in admin bar
- ✅ Badge shows entity count
- ✅ Dropdown has links for 6 entity types
- ✅ Recent entities list shows (5 items)

**Actual:** _______________

#### Test 5.2: Keyboard Shortcut
**Steps:**
1. Press `Ctrl+Shift+E`
2. Observe modal appears
3. Press `Esc`
4. Modal closes

**Expected:**
- ✅ Ctrl+Shift+E opens modal
- ✅ Modal fades in smoothly
- ✅ First field auto-focused
- ✅ Esc closes modal
- ✅ Focus returns to page

**Actual:** _______________

#### Test 5.3: Entity Creation
**Steps:**
1. Open quick create modal
2. Select entity type: "Character"
3. Enter name: "Test Hero"
4. Select importance: 75
5. Enter description
6. Click "Publish"

**Expected:**
- ✅ Form validates before submit
- ✅ AJAX submission (no page reload)
- ✅ Success notification appears
- ✅ "Edit Entity" link provided
- ✅ Entity appears in recent list
- ✅ Modal closes after success

**Actual:** _______________

### Template Tests

#### Test 5.4: Entity Templates
**Steps:**
1. Open quick create modal
2. Select entity type
3. Click "Load Template" dropdown
4. Select "Protagonist" template
5. Observe fields populate

**Expected:**
- ✅ Templates available for all types
- ✅ Template loads field values
- ✅ Description pre-filled
- ✅ Importance pre-set
- ✅ Can edit template values

**Available Templates:**
- ✅ Character: Protagonist, Antagonist, Supporting
- ✅ Location: World, Settlement, Structure
- ✅ Event: Battle, Political
- ✅ Faction: Government, Organization
- ✅ Artifact: Weapon, Magical
- ✅ Concept: Philosophy, Magic System

**Actual:** _______________

### Autosave Tests

#### Test 5.5: Autosave and Recovery
**Steps:**
1. Open quick create modal
2. Enter entity name and description
3. Wait 2+ seconds
4. Close modal (Esc)
5. Reopen modal (Ctrl+Shift+E)

**Expected:**
- ✅ Autosave triggers every 2 seconds
- ✅ localStorage stores draft
- ✅ "Draft Recovered" notification shows
- ✅ Fields restore previous values
- ✅ Can discard draft

**Actual:** _______________

### Validation Tests

#### Test 5.6: Duplicate Name Detection
**Steps:**
1. Create entity: "Luke Skywalker"
2. Open quick create again
3. Enter name: "Luke Skywalker"
4. Wait 500ms for validation

**Expected:**
- ✅ Inline error appears
- ✅ "Entity name already exists" message
- ✅ Submit button disabled
- ✅ Error clears when name changed

**Actual:** _______________

### Security Tests

#### Test 5.7: Capability Checks
**Steps:**
1. Log out
2. Try to access quick create
3. Log in as subscriber
4. Try to access quick create
5. Log in as editor
6. Access quick create

**Expected:**
- ✅ Logged out: No "+ New Entity" in admin bar
- ✅ Subscriber: No "+ New Entity" visible
- ✅ Editor: "+ New Entity" appears
- ✅ Admin: "+ New Entity" appears

**Actual:** _______________

---

## 🔒 Security Testing

### SQL Injection Tests
**For all features:**
1. Try SQL injection in search: `' OR 1=1 --`
2. Try SQL in entity name: `'; DROP TABLE--`
3. Observe sanitization

**Expected:**
- ✅ All inputs sanitized
- ✅ No SQL executed
- ✅ Error messages safe
- ✅ `$wpdb->prepare()` used everywhere

**Actual:** _______________

### XSS Tests
**For all features:**
1. Try XSS in search: `<script>alert('XSS')</script>`
2. Try XSS in entity description
3. Observe escaping

**Expected:**
- ✅ All outputs escaped
- ✅ No script execution
- ✅ HTML entities shown
- ✅ `esc_html()` / `esc_attr()` used

**Actual:** _______________

### CSRF Tests
**For all AJAX:**
1. Submit form without nonce
2. Submit with invalid nonce
3. Observe rejection

**Expected:**
- ✅ Missing nonce: 403 error
- ✅ Invalid nonce: 403 error
- ✅ Valid nonce: Success
- ✅ `wp_verify_nonce()` used

**Actual:** _______________

---

## ♿ Accessibility Testing

### Screen Reader Tests
**Tools:** NVDA, JAWS, or VoiceOver

1. Navigate each feature with screen reader
2. Verify ARIA labels announced
3. Check focus order logical
4. Verify live regions work

**Expected:**
- ✅ All interactive elements labeled
- ✅ Status changes announced
- ✅ Focus visible and logical
- ✅ Keyboard navigation complete

**Actual:** _______________

### Keyboard Navigation Tests
**For each feature:**
1. Tab through all controls
2. Use arrow keys where applicable
3. Activate with Enter/Space
4. Close with Esc

**Expected:**
- ✅ All controls keyboard accessible
- ✅ Focus indicators visible
- ✅ Tab order logical
- ✅ No keyboard traps

**Actual:** _______________

### Color Contrast Tests
**Tools:** WAVE, axe DevTools

1. Run accessibility audit
2. Check color contrast ratios
3. Test high contrast mode
4. Test dark mode

**Expected:**
- ✅ All text 4.5:1 contrast (AA)
- ✅ Large text 3:1 contrast (AA)
- ✅ UI components 3:1 contrast
- ✅ High contrast mode works

**Actual:** _______________

### Reduced Motion Tests
1. Enable "Reduce Motion" in OS
2. Reload pages with features
3. Observe animations

**Expected:**
- ✅ Animations disabled/reduced
- ✅ Transitions instant
- ✅ No parallax effects
- ✅ `prefers-reduced-motion` respected

**Actual:** _______________

---

## ⚡ Performance Testing

### Lighthouse Audit
1. Open Chrome DevTools
2. Run Lighthouse audit
3. Check scores

**Expected:**
- ✅ Performance: 90+
- ✅ Accessibility: 95+
- ✅ Best Practices: 90+
- ✅ SEO: 90+

**Actual Scores:**
- Performance: ___
- Accessibility: ___
- Best Practices: ___
- SEO: ___

### Query Monitor Tests
**Plugin:** Query Monitor

1. Install Query Monitor plugin
2. Load pages with features
3. Check "Queries" tab

**Expected:**
- ✅ All queries <50ms
- ✅ No duplicate queries
- ✅ Proper indexing used
- ✅ Cache hit rate >80%

**Actual:** _______________

### Memory Leak Tests
1. Open Chrome DevTools > Memory
2. Take heap snapshot
3. Use feature for 5 minutes
4. Take another snapshot
5. Compare

**Expected:**
- ✅ No significant memory growth
- ✅ Objects cleaned up properly
- ✅ Event listeners removed
- ✅ `dispose()` methods work

**Actual:** _______________

---

## 📱 Mobile Testing

### Responsive Design Tests
**Test on devices:**
- [ ] iPhone 12/13/14 (iOS Safari)
- [ ] Samsung Galaxy S21+ (Chrome)
- [ ] iPad Pro (Safari)

**For each feature:**
1. Verify layouts adapt
2. Test touch gestures
3. Check text readability
4. Verify button tap targets

**Expected:**
- ✅ Mobile layouts work
- ✅ Touch gestures responsive
- ✅ Text 16px+ (readable)
- ✅ Buttons 44x44px+ (tappable)

**Actual:** _______________

---

## 🔧 Edge Case Testing

### Empty State Tests
1. Test each feature with no data
2. Verify empty state messages
3. Check for errors

**Expected:**
- ✅ Graceful empty states
- ✅ Helpful messages shown
- ✅ No console errors
- ✅ CTA buttons visible

**Actual:** _______________

### Large Data Tests
1. Test with 10,000+ entities
2. Test with 1,000+ relationships
3. Monitor performance

**Expected:**
- ✅ No performance degradation
- ✅ Virtual scrolling works
- ✅ Pagination functional
- ✅ Queries remain <50ms

**Actual:** _______________

### Network Error Tests
1. Simulate offline mode
2. Attempt AJAX requests
3. Observe error handling

**Expected:**
- ✅ Error messages shown
- ✅ Retry buttons available
- ✅ No data corruption
- ✅ Graceful degradation

**Actual:** _______________

---

## ✅ Final Checklist

### Pre-Deployment
- [ ] All features render without errors
- [ ] Performance metrics met (<50ms queries, 60 FPS)
- [ ] Security tests passed (SQL injection, XSS, CSRF)
- [ ] Accessibility tests passed (WCAG 2.1 AA)
- [ ] Browser compatibility verified
- [ ] Mobile responsiveness confirmed
- [ ] Documentation reviewed
- [ ] No console errors in any browser

### Post-Deployment
- [ ] Monitor error logs for 24 hours
- [ ] Check analytics for usage patterns
- [ ] Collect user feedback
- [ ] Performance metrics tracked
- [ ] Cache hit rates monitored
- [ ] Database query performance verified

---

## 📊 Test Results Summary

### Overall Status
- **Total Tests:** 50+
- **Passed:** ___
- **Failed:** ___
- **Skipped:** ___
- **Success Rate:** ____%

### Critical Issues Found
1. _______________
2. _______________
3. _______________

### Non-Critical Issues Found
1. _______________
2. _______________
3. _______________

### Recommendations
1. _______________
2. _______________
3. _______________

---

## 📞 Support

### Reporting Issues
If you encounter any issues during testing:

1. **Check console for errors** (F12)
2. **Verify WordPress debug log** (`wp-content/debug.log`)
3. **Document steps to reproduce**
4. **Include browser/version info**
5. **Screenshot error messages**
6. **Note expected vs actual behavior**

### Getting Help
- Review feature documentation in `/docs`
- Check README files for each feature
- Inspect code in `/assets` and `/inc`
- Test with minimal theme/plugins
- Clear cache and test again

---

**Testing Guide Version:** 1.0
**Last Updated:** 2025-01-01
**Theme Version:** 1.3.0
**Status:** Ready for Testing ✅
