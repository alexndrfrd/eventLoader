# SCRUM-42: Distributed Coordination via Redis

**Priority:** High | **Points:** 5

Implement RedisLoaderCoordination for distributed locks and shared state management.

## Key Implementation

```php
class RedisLoaderCoordination implements LoaderCoordinationInterface
{
    private const LOCK_PREFIX = 'loader:lock:';
    private const STATE_PREFIX = 'loader:last_id:';

    public function __construct(
        private \Redis $redis,
        private string $instanceId
    ) {}

    public function acquireLock(string $sourceName, int $ttl): bool
    {
        $key = self::LOCK_PREFIX . $sourceName;
        return $this->redis->set($key, $this->instanceId, ['NX', 'EX' => $ttl]) === true;
    }

    public function releaseLock(string $sourceName): void
    {
        $key = self::LOCK_PREFIX . $sourceName;
        $owner = $this->redis->get($key);
        
        if ($owner === $this->instanceId) {
            $this->redis->del($key);
        }
    }

    public function getLastProcessedId(string $sourceName): ?EventId
    {
        $key = self::STATE_PREFIX . $sourceName;
        $value = $this->redis->get($key);
        
        return $value !== false ? new EventId((int) $value) : null;
    }

    public function updateLastProcessedId(string $sourceName, EventId $eventId): void
    {
        $key = self::STATE_PREFIX . $sourceName;
        $this->redis->set($key, $eventId->getValue());
    }
}
```

## Edge Cases
- Instance crashes while holding lock → TTL expires, lock auto-released
- Redis unavailable → Return false, skip source
- Lock stolen → Detect and gracefully stop

## Tests
- Lock acquisition/release
- TTL expiration
- Concurrent lock attempts
- State persistence across instances
