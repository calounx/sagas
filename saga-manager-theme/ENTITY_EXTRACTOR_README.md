# Entity Extractor Admin Interface

Complete WordPress admin interface for AI-powered entity extraction with approval workflow.

## Overview

The Entity Extractor provides a full-featured admin interface for extracting saga entities from text using AI, reviewing extracted entities, managing duplicates, and batch creating approved entities.

## File Structure

```
saga-manager-theme/
├── inc/
│   ├── ajax/
│   │   └── extraction-ajax.php          # 12 AJAX endpoints
│   └── admin/
│       └── extraction-admin-init.php    # Admin menu & asset loading
├── page-templates/
│   ├── admin-extraction-page.php        # Main admin page
│   └── partials/
│       └── extraction-preview.php       # Entity card template
├── assets/
│   ├── js/
│   │   └── extraction-dashboard.js      # Frontend JavaScript
│   └── css/
│       └── extraction-dashboard.css     # Styling
└── functions.php                         # Loads extraction admin init
```

## Features

### 1. Text Extraction Form
- Textarea input with character counter (max 100K chars)
- Saga selector dropdown
- Advanced settings:
  - Chunk size selector (1K, 2.5K, 5K, 10K chars)
  - AI provider selection (OpenAI, Anthropic, Google)
  - AI model selection (GPT-4, GPT-3.5)
- Real-time cost estimation (debounced 1s)
- Validation and sanitization

### 2. Progress Tracking
- Real-time progress bar with percentage
- Progress polling every 2 seconds
- Statistics display:
  - Status (pending, processing, completed, failed)
  - Entities found
  - Pending review count
  - Approved count
- Cancel job button

### 3. Entity Preview & Approval
- Grid layout with responsive design
- Entity cards showing:
  - Type badge (color-coded)
  - Confidence score badge (high/medium/low)
  - Canonical name
  - Description
  - Attributes (key-value pairs)
  - Context snippet
  - Duplicate warnings with similarity score
- Filters:
  - Entity type
  - Status (pending, approved, rejected)
  - Confidence level
- Bulk actions:
  - Select all checkbox
  - Bulk approve
  - Bulk reject
  - Batch create approved entities
- Pagination (25 entities per page)

### 4. Duplicate Resolution
- Modal interface for duplicate review
- Shows similarity score and match reason
- Actions:
  - Confirm as duplicate
  - Mark as unique
- Updates extracted entity status

### 5. Job History
- Table showing recent extraction jobs
- Columns:
  - Job ID
  - Status (color-coded)
  - Entities found
  - Entities created
  - Entities rejected
  - Duplicates found
  - Date/time
- Load job button to review previous extractions

### 6. Dashboard Widget
- Shows last 5 extraction jobs
- Quick statistics
- Link to main extractor page

## AJAX Endpoints

All endpoints require:
- Nonce verification: `saga_extraction_nonce`
- User capability: `edit_posts` (or `read` for view-only)
- Input sanitization

### Available Endpoints

1. **saga_start_extraction**
   - Start new extraction job
   - Rate limited: 10 per hour per user
   - Max text length: 100K characters

2. **saga_get_extraction_progress**
   - Get job progress/status
   - Polled every 2 seconds during extraction

3. **saga_load_extracted_entities**
   - Load entities with pagination
   - Supports filtering by type, status, confidence
   - Returns 25 entities per page

4. **saga_approve_entity**
   - Approve single entity

5. **saga_reject_entity**
   - Reject single entity

6. **saga_bulk_approve_entities**
   - Approve multiple entities at once

7. **saga_batch_create_approved**
   - Create approved entities as permanent saga entities
   - Uses BatchEntityCreationService

8. **saga_resolve_duplicate**
   - Mark duplicate as confirmed or unique
   - Actions: `confirmed_duplicate`, `marked_unique`

9. **saga_load_job_history**
   - Get extraction job history
   - Supports pagination
   - Can filter by saga

10. **saga_cancel_extraction_job**
    - Cancel running job

11. **saga_get_extraction_stats**
    - Get statistics dashboard data
    - Global or per-saga stats

12. **saga_estimate_extraction_cost**
    - Estimate API cost before extraction
    - Returns: tokens, cost, time, expected entities

## Security Features

### Input Validation
- All POST data sanitized using WordPress functions
- `absint()` for IDs
- `sanitize_text_field()` for text
- `sanitize_key()` for keys
- `wp_kses_post()` for rich text

### Authorization
- Nonce verification on all AJAX requests
- Capability checks:
  - `edit_posts` - for write operations
  - `read` - for read-only operations
- Rate limiting (10 extractions/hour per user)

### Error Handling
- Try-catch blocks on all operations
- Database transactions with rollback
- Error logging with `[SAGA][EXTRACTOR][AJAX]` prefix
- User-friendly error messages

## Usage

### Admin Access

1. Navigate to: **Saga Manager → Entity Extractor**
2. Select target saga
3. Paste text to extract entities from
4. (Optional) Adjust advanced settings
5. Click "Start Extraction"
6. Review progress in real-time
7. Preview and approve/reject extracted entities
8. Click "Create Approved Entities" to finalize

### Cost Estimation

The interface automatically estimates:
- API tokens required
- Estimated cost in USD
- Processing time in seconds
- Expected number of entities

Updates as you type (debounced 1 second).

### Filtering Entities

Use filters to narrow down entities:
- **Type**: character, location, event, faction, artifact, concept
- **Status**: pending, approved, rejected
- **Confidence**: high (80%+), medium (60-80%), low (<60%)

### Duplicate Management

Entities with potential duplicates show:
- Orange warning badge
- Similarity percentage
- Name of existing entity
- "Resolve Duplicate" button

Click to open modal and confirm/reject duplicate match.

## Integration with Backend

### Services Used

- **ExtractionOrchestrator**: Main workflow coordination
- **ExtractionRepository**: Database operations
- **EntityExtractionService**: AI extraction
- **DuplicateDetectionService**: Duplicate matching
- **BatchEntityCreationService**: Bulk entity creation

### Database Tables

- `wp_saga_extraction_jobs`: Job tracking
- `wp_saga_extracted_entities`: Extracted entities
- `wp_saga_extraction_duplicates`: Duplicate matches

## Customization

### Styling

Edit `/assets/css/extraction-dashboard.css`:
- Color scheme
- Card layout
- Responsive breakpoints
- Badge colors

### JavaScript

Edit `/assets/js/extraction-dashboard.js`:
- Poll interval (default: 2s)
- Debounce delay (default: 1s)
- Entities per page (default: 25)
- Toast duration (default: 3s)

### AJAX Handlers

Edit `/inc/ajax/extraction-ajax.php`:
- Rate limits
- Max text length
- Validation rules
- Error messages

## Error Handling

### Common Errors

**"Rate limit exceeded"**
- Wait 1 hour or contact admin to reset

**"Text too long"**
- Maximum 100,000 characters
- Split into multiple extractions

**"Invalid saga ID"**
- Select a valid saga from dropdown

**"Extraction failed"**
- Check AI API keys in settings
- Verify API provider is accessible
- Check error logs

### Logging

All operations logged with prefix `[SAGA][EXTRACTOR][AJAX]`:
```
[SAGA][EXTRACTOR][AJAX] User 1 started extraction job #123
[SAGA][EXTRACTOR][AJAX] Approved entity #456: Luke Skywalker
[SAGA][EXTRACTOR][AJAX] Batch created 25/30 entities from job #123
[SAGA][EXTRACTOR][AJAX][ERROR] Start extraction failed: API key missing
```

## Performance

### Optimizations

- WordPress object caching for job data
- Debounced cost estimation (1s delay)
- Paginated entity loading (25 per page)
- Progress polling instead of long requests
- Transient-based rate limiting

### Recommendations

- Use chunk size 5000 for optimal balance
- Filter entities before bulk operations
- Process in batches of 25-50 entities
- Clear old jobs periodically

## Accessibility

- Keyboard navigation support
- ARIA labels on interactive elements
- Color-blind friendly status indicators
- Screen reader compatible
- Mobile responsive design

## Browser Support

- Chrome/Edge 90+
- Firefox 88+
- Safari 14+
- Mobile browsers (iOS Safari, Chrome Mobile)

## Troubleshooting

### Extraction not starting
1. Check AI API keys configured
2. Verify user has `edit_posts` capability
3. Check browser console for errors
4. Verify AJAX URL in network tab

### Progress stuck at 0%
1. Check if job status is "processing"
2. Verify backend services running
3. Check PHP error logs
4. Test API connectivity

### Entities not loading
1. Check job completed successfully
2. Verify entities saved to database
3. Check filter settings
4. Clear browser cache

### Duplicates not detected
1. Verify DuplicateDetectionService configured
2. Check similarity threshold settings
3. Ensure existing entities in saga
4. Check database indexes

## Future Enhancements

- [ ] Bulk reject entities endpoint
- [ ] Export extraction results to CSV
- [ ] Import entities from file
- [ ] Custom extraction templates
- [ ] Scheduled extractions
- [ ] Multi-language support
- [ ] Advanced duplicate resolution with merge
- [ ] Entity relationship extraction
- [ ] Confidence score tuning
- [ ] Background processing for large texts

## Support

For issues or questions:
1. Check error logs: `[SAGA][EXTRACTOR][AJAX]`
2. Verify database tables exist
3. Test AI API connectivity
4. Check WordPress debug mode
5. Review extraction job history

## Changelog

### Version 1.4.0 (2026-01-01)
- Initial release
- 12 AJAX endpoints
- Complete admin interface
- Real-time progress tracking
- Duplicate detection and resolution
- Batch entity creation
- Job history tracking
- Dashboard widget
- Mobile responsive design
- Rate limiting and security features
