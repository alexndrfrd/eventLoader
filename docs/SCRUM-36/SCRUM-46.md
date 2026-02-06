# SCRUM-46: Redis Rate Limiter

**Priority:** High | **Points:** 3 | **Epic:** SCRUM-36

## Story
Implement RedisRateLimiter to enforce 200ms interval across distributed instances.

## Implementation
```php
class RedisRateLimiter implements RateLimiterInterface {
    private const KEY_PREFIX = 'loader:rate:';
    private const MIN_INTERVAL = 0.2; // 200ms in seconds

    public function canRequest(string $sourceName): bool {
        $key = self::KEY_PREFIX . $sourceName;
        $lastRequest = $this->redis->get($key);

        if ($lastRequest === false) {
            return true; // No previous request
        }

        $elapsed = microtime(true) - (float) $lastRequest;
        return $elapsed >= self::MIN_INTERVAL;
    }

    public function recordRequest(string $sourceName): void {
        $key = self::KEY_PREFIX . $sourceName;
        $this->redis->set($key, microtime(true), ['EX' => 1]); // TTL 1 second
    }
}
```

## Redis Pattern
- **Key:** `loader:rate:{sourceName}`
- **Value:** Last request timestamp (microtime)
- **TTL:** 1 second (cleanup old data)

## How it Works
1. Check last request time from Redis
2. Calculate elapsed time
3. Allow request if elapsed >= 200ms
4. Record new timestamp after request

## Edge Cases
- Redis unavailable → Fallback to InMemoryRateLimiter
- Clock skew → Use Redis server time (TIME command)
- Concurrent checks → Atomic operations prevent race conditions

## Tests
- Enforce 200ms interval
- Multiple instances respect limit
- Redis failure fallback
- Atomic operations

## Links
Jira: https://alexandrubesleaga92.atlassian.net/browse/SCRUM-46
