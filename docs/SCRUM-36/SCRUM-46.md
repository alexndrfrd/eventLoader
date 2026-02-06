# SCRUM-46: Redis Rate Limiter

**Priority:** High | **Points:** 3

Implement RedisRateLimiter to enforce 200ms interval across distributed instances.

## Implementation

```php
class RedisRateLimiter implements RateLimiterInterface
{
    private const KEY_PREFIX = 'loader:rate:';
    private const MIN_INTERVAL = 0.2; // 200ms

    public function __construct(private \Redis $redis) {}

    public function canRequest(string $sourceName): bool
    {
        $key = self::KEY_PREFIX . $sourceName;
        $lastRequest = $this->redis->get($key);

        if ($lastRequest === false) {
            return true; // No previous request
        }

        $elapsed = microtime(true) - (float) $lastRequest;
        return $elapsed >= self::MIN_INTERVAL;
    }

    public function recordRequest(string $sourceName): void
    {
        $key = self::KEY_PREFIX . $sourceName;
        $this->redis->set($key, microtime(true), ['EX' => 1]); // TTL 1 second
    }
}
```

## Tests
- Rate limit enforcement
- 200ms interval validation
- Atomic operations
- Redis failures (fallback)
