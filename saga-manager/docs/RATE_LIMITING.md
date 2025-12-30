# Rate Limiting System

## Overview

The Saga Manager plugin implements a comprehensive rate limiting system to protect the REST API from abuse and ensure fair resource usage. The system uses WordPress transients for storage and supports both user-based and IP-based rate limiting.

## Architecture

The rate limiting system follows hexagonal architecture principles:

- **Infrastructure Layer**: `RateLimiter`, `RateLimitResult`, `RateLimitConfig` (uses WordPress transients)
- **Presentation Layer**: `RateLimitMiddleware` trait (REST API integration)
- **Domain Layer**: No rate limiting concerns (pure business logic)

## Components

### 1. RateLimiter Service

**Location**: `/src/Infrastructure/Security/RateLimiter.php`

Core service that implements rate limiting logic using WordPress transients.

```php
use SagaManager\Infrastructure\Security\RateLimiter;

$rateLimiter = new RateLimiter();

// Check rate limit for a user
$result = $rateLimiter->checkLimit('entity_create', userId: 123);

if ($result->isExceeded()) {
    // Rate limit exceeded
    echo "Retry after: " . $result->getRetryAfter() . " seconds";
}
```

### 2. RateLimitResult Value Object

**Location**: `/src/Infrastructure/Security/RateLimitResult.php`

Immutable object containing rate limit check results.

```php
// Properties
$result->exceeded;      // bool: Was rate limit exceeded?
$result->limit;         // int: Maximum requests per minute
$result->remaining;     // int: Requests remaining in window
$result->resetAt;       // int: Unix timestamp when limit resets
$result->currentCount;  // int: Current request count
$result->retryAfter;    // ?int: Seconds to wait before retry

// Methods
$result->isExceeded();           // Check if exceeded
$result->getRetryAfter();        // Get retry-after in seconds
$result->getHttpHeaders();       // Get HTTP headers array
$result->getErrorMessage();      // Get user-friendly error message
$result->toArray();              // Convert to array
```

### 3. RateLimitMiddleware Trait

**Location**: `/src/Presentation/API/RateLimitMiddleware.php`

Trait that controllers can use to apply rate limiting.

```php
use SagaManager\Presentation\API\RateLimitMiddleware;

class MyController
{
    use RateLimitMiddleware;

    public function create(\WP_REST_Request $request): \WP_REST_Response
    {
        // Check rate limit
        $rateLimitCheck = $this->checkRateLimit($request, 'entity_create');
        if ($rateLimitCheck instanceof \WP_REST_Response) {
            return $rateLimitCheck; // Rate limit exceeded - return 429
        }

        // Continue with normal logic...
    }
}
```

### 4. RateLimitConfig

**Location**: `/src/Infrastructure/Security/RateLimitConfig.php`

Configuration management with WordPress filter integration.

```php
// Get all rate limits
$limits = RateLimitConfig::getLimits();

// Get specific limit
$limit = RateLimitConfig::getLimit('entity_create');

// Check if rate limiting is enabled
if (RateLimitConfig::isEnabled()) {
    // Rate limiting active
}

// Check whitelists
$isWhitelisted = RateLimitConfig::isUserWhitelisted($userId);
$isWhitelisted = RateLimitConfig::isIPWhitelisted($ipAddress);
```

## Default Rate Limits

| Action | Limit (requests/minute) |
|--------|------------------------|
| `entity_create` | 10 |
| `entity_update` | 20 |
| `entity_delete` | 5 |
| `entity_search` | 30 |
| `default` | 15 |

## Configuration via WordPress Filters

### Customize Individual Action Limit

```php
// Increase create limit for admins
add_filter('saga_rate_limit_entity_create', function(int $limit, string $action): int {
    if (current_user_can('manage_options')) {
        return 50; // Admins get higher limit
    }
    return $limit;
}, 10, 2);
```

### Customize All Limits at Once

```php
// Adjust all limits for production
add_filter('saga_rate_limits', function(array $limits): array {
    $limits['entity_create'] = 5;
    $limits['entity_update'] = 10;
    $limits['entity_delete'] = 2;
    return $limits;
});
```

### Disable Rate Limiting

```php
// Disable in development environment
add_filter('saga_rate_limiting_enabled', function(bool $enabled): bool {
    return wp_get_environment_type() !== 'development';
});
```

### Bypass Rate Limiting for Specific Actions

```php
// Don't rate limit search operations
add_filter('saga_rate_limit_bypass_actions', function(array $actions): array {
    $actions[] = 'entity_search';
    return $actions;
});
```

### Whitelist Users

```php
// Whitelist all administrators
add_filter('saga_rate_limit_whitelist_users', function(array $userIds): array {
    $admins = get_users(['role' => 'administrator', 'fields' => 'ID']);
    return array_merge($userIds, $admins);
});
```

### Whitelist IP Addresses

```php
// Whitelist internal API servers
add_filter('saga_rate_limit_whitelist_ips', function(array $ips): array {
    $ips[] = '10.0.1.100';
    $ips[] = '10.0.1.101';
    return $ips;
});
```

## HTTP Response

### Successful Request (Under Limit)

**Status**: `200 OK`

```json
{
  "id": 123,
  "canonical_name": "Luke Skywalker"
}
```

**Headers**:
```
X-RateLimit-Limit: 10
X-RateLimit-Remaining: 5
X-RateLimit-Reset: 1703980800
```

### Rate Limited Request

**Status**: `429 Too Many Requests`

```json
{
  "error": "rate_limit_exceeded",
  "message": "Rate limit exceeded. Please try again in 45 seconds.",
  "details": {
    "limit": 10,
    "remaining": 0,
    "reset_at": 1703980800,
    "retry_after": 45
  }
}
```

**Headers**:
```
X-RateLimit-Limit: 10
X-RateLimit-Remaining: 0
X-RateLimit-Reset: 1703980800
Retry-After: 45
```

## Rate Limiting Strategy

### User-Based vs IP-Based

1. **Authenticated Requests**: Rate limited by user ID
2. **Unauthenticated Requests**: Rate limited by IP address
3. **If both available**: User ID takes precedence

### IP Address Detection

The system checks headers in this order:

1. `HTTP_CF_CONNECTING_IP` (Cloudflare)
2. `HTTP_X_FORWARDED_FOR` (Standard proxy)
3. `HTTP_X_REAL_IP` (Nginx)
4. `HTTP_CLIENT_IP` (Alternative)
5. `REMOTE_ADDR` (Direct connection)

### Time Window

- **Window Duration**: 1 minute (60 seconds)
- **Storage**: WordPress transients (auto-expire)
- **Reset**: Automatic after window expires

## Advanced Usage

### Custom Limits in Constructor

```php
$rateLimiter = new RateLimiter([
    'entity_create' => 5,
    'entity_update' => 10,
]);
```

### Manual Reset

```php
// Reset rate limit for a user
$rateLimiter->reset('entity_create', userId: 123);

// Reset rate limit for an IP
$rateLimiter->reset('entity_create', ipAddress: '192.168.1.100');
```

### Check Current Count

```php
$count = $rateLimiter->getCurrentCount('entity_create', userId: 123);
echo "Current requests: $count";
```

### Add Rate Limit Headers to Successful Response

```php
public function create(\WP_REST_Request $request): \WP_REST_Response
{
    $rateLimitCheck = $this->checkRateLimit($request, 'entity_create');
    if ($rateLimitCheck instanceof \WP_REST_Response) {
        return $rateLimitCheck;
    }

    // ... create entity logic ...

    $response = new \WP_REST_Response($data, 201);

    // Optionally add rate limit headers to successful response
    // (This requires storing the RateLimitResult from checkRateLimit)

    return $response;
}
```

## Testing

### Unit Tests

Run unit tests for the rate limiter:

```bash
phpunit tests/Unit/Infrastructure/Security/RateLimiterTest.php
```

### Integration Tests

Run integration tests for the middleware:

```bash
phpunit tests/Integration/Presentation/RateLimitMiddlewareTest.php
```

### Manual Testing

```bash
# Test rate limiting with curl
for i in {1..15}; do
  curl -X POST "http://localhost/wp-json/saga/v1/entities" \
    -H "Content-Type: application/json" \
    -d '{"saga_id":1,"type":"character","canonical_name":"Test"}' \
    -u admin:password \
    -i
done
```

## Performance Considerations

### Storage

- Uses WordPress transients (stored in `wp_options` table)
- Transients auto-expire after 1 minute
- No manual cleanup required
- Object cache compatible (Redis, Memcached)

### Database Impact

- 2 database queries per rate limit check:
  1. `get_transient()` - read current count
  2. `set_transient()` - update count

- Consider using persistent object cache for high-traffic sites

### Scaling

For high-traffic sites:

1. **Enable Object Cache**: Use Redis or Memcached
2. **Whitelist Trusted IPs**: Reduce checks for internal services
3. **Bypass Low-Risk Actions**: Don't rate limit read operations
4. **Adjust Limits**: Tune based on server capacity

## Security Considerations

### SQL Injection

- All queries use `$wpdb->prepare()`
- User input sanitized with `sanitize_key()`, `filter_var()`

### IP Spoofing

- IP validation with `FILTER_VALIDATE_IP`
- Filters out private/reserved IPs in production
- Cloudflare and proxy headers supported

### DoS Protection

- Transient-based storage prevents memory exhaustion
- Automatic expiration prevents storage bloat
- Rate limits protect API endpoints

## Troubleshooting

### Rate Limits Not Working

1. Check if rate limiting is enabled:
   ```php
   var_dump(RateLimitConfig::isEnabled());
   ```

2. Check if action is bypassed:
   ```php
   var_dump(RateLimitConfig::shouldBypass('entity_create'));
   ```

3. Check if user is whitelisted:
   ```php
   var_dump(RateLimitConfig::isUserWhitelisted(get_current_user_id()));
   ```

### Transients Not Persisting

1. Verify WordPress transient API is working:
   ```php
   set_transient('test_key', 'test_value', 60);
   var_dump(get_transient('test_key'));
   ```

2. Check database permissions
3. Clear object cache: `wp_cache_flush()`

### False Positives

1. Check IP detection:
   ```php
   // Add to controller for debugging
   error_log('Detected IP: ' . $this->getClientIpAddress($request));
   ```

2. Verify different users aren't sharing IPs (NAT)
3. Consider whitelisting the affected IP range

## Monitoring

### Log Rate Limit Violations

```php
// Monitor rate limit violations
add_action('saga_rate_limit_exceeded', function(string $action, $userId, ?string $ip) {
    error_log(sprintf(
        '[RATE_LIMIT] Exceeded: action=%s, user=%s, ip=%s',
        $action,
        $userId ?? 'anonymous',
        $ip ?? 'unknown'
    ));
}, 10, 3);
```

### Track Metrics

```php
// Track daily violations per user
add_action('saga_rate_limit_exceeded', function(string $action, $userId, ?string $ip) {
    if ($userId === null) {
        return;
    }

    $count = get_user_meta($userId, 'saga_rate_violations_today', true) ?: 0;
    update_user_meta($userId, 'saga_rate_violations_today', $count + 1);
}, 10, 3);
```

## Examples

See `/examples/rate-limit-configuration.php` for complete configuration examples.

## API Reference

### RateLimiter

```php
checkLimit(string $action, ?int $userId, ?string $ipAddress): RateLimitResult
reset(string $action, ?int $userId, ?string $ipAddress): bool
getCurrentCount(string $action, ?int $userId, ?string $ipAddress): int
```

### RateLimitResult

```php
isExceeded(): bool
getRetryAfter(): int
getHttpHeaders(): array
getErrorMessage(): string
toArray(): array
```

### RateLimitConfig

```php
static getLimits(): array
static getLimit(string $action): int
static isEnabled(): bool
static shouldBypass(string $action): bool
static isUserWhitelisted(int $userId): bool
static isIPWhitelisted(string $ipAddress): bool
```

### RateLimitMiddleware

```php
setRateLimiter(RateLimiter $rateLimiter): void
checkRateLimit(\WP_REST_Request $request, string $action): bool|\WP_REST_Response
addRateLimitHeaders(\WP_REST_Response $response, RateLimitResult $result): \WP_REST_Response
```
