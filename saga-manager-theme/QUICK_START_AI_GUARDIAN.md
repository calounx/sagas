# AI Consistency Guardian - Quick Start Guide

## Installation

The AI Consistency Guardian is automatically initialized when the theme is activated.

## Configuration (5 minutes)

### Step 1: Access Settings

Navigate to: **WordPress Admin → Appearance → AI Guardian**

### Step 2: Get API Keys

**Option A: OpenAI (Recommended)**
1. Visit https://platform.openai.com/api-keys
2. Create new API key
3. Copy key (starts with `sk-`)

**Option B: Anthropic Claude (Alternative)**
1. Visit https://console.anthropic.com/settings/keys
2. Create new API key
3. Copy key (starts with `sk-ant-`)

### Step 3: Configure Settings

1. **Enable AI Guardian**: Check the toggle
2. **AI Provider**: Select OpenAI or Anthropic
3. **API Key**: Paste your key (will be encrypted)
4. **Sensitivity**: Choose level:
   - **Strict**: Flags all potential issues
   - **Moderate**: Balanced (recommended)
   - **Permissive**: Only major issues
5. **Rule Types**: Select which checks to run
6. **Save Changes**

### Step 4: Test Connection

Click **"Test AI Connection"** button → Should show: ✓ AI connection successful!

## Basic Usage

### Run Analysis (PHP)

```php
// Analyze a saga
$issues = saga_analyze_consistency($sagaId);

// Display issues
foreach ($issues as $issue) {
    printf(
        "[%s] %s: %s\n",
        $issue->severity,
        $issue->getIssueTypeLabel(),
        $issue->description
    );
}
```

### Get Statistics

```php
$stats = saga_get_consistency_stats($sagaId);

echo "Open issues: {$stats['open_issues']}\n";
echo "Critical: {$stats['critical_count']}\n";
```

### Rule-Based Only (No AI - Fast)

```php
// Skip AI analysis (sub-50ms)
$issues = saga_analyze_consistency($sagaId, false);
```

## Issue Types

| Type | Description | Example |
|------|-------------|---------|
| **timeline** | Events out of order, invalid dates | "Event B occurs before Event A but is dated later" |
| **character** | Missing attributes, contradictions | "Character has no defined attributes" |
| **location** | Isolated locations, invalid hierarchies | "Location has no relationships to other entities" |
| **relationship** | Invalid links, temporal issues | "Relationship end date before start date" |
| **logical** | Duplicate slugs, invalid scores | "Entity has importance score of 150 (max: 100)" |

## Severity Levels

| Level | When to Use | Action Required |
|-------|-------------|-----------------|
| **critical** | Data corruption, broken references | Fix immediately |
| **high** | Major plot holes, contradictions | Fix soon |
| **medium** | Minor inconsistencies | Review and fix |
| **low** | Suggestions for improvement | Optional |
| **info** | Informational only | No action needed |

## Advanced Usage

### Custom Analysis

```php
$analyzer = saga_get_consistency_analyzer();

// Custom options
$issues = $analyzer->analyze($sagaId, [
    'entity_limit' => 100,        // Analyze top 100 entities
    'relationship_limit' => 200,
    'timeline_limit' => 50,
], true, ['timeline', 'character']); // Only timeline and character rules
```

### Resolve Issues

```php
$analyzer = saga_get_consistency_analyzer();

// Mark as resolved
$analyzer->resolveIssue($issueId, get_current_user_id());

// Dismiss as false positive
$analyzer->dismissIssue($issueId, get_current_user_id(), true);
```

### Access Repository Directly

```php
$repository = new \SagaManager\AI\ConsistencyRepository();

// Find by saga
$issues = $repository->findBySaga($sagaId, 'open', 50);

// Find by entity
$issues = $repository->findByEntity($entityId);

// Get statistics
$stats = $repository->getStatistics($sagaId);
```

## Performance Tips

### Caching

AI results are cached for 24 hours. To force refresh:

```php
// Delete cache
delete_transient("saga_ai_consistency_{$sagaId}");

// Re-run analysis
$issues = saga_analyze_consistency($sagaId, true);
```

### Rate Limits

- **10 AI checks per hour** per user
- Rule-based checks have no limit
- Cache hit = no rate limit charge

### Optimization

```php
// Fast: Rule-based only (< 50ms)
$issues = saga_analyze_consistency($sagaId, false);

// Slow: Full AI analysis (2-5s, cached 24h)
$issues = saga_analyze_consistency($sagaId, true);

// Recommended: Use AI for important sagas only
if ($saga->importance_score > 80) {
    $issues = saga_analyze_consistency($sagaId, true);
} else {
    $issues = saga_analyze_consistency($sagaId, false);
}
```

## Troubleshooting

### "Rate limit exceeded"

**Solution:** Wait 1 hour or use rule-based checks:

```php
$issues = saga_analyze_consistency($sagaId, false);
```

### "AI connection failed"

**Causes:**
- Invalid API key
- Insufficient API quota
- Network/firewall issues

**Solution:**
1. Check API key in settings
2. Verify API quota at provider dashboard
3. Test connection in settings page

### "No issues detected" (but you know there are issues)

**Causes:**
- Sensitivity set too low
- Some rule types disabled
- Cache showing old results

**Solution:**
1. Increase sensitivity to "Strict"
2. Enable all rule types
3. Clear cache and re-run

## Best Practices

### When to Run Analysis

**Always:**
- After bulk entity import
- Before publishing major saga updates
- After timeline modifications

**Recommended:**
- Weekly for active sagas
- Daily for critical/public sagas

**Optional:**
- After every entity creation (use auto-run)

### Auto-Run Settings

**Manual (Default):**
- Run analysis on demand
- Best for development

**Daily:**
- Scheduled analysis at midnight
- Good for production sagas

**Weekly:**
- Analysis every Monday
- Good for archived sagas

### Handling False Positives

```php
// Dismiss with reason
$analyzer = saga_get_consistency_analyzer();
$analyzer->dismissIssue($issueId, get_current_user_id(), true);

// AI will learn to avoid similar issues (future enhancement)
```

## Security Notes

### API Keys

- Encrypted with AES-256-CBC before storage
- Uses WordPress `AUTH_KEY` and `SECURE_AUTH_KEY`
- Never logged or exposed in errors
- Automatically decrypted on use

### Permissions

- Only users with `manage_options` capability can:
  - Configure settings
  - View API keys
  - Test connections

### Data Privacy

- AI analysis sends entity metadata only (no user data)
- Results cached locally (not shared)
- No telemetry or tracking

## Cost Estimation

### OpenAI GPT-4

- Input: ~500 tokens per analysis ($0.01)
- Output: ~200 tokens per analysis ($0.03)
- **Total: ~$0.04 per analysis**

With 24-hour caching:
- 10 sagas analyzed daily = $0.40/day = $12/month

### Anthropic Claude

- Input/Output: ~700 tokens total ($0.02)
- **Total: ~$0.02 per analysis**

With 24-hour caching:
- 10 sagas analyzed daily = $0.20/day = $6/month

### Free Tier

Use **rule-based only** for unlimited free checks:

```php
$issues = saga_analyze_consistency($sagaId, false);
```

## Next Steps

1. **Configure settings** (5 minutes)
2. **Test on small saga** (verify results)
3. **Review detected issues** (validate accuracy)
4. **Adjust sensitivity** (if needed)
5. **Enable auto-run** (for production)

## Resources

- **Full Documentation:** `inc/ai/README.md`
- **Implementation Details:** `AI_CONSISTENCY_GUARDIAN_IMPLEMENTATION.md`
- **API Reference:** See `inc/ai/README.md` → API Reference section

## Support

For issues:
- Check `error_log` for detailed messages
- Enable `WP_DEBUG` for debug logging
- Review troubleshooting section above

## Examples Repository

Coming soon: Example implementations and use cases.
