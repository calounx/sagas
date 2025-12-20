# Application Layer

## Overview

The Application layer implements **CQRS (Command Query Responsibility Segregation)** pattern within **Hexagonal Architecture**. It orchestrates domain logic without containing business rules.

**Key Responsibilities:**
- Execute use cases (commands and queries)
- Validate input from presentation layer
- Coordinate domain entities and repositories
- Transform domain objects to DTOs
- Manage transactions and error handling

**Does NOT contain:**
- Business logic (belongs in Domain)
- Database queries (belongs in Infrastructure)
- HTTP/presentation logic (belongs in Presentation)

## Architecture

```
┌─────────────────────────────────────────────────────────┐
│                  Presentation Layer                      │
│          (REST API, Admin UI, Shortcodes)               │
└──────────────────────┬──────────────────────────────────┘
                       │
                       ▼
┌─────────────────────────────────────────────────────────┐
│                  Application Layer                       │
│  ┌──────────────┐         ┌─────────────────────────┐  │
│  │ Command Bus  │         │     Query Bus           │  │
│  └──────┬───────┘         └──────┬──────────────────┘  │
│         │                        │                      │
│         ▼                        ▼                      │
│  ┌──────────────┐         ┌─────────────────────────┐  │
│  │  Handlers    │         │      Handlers           │  │
│  │  (Create,    │         │  (Get, Search)          │  │
│  │  Update,     │         │                         │  │
│  │  Delete)     │         │                         │  │
│  └──────┬───────┘         └──────┬──────────────────┘  │
│         │                        │                      │
│         └────────────┬───────────┘                      │
│                      ▼                                   │
│             ┌────────────────────┐                      │
│             │   DTOs             │                      │
│             └────────────────────┘                      │
└──────────────────────┬──────────────────────────────────┘
                       │
                       ▼
┌─────────────────────────────────────────────────────────┐
│                    Domain Layer                          │
│         (Entities, Value Objects, Repositories)         │
└─────────────────────────────────────────────────────────┘
```

## CQRS Pattern

### Commands (Write Operations)

Commands represent **intentions to change state**. They:
- Modify system state
- May trigger side effects
- Return simple results (ID, void)
- Are validated before execution

**Example:**
```php
$command = new CreateEntityCommand(
    sagaId: 1,
    type: 'character',
    canonicalName: 'Luke Skywalker',
    slug: 'luke-skywalker'
);

$entityId = $commandBus->dispatch($command);
```

### Queries (Read Operations)

Queries represent **requests for data**. They:
- Never modify state
- Return DTOs (Data Transfer Objects)
- Support filtering and pagination
- Can be cached aggressively

**Example:**
```php
$query = new GetEntityQuery(entityId: 42);
$entityDTO = $queryBus->dispatch($query);
```

## Directory Structure

```
Application/
├── Command/
│   ├── CommandInterface.php              # Marker interface
│   └── CommandHandlerInterface.php       # Handler contract
├── Query/
│   ├── QueryInterface.php                # Marker interface
│   └── QueryHandlerInterface.php         # Handler contract
├── DTO/
│   ├── EntityDTO.php                     # Entity representation for API
│   ├── CreateEntityRequest.php           # Input validation
│   └── SearchEntitiesResult.php          # Paginated result
├── Service/
│   ├── CommandBus.php                    # Command dispatcher
│   ├── QueryBus.php                      # Query dispatcher
│   └── ApplicationServiceProvider.php    # DI container
└── UseCase/
    ├── CreateEntity/
    │   ├── CreateEntityCommand.php
    │   └── CreateEntityHandler.php
    ├── GetEntity/
    │   ├── GetEntityQuery.php
    │   └── GetEntityHandler.php
    ├── SearchEntities/
    │   ├── SearchEntitiesQuery.php
    │   └── SearchEntitiesHandler.php
    ├── UpdateEntity/
    │   ├── UpdateEntityCommand.php
    │   └── UpdateEntityHandler.php
    └── DeleteEntity/
        ├── DeleteEntityCommand.php
        └── DeleteEntityHandler.php
```

## Use Cases

### 1. CreateEntity

**Purpose:** Create a new saga entity

**Input:**
- `sagaId`: ID of the saga
- `type`: Entity type (character, location, etc.)
- `canonicalName`: Display name
- `slug`: URL-friendly identifier
- `importanceScore`: Optional, 0-100
- `wpPostId`: Optional WordPress post link

**Output:** `EntityId` of created entity

**Exceptions:**
- `ValidationException`: Invalid input
- `DuplicateEntityException`: Name or slug already exists
- `DatabaseException`: Persistence failure

**Example:**
```php
$command = new CreateEntityCommand(
    sagaId: 1,
    type: 'character',
    canonicalName: 'Luke Skywalker',
    slug: 'luke-skywalker',
    importanceScore: 95
);

$entityId = $commandBus->dispatch($command);
```

### 2. GetEntity

**Purpose:** Retrieve a single entity by ID

**Input:**
- `entityId`: Entity identifier

**Output:** `EntityDTO` with all entity data

**Exceptions:**
- `EntityNotFoundException`: Entity not found
- `ValidationException`: Invalid entity ID

**Example:**
```php
$query = new GetEntityQuery(entityId: 42);
$entityDTO = $queryBus->dispatch($query);

echo $entityDTO->canonicalName; // "Luke Skywalker"
echo $entityDTO->importanceScore; // 95
```

### 3. SearchEntities

**Purpose:** Search entities with filters and pagination

**Input:**
- `sagaId`: Required, filter by saga
- `type`: Optional, filter by entity type
- `limit`: Results per page (max 100)
- `offset`: Pagination offset

**Output:** `SearchEntitiesResult` with:
- `entities`: Array of EntityDTO
- `total`: Total count (for pagination)
- `limit`: Applied limit
- `offset`: Applied offset
- `has_more`: Boolean flag

**Example:**
```php
$query = new SearchEntitiesQuery(
    sagaId: 1,
    type: 'character',
    limit: 20,
    offset: 0
);

$result = $queryBus->dispatch($query);

foreach ($result->entities as $entity) {
    echo "{$entity->canonicalName}\n";
}

echo "Total: {$result->total}, Has more: " . ($result->has_more ? 'yes' : 'no');
```

### 4. UpdateEntity

**Purpose:** Update existing entity fields

**Input:**
- `entityId`: Entity to update
- `canonicalName`: Optional new name
- `slug`: Optional new slug
- `importanceScore`: Optional new score

**Output:** `void`

**Exceptions:**
- `EntityNotFoundException`: Entity not found
- `ValidationException`: No changes or invalid input
- `DuplicateEntityException`: Slug conflict

**Example:**
```php
$command = new UpdateEntityCommand(
    entityId: 42,
    canonicalName: 'Luke Skywalker (Jedi Master)',
    importanceScore: 100
);

$commandBus->dispatch($command);
```

### 5. DeleteEntity

**Purpose:** Delete an entity

**Input:**
- `entityId`: Entity to delete

**Output:** `void`

**Exceptions:**
- `EntityNotFoundException`: Entity not found
- `DatabaseException`: Deletion failure

**Example:**
```php
$command = new DeleteEntityCommand(entityId: 42);
$commandBus->dispatch($command);
```

## Command/Query Buses

### Command Bus

Dispatches commands to their handlers. Ensures one command = one handler.

**Usage:**
```php
$commandBus = new CommandBus();

// Register handlers
$commandBus->register(
    CreateEntityCommand::class,
    new CreateEntityHandler($entityRepository)
);

// Dispatch
$result = $commandBus->dispatch($command);
```

### Query Bus

Dispatches queries to their handlers. Enables query optimization and caching.

**Usage:**
```php
$queryBus = new QueryBus();

// Register handlers
$queryBus->register(
    GetEntityQuery::class,
    new GetEntityHandler($entityRepository)
);

// Dispatch
$result = $queryBus->dispatch($query);
```

### Application Service Provider

Simplifies setup by configuring all buses and handlers.

**Usage:**
```php
$serviceProvider = new ApplicationServiceProvider($entityRepository);

$commandBus = $serviceProvider->getCommandBus();
$queryBus = $serviceProvider->getQueryBus();
```

## DTOs (Data Transfer Objects)

### EntityDTO

Immutable representation of an entity for API responses.

**Features:**
- Readonly properties (PHP 8.2)
- `toArray()` for JSON serialization
- Static factory from domain entity

**Example:**
```php
$dto = EntityDTO::fromEntity($sagaEntity);

$json = json_encode($dto->toArray());
// {
//   "id": 42,
//   "saga_id": 1,
//   "type": "character",
//   "canonical_name": "Luke Skywalker",
//   "slug": "luke-skywalker",
//   "importance_score": 95,
//   "created_at": "2024-01-15T10:30:00+00:00",
//   "updated_at": "2024-01-15T10:30:00+00:00"
// }
```

### CreateEntityRequest

Validates input before passing to domain layer.

**Features:**
- `fromArray()` for HTTP request parsing
- Validation in constructor
- Type coercion

**Example:**
```php
$request = CreateEntityRequest::fromArray($request->get_json_params());
// Throws ValidationException if invalid

$command = new CreateEntityCommand(
    sagaId: $request->sagaId,
    type: $request->type,
    canonicalName: $request->canonicalName,
    slug: $request->slug
);
```

### SearchEntitiesResult

Paginated collection with metadata.

**Example:**
```php
$result = new SearchEntitiesResult(
    entities: $entityDTOs,
    total: 150,
    limit: 20,
    offset: 0
);

$json = json_encode($result->toArray());
// {
//   "entities": [...],
//   "total": 150,
//   "limit": 20,
//   "offset": 0,
//   "has_more": true
// }
```

## Error Handling

### Exception Hierarchy

```
SagaException (Domain\Exception)
├── ValidationException       # Invalid input
├── EntityNotFoundException   # Entity not found
├── DuplicateEntityException  # Uniqueness violation
└── DatabaseException         # Persistence failure
```

### WordPress Integration

Convert domain exceptions to `WP_Error`:

```php
try {
    $result = $commandBus->dispatch($command);
} catch (ValidationException $e) {
    return new WP_Error('validation_failed', $e->getMessage(), ['status' => 400]);
} catch (EntityNotFoundException $e) {
    return new WP_Error('not_found', $e->getMessage(), ['status' => 404]);
} catch (DuplicateEntityException $e) {
    return new WP_Error('conflict', $e->getMessage(), ['status' => 409]);
} catch (DatabaseException $e) {
    error_log('[SAGA][ERROR] ' . $e->getMessage());
    return new WP_Error('internal_error', 'Server error', ['status' => 500]);
}
```

## Testing

### Unit Tests

Test handlers in isolation using mock repositories.

**Example:**
```php
final class CreateEntityHandlerTest extends TestCase
{
    private EntityRepositoryInterface $mockRepository;
    private CreateEntityHandler $handler;

    protected function setUp(): void
    {
        $this->mockRepository = $this->createMock(EntityRepositoryInterface::class);
        $this->handler = new CreateEntityHandler($this->mockRepository);
    }

    public function test_handle_creates_entity_successfully(): void
    {
        $this->mockRepository
            ->expects($this->once())
            ->method('save')
            ->willReturnCallback(function(SagaEntity $entity) {
                $entity->setId(new EntityId(42));
            });

        $command = new CreateEntityCommand(...);
        $result = $this->handler->handle($command);

        $this->assertEquals(42, $result->value());
    }
}
```

### Integration Tests

Test with real repository and WordPress database.

**Example:**
```php
final class CreateEntityIntegrationTest extends WP_UnitTestCase
{
    private ApplicationServiceProvider $serviceProvider;

    public function setUp(): void
    {
        parent::setUp();
        $this->serviceProvider = new ApplicationServiceProvider(
            new MariaDBEntityRepository()
        );
    }

    public function test_create_and_retrieve_entity(): void
    {
        $createCommand = new CreateEntityCommand(...);
        $entityId = $this->serviceProvider->getCommandBus()->dispatch($createCommand);

        $getQuery = new GetEntityQuery(entityId: $entityId->value());
        $entityDTO = $this->serviceProvider->getQueryBus()->dispatch($getQuery);

        $this->assertEquals('Luke Skywalker', $entityDTO->canonicalName);
    }
}
```

## Best Practices

### 1. Handler Design

- One handler per command/query (Single Responsibility)
- Inject dependencies via constructor
- Keep handlers thin (orchestration only)
- Business logic belongs in domain entities

### 2. Validation Strategy

- **DTO layer**: Format and type validation
- **Domain layer**: Business rule validation
- Fail fast with clear error messages

### 3. Transaction Management

Handlers should wrap multi-step operations in transactions:

```php
global $wpdb;
$wpdb->query('START TRANSACTION');

try {
    $this->entityRepository->save($entity);
    $this->relationshipRepository->save($relationship);
    $wpdb->query('COMMIT');
} catch (\Exception $e) {
    $wpdb->query('ROLLBACK');
    throw new DatabaseException('Transaction failed', 0, $e);
}
```

### 4. Return Types

- **Commands**: Return ID or void
- **Queries**: Return DTOs, never domain entities
- Keep presentation layer decoupled from domain

### 5. Naming Conventions

- Commands: Verb + Noun (CreateEntity, UpdateEntity)
- Queries: Get/Search/Find + Noun (GetEntity, SearchEntities)
- Handlers: CommandName + Handler

## WordPress Integration Example

```php
// In plugin initialization
$serviceProvider = new ApplicationServiceProvider(
    new MariaDBEntityRepository()
);

// Register REST API endpoint
add_action('rest_api_init', function() use ($serviceProvider) {
    register_rest_route('saga/v1', '/entities', [
        'methods' => 'POST',
        'callback' => function(\WP_REST_Request $request) use ($serviceProvider) {
            try {
                $requestDTO = CreateEntityRequest::fromArray($request->get_json_params());

                $command = new CreateEntityCommand(
                    sagaId: $requestDTO->sagaId,
                    type: $requestDTO->type,
                    canonicalName: $requestDTO->canonicalName,
                    slug: $requestDTO->slug,
                    importanceScore: $requestDTO->importanceScore
                );

                $entityId = $serviceProvider->getCommandBus()->dispatch($command);

                $query = new GetEntityQuery(entityId: $entityId->value());
                $entityDTO = $serviceProvider->getQueryBus()->dispatch($query);

                return new \WP_REST_Response($entityDTO->toArray(), 201);

            } catch (ValidationException $e) {
                return new \WP_REST_Response([
                    'error' => 'Validation failed',
                    'message' => $e->getMessage()
                ], 400);
            }
        },
        'permission_callback' => fn() => current_user_can('edit_posts')
    ]);
});
```

## Performance Considerations

### Caching Queries

```php
final class CachedGetEntityHandler implements QueryHandlerInterface
{
    public function handle(QueryInterface $query): EntityDTO
    {
        $cacheKey = "saga_entity_{$query->entityId}";

        $cached = wp_cache_get($cacheKey, 'saga');
        if ($cached !== false) {
            return $cached;
        }

        $result = $this->innerHandler->handle($query);

        wp_cache_set($cacheKey, $result, 'saga', 300); // 5 min TTL

        return $result;
    }
}
```

### Batch Operations

For bulk operations, create dedicated commands:

```php
final class BulkCreateEntitiesCommand implements CommandInterface
{
    public function __construct(
        public readonly array $entities
    ) {}
}
```

## Migration from Legacy Code

If migrating from procedural WordPress code:

1. **Extract logic into handlers** (use cases)
2. **Create DTOs** for API contracts
3. **Register with buses** for decoupling
4. **Add tests** before refactoring
5. **Replace direct calls** with bus dispatch

**Before:**
```php
function create_saga_entity($data) {
    global $wpdb;
    $wpdb->insert($wpdb->prefix . 'saga_entities', $data);
    return $wpdb->insert_id;
}
```

**After:**
```php
$command = new CreateEntityCommand(...);
$entityId = $commandBus->dispatch($command);
```

## Further Reading

- [CQRS Pattern](https://martinfowler.com/bliki/CQRS.html)
- [Hexagonal Architecture](https://alistair.cockburn.us/hexagonal-architecture/)
- [Command Bus Pattern](https://matthiasnoback.nl/2015/01/a-wave-of-command-buses/)
- [Domain-Driven Design](https://www.domainlanguage.com/ddd/)
