<?php
declare(strict_types=1);

/**
 * Application Layer Usage Examples
 *
 * Demonstrates how to use the CQRS-based Application layer.
 * This file shows the complete flow from command/query to handler execution.
 */

require_once __DIR__ . '/../vendor/autoload.php';

use SagaManager\Application\DTO\CreateEntityRequest;
use SagaManager\Application\Service\ApplicationServiceProvider;
use SagaManager\Application\UseCase\CreateEntity\CreateEntityCommand;
use SagaManager\Application\UseCase\DeleteEntity\DeleteEntityCommand;
use SagaManager\Application\UseCase\GetEntity\GetEntityQuery;
use SagaManager\Application\UseCase\SearchEntities\SearchEntitiesQuery;
use SagaManager\Application\UseCase\UpdateEntity\UpdateEntityCommand;
use SagaManager\Infrastructure\Repository\MariaDBEntityRepository;

// ==========================================
// SETUP: Initialize dependencies
// ==========================================

global $wpdb; // WordPress database object
$entityRepository = new MariaDBEntityRepository();
$serviceProvider = new ApplicationServiceProvider($entityRepository);

$commandBus = $serviceProvider->getCommandBus();
$queryBus = $serviceProvider->getQueryBus();

// ==========================================
// EXAMPLE 1: Create Entity
// ==========================================

echo "=== CREATE ENTITY ===\n";

try {
    // Validate input using DTO
    $request = CreateEntityRequest::fromArray([
        'saga_id' => 1,
        'type' => 'character',
        'canonical_name' => 'Luke Skywalker',
        'slug' => 'luke-skywalker',
        'importance_score' => 95,
    ]);

    // Create command from validated request
    $command = new CreateEntityCommand(
        sagaId: $request->sagaId,
        type: $request->type,
        canonicalName: $request->canonicalName,
        slug: $request->slug,
        importanceScore: $request->importanceScore
    );

    // Dispatch command via bus
    $entityId = $commandBus->dispatch($command);

    echo "Entity created with ID: {$entityId}\n";

} catch (\SagaManager\Domain\Exception\ValidationException $e) {
    echo "Validation error: {$e->getMessage()}\n";
} catch (\SagaManager\Domain\Exception\DuplicateEntityException $e) {
    echo "Duplicate entity error: {$e->getMessage()}\n";
} catch (\SagaManager\Domain\Exception\DatabaseException $e) {
    echo "Database error: {$e->getMessage()}\n";
    error_log('[SAGA][ERROR] ' . $e->getMessage());
}

// ==========================================
// EXAMPLE 2: Get Entity
// ==========================================

echo "\n=== GET ENTITY ===\n";

try {
    $query = new GetEntityQuery(entityId: 1);
    $entityDTO = $queryBus->dispatch($query);

    echo "Entity found:\n";
    echo "  Name: {$entityDTO->canonicalName}\n";
    echo "  Type: {$entityDTO->type}\n";
    echo "  Slug: {$entityDTO->slug}\n";
    echo "  Importance: {$entityDTO->importanceScore}\n";

    // Convert to array for JSON API response
    $jsonResponse = json_encode($entityDTO->toArray(), JSON_PRETTY_PRINT);
    echo "JSON Response:\n{$jsonResponse}\n";

} catch (\SagaManager\Domain\Exception\EntityNotFoundException $e) {
    echo "Entity not found: {$e->getMessage()}\n";
}

// ==========================================
// EXAMPLE 3: Search Entities
// ==========================================

echo "\n=== SEARCH ENTITIES ===\n";

try {
    // Search with filters and pagination
    $query = new SearchEntitiesQuery(
        sagaId: 1,
        type: 'character',
        limit: 10,
        offset: 0
    );

    $result = $queryBus->dispatch($query);

    echo "Found {$result->total} total entities, showing " . count($result->entities) . "\n";

    foreach ($result->entities as $entity) {
        echo "  - {$entity->canonicalName} (ID: {$entity->id})\n";
    }

    // API response with pagination metadata
    $jsonResponse = json_encode($result->toArray(), JSON_PRETTY_PRINT);
    echo "JSON Response:\n{$jsonResponse}\n";

} catch (\SagaManager\Domain\Exception\ValidationException $e) {
    echo "Validation error: {$e->getMessage()}\n";
}

// ==========================================
// EXAMPLE 4: Update Entity
// ==========================================

echo "\n=== UPDATE ENTITY ===\n";

try {
    $command = new UpdateEntityCommand(
        entityId: 1,
        canonicalName: 'Luke Skywalker (Jedi Master)',
        importanceScore: 100
    );

    $commandBus->dispatch($command);

    echo "Entity updated successfully\n";

} catch (\SagaManager\Domain\Exception\EntityNotFoundException $e) {
    echo "Entity not found: {$e->getMessage()}\n";
} catch (\SagaManager\Domain\Exception\DuplicateEntityException $e) {
    echo "Duplicate slug error: {$e->getMessage()}\n";
}

// ==========================================
// EXAMPLE 5: Delete Entity
// ==========================================

echo "\n=== DELETE ENTITY ===\n";

try {
    $command = new DeleteEntityCommand(entityId: 999);
    $commandBus->dispatch($command);

    echo "Entity deleted successfully\n";

} catch (\SagaManager\Domain\Exception\EntityNotFoundException $e) {
    echo "Entity not found: {$e->getMessage()}\n";
}

// ==========================================
// EXAMPLE 6: WordPress REST API Integration
// ==========================================

echo "\n=== WORDPRESS REST API EXAMPLE ===\n";

/**
 * Example WordPress REST API controller using Application layer
 */
class SagaEntityController
{
    private $commandBus;
    private $queryBus;

    public function __construct($commandBus, $queryBus)
    {
        $this->commandBus = $commandBus;
        $this->queryBus = $queryBus;
    }

    /**
     * GET /wp-json/saga/v1/entities/{id}
     */
    public function show(\WP_REST_Request $request): \WP_Response
    {
        try {
            $query = new GetEntityQuery(entityId: (int) $request['id']);
            $entityDTO = $this->queryBus->dispatch($query);

            return new \WP_REST_Response($entityDTO->toArray(), 200);

        } catch (\SagaManager\Domain\Exception\EntityNotFoundException $e) {
            return new \WP_REST_Response(
                ['error' => 'Entity not found', 'message' => $e->getMessage()],
                404
            );
        } catch (\Exception $e) {
            error_log('[SAGA][ERROR] ' . $e->getMessage());
            return new \WP_REST_Response(
                ['error' => 'Internal server error'],
                500
            );
        }
    }

    /**
     * POST /wp-json/saga/v1/entities
     */
    public function create(\WP_REST_Request $request): \WP_Response
    {
        try {
            // Validate input with DTO
            $requestDTO = CreateEntityRequest::fromArray($request->get_json_params());

            // Create command
            $command = new CreateEntityCommand(
                sagaId: $requestDTO->sagaId,
                type: $requestDTO->type,
                canonicalName: $requestDTO->canonicalName,
                slug: $requestDTO->slug,
                importanceScore: $requestDTO->importanceScore
            );

            // Execute
            $entityId = $this->commandBus->dispatch($command);

            // Fetch created entity to return
            $query = new GetEntityQuery(entityId: $entityId->value());
            $entityDTO = $this->queryBus->dispatch($query);

            return new \WP_REST_Response($entityDTO->toArray(), 201);

        } catch (\SagaManager\Domain\Exception\ValidationException $e) {
            return new \WP_REST_Response(
                ['error' => 'Validation failed', 'message' => $e->getMessage()],
                400
            );
        } catch (\SagaManager\Domain\Exception\DuplicateEntityException $e) {
            return new \WP_REST_Response(
                ['error' => 'Entity already exists', 'message' => $e->getMessage()],
                409
            );
        } catch (\Exception $e) {
            error_log('[SAGA][ERROR] ' . $e->getMessage());
            return new \WP_REST_Response(
                ['error' => 'Internal server error'],
                500
            );
        }
    }

    /**
     * GET /wp-json/saga/v1/entities
     */
    public function index(\WP_REST_Request $request): \WP_Response
    {
        try {
            $query = new SearchEntitiesQuery(
                sagaId: (int) ($request['saga_id'] ?? 1),
                type: $request['type'] ?? null,
                limit: min((int) ($request['per_page'] ?? 20), 100),
                offset: (int) ($request['offset'] ?? 0)
            );

            $result = $this->queryBus->dispatch($query);

            return new \WP_REST_Response($result->toArray(), 200);

        } catch (\SagaManager\Domain\Exception\ValidationException $e) {
            return new \WP_REST_Response(
                ['error' => 'Invalid parameters', 'message' => $e->getMessage()],
                400
            );
        } catch (\Exception $e) {
            error_log('[SAGA][ERROR] ' . $e->getMessage());
            return new \WP_REST_Response(
                ['error' => 'Internal server error'],
                500
            );
        }
    }
}

echo "REST API controller example created (see code)\n";

// ==========================================
// ARCHITECTURE BENEFITS
// ==========================================

echo "\n=== ARCHITECTURE BENEFITS ===\n";
echo "✓ CQRS: Clear separation between commands (writes) and queries (reads)\n";
echo "✓ Hexagonal Architecture: Domain logic isolated from infrastructure\n";
echo "✓ SOLID Principles: Single responsibility, dependency inversion\n";
echo "✓ Type Safety: PHP 8.2 strict types, readonly properties\n";
echo "✓ Error Handling: Domain exceptions properly propagated\n";
echo "✓ Testability: Each handler can be unit tested in isolation\n";
echo "✓ Decoupling: Bus pattern allows handler swapping without changing API\n";
echo "✓ Validation: Input validated at DTO level before domain logic\n";
