# Saga Manager Theme - Architecture Documentation

## Overview

This GeneratePress child theme follows enterprise-grade PHP architecture principles with strict type safety, dependency injection, and SOLID design patterns.

## Core Principles

### 1. SOLID Principles

**Single Responsibility Principle (SRP)**
- `SagaQueries`: Database queries only
- `SagaHelpers`: Formatting and display utilities
- `SagaHooks`: WordPress/GeneratePress hook management
- `SagaAjaxHandler`: AJAX endpoint handling
- `SagaCache`: Caching layer
- `SagaTheme`: Orchestration and initialization

**Open/Closed Principle (OCP)**
- Classes open for extension via inheritance
- Closed for modification through final methods where appropriate
- Extension points via WordPress hooks

**Liskov Substitution Principle (LSP)**
- All type hints can accept their declared types or subtypes
- No unexpected behavior in subclasses

**Interface Segregation Principle (ISP)**
- Small, focused public APIs
- No forced implementation of unused methods

**Dependency Inversion Principle (DIP)**
- Dependencies injected via constructor
- High-level modules don't depend on low-level modules
- Both depend on abstractions (interfaces would be next step)

### 2. Type Safety

**PHP 8.2+ Features:**
```php
declare(strict_types=1);  // All files

// Strict type hints
public function getEntityByPostId(int $postId): ?object

// Readonly properties (where applicable)
private readonly string $prefix;

// Match expressions
return match ($type) {
    'character' => 'Character',
    'location' => 'Location',
    default => 'Unknown',
};

// Null coalescing assignment
$value ??= $default;
```

### 3. Dependency Injection

**Constructor Injection Pattern:**

```php
class SagaTheme
{
    public function __construct(
        private SagaHelpers $helpers,
        private SagaQueries $queries,
        private SagaHooks $hooks,
        private SagaAjaxHandler $ajaxHandler,
        private SagaCache $cache
    ) {}
}
```

**Benefits:**
- Testable: Can inject mocks for unit testing
- Flexible: Easy to swap implementations
- Explicit: Dependencies clearly declared
- No globals: No hidden dependencies

### 4. Clean Architecture Layers

```
┌─────────────────────────────────────────┐
│   Presentation Layer (functions.php)    │
│   - Template functions                  │
│   - Global helpers                      │
└───────────────┬─────────────────────────┘
                │
┌───────────────▼─────────────────────────┐
│   Application Layer (SagaTheme)         │
│   - Orchestration                       │
│   - Asset management                    │
│   - Hook registration                   │
└───────────────┬─────────────────────────┘
                │
┌───────────────▼─────────────────────────┐
│   Domain Layer (Services)               │
│   - SagaHelpers (business logic)        │
│   - SagaQueries (data access)           │
│   - SagaCache (caching strategy)        │
└───────────────┬─────────────────────────┘
                │
┌───────────────▼─────────────────────────┐
│   Infrastructure Layer                  │
│   - WordPress $wpdb                     │
│   - WordPress object cache              │
│   - Database tables                     │
└─────────────────────────────────────────┘
```

## Class Responsibilities

### SagaTheme (Orchestrator)

**Purpose:** Bootstrap and coordinate all theme components

**Responsibilities:**
- Initialize dependency injection container
- Register WordPress hooks
- Enqueue assets (CSS/JS)
- Add theme support features
- Provide dependency access (getters)

**Dependencies:**
- All service classes (SagaHelpers, SagaQueries, etc.)

**Example:**
```php
$sagaTheme = new SagaTheme($helpers, $queries, $hooks, $ajaxHandler, $cache);
$sagaTheme->init();
```

### SagaQueries (Data Access Layer)

**Purpose:** Encapsulate all database queries

**Responsibilities:**
- Execute type-safe queries via wpdb
- Handle WordPress table prefix
- Implement caching strategy
- Return typed results

**Key Methods:**
- `getEntityByPostId(int $postId): ?object`
- `getEntitiesBySaga(int $sagaId, int $limit, int $offset): array`
- `getRelatedEntities(int $entityId, string $direction, int $limit): array`
- `searchEntities(string $searchTerm, ?int $sagaId, int $limit): array`

**Security:**
- All queries use `$wpdb->prepare()`
- Input sanitization before queries
- No direct SQL concatenation

### SagaHelpers (Business Logic)

**Purpose:** Format data for display and provide utility functions

**Responsibilities:**
- Entity data retrieval
- Formatting functions
- Badge/badge generation
- Relationship grouping
- Type conversions

**Key Methods:**
- `formatImportanceScore(int $score): string`
- `getEntityTypeBadge(string $type): string`
- `getRelationshipStrengthBadge(int $strength): string`
- `groupRelationshipsByType(array $relationships): array`

**Design Pattern:** Service Layer

### SagaHooks (Integration Layer)

**Purpose:** Integrate with WordPress and GeneratePress hooks

**Responsibilities:**
- Register all WordPress/GeneratePress hooks
- Customize layout and display
- Add entity metadata to posts
- Modify archive queries
- Cache invalidation on post update

**Key Hooks:**
- `generate_sidebar_layout` - Customize sidebar
- `generate_after_entry_title` - Add entity meta
- `generate_after_entry_content` - Add relationships
- `save_post` - Invalidate cache

**Design Pattern:** Event-driven architecture

### SagaAjaxHandler (API Layer)

**Purpose:** Handle AJAX requests securely

**Responsibilities:**
- Register AJAX endpoints
- Validate and sanitize input
- Verify nonces for security
- Format JSON responses
- Error handling

**Endpoints:**
- `saga_filter_entities` - Filter entities by criteria
- `saga_search_entities` - Search entities by name
- `saga_get_relationships` - Get entity relationships

**Security Pattern:**
```php
public function filterEntities(): void
{
    // 1. Verify nonce
    if (!$this->verifyNonce('saga_filter_nonce', 'saga_filter')) {
        $this->sendError('Security check failed', 403);
        return;
    }

    // 2. Sanitize input
    $entityType = isset($_POST['entity_type']) ? sanitize_key($_POST['entity_type']) : null;

    // 3. Validate
    if ($entityType !== null && !in_array($entityType, $validTypes, true)) {
        $this->sendError('Invalid entity type', 400);
        return;
    }

    // 4. Process and respond
    try {
        $entities = $this->queries->getEntitiesByType($entityType);
        $this->sendSuccess(['entities' => $entities]);
    } catch (\Exception $e) {
        error_log('[SAGA-THEME][ERROR] ' . $e->getMessage());
        $this->sendError('Failed to retrieve entities', 500);
    }
}
```

### SagaCache (Caching Layer)

**Purpose:** Abstract WordPress object cache operations

**Responsibilities:**
- Get/set cached data
- Cache invalidation
- TTL management
- Group-based caching

**Cache Strategy:**
```php
// Cache-aside pattern
public function getEntity(int $postId): ?object
{
    // 1. Check cache
    $cached = wp_cache_get("entity_{$postId}", 'saga_theme');
    if ($cached !== false) {
        return $cached;
    }

    // 2. Cache miss - return null
    return null;
}

// Store in cache
public function setEntity(int $postId, object $entity, int $ttl = 300): bool
{
    return wp_cache_set("entity_{$postId}", $entity, 'saga_theme', $ttl);
}

// Invalidate on update
public function invalidateEntity(int $postId): bool
{
    return wp_cache_delete("entity_{$postId}", 'saga_theme');
}
```

## Data Flow

### Entity Display Flow

```
User Request → WordPress
    ↓
GeneratePress Template
    ↓
SagaHooks::addEntityMeta()
    ↓
SagaHelpers::getEntityByPostId()
    ↓
SagaQueries::getEntityByPostId()
    ↓
SagaCache::getEntity() → [Cache Hit] → Return
    ↓ [Cache Miss]
    ↓
WordPress $wpdb Query
    ↓
SagaCache::setEntity()
    ↓
Return to Template
```

### AJAX Filter Flow

```
User Action (JS)
    ↓
AJAX Request + Nonce
    ↓
SagaAjaxHandler::filterEntities()
    ↓
Verify Nonce ✓
    ↓
Sanitize Input
    ↓
SagaQueries::getEntitiesByType()
    ↓
Format Response
    ↓
JSON Response
    ↓
JavaScript Update DOM
```

## Performance Optimizations

### 1. Query Optimization

**Indexed Queries:**
```sql
-- Uses index on wp_post_id
SELECT * FROM wp_saga_entities WHERE wp_post_id = %d

-- Uses composite index on saga_id, entity_type
SELECT * FROM wp_saga_entities
WHERE saga_id = %d AND entity_type = %s
ORDER BY importance_score DESC
```

**Batch Operations:**
```php
// BAD - N+1 query problem
foreach ($entities as $entity) {
    $relationships = $this->getRelationships($entity->id);
}

// GOOD - Batch query
$entityIds = array_column($entities, 'id');
$allRelationships = $this->getBulkRelationships($entityIds);
```

### 2. Caching Strategy

**Three-tier caching:**

1. **In-memory cache** (PHP variables during request)
2. **WordPress object cache** (Redis/Memcached if available)
3. **Database** (last resort)

**Cache TTL:**
- Entity data: 300s (5 minutes)
- Relationships: 300s
- Attribute values: 300s
- Search results: 60s (1 minute)

**Cache invalidation:**
- On `save_post` hook
- Manual via `wp cache flush`
- Automatic expiration

### 3. Asset Loading

**Conditional Loading:**
```php
public function enqueueAssets(): void
{
    // Only load on relevant pages
    if (is_singular('saga_entity') || is_post_type_archive('saga_entity')) {
        wp_enqueue_script('saga-manager-theme', ...);
    }
}
```

**Minification:**
- Use minified versions in production
- Combine CSS/JS where appropriate

## Security Architecture

### Defense in Depth

**Layer 1: Input Validation**
```php
$sagaId = absint($_POST['saga_id'] ?? 0);
$searchTerm = sanitize_text_field($_POST['search'] ?? '');
```

**Layer 2: Nonce Verification**
```php
if (!wp_verify_nonce($_POST['nonce'], 'saga_filter')) {
    wp_die('Security check failed');
}
```

**Layer 3: Capability Checks** (for admin actions)
```php
if (!current_user_can('edit_posts')) {
    wp_send_json_error('Insufficient permissions', 403);
}
```

**Layer 4: SQL Injection Prevention**
```php
$query = $wpdb->prepare(
    "SELECT * FROM {$table} WHERE id = %d",
    $entityId
);
```

**Layer 5: Output Escaping**
```php
echo esc_html($entity->canonical_name);
echo esc_url($entity->permalink);
echo esc_attr($entity->slug);
```

## Error Handling Strategy

### Graceful Degradation

```php
try {
    $entity = $this->queries->getEntityByPostId($postId);
} catch (\Exception $e) {
    // 1. Log error
    error_log('[SAGA-THEME][ERROR] ' . $e->getMessage());

    // 2. Return safe default
    return null;

    // Never expose internal errors to users
}
```

### Error Levels

**Development:**
```php
if (WP_DEBUG) {
    error_log('[SAGA-THEME][DEBUG] Query took ' . $duration . 'ms');
}
```

**Production:**
```php
// Only log critical errors
error_log('[SAGA-THEME][CRITICAL] Database connection failed');
```

## Testing Strategy

### Unit Testing (Recommended)

```php
// Test value objects and business logic
class SagaHelpersTest extends PHPUnit\Framework\TestCase
{
    public function testFormatImportanceScore(): void
    {
        $helpers = new SagaHelpers($mockQueries);

        $this->assertEquals('Critical (95/100)', $helpers->formatImportanceScore(95));
        $this->assertEquals('Major (75/100)', $helpers->formatImportanceScore(75));
    }
}
```

### Integration Testing

```php
// Test database interactions
class SagaQueriesTest extends WP_UnitTestCase
{
    public function testGetEntityByPostId(): void
    {
        // Create test entity
        $postId = $this->factory->post->create();

        $cache = new SagaCache();
        $queries = new SagaQueries($cache);

        $entity = $queries->getEntityByPostId($postId);

        $this->assertNotNull($entity);
        $this->assertEquals($postId, $entity->wp_post_id);
    }
}
```

## Future Enhancements

### Phase 1: Interfaces

Add interfaces for all service classes to enable easier mocking and swapping:

```php
interface EntityQueryInterface
{
    public function getEntityByPostId(int $postId): ?object;
    public function getEntitiesBySaga(int $sagaId, int $limit, int $offset): array;
}

class SagaQueries implements EntityQueryInterface
{
    // Implementation
}
```

### Phase 2: Service Container

Replace manual DI with PSR-11 container:

```php
$container = new ServiceContainer();
$container->register(SagaCache::class);
$container->register(SagaQueries::class, [SagaCache::class]);
$container->register(SagaHelpers::class, [SagaQueries::class]);

$theme = $container->get(SagaTheme::class);
```

### Phase 3: Event System

Add event dispatching for extensibility:

```php
do_action('saga_theme_entity_loaded', $entity);
do_action('saga_theme_cache_invalidated', $entityId);
```

### Phase 4: Repository Pattern

Abstract database layer behind repository interface:

```php
interface EntityRepositoryInterface
{
    public function findById(int $id): ?Entity;
    public function findBySaga(int $sagaId): array;
}
```

## Conclusion

This architecture provides:

1. **Maintainability**: Clear separation of concerns, easy to locate code
2. **Testability**: Dependency injection enables unit testing
3. **Performance**: Multi-tier caching, optimized queries
4. **Security**: Defense in depth, proper escaping and sanitization
5. **Scalability**: Can handle 100K+ entities with proper caching
6. **Extensibility**: SOLID principles allow easy extension

The codebase follows modern PHP and WordPress best practices while remaining accessible to developers familiar with WordPress theme development.
