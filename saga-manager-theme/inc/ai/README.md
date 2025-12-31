# AI Consistency Guardian - Backend Infrastructure

## Overview

AI-powered consistency checking system for Saga Manager theme (v1.4.0). Detects plot holes, timeline inconsistencies, character contradictions, and logical errors using hybrid rule-based + AI semantic analysis.

## Architecture

### Hexagonal Architecture Layers

```
Domain Layer (entities/)
  ├── ConsistencyIssue.php          # Immutable value object

Application Layer
  ├── ConsistencyRuleEngine.php     # Rule-based validation
  ├── AIClient.php                  # OpenAI/Anthropic integration
  └── ConsistencyAnalyzer.php       # Orchestration layer

Infrastructure Layer
  ├── ConsistencyRepository.php     # Database operations
  └── database-migrator.php         # Schema management

Presentation Layer
  └── ../admin/ai-settings.php      # WordPress admin UI
```

## Features

### Rule-Based Validation (Fast)
- Timeline consistency checks
- Character attribute validation
- Location relationship logic
- Relationship temporal validity
- Importance score validation
- Duplicate slug detection

### AI Semantic Analysis (Accurate)
- Plot hole detection
- Character contradiction analysis
- Narrative consistency
- Complex logical errors
- Context-aware suggestions

### Hybrid Approach
1. Fast rule-based checks (sub-50ms)
2. AI analysis for complex issues (2-5s)
3. Deduplication and merging
4. Severity-based sorting

## Database Schema

```sql
CREATE TABLE wp_saga_consistency_issues (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    saga_id INT UNSIGNED NOT NULL,
    issue_type ENUM('timeline','character','location','relationship','logical') NOT NULL,
    severity ENUM('critical','high','medium','low','info') NOT NULL,
    entity_id BIGINT UNSIGNED,
    related_entity_id BIGINT UNSIGNED,
    description TEXT NOT NULL,
    context JSON,
    suggested_fix TEXT,
    status ENUM('open','resolved','dismissed','false_positive') DEFAULT 'open',
    detected_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    resolved_at TIMESTAMP NULL,
    resolved_by BIGINT UNSIGNED,
    ai_confidence DECIMAL(3,2),
    INDEX idx_saga_status (saga_id, status),
    INDEX idx_severity (severity),
    INDEX idx_detected (detected_at DESC)
);
```

## Usage Examples

### Basic Analysis

```php
// Run full analysis (rules + AI)
$issues = saga_analyze_consistency($sagaId, true);

foreach ($issues as $issue) {
    echo $issue->getSeverityLabel() . ': ' . $issue->description . "\n";

    if ($issue->suggestedFix) {
        echo "Fix: " . $issue->suggestedFix . "\n";
    }
}
```

### Rule-Based Only

```php
// Fast rule checks only (no AI)
$issues = saga_analyze_consistency($sagaId, false);
```

### Specific Rule Types

```php
// Check only timeline and character rules
$issues = saga_analyze_consistency($sagaId, false, ['timeline', 'character']);
```

### Get Statistics

```php
$stats = saga_get_consistency_stats($sagaId);

echo "Open issues: {$stats['open_issues']}\n";
echo "Critical: {$stats['critical_count']}\n";
echo "AI confidence: " . ($stats['avg_ai_confidence'] * 100) . "%\n";
```

### Resolve/Dismiss Issues

```php
$analyzer = saga_get_consistency_analyzer();

// Resolve an issue
$analyzer->resolveIssue($issueId, get_current_user_id());

// Dismiss as false positive
$analyzer->dismissIssue($issueId, get_current_user_id(), true);
```

## Configuration

### Admin Settings Page

Navigate to: **Appearance → AI Guardian**

Settings:
- Enable/disable AI analysis
- AI provider selection (OpenAI/Anthropic)
- API key management (encrypted)
- Sensitivity level (strict/moderate/permissive)
- Rule type selection
- Auto-run frequency

### API Key Setup

1. Get OpenAI API key: https://platform.openai.com/api-keys
2. Get Anthropic API key: https://console.anthropic.com/settings/keys
3. Add to settings page (encrypted before storage)

### Encryption

API keys are encrypted using WordPress salts:

```php
// Manual encryption
$encrypted = \SagaManager\AI\AIClient::encrypt('sk-...');
update_option('saga_ai_openai_key', $encrypted);
```

## Security Features

### Input Sanitization
- All user input sanitized via `sanitize_text_field()`
- SQL injection prevention with `$wpdb->prepare()`
- Nonce verification on AJAX requests
- Capability checks (`manage_options`)

### API Key Protection
- Encrypted storage using AES-256-CBC
- WordPress AUTH_KEY and SECURE_AUTH_KEY as encryption keys
- Never logged or exposed in errors

### Rate Limiting
- 10 AI checks per user per hour
- Transient-based rate limiting
- Graceful degradation (rules-only mode)

### SQL Injection Prevention

```php
// ✓ CORRECT - All queries use wpdb->prepare()
$issues = $wpdb->get_results($wpdb->prepare(
    "SELECT * FROM {$tableName} WHERE saga_id = %d AND status = %s",
    $sagaId,
    $status
));

// ✗ WRONG - Never used in this codebase
$issues = $wpdb->get_results("SELECT * FROM {$tableName} WHERE saga_id = {$sagaId}");
```

## Performance Optimization

### Caching Strategy

```php
// Object cache (5 minutes)
wp_cache_set("saga_issue_{$id}", $issue, 'saga', 300);

// AI results cache (24 hours)
set_transient("saga_ai_consistency_{$cacheKey}", $results, 86400);

// Statistics cache (5 minutes)
wp_cache_set("saga_stats_{$sagaId}", $stats, 'saga', 300);
```

### Query Optimization

- All queries use proper indexes
- LIMIT clauses on AI context building
- Bulk hydration instead of N+1 queries
- `FIELD()` for custom sort orders

### Benchmarks

Target performance:
- Rule-based checks: <50ms for 100K entities
- AI analysis: 2-5s (cached for 24 hours)
- Database operations: <10ms per query

## Error Handling

### Exception Hierarchy

```php
try {
    $issues = $analyzer->analyze($sagaId);
} catch (\InvalidArgumentException $e) {
    // Validation error (issue type, severity, confidence)
    error_log('[SAGA][AI] Validation error: ' . $e->getMessage());
} catch (\RuntimeException $e) {
    // AI API error, database error
    error_log('[SAGA][AI] Runtime error: ' . $e->getMessage());
}
```

### Logging

```php
// Debug (only if WP_DEBUG)
if (WP_DEBUG) {
    error_log('[SAGA][AI][DEBUG] Found ' . count($issues) . ' issues');
}

// Errors (always logged)
error_log('[SAGA][AI][ERROR] AI API failed: ' . $exception->getMessage());

// Critical (always logged)
error_log('[SAGA][AI][CRITICAL] Database migration failed');
```

## Testing

### Unit Tests

```php
// Test ConsistencyIssue value object
$issue = new ConsistencyIssue(
    id: null,
    sagaId: 1,
    issueType: 'timeline',
    severity: 'high',
    entityId: 123,
    relatedEntityId: null,
    description: 'Event dates out of order',
    context: ['event_ids' => [1, 2]],
    suggestedFix: 'Reorder events chronologically'
);

assert($issue->isOpen() === true);
assert($issue->severity === 'high');

// Test resolve
$resolved = $issue->resolve(1);
assert($resolved->isResolved() === true);
assert($resolved->resolvedBy === 1);
```

### Integration Tests

```php
// Test rule engine
$ruleEngine = new \SagaManager\AI\ConsistencyRuleEngine();
$issues = $ruleEngine->runRules($sagaId, ['timeline']);

assert(count($issues) > 0);
assert($issues[0] instanceof \SagaManager\AI\Entities\ConsistencyIssue);
```

### Manual Testing

1. Navigate to **Appearance → AI Guardian**
2. Configure API keys
3. Click **Test AI Connection**
4. Expected: "✓ AI connection successful!"

## Troubleshooting

### AI Connection Fails

```
Error: Connection failed: cURL error 28: Timeout
```

**Solution:**
- Increase timeout in `AIClient::callOpenAI()` (default: 30s)
- Check firewall/proxy settings
- Verify API key validity

### Rate Limit Exceeded

```
[SAGA][AI] Rate limit exceeded for consistency checks
```

**Solution:**
- Wait for rate limit reset (1 hour)
- Increase `RATE_LIMIT` constant in `AIClient.php`
- Use rule-based checks only

### Database Migration Fails

```
[SAGA][AI][ERROR] Failed to create consistency issues table
```

**Solution:**
- Check database permissions
- Verify `wp_saga_sagas` and `wp_saga_entities` tables exist
- Run `saga_ai_verify_table_integrity()` for diagnostics

### Invalid API Response

```
[SAGA][AI][ERROR] Invalid OpenAI API response
```

**Solution:**
- Check API quota/billing
- Verify model availability (gpt-4-turbo-preview)
- Switch to Anthropic fallback provider

## API Reference

### ConsistencyIssue

```php
readonly class ConsistencyIssue {
    public ?int $id;
    public int $sagaId;
    public string $issueType;        // timeline|character|location|relationship|logical
    public string $severity;         // critical|high|medium|low|info
    public ?int $entityId;
    public ?int $relatedEntityId;
    public string $description;
    public array $context;
    public ?string $suggestedFix;
    public string $status;           // open|resolved|dismissed|false_positive
    public string $detectedAt;
    public ?string $resolvedAt;
    public ?int $resolvedBy;
    public ?float $aiConfidence;     // 0.00-1.00

    public function resolve(int $userId): self;
    public function dismiss(int $userId, bool $isFalsePositive = false): self;
    public function isOpen(): bool;
    public function isResolved(): bool;
    public function getSeverityLabel(): string;
    public function getIssueTypeLabel(): string;
}
```

### ConsistencyAnalyzer

```php
class ConsistencyAnalyzer {
    public function analyze(
        int $sagaId,
        array $options = [],
        bool $useAI = true,
        array $ruleTypes = []
    ): array;

    public function getIssues(int $sagaId, string $status = 'open'): array;
    public function resolveIssue(int $issueId, int $userId): bool;
    public function dismissIssue(int $issueId, int $userId, bool $isFalsePositive = false): bool;
    public function getStatistics(int $sagaId): array;
}
```

### ConsistencyRepository

```php
class ConsistencyRepository {
    public function save(ConsistencyIssue $issue): int|false;
    public function update(ConsistencyIssue $issue): bool;
    public function findById(int $issueId): ?ConsistencyIssue;
    public function findBySaga(int $sagaId, string $status = '', int $limit = 100, int $offset = 0): array;
    public function findByEntity(int $entityId, string $status = ''): array;
    public function delete(int $issueId): bool;
    public function deleteBySaga(int $sagaId): bool;
    public function getStatistics(int $sagaId): array;
    public function getIssuesByType(int $sagaId, string $status = 'open'): array;
    public function countByStatus(int $sagaId, string $status): int;
}
```

## WordPress Integration

### Filters

```php
// Modify AI context before analysis
add_filter('saga_ai_analysis_context', function($context, $sagaId) {
    $context['custom_data'] = get_saga_custom_data($sagaId);
    return $context;
}, 10, 2);

// Modify detected issues before saving
add_filter('saga_ai_detected_issues', function($issues, $sagaId) {
    // Filter out low-priority issues
    return array_filter($issues, fn($issue) => $issue->severity !== 'info');
}, 10, 2);
```

### Actions

```php
// After analysis completes
add_action('saga_ai_analysis_complete', function($sagaId, $issueCount) {
    error_log("Saga {$sagaId}: {$issueCount} issues found");
}, 10, 2);

// After issue resolution
add_action('saga_ai_issue_resolved', function($issueId, $userId) {
    // Send notification, update stats, etc.
}, 10, 2);
```

## Future Enhancements

- [ ] Frontend dashboard widget
- [ ] AJAX handlers for real-time analysis
- [ ] Batch processing for large sagas
- [ ] Export issues to CSV/JSON
- [ ] Custom rule definitions
- [ ] Machine learning for false positive detection
- [ ] Integration with block editor
- [ ] Slack/email notifications
- [ ] Multi-saga analysis
- [ ] Historical issue tracking

## License

Part of Saga Manager Theme - GPL v2 or later

## Support

For issues and questions:
- GitHub Issues: [saga-manager-theme/issues]
- Documentation: [saga-manager-theme/wiki]
