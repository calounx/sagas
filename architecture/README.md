# Saga Manager Split Architecture

## Overview

Saga Manager is split into two WordPress plugins with a clean separation of concerns:

```
┌─────────────────────────────────────────────────────────────┐
│                    SAGA MANAGER CORE                        │
│                   (Backend Plugin)                          │
├─────────────────────────────────────────────────────────────┤
│  - Database tables (saga_*)                                 │
│  - Domain models and business logic                         │
│  - REST API (saga/v1/*)                                     │
│  - Admin UI (WP_List_Table based)                          │
│  - Background processing (WP-Cron)                          │
│  - Settings management                                      │
└─────────────────────────────────────────────────────────────┘
                           │
                           │ REST API
                           │ (saga/v1/*)
                           ▼
┌─────────────────────────────────────────────────────────────┐
│                   SAGA MANAGER DISPLAY                      │
│                   (Frontend Plugin)                         │
├─────────────────────────────────────────────────────────────┤
│  - Shortcodes                                               │
│  - Gutenberg blocks                                         │
│  - Widgets                                                  │
│  - Templates and frontend assets                            │
│  - API client (consumes backend API)                        │
│  - Response caching                                         │
└─────────────────────────────────────────────────────────────┘
```

## Plugin Dependency

The frontend plugin depends on the backend plugin:

```php
// Frontend checks backend on activation
if (!class_exists('\SagaManagerCore\SagaManagerCore')) {
    wp_die('Saga Manager Display requires Saga Manager Core.');
}

// Frontend checks backend on every load
add_action('plugins_loaded', function() {
    if (!SagaManagerCore\SagaManagerCore::isReady()) {
        // Show admin notice, disable functionality
    }
});
```

## Key Files

### Backend Plugin (saga-manager-core)

| File | Purpose |
|------|---------|
| `saga-manager-core.php` | Main plugin file, bootstrapping |
| `src/Domain/` | Pure PHP domain models, no WP dependencies |
| `src/Application/` | Use cases, commands, queries, handlers |
| `src/Infrastructure/` | Database repositories, external services |
| `src/Presentation/Admin/` | Admin pages using WP_List_Table |
| `src/Presentation/Rest/` | REST API controllers |
| `src/Contract/` | Shared interfaces for frontend |

### Frontend Plugin (saga-manager-display)

| File | Purpose |
|------|---------|
| `saga-manager-display.php` | Main plugin file, dependency checking |
| `src/ApiClient/` | REST API consumption layer |
| `src/Shortcode/` | Shortcode implementations |
| `src/Block/` | Gutenberg block registrations |
| `src/Widget/` | WordPress widgets |
| `src/Template/` | Template loading and rendering |
| `templates/` | PHP template files |

## REST API Endpoints

All endpoints prefixed with `/wp-json/saga/v1/`

### Sagas
- `GET /sagas` - List all sagas
- `GET /sagas/{id}` - Get single saga
- `POST /sagas` - Create saga
- `PUT /sagas/{id}` - Update saga
- `DELETE /sagas/{id}` - Delete saga

### Entities
- `GET /entities` - List entities (filterable)
- `GET /entities/{id}` - Get single entity
- `POST /entities` - Create entity
- `PUT /entities/{id}` - Update entity
- `DELETE /entities/{id}` - Delete entity
- `GET /entities/{id}/attributes` - Get entity attributes
- `PUT /entities/{id}/attributes` - Update attributes
- `GET /entities/{id}/relationships` - Get relationships

### Relationships
- `GET /relationships` - List relationships
- `POST /relationships` - Create relationship
- `DELETE /relationships/{id}` - Delete relationship

### Timeline
- `GET /timeline` - Get timeline events
- `GET /timeline/{id}` - Get single event

### Search
- `GET /search` - Full-text search
- `GET /search/semantic` - Vector similarity search

## Admin UI (Backend)

Uses custom admin pages with WP_List_Table, NOT Custom Post Types:

```
Saga Manager (menu)
├── Dashboard        → Overview stats, quality alerts
├── Sagas           → Saga list/edit
├── Entities        → Entity list with type/saga filters
├── Relationships   → Relationship management
├── Timeline        → Timeline event management
├── Attributes      → Attribute definition management
└── Settings        → Plugin configuration
```

## Shortcodes (Frontend)

```
[saga_entity id="123"]
[saga_entity id="123" include="attributes,relationships" template="card"]

[saga_entities saga="1"]
[saga_entities saga="1" type="character" limit="10" orderby="importance_score"]

[saga_timeline saga="1"]
[saga_timeline saga="1" from="10191 AG" to="10193 AG"]

[saga_relationships entity="123"]
[saga_relationships entity="123" type="ally_of" direction="outgoing"]

[saga_search saga="1"]
[saga_search saga="1" placeholder="Search characters..."]

[saga_archive]
```

## Installation Order

1. Install and activate **saga-manager-core** first
2. Tables are created, admin UI available
3. Install and activate **saga-manager-display**
4. Frontend features become available

## Development Setup

```bash
# Clone repositories
git clone https://github.com/your-org/saga-manager-core.git
git clone https://github.com/your-org/saga-manager-display.git

# Backend setup
cd saga-manager-core
composer install
npm install  # For admin JS/CSS build

# Frontend setup
cd ../saga-manager-display
composer install
npm install
npm run build  # Build Gutenberg blocks
```

## Testing

```bash
# Backend tests
cd saga-manager-core
./vendor/bin/phpunit

# Frontend tests
cd saga-manager-display
./vendor/bin/phpunit

# Integration tests (both plugins active)
./vendor/bin/phpunit --testsuite=integration
```

## Performance Considerations

1. **API Response Caching**
   - Frontend caches API responses in transients
   - Default TTL: 5 minutes
   - Configurable per-endpoint

2. **Query Optimization**
   - All entity queries filtered by saga_id first
   - EAV queries use bulk loading, not JOINs
   - Covering indexes on frequently filtered columns

3. **Asset Loading**
   - Frontend assets only loaded when shortcodes/blocks present
   - Admin assets only on Saga Manager pages

## Security

1. **API Authentication**
   - Read operations: public or logged-in depending on settings
   - Write operations: require `edit_posts` capability
   - Nonce verification for all mutations

2. **Input Sanitization**
   - All inputs sanitized before processing
   - All outputs escaped in templates
   - Prepared statements for all SQL queries

3. **Rate Limiting**
   - API endpoints rate-limited per user
   - Configurable limits in settings

## File Locations

After installation:

```
wp-content/plugins/
├── saga-manager-core/
│   ├── saga-manager-core.php
│   ├── src/
│   ├── templates/admin/
│   └── assets/admin/
│
└── saga-manager-display/
    ├── saga-manager-display.php
    ├── src/
    ├── templates/
    ├── blocks/
    └── assets/
```

Theme template overrides:

```
wp-content/themes/your-theme/
└── saga-manager/
    ├── entity-single.php
    ├── entity-list.php
    └── timeline.php
```
