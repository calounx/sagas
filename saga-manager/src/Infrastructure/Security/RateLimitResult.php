<?php
declare(strict_types=1);

namespace SagaManager\Infrastructure\Security;

/**
 * Rate Limit Result Value Object
 *
 * Immutable object containing rate limit check results.
 * Provides metadata for HTTP headers and error responses.
 */
readonly class RateLimitResult
{
    /**
     * @param bool $exceeded Whether the rate limit was exceeded
     * @param int $limit Maximum requests allowed per time window
     * @param int $remaining Number of requests remaining in current window
     * @param int $resetAt Unix timestamp when the rate limit resets
     * @param int $currentCount Current request count in this window
     * @param int|null $retryAfter Seconds to wait before retrying (only set if exceeded)
     */
    public function __construct(
        public bool $exceeded,
        public int $limit,
        public int $remaining,
        public int $resetAt,
        public int $currentCount = 0,
        public ?int $retryAfter = null
    ) {
    }

    /**
     * Check if rate limit was exceeded
     */
    public function isExceeded(): bool
    {
        return $this->exceeded;
    }

    /**
     * Get retry-after value in seconds
     */
    public function getRetryAfter(): int
    {
        return $this->retryAfter ?? 0;
    }

    /**
     * Get HTTP headers for rate limit information
     *
     * @return array<string, string>
     */
    public function getHttpHeaders(): array
    {
        $headers = [
            'X-RateLimit-Limit' => (string) $this->limit,
            'X-RateLimit-Remaining' => (string) $this->remaining,
            'X-RateLimit-Reset' => (string) $this->resetAt,
        ];

        if ($this->exceeded && $this->retryAfter !== null) {
            $headers['Retry-After'] = (string) $this->retryAfter;
        }

        return $headers;
    }

    /**
     * Get error message for rate limit exceeded
     */
    public function getErrorMessage(): string
    {
        if (!$this->exceeded) {
            return '';
        }

        $retryAfter = $this->retryAfter ?? 60;

        return sprintf(
            'Rate limit exceeded. Please try again in %d seconds.',
            $retryAfter
        );
    }

    /**
     * Convert to array for JSON serialization
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'exceeded' => $this->exceeded,
            'limit' => $this->limit,
            'remaining' => $this->remaining,
            'reset_at' => $this->resetAt,
            'current_count' => $this->currentCount,
            'retry_after' => $this->retryAfter,
        ];
    }
}
