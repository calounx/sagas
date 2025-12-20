# Fictional Universe Saga Manager - CLAUDE.md

## Project Overview

Multi-tenant saga management system for complex fictional universes. Hybrid EAV architecture optimized for flexible entity modeling with relational integrity. **WordPress-native design** with proper table prefix handling.

**Target Scale:** 100K+ entities per saga, sub-50ms query response, semantic search on 1M+ text fragments.

## Technical Stack

- **Runtime:** PHP 8.2+ (strict types, attributes, readonly props)
- **Database:** MariaDB 11.4.8 (vectors via UDF, JSON functions, window functions)
- **Framework:** WordPress 6.0+ (native tables with wp_ prefix)
- **API:** WordPress REST API + Slim Framework 4.x for complex operations
- **Cache:** Redis 7+ (semantic embeddings, query cache)
- **Search:** Hybrid approach - MariaDB full-text + vector similarity

## Claude Code Behavior Guidelines

### Response Format
1. **Code First**: Always provide runnable code snippets
2. **Concise Explanations**: Max 2-3 lines per code block
3. **No Preambles**: Skip "Here's how..." - just deliver
4. **Highlight Risks**: Flag security/performance issues immediately

### Pre-Submission Checklist
- [ ] WordPress prefix correctly used ($wpdb->prefix)
- [ ] SQL injection prevention (wpdb->prepare)
- [ ] Type safety (PHP 8.2 strict types)
- [ ] Error handling implemented
- [ ] Security: capability checks + nonce verification

### When Uncertain
- Ask specific technical questions
- Propose 2-3 alternatives with tradeoffs
- Never assume WordPress conventions without checking

### Agent Delegation
**Use specialized agents proactively:**
- `php-developer` → Design patterns, SOLID, type safety, refactoring
- `wordpress-developer` → WP standards, security, hooks, APIs
- `backend-architect` → System design, scaling, API contracts
- `frontend-developer` → Next.js, React, shadcn/ui (if frontend needed)
- `ui-ux-designer` → User flows, accessibility, design systems

## Architecture Principles

### Hexagonal Architecture Layers

```
Domain Core (entities, value objects, ports)
  ↓
Application Services (use cases, orchestration)
  ↓
Infrastructure (MariaDB repos, WordPress adapters)
  ↓
Presentation (WP plugin, REST endpoints)
```

### WordPress Integration Strategy

**CRITICAL:** All tables use WordPress table prefix (`wp_` by default, configurable via `$wpdb->prefix`).

**Database layer abstractions:**
- Never hardcode table names
- Use `$wpdb->prefix . 'saga_entities'` pattern
- Support multisite installations (`$wpdb->base_prefix`)

### Hybrid EAV Design

**Problem with pure EAV:** Performance degradation, JOIN hell, type safety loss.

**Solution:** Hybrid model
- **Core table:** Fixed high-frequency columns (id, type, created_at, importance_score)
- **EAV tables:** Dynamic attributes per entity type
- **Materialized views:** Denormalized frequent queries
- **JSON columns:** Nested/rare attributes

## Database Schema with WordPress Prefix

### Table Naming Convention

```
{$wpdb->prefix}saga_sagas
{$wpdb->prefix}saga_entities
{$wpdb->prefix}saga_attribute_definitions
{$wpdb->prefix}saga_attribute_values
{$wpdb->prefix}saga_entity_relationships
{$wpdb->prefix}saga_timeline_events
{$wpdb->prefix}saga_content_fragments
{$wpdb->prefix}saga_quality_metrics
```

**Note:** `saga_` prefix distinguishes from core WordPress tables.

### Core Schema DDL

```sql
-- Sagas table (multi-tenant)
CREATE TABLE IF NOT EXISTS {PREFIX}saga_sagas (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    universe VARCHAR(100) NOT NULL COMMENT 'e.g. Dune, Star Wars, LOTR',
    calendar_type ENUM('absolute','epoch_relative','age_based') NOT NULL,
    calendar_config JSON NOT NULL COMMENT 'Epoch dates, age definitions',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uk_name (name),
    INDEX idx_universe (universe)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Core entity table
CREATE TABLE IF NOT EXISTS {PREFIX}saga_entities (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    saga_id INT UNSIGNED NOT NULL,
    entity_type ENUM('character','location','event','faction','artifact','concept') NOT NULL,
    canonical_name VARCHAR(255) NOT NULL,
    slug VARCHAR(255) NOT NULL,
    importance_score TINYINT UNSIGNED DEFAULT 50 COMMENT '0-100 scale',
    embedding_hash CHAR(64) COMMENT 'SHA256 of embedding for duplicate detection',
    wp_post_id BIGINT UNSIGNED COMMENT 'Link to wp_posts for display',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (saga_id) REFERENCES {PREFIX}saga_sagas(id) ON DELETE CASCADE,
    INDEX idx_saga_type (saga_id, entity_type),
    INDEX idx_importance (importance_score DESC),
    INDEX idx_embedding (embedding_hash),
    INDEX idx_slug (slug),
    INDEX idx_wp_post (wp_post_id),
    UNIQUE KEY uk_saga_name (saga_id, canonical_name)
) ENGINE=InnoDB ROW_FORMAT=COMPRESSED DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Attribute definitions (schema for EAV)
CREATE TABLE IF NOT EXISTS {PREFIX}saga_attribute_definitions (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    entity_type ENUM('character','location','event','faction','artifact','concept') NOT NULL,
    attribute_key VARCHAR(100) NOT NULL,
    display_name VARCHAR(150) NOT NULL,
    data_type ENUM('string','int','float','bool','date','text','json') NOT NULL,
    is_searchable BOOLEAN DEFAULT FALSE,
    is_required BOOLEAN DEFAULT FALSE,
    validation_rule JSON COMMENT 'regex, min, max, enum',
    default_value VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uk_type_key (entity_type, attribute_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- EAV attribute values
CREATE TABLE IF NOT EXISTS {PREFIX}saga_attribute_values (
    entity_id BIGINT UNSIGNED NOT NULL,
    attribute_id INT UNSIGNED NOT NULL,
    value_string VARCHAR(500),
    value_int BIGINT,
    value_float DOUBLE,
    value_bool BOOLEAN,
    value_date DATE,
    value_text TEXT,
    value_json JSON,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (entity_id, attribute_id),
    FOREIGN KEY (entity_id) REFERENCES {PREFIX}saga_entities(id) ON DELETE CASCADE,
    FOREIGN KEY (attribute_id) REFERENCES {PREFIX}saga_attribute_definitions(id) ON DELETE CASCADE,
    INDEX idx_searchable_string (attribute_id, value_string(100)),
    INDEX idx_searchable_int (attribute_id, value_int),
    INDEX idx_searchable_date (attribute_id, value_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Relationships (typed, weighted, temporal)
CREATE TABLE IF NOT EXISTS {PREFIX}saga_entity_relationships (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    source_entity_id BIGINT UNSIGNED NOT NULL,
    target_entity_id BIGINT UNSIGNED NOT NULL,
    relationship_type VARCHAR(50) NOT NULL,
    strength TINYINT UNSIGNED DEFAULT 50 COMMENT '0-100 relationship strength',
    valid_from DATE,
    valid_until DATE,
    metadata JSON,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (source_entity_id) REFERENCES {PREFIX}saga_entities(id) ON DELETE CASCADE,
    FOREIGN KEY (target_entity_id) REFERENCES {PREFIX}saga_entities(id) ON DELETE CASCADE,
    INDEX idx_source_type (source_entity_id, relationship_type),
    INDEX idx_target (target_entity_id),
    INDEX idx_temporal (valid_from, valid_until),
    CONSTRAINT chk_no_self_ref CHECK (source_entity_id != target_entity_id),
    CONSTRAINT chk_valid_dates CHECK (valid_until IS NULL OR valid_until >= valid_from)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Timeline events
CREATE TABLE IF NOT EXISTS {PREFIX}saga_timeline_events (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    saga_id INT UNSIGNED NOT NULL,
    event_entity_id BIGINT UNSIGNED,
    canon_date VARCHAR(100) NOT NULL COMMENT 'Original saga date format',
    normalized_timestamp BIGINT NOT NULL COMMENT 'Unix-like timestamp for sorting',
    title VARCHAR(255) NOT NULL,
    description TEXT,
    participants JSON COMMENT 'Array of entity IDs',
    locations JSON COMMENT 'Array of location entity IDs',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (saga_id) REFERENCES {PREFIX}saga_sagas(id) ON DELETE CASCADE,
    FOREIGN KEY (event_entity_id) REFERENCES {PREFIX}saga_entities(id) ON DELETE SET NULL,
    INDEX idx_saga_time (saga_id, normalized_timestamp),
    INDEX idx_canon_date (canon_date(50))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Content fragments (for semantic search)
CREATE TABLE IF NOT EXISTS {PREFIX}saga_content_fragments (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    entity_id BIGINT UNSIGNED NOT NULL,
    fragment_text TEXT NOT NULL,
    embedding BLOB COMMENT 'Vector embedding (384-dim float32)',
    token_count SMALLINT UNSIGNED,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (entity_id) REFERENCES {PREFIX}saga_entities(id) ON DELETE CASCADE,
    FULLTEXT INDEX ft_fragment (fragment_text)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Quality metrics
CREATE TABLE IF NOT EXISTS {PREFIX}saga_quality_metrics (
    entity_id BIGINT UNSIGNED PRIMARY KEY,
    completeness_score TINYINT UNSIGNED DEFAULT 0 COMMENT 'Percentage of required attrs',
    consistency_score TINYINT UNSIGNED DEFAULT 100 COMMENT 'Cross-ref validation score',
    last_verified TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    issues JSON COMMENT 'Array of issue codes',
    FOREIGN KEY (entity_id) REFERENCES {PREFIX}saga_entities(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

## WordPress Integration

### Database Abstraction Layer

**CRITICAL:** Use WordPress $wpdb global for all queries.

```php
global $wpdb;

class WordPressTablePrefixAware
{
    protected string $prefix;
    
    public function __construct()
    {
        global $wpdb;
        $this->prefix = $wpdb->prefix . 'saga_';
    }
    
    protected function getTableName(string $table): string
    {
        return $this->prefix . $table;
    }
}

// Usage in repositories
class MariaDBEntityRepository extends WordPressTablePrefixAware
{
    public function findById(EntityId $id): ?SagaEntity
    {
        global $wpdb;
        
        $table = $this->getTableName('entities');
        
        $query = $wpdb->prepare(
            "SELECT * FROM {$table} WHERE id = %d",
            $id->value()
        );
        
        $row = $wpdb->get_row($query, ARRAY_A);
        
        if (!$row) {
            return null;
        }
        
        return $this->hydrate($row);
    }
}
```

### Plugin Structure

```
saga-manager/
├── saga-manager.php           # Main plugin file
├── composer.json
├── src/
│   ├── Domain/
│   │   ├── Entity/
│   │   │   ├── SagaEntity.php
│   │   │   ├── EntityId.php
│   │   │   └── ImportanceScore.php
│   │   ├── Repository/
│   │   │   └── EntityRepositoryInterface.php
│   │   └── Exception/
│   │       └── DomainException.php
│   ├── Application/
│   │   ├── UseCase/
│   │   │   ├── CreateEntity/
│   │   │   │   ├── CreateEntityCommand.php
│   │   │   │   └── CreateEntityHandler.php
│   │   │   └── SearchEntities/
│   │   └── Service/
│   ├── Infrastructure/
│   │   ├── Repository/
│   │   │   └── MariaDBEntityRepository.php
│   │   ├── WordPress/
│   │   │   ├── CustomPostType.php
│   │   │   └── RestController.php
│   │   └── Cache/
│   │       └── RedisCacheAdapter.php
│   └── Presentation/
│       ├── Admin/
│       └── Shortcode/
└── tests/
    ├── Unit/
    └── Integration/
```

### REST API Endpoints

```php
// Register custom REST routes
add_action('rest_api_init', function() {
    register_rest_route('saga/v1', '/entities', [
        'methods' => 'GET',
        'callback' => [EntityController::class, 'index'],
        'permission_callback' => function() {
            return current_user_can('read');
        }
    ]);
    
    register_rest_route('saga/v1', '/entities/(?P<id>\d+)', [
        'methods' => 'GET',
        'callback' => [EntityController::class, 'show'],
        'permission_callback' => function() {
            return current_user_can('read');
        }
    ]);
    
    register_rest_route('saga/v1', '/entities', [
        'methods' => 'POST',
        'callback' => [EntityController::class, 'create'],
        'permission_callback' => function() {
            return current_user_can('edit_posts');
        }
    ]);
});
```

### Custom Post Type Integration

```php
class SagaEntityPostType
{
    public function register(): void
    {
        register_post_type('saga_entity', [
            'labels' => [
                'name' => 'Saga Entities',
                'singular_name' => 'Saga Entity',
            ],
            'public' => true,
            'has_archive' => true,
            'show_in_rest' => true,
            'supports' => ['title', 'editor', 'custom-fields'],
            'taxonomies' => ['saga_type'],
        ]);
    }
    
    public function syncToDatabase(int $post_id): void
    {
        global $wpdb;
        
        $post = get_post($post_id);
        if (!$post || $post->post_type !== 'saga_entity') {
            return;
        }
        
        $table = $wpdb->prefix . 'saga_entities';
        
        // Check for existing entity
        $entity = $wpdb->get_row($wpdb->prepare(
            "SELECT id FROM {$table} WHERE wp_post_id = %d",
            $post_id
        ));
        
        $data = [
            'canonical_name' => $post->post_title,
            'slug' => $post->post_name,
            'updated_at' => current_time('mysql'),
        ];
        
        if ($entity) {
            $wpdb->update($table, $data, ['id' => $entity->id]);
        } else {
            $data['wp_post_id'] = $post_id;
            $wpdb->insert($table, $data);
        }
    }
}
```

## Error Handling Standards

### Exception Hierarchy

```php
namespace SagaManager\Domain\Exception;

// Base exception
abstract class SagaException extends \Exception {}

// Domain exceptions
class EntityNotFoundException extends SagaException {}
class ValidationException extends SagaException {}
class DuplicateEntityException extends SagaException {}
class InvalidImportanceScoreException extends SagaException {}
class RelationshipConstraintException extends SagaException {}

// Infrastructure exceptions
class DatabaseException extends SagaException {}
class EmbeddingServiceException extends SagaException {}
class CacheException extends SagaException {}
```

### WordPress Error Integration

```php
// Convert domain exceptions to WP_Error
try {
    $entity = $repository->findById($id);
} catch (EntityNotFoundException $e) {
    return new WP_Error('entity_not_found', $e->getMessage(), ['id' => $id]);
} catch (DatabaseException $e) {
    error_log('Saga DB Error: ' . $e->getMessage());
    return new WP_Error('database_error', 'Internal error', ['status' => 500]);
}
```

### Critical Operations Pattern

**MUST have error handling:**
- Database writes (transactions with rollback)
- External API calls (embedding service)
- File operations
- wp_posts sync operations

**Pattern:**
```php
global $wpdb;
$wpdb->query('START TRANSACTION');

try {
    // Create entity
    $wpdb->insert($wpdb->prefix . 'saga_entities', $entity_data);
    $entity_id = $wpdb->insert_id;
    
    // Create attributes
    foreach ($attributes as $attr) {
        $wpdb->insert($wpdb->prefix . 'saga_attribute_values', [
            'entity_id' => $entity_id,
            'attribute_id' => $attr['id'],
            'value_string' => $attr['value'],
        ]);
    }
    
    $wpdb->query('COMMIT');
    
    return $entity_id;
    
} catch (\Exception $e) {
    $wpdb->query('ROLLBACK');
    error_log('[SAGA][ERROR] Entity creation failed: ' . $e->getMessage());
    throw new DatabaseException('Transaction failed: ' . $e->getMessage(), 0, $e);
}
```

## Logging & Observability

### Logging Levels (WordPress compatible)

```php
// Use WordPress debug functions
if (WP_DEBUG) {
    error_log('[SAGA][DEBUG] Entity created: ' . $entity_id);
}

// Production errors only
error_log('[SAGA][ERROR] Database query failed: ' . $wpdb->last_error);

// Critical: always log
error_log('[SAGA][CRITICAL] Embedding service unavailable');
```

### What to Log

**ALWAYS:**
- Database errors (with sanitized queries)
- External API failures (embedding service)
- Transaction rollbacks
- Security violations (capability checks failed)

**NEVER:**
- Sensitive data (passwords, tokens)
- Full SQL queries in production
- User PII without consent

### Performance Monitoring

**Critical Metrics:**
```php
// Query performance tracking
$start = microtime(true);
$result = $wpdb->get_results($query);
$duration = (microtime(true) - $start) * 1000;

if ($duration > 50) { // Target: sub-50ms
    error_log("[SAGA][PERF] Slow query ({$duration}ms): " . 
              substr($query, 0, 100));
}
```

**Track:**
- Query execution time (target: <50ms)
- Cache hit rate (wp_cache_get)
- EAV JOIN complexity (warn if >3 joins)
- Embedding generation latency

### WordPress Integration

```php
// Use WordPress transients for metrics
function saga_track_metric($key, $value) {
    $metrics = get_transient('saga_metrics_hourly') ?: [];
    $metrics[$key] = ($metrics[$key] ?? 0) + $value;
    set_transient('saga_metrics_hourly', $metrics, HOUR_IN_SECONDS);
}

// Example usage
saga_track_metric('queries_total', 1);
saga_track_metric('cache_hits', $hit ? 1 : 0);
```

## Testing Standards

### Test Strategy

**Unit Tests (Domain Layer)**
- All value objects (EntityId, SagaId, ImportanceScore)
- Business logic validation
- No database/WordPress dependencies
- Target: 90%+ coverage

**Integration Tests (Infrastructure Layer)**
```php
// Use WordPress test framework
class EntityRepositoryTest extends WP_UnitTestCase {
    private $repository;
    
    public function setUp(): void {
        parent::setUp();
        global $wpdb;
        
        // Tables created automatically by WordPress test suite
        $this->repository = new MariaDBEntityRepository();
    }
    
    public function test_find_by_id_with_wordpress_prefix() {
        global $wpdb;
        
        // Insert test data with correct prefix
        $table = $wpdb->prefix . 'saga_entities';
        $wpdb->insert($table, [
            'saga_id' => 1,
            'entity_type' => 'character',
            'canonical_name' => 'Luke Skywalker',
            'slug' => 'luke-skywalker',
        ]);
        
        $entity = $this->repository->findById(
            new EntityId($wpdb->insert_id)
        );
        
        $this->assertNotNull($entity);
        $this->assertEquals('Luke Skywalker', $entity->getName());
    }
    
    public function test_transaction_rollback_on_error() {
        global $wpdb;
        
        $wpdb->query('START TRANSACTION');
        
        try {
            // Force duplicate key error
            $this->repository->create($duplicate_entity);
            $this->fail('Should have thrown exception');
        } catch (DuplicateEntityException $e) {
            $wpdb->query('ROLLBACK');
        }
        
        // Verify rollback worked
        $count = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}saga_entities");
        $this->assertEquals(0, $count);
    }
}
```

### Required Test Fixtures

**Minimal Test Dataset:**
- 1 saga (Star Wars)
- 5 entities (2 characters, 1 location, 1 event, 1 faction)
- 3 relationships
- 2 timeline events

**Fixture Loading:**
```php
class SagaTestFixtures {
    public static function loadStarWars(): int {
        global $wpdb;
        
        // Insert saga
        $wpdb->insert($wpdb->prefix . 'saga_sagas', [
            'name' => 'Star Wars Test',
            'universe' => 'Star Wars',
            'calendar_type' => 'epoch_relative',
            'calendar_config' => json_encode(['epoch' => 'BBY']),
        ]);
        
        return $wpdb->insert_id;
    }
}
```

### Test Coverage Requirements

**Minimum Coverage:**
- Critical paths: 100% (entity CRUD, relationships)
- WordPress integration: 90% (prefix handling, $wpdb usage)
- Error handling: 80% (exception paths)
- Overall: 70%+

**Critical Test Cases:**
- [ ] Table prefix handling (wp_, custom prefixes)
- [ ] Multisite compatibility ($wpdb->base_prefix)
- [ ] SQL injection prevention (wpdb->prepare)
- [ ] Transaction rollback
- [ ] Cache invalidation
- [ ] wp_posts sync (bidirectional)

### Running Tests

```bash
# Install WordPress test suite
./bin/install-wp-tests.sh wordpress_test root '' localhost latest

# Run tests
phpunit

# With coverage
phpunit --coverage-html coverage/
```

## Code Quality Checklist

### Before Committing Code

**Security (NON-NÉGOCIABLE)**
- [ ] All database queries use `$wpdb->prepare()`
- [ ] User input sanitized (`sanitize_text_field`, `absint`, etc.)
- [ ] Capability checks on admin actions (`current_user_can`)
- [ ] Nonce verification on forms (`wp_verify_nonce`)
- [ ] No hardcoded credentials or API keys

**WordPress Compliance**
- [ ] Table names use `$wpdb->prefix . 'saga_*'`
- [ ] Never hardcode 'wp_' prefix
- [ ] Object cache used for frequently accessed data
- [ ] Hooks/filters use proper priority
- [ ] Follows WordPress coding standards (PHPCS)

**PHP 8.2 Standards**
- [ ] Strict types declared (`declare(strict_types=1);`)
- [ ] Type hints on all parameters and returns
- [ ] Readonly properties where applicable
- [ ] Attributes used for metadata
- [ ] No deprecated functions

**Architecture**
- [ ] Hexagonal architecture respected (no domain → infrastructure deps)
- [ ] Repository pattern for data access
- [ ] Value objects for domain primitives
- [ ] SOLID principles followed
- [ ] No business logic in controllers

**Performance**
- [ ] Queries indexed (check EXPLAIN)
- [ ] N+1 queries avoided
- [ ] Cache strategy implemented
- [ ] Target <50ms query time verified
- [ ] Joins limited to 3 max on EAV tables

**Error Handling**
- [ ] Try-catch on all external calls
- [ ] Transactions with rollback on failures
- [ ] Domain exceptions properly mapped to WP_Error
- [ ] Critical errors logged
- [ ] User-friendly error messages

**Testing**
- [ ] Unit tests for domain logic
- [ ] Integration tests for repositories
- [ ] Edge cases covered (null, empty, duplicates)
- [ ] Multisite compatibility tested
- [ ] Different table prefixes tested

**Documentation**
- [ ] PHPDoc on all public methods
- [ ] Complex algorithms explained
- [ ] Security considerations noted
- [ ] Performance implications documented

### Pre-PR Review Questions

**For Claude Code to ask itself:**
1. Can this code handle 100K+ entities without degradation?
2. Will this work with a custom table prefix like `mysite_`?
3. What happens if the embedding service is down?
4. Is there any SQL injection vector?
5. Does this respect WordPress transaction isolation?

**Red Flags (STOP and refactor):**
- More than 3 JOINs on EAV tables
- Hardcoded 'wp_' anywhere
- Missing $wpdb->prepare on user input
- Direct $_GET/$_POST access without sanitization
- Domain code importing WordPress functions

## Performance Optimization

### Database Indexes

```sql
-- Additional covering indexes for WordPress integration
CREATE INDEX idx_wp_sync ON {PREFIX}saga_entities(wp_post_id, updated_at);
CREATE INDEX idx_search_cover ON {PREFIX}saga_entities(saga_id, entity_type, importance_score);
```

### Object Cache Integration

```php
// Use WordPress object cache
$entity_id = 123;
$cache_key = "saga_entity_{$entity_id}";

$entity = wp_cache_get($cache_key, 'saga');

if (false === $entity) {
    global $wpdb;
    $table = $wpdb->prefix . 'saga_entities';
    $entity = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM {$table} WHERE id = %d",
        $entity_id
    ));
    
    wp_cache_set($cache_key, $entity, 'saga', 300); // 5 min TTL
}
```

### WordPress Cron for Background Processing

```php
// Register cron job for quality analysis
add_action('wp', function() {
    if (!wp_next_scheduled('saga_daily_quality_check')) {
        wp_schedule_event(time(), 'daily', 'saga_daily_quality_check');
    }
});

add_action('saga_daily_quality_check', function() {
    global $wpdb;
    $table = $wpdb->prefix . 'saga_entities';
    
    // Get all entities that need quality check
    $entities = $wpdb->get_col("
        SELECT id FROM {$table} 
        WHERE id NOT IN (
            SELECT entity_id FROM {$wpdb->prefix}saga_quality_metrics
            WHERE last_verified > DATE_SUB(NOW(), INTERVAL 7 DAY)
        )
        LIMIT 100
    ");
    
    foreach ($entities as $entity_id) {
        $wpdb->query($wpdb->prepare(
            "CALL update_quality_metrics(%d)",
            $entity_id
        ));
    }
});
```

## Security Best Practices

### Input Sanitization

```php
// Always sanitize user input
$saga_id = absint($_GET['saga_id'] ?? 1);
$query = sanitize_text_field($_GET['q'] ?? '');
$type = sanitize_key($_GET['type'] ?? '');

// Use wpdb->prepare for all queries
global $wpdb;
$results = $wpdb->get_results($wpdb->prepare(
    "SELECT * FROM {$wpdb->prefix}saga_entities WHERE saga_id = %d",
    $saga_id
));
```

### Capability Checks

```php
// Restrict admin functions
if (!current_user_can('manage_options')) {
    wp_die('Unauthorized');
}

// Check nonces for forms
if (!wp_verify_nonce($_POST['saga_nonce'], 'saga_action')) {
    wp_die('Invalid nonce');
}
```

### Rate Limiting

```php
// Use transients for rate limiting
function saga_check_rate_limit($user_id, $action) {
    $key = "saga_rate_{$action}_{$user_id}";
    $count = get_transient($key);
    
    if ($count === false) {
        set_transient($key, 1, MINUTE_IN_SECONDS);
        return true;
    }
    
    if ($count >= 10) { // 10 requests per minute
        return false;
    }
    
    set_transient($key, $count + 1, MINUTE_IN_SECONDS);
    return true;
}
```

## Saga Manager Specific Pitfalls

> **Delegation:** For general anti-patterns, use these agents:
> - `php-developer` → SOLID violations, design patterns, type safety
> - `wordpress-developer` → Security, performance, WP standards
> - `backend-architect` → API design, scaling, database optimization

### EAV Query Performance Traps

**❌ JOIN Explosion on Attributes**
```php
// WRONG - 10+ JOINs for character with many attributes
SELECT e.*, av1.value_string as name, av2.value_int as age, ...
FROM saga_entities e
LEFT JOIN saga_attribute_values av1 ON ...
LEFT JOIN saga_attribute_values av2 ON ...
-- ❌ Degrades at 5+ attributes

// CORRECT - Two queries with bulk hydration
// 1. Get entities: SELECT * FROM saga_entities WHERE saga_id = ?
// 2. Get attributes: SELECT * FROM saga_attribute_values WHERE entity_id IN (...)
```

**❌ Missing entity_type Filter on EAV Queries**
```php
// WRONG - Scans all types
SELECT * FROM saga_attribute_values WHERE value_string LIKE '%Skywalker%';

// CORRECT
SELECT av.* FROM saga_attribute_values av
JOIN saga_entities e ON av.entity_id = e.id
WHERE e.entity_type = 'character' AND e.saga_id = ? -- ✓ Filter first
```

### Relationship Graph Pitfalls

**❌ Unbounded Recursive Traversal**
```php
// WRONG - Can cause infinite loops in circular relationships
function get_all_allies($entity_id) {
    foreach (get_relationships($entity_id) as $rel) {
        get_all_allies($rel->target_id); // ❌ No depth limit
    }
}

// CORRECT - Max depth + visited tracking
function get_allies($entity_id, $max_depth = 3, &$visited = []) {
    if ($max_depth === 0 || isset($visited[$entity_id])) return;
    $visited[$entity_id] = true;
    // ...
}
```

### Timeline Normalization Issues

**❌ Normalizing Dates in Application Layer**
```php
// WRONG - PHP converting "10,191 AG" to timestamp
$timestamp = convert_saga_date($_POST['canon_date']); // ❌ Business logic
$wpdb->insert('saga_timeline_events', ['normalized_timestamp' => $timestamp]);

// CORRECT - Use DB trigger or stored procedure
-- CREATE TRIGGER before_timeline_insert
-- SET NEW.normalized_timestamp = normalize_saga_date(NEW.canon_date, saga_calendar);
```

### wp_posts Sync Race Conditions

**❌ No Conflict Detection on Bidirectional Sync**
```php
// WRONG - Race between wp_posts update and saga_entities update
add_action('save_post', function($post_id) {
    $wpdb->update('saga_entities', 
        ['canonical_name' => get_the_title($post_id)],
        ['wp_post_id' => $post_id]
    ); // ❌ Overwrites without checking
});

// CORRECT - Timestamp-based conflict detection
if ($entity->updated_at > get_post_modified_time('U', false, $post_id)) {
    error_log("Sync conflict: entity newer than post");
    // Merge strategy or manual resolution
}
```

### Hexagonal Boundary Violations

**❌ Domain Importing WordPress/Infrastructure**
```php
// WRONG
namespace SagaManager\Domain\Entity;
use function wp_cache_get; // ❌ Infrastructure leak into domain

// CORRECT - Domain stays pure, caching in repository layer
namespace SagaManager\Infrastructure;
class CachedEntityRepository { /* wp_cache here */ }
```

### Critical Red Flags for Code Review

**STOP if you see:**
- `saga_entities` query without `saga_id` filter (full table scan)
- More than 3 JOINs on `saga_attribute_values`
- Recursive relationship query without max depth
- Timeline query without date range (`normalized_timestamp BETWEEN`)
- `importance_score` outside 0-100 range without validation
- Calendar date conversion in PHP (should be DB-side)
- Domain entity with `global $wpdb` or WordPress functions
- Bidirectional sync without conflict resolution strategy

## Implementation Priorities & Dependencies

### Phase 1: Foundation (MVP - REQUIRED)

**1.1 Database Layer** ⚠️ BLOCKING for all other work
```php
// Must be completed first
- [ ] WordPressTablePrefixAware base class
- [ ] Database schema creation (dbDelta)
- [ ] Migration/rollback system
- [ ] Multisite compatibility verification
```
**Dependencies:** None  
**Validation:** Tables created with correct prefix, verified on multisite

**1.2 Core Domain Models** ⚠️ BLOCKING for repositories
```php
- [ ] Entity value objects (EntityId, SagaId)
- [ ] Domain entities (SagaEntity, Relationship)
- [ ] Repository interfaces (ports)
- [ ] Domain exceptions
```
**Dependencies:** None (pure PHP, no WordPress deps)  
**Validation:** Unit tests at 90%+ coverage

**1.3 Repository Implementation** ⚠️ BLOCKING for API
```php
- [ ] MariaDBEntityRepository
- [ ] MariaDBRelationshipRepository
- [ ] Transaction handling
- [ ] Cache integration (wp_cache)
```
**Dependencies:** 1.1, 1.2  
**Validation:** Integration tests pass, queries <50ms

### Phase 2: WordPress Integration (REQUIRED)

**2.1 Plugin Skeleton**
```php
- [ ] Activation/deactivation hooks
- [ ] Uninstall cleanup
- [ ] Admin menu structure
- [ ] Settings page
```
**Dependencies:** 1.1  
**Validation:** Plugin activates without errors, tables cleanup on uninstall

**2.2 Custom Post Type Sync** ⚠️ CRITICAL for content management
```php
- [ ] Bidirectional sync (wp_posts ↔ saga_entities)
- [ ] Meta boxes for entity data
- [ ] Bulk operations
- [ ] Conflict resolution
```
**Dependencies:** 1.3, 2.1  
**Validation:** Create entity → CPT appears, edit CPT → entity updates

### Phase 3: API & Search (REQUIRED)

**3.1 REST API Endpoints**
```php
- [ ] GET /wp-json/saga/v1/entities/{id}
- [ ] POST /wp-json/saga/v1/entities
- [ ] GET /wp-json/saga/v1/search
- [ ] GET /wp-json/saga/v1/relationships
```
**Dependencies:** 1.3, 2.1  
**Validation:** All endpoints return <100ms, proper error codes

**3.2 Semantic Search**
```php
- [ ] Embedding service client
- [ ] Vector similarity UDF
- [ ] Hybrid search (full-text + vector)
- [ ] Cache embeddings in Redis
```
**Dependencies:** 3.1, External embedding service  
**Validation:** Search 1M+ fragments in <200ms

### Phase 4: Frontend (OPTIONAL - Post-MVP)

**4.1 Shortcodes**
```php
- [ ] [saga_entity id="123"]
- [ ] [saga_timeline saga="star-wars"]
- [ ] [saga_search]
```
**Dependencies:** 3.1  
**Validation:** Shortcodes render without errors

**4.2 Admin Dashboard**
```php
- [ ] Quality metrics widget
- [ ] Recent activity log
- [ ] Performance charts
```
**Dependencies:** 2.1, Background cron jobs  
**Validation:** Loads <1s with 10K+ entities

### Phase 5: Production Hardening (REQUIRED before launch)

**5.1 Background Processing**
```php
- [ ] WP-Cron for quality analysis
- [ ] Async embedding generation
- [ ] Batch operations
```
**Dependencies:** 1.3, 3.2  
**Validation:** Handles 1000 entities/hour

**5.2 Security Audit**
```php
- [ ] Penetration testing
- [ ] SQL injection verification
- [ ] XSS prevention validation
- [ ] CSRF token checks
```
**Dependencies:** All previous phases  
**Validation:** OWASP Top 10 compliance

### Critical Path (Minimum Viable Product)

```
1.1 Database → 1.2 Domain → 1.3 Repositories
                              ↓
                          2.1 Plugin Skeleton
                              ↓
                          2.2 CPT Sync
                              ↓
                          3.1 REST API
                              ↓
                          5.2 Security Audit
```

**Total MVP Timeline:** ~4-6 weeks for experienced developer

### Parallel Work Opportunities

- Domain models (1.2) can be developed while database (1.1) is being reviewed
- Frontend (4.x) can start once API (3.1) is stable
- Documentation can be written alongside any phase

## Critical Implementation Notes

### Table Prefix Handling

**DO:**
- Always use `$wpdb->prefix . 'saga_*'`
- Test on multisite installations
- Use `dbDelta()` for table creation
- Support custom charset/collate

**DON'T:**
- Hardcode 'wp_' prefix
- Forget foreign key constraints
- Skip index creation
- Ignore WordPress coding standards

### Embedding Service Integration

**Required:** Deploy sentence-transformers API separately

```bash
# Install dependencies
pip install sentence-transformers fastapi uvicorn

# Create embedding server
# server.py
from fastapi import FastAPI
from sentence_transformers import SentenceTransformer

app = FastAPI()
model = SentenceTransformer('all-MiniLM-L6-v2')

@app.post("/embed")
async def embed(texts: list[str]):
    embeddings = model.encode(texts)
    return {"embeddings": embeddings.tolist()}

# Run server
uvicorn server:app --host 0.0.0.0 --port 8000
```

**WordPress Integration:**

```php
function saga_generate_embedding($text) {
    $api_url = SAGA_EMBEDDING_API_URL;
    
    $response = wp_remote_post($api_url, [
        'body' => json_encode(['texts' => [$text]]),
        'headers' => ['Content-Type' => 'application/json'],
        'timeout' => 10,
    ]);
    
    if (is_wp_error($response)) {
        error_log('Embedding API error: ' . $response->get_error_message());
        return null;
    }
    
    $data = json_decode(wp_remote_retrieve_body($response), true);
    return $data['embeddings'][0] ?? null;
}
```

## Known Limitations

1. **Vector Similarity:** Requires external UDF or Redis module
2. **Embedding Service:** Needs separate deployment
3. **Multisite:** Limited testing on WordPress multisite
4. **Scale:** EAV queries degrade >1M entities without partitioning
5. **Real-time Sync:** wp_posts ↔ saga_entities may lag
