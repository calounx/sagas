<?php
declare(strict_types=1);

namespace SagaManager\Tests\Integration\Presentation;

use SagaManager\Infrastructure\Security\RateLimiter;
use SagaManager\Presentation\API\RateLimitMiddleware;
use WP_REST_Request;
use WP_REST_Response;
use WP_UnitTestCase;

/**
 * Integration Tests for RateLimitMiddleware
 *
 * Tests middleware integration with WordPress REST API.
 */
class RateLimitMiddlewareTest extends WP_UnitTestCase
{
    use RateLimitMiddleware;

    private RateLimiter $rateLimiter;

    public function setUp(): void
    {
        parent::setUp();

        // Clear transients
        $this->clearAllSagaTransients();

        // Create rate limiter with custom limits for testing
        $this->rateLimiter = new RateLimiter([
            'test_action' => 3, // Very low limit for testing
        ]);

        $this->setRateLimiter($this->rateLimiter);
    }

    public function tearDown(): void
    {
        $this->clearAllSagaTransients();
        parent::tearDown();
    }

    private function clearAllSagaTransients(): void
    {
        global $wpdb;

        $wpdb->query(
            "DELETE FROM {$wpdb->options}
             WHERE option_name LIKE '_transient_saga_rate_%'
                OR option_name LIKE '_transient_timeout_saga_rate_%'"
        );

        wp_cache_flush();
    }

    /**
     * Test checkRateLimit allows request under limit
     */
    public function test_check_rate_limit_allows_under_limit(): void
    {
        // Create user and log in
        $userId = $this->factory->user->create();
        wp_set_current_user($userId);

        // Create mock request
        $request = new WP_REST_Request('POST', '/saga/v1/test');

        // First request should be allowed
        $result = $this->checkRateLimit($request, 'test_action');

        $this->assertTrue($result);
    }

    /**
     * Test checkRateLimit blocks request over limit
     */
    public function test_check_rate_limit_blocks_over_limit(): void
    {
        $userId = $this->factory->user->create();
        wp_set_current_user($userId);

        $request = new WP_REST_Request('POST', '/saga/v1/test');

        // Make 3 requests (at limit)
        for ($i = 0; $i < 3; $i++) {
            $result = $this->checkRateLimit($request, 'test_action');
            $this->assertTrue($result);
        }

        // 4th request should be blocked
        $result = $this->checkRateLimit($request, 'test_action');

        $this->assertInstanceOf(WP_REST_Response::class, $result);
        $this->assertEquals(429, $result->get_status());
    }

    /**
     * Test rate limit response contains error details
     */
    public function test_rate_limit_response_contains_error_details(): void
    {
        $userId = $this->factory->user->create();
        wp_set_current_user($userId);

        $request = new WP_REST_Request('POST', '/saga/v1/test');

        // Exceed rate limit
        for ($i = 0; $i < 4; $i++) {
            $result = $this->checkRateLimit($request, 'test_action');
        }

        $this->assertInstanceOf(WP_REST_Response::class, $result);

        $data = $result->get_data();
        $this->assertEquals('rate_limit_exceeded', $data['error']);
        $this->assertArrayHasKey('message', $data);
        $this->assertArrayHasKey('details', $data);
        $this->assertEquals(3, $data['details']['limit']);
        $this->assertEquals(0, $data['details']['remaining']);
    }

    /**
     * Test rate limit response includes HTTP headers
     */
    public function test_rate_limit_response_includes_headers(): void
    {
        $userId = $this->factory->user->create();
        wp_set_current_user($userId);

        $request = new WP_REST_Request('POST', '/saga/v1/test');

        // Exceed rate limit
        for ($i = 0; $i < 4; $i++) {
            $result = $this->checkRateLimit($request, 'test_action');
        }

        $this->assertInstanceOf(WP_REST_Response::class, $result);

        $headers = $result->get_headers();
        $this->assertArrayHasKey('X-RateLimit-Limit', $headers);
        $this->assertArrayHasKey('X-RateLimit-Remaining', $headers);
        $this->assertArrayHasKey('X-RateLimit-Reset', $headers);
        $this->assertArrayHasKey('Retry-After', $headers);

        $this->assertEquals('3', $headers['X-RateLimit-Limit']);
        $this->assertEquals('0', $headers['X-RateLimit-Remaining']);
    }

    /**
     * Test IP-based rate limiting for unauthenticated requests
     */
    public function test_ip_based_rate_limiting_for_unauthenticated(): void
    {
        // Ensure no user is logged in
        wp_set_current_user(0);

        // Mock IP address
        $_SERVER['REMOTE_ADDR'] = '192.168.1.100';

        $request = new WP_REST_Request('POST', '/saga/v1/test');

        // Make 3 requests (at limit)
        for ($i = 0; $i < 3; $i++) {
            $result = $this->checkRateLimit($request, 'test_action');
            $this->assertTrue($result);
        }

        // 4th request should be blocked
        $result = $this->checkRateLimit($request, 'test_action');
        $this->assertInstanceOf(WP_REST_Response::class, $result);
        $this->assertEquals(429, $result->get_status());

        // Cleanup
        unset($_SERVER['REMOTE_ADDR']);
    }

    /**
     * Test different users have independent rate limits
     */
    public function test_different_users_independent_limits(): void
    {
        $user1 = $this->factory->user->create();
        $user2 = $this->factory->user->create();

        $request = new WP_REST_Request('POST', '/saga/v1/test');

        // User 1 exceeds limit
        wp_set_current_user($user1);
        for ($i = 0; $i < 4; $i++) {
            $result = $this->checkRateLimit($request, 'test_action');
        }
        $this->assertInstanceOf(WP_REST_Response::class, $result);

        // User 2 should still be allowed
        wp_set_current_user($user2);
        $result = $this->checkRateLimit($request, 'test_action');
        $this->assertTrue($result);
    }

    /**
     * Test X-Forwarded-For header is used for IP detection
     */
    public function test_x_forwarded_for_header_detection(): void
    {
        wp_set_current_user(0);

        // Simulate proxy forwarding
        $_SERVER['HTTP_X_FORWARDED_FOR'] = '203.0.113.1, 10.0.0.1';
        $_SERVER['REMOTE_ADDR'] = '10.0.0.1';

        $request = new WP_REST_Request('POST', '/saga/v1/test');

        // Make 3 requests (at limit)
        for ($i = 0; $i < 3; $i++) {
            $result = $this->checkRateLimit($request, 'test_action');
            $this->assertTrue($result);
        }

        // 4th request should be blocked
        $result = $this->checkRateLimit($request, 'test_action');
        $this->assertInstanceOf(WP_REST_Response::class, $result);

        // Cleanup
        unset($_SERVER['HTTP_X_FORWARDED_FOR'], $_SERVER['REMOTE_ADDR']);
    }

    /**
     * Test Cloudflare IP header detection
     */
    public function test_cloudflare_ip_header_detection(): void
    {
        wp_set_current_user(0);

        // Simulate Cloudflare
        $_SERVER['HTTP_CF_CONNECTING_IP'] = '203.0.113.1';
        $_SERVER['REMOTE_ADDR'] = '172.68.1.1'; // Cloudflare IP

        $request = new WP_REST_Request('POST', '/saga/v1/test');

        // Make requests
        for ($i = 0; $i < 3; $i++) {
            $result = $this->checkRateLimit($request, 'test_action');
            $this->assertTrue($result);
        }

        // Should be blocked
        $result = $this->checkRateLimit($request, 'test_action');
        $this->assertInstanceOf(WP_REST_Response::class, $result);

        // Cleanup
        unset($_SERVER['HTTP_CF_CONNECTING_IP'], $_SERVER['REMOTE_ADDR']);
    }
}
