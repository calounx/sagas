<?php
declare(strict_types=1);

namespace SagaManager\Tests\Integration\Presentation\API;

use WP_REST_Request;
use WP_REST_Response;
use WP_UnitTestCase;
use SagaManager\Presentation\API\EntityController;
use SagaManager\Infrastructure\Repository\MariaDBEntityRepository;
use SagaManager\Tests\Fixtures\SagaFixtures;

/**
 * Integration tests for EntityController REST API
 *
 * Tests all REST API endpoints for entity CRUD operations.
 * Verifies authentication, validation, and response formats.
 *
 * @covers \SagaManager\Presentation\API\EntityController
 */
final class EntityControllerTest extends WP_UnitTestCase
{
    private EntityController $controller;
    private MariaDBEntityRepository $repository;
    private int $sagaId;
    private int $adminUserId;

    public function set_up(): void
    {
        parent::set_up();

        $this->repository = new MariaDBEntityRepository();
        $this->controller = new EntityController($this->repository);

        // Register routes for testing
        do_action('rest_api_init');
        $this->controller->registerRoutes();

        // Load test fixtures
        $this->sagaId = SagaFixtures::loadStarWarsSaga();

        // Create admin user for authenticated requests
        $this->adminUserId = self::factory()->user->create([
            'role' => 'administrator',
        ]);

        wp_cache_flush();
    }

    public function tear_down(): void
    {
        SagaFixtures::cleanup();
        wp_cache_flush();

        parent::tear_down();
    }

    // =========================================================================
    // INDEX ENDPOINT TESTS
    // =========================================================================

    public function test_index_returns_entities_for_saga(): void
    {
        wp_set_current_user($this->adminUserId);

        $request = new WP_REST_Request('GET', '/saga/v1/entities');
        $request->set_param('saga_id', $this->sagaId);

        $response = $this->controller->index($request);

        $this->assertInstanceOf(WP_REST_Response::class, $response);
        $this->assertSame(200, $response->get_status());

        $data = $response->get_data();
        $this->assertIsArray($data);
        $this->assertCount(5, $data);
    }

    public function test_index_returns_pagination_headers(): void
    {
        wp_set_current_user($this->adminUserId);

        $request = new WP_REST_Request('GET', '/saga/v1/entities');
        $request->set_param('saga_id', $this->sagaId);
        $request->set_param('per_page', 2);

        $response = $this->controller->index($request);

        $headers = $response->get_headers();
        $this->assertSame('5', $headers['X-WP-Total']);
        $this->assertSame('3', $headers['X-WP-TotalPages']);
    }

    public function test_index_filters_by_entity_type(): void
    {
        wp_set_current_user($this->adminUserId);

        $request = new WP_REST_Request('GET', '/saga/v1/entities');
        $request->set_param('saga_id', $this->sagaId);
        $request->set_param('type', 'character');

        $response = $this->controller->index($request);
        $data = $response->get_data();

        $this->assertCount(2, $data);
        foreach ($data as $entity) {
            $this->assertSame('character', $entity['type']);
        }
    }

    public function test_index_respects_pagination(): void
    {
        wp_set_current_user($this->adminUserId);

        $request = new WP_REST_Request('GET', '/saga/v1/entities');
        $request->set_param('saga_id', $this->sagaId);
        $request->set_param('page', 2);
        $request->set_param('per_page', 2);

        $response = $this->controller->index($request);
        $data = $response->get_data();

        $this->assertCount(2, $data);
        // Should be 3rd and 4th entities by importance
        $this->assertSame('Battle of Yavin', $data[0]['canonical_name']);
        $this->assertSame('Rebel Alliance', $data[1]['canonical_name']);
    }

    public function test_index_orders_by_importance_desc(): void
    {
        wp_set_current_user($this->adminUserId);

        $request = new WP_REST_Request('GET', '/saga/v1/entities');
        $request->set_param('saga_id', $this->sagaId);

        $response = $this->controller->index($request);
        $data = $response->get_data();

        // Verify ordering: Vader(100) > Luke(95) > Battle(90) > Rebel(85) > Tatooine(70)
        $this->assertSame('Darth Vader', $data[0]['canonical_name']);
        $this->assertSame(100, $data[0]['importance_score']);
        $this->assertSame('Tatooine', $data[4]['canonical_name']);
        $this->assertSame(70, $data[4]['importance_score']);
    }

    // =========================================================================
    // SHOW ENDPOINT TESTS
    // =========================================================================

    public function test_show_returns_entity_by_id(): void
    {
        wp_set_current_user($this->adminUserId);

        $lukeId = SagaFixtures::getEntityId('luke');

        $request = new WP_REST_Request('GET', "/saga/v1/entities/{$lukeId}");
        $request->set_param('id', $lukeId);

        $response = $this->controller->show($request);

        $this->assertSame(200, $response->get_status());

        $data = $response->get_data();
        $this->assertSame($lukeId, $data['id']);
        $this->assertSame('Luke Skywalker', $data['canonical_name']);
        $this->assertSame('luke-skywalker', $data['slug']);
        $this->assertSame('character', $data['type']);
        $this->assertSame('Character', $data['type_label']);
        $this->assertSame(95, $data['importance_score']);
    }

    public function test_show_returns_404_for_nonexistent_entity(): void
    {
        wp_set_current_user($this->adminUserId);

        $request = new WP_REST_Request('GET', '/saga/v1/entities/999999');
        $request->set_param('id', 999999);

        $response = $this->controller->show($request);

        $this->assertSame(404, $response->get_status());

        $data = $response->get_data();
        $this->assertSame('Entity not found', $data['error']);
    }

    public function test_show_response_contains_timestamps(): void
    {
        wp_set_current_user($this->adminUserId);

        $lukeId = SagaFixtures::getEntityId('luke');

        $request = new WP_REST_Request('GET', "/saga/v1/entities/{$lukeId}");
        $request->set_param('id', $lukeId);

        $response = $this->controller->show($request);
        $data = $response->get_data();

        $this->assertArrayHasKey('created_at', $data);
        $this->assertArrayHasKey('updated_at', $data);
        // ISO 8601 format
        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}/', $data['created_at']);
    }

    // =========================================================================
    // CREATE ENDPOINT TESTS
    // =========================================================================

    public function test_create_inserts_new_entity(): void
    {
        wp_set_current_user($this->adminUserId);

        $request = new WP_REST_Request('POST', '/saga/v1/entities');
        $request->set_param('saga_id', $this->sagaId);
        $request->set_param('type', 'artifact');
        $request->set_param('canonical_name', 'Lightsaber');
        $request->set_param('slug', 'lightsaber');
        $request->set_param('importance_score', 75);

        // Mock REST_REQUEST constant for nonce bypass
        if (!defined('REST_REQUEST')) {
            define('REST_REQUEST', true);
        }

        $response = $this->controller->create($request);

        $this->assertSame(201, $response->get_status());

        $data = $response->get_data();
        $this->assertNotNull($data['id']);
        $this->assertSame('Lightsaber', $data['canonical_name']);
        $this->assertSame('lightsaber', $data['slug']);
        $this->assertSame('artifact', $data['type']);
        $this->assertSame(75, $data['importance_score']);
    }

    public function test_create_auto_generates_slug_from_name(): void
    {
        wp_set_current_user($this->adminUserId);

        $request = new WP_REST_Request('POST', '/saga/v1/entities');
        $request->set_param('saga_id', $this->sagaId);
        $request->set_param('type', 'character');
        $request->set_param('canonical_name', 'Obi-Wan Kenobi');
        // No slug provided

        $response = $this->controller->create($request);

        $this->assertSame(201, $response->get_status());

        $data = $response->get_data();
        $this->assertSame('obi-wan-kenobi', $data['slug']);
    }

    public function test_create_returns_409_for_duplicate_name(): void
    {
        wp_set_current_user($this->adminUserId);

        $request = new WP_REST_Request('POST', '/saga/v1/entities');
        $request->set_param('saga_id', $this->sagaId);
        $request->set_param('type', 'character');
        $request->set_param('canonical_name', 'Luke Skywalker'); // Already exists
        $request->set_param('slug', 'luke-skywalker-2');

        $response = $this->controller->create($request);

        $this->assertSame(409, $response->get_status());

        $data = $response->get_data();
        $this->assertSame('Duplicate entity', $data['error']);
    }

    public function test_create_uses_default_importance_score(): void
    {
        wp_set_current_user($this->adminUserId);

        $request = new WP_REST_Request('POST', '/saga/v1/entities');
        $request->set_param('saga_id', $this->sagaId);
        $request->set_param('type', 'location');
        $request->set_param('canonical_name', 'Dagobah');
        // No importance_score provided

        $response = $this->controller->create($request);

        $this->assertSame(201, $response->get_status());

        $data = $response->get_data();
        $this->assertSame(50, $data['importance_score']); // Default value
    }

    public function test_create_sanitizes_input(): void
    {
        wp_set_current_user($this->adminUserId);

        $request = new WP_REST_Request('POST', '/saga/v1/entities');
        $request->set_param('saga_id', $this->sagaId);
        $request->set_param('type', 'character');
        $request->set_param('canonical_name', '<script>alert("xss")</script>Han Solo');
        $request->set_param('slug', 'han-solo');

        $response = $this->controller->create($request);

        $this->assertSame(201, $response->get_status());

        $data = $response->get_data();
        // Script tags should be stripped
        $this->assertStringNotContainsString('<script>', $data['canonical_name']);
        $this->assertSame('Han Solo', $data['canonical_name']);
    }

    // =========================================================================
    // UPDATE ENDPOINT TESTS
    // =========================================================================

    public function test_update_modifies_entity(): void
    {
        wp_set_current_user($this->adminUserId);

        $lukeId = SagaFixtures::getEntityId('luke');

        $request = new WP_REST_Request('PUT', "/saga/v1/entities/{$lukeId}");
        $request->set_param('id', $lukeId);
        $request->set_param('canonical_name', 'Luke Skywalker (Jedi Master)');
        $request->set_param('importance_score', 100);

        $response = $this->controller->update($request);

        $this->assertSame(200, $response->get_status());

        $data = $response->get_data();
        $this->assertSame('Luke Skywalker (Jedi Master)', $data['canonical_name']);
        $this->assertSame(100, $data['importance_score']);
    }

    public function test_update_returns_404_for_nonexistent_entity(): void
    {
        wp_set_current_user($this->adminUserId);

        $request = new WP_REST_Request('PUT', '/saga/v1/entities/999999');
        $request->set_param('id', 999999);
        $request->set_param('canonical_name', 'Test');

        $response = $this->controller->update($request);

        $this->assertSame(404, $response->get_status());
    }

    public function test_update_only_modifies_provided_fields(): void
    {
        wp_set_current_user($this->adminUserId);

        $lukeId = SagaFixtures::getEntityId('luke');

        // Only update importance_score
        $request = new WP_REST_Request('PUT', "/saga/v1/entities/{$lukeId}");
        $request->set_param('id', $lukeId);
        $request->set_param('importance_score', 99);

        $response = $this->controller->update($request);
        $data = $response->get_data();

        // Name and slug should be unchanged
        $this->assertSame('Luke Skywalker', $data['canonical_name']);
        $this->assertSame('luke-skywalker', $data['slug']);
        $this->assertSame(99, $data['importance_score']);
    }

    // =========================================================================
    // DELETE ENDPOINT TESTS
    // =========================================================================

    public function test_delete_removes_entity(): void
    {
        wp_set_current_user($this->adminUserId);

        $lukeId = SagaFixtures::getEntityId('luke');

        $request = new WP_REST_Request('DELETE', "/saga/v1/entities/{$lukeId}");
        $request->set_param('id', $lukeId);

        $response = $this->controller->delete($request);

        $this->assertSame(200, $response->get_status());

        $data = $response->get_data();
        $this->assertSame('Entity deleted successfully', $data['message']);
        $this->assertSame($lukeId, $data['id']);

        // Verify entity is gone
        $this->assertFalse($this->repository->exists(
            new \SagaManager\Domain\Entity\EntityId($lukeId)
        ));
    }

    public function test_delete_returns_404_for_nonexistent_entity(): void
    {
        wp_set_current_user($this->adminUserId);

        $request = new WP_REST_Request('DELETE', '/saga/v1/entities/999999');
        $request->set_param('id', 999999);

        $response = $this->controller->delete($request);

        $this->assertSame(404, $response->get_status());
    }

    // =========================================================================
    // PERMISSION TESTS
    // =========================================================================

    public function test_read_permission_allows_subscribers(): void
    {
        $subscriberId = self::factory()->user->create(['role' => 'subscriber']);
        wp_set_current_user($subscriberId);

        $this->assertTrue($this->controller->checkReadPermission());
    }

    public function test_write_permission_requires_edit_posts_capability(): void
    {
        // Subscriber cannot edit posts
        $subscriberId = self::factory()->user->create(['role' => 'subscriber']);
        wp_set_current_user($subscriberId);

        $this->assertFalse($this->controller->checkWritePermission());

        // Editor can edit posts
        $editorId = self::factory()->user->create(['role' => 'editor']);
        wp_set_current_user($editorId);

        $this->assertTrue($this->controller->checkWritePermission());
    }

    public function test_read_permission_denies_logged_out_users(): void
    {
        wp_set_current_user(0);

        $this->assertFalse($this->controller->checkReadPermission());
    }

    // =========================================================================
    // RESPONSE FORMAT TESTS
    // =========================================================================

    public function test_entity_response_contains_all_fields(): void
    {
        wp_set_current_user($this->adminUserId);

        $lukeId = SagaFixtures::getEntityId('luke');

        $request = new WP_REST_Request('GET', "/saga/v1/entities/{$lukeId}");
        $request->set_param('id', $lukeId);

        $response = $this->controller->show($request);
        $data = $response->get_data();

        $expectedFields = [
            'id',
            'saga_id',
            'type',
            'type_label',
            'canonical_name',
            'slug',
            'importance_score',
            'wp_post_id',
            'created_at',
            'updated_at',
        ];

        foreach ($expectedFields as $field) {
            $this->assertArrayHasKey($field, $data, "Missing field: {$field}");
        }
    }

    public function test_error_response_format(): void
    {
        wp_set_current_user($this->adminUserId);

        $request = new WP_REST_Request('GET', '/saga/v1/entities/999999');
        $request->set_param('id', 999999);

        $response = $this->controller->show($request);
        $data = $response->get_data();

        $this->assertArrayHasKey('error', $data);
        $this->assertArrayHasKey('message', $data);
    }
}
