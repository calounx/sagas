# Saga Manager Testing Guide

Complete guide for testing the Saga Manager Theme using Docker-based test environment.

## Quick Start

```bash
# Complete setup and test run
make init

# That's it! This command will:
# 1. Build Docker containers
# 2. Start services (WordPress + MariaDB)
# 3. Install dependencies (Composer + WordPress test suite)
# 4. Run all tests
```

## Table of Contents

- [Prerequisites](#prerequisites)
- [Docker Test Environment](#docker-test-environment)
- [Running Tests](#running-tests)
- [Test Suites](#test-suites)
- [Code Coverage](#code-coverage)
- [Development Workflow](#development-workflow)
- [Troubleshooting](#troubleshooting)
- [Advanced Usage](#advanced-usage)

## Prerequisites

**Required:**
- Docker 20.10+
- Docker Compose 2.0+
- Make (GNU Make)

**Optional:**
- `entr` for watch mode (install via package manager)

**Verify installation:**
```bash
docker --version          # Should show 20.10+
docker-compose --version  # Should show 2.0+
make --version           # Should show GNU Make
```

## Docker Test Environment

### Architecture

The test environment consists of 3 Docker containers:

1. **MariaDB 11.4** - Test database
   - Database: `wordpress_test`
   - User: `wordpress`
   - Password: `wordpress`
   - Port: 3307 (host) → 3306 (container)

2. **WordPress (PHP 8.2)** - WordPress core
   - PHP 8.2 with Apache
   - WordPress latest version
   - Port: 8080 (host) → 80 (container)

3. **PHPUnit Runner** - Test execution
   - PHP 8.2-CLI
   - Xdebug 3.3.0 (for coverage)
   - Composer 2.x
   - WP-CLI latest

### Container Features

**Xdebug Configuration:**
- Mode: `coverage` (enabled for code coverage)
- Compatible with PHPUnit 10.5
- HTML/XML/Text coverage reports

**Volume Mounts:**
- Project directory: `/app`
- Test results: `./tests/results` (for coverage reports)

**Health Checks:**
- MariaDB: Checks InnoDB initialization
- Interval: 10s, Timeout: 5s, Retries: 5

## Running Tests

### Available Commands

View all commands:
```bash
make help
```

### Essential Commands

```bash
# Initial setup (first time only)
make init              # Build, start, install, test

# Start containers
make up                # Start all containers in background

# Stop containers
make down              # Stop and remove containers

# Run tests
make test              # Run all tests (unit + integration)
make test-unit         # Run unit tests only
make test-integration  # Run integration tests only

# Generate coverage
make test-coverage     # Generate HTML coverage report

# Code quality
make lint              # Run PHP CodeSniffer (WordPress standards)
make lint-fix          # Auto-fix code style issues
make analyse           # Run PHPStan static analysis (level 5)
```

### Test Output

**Successful test run:**
```
PHPUnit 10.5.29 by Sebastian Bergmann and contributors.

Runtime:       PHP 8.2.24
Configuration: /app/phpunit.xml

............................................................  60 / 126 ( 47%)
............................................................  120 / 126 ( 95%)
......                                                         126 / 126 (100%)

Time: 00:02.456, Memory: 32.00 MB

OK (126 tests, 487 assertions)
```

## Test Suites

### Unit Tests (95 tests)

**Location:** `tests/unit/`

**Features tested:**
- AI Consistency Guardian (23 tests)
- Entity Extractor (41 tests)
- Predictive Relationships (31 tests)

**Characteristics:**
- No database dependencies
- No WordPress dependencies (pure PHP)
- Fast execution (<1 second)
- Test value objects, validation, business logic

**Run unit tests:**
```bash
make test-unit

# Or specific file
docker-compose -f docker-compose.test.yml exec phpunit \
  vendor/bin/phpunit tests/unit/EntityExtractor/ExtractionJobTest.php
```

### Integration Tests (31 tests)

**Location:** `tests/integration/`

**Features tested:**
- Database operations (WordPress $wpdb)
- WordPress integration (table prefixes, caching)
- Complete workflows (extraction, prediction, consistency checking)

**Characteristics:**
- Uses WordPress test database
- Tests real database queries
- Tests cache integration
- Slower execution (2-3 seconds)

**Run integration tests:**
```bash
make test-integration
```

### Test Coverage

**Current coverage (Phase 2 features):**
- Consistency Guardian: 92%
- Entity Extractor: 88%
- Predictive Relationships: 85%
- Overall: 87%

**Coverage goals:**
- Critical paths (CRUD): 100%
- WordPress integration: 90%
- Error handling: 80%
- Overall target: 70%+

## Code Coverage

### Generate Coverage Report

```bash
# HTML report (recommended)
make test-coverage

# View report
open tests/results/coverage-html/index.html
```

**Coverage report includes:**
- Line coverage percentage
- Branch coverage
- Complexity metrics
- Uncovered lines highlighted in red

### Coverage Output Formats

**HTML report:**
- Location: `tests/results/coverage-html/index.html`
- Interactive, browse by file/class
- Shows covered/uncovered lines with colors

**XML report (Clover):**
- Location: `tests/results/coverage.xml`
- CI/CD integration
- Code quality tools (SonarQube, Codecov)

**Text report:**
```bash
docker-compose -f docker-compose.test.yml exec phpunit \
  vendor/bin/phpunit --coverage-text
```

## Development Workflow

### Typical Workflow

**1. Start environment:**
```bash
make up
```

**2. Write feature code:**
```bash
# Edit PHP files in inc/
vim inc/ai/YourNewService.php
```

**3. Write tests:**
```bash
# Create test file
vim tests/unit/YourFeature/YourServiceTest.php
```

**4. Run tests:**
```bash
make test-unit
```

**5. Fix failures and repeat:**
```bash
# Fix code, re-run tests
make test-unit
```

**6. Check coverage:**
```bash
make test-coverage
open tests/results/coverage-html/index.html
```

**7. Code quality checks:**
```bash
make lint      # Check WordPress coding standards
make analyse   # PHPStan static analysis
```

**8. Cleanup:**
```bash
make down
```

### Watch Mode (Auto-run tests on file changes)

**Requires `entr` installed:**
```bash
# Install entr
sudo apt-get install entr  # Debian/Ubuntu
brew install entr          # macOS

# Watch PHP files and auto-run tests
make test-watch
```

This monitors `inc/` and `tests/` directories and reruns unit tests on any `.php` file change.

## Troubleshooting

### Container Issues

**Containers won't start:**
```bash
# Check logs
make logs

# Rebuild from scratch
make clean
make build
make up
```

**Database connection errors:**
```bash
# Check database health
docker-compose -f docker-compose.test.yml ps

# Restart database
docker-compose -f docker-compose.test.yml restart db

# Reset database
make reset-db
```

**Permission errors:**
```bash
# Fix permissions on test results directory
chmod -R 777 tests/results/
```

### Test Failures

**WordPress test suite not found:**
```bash
# Reinstall WordPress test suite
make install
```

**Database tables missing:**
```bash
# Verify migrations ran
make shell-phpunit
php -r "require 'vendor/autoload.php';
  \SagaManager\AI\ConsistencyGuardian\ConsistencyGuardianMigrator::migrate();"
exit
```

**Cache issues:**
```bash
# Clear WordPress object cache
make shell-phpunit
wp cache flush --allow-root
exit
```

**Xdebug not working:**
```bash
# Verify Xdebug is loaded
docker-compose -f docker-compose.test.yml exec phpunit php -v
# Should show "with Xdebug v3.3.0"

# Check Xdebug mode
docker-compose -f docker-compose.test.yml exec phpunit php -i | grep xdebug.mode
# Should show "xdebug.mode => coverage"
```

### Common Errors

**Error: "No such file or directory: /tmp/wordpress-tests-lib"**
```bash
# Solution: Install WordPress test suite
make install
```

**Error: "Class not found"**
```bash
# Solution: Regenerate autoloader
docker-compose -f docker-compose.test.yml exec phpunit composer dump-autoload
```

**Error: "Table doesn't exist"**
```bash
# Solution: Run migrations
make shell-phpunit
vendor/bin/phpunit --filter test_table_exists
exit
```

## Advanced Usage

### Interactive Shell Access

**PHPUnit container:**
```bash
make shell-phpunit

# Inside container:
vendor/bin/phpunit                    # Run all tests
vendor/bin/phpunit --filter test_name # Run specific test
wp --allow-root plugin list           # WP-CLI commands
exit
```

**WordPress container:**
```bash
make shell

# Inside container:
wp --allow-root core version          # Check WordPress version
wp --allow-root db query "SHOW TABLES" # Database queries
exit
```

**Database shell:**
```bash
make db-shell

# Inside MySQL:
SHOW TABLES;
SELECT * FROM wp_saga_entities LIMIT 10;
exit
```

### Custom Test Commands

**Run specific test:**
```bash
docker-compose -f docker-compose.test.yml exec phpunit \
  vendor/bin/phpunit --filter test_can_create_extraction_job
```

**Run tests with testdox (readable output):**
```bash
docker-compose -f docker-compose.test.yml exec phpunit \
  vendor/bin/phpunit --testdox
```

**Stop on first failure:**
```bash
docker-compose -f docker-compose.test.yml exec phpunit \
  vendor/bin/phpunit --stop-on-failure
```

**Debug mode:**
```bash
docker-compose -f docker-compose.test.yml exec phpunit \
  vendor/bin/phpunit --debug
```

### Composer Commands

**Install/update dependencies:**
```bash
docker-compose -f docker-compose.test.yml exec phpunit composer install
docker-compose -f docker-compose.test.yml exec phpunit composer update
```

**Run composer scripts:**
```bash
docker-compose -f docker-compose.test.yml exec phpunit composer test
docker-compose -f docker-compose.test.yml exec phpunit composer lint
docker-compose -f docker-compose.test.yml exec phpunit composer analyse
```

### Performance Testing

**Measure test execution time:**
```bash
docker-compose -f docker-compose.test.yml exec phpunit \
  time vendor/bin/phpunit
```

**Profile slow tests:**
```bash
docker-compose -f docker-compose.test.yml exec phpunit \
  vendor/bin/phpunit --log-junit tests/results/junit.xml
```

### Continuous Integration

**GitHub Actions example:**
```yaml
name: Tests

on: [push, pull_request]

jobs:
  test:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v3

      - name: Build containers
        run: make build

      - name: Start services
        run: make up

      - name: Install dependencies
        run: make install

      - name: Run tests
        run: make test

      - name: Generate coverage
        run: make test-coverage

      - name: Upload coverage
        uses: codecov/codecov-action@v3
        with:
          files: tests/results/coverage.xml
```

## Container Management

### View Container Status

```bash
make status

# Shows:
# - Container names
# - Status (Up/Down)
# - Ports
# - Health
```

### View Logs

```bash
# All containers
make logs

# Specific container
docker-compose -f docker-compose.test.yml logs -f phpunit
docker-compose -f docker-compose.test.yml logs -f db
```

### Restart Containers

```bash
# All containers
make restart

# Specific container
docker-compose -f docker-compose.test.yml restart phpunit
```

### Clean Up

**Remove containers and volumes:**
```bash
make clean

# Confirmation required, then:
# - Stops containers
# - Removes volumes (database data lost)
# - Removes test results
```

**Rebuild completely:**
```bash
make clean
make init
```

## Best Practices

### Before Committing

1. **Run all tests:**
   ```bash
   make test
   ```

2. **Check code style:**
   ```bash
   make lint
   ```

3. **Static analysis:**
   ```bash
   make analyse
   ```

4. **Verify coverage:**
   ```bash
   make test-coverage
   # Ensure coverage didn't drop
   ```

### Writing Tests

1. **Use factory methods:**
   ```php
   $saga_id = $this->createSaga();
   $entity_id = $this->createEntity($saga_id);
   ```

2. **Clean up in tearDown:**
   ```php
   public function tearDown(): void
   {
       parent::tearDown();
       // Automatic cleanup via TestCase::clean_database()
   }
   ```

3. **Test one thing per test:**
   ```php
   public function test_can_create_entity(): void
   {
       // Test ONLY entity creation
   }

   public function test_validates_entity_name(): void
   {
       // Test ONLY name validation
   }
   ```

4. **Use descriptive test names:**
   ```php
   // Good
   public function test_extraction_job_calculates_progress_percentage(): void

   // Bad
   public function test_job(): void
   ```

### Performance Tips

1. **Run unit tests first** (faster feedback):
   ```bash
   make test-unit && make test-integration
   ```

2. **Use watch mode during development:**
   ```bash
   make test-watch
   ```

3. **Run specific tests when debugging:**
   ```bash
   docker-compose -f docker-compose.test.yml exec phpunit \
     vendor/bin/phpunit --filter test_specific_feature
   ```

4. **Use `--stop-on-failure` to save time:**
   ```bash
   docker-compose -f docker-compose.test.yml exec phpunit \
     vendor/bin/phpunit --stop-on-failure
   ```

## Test Data Factories

### Available Factories

All factories available via `FactoryTrait`:

```php
// Create saga
$saga_id = $this->createSaga([
    'name' => 'Star Wars',
    'universe' => 'Star Wars',
    'calendar_type' => 'epoch_relative'
]);

// Create entity
$entity_id = $this->createEntity($saga_id, [
    'entity_type' => 'character',
    'canonical_name' => 'Luke Skywalker',
    'importance_score' => 95
]);

// Create multiple entities
$entity_ids = $this->createEntities($saga_id, 10);

// Create extraction job
$job_id = $this->createExtractionJob($saga_id, $user_id, [
    'source_text' => 'Long text...',
    'chunk_size' => 5000,
    'status' => 'pending'
]);

// Create extracted entity
$extracted_id = $this->createExtractedEntity($job_id, [
    'entity_type' => 'character',
    'canonical_name' => 'Paul Atreides',
    'confidence_score' => 92.5
]);

// Create relationship suggestion
$suggestion_id = $this->createRelationshipSuggestion(
    $saga_id,
    $source_entity_id,
    $target_entity_id,
    [
        'suggested_type' => 'ally',
        'confidence_score' => 85.0,
        'status' => 'pending'
    ]
);

// Create consistency issue
$issue_id = $this->createConsistencyIssue($saga_id, [
    'issue_type' => 'timeline',
    'severity' => 'high',
    'description' => 'Date inconsistency detected',
    'status' => 'open'
]);
```

## Resources

- **PHPUnit Documentation:** https://phpunit.de/documentation.html
- **WordPress Testing:** https://make.wordpress.org/core/handbook/testing/automated-testing/phpunit/
- **Docker Compose:** https://docs.docker.com/compose/
- **Xdebug:** https://xdebug.org/docs/
- **Project Guidelines:** [CLAUDE.md](./CLAUDE.md)
- **Test Suite Details:** [tests/README.md](./tests/README.md)

## Support

For issues or questions:
1. Check [Troubleshooting](#troubleshooting) section
2. Review [tests/README.md](./tests/README.md)
3. Check Docker logs: `make logs`
4. Open issue on GitHub

## Quick Reference

```bash
# Setup
make init              # Complete setup

# Daily workflow
make up                # Start
make test              # Test
make down              # Stop

# Development
make test-unit         # Fast tests
make test-coverage     # Coverage
make lint              # Code style
make analyse           # Static analysis

# Debugging
make shell-phpunit     # Container shell
make logs              # View logs
make db-shell          # Database shell

# Cleanup
make clean             # Remove all
```
