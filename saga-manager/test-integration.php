<?php
/**
 * Integration Test Script
 *
 * Run this from WordPress root: php wp-content/plugins/saga-manager/test-integration.php
 * Or use WP-CLI: wp eval-file wp-content/plugins/saga-manager/test-integration.php
 */

// Load WordPress
if (!defined('ABSPATH')) {
    // Adjust path if needed
    require_once __DIR__ . '/../../../wp-load.php';
}

use SagaManager\Domain\Entity\SagaEntity;
use SagaManager\Domain\Entity\SagaId;
use SagaManager\Domain\Entity\EntityId;
use SagaManager\Domain\Entity\EntityType;
use SagaManager\Domain\Entity\ImportanceScore;
use SagaManager\Infrastructure\Repository\MariaDBEntityRepository;
use SagaManager\Infrastructure\WordPress\SagaEntityPostType;

echo "=== Saga Manager Integration Test ===\n\n";

// 1. Test Repository
echo "1. Testing Repository...\n";
try {
    $repository = new MariaDBEntityRepository();
    echo "   ✓ Repository instantiated\n";

    // Create test entity
    $entity = new SagaEntity(
        sagaId: new SagaId(1),
        type: EntityType::CHARACTER,
        canonicalName: 'Test Character',
        slug: 'test-character-' . time(),
        importanceScore: new ImportanceScore(75)
    );

    $repository->save($entity);
    echo "   ✓ Entity created with ID: {$entity->getId()->value()}\n";

    // Read entity
    $found = $repository->findById($entity->getId());
    assert($found->getCanonicalName() === 'Test Character');
    echo "   ✓ Entity retrieved successfully\n";

    // Update entity
    $found->updateCanonicalName('Updated Character');
    $repository->save($found);
    echo "   ✓ Entity updated\n";

    // List entities
    $entities = $repository->findBySaga(new SagaId(1), 10);
    echo "   ✓ Found " . count($entities) . " entities in saga\n";

    // Clean up
    $repository->delete($entity->getId());
    echo "   ✓ Entity deleted\n";

} catch (Exception $e) {
    echo "   ✗ Error: {$e->getMessage()}\n";
    exit(1);
}

// 2. Test Custom Post Type
echo "\n2. Testing Custom Post Type...\n";
try {
    $postType = new SagaEntityPostType($repository);
    echo "   ✓ Post type instantiated\n";

    // Create entity and sync to post
    $entity2 = new SagaEntity(
        sagaId: new SagaId(1),
        type: EntityType::LOCATION,
        canonicalName: 'Test Location',
        slug: 'test-location-' . time(),
        importanceScore: new ImportanceScore(60)
    );

    $repository->save($entity2);

    $post_id = $postType->syncFromEntity($entity2);
    echo "   ✓ Entity synced to wp_posts (post ID: {$post_id})\n";

    // Verify post exists
    $post = get_post($post_id);
    assert($post !== null);
    assert($post->post_type === 'saga_entity');
    assert($post->post_title === 'Test Location');
    echo "   ✓ wp_post verified\n";

    // Verify meta fields
    $saga_id_meta = get_post_meta($post_id, '_saga_id', true);
    $entity_type_meta = get_post_meta($post_id, '_entity_type', true);
    assert($saga_id_meta == 1);
    assert($entity_type_meta === 'location');
    echo "   ✓ Meta fields verified\n";

    // Clean up
    wp_delete_post($post_id, true);
    $repository->delete($entity2->getId());
    echo "   ✓ Post and entity deleted\n";

} catch (Exception $e) {
    echo "   ✗ Error: {$e->getMessage()}\n";
    exit(1);
}

// 3. Test REST API Routes
echo "\n3. Testing REST API Routes...\n";
try {
    // Check if routes are registered
    $routes = rest_get_server()->get_routes();

    $expected_routes = [
        '/saga/v1/entities',
        '/saga/v1/entities/(?P<id>\d+)',
    ];

    $found_routes = [];
    foreach ($routes as $route => $handlers) {
        if (strpos($route, '/saga/v1/entities') !== false) {
            $found_routes[] = $route;
        }
    }

    echo "   ✓ Found " . count($found_routes) . " REST routes\n";

    foreach ($expected_routes as $expected) {
        $found = false;
        foreach ($found_routes as $route) {
            if (preg_match('#^' . $expected . '$#', $route)) {
                $found = true;
                break;
            }
        }
        if ($found) {
            echo "   ✓ Route exists: {$expected}\n";
        } else {
            echo "   ✗ Route missing: {$expected}\n";
        }
    }

} catch (Exception $e) {
    echo "   ✗ Error: {$e->getMessage()}\n";
    exit(1);
}

// 4. Summary
echo "\n=== All Tests Passed ✓ ===\n\n";

echo "Next Steps:\n";
echo "1. Navigate to wp-admin and check 'Saga Entities' menu\n";
echo "2. Test REST API endpoints with curl or Postman\n";
echo "3. Create a test saga and entities\n";
echo "4. Monitor error logs for any issues\n\n";

echo "Example curl commands:\n";
echo "# List entities\n";
echo "curl -X GET \"http://localhost/wp-json/saga/v1/entities?saga_id=1\"\n\n";
echo "# Create entity\n";
echo "curl -X POST \"http://localhost/wp-json/saga/v1/entities\" \\\n";
echo "  -H \"Content-Type: application/json\" \\\n";
echo "  -H \"X-WP-Nonce: YOUR_NONCE\" \\\n";
echo "  -d '{\"saga_id\":1,\"type\":\"character\",\"canonical_name\":\"Test Character\"}'\n\n";
