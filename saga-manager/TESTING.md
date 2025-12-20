# Testing Quick Reference

## Setup (One-Time)

```bash
# 1. Install dependencies
composer install

# 2. Install WordPress test suite
bash bin/install-wp-tests.sh wordpress_test root '' localhost latest
```

## Run Tests

```bash
# All tests
composer test

# Unit tests only (fast, no WordPress)
composer test:unit

# Integration tests only (requires WordPress & MySQL)
composer test:integration

# Generate coverage report
composer test:coverage
```

## Static Analysis

```bash
# PHPStan (level 8)
composer stan

# Code style check
composer cs

# Auto-fix code style
composer cs:fix
```

## Test Structure

```
tests/
├── Unit/                    # Pure PHP tests (90%+ coverage)
│   └── Domain/Entity/      # EntityId, SagaId, ImportanceScore, SagaEntity
├── Integration/             # WordPress integration tests
│   └── Infrastructure/     # MariaDBEntityRepository
└── Fixtures/               # SagaFixtures (Star Wars test data)
```

## Coverage Targets

- Domain Layer: 90%+ (REQUIRED)
- Infrastructure: 90%+ (REQUIRED)
- WordPress Integration: 80%+
- Overall: 70%+

## Key Test Files

| File | Purpose | Tests |
|------|---------|-------|
| EntityIdTest.php | Value object validation | 9 tests |
| SagaIdTest.php | Value object validation | 9 tests |
| ImportanceScoreTest.php | Score validation, predicates | 14 tests with data providers |
| SagaEntityTest.php | Domain model, mutations | 20+ tests |
| MariaDBEntityRepositoryTest.php | CRUD, transactions, cache | 25+ tests |

## Common Commands

```bash
# Run specific test file
vendor/bin/phpunit tests/Unit/Domain/Entity/EntityIdTest.php

# Run specific test method
vendor/bin/phpunit --filter test_constructs_with_valid_positive_id

# Verbose output
vendor/bin/phpunit --verbose

# Stop on first failure
vendor/bin/phpunit --stop-on-failure

# Debug mode
vendor/bin/phpunit --debug
```

## Performance Targets

- Query execution: <50ms
- Single entity queries: 1 query only
- Cache hit rate: 80%+
- N+1 queries: ZERO

## Critical Test Coverage

### WordPress Compliance
- [x] Table prefix handling ($wpdb->prefix)
- [x] SQL injection prevention (wpdb->prepare)
- [x] Transactions with rollback
- [x] Cache integration (wp_cache)
- [x] Foreign key cascades

### Domain Rules
- [x] Positive ID validation
- [x] Score range validation (0-100)
- [x] Slug format validation
- [x] Name length validation
- [x] Entity immutability (ID cannot change)

### Edge Cases
- [x] Boundary values (0, 100, 255 chars)
- [x] Null handling
- [x] Empty strings
- [x] Duplicates
- [x] Whitespace trimming

## Troubleshooting

### WordPress test suite not found
```bash
bash bin/install-wp-tests.sh wordpress_test root '' localhost latest
```

### Database connection error
Check credentials in `tests/wp-config.php`

### Memory limit
Increase in `phpunit.xml`: `memory_limit=256M`

### Cache issues
```php
wp_cache_flush();
```

## CI/CD Integration

See `tests/README.md` for GitHub Actions configuration example.

## Resources

- Full documentation: `tests/README.md`
- PHPUnit: https://phpunit.de/
- WordPress Testing: https://make.wordpress.org/core/handbook/testing/
- PHPStan: https://phpstan.org/
