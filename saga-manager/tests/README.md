# Saga Manager Test Suite

Comprehensive testing infrastructure for the Saga Manager WordPress plugin following the test pyramid approach.

## Test Strategy

- **Unit Tests** (tests/Unit/): Domain layer - 90%+ coverage target
- **Integration Tests** (tests/Integration/): Infrastructure with WordPress - actual database operations
- **Test Fixtures**: Reusable sample saga data for integration tests

## Setup

### 1. Install Dependencies

```bash
composer install
```

### 2. Install WordPress Test Suite

```bash
bash bin/install-wp-tests.sh wordpress_test root '' localhost latest
```

Parameters:
- `wordpress_test`: Test database name
- `root`: MySQL username
- `''`: MySQL password (empty)
- `localhost`: MySQL host
- `latest`: WordPress version (or specific version like 6.4)

### 3. Configure Database

If using different credentials, update environment variables:

```bash
export WP_DB_NAME=wordpress_test
export WP_DB_USER=root
export WP_DB_PASSWORD=''
export WP_DB_HOST=localhost
```

## Running Tests

### All Tests

```bash
composer test
# or
vendor/bin/phpunit
```

### Unit Tests Only (No WordPress Dependency)

```bash
composer test:unit
# or
vendor/bin/phpunit --testsuite=Unit
```

### Integration Tests Only

```bash
composer test:integration
# or
vendor/bin/phpunit --testsuite=Integration
```

### Specific Test File

```bash
vendor/bin/phpunit tests/Unit/Domain/Entity/EntityIdTest.php
```

### Specific Test Method

```bash
vendor/bin/phpunit --filter test_constructs_with_valid_positive_id
```

### With Coverage Report

```bash
composer test:coverage
# Opens coverage/html/index.html
```

## Test Organization

```
tests/
├── Unit/                          # Pure PHP unit tests (no WordPress)
│   └── Domain/
│       └── Entity/
│           ├── EntityIdTest.php        # 100% coverage
│           ├── SagaIdTest.php          # 100% coverage
│           ├── ImportanceScoreTest.php # 100% coverage with data providers
│           └── SagaEntityTest.php      # Comprehensive domain model tests
│
├── Integration/                   # WordPress integration tests
│   └── Infrastructure/
│       └── Repository/
│           └── MariaDBEntityRepositoryTest.php # Database operations, transactions
│
├── Fixtures/                      # Reusable test data
│   └── SagaFixtures.php          # Star Wars test saga (5 entities, 3 relationships)
│
├── bootstrap.php                  # PHPUnit bootstrap
├── wp-config.php                  # WordPress test config
└── README.md                      # This file
```

## Test Coverage Requirements

| Component | Target Coverage | Current Status |
|-----------|----------------|----------------|
| Domain Entities | 90%+ | Unit tests complete |
| Value Objects | 100% | Unit tests complete |
| Repositories | 90%+ | Integration tests complete |
| WordPress Integration | 80%+ | Integration tests complete |
| Overall | 70%+ | Comprehensive suite |

## Writing Tests

### Unit Test Example

```php
<?php
declare(strict_types=1);

namespace SagaManager\Tests\Unit\Domain\Entity;

use PHPUnit\Framework\TestCase;
use SagaManager\Domain\Entity\EntityId;
use SagaManager\Domain\Exception\ValidationException;

final class EntityIdTest extends TestCase
{
    public function test_constructs_with_valid_positive_id(): void
    {
        $id = new EntityId(1);
        $this->assertSame(1, $id->value());
    }

    public function test_throws_exception_for_zero_id(): void
    {
        $this->expectException(ValidationException::class);
        new EntityId(0);
    }
}
```

### Integration Test Example

```php
<?php
declare(strict_types=1);

namespace SagaManager\Tests\Integration\Infrastructure\Repository;

use WP_UnitTestCase; // WordPress test case
use SagaManager\Domain\Entity\EntityId;
use SagaManager\Infrastructure\Repository\MariaDBEntityRepository;
use SagaManager\Tests\Fixtures\SagaFixtures;

final class MariaDBEntityRepositoryTest extends WP_UnitTestCase
{
    private MariaDBEntityRepository $repository;
    private int $sagaId;

    public function set_up(): void
    {
        parent::set_up();
        $this->repository = new MariaDBEntityRepository();
        $this->sagaId = SagaFixtures::loadStarWarsSaga();
        wp_cache_flush();
    }

    public function tear_down(): void
    {
        SagaFixtures::cleanup();
        wp_cache_flush();
        parent::tear_down();
    }

    public function test_find_by_id_returns_entity(): void
    {
        $lukeId = SagaFixtures::getEntityId('luke');
        $entity = $this->repository->findById(new EntityId($lukeId));

        $this->assertSame('Luke Skywalker', $entity->getCanonicalName());
    }
}
```

## Critical Test Cases

### WordPress Integration
- [x] Table prefix handling (wp_, custom prefixes)
- [x] Multisite compatibility verification
- [x] SQL injection prevention (wpdb->prepare)
- [x] Transaction rollback on errors
- [x] Cache invalidation
- [x] Foreign key cascade deletes

### Domain Logic
- [x] Value object validation (positive IDs, score ranges)
- [x] Entity immutability rules (ID cannot change)
- [x] Business rules (slug format, name length)
- [x] Edge cases (boundary values, null handling)

### Performance
- [x] Query optimization (<50ms target)
- [x] Cache hit rates
- [x] N+1 query prevention

## Static Analysis

### PHPStan (Level 8)

```bash
composer stan
# or
vendor/bin/phpstan analyse
```

Configuration: `phpstan.neon`

### Code Style (WordPress Standards)

```bash
# Check code style
composer cs

# Auto-fix code style issues
composer cs:fix
```

## Continuous Integration

### GitHub Actions Example

```yaml
name: Tests

on: [push, pull_request]

jobs:
  phpunit:
    runs-on: ubuntu-latest

    services:
      mysql:
        image: mariadb:11.4
        env:
          MYSQL_ROOT_PASSWORD: root
          MYSQL_DATABASE: wordpress_test
        ports:
          - 3306:3306

    steps:
      - uses: actions/checkout@v3

      - name: Setup PHP
        uses: shivammathur/setup-php@v2
        with:
          php-version: '8.2'
          coverage: xdebug

      - name: Install dependencies
        run: composer install

      - name: Install WordPress test suite
        run: bash bin/install-wp-tests.sh wordpress_test root root 127.0.0.1 latest

      - name: Run tests
        run: composer test

      - name: Run static analysis
        run: composer stan
```

## Test Data Fixtures

### Star Wars Saga (Default)

```php
use SagaManager\Tests\Fixtures\SagaFixtures;

// Load complete Star Wars test data
$sagaId = SagaFixtures::loadStarWarsSaga();

// Get specific entity IDs
$lukeId = SagaFixtures::getEntityId('luke');
$vaderId = SagaFixtures::getEntityId('vader');
$tatooineId = SagaFixtures::getEntityId('tatooine');

// Clean up after test
SagaFixtures::cleanup();
```

Includes:
- 1 saga (Star Wars)
- 5 entities:
  - 2 characters (Luke Skywalker, Darth Vader)
  - 1 location (Tatooine)
  - 1 event (Battle of Yavin)
  - 1 faction (Rebel Alliance)
- 3 relationships:
  - Vader -> Luke (parent_of)
  - Luke -> Rebel Alliance (member_of)
  - Luke -> Tatooine (born_on)

## Debugging Tests

### Verbose Output

```bash
vendor/bin/phpunit --verbose
```

### Stop on Failure

```bash
vendor/bin/phpunit --stop-on-failure
```

### Debug Specific Test

```bash
vendor/bin/phpunit --filter test_name --debug
```

### WordPress Debug Log

Enable in `tests/wp-config.php`:

```php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
define('WP_DEBUG_DISPLAY', true);
```

Check logs: `/tmp/wordpress/wp-content/debug.log`

## Performance Testing

### Query Count Verification

```php
public function test_query_performance(): void
{
    global $wpdb;

    $start = microtime(true);
    $before_queries = $wpdb->num_queries;

    $entity = $this->repository->findById(new EntityId(1));

    $duration = (microtime(true) - $start) * 1000;
    $query_count = $wpdb->num_queries - $before_queries;

    $this->assertLessThan(50, $duration); // <50ms
    $this->assertEquals(1, $query_count);  // Single query
}
```

## Common Issues

### WordPress Test Suite Not Found

```bash
# Reinstall
bash bin/install-wp-tests.sh wordpress_test root '' localhost latest
```

### Database Connection Errors

Check credentials in environment or update `tests/wp-config.php`.

### Memory Limit

Increase in `phpunit.xml`:

```xml
<php>
    <ini name="memory_limit" value="256M"/>
</php>
```

### Cache Issues

Clear WordPress object cache:

```php
wp_cache_flush();
```

## Resources

- [PHPUnit Documentation](https://phpunit.de/documentation.html)
- [WordPress Plugin Handbook - Testing](https://developer.wordpress.org/plugins/testing/)
- [WordPress PHPUnit Test Suite](https://make.wordpress.org/core/handbook/testing/automated-testing/phpunit/)
- [PHPStan Documentation](https://phpstan.org/user-guide/getting-started)
