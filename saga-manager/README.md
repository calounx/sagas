# Saga Manager

Multi-tenant saga management system for complex fictional universes. Built with WordPress and hexagonal architecture.

## Overview

Saga Manager is a WordPress plugin designed to manage complex fictional universe data at scale. It uses a hybrid EAV (Entity-Attribute-Value) architecture optimized for flexible entity modeling while maintaining relational integrity.

**Target Scale:** 100K+ entities per saga, sub-50ms query response, semantic search on 1M+ text fragments.

## Features

- **Multi-tenant Architecture**: Manage multiple sagas (universes) in one installation
- **Flexible Entity System**: Characters, locations, events, factions, artifacts, and concepts
- **Dynamic Attributes (EAV)**: Define custom attributes per entity type with validation
- **Relationship Management**: Directed, weighted, temporal relationships between entities
- **Hybrid EAV Design**: Balance between flexibility and performance
- **WordPress Integration**: Native table prefix support, multisite compatible
- **Hexagonal Architecture**: Clean separation of domain and infrastructure layers
- **REST API**: Full CRUD operations for all entity types

## Requirements

- PHP 8.2 or higher
- WordPress 6.0 or higher
- MariaDB 11.4.8 or higher
- Composer

## Installation

1. Clone the repository to your WordPress plugins directory:
```bash
cd wp-content/plugins
git clone <repository-url> saga-manager
cd saga-manager
```

2. Install dependencies:
```bash
composer install --no-dev
```

3. Activate the plugin through WordPress admin panel

## REST API Reference

All endpoints are prefixed with `/wp-json/saga/v1/`.

### Entities

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/entities` | List all entities (with pagination) |
| GET | `/entities/{id}` | Get a single entity |
| POST | `/entities` | Create a new entity |
| PUT | `/entities/{id}` | Update an entity |
| DELETE | `/entities/{id}` | Delete an entity |

**Create Entity Example:**
```json
POST /wp-json/saga/v1/entities
{
  "saga_id": 1,
  "entity_type": "character",
  "canonical_name": "Luke Skywalker",
  "slug": "luke-skywalker",
  "importance_score": 95
}
```

### Attribute Definitions

Define custom attributes for each entity type.

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/attribute-definitions` | List definitions (filter by entity_type) |
| GET | `/attribute-definitions/{id}` | Get a single definition |
| POST | `/attribute-definitions` | Create a new definition |
| PUT | `/attribute-definitions/{id}` | Update a definition |
| DELETE | `/attribute-definitions/{id}` | Delete a definition |

**Create Attribute Definition Example:**
```json
POST /wp-json/saga/v1/attribute-definitions
{
  "entity_type": "character",
  "attribute_key": "birth_year",
  "display_name": "Birth Year",
  "data_type": "string",
  "is_searchable": true,
  "is_required": false,
  "validation_rule": {
    "pattern": "^\\d+ (BBY|ABY)$"
  }
}
```

**Supported Data Types:**
- `string` - Short text (max 500 chars)
- `text` - Long text
- `int` - Integer values
- `float` - Decimal values
- `bool` - True/false
- `date` - Date values (Y-m-d format)
- `json` - JSON objects

### Entity Attributes

Manage attribute values for entities.

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/entities/{id}/attributes` | Get all attributes for an entity |
| PUT | `/entities/{id}/attributes` | Bulk set attributes (upsert) |
| PUT | `/entities/{id}/attributes/{key}` | Set a single attribute |
| DELETE | `/entities/{id}/attributes/{key}` | Remove an attribute |

**Set Attributes Example:**
```json
PUT /wp-json/saga/v1/entities/123/attributes
{
  "attributes": [
    {"attribute_key": "birth_year", "value": "19 BBY"},
    {"attribute_key": "homeworld", "value": "Tatooine"},
    {"attribute_key": "force_sensitive", "value": true}
  ]
}
```

### Relationships

Manage relationships between entities. Relationships are directed (source -> target), weighted (0-100 strength), and can have temporal validity.

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/relationships` | List relationships (requires entity_id or type) |
| GET | `/relationships/{id}` | Get a single relationship |
| POST | `/relationships` | Create a new relationship |
| PUT | `/relationships/{id}` | Update a relationship |
| DELETE | `/relationships/{id}` | Delete a relationship |
| GET | `/entities/{id}/relationships` | Get relationships for an entity |
| GET | `/relationship-types` | List all relationship types in use |

**Create Relationship Example:**
```json
POST /wp-json/saga/v1/relationships
{
  "source_entity_id": 1,
  "target_entity_id": 2,
  "relationship_type": "parent_of",
  "strength": 100,
  "valid_from": "0 BBY",
  "valid_until": null,
  "metadata": {
    "biological": true,
    "note": "Discovered truth at Battle of Endor"
  }
}
```

**Relationship Strength Scale:**
- 0-30: Weak relationship
- 31-69: Moderate relationship
- 70-100: Strong relationship

**Entity Relationships Query Parameters:**
- `type`: Filter by relationship type
- `direction`: `outgoing`, `incoming`, or `both` (default)
- `current_only`: If true, only return currently valid relationships

## Project Structure

```
saga-manager/
├── saga-manager.php           # Main plugin file
├── composer.json
├── src/
│   ├── Domain/                # Pure domain logic (no WordPress deps)
│   │   ├── Entity/           # Domain entities and value objects
│   │   │   ├── EntityId.php, SagaId.php, ImportanceScore.php
│   │   │   ├── SagaEntity.php
│   │   │   ├── AttributeDefinition.php, AttributeValue.php
│   │   │   ├── Relationship.php, RelationshipStrength.php
│   │   │   └── DataType.php, ValidationRule.php
│   │   ├── Repository/       # Repository interfaces (ports)
│   │   └── Exception/        # Domain exceptions
│   ├── Application/          # Use cases and application services
│   │   ├── UseCase/         # Command handlers (create, update, delete)
│   │   ├── Query/           # Query handlers
│   │   └── Command/         # Command/Query interfaces
│   ├── Infrastructure/       # Implementation details
│   │   ├── Repository/      # MariaDB repository implementations
│   │   ├── WordPress/       # WordPress-specific adapters
│   │   └── Exception/       # Infrastructure exceptions
│   └── Presentation/        # UI layer
│       ├── API/             # REST controllers
│       └── Admin/           # WordPress admin pages
└── tests/
    ├── Unit/               # Unit tests for domain layer
    ├── Integration/        # Integration tests
    ├── Performance/        # Performance benchmarks
    └── Fixtures/           # Test fixtures
```

## Architecture

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

### CQRS Pattern

The application layer uses Command/Query Responsibility Segregation:
- **Commands**: CreateEntity, UpdateEntity, DeleteEntity, SetAttributeValue, CreateRelationship, etc.
- **Queries**: GetEntityById, GetAttributeDefinitions, GetRelationships, etc.

Each command/query has a dedicated handler that orchestrates the operation.

### Database Tables

All tables use WordPress prefix (`wp_` by default) + `saga_` prefix:

| Table | Purpose |
|-------|---------|
| `{prefix}saga_sagas` | Saga/universe definitions |
| `{prefix}saga_entities` | Core entity data |
| `{prefix}saga_attribute_definitions` | EAV schema definitions |
| `{prefix}saga_attribute_values` | Dynamic entity attributes |
| `{prefix}saga_entity_relationships` | Entity relationships |
| `{prefix}saga_timeline_events` | Timeline events |
| `{prefix}saga_content_fragments` | Text fragments for search |
| `{prefix}saga_quality_metrics` | Data quality tracking |

## Development

### Running Tests

```bash
# Install development dependencies
composer install

# Run unit tests only
./vendor/bin/phpunit --testsuite Unit

# Run all tests
composer test

# Run code sniffer
composer cs

# Run static analysis
composer stan
```

### Test Coverage

Current test coverage includes:
- Domain value objects: EntityId, SagaId, ImportanceScore, RelationshipId, RelationshipStrength, etc.
- Domain entities: SagaEntity, AttributeDefinition, Relationship
- Integration tests for repositories and REST API

### Coding Standards

This plugin follows WordPress coding standards and uses:
- Strict types (`declare(strict_types=1)`)
- Type hints on all parameters and returns
- Readonly properties for immutable data
- `$wpdb->prepare()` for all SQL queries
- WordPress object cache integration
- SOLID principles throughout

## Version History

### v1.2.1 (Current)

**New Features:**
- Complete Attribute Management (EAV) system
  - Define custom attributes per entity type
  - Validation rules (pattern, min/max, enum)
  - Searchable/required attribute flags
  - Bulk attribute operations

- Complete Relationship Management system
  - Directed relationships (source -> target)
  - Relationship strength (0-100 scale)
  - Temporal validity (valid_from, valid_until)
  - Metadata support (JSON)
  - Current-only filtering

**Improvements:**
- Multi-level caching (in-memory + wp_cache)
- Bulk fetch optimization to prevent N+1 queries
- Transaction support for complex operations
- Comprehensive unit tests for new features

### v1.2.0

- REST API integration tests for all endpoints
- Bulk operations with proper cache invalidation
- EAV bulk fetch optimization with attribute caching
- Query performance logging (target: sub-50ms)
- Multisite test fixtures
- Infrastructure layer exceptions

### v1.1.0

- Core entity CRUD operations
- Basic REST API endpoints
- Plugin skeleton with activation/deactivation

### v1.0.0

- Initial release
- Database schema
- Domain models

## Security

- All database queries use `$wpdb->prepare()` for SQL injection prevention
- Capability checks on all admin actions (`read` for GET, `edit_posts` for write)
- Nonce verification on forms
- Input sanitization (`sanitize_text_field`, `absint`, `sanitize_key`)
- No hardcoded credentials

## Performance

- WordPress object cache integration
- In-memory caching for frequently accessed data
- Sub-50ms query target (with monitoring)
- Transaction support for data integrity
- Optimized indexes on all tables
- Bulk operations to minimize database round-trips

## Roadmap

Planned features for future releases:
- Timeline Events management
- Content Fragments for semantic search
- Vector embedding integration
- Quality metrics dashboard
- Frontend shortcodes

## License

GPL v2 or later

## Support

For issues and feature requests, please use the GitHub issue tracker.

## Credits

Built following WordPress and PHP 8.2 best practices with hexagonal architecture principles.
