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
        parent::setUp();

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

        // Clean custom tables
        $tables = [
            $wpdb->prefix . 'saga_consistency_issues',
            $wpdb->prefix . 'saga_extraction_jobs',
            $wpdb->prefix . 'saga_extracted_entities',
            $wpdb->prefix . 'saga_extraction_duplicates',
            $wpdb->prefix . 'saga_relationship_suggestions',
            $wpdb->prefix . 'saga_suggestion_features',
            $wpdb->prefix . 'saga_suggestion_feedback',
            $wpdb->prefix . 'saga_learning_weights',
        ];

        foreach ($tables as $table) {
            $wpdb->query("DELETE FROM {$table}");
        }

        // Reset auto-increment
        foreach ($tables as $table) {
            $wpdb->query("ALTER TABLE {$table} AUTO_INCREMENT = 1");
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
