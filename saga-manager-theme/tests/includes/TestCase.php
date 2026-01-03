<?php
/**
 * Base Test Case for Saga Manager Theme
 *
 * Extends WP_UnitTestCase with additional utilities for theme testing.
 *
 * @package SagaManager
 * @subpackage Tests
 */

namespace SagaManager\Tests;

use WP_UnitTestCase;

/**
 * Base test case class
 */
abstract class TestCase extends WP_UnitTestCase
{
    use FactoryTrait;

    /**
     * Setup before each test
     */
    public function setUp(): void
    {
        // Handle PHPUnit 10 compatibility issue with WordPress test suite
        try {
            parent::setUp();
        } catch (\Error $e) {
            // Ignore parseTestMethodAnnotations error (PHPUnit 10 deprecation)
            if (strpos($e->getMessage(), 'parseTestMethodAnnotations') === false) {
                throw $e;
            }
        }

        // Clear caches
        wp_cache_flush();

        // Reset WordPress to clean state
        $this->clean_up_global_scope();
    }

    /**
     * Teardown after each test
     */
    public function tearDown(): void
    {
        // Clean up database
        $this->clean_database();

        // Clear caches
        wp_cache_flush();

        parent::tearDown();
    }

    /**
     * Clean up database tables
     */
    protected function clean_database(): void
    {
        global $wpdb;

        // Clean custom tables in correct order (respecting foreign key constraints)
        // Order: Child tables first, parent tables last
        // CASCADE dependencies:
        //   - saga_sagas (root parent)
        //   - saga_entities (depends on saga_sagas)
        //   - saga_summary_requests (depends on saga_sagas, saga_entities, users)
        //   - saga_extraction_jobs (depends on saga_sagas, users)
        //   - saga_relationship_suggestions (depends on saga_sagas, saga_entities)

        $tables = [
            // Level 4: Deepest children (depend on suggestions/requests/jobs)
            'saga_suggestion_features',        // → saga_relationship_suggestions
            'saga_suggestion_feedback',        // → saga_relationship_suggestions
            'saga_learning_weights',           // → saga_relationship_suggestions (via feedback)
            'saga_generated_summaries',        // → saga_summary_requests
            'saga_consistency_issues',         // → saga_sagas
            'saga_extraction_duplicates',      // → saga_extracted_entities

            // Level 3: Mid-level children (depend on jobs/entities/sagas)
            'saga_extracted_entities',         // → saga_extraction_jobs
            'saga_relationship_suggestions',   // → saga_sagas, saga_entities
            'saga_summary_requests',           // → saga_sagas, saga_entities, users

            // Level 2: Parent children (depend on saga_entities)
            'saga_extraction_jobs',            // → saga_sagas, users
            'saga_attribute_values',           // → saga_entities, saga_attribute_definitions
            'saga_content_fragments',          // → saga_entities
            'saga_quality_metrics',            // → saga_entities
            'saga_entity_relationships',       // → saga_entities
            'saga_timeline_events',            // → saga_sagas, saga_entities

            // Level 1: Direct children of saga_sagas
            'saga_attribute_definitions',      // No FK, but referenced by saga_attribute_values
            'saga_entities',                   // → saga_sagas

            // Level 0: Root parent (must be LAST)
            'saga_sagas',                      // Root parent table
        ];

        foreach ($tables as $table_name) {
            $table = $wpdb->prefix . $table_name;

            // Check if table exists before cleaning
            $exists = $wpdb->get_var("SHOW TABLES LIKE '{$table}'");
            if ($exists) {
                $wpdb->query("DELETE FROM {$table}");
                $wpdb->query("ALTER TABLE {$table} AUTO_INCREMENT = 1");
            }
        }
    }

    /**
     * Assert that a table exists
     *
     * @param string $table_name Table name (without prefix)
     */
    protected function assertTableExists(string $table_name): void
    {
        global $wpdb;

        $full_table_name = $wpdb->prefix . $table_name;
        $result = $wpdb->get_var("SHOW TABLES LIKE '{$full_table_name}'");

        $this->assertEquals(
            $full_table_name,
            $result,
            "Table {$full_table_name} should exist"
        );
    }

    /**
     * Assert that a table has specific columns
     *
     * @param string $table_name Table name (without prefix)
     * @param array $columns Expected column names
     */
    protected function assertTableHasColumns(string $table_name, array $columns): void
    {
        global $wpdb;

        $full_table_name = $wpdb->prefix . $table_name;
        $actual_columns = $wpdb->get_col("DESCRIBE {$full_table_name}");

        foreach ($columns as $column) {
            $this->assertContains(
                $column,
                $actual_columns,
                "Table {$full_table_name} should have column {$column}"
            );
        }
    }

    /**
     * Create a mock API response
     *
     * @param string $content Response content
     * @return string JSON response
     */
    protected function mockAIResponse(string $content): string
    {
        return json_encode([
            'choices' => [
                [
                    'message' => [
                        'content' => $content
                    ]
                ]
            ]
        ]);
    }

    /**
     * Create test user with specific role
     *
     * @param string $role User role
     * @return int User ID
     */
    protected function createTestUser(string $role = 'editor'): int
    {
        return $this->factory()->user->create([
            'role' => $role,
            'user_login' => 'testuser_' . uniqid(),
            'user_email' => 'test_' . uniqid() . '@example.com'
        ]);
    }

    /**
     * Set current user for testing
     *
     * @param int $user_id User ID
     */
    protected function actingAs(int $user_id): void
    {
        wp_set_current_user($user_id);
    }

    /**
     * Assert that AJAX request returns success
     *
     * @param string $action AJAX action
     * @param array $data Request data
     */
    protected function assertAjaxSuccess(string $action, array $data = []): void
    {
        $_POST = array_merge(['action' => $action], $data);
        $_REQUEST = $_POST;

        try {
            $this->_handleAjax($action);
        } catch (\WPAjaxDieContinueException $e) {
            // Expected - AJAX die continues
        }

        $response = json_decode($this->_last_response, true);

        $this->assertTrue(
            $response['success'] ?? false,
            'AJAX request should succeed. Response: ' . $this->_last_response
        );
    }

    /**
     * Assert that AJAX request returns error
     *
     * @param string $action AJAX action
     * @param array $data Request data
     */
    protected function assertAjaxError(string $action, array $data = []): void
    {
        $_POST = array_merge(['action' => $action], $data);
        $_REQUEST = $_POST;

        try {
            $this->_handleAjax($action);
        } catch (\WPAjaxDieStopException $e) {
            // Expected - AJAX die stops
        }

        $response = json_decode($this->_last_response, true);

        $this->assertFalse(
            $response['success'] ?? true,
            'AJAX request should fail'
        );
    }

    /**
     * Create a nonce for testing
     *
     * @param string $action Nonce action
     * @return string Nonce value
     */
    protected function createNonce(string $action): string
    {
        return wp_create_nonce($action);
    }

    /**
     * Assert array has structure
     *
     * @param array $expected Expected keys
     * @param array $actual Actual array
     */
    protected function assertArrayStructure(array $expected, array $actual): void
    {
        foreach ($expected as $key) {
            $this->assertArrayHasKey(
                $key,
                $actual,
                "Array should have key: {$key}"
            );
        }
    }
}
