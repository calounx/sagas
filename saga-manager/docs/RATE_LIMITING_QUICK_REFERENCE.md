# Rate Limiting Quick Reference

## Apply Rate Limiting to Controller

```php
use SagaManager\Presentation\API\RateLimitMiddleware;

class MyController
{
    use RateLimitMiddleware;

    public function create(\WP_REST_Request $request): \WP_REST_Response
    {
        $rateLimitCheck = $this->checkRateLimit($request, 'entity_create');
        if ($rateLimitCheck instanceof \WP_REST_Response) {
            return $rateLimitCheck; // 429 response
        }

        // Your logic here...
    }
}
```

## Default Limits

```php
'entity_create' => 10,   // requests per minute
'entity_update' => 20,
'entity_delete' => 5,
'entity_search' => 30,
'default'       => 15
```

## Common Filter Patterns

### Change Limit for Action

```php
add_filter('saga_rate_limit_entity_create', fn($limit) => 50);
```

### Admin Gets Higher Limits

```php
add_filter('saga_rate_limit_entity_create', function($limit) {
    return current_user_can('manage_options') ? 100 : $limit;
});
```

### Disable in Development

```php
add_filter('saga_rate_limiting_enabled', function() {
    return wp_get_environment_type() !== 'development';
});
```

### Whitelist User

```php
add_filter('saga_rate_limit_whitelist_users', function($users) {
    $users[] = 123; // User ID
    return $users;
});
```

### Whitelist IP

```php
add_filter('saga_rate_limit_whitelist_ips', function($ips) {
    $ips[] = '10.0.1.100';
    return $ips;
});
```

### Bypass Action

```php
add_filter('saga_rate_limit_bypass_actions', function($actions) {
    $actions[] = 'entity_search';
    return $actions;
});
```

## Response Format

### 429 Too Many Requests

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

### HTTP Headers

```
X-RateLimit-Limit: 10
X-RateLimit-Remaining: 0
X-RateLimit-Reset: 1703980800
Retry-After: 45
```

## Manual Operations

### Reset Rate Limit

```php
$rateLimiter = new RateLimiter();
$rateLimiter->reset('entity_create', userId: 123);
```

### Check Current Count

```php
$count = $rateLimiter->getCurrentCount('entity_create', userId: 123);
```

### Check Limit

```php
$result = $rateLimiter->checkLimit('entity_create', userId: 123);

if ($result->isExceeded()) {
    echo "Retry after: " . $result->getRetryAfter() . " seconds";
}
```

## Testing

```bash
# Manual test with curl
for i in {1..15}; do
  curl -X POST "http://localhost/wp-json/saga/v1/entities" \
    -H "Content-Type: application/json" \
    -d '{"saga_id":1,"type":"character","canonical_name":"Test"}' \
    -u admin:password \
    -i
done
```

## Files Location

```
src/Infrastructure/Security/
  ├── RateLimiter.php          # Core service
  ├── RateLimitResult.php      # Result value object
  └── RateLimitConfig.php      # Configuration

src/Presentation/API/
  └── RateLimitMiddleware.php  # Controller trait

tests/Unit/Infrastructure/Security/
  ├── RateLimiterTest.php
  └── RateLimitResultTest.php

tests/Integration/Presentation/
  └── RateLimitMiddlewareTest.php

docs/
  └── RATE_LIMITING.md         # Full documentation

examples/
  └── rate-limit-configuration.php
```

## Monitoring

```php
// Log violations
add_action('saga_rate_limit_exceeded', function($action, $userId, $ip) {
    error_log("Rate limit exceeded: $action by user $userId from $ip");
}, 10, 3);
```
