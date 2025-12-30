<?php
declare(strict_types=1);

namespace SagaManager\Tests\Integration\Infrastructure\Security;

use SagaManager\Infrastructure\Security\RateLimiter;
use SagaManager\Infrastructure\Security\RateLimitResult;
use WP_UnitTestCase;

/**
 * Unit Tests for RateLimiter
 *
 * Tests rate limiting functionality using WordPress transients.
 */
class RateLimiterTest extends WP_UnitTestCase
{
    private RateLimiter $rateLimiter;

    public function setUp(): void
    {
        parent::setUp();

        // Clear all transients before each test
        $this->clearAllSagaTransients();

        // Create fresh rate limiter
        $this->rateLimiter = new RateLimiter();
    }

    public function tearDown(): void
    {
        $this->clearAllSagaTransients();
        parent::tearDown();
    }

    /**
     * Clear all saga rate limit transients
     */
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
     * Test first request is allowed
     */
    public function test_first_request_is_allowed(): void
    {
        $result = $this->rateLimiter->checkLimit('entity_create', userId: 1);

        $this->assertInstanceOf(RateLimitResult::class, $result);
        $this->assertFalse($result->isExceeded());
        $this->assertEquals(1, $result->currentCount);
        $this->assertEquals(10, $result->limit); // Default limit for entity_create
        $this->assertEquals(9, $result->remaining);
    }

    /**
     * Test multiple requests under limit are allowed
     */
    public function test_requests_under_limit_are_allowed(): void
    {
        $userId = 1;

        // Make 5 requests (under the limit of 10)
        for ($i = 0; $i < 5; $i++) {
            $result = $this->rateLimiter->checkLimit('entity_create', userId: $userId);
            $this->assertFalse($result->isExceeded());
            $this->assertEquals($i + 1, $result->currentCount);
        }

        // Verify the 5th request shows correct remaining count
        $result = $this->rateLimiter->checkLimit('entity_create', userId: $userId);
        $this->assertFalse($result->isExceeded());
        $this->assertEquals(6, $result->currentCount);
        $this->assertEquals(4, $result->remaining);
    }

    /**
     * Test rate limit is exceeded when limit is reached
     */
    public function test_rate_limit_exceeded_when_limit_reached(): void
    {
        $userId = 1;

        // Make requests up to the limit (10)
        for ($i = 0; $i < 10; $i++) {
            $result = $this->rateLimiter->checkLimit('entity_create', userId: $userId);
            $this->assertFalse($result->isExceeded());
        }

        // 11th request should be rate limited
        $result = $this->rateLimiter->checkLimit('entity_create', userId: $userId);
        $this->assertTrue($result->isExceeded());
        $this->assertEquals(10, $result->currentCount);
        $this->assertEquals(0, $result->remaining);
        $this->assertGreaterThan(0, $result->getRetryAfter());
    }

    /**
     * Test rate limiting by IP address
     */
    public function test_rate_limiting_by_ip_address(): void
    {
        $ipAddress = '192.168.1.100';

        // Make requests up to limit
        for ($i = 0; $i < 10; $i++) {
            $result = $this->rateLimiter->checkLimit('entity_create', ipAddress: $ipAddress);
            $this->assertFalse($result->isExceeded());
        }

        // Next request should be rate limited
        $result = $this->rateLimiter->checkLimit('entity_create', ipAddress: $ipAddress);
        $this->assertTrue($result->isExceeded());
    }

    /**
     * Test different users have separate rate limits
     */
    public function test_different_users_have_separate_limits(): void
    {
        // User 1 makes 10 requests
        for ($i = 0; $i < 10; $i++) {
            $result = $this->rateLimiter->checkLimit('entity_create', userId: 1);
            $this->assertFalse($result->isExceeded());
        }

        // User 1 is rate limited
        $result = $this->rateLimiter->checkLimit('entity_create', userId: 1);
        $this->assertTrue($result->isExceeded());

        // User 2 should still be allowed
        $result = $this->rateLimiter->checkLimit('entity_create', userId: 2);
        $this->assertFalse($result->isExceeded());
        $this->assertEquals(1, $result->currentCount);
    }

    /**
     * Test different actions have separate rate limits
     */
    public function test_different_actions_have_separate_limits(): void
    {
        $userId = 1;

        // Make 10 entity_create requests (at limit)
        for ($i = 0; $i < 10; $i++) {
            $result = $this->rateLimiter->checkLimit('entity_create', userId: $userId);
            $this->assertFalse($result->isExceeded());
        }

        // entity_create is rate limited
        $result = $this->rateLimiter->checkLimit('entity_create', userId: $userId);
        $this->assertTrue($result->isExceeded());

        // entity_update should still be allowed (separate limit)
        $result = $this->rateLimiter->checkLimit('entity_update', userId: $userId);
        $this->assertFalse($result->isExceeded());
        $this->assertEquals(1, $result->currentCount);
        $this->assertEquals(20, $result->limit); // entity_update has limit of 20
    }

    /**
     * Test custom rate limits via constructor
     */
    public function test_custom_rate_limits_via_constructor(): void
    {
        $rateLimiter = new RateLimiter([
            'entity_create' => 5, // Custom limit of 5
        ]);

        // Make 5 requests
        for ($i = 0; $i < 5; $i++) {
            $result = $rateLimiter->checkLimit('entity_create', userId: 1);
            $this->assertFalse($result->isExceeded());
        }

        // 6th request should be rate limited
        $result = $rateLimiter->checkLimit('entity_create', userId: 1);
        $this->assertTrue($result->isExceeded());
    }

    /**
     * Test reset method clears rate limit
     */
    public function test_reset_clears_rate_limit(): void
    {
        $userId = 1;

        // Hit rate limit
        for ($i = 0; $i < 10; $i++) {
            $this->rateLimiter->checkLimit('entity_create', userId: $userId);
        }

        $result = $this->rateLimiter->checkLimit('entity_create', userId: $userId);
        $this->assertTrue($result->isExceeded());

        // Reset rate limit
        $reset = $this->rateLimiter->reset('entity_create', userId: $userId);
        $this->assertTrue($reset);

        // Should be allowed again
        $result = $this->rateLimiter->checkLimit('entity_create', userId: $userId);
        $this->assertFalse($result->isExceeded());
        $this->assertEquals(1, $result->currentCount);
    }

    /**
     * Test getCurrentCount method
     */
    public function test_get_current_count(): void
    {
        $userId = 1;

        // Initial count should be 0
        $count = $this->rateLimiter->getCurrentCount('entity_create', userId: $userId);
        $this->assertEquals(0, $count);

        // Make 3 requests
        for ($i = 0; $i < 3; $i++) {
            $this->rateLimiter->checkLimit('entity_create', userId: $userId);
        }

        // Count should be 3
        $count = $this->rateLimiter->getCurrentCount('entity_create', userId: $userId);
        $this->assertEquals(3, $count);
    }

    /**
     * Test rate limit result contains correct metadata
     */
    public function test_rate_limit_result_metadata(): void
    {
        $result = $this->rateLimiter->checkLimit('entity_create', userId: 1);

        $this->assertFalse($result->exceeded);
        $this->assertEquals(10, $result->limit);
        $this->assertEquals(9, $result->remaining);
        $this->assertGreaterThan(time(), $result->resetAt);
        $this->assertLessThanOrEqual(time() + MINUTE_IN_SECONDS, $result->resetAt);
        $this->assertEquals(1, $result->currentCount);
    }

    /**
     * Test rate limit exceeded includes retry-after
     */
    public function test_rate_limit_exceeded_includes_retry_after(): void
    {
        $userId = 1;

        // Hit rate limit
        for ($i = 0; $i < 11; $i++) {
            $result = $this->rateLimiter->checkLimit('entity_create', userId: $userId);
        }

        $this->assertTrue($result->isExceeded());
        $this->assertNotNull($result->retryAfter);
        $this->assertGreaterThan(0, $result->retryAfter);
        $this->assertLessThanOrEqual(MINUTE_IN_SECONDS, $result->retryAfter);
    }

    /**
     * Test IP address sanitization
     */
    public function test_invalid_ip_address_handling(): void
    {
        // Invalid IP should be ignored
        $result = $this->rateLimiter->checkLimit('entity_create', ipAddress: 'invalid-ip');

        $this->assertFalse($result->isExceeded());
        // Should allow since no valid identifier
    }

    /**
     * Test user ID takes precedence over IP address
     */
    public function test_user_id_takes_precedence_over_ip(): void
    {
        $userId = 1;
        $ipAddress = '192.168.1.100';

        // Make requests with both user ID and IP
        for ($i = 0; $i < 5; $i++) {
            $this->rateLimiter->checkLimit('entity_create', userId: $userId, ipAddress: $ipAddress);
        }

        // Check count for user (should be 5)
        $count = $this->rateLimiter->getCurrentCount('entity_create', userId: $userId);
        $this->assertEquals(5, $count);

        // Check count for IP (should be 0 - wasn't used)
        $count = $this->rateLimiter->getCurrentCount('entity_create', ipAddress: $ipAddress);
        $this->assertEquals(0, $count);
    }

    /**
     * Test different action limits
     */
    public function test_different_action_limits(): void
    {
        $userId = 1;

        // entity_create: limit 10
        $result = $this->rateLimiter->checkLimit('entity_create', userId: $userId);
        $this->assertEquals(10, $result->limit);

        // entity_update: limit 20
        $result = $this->rateLimiter->checkLimit('entity_update', userId: $userId);
        $this->assertEquals(20, $result->limit);

        // entity_delete: limit 5
        $result = $this->rateLimiter->checkLimit('entity_delete', userId: $userId);
        $this->assertEquals(5, $result->limit);

        // entity_search: limit 30
        $result = $this->rateLimiter->checkLimit('entity_search', userId: $userId);
        $this->assertEquals(30, $result->limit);
    }

    /**
     * Test unknown action uses default limit
     */
    public function test_unknown_action_uses_default_limit(): void
    {
        $result = $this->rateLimiter->checkLimit('unknown_action', userId: 1);

        $this->assertEquals(15, $result->limit); // Default limit
    }
}
