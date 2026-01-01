# Entity Extractor - Quick Start Guide

## 1. Access the Interface

Navigate to: **WordPress Admin → Saga Manager → Entity Extractor**

## 2. Start Extraction

1. Select target saga from dropdown
2. Paste text (max 100,000 characters)
3. (Optional) Click "Show Advanced Settings" to adjust:
   - Chunk size: 1K, 2.5K, 5K (default), 10K
   - AI provider: OpenAI, Anthropic, Google
   - AI model: GPT-4, GPT-3.5
4. Click "Start Extraction"

## 3. Monitor Progress

- Real-time progress bar updates every 2 seconds
- Statistics show entities found, pending, approved
- Click "Cancel Job" to stop if needed

## 4. Review Entities

Once extraction completes:

### Filter entities:
- Type: character, location, event, faction, artifact, concept
- Status: pending, approved, rejected
- Confidence: high (80%+), medium (60-80%), low (<60%)

### Review each entity card:
- Type badge (color-coded)
- Confidence score
- Name, description, attributes
- Context snippet
- Duplicate warnings (if any)

### Actions:
- Click "Approve" → Mark as approved
- Click "Reject" → Mark as rejected
- Use checkboxes + "Approve Selected" for bulk operations

## 5. Handle Duplicates

Entities with duplicate warnings show:
- Orange warning badge
- Similarity percentage
- Name of existing entity

Click "Resolve Duplicate":
- "Confirm as Duplicate" → Mark as duplicate (won't create)
- "Mark as Unique" → Create as new entity

## 6. Create Saga Entities

Once entities are reviewed and approved:

1. Click "Create Approved Entities"
2. Confirms creation of all approved entities
3. Shows success/failure count
4. Creates permanent entities in saga

## 7. View History

Scroll down to "Recent Extraction Jobs" table:
- See all past extraction jobs
- Click "Load" to review previous extractions
- Track success rates and costs

## AJAX Endpoints Reference

All endpoints use:
- URL: `admin-ajax.php`
- Nonce: `saga_extraction_nonce`
- Method: POST

### Start Extraction
```javascript
{
  action: 'saga_start_extraction',
  nonce: '...',
  saga_id: 123,
  source_text: 'text...',
  chunk_size: 5000,
  ai_provider: 'openai',
  ai_model: 'gpt-4'
}
```

### Get Progress
```javascript
{
  action: 'saga_get_extraction_progress',
  nonce: '...',
  job_id: 456
}
```

### Load Entities
```javascript
{
  action: 'saga_load_extracted_entities',
  nonce: '...',
  job_id: 456,
  page: 1,
  per_page: 25,
  filter_type: 'character',
  filter_status: 'pending',
  filter_confidence: 'high'
}
```

### Approve Entity
```javascript
{
  action: 'saga_approve_entity',
  nonce: '...',
  entity_id: 789
}
```

### Batch Create
```javascript
{
  action: 'saga_batch_create_approved',
  nonce: '...',
  job_id: 456,
  entity_ids: [] // empty = all approved
}
```

## Common Issues

### "Rate limit exceeded"
Wait 1 hour. Limit: 10 extractions per hour per user.

### "Text too long"
Maximum 100,000 characters. Split into multiple extractions.

### Extraction fails
1. Check AI API keys configured
2. Verify provider API is accessible
3. Check WordPress error logs

### Entities not loading
1. Verify job completed successfully
2. Check filter settings
3. Refresh page

## Tips

- Use 5000 character chunks for best balance
- Filter by confidence to prioritize review
- Approve high-confidence entities first
- Resolve duplicates before batch creation
- Check job history for cost tracking

## Support

Check logs for errors:
```
[SAGA][EXTRACTOR][AJAX] prefix in WordPress debug log
```

For detailed documentation, see:
- ENTITY_EXTRACTOR_README.md - Full feature guide
- IMPLEMENTATION_SUMMARY.md - Technical details
