# SCRUM-41: Comprehensive Testing

**Priority:** High | **Points:** 5 | **Epic:** SCRUM-36

## Story
Implement comprehensive test suite for distributed event loader.

## Test Categories

### Unit Tests (80%+ coverage)
- Value objects: EventId, EventName, EventDate
- Event entity
- Service mocks
- Interface compliance

### Integration Tests
- DoctrineEventRepository with real PostgreSQL
- RedisRateLimiter with real Redis
- RedisLoaderCoordination with real Redis
- HttpEventSource with mock HTTP server

### Functional Tests
```php
// Test: Multiple instances don't create duplicates
public function testMultipleInstancesNoDuplicates(): void {
    // Start 3 loader instances in parallel
    // Insert 1000 events from 5 sources
    // Verify:
    // - No duplicate events in DB
    // - All events processed exactly once
    // - Rate limits respected (200ms)
    // - Locks prevent conflicts
}

// Test: Performance
public function testPerformance(): void {
    // Load 10k events from 5 sources
    // 3 concurrent instances
    // Assert: completion in <3 minutes
    // Assert: 0 duplicates
}
```

## Docker Test Environment
```yaml
services:
  php:
    build: .
  db:
    image: postgres:15
  redis:
    image: redis:7
```

Run: `docker-compose exec php bin/phpunit --testdox`

## Links
Jira: https://alexandrubesleaga92.atlassian.net/browse/SCRUM-41
