# WordPress Integration Implementation

This document describes the WordPress integration for the Saga Manager plugin, including Custom Post Types and REST API endpoints.

## Files Created

### 1. Custom Post Type (`src/Infrastructure/WordPress/SagaEntityPostType.php`)

**Features:**
- Registers `saga_entity` custom post type with Gutenberg support
- Registers `saga_type` taxonomy for entity classification
- Implements bidirectional sync between `wp_posts` and `saga_entities` table
- Conflict resolution using timestamp comparison
- Meta boxes for saga_id, entity_type, and importance_score
- Prevents sync loops with internal flag
- Unlinks entities from deleted posts (preserves entity data)

**Key Methods:**
- `register()` - Registers CPT, taxonomy, and hooks
- `syncToDatabase()` - wp_posts → saga_entities sync on save_post
- `syncFromEntity()` - saga_entities → wp_posts sync (for programmatic updates)
- `handlePostDeletion()` - Unlinks entity when post is deleted

**Security:**
- Nonce verification (`saga_entity_meta_nonce`)
- Capability checks (`edit_post`)
- Input sanitization (`sanitize_text_field`, `absint`, `sanitize_key`)

### 2. REST API Controller (`src/Presentation/API/EntityController.php`)

**Endpoints:**

#### GET /wp-json/saga/v1/entities
List entities with pagination and filtering

**Parameters:**
- `saga_id` (required, integer): Saga ID to filter by
- `type` (optional, string): Entity type (character, location, event, faction, artifact, concept)
- `page` (optional, integer, default: 1): Page number
- `per_page` (optional, integer, default: 20, max: 100): Items per page

**Response Headers:**
- `X-WP-Total`: Total number of entities
- `X-WP-TotalPages`: Total number of pages

**Example:**
```bash
curl -X GET "http://example.com/wp-json/saga/v1/entities?saga_id=1&type=character&page=1&per_page=20" \
  -H "Authorization: Bearer YOUR_TOKEN"
```

#### GET /wp-json/saga/v1/entities/{id}
Get single entity by ID

**Example:**
```bash
curl -X GET "http://example.com/wp-json/saga/v1/entities/123" \
  -H "Authorization: Bearer YOUR_TOKEN"
```

#### POST /wp-json/saga/v1/entities
Create new entity

**Request Body:**
```json
{
  "saga_id": 1,
  "type": "character",
  "canonical_name": "Luke Skywalker",
  "slug": "luke-skywalker",
  "importance_score": 90
}
```

**Example:**
```bash
curl -X POST "http://example.com/wp-json/saga/v1/entities" \
  -H "Content-Type: application/json" \
  -H "X-WP-Nonce: YOUR_NONCE" \
  -d '{
    "saga_id": 1,
    "type": "character",
    "canonical_name": "Luke Skywalker",
    "importance_score": 90
  }'
```

#### PUT /wp-json/saga/v1/entities/{id}
Update existing entity

**Request Body:**
```json
{
  "canonical_name": "Luke Skywalker (Updated)",
  "importance_score": 95
}
```

**Example:**
```bash
curl -X PUT "http://example.com/wp-json/saga/v1/entities/123" \
  -H "Content-Type: application/json" \
  -H "X-WP-Nonce: YOUR_NONCE" \
  -d '{
    "canonical_name": "Luke Skywalker (Updated)",
    "importance_score": 95
  }'
```

#### DELETE /wp-json/saga/v1/entities/{id}
Delete entity

**Example:**
```bash
curl -X DELETE "http://example.com/wp-json/saga/v1/entities/123" \
  -H "X-WP-Nonce: YOUR_NONCE"
```

**Security:**
- Read operations: `current_user_can('read')`
- Write operations: `current_user_can('edit_posts')`
- Nonce verification for POST/PUT/DELETE
- Input sanitization on all parameters

### 3. Plugin Integration (`src/Infrastructure/WordPress/Plugin.php`)

**Updates:**
- Lazy initialization of repository, post type, and controller
- Dependency injection via constructor
- `registerPostTypes()` now instantiates and registers `SagaEntityPostType`
- `registerRestRoutes()` now registers all REST endpoints

## Usage Examples

### Creating an Entity via WordPress Admin

1. Navigate to "Saga Entities" in WordPress admin
2. Click "Add New"
3. Enter title (canonical name)
4. Fill in meta box fields:
   - Saga ID: 1
   - Entity Type: Character
   - Importance: 90
5. Click "Publish"
6. Entity is automatically created in `saga_entities` table

### Creating an Entity Programmatically

```php
use SagaManager\Domain\Entity\SagaEntity;
use SagaManager\Domain\Entity\SagaId;
use SagaManager\Domain\Entity\EntityType;
use SagaManager\Domain\Entity\ImportanceScore;
use SagaManager\Infrastructure\Repository\MariaDBEntityRepository;
use SagaManager\Infrastructure\WordPress\SagaEntityPostType;

$repository = new MariaDBEntityRepository();
$postType = new SagaEntityPostType($repository);

// Create entity
$entity = new SagaEntity(
    sagaId: new SagaId(1),
    type: EntityType::CHARACTER,
    canonicalName: 'Darth Vader',
    slug: 'darth-vader',
    importanceScore: new ImportanceScore(100)
);

$repository->save($entity);

// Sync to wp_posts
$post_id = $postType->syncFromEntity($entity);

echo "Created post ID: {$post_id}";
```

### Using REST API with JavaScript

```javascript
// Get WordPress nonce
const nonce = wpApiSettings.nonce;

// Create entity
fetch('/wp-json/saga/v1/entities', {
  method: 'POST',
  headers: {
    'Content-Type': 'application/json',
    'X-WP-Nonce': nonce
  },
  body: JSON.stringify({
    saga_id: 1,
    type: 'character',
    canonical_name: 'Princess Leia',
    importance_score: 95
  })
})
  .then(response => response.json())
  .then(data => console.log('Created entity:', data))
  .catch(error => console.error('Error:', error));

// List entities
fetch('/wp-json/saga/v1/entities?saga_id=1&type=character&page=1&per_page=20')
  .then(response => {
    const total = response.headers.get('X-WP-Total');
    const totalPages = response.headers.get('X-WP-TotalPages');
    console.log(`Total: ${total}, Pages: ${totalPages}`);
    return response.json();
  })
  .then(data => console.log('Entities:', data))
  .catch(error => console.error('Error:', error));
```

## Bidirectional Sync Behavior

### wp_posts → saga_entities (Auto Sync)
When a saga_entity post is saved in WordPress admin:
1. Meta box data is validated
2. Existing entity is checked via `wp_post_id`
3. If entity exists and is newer than post: **conflict** (entity wins)
4. If entity exists and post is newer: entity is updated
5. If entity doesn't exist: new entity is created

### saga_entities → wp_posts (Manual Sync)
When an entity is created/updated programmatically:
```php
$postType->syncFromEntity($entity);
```
This creates or updates the corresponding wp_post.

### Conflict Resolution
- Comparison based on `updated_at` timestamps
- Entity timestamp > Post timestamp: Entity data preserved, sync skipped
- Post timestamp >= Entity timestamp: Entity updated from post
- Logged to error_log for monitoring

## Testing

### Manual Testing Checklist

1. **Custom Post Type**
   - [ ] "Saga Entities" appears in admin menu
   - [ ] Can create new entity with meta box data
   - [ ] Entity appears in `saga_entities` table
   - [ ] Updating post updates entity
   - [ ] Deleting post unlinks entity (doesn't delete)

2. **REST API**
   - [ ] GET /entities returns list with pagination
   - [ ] GET /entities/{id} returns single entity
   - [ ] POST /entities creates entity
   - [ ] PUT /entities/{id} updates entity
   - [ ] DELETE /entities/{id} deletes entity
   - [ ] Unauthenticated requests return 401/403
   - [ ] Invalid data returns 400

3. **Security**
   - [ ] Nonce verification works on mutations
   - [ ] Capability checks prevent unauthorized access
   - [ ] SQL injection prevented (all queries use wpdb->prepare)
   - [ ] Input sanitization works (no XSS)

4. **Sync**
   - [ ] Creating post creates entity
   - [ ] Updating post updates entity
   - [ ] No infinite sync loops
   - [ ] Conflicts logged properly

## Database Schema Requirements

Ensure these tables exist (created by Activator.php):
- `{prefix}saga_sagas`
- `{prefix}saga_entities`

The `saga_entities` table must have:
- `wp_post_id` column (BIGINT UNSIGNED, nullable)
- `updated_at` column (TIMESTAMP)
- Index on `wp_post_id`

## Error Handling

All errors are logged to WordPress error_log with `[SAGA]` prefix:

```php
// Example log entries
[SAGA][ERROR] wp_posts sync failed: Invalid entity type
[SAGA][WARNING] Sync conflict for post 123: entity newer than post
[SAGA][INFO] Unlinked entity 456 from deleted post 789
```

## Performance Considerations

1. **Caching**: Entity reads use WordPress object cache (5-minute TTL)
2. **Transactions**: All database writes use transactions with rollback
3. **Lazy Loading**: Repository, controller, and post type initialized on demand
4. **Query Optimization**: All queries use proper indexes
5. **Pagination**: REST API enforces max 100 items per page

## Next Steps

1. **Run Composer**: `composer dump-autoload` (requires Composer installation)
2. **Activate Plugin**: Enable via WordPress admin
3. **Test Endpoints**: Use provided curl examples
4. **Create Test Data**: Add sample saga and entities
5. **Monitor Logs**: Check error_log for sync issues

## Compliance Checklist

- [x] All database queries use `$wpdb->prepare()`
- [x] Table names use `$wpdb->prefix . 'saga_*'`
- [x] User input sanitized (`sanitize_text_field`, `absint`, etc.)
- [x] Capability checks on admin actions
- [x] Nonce verification on forms and mutations
- [x] Strict types declared (`declare(strict_types=1);`)
- [x] Type hints on all parameters and returns
- [x] Hexagonal architecture respected
- [x] Repository pattern for data access
- [x] Transactions with rollback on failures
- [x] Error logging for critical operations
- [x] Cache invalidation on updates
