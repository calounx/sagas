<?php
declare(strict_types=1);

namespace SagaManager\Tests\Unit\Infrastructure\Security;

use PHPUnit\Framework\TestCase;
use SagaManager\Infrastructure\Security\RateLimitResult;

/**
 * Unit Tests for RateLimitResult
 *
 * Tests the immutable result value object.
 */
class RateLimitResultTest extends TestCase
{
    /**
     * Test result object creation
     */
    public function test_result_object_creation(): void
    {
        $result = new RateLimitResult(
            exceeded: false,
            limit: 10,
            remaining: 5,
            resetAt: 1234567890,
            currentCount: 5
        );

        $this->assertFalse($result->exceeded);
        $this->assertEquals(10, $result->limit);
        $this->assertEquals(5, $result->remaining);
        $this->assertEquals(1234567890, $result->resetAt);
        $this->assertEquals(5, $result->currentCount);
        $this->assertNull($result->retryAfter);
    }

    /**
     * Test result with retry-after
     */
    public function test_result_with_retry_after(): void
    {
        $result = new RateLimitResult(
            exceeded: true,
            limit: 10,
            remaining: 0,
            resetAt: time() + 60,
            currentCount: 10,
            retryAfter: 60
        );

        $this->assertTrue($result->exceeded);
        $this->assertEquals(0, $result->remaining);
        $this->assertEquals(60, $result->retryAfter);
        $this->assertEquals(60, $result->getRetryAfter());
    }

    /**
     * Test isExceeded method
     */
    public function test_is_exceeded(): void
    {
        $notExceeded = new RateLimitResult(
            exceeded: false,
            limit: 10,
            remaining: 5,
            resetAt: time()
        );

        $exceeded = new RateLimitResult(
            exceeded: true,
            limit: 10,
            remaining: 0,
            resetAt: time(),
            retryAfter: 60
        );

        $this->assertFalse($notExceeded->isExceeded());
        $this->assertTrue($exceeded->isExceeded());
    }

    /**
     * Test getRetryAfter returns 0 when null
     */
    public function test_get_retry_after_returns_zero_when_null(): void
    {
        $result = new RateLimitResult(
            exceeded: false,
            limit: 10,
            remaining: 5,
            resetAt: time()
        );

        $this->assertEquals(0, $result->getRetryAfter());
    }

    /**
     * Test getHttpHeaders
     */
    public function test_get_http_headers(): void
    {
        $result = new RateLimitResult(
            exceeded: false,
            limit: 10,
            remaining: 5,
            resetAt: 1234567890
        );

        $headers = $result->getHttpHeaders();

        $this->assertIsArray($headers);
        $this->assertEquals('10', $headers['X-RateLimit-Limit']);
        $this->assertEquals('5', $headers['X-RateLimit-Remaining']);
        $this->assertEquals('1234567890', $headers['X-RateLimit-Reset']);
        $this->assertArrayNotHasKey('Retry-After', $headers);
    }

    /**
     * Test getHttpHeaders includes Retry-After when exceeded
     */
    public function test_get_http_headers_includes_retry_after(): void
    {
        $result = new RateLimitResult(
            exceeded: true,
            limit: 10,
            remaining: 0,
            resetAt: time() + 60,
            retryAfter: 60
        );

        $headers = $result->getHttpHeaders();

        $this->assertEquals('60', $headers['Retry-After']);
        $this->assertEquals('0', $headers['X-RateLimit-Remaining']);
    }

    /**
     * Test getErrorMessage
     */
    public function test_get_error_message(): void
    {
        $notExceeded = new RateLimitResult(
            exceeded: false,
            limit: 10,
            remaining: 5,
            resetAt: time()
        );

        $this->assertEquals('', $notExceeded->getErrorMessage());

        $exceeded = new RateLimitResult(
            exceeded: true,
            limit: 10,
            remaining: 0,
            resetAt: time() + 60,
            retryAfter: 60
        );

        $message = $exceeded->getErrorMessage();
        $this->assertStringContainsString('Rate limit exceeded', $message);
        $this->assertStringContainsString('60 seconds', $message);
    }

    /**
     * Test toArray method
     */
    public function test_to_array(): void
    {
        $result = new RateLimitResult(
            exceeded: true,
            limit: 10,
            remaining: 0,
            resetAt: 1234567890,
            currentCount: 10,
            retryAfter: 60
        );

        $array = $result->toArray();

        $this->assertIsArray($array);
        $this->assertTrue($array['exceeded']);
        $this->assertEquals(10, $array['limit']);
        $this->assertEquals(0, $array['remaining']);
        $this->assertEquals(1234567890, $array['reset_at']);
        $this->assertEquals(10, $array['current_count']);
        $this->assertEquals(60, $array['retry_after']);
    }

    /**
     * Test readonly properties cannot be modified
     */
    public function test_readonly_properties(): void
    {
        $result = new RateLimitResult(
            exceeded: false,
            limit: 10,
            remaining: 5,
            resetAt: time()
        );

        $this->expectException(\Error::class);
        $result->exceeded = true; // Should throw error (readonly)
    }
}
