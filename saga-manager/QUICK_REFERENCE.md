# Saga Manager - Quick Reference Card

## Installation & Activation

```bash
# Install dependencies
cd /path/to/saga-manager
composer install

# Activate plugin
wp plugin activate saga-manager

# Verify tables created
wp db query "SHOW TABLES LIKE '%saga_%'"
```

## REST API Endpoints

### List Entities
```bash
GET /wp-json/saga/v1/entities?saga_id=1&type=character&page=1&per_page=20
```

### Get Entity
```bash
GET /wp-json/saga/v1/entities/123
```

### Create Entity
```bash
POST /wp-json/saga/v1/entities
Content-Type: application/json
X-WP-Nonce: YOUR_NONCE

{
  "saga_id": 1,
  "type": "character",
  "canonical_name": "Luke Skywalker",
  "importance_score": 90
}
```

### Update Entity
```bash
PUT /wp-json/saga/v1/entities/123
Content-Type: application/json
X-WP-Nonce: YOUR_NONCE

{
  "canonical_name": "Luke Skywalker (Updated)",
  "importance_score": 95
}
```

### Delete Entity
```bash
DELETE /wp-json/saga/v1/entities/123
X-WP-Nonce: YOUR_NONCE
```

## PHP Usage

### Create Entity Programmatically
```php
use SagaManager\Domain\Entity\{SagaEntity, SagaId, EntityType, ImportanceScore};
use SagaManager\Infrastructure\Repository\MariaDBEntityRepository;

$repo = new MariaDBEntityRepository();

$entity = new SagaEntity(
    sagaId: new SagaId(1),
    type: EntityType::CHARACTER,
    canonicalName: 'Darth Vader',
    slug: 'darth-vader',
    importanceScore: new ImportanceScore(100)
);

$repo->save($entity);
```

### Sync Entity to wp_posts
```php
use SagaManager\Infrastructure\WordPress\SagaEntityPostType;

$postType = new SagaEntityPostType($repo);
$post_id = $postType->syncFromEntity($entity);
```

### Find Entities
```php
// By ID
$entity = $repo->findById(new EntityId(123));

// By saga
$entities = $repo->findBySaga(new SagaId(1), limit: 10);

// By saga and type
$characters = $repo->findBySagaAndType(
    new SagaId(1),
    EntityType::CHARACTER,
    limit: 20
);

// By slug
$entity = $repo->findBySlug('luke-skywalker');

// By wp_post_id
$entity = $repo->findByWpPostId(456);
```

## JavaScript Usage

### Get Nonce
```javascript
// In WordPress admin (wp-admin)
const nonce = wpApiSettings.nonce;

// In frontend (if localized)
const nonce = sagaSettings.nonce;
```

### Create Entity
```javascript
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
.then(res => res.json())
.then(data => console.log('Created:', data));
```

### List Entities
```javascript
fetch('/wp-json/saga/v1/entities?saga_id=1&type=character')
  .then(res => {
    const total = res.headers.get('X-WP-Total');
    const pages = res.headers.get('X-WP-TotalPages');
    console.log(`Total: ${total}, Pages: ${pages}`);
    return res.json();
  })
  .then(entities => console.log(entities));
```

## WordPress Admin

### Create Entity via CPT
1. Go to **wp-admin → Saga Entities → Add New**
2. Enter **Title** (canonical name)
3. Fill **Meta Box**:
   - Saga ID: 1
   - Entity Type: Character
   - Importance: 90
4. Click **Publish**
5. Entity auto-synced to `saga_entities` table

### Meta Box Fields
- **Saga ID**: Which saga this entity belongs to
- **Entity Type**: character, location, event, faction, artifact, concept
- **Importance**: 0-100 score for entity significance

## Entity Types

```php
EntityType::CHARACTER  // Characters, people
EntityType::LOCATION   // Places, planets, regions
EntityType::EVENT      // Historical events, battles
EntityType::FACTION    // Organizations, groups
EntityType::ARTIFACT   // Items, weapons, technology
EntityType::CONCEPT    // Ideas, philosophies, concepts
```

## Permissions

### Read Operations
- Requires: `current_user_can('read')`
- Endpoints: GET /entities, GET /entities/{id}

### Write Operations
- Requires: `current_user_can('edit_posts')`
- Endpoints: POST, PUT, DELETE
- Requires: X-WP-Nonce header

## Database Tables

```
{prefix}saga_sagas              # Saga definitions
{prefix}saga_entities           # Core entity table
{prefix}saga_attribute_definitions # EAV schema
{prefix}saga_attribute_values   # EAV data
{prefix}saga_entity_relationships # Entity links
{prefix}saga_timeline_events    # Timeline
{prefix}saga_content_fragments  # Text for search
{prefix}saga_quality_metrics    # Data quality
```

## Common Tasks

### Check if Entity Exists
```php
$exists = $repo->exists(new EntityId(123));
```

### Count Entities in Saga
```php
$count = $repo->countBySaga(new SagaId(1));
```

### Update Entity
```php
$entity = $repo->findById(new EntityId(123));
$entity->updateCanonicalName('New Name');
$entity->setImportanceScore(new ImportanceScore(95));
$repo->save($entity);
```

### Delete Entity
```php
$repo->delete(new EntityId(123));
```

### Link Entity to wp_post
```php
$entity->linkToWpPost(456);
$repo->save($entity);
```

## Error Handling

### REST API Error Codes
- **400**: Validation error (invalid data)
- **403**: Permission denied (auth failed)
- **404**: Entity not found
- **409**: Conflict (duplicate entity)
- **500**: Internal server error

### PHP Exceptions
```php
try {
    $entity = $repo->findById(new EntityId(999));
} catch (EntityNotFoundException $e) {
    // Handle not found
} catch (ValidationException $e) {
    // Handle validation error
} catch (\Exception $e) {
    // Handle general error
}
```

## Debugging

### Enable WordPress Debug
```php
// wp-config.php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
define('WP_DEBUG_DISPLAY', false);
```

### Check Logs
```bash
tail -f wp-content/debug.log | grep SAGA
```

### Common Log Entries
```
[SAGA][ERROR] wp_posts sync failed: Invalid entity type
[SAGA][WARNING] Sync conflict for post 123
[SAGA][INFO] Unlinked entity 456 from deleted post 789
```

### Test Integration
```bash
wp eval-file wp-content/plugins/saga-manager/test-integration.php
```

## Cache Management

### Cache Keys
```php
"saga_entity_{$entity_id}"  // Single entity cache
```

### Cache Operations
```php
// Get from cache
$entity = wp_cache_get("saga_entity_123", 'saga');

// Set cache (5 min TTL)
wp_cache_set("saga_entity_123", $entity, 'saga', 300);

// Delete cache
wp_cache_delete("saga_entity_123", 'saga');
```

### Flush Saga Cache
```php
// Manual flush (if needed)
wp_cache_flush_group('saga');
```

## Performance Tips

1. **Use pagination**: Max 100 items per request
2. **Enable object cache**: Redis/Memcached in production
3. **Monitor query time**: Target <50ms per query
4. **Use filtering**: Always filter by saga_id
5. **Batch operations**: Use repository bulk methods

## Security Tips

1. **Always verify nonce** on mutations
2. **Check capabilities** before operations
3. **Sanitize input** on all user data
4. **Escape output** in templates
5. **Use wpdb->prepare()** for all queries

## Files

### Core Implementation
- `src/Infrastructure/WordPress/SagaEntityPostType.php` - Custom Post Type
- `src/Presentation/API/EntityController.php` - REST API
- `src/Infrastructure/Repository/MariaDBEntityRepository.php` - Data Access
- `src/Infrastructure/WordPress/Plugin.php` - Plugin Bootstrap

### Documentation
- `INTEGRATION_README.md` - Full integration guide
- `IMPLEMENTATION_SUMMARY.md` - Technical summary
- `VALIDATION_CHECKLIST.md` - Compliance checklist
- `QUICK_REFERENCE.md` - This file

### Testing
- `test-integration.php` - Integration test script

## Support

### GitHub Issues
- Report bugs
- Request features
- Ask questions

### WordPress Support Forum
- Community help
- Best practices
- Use cases

### Error Logs
- Check `wp-content/debug.log`
- Look for `[SAGA]` prefix
- Enable WP_DEBUG for details
