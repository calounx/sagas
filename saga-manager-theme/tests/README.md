# Saga Manager Test Suite

Comprehensive unit and integration tests for Saga Manager Phase 2 AI features.

## Test Coverage

### Unit Tests (tests/unit/)

Value object tests verifying immutability, validation, and business logic:

#### ConsistencyGuardian (`tests/unit/ConsistencyGuardian/`)
- **ConsistencyIssueTest.php** - 30+ tests
  - Construction and validation
  - Issue type validation (timeline, character, location, relationship, logical)
  - Severity validation (critical, high, medium, low, info)
  - Status transitions (open → resolved, dismissed, false_positive)
  - AI confidence validation (0.00-1.00 range)
  - Array conversions (toArray, fromDatabase)
  - Edge cases (empty context, complex JSON data)

#### EntityExtractor (`tests/unit/EntityExtractor/`)
- **ExtractionJobTest.php** - 25+ tests
  - Job creation and validation
  - Chunk size validation (100-50000)
  - Progress percentage calculation
  - Acceptance rate calculation (entities_created / total)
  - Cost per entity calculation
  - Status transitions (pending → processing → completed/failed)
  - Processing duration tracking
  - JobStatus enum methods (isFinal, canProcess)

- **ExtractedEntityTest.php** - 20+ tests
  - Entity creation and validation
  - Confidence score validation (0-100)
  - Confidence levels (high, medium, low)
  - Status transitions (pending → approved/rejected/duplicate/created)
  - Quality score calculation
  - Attribute management
  - Name handling (canonical + alternatives)

#### PredictiveRelationships (`tests/unit/PredictiveRelationships/`)
- **RelationshipSuggestionTest.php** - 20+ tests
  - Suggestion creation and validation
  - Confidence and strength validation
  - Self-relationship prevention
  - User actions (accept, reject, modify)
  - Priority score calculation
  - Auto-accept logic (95%+ confidence + hybrid method)
  - Time to decision tracking

- **SuggestionFeatureTest.php** - 18+ tests
  - Feature value normalization (0-1 range)
  - Weight validation (0-1 range)
  - Weighted value calculation
  - Strength labels (very_strong, strong, moderate, weak)
  - Contribution percentage calculation
  - High-value feature detection
  - Feature normalization with clamping
  - Default weight handling per feature type

### Integration Tests (tests/integration/)

Database and workflow tests using WordPress test framework:

#### ConsistencyGuardian (`tests/integration/ConsistencyGuardian/`)
- **ConsistencyRepositoryTest.php** - 12+ tests
  - Table structure verification
  - CRUD operations with WordPress $wpdb
  - Filtering by status, severity, type
  - Cascade delete on saga deletion
  - Statistics calculation
  - WordPress object cache integration
  - JSON context searching
  - Priority ordering

#### EntityExtractor (`tests/integration/EntityExtractor/`)
- **ExtractionWorkflowTest.php** - 8+ tests
  - Complete extraction workflow
    1. Job creation
    2. Entity extraction
    3. User review (approve/reject)
    4. Batch entity creation
    5. Statistics update
  - Duplicate detection
  - Batch entity creation (5+ entities)
  - Job statistics calculation
  - Feedback recording
  - Cascade delete behavior

#### PredictiveRelationships (`tests/integration/PredictiveRelationships/`)
- **PredictionWorkflowTest.php** - 5+ tests
  - Complete prediction workflow
    1. Feature extraction
    2. Suggestion creation
    3. User feedback
    4. Weight learning
  - Feedback loop integration
  - Accuracy metrics calculation
  - Acceptance rate tracking

- **FeatureExtractionTest.php** - 7+ tests
  - Co-occurrence feature extraction
  - Timeline proximity calculation
  - Attribute similarity detection
  - Feature normalization
  - Weighted feature combination
  - Feature storage and retrieval

## Running Tests

### Prerequisites

```bash
# Install dependencies
composer install

# Set up WordPress test environment
bash bin/install-wp-tests.sh wordpress_test wordpress wordpress db latest
```

### Run All Tests

```bash
# All test suites
vendor/bin/phpunit

# Unit tests only
vendor/bin/phpunit --testsuite=unit

# Integration tests only
vendor/bin/phpunit --testsuite=integration

# Specific test file
vendor/bin/phpunit tests/unit/ConsistencyGuardian/ConsistencyIssueTest.php
```

### With Coverage

```bash
# Generate HTML coverage report
vendor/bin/phpunit --coverage-html tests/results/coverage-html

# View coverage summary
vendor/bin/phpunit --coverage-text
```

### Using Docker Compose

```bash
# Run tests in Docker container
docker-compose exec wordpress vendor/bin/phpunit

# Run with coverage
docker-compose exec wordpress vendor/bin/phpunit --coverage-text
```

## Test Utilities

### Base Test Case (`tests/includes/TestCase.php`)

Extended `WP_UnitTestCase` with utilities:

- `setUp()` / `tearDown()` - Clean database and cache
- `clean_database()` - Reset custom tables
- `assertTableExists($table)` - Verify table creation
- `assertTableHasColumns($table, $columns)` - Verify schema
- `mockAIResponse($content)` - Mock AI API responses
- `createTestUser($role)` - Create test user
- `actingAs($userId)` - Set current user
- `assertAjaxSuccess()` / `assertAjaxError()` - AJAX testing
- `createNonce($action)` - Generate test nonces
- `assertArrayStructure($expected, $actual)` - Verify array keys

### Factory Trait (`tests/includes/FactoryTrait.php`)

Test data creation helpers:

- `createSaga($args)` - Create test saga
- `createEntity($sagaId, $args)` - Create test entity
- `createConsistencyIssue($sagaId, $args)` - Create consistency issue
- `createExtractionJob($sagaId, $userId, $args)` - Create extraction job
- `createExtractedEntity($jobId, $args)` - Create extracted entity
- `createRelationshipSuggestion($sagaId, $sourceId, $targetId, $args)` - Create suggestion
- `createEntities($sagaId, $count)` - Batch create entities

## Test Organization

```
tests/
├── bootstrap.php              # Test environment setup
├── phpunit.xml               # PHPUnit configuration
├── includes/                 # Test utilities
│   ├── TestCase.php         # Base test class
│   └── FactoryTrait.php     # Data factories
├── unit/                     # Unit tests (no database)
│   ├── ConsistencyGuardian/
│   ├── EntityExtractor/
│   └── PredictiveRelationships/
└── integration/              # Integration tests (database)
    ├── ConsistencyGuardian/
    ├── EntityExtractor/
    └── PredictiveRelationships/
```

## Coverage Goals

- **Critical Paths:** 100% (CRUD operations, workflows)
- **WordPress Integration:** 90% (table prefixes, $wpdb, cache)
- **Error Handling:** 80% (exceptions, validation)
- **Overall:** 70%+

## Test Standards

### Unit Test Requirements
- Test value object immutability
- Test validation rules (ranges, formats, enums)
- Test edge cases (null, empty, boundary values)
- Test method return values and side effects
- No database or WordPress dependencies

### Integration Test Requirements
- Test WordPress $wpdb integration
- Test table prefix handling
- Test cascade deletes
- Test object cache usage
- Test transaction handling
- Test complex queries and joins

### What to Test
- **Value Objects:** Construction, validation, transformations
- **Repositories:** CRUD, filtering, statistics, caching
- **Workflows:** End-to-end user flows, state transitions
- **Edge Cases:** Empty data, null values, boundary conditions
- **Error Handling:** Invalid input, constraint violations

### What NOT to Test
- WordPress core functionality
- Third-party library internals
- Trivial getters/setters (unless validation logic)
- Database engine features

## CI/CD Integration

Tests run automatically on:
- Push to main branch
- Pull request creation
- Before deployment

## Debugging Tests

```bash
# Run single test method
vendor/bin/phpunit --filter test_can_create_consistency_issue

# Run with verbose output
vendor/bin/phpunit --testdox

# Stop on first failure
vendor/bin/phpunit --stop-on-failure

# Debug mode
vendor/bin/phpunit --debug
```

## Test Data Cleanup

All test data is automatically cleaned up:
- `tearDown()` deletes all custom table data
- `tearDown()` resets auto-increment counters
- `wp_cache_flush()` clears object cache
- WordPress test framework resets wp_posts, wp_users

## Writing New Tests

### Unit Test Template

```php
<?php
namespace SagaManager\Tests\Unit\YourFeature;

use SagaManager\Tests\TestCase;

class YourTest extends TestCase
{
    public function test_your_feature(): void
    {
        // Arrange
        $input = ['key' => 'value'];

        // Act
        $result = YourClass::doSomething($input);

        // Assert
        $this->assertEquals('expected', $result);
    }
}
```

### Integration Test Template

```php
<?php
namespace SagaManager\Tests\Integration\YourFeature;

use SagaManager\Tests\TestCase;

class YourIntegrationTest extends TestCase
{
    public function test_database_operation(): void
    {
        global $wpdb;

        $sagaId = $this->createSaga();

        // Test database operation
        $result = $wpdb->insert(
            $wpdb->prefix . 'your_table',
            ['saga_id' => $sagaId, 'data' => 'value']
        );

        $this->assertEquals(1, $result);
    }
}
```

## Continuous Improvement

- Add tests for new features
- Maintain 70%+ coverage
- Run tests before committing
- Fix failing tests immediately
- Review test failures in CI/CD

## Resources

- [PHPUnit Documentation](https://phpunit.de/documentation.html)
- [WordPress Test Library](https://make.wordpress.org/core/handbook/testing/automated-testing/phpunit/)
- [CLAUDE.md](../CLAUDE.md) - Project guidelines
