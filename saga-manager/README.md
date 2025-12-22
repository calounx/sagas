# Saga Manager

Multi-tenant saga management system for complex fictional universes. Built with WordPress and hexagonal architecture.

## Overview

Saga Manager is a WordPress plugin designed to manage complex fictional universe data at scale. It uses a hybrid EAV (Entity-Attribute-Value) architecture optimized for flexible entity modeling while maintaining relational integrity.

**Target Scale:** 100K+ entities per saga, sub-50ms query response, semantic search on 1M+ text fragments.

## Features

- **Multi-tenant Architecture**: Manage multiple sagas (universes) in one installation
- **Flexible Entity System**: Characters, locations, events, factions, artifacts, and concepts
- **Hybrid EAV Design**: Balance between flexibility and performance
- **WordPress Integration**: Native table prefix support, multisite compatible
- **Hexagonal Architecture**: Clean separation of domain and infrastructure layers

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

## Project Structure

```
saga-manager/
├── saga-manager.php           # Main plugin file
├── composer.json
├── src/
│   ├── Domain/                # Pure domain logic (no WordPress deps)
│   │   ├── Entity/           # Domain entities and value objects
│   │   ├── Repository/       # Repository interfaces (ports)
│   │   └── Exception/        # Domain exceptions
│   ├── Application/          # Use cases and application services
│   ├── Infrastructure/       # Implementation details
│   │   ├── Repository/      # MariaDB repository implementations
│   │   ├── WordPress/       # WordPress-specific adapters
│   │   └── Cache/           # Cache implementations
│   └── Presentation/        # UI layer
└── tests/
    ├── Unit/               # Unit tests for domain layer
    └── Integration/        # Integration tests
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

### Database Tables

All tables use WordPress prefix (`wp_` by default) + `saga_` prefix:

- `{prefix}saga_sagas` - Saga definitions
- `{prefix}saga_entities` - Core entity data
- `{prefix}saga_attribute_definitions` - EAV schema definitions
- `{prefix}saga_attribute_values` - Dynamic entity attributes
- `{prefix}saga_entity_relationships` - Entity relationships
- `{prefix}saga_timeline_events` - Timeline events
- `{prefix}saga_content_fragments` - Text fragments for search
- `{prefix}saga_quality_metrics` - Data quality tracking

## Development

### Running Tests

```bash
# Install development dependencies
composer install

# Run tests
composer test

# Run code sniffer
composer cs

# Run static analysis
composer stan
```

### WordPress Coding Standards

This plugin follows WordPress coding standards and uses:
- Strict types (`declare(strict_types=1)`)
- Type hints on all parameters and returns
- `$wpdb->prepare()` for all SQL queries
- WordPress object cache integration

## Version 1.2.0

### Completed Features

✅ **Core Architecture:**
- Database layer with WordPress prefix support
- Domain models (EntityId, SagaId, ImportanceScore, SagaEntity)
- Repository interfaces and MariaDB implementations
- Plugin skeleton with activation/deactivation hooks
- Database schema creation via dbDelta
- Transaction support with rollback

✅ **v1.2.0 Improvements:**
- REST API integration tests for all endpoints
- Bulk operations with proper cache invalidation
- EAV bulk fetch optimization with attribute caching
- Query performance logging (target: sub-50ms)
- Multisite test fixtures
- Infrastructure layer exceptions (DatabaseException moved from Domain)

## Security

- All database queries use `$wpdb->prepare()` for SQL injection prevention
- Capability checks on all admin actions
- Nonce verification on forms
- No hardcoded credentials

## Performance

- WordPress object cache integration
- Sub-50ms query target
- Transaction support for data integrity
- Optimized indexes on all tables

## License

GPL v2 or later

## Support

For issues and feature requests, please use the GitHub issue tracker.

## Credits

Built following WordPress and PHP 8.2 best practices with hexagonal architecture principles.
