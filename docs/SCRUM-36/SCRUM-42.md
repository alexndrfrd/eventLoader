# SCRUM-42: Distributed Coordination via Redis

**Priority:** High | **Points:** 5 | **Epic:** SCRUM-36

## Story
Implement RedisLoaderCoordination for distributed locks and shared state.

## Implementation
```php
class RedisLoaderCoordination implements LoaderCoordinationInterface {
    private const LOCK_PREFIX = 'loader:lock:';
    private const STATE_PREFIX = 'loader:last_id:';

    public function acquireLock(string $sourceName, int $ttl): bool {
        $key = self::LOCK_PREFIX . $sourceName;
        // SET key value NX EX ttl
        return $this->redis->set($key, $this->instanceId, ['NX', 'EX' => $ttl]) === true;
    }

    public function releaseLock(string $sourceName): void {
        $key = self::LOCK_PREFIX . $sourceName;
        if ($this->redis->get($key) === $this->instanceId) {
            $this->redis->del($key);
        }
    }

    public function getLastProcessedId(string $sourceName): ?EventId {
        $key = self::STATE_PREFIX . $sourceName;
        $value = $this->redis->get($key);
        return $value !== false ? new EventId((int) $value) : null;
    }

    public function updateLastProcessedId(string $sourceName, EventId $eventId): void {
        $key = self::STATE_PREFIX . $sourceName;
        $this->redis->set($key, $eventId->getValue());
    }
}
```

## Redis Patterns
- **Lock:** `loader:lock:{sourceName}` → TTL 30s
- **State:** `loader:last_id:{sourceName}` → Persistent
- **Atomic:** Use SET NX EX for lock acquisition

## Edge Cases
- Instance crashes → Lock auto-expires (TTL)
- Redis unavailable → Return false, skip source
- Lock stolen → Detect owner before release

## Tests
- Lock acquisition/release
- TTL expiration
- Concurrent lock attempts
- State persistence across instances

## Links
Jira: https://alexandrubesleaga92.atlassian.net/browse/SCRUM-42
