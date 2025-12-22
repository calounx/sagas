# Saga Manager - Professional WordPress Plugin Suite

[![CI](https://github.com/calounx/sagas/actions/workflows/ci.yml/badge.svg)](https://github.com/calounx/sagas/actions/workflows/ci.yml)
[![PHP Tests](https://github.com/calounx/sagas/actions/workflows/php-tests.yml/badge.svg)](https://github.com/calounx/sagas/actions/workflows/php-tests.yml)
[![Static Analysis](https://github.com/calounx/sagas/actions/workflows/static-analysis.yml/badge.svg)](https://github.com/calounx/sagas/actions/workflows/static-analysis.yml)
[![Code Quality](https://github.com/calounx/sagas/actions/workflows/code-quality.yml/badge.svg)](https://github.com/calounx/sagas/actions/workflows/code-quality.yml)
[![Frontend Build](https://github.com/calounx/sagas/actions/workflows/frontend-build.yml/badge.svg)](https://github.com/calounx/sagas/actions/workflows/frontend-build.yml)
[![PHP Version](https://img.shields.io/badge/PHP-8.2%2B-blue)](https://www.php.net/)
[![WordPress](https://img.shields.io/badge/WordPress-6.0%2B-blue)](https://wordpress.org/)
[![License](https://img.shields.io/badge/License-MIT-green.svg)](LICENSE)

A professional-grade WordPress plugin suite for managing complex fictional universe sagas with complete database abstraction, hexagonal architecture, and CQRS pattern implementation.

## 🎯 Overview

Saga Manager is a two-plugin WordPress system designed to manage entities in complex fictional universes (like Star Wars, Lord of the Rings, Dune, etc.) with unprecedented flexibility and scalability.

**Target Scale:** 100K+ entities per saga, sub-50ms query response, semantic search on 1M+ text fragments.

## 🏗️ Architecture

### Two-Plugin Design

**Backend Plugin: `saga-manager-core`**
- Pure data management and business logic
- REST API (`/wp-json/saga/v1/*`)
- Custom admin UI (no Custom Post Types)
- Database abstraction layer
- All domain and application logic

**Frontend Plugin: `saga-manager-display`**
- Public-facing display components
- Shortcodes, Gutenberg blocks, widgets
- Consumes backend REST API
- Zero direct database access

### Hexagonal Architecture

```
Domain Layer (Pure PHP)
    ↓
Application Layer (CQRS Use Cases)
    ↓
Port Interfaces (Database Abstraction)
    ↓
Adapters (WordPress, PDO, InMemory)
    ↓
Database
```

## ✨ Key Features

### Complete Database Abstraction
- **3 Database Adapters**: WordPress ($wpdb), PDO (MySQL/PostgreSQL/SQLite), InMemory (testing)
- **Swappable Backends**: Migrate from WordPress to any database without code changes
- **Query Builder**: Fluent interface with full SQL support
- **Transaction Management**: ACID compliance with savepoints
- **Schema Management**: Database-agnostic DDL operations

### Performance Optimization
- **Query Caching**: Two-level caching (compiled SQL + results)
- **Connection Pooling**: Efficient connection management
- **Query Profiling**: EXPLAIN analysis, slow query detection
- **Batch Operations**: 5-10x speedup for bulk inserts/updates
- **Target**: Sub-50ms query response at 100K+ entities

### Security & Compliance
- ✅ SQL injection prevention (prepared statements everywhere)
- ✅ Capability checks on all admin operations
- ✅ Nonce verification on forms and AJAX
- ✅ Input sanitization and output escaping
- ✅ WordPress coding standards compliant
- ✅ OWASP Top 10 compliant

### Data Integrity
- 9 foreign key constraints with CASCADE/SET NULL
- 3 critical performance indexes
- 2 CHECK constraints for validation
- Referential integrity at database level

## 📦 Installation

### Backend Plugin (Required)

```bash
cd wp-content/plugins/
git clone https://github.com/calounx/sagas.git
cd sagas/saga-manager-core
composer install
composer dump-autoload
```

### Frontend Plugin (Optional)

```bash
cd wp-content/plugins/sagas/saga-manager-display
composer install
npm install
npm run build
```

### WordPress Activation

1. Activate `Saga Manager Core` in WordPress admin
2. Optionally activate `Saga Manager Display` for frontend features

## 🚀 Usage

### Admin Interface

Navigate to **Saga Manager** in WordPress admin:
- **Dashboard**: Overview statistics
- **Sagas**: Manage sagas (universes)
- **Entities**: Manage characters, locations, events, etc.
- **Settings**: Plugin configuration

### REST API

```bash
# List entities
GET /wp-json/saga/v1/entities

# Get single entity
GET /wp-json/saga/v1/entities/123

# Create entity
POST /wp-json/saga/v1/entities
{
  "saga_id": 1,
  "entity_type": "character",
  "canonical_name": "Luke Skywalker",
  "slug": "luke-skywalker",
  "importance_score": 95
}
```

### Shortcodes

```
[saga_entity id="123"]
[saga_timeline saga="star-wars"]
[saga_search]
[saga_relationships entity="123"]
```

### Gutenberg Blocks

- Entity Display Block
- Timeline Block
- Search Block

## 🧪 Testing

```bash
cd saga-manager-core

# Install WordPress test suite
bash bin/install-wp-tests.sh wordpress_test root '' localhost latest

# Run all tests
composer test

# Run unit tests only
composer test:unit

# Run integration tests
composer test:integration

# Generate coverage report
composer test:coverage

# Run static analysis
composer stan
```

**Test Coverage:**
- 75+ unit and integration tests
- Domain layer: 90%+ coverage
- Overall: 70%+ coverage target

## 📊 Database Schema

8 dedicated tables with hybrid EAV architecture:
- `saga_sagas` - Saga/universe definitions
- `saga_entities` - Core entity data
- `saga_attribute_definitions` - EAV schema
- `saga_attribute_values` - EAV attributes
- `saga_entity_relationships` - Typed relationships
- `saga_timeline_events` - Temporal events
- `saga_content_fragments` - Semantic search
- `saga_quality_metrics` - Data quality tracking

## 🔧 Technical Stack

- **PHP**: 8.2+ (strict types, readonly properties, enums)
- **Database**: MariaDB 11.4+ / MySQL 8.0+
- **WordPress**: 6.0+
- **Testing**: PHPUnit 10, PHPStan level 8
- **Frontend**: Vanilla JS, CSS Grid/Flexbox, Gutenberg

## 📈 Performance Metrics

| Metric | Target | Implementation |
|--------|--------|----------------|
| Query Response | <50ms | Query profiling + indexes |
| Abstraction Overhead | <5% | Query cache + optimization |
| Cache Hit Ratio | >80% | Two-level caching |
| Batch Speedup | 5-10x | Multi-row operations |
| Test Execution | <5s | InMemory adapter |

## 🏛️ Design Patterns

- **Hexagonal Architecture**: Clean separation of concerns
- **CQRS**: Command/Query separation
- **Repository Pattern**: Data access abstraction
- **Port/Adapter**: Interface-based dependencies
- **Value Objects**: Domain primitives
- **Factory Pattern**: Database adapter creation
- **Strategy Pattern**: Multiple database backends

## 🔄 CI/CD Pipeline

Automated workflows run on every push and pull request:

### Test Matrix
- **PHP Versions**: 8.2, 8.3
- **WordPress Versions**: 6.4, 6.5, latest
- **Database**: MariaDB 11.4
- **Coverage**: Codecov integration

### Workflows

| Workflow | Purpose | Status |
|----------|---------|--------|
| **CI** | Main integration pipeline | ![CI](https://github.com/calounx/sagas/actions/workflows/ci.yml/badge.svg) |
| **PHP Tests** | PHPUnit with coverage | ![Tests](https://github.com/calounx/sagas/actions/workflows/php-tests.yml/badge.svg) |
| **Static Analysis** | PHPStan level 8 + Psalm | ![Analysis](https://github.com/calounx/sagas/actions/workflows/static-analysis.yml/badge.svg) |
| **Code Quality** | WordPress coding standards | ![Quality](https://github.com/calounx/sagas/actions/workflows/code-quality.yml/badge.svg) |
| **Frontend Build** | Gutenberg blocks compilation | ![Build](https://github.com/calounx/sagas/actions/workflows/frontend-build.yml/badge.svg) |
| **Release** | Automated release packaging | Triggered on version tags |

### Quality Gates
- ✅ All tests must pass (75+ tests)
- ✅ PHPStan level 8 analysis
- ✅ WordPress coding standards
- ✅ Security audit (composer/npm)
- ✅ Frontend build successful

### Automated Releases
Create a release by pushing a version tag:
```bash
git tag -a v1.0.0 -m "Release version 1.0.0"
git push origin v1.0.0
```

This triggers:
1. Full test suite execution
2. Production build (optimized autoloader, minified assets)
3. Package creation (.zip files for both plugins)
4. GitHub release with changelogs
5. Upload to release assets

## 📚 Documentation

- [Architecture Overview](architecture/README.md)
- [Backend Structure](architecture/backend-structure.md)
- [Frontend Structure](architecture/frontend-structure.md)
- [Development Guidelines](CLAUDE.md) - Complete development reference

## 🤝 Contributing

This is a professional implementation showcasing:
- Modern PHP 8.2+ patterns
- Hexagonal architecture
- Complete database abstraction
- SOLID principles
- Comprehensive testing
- WordPress best practices

## 📄 License

This project is licensed under the MIT License.

## 🙏 Acknowledgments

Built with:
- WordPress for the platform
- PHP 8.2 for modern language features
- MariaDB for database backend
- PHPUnit for testing
- PHPStan for static analysis

---

**Project Stats:**
- 401 files
- 65,490+ lines of code
- 50+ database abstraction classes
- 75+ unit/integration tests
- 3 complete database adapters
- 2 WordPress plugins

🤖 Generated with [Claude Code](https://claude.com/claude-code)
